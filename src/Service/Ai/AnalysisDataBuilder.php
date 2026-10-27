<?php

namespace App\Service\Ai;

use App\Entity\{Team, Player, MatchGame, PlayerMatchStat, PeriodScore};
use App\Service\Statistics\{SeasonStatistics, SeasonCalendar, StatisticsCalculator};
use Doctrine\ORM\EntityManagerInterface;

final class AnalysisDataBuilder
{
    public function __construct(private EntityManagerInterface $em, private SeasonStatistics $seasons, private SeasonCalendar $calendar, private StatisticsCalculator $calculator) {}

    public function build(Team $team, string $scope, int $target): array
    {
        $game=$scope==='match'?$this->em->find(MatchGame::class,$target):null;
        $player=$scope==='player'?$this->em->find(Player::class,$target):null;
        if (($scope==='match' && (!$game || $game->getTeam()->getId()!==$team->getId())) || ($scope==='player' && (!$player || $player->getTeam()->getId()!==$team->getId()))) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }
        if ($game && $game->getStatus()!=='finished') { throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('L’analyse est disponible après la fin du match.'); }
        $selected=$scope==='season'?(string)$target:($game?(string)$this->calendar->year($game->getPlayedAt()):null);
        // Player season is passed separately by the controller through buildPlayer().
        return $this->assemble($team,$scope,$target,$selected,$game,$player);
    }

    public function buildPlayer(Team $team, Player $player, ?string $year): array
    {
        return $this->assemble($team,'player',$player->getId(),$year,null,$player);
    }

    private function assemble(Team $team,string $scope,int $target,?string $year,?MatchGame $targetGame,?Player $targetPlayer): array
    {
        $season=$this->seasons->load($team,$year);
        // Match reports receive only this match, including all calculated aggregates.
        $games=array_values(array_filter($season['summary']['games'], fn($g)=>$g->getStatus()==='finished' && (!$targetGame || $g->getId()===$targetGame->getId())));
        $ids=array_map(fn($g)=>$g->getId(),$games);
        $stats=array_values(array_filter($season['stats'],fn($s)=>in_array($s->getMatchGame()->getId(),$ids,true)));
        $summary=$this->calculator->summarize($games,$stats,$season['players']);
        $collective=$summary;
        foreach (['games','results','form','players'] as $key) { unset($collective[$key]); }
        $players=[]; $labels=[]; $displayNames=[]; $firstNameCounts=[];
        foreach ($summary['players'] as $row) {
            $key=mb_strtolower(trim($row['player']->getFirstName()));
            $firstNameCounts[$key]=($firstNameCounts[$key]??0)+1;
        }
        foreach ($summary['players'] as $row) {
            $p=$row['player']; $alias='J'.$p->getId(); $labels[$alias]=$p->getFullName();
            $firstName=trim($p->getFirstName());
            $displayNames[$alias]=$firstNameCounts[mb_strtolower($firstName)]>1 ? $firstName.' ('.$alias.')' : $firstName;
            if ($targetPlayer && $p->getId()!==$targetPlayer->getId()) { continue; }
            unset($row['player']);
            if ($row['best']) { $row['best']['game']=$row['best']['game']->getId(); }
            $players[$alias]=$row;
        }
        $history=[]; $warnings=[];
        foreach ($games as $g) {
            $rows=[]; $knownPoints=[];
            foreach ($stats as $s) {
                if ($s->getMatchGame()->getId()!==$g->getId()) { continue; }
                if ($s->getPoints()!==null) { $knownPoints[]=$s->getPoints(); }
                if ($s->getWarnings()) { $warnings[]='Match '.$g->getId().' / J'.$s->getPlayer()->getId().' : incohérence entre points et paniers ou détail des paniers.'; }
                if ($targetPlayer && $s->getPlayer()->getId()!==$targetPlayer->getId()) { continue; }
                $row=['player'=>'J'.$s->getPlayer()->getId(),'participation'=>$s->getEffectiveParticipation()];
                foreach (['points','secondsPlayed','fouls','twoPtMade','threePtMade','freeThrowsMade','fieldGoalsMade','twoPtInsideMade','twoPtOutsideMade'] as $field) { $row[$field]=$s->{'get'.ucfirst($field)}(); }
                $rows[]=$row;
            }
            if ($knownPoints && $g->getTeamScore()!==null && array_sum($knownPoints)!==$g->getTeamScore()) { $warnings[]='Match '.$g->getId().' : somme des points individuels connus différente du score collectif (données éventuellement incomplètes).'; }
            $periods=[];
            foreach ($this->em->getRepository(PeriodScore::class)->findBy(['matchGame'=>$g],['periodNumber'=>'ASC']) as $p) { $periods[]=['number'=>$p->getPeriodNumber(),'teamPoints'=>$p->getTeamPoints(),'opponentPoints'=>$p->getOpponentPoints()]; }
            foreach (['teamPoints'=>'getTeamScore','opponentPoints'=>'getOpponentScore'] as $field=>$getter) {
                $values=array_column($periods,$field);
                if ($values && !in_array(null,$values,true) && $g->$getter()!==null && array_sum($values)!==$g->$getter()) { $warnings[]='Match '.$g->getId().' : total des périodes '.$field.' différent du score final.'; }
            }
            $history[]=['id'=>$g->getId(),'date'=>$g->getPlayedAt()->format(DATE_ATOM),'home'=>$g->isHome(),'teamScore'=>$g->getTeamScore(),'opponentScore'=>$g->getOpponentScore(),'periods'=>$periods,'players'=>$rows];
        }
        $data=['scope'=>$scope,'season'=>$season['label'],'target'=>$scope==='player'?'J'.$target:$target,'units'=>['secondsPlayed'=>'secondes','metrics'=>'total, moyenne sur valeurs connues et count de valeurs connues'],'collective'=>$collective,'players'=>$players,'history'=>$history,'warnings'=>$warnings];
        return ['data'=>$data,'hash'=>hash('sha256',json_encode($data,JSON_THROW_ON_ERROR)),'season'=>$season['year'],'labels'=>$labels,'displayNames'=>$displayNames,'hasData'=>count($games)>0,'title'=>$targetGame?'Analyse du match contre '.$targetGame->getOpponent():($targetPlayer?'Analyse de '.$targetPlayer->getFullName():'Analyse collective et individuelle de saison')];
    }
}
