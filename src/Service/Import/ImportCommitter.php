<?php

namespace App\Service\Import;

use App\Entity\{ImportJob, Team, Player, MatchGame, PlayerMatchStat, PeriodScore};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ImportCommitter
{
    public function __construct(private EntityManagerInterface $em, private IdentityNormalizer $normalizer, private PlayerMatcher $matcher, private ValidatorInterface $validator, private ImportConsistencyChecker $checker) {}

    public function commit(ImportJob $job, array $data): MatchGame
    {
        try {
            return $this->em->wrapInTransaction(function() use($job,$data): MatchGame {
                $this->em->refresh($job,LockMode::PESSIMISTIC_WRITE);
                if ($job->getStatus()==='complete') { return $job->getMatchGame(); }
                if ($job->getStatus()!=='pending') { throw new ImportException('Cet import n’est plus disponible.'); }
                if (!($data['acknowledge']??false)) { throw new ImportException('La vérification doit être confirmée.'); }
                $this->em->refresh($job->getTeam(),LockMode::PESSIMISTIC_WRITE);
                $team=$job->getTeam(); $payload=$job->getPayload();
                /** @var MatchGame $draft */
                $draft=$data['game'];
                $errors=$this->validator->validate($draft);
                if (count($errors)) { throw new ImportException('Les informations du match sont invalides.'); }
                $key=$this->importKey($job);
                $duplicate=$this->em->getRepository(MatchGame::class)->findOneBy(['team'=>$team,'importKey'=>$key]);
                if ($duplicate) { throw new ImportException('Cette rencontre FFBB a déjà été importée. Ouvrez le match existant depuis votre équipe.'); }
                $game=$draft;
                if (!empty($data['existingMatch'])) {
                    $game=$this->em->find(MatchGame::class,(int)$data['existingMatch']);
                    if (!$game || $game->getTeam()->getId()!==$team->getId()) { throw new ImportException('Le match sélectionné n’appartient pas à votre équipe.'); }
                    if ($game->getImportKey()!==null) { throw new ImportException('Ce match a déjà un import validé. Modifiez-le depuis sa fiche.'); }
                    $game->setOpponent($draft->getOpponent())->setPlayedAt($draft->getPlayedAt())->setHome($draft->isHome())->setStatus($draft->getStatus())->setTeamScore($draft->getTeamScore())->setOpponentScore($draft->getOpponentScore());
                } else {
                    // A same-day manual encounter is never silently duplicated.
                    foreach ($this->em->getRepository(MatchGame::class)->findBy(['team'=>$team]) as $existing) {
                        if ($existing->getPlayedAt()->format('Y-m-d')===$draft->getPlayedAt()->format('Y-m-d')) {
                            $a=$this->normalizer->name($existing->getOpponent()); $b=$this->normalizer->name($draft->getOpponent());
                            if (strlen($a)>=6 && strlen($b)>=6 && (str_contains($a,$b)||str_contains($b,$a))) { throw new ImportException('Un match contre cet adversaire existe à cette date. Sélectionnez-le dans « Destination du match » pour le compléter.'); }
                        }
                    }
                }
                $game->setTeam($team)->setImportKey($key)->setExternalReference($data['reference']?:null);
                $players=$this->em->getRepository(Player::class)->findBy(['team'=>$team]);
                $used=[]; $newIdentities=[]; $count=0;
                $this->em->persist($game);
                foreach ($data['players'] as $row) {
                    if (!$row['include']) { continue; } ++$count;
                    $license=$this->normalizer->license($row['licenseNumber']);
                    $row['licenseNumber']=$license;
                    $id=$row['existingPlayer'];
                    if ($id==='__choose__') { throw new ImportException('Choisissez le joueur correspondant à '.$row['firstName'].' '.$row['lastName'].'.'); }
                    $player=null;
                    if ($id!=='__new__') {
                        $player=$this->em->find(Player::class,(int)$id);
                        if (!$player || $player->getTeam()->getId()!==$team->getId()) { throw new ImportException('Un joueur sélectionné n’appartient pas à l’équipe.'); }
                    } else {
                        $match=$this->matcher->match($row,$players);
                        if ($match['id']!==null || ($match['needsChoice']??false)) { throw new ImportException('Un joueur identique ou proche existe déjà : sélectionnez-le pour '.$row['firstName'].' '.$row['lastName'].'.'); }
                        $identity=$license??($this->normalizer->name($row['lastName']).'|'.$this->normalizer->name($row['firstName']));
                        if (isset($newIdentities[$identity])) { throw new ImportException('Le même nouveau joueur apparaît deux fois dans l’import.'); }
                        $newIdentities[$identity]=true;
                        $player=(new Player())->setTeam($team)->setFirstName($row['firstName'])->setLastName($row['lastName'])->setJerseyNumber($row['jerseyNumber']);
                    }
                    $unique=$player->getId()??('new:'.spl_object_id($player));
                    if (isset($used[$unique])) { throw new ImportException('Deux lignes correspondent au même joueur. Corrigez le rapprochement.'); }
                    $used[$unique]=true;
                    if ($license!==null && $player->getLicenseNumber()!==null && $player->getLicenseNumber()!==$license) { throw new ImportException('La licence de '.$player->getFullName().' diffère de celle du document. Vérifiez l’identité.'); }
                    if ($license!==null) {
                        foreach ($players as $other) { if ($other->getId()!==$player->getId() && $other->getLicenseNumber()===$license) { throw new ImportException('Cette licence appartient déjà à un autre joueur de l’équipe.'); } }
                        $player->setLicenseNumber($license);
                    }
                    $this->em->persist($player);
                    $stat=$player->getId() && $game->getId()?$this->em->getRepository(PlayerMatchStat::class)->findOneBy(['matchGame'=>$game,'player'=>$player]):null;
                    $stat??=(new PlayerMatchStat())->setMatchGame($game)->setPlayer($player);
                    foreach (['participation','points','secondsPlayed','fouls','twoPtMade','threePtMade','freeThrowsMade','fieldGoalsMade','twoPtInsideMade','twoPtOutsideMade','jerseyNumber','starter','captain'] as $field) {
                        $stat->{'set'.ucfirst($field)}($row[$field]??null);
                    }
                    if (count($this->validator->validate($player)) || count($this->validator->validate($stat))) { throw new ImportException('Les données d’un joueur sont invalides. Corrigez-les avant validation.'); }
                    $this->em->persist($stat);
                }
                if ($count===0) { throw new ImportException('Conservez au moins un joueur dans l’import.'); }
                foreach ($data['periods'] as $index=>$row) {
                    $number=$payload['periods'][$index]['number'];
                    $period=$game->getId()?$this->em->getRepository(PeriodScore::class)->findOneBy(['matchGame'=>$game,'periodNumber'=>$number]):null;
                    $period??=(new PeriodScore())->setMatchGame($game)->setPeriodNumber($number);
                    $period->setTeamPoints($row[$game->isHome()?'homePoints':'awayPoints'])->setOpponentPoints($row[$game->isHome()?'awayPoints':'homePoints']);
                    $this->em->persist($period);
                }
                $metadata=['jobId'=>$job->getId(),'parserVersion'=>$payload['version'],'sourceHash'=>$job->getSourceHash(),
                    'competition'=>$data['competition'],'venue'=>$data['venue'],'pool'=>$payload['pool'],'officials'=>$payload['officials'],
                    'homeName'=>$payload['homeName'],'awayName'=>$payload['awayName'],'metrics'=>$payload['metrics'],'teamDetails'=>$payload['teamDetails']??[],
                    'opponentPlayers'=>$payload['players'][$payload['side']==='home'?'away':'home'],
                    'warnings'=>$this->checker->readable(array_values(array_unique(array_merge($payload['warnings'],$this->checker->warnings($game,$data['players'],$data['periods']))))),
                    'confirmedAt'=>(new \DateTimeImmutable())->format(DATE_ATOM),'confirmedBy'=>$team->getOwner()->getId()];
                $game->setImportMetadata($metadata);
                $payload['review']=['players'=>$data['players'],'periods'=>$data['periods'],'reference'=>$data['reference'],'competition'=>$data['competition'],'venue'=>$data['venue']];
                $job->setPayload($payload)->setStatus('complete')->setMatchGame($game);
                $this->em->flush();
                return $game;
            });
        } catch (UniqueConstraintViolationException $error) {
            throw new ImportException('Ce match ou une licence vient déjà d’être enregistré. Rechargez l’équipe avant de réessayer.',previous:$error);
        }
    }

    public function importKey(ImportJob $job): string
    {
        $p=$job->getPayload();
        $date=$p['playedAt']?new \DateTimeImmutable($p['playedAt']):null;
        $source=$p['reference']?($p['reference'].'|'.($date?->format('Y')??'unknown').'|'.$this->normalizer->name($p['competition']).'|'.$p['pool'])
            :($p['playedAt'].'|'.$this->normalizer->name($p['homeName']).'|'.$this->normalizer->name($p['awayName']));
        return hash('sha256',$source);
    }
}
