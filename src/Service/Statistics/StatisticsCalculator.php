<?php

namespace App\Service\Statistics;

use App\Entity\{MatchGame,Player,PlayerMatchStat};

/** Pure calculations shared by team summaries, rankings and individual profiles. */
final class StatisticsCalculator
{
    private const METRICS=['points','secondsPlayed','fouls','twoPtMade','threePtMade','freeThrowsMade'];
    private function key(object $entity): string { return $entity->getId()!==null?'id:'.$entity->getId():'new:'.spl_object_id($entity); }

    public function summarize(array $games, array $stats, array $players): array
    {
        $unique=[];
        foreach ($games as $game) { $unique[$this->key($game)]=$game; }
        $games=array_values($unique);
        usort($games,fn($a,$b)=>($a->getPlayedAt()<=>$b->getPlayedAt())?:strcmp($this->key($a),$this->key($b)));
        $finished=array_filter($games,fn($g)=>$g->getStatus()==='finished');
        $wins=$losses=$draws=0; $for=[]; $against=[]; $differences=[]; $results=[];
        $splits=['home'=>['wins'=>0,'losses'=>0,'draws'=>0],'away'=>['wins'=>0,'losses'=>0,'draws'=>0]];
        foreach ($finished as $game) {
            if ($game->getTeamScore()!==null) { $for[]=$game->getTeamScore(); }
            if ($game->getOpponentScore()!==null) { $against[]=$game->getOpponentScore(); }
            if ($game->getTeamScore()===null || $game->getOpponentScore()===null) { continue; }
            $diff=$game->getTeamScore()-$game->getOpponentScore(); $differences[]=$diff;
            $result=$diff>0?'wins':($diff<0?'losses':'draws');
            if ($result==='wins') { ++$wins; } elseif ($result==='losses') { ++$losses; } else { ++$draws; }
            ++$splits[$game->isHome()?'home':'away'][$result];
            $results[]=['game'=>$game,'result'=>$diff>0?'V':($diff<0?'D':'N'),'difference'=>$diff];
        }
        $byPlayer=[]; $uniqueStats=[];
        foreach ($stats as $stat) {
            $game=$stat->getMatchGame(); $gKey=$this->key($game);
            if (!isset($unique[$gKey]) || $game->getStatus()!=='finished') { continue; }
            $pKey=$this->key($stat->getPlayer()); $key=$gKey.'|'.$pKey;
            if (isset($uniqueStats[$key])) { continue; }
            $uniqueStats[$key]=true; $byPlayer[$pKey][]=$stat;
        }
        $rows=[]; $seenPlayers=[];
        foreach ($players as $player) {
            $pKey=$this->key($player);
            if (isset($seenPlayers[$pKey])) { continue; } $seenPlayers[$pKey]=true;
            $rows[]=$this->player($player,$byPlayer[$pKey]??[]);
        }
        return ['games'=>$games,'finished'=>count($finished),'scheduled'=>count($games)-count($finished),
            'wins'=>$wins,'losses'=>$losses,'draws'=>$draws,'scoredGames'=>count($results),
            'winRate'=>count($results)>0?100*$wins/count($results):null,
            'pointsFor'=>$this->metric($for),'pointsAgainst'=>$this->metric($against),'difference'=>$this->metric($differences),
            'splits'=>$splits,'results'=>$results,'form'=>array_slice($results,-5),'players'=>$rows];
    }

    public function player(Player $player,array $stats): array
    {
        $values=array_fill_keys(self::METRICS,[]); $played=0; $dnp=0; $unknown=0; $listed=0;
        $pairedPoints=0; $pairedSeconds=0; $pairedGames=0; $best=null; $seen=[];
        foreach ($stats as $stat) {
            if ($stat->getPlayer()!==$player && $this->key($stat->getPlayer())!==$this->key($player)) { continue; }
            if ($stat->getMatchGame()->getStatus()!=='finished') { continue; }
            $key=$this->key($stat->getMatchGame()); if (isset($seen[$key])) { continue; } $seen[$key]=true; ++$listed;
            $status=$stat->getEffectiveParticipation();
            if ($status==='did_not_play') { ++$dnp; continue; }
            if ($status!=='played') { ++$unknown; continue; } ++$played;
            foreach (self::METRICS as $metric) { $v=$stat->{'get'.ucfirst($metric)}(); if ($v!==null) { $values[$metric][]=$v; } }
            if ($stat->getPoints()!==null && ($best===null || $stat->getPoints()>$best['points'])) { $best=['points'=>$stat->getPoints(),'game'=>$stat->getMatchGame()]; }
            if ($stat->getPoints()!==null && $stat->getSecondsPlayed()!==null && $stat->getSecondsPlayed()>0) {
                $pairedPoints+=$stat->getPoints(); $pairedSeconds+=$stat->getSecondsPlayed(); ++$pairedGames;
            }
        }
        $metrics=[];
        foreach ($values as $key=>$v) { $metrics[$key]=$this->metric($v); }
        return ['player'=>$player,'played'=>$played,'listed'=>$listed,'didNotPlay'=>$dnp,'unknown'=>$unknown,'metrics'=>$metrics,
            'pointsPer40'=>$pairedSeconds>0?$pairedPoints*2400/$pairedSeconds:null,'per40Games'=>$pairedGames,'best'=>$best];
    }

    public function ranking(array $rows,string $sort): array
    {
        $value=static fn(array $r)=>match($sort) {
            'totalPoints'=>$r['metrics']['points']['total'], 'minutes'=>$r['metrics']['secondsPlayed']['average'],
            'per40'=>$r['pointsPer40'], default=>$r['metrics']['points']['average'],
        };
        usort($rows,static function($a,$b) use($value) {
            $av=$value($a); $bv=$value($b);
            if ($av===null || $bv===null) { $cmp=($av===null)<=>($bv===null); } else { $cmp=$bv<=>$av; }
            return $cmp?:strnatcasecmp($a['player']->getFullName(),$b['player']->getFullName())?:($a['player']->getId()<=>$b['player']->getId());
        });
        $rank=0; $previous=null;
        foreach ($rows as $index=>&$row) {
            $v=$value($row);
            if ($v===null) { $row['rank']=null; continue; }
            if ($previous===null || abs($v-$previous)>1e-9) { $rank=$index+1; }
            $row['rank']=$rank; $previous=$v;
        }
        unset($row); return $rows;
    }

    private function metric(array $values): array
    {
        $count=count($values); $total=$count>0?array_sum($values):null;
        return ['total'=>$total,'average'=>$count>0?$total/$count:null,'count'=>$count];
    }
}
