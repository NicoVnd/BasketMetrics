<?php

namespace App\Service\Import;

use App\Entity\MatchGame;

final class ImportConsistencyChecker
{
    public function readable(array $warnings): array
    {
        return array_map(static fn(string $message)=>strtr($message,[
            '« points »'=>'« points »','« fouls »'=>'« fautes »','« twoPtMade »'=>'« tirs à 2 points réussis »',
            '« threePtMade »'=>'« tirs à 3 points réussis »','« freeThrowsMade »'=>'« lancers francs réussis »',
            'pour home.'=>'pour les locaux.','pour away.'=>'pour les visiteurs.',
        ]),$warnings);
    }

    public function warnings(MatchGame $game, array $players, array $periods): array
    {
        $warnings=[]; $points=0; $seconds=0; $knownPoints=true; $knownSeconds=true;
        foreach ($players as $row) {
            if (!($row['include']??true)) { continue; }
            if ($row['points']===null) { $knownPoints=false; } else { $points+=$row['points']; }
            if ($row['secondsPlayed']===null) { $knownSeconds=false; } else { $seconds+=$row['secondsPlayed']; }
            if ($row['points']!==null && $row['twoPtMade']!==null && $row['threePtMade']!==null && $row['freeThrowsMade']!==null
                && $row['points']!==2*$row['twoPtMade']+3*$row['threePtMade']+$row['freeThrowsMade']) {
                $warnings[]=$row['lastName'].' '.$row['firstName'].' : total des points différent des paniers renseignés.';
            }
            if (($row['twoPtInsideMade']??null)!==null && ($row['twoPtOutsideMade']??null)!==null && $row['twoPtMade']!==null
                && $row['twoPtMade']!==$row['twoPtInsideMade']+$row['twoPtOutsideMade']) {
                $warnings[]=$row['lastName'].' : les 2 points intérieurs et extérieurs ne correspondent pas au total à 2 points.';
            }
        }
        if ($game->getTeamScore()!==null && $points!==$game->getTeamScore()) { $warnings[]='Points individuels renseignés : '.$points.' ; score de l’équipe : '.$game->getTeamScore().'.'.(!$knownPoints?' Certaines valeurs sont inconnues.':''); }
        if (!$knownPoints) { $warnings[]='Des points individuels restent inconnus : les moyennes futures devront en tenir compte.'; }
        if ($knownSeconds && $seconds>0 && $periods && count($periods)===4 && $seconds!==200*60) { $warnings[]='Le cumul des temps de jeu ne correspond pas à 200 minutes pour quatre quarts-temps de 10 minutes. Vérifiez le document ; aucune durée n’a été corrigée.'; }
        foreach (['homePoints','awayPoints'] as $field) {
            $values=array_column($periods,$field);
            if ($periods && count(array_filter($values,fn($v)=>$v!==null))===count($periods)) {
                $expected=($field==='homePoints')===$game->isHome()?$game->getTeamScore():$game->getOpponentScore();
                if ($expected!==null && array_sum($values)!==$expected) { $warnings[]='Somme des périodes différente du score final pour '.($field==='homePoints'?'les locaux':'les visiteurs').'.'; }
            } elseif ($periods) { $warnings[]='Des scores par période restent illisibles ou absents.'; }
        }
        return array_values(array_unique($warnings));
    }
}
