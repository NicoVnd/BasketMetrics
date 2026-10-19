<?php

namespace App\Service\Import;

use App\Entity\{ImportJob, Player, MatchGame};
use Doctrine\ORM\EntityManagerInterface;

final class ImportReviewBuilder
{
    public function __construct(private EntityManagerInterface $em, private PlayerMatcher $matcher, private IdentityNormalizer $normalizer) {}

    public function build(ImportJob $job): array
    {
        $payload=$job->getPayload(); $home=$payload['side']==='home';
        $players=$this->em->getRepository(Player::class)->findBy(['team'=>$job->getTeam()],['lastName'=>'ASC','firstName'=>'ASC']);
        $rows=$payload['players'][$payload['side']];
        foreach ($rows as &$row) {
            $row['participation']??='unknown';
            $match=$this->matcher->match($row,$players);
            $row['existingPlayer']=$match['id']===null?(($match['needsChoice']??false)?'__choose__':'__new__'):(string)$match['id']; $row['matchReason']=$match['reason'];
        }
        unset($row);
        $game=(new MatchGame())->setTeam($job->getTeam())->setOpponent($home?$payload['awayName']:$payload['homeName'])
            ->setHome($home)->setPlayedAt($payload['playedAt']?new \DateTimeImmutable($payload['playedAt']):null)
            ->setStatus('finished')->setTeamScore($payload['scores'][$payload['side']])->setOpponentScore($payload['scores'][$home?'away':'home']);
        $choices=[];
        foreach ($players as $player) { $choices[$player->getFullName().' · '.($player->getLicenseNumber()??'sans licence').' · n°'.($player->getJerseyNumber()??'—').' (ID '.$player->getId().')']=(string)$player->getId(); }
        $games=$this->em->getRepository(MatchGame::class)->findBy(['team'=>$job->getTeam()],['playedAt'=>'DESC']);
        $gameChoices=[]; $suggested='';
        foreach ($games as $existing) {
            if (!$game->getPlayedAt() || $existing->getPlayedAt()->format('Y-m-d')!==$game->getPlayedAt()->format('Y-m-d')) { continue; }
            $gameChoices[$existing->getOpponent().' · '.$existing->getPlayedAt()->setTimezone(new \DateTimeZone('Europe/Paris'))->format('d/m/Y H:i').' (ID '.$existing->getId().')']=(string)$existing->getId();
            $a=$this->normalizer->name($existing->getOpponent()); $b=$this->normalizer->name($game->getOpponent());
            if (strlen($a)>=6 && (str_contains($a,$b)||str_contains($b,$a))) { $suggested=(string)$existing->getId(); }
        }
        return ['data'=>['game'=>$game,'players'=>$rows,'periods'=>$payload['periods'],'existingMatch'=>$suggested,
            'reference'=>$payload['reference'],'competition'=>$payload['competition'],'venue'=>$payload['venue'],'acknowledge'=>false],
            'player_choices'=>$choices,'game_choices'=>$gameChoices];
    }
}
