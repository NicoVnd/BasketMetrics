<?php

namespace App\Service\Statistics;

use App\Entity\{Team,Player,MatchGame,PlayerMatchStat};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class SeasonStatistics
{
    public const SORTS=['points'=>'Points / match','totalPoints'=>'Total de points','minutes'=>'Temps de jeu / match','per40'=>'Points pour 40 minutes'];
    public function __construct(private EntityManagerInterface $em,private SeasonCalendar $calendar,private StatisticsCalculator $calculator) {}

    public function load(Team $team,?string $selected=null,string $sort='points'): array
    {
        if (!isset(self::SORTS[$sort])) { throw new BadRequestHttpException('Classement inconnu.'); }
        $dates=$this->em->createQueryBuilder()->select('m.playedAt')->from(MatchGame::class,'m')->where('m.team = :team')->setParameter('team',$team)->getQuery()->getResult();
        $years=[]; foreach ($dates as $date) { $years[$this->calendar->year($date['playedAt'])]=true; }
        $current=$this->calendar->currentYear();
        if ($selected!==null && (!preg_match('/^\d{4}$/D',$selected) || (int)$selected<1900 || (int)$selected>2200)) { throw new BadRequestHttpException('Saison invalide.'); }
        // Prefer an actual season when the current season has no fixtures yet.
        $year=$selected!==null ? (int)$selected : (isset($years[$current]) || !$years ? $current : max(array_keys($years)));
        $years[$current]=true;
        $years[$year]=true; krsort($years); $options=[];
        foreach (array_keys($years) as $y) { $options[$y]=$this->calendar->label($y); }
        [$start,$end]=$this->calendar->bounds($year);
        $games=$this->em->createQueryBuilder()->select('m')->from(MatchGame::class,'m')->where('m.team = :team')->andWhere('m.playedAt >= :start')->andWhere('m.playedAt < :end')->setParameter('team',$team)->setParameter('start',$start)->setParameter('end',$end)->orderBy('m.playedAt','ASC')->addOrderBy('m.id','ASC')->getQuery()->getResult();
        $players=$this->em->getRepository(Player::class)->findBy(['team'=>$team],['lastName'=>'ASC','firstName'=>'ASC','id'=>'ASC']);
        $stats=$this->em->createQueryBuilder()->select('s','p','m')->from(PlayerMatchStat::class,'s')->join('s.player','p')->join('s.matchGame','m')
            ->where('m.team = :team')->andWhere('p.team = :team')->andWhere('m.playedAt >= :start')->andWhere('m.playedAt < :end')
            ->setParameter('team',$team)->setParameter('start',$start)->setParameter('end',$end)->getQuery()->getResult();
        $summary=$this->calculator->summarize($games,$stats,$players);
        return ['year'=>$year,'label'=>$this->calendar->label($year),'seasons'=>$options,'start'=>$start,'end'=>$end,
            'summary'=>$summary,'ranking'=>$this->calculator->ranking($summary['players'],$sort),'sort'=>$sort,'sorts'=>self::SORTS,'stats'=>$stats,'players'=>$players];
    }

    public function profile(Player $player,array $season): array
    {
        $byGame=[];
        foreach ($season['stats'] as $stat) { if ($stat->getPlayer()->getId()===$player->getId()) { $byGame[$stat->getMatchGame()->getId()]=$stat; } }
        $stats=$this->calculator->player($player,array_values($byGame));
        $history=[];
        foreach (array_reverse($season['summary']['games']) as $game) { $history[]=['game'=>$game,'stat'=>$byGame[$game->getId()]??null]; }
        return ['numbers'=>$stats,'history'=>$history];
    }
}
