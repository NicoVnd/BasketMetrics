<?php
namespace App\Tests;

use App\Entity\{Player,MatchGame,PlayerMatchStat};
use App\Service\Statistics\{StatisticsCalculator,SeasonCalendar};
use App\Twig\StatisticsExtension;
use PHPUnit\Framework\TestCase;

final class StatisticsCalculatorTest extends TestCase
{
    private function game(string $status='finished',?int $for=80,?int $against=60): MatchGame
    { return (new MatchGame())->setOpponent('Adversaire')->setPlayedAt(new \DateTimeImmutable('2026-10-15'))->setStatus($status)->setTeamScore($for)->setOpponentScore($against); }
    private function stat(Player $p,MatchGame $g,?int $points=null,?int $seconds=null): PlayerMatchStat
    { return (new PlayerMatchStat())->setPlayer($p)->setMatchGame($g)->setPoints($points)->setSecondsPlayed($seconds); }

    public function testRecordScoresAndDuplicateInputs(): void
    {
        $calc=new StatisticsCalculator(); $win=$this->game(); $loss=$this->game('finished',50,70)->setHome(false); $draw=$this->game('finished',60,60);
        $scheduled=$this->game('scheduled',999,0); $incomplete=$this->game('finished',null,null);
        $p=(new Player())->setFirstName('Alex'); $s=$this->stat($p,$win,20,1200);
        $r=$calc->summarize([$win,$win,$loss,$draw,$scheduled,$incomplete],[$s,$s],[$p,$p]);
        self::assertSame(4,$r['finished']); self::assertSame(1,$r['scheduled']);
        self::assertSame(1,$r['wins']); self::assertSame(1,$r['losses']); self::assertSame(1,$r['draws']);
        self::assertEqualsWithDelta(100/3,$r['winRate'],.00001);
        self::assertSame(190,$r['pointsFor']['total']); self::assertSame(190,$r['pointsAgainst']['total']); self::assertSame(0,$r['difference']['total']);
        self::assertSame(3,$r['pointsFor']['count']); self::assertSame(1,$r['splits']['away']['losses']);
        self::assertCount(1,$r['players']); self::assertSame(1,$r['players'][0]['played']); self::assertSame(20,$r['players'][0]['metrics']['points']['total']);
    }

    public function testUnknownsZerosAndPairedPer40AreDistinct(): void
    {
        $p=new Player(); $rows=[
            $this->stat($p,$this->game(),20,1200)->setFouls(0)->setJerseyNumber(4),
            $this->stat($p,$this->game(),0,600)->setFouls(2)->setJerseyNumber(12),
            $this->stat($p,$this->game(),10,null),
            $this->stat($p,$this->game(),null,600),
            $this->stat($p,$this->game(),0,null)->setParticipation('played'),
            $this->stat($p,$this->game(),0,0),
            $this->stat($p,$this->game(),null,null)->setFouls(1),
            $this->stat($p,$this->game('scheduled'),999,1200),
        ];
        $r=(new StatisticsCalculator())->player($p,[...$rows,$rows[0]]);
        self::assertSame(7,$r['listed']); self::assertSame(5,$r['played']); self::assertSame(1,$r['didNotPlay']); self::assertSame(1,$r['unknown']);
        self::assertSame(30,$r['metrics']['points']['total']); self::assertSame(4,$r['metrics']['points']['count']); self::assertEquals(7.5,$r['metrics']['points']['average']);
        self::assertSame(2400,$r['metrics']['secondsPlayed']['total']); self::assertEquals(800,$r['metrics']['secondsPlayed']['average']);
        self::assertEquals(1,$r['metrics']['fouls']['average']); self::assertSame(2,$r['metrics']['fouls']['count']);
        self::assertEqualsWithDelta(20*2400/1800,$r['pointsPer40'],.00001); self::assertSame(2,$r['per40Games']); self::assertSame(20,$r['best']['points']);
    }

    public function testRankingTiesAndMissingValues(): void
    {
        $c=new StatisticsCalculator(); $a=(new Player())->setFirstName('Alex'); $b=(new Player())->setFirstName('Benoit'); $d=(new Player())->setFirstName('Dan'); $unknown=new Player();
        $rows=[$c->player($d,[$this->stat($d,$this->game(),5,120)]),$c->player($unknown,[]),$c->player($b,[$this->stat($b,$this->game(),10,600)]),$c->player($a,[$this->stat($a,$this->game(),10,1200)])];
        $r=$c->ranking($rows,'points'); self::assertSame([1,1,3,null],array_column($r,'rank')); self::assertSame($a,$r[0]['player']);
        self::assertSame($d,$c->ranking($rows,'per40')[0]['player']); self::assertSame($a,$c->ranking($rows,'minutes')[0]['player']);
        self::assertNull($c->summarize([],[],[])['pointsFor']['total']); self::assertNull($c->summarize([],[],[])['winRate']);
    }

    public function testSeasonBoundariesUseParisTimeAndHalfOpenRanges(): void
    {
        $c=new SeasonCalendar(); [$start,$end]=$c->bounds(2026);
        self::assertSame('2026-06-30 22:00:00',$start->format('Y-m-d H:i:s'));
        self::assertSame('2027-06-30 22:00:00',$end->format('Y-m-d H:i:s'));
        self::assertSame(2025,$c->year(new \DateTimeImmutable('2026-06-30T21:59:59Z')));
        self::assertSame(2026,$c->year($start)); self::assertSame(2027,$c->year($end));
        $f=new StatisticsExtension(); self::assertSame('—',$f->time(null)); self::assertSame('0:00',$f->time(0)); self::assertSame('120:01',$f->time(7200.6));
    }
}
