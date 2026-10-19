<?php

namespace App\Service\Statistics;

use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

final class StatisticsCharts
{
    public function __construct(private ChartBuilderInterface $builder) {}
    private function chart(array $labels,array $datasets): Chart
    {
        $chart=$this->builder->createChart(Chart::TYPE_LINE);
        $chart->setData(['labels'=>$labels,'datasets'=>$datasets]);
        $chart->setOptions(['responsive'=>true,'maintainAspectRatio'=>false,'spanGaps'=>false,
            'plugins'=>['legend'=>['labels'=>['color'=>'#b6c2d1','usePointStyle'=>true]]],
            'scales'=>['x'=>['ticks'=>['color'=>'#9aa8b9','maxTicksLimit'=>7],'grid'=>['display'=>false]],
                'y'=>['beginAtZero'=>true,'ticks'=>['color'=>'#9aa8b9'],'grid'=>['color'=>'#303946']]]]);
        return $chart;
    }
    public function team(array $summary): ?Chart
    {
        $labels=$for=$against=[];
        foreach ($summary['games'] as $game) {
            if ($game->getStatus()!=='finished') { continue; }
            $labels[]=$game->getPlayedAt()->setTimezone(new \DateTimeZone('Europe/Paris'))->format('d/m').' · '.$game->getOpponent();
            $for[]=$game->getTeamScore(); $against[]=$game->getOpponentScore();
        }
        if (!$labels) { return null; }
        return $this->chart($labels,[['label'=>'Points marqués','data'=>$for,'borderColor'=>'#ff9b5c','backgroundColor'=>'#ff9b5c','tension'=>.25],
            ['label'=>'Points encaissés','data'=>$against,'borderColor'=>'#81a6be','backgroundColor'=>'#81a6be','tension'=>.25]]);
    }
    public function player(array $history): ?Chart
    {
        $labels=$points=[];
        foreach (array_reverse($history) as $row) {
            if ($row['game']->getStatus()!=='finished') { continue; }
            $labels[]=$row['game']->getPlayedAt()->setTimezone(new \DateTimeZone('Europe/Paris'))->format('d/m').' · '.$row['game']->getOpponent();
            $points[]=$row['stat']?->getEffectiveParticipation()==='played'?$row['stat']->getPoints():null;
        }
        return $labels?$this->chart($labels,[['label'=>'Points du joueur','data'=>$points,'borderColor'=>'#ff9b5c','backgroundColor'=>'#ff9b5c','tension'=>.25]]):null;
    }
}
