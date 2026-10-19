<?php
namespace App\Tests;

use App\Entity\{User,Team,Player,MatchGame,PlayerMatchStat};
use App\Service\Statistics\SeasonStatistics;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SeasonStatisticsTest extends WebTestCase
{
    private $client;
    private int $teamId;
    private int $playerId;
    private int $gameId;
    private function em(): EntityManagerInterface { return static::getContainer()->get(EntityManagerInterface::class); }
    protected function setUp(): void
    {
        $this->client=static::createClient(); $em=$this->em();
        self::assertStringEndsWith('auth_test.db',$em->getConnection()->getParams()['path']);
        $metadata=$em->getMetadataFactory()->getAllMetadata(); $tool=new SchemaTool($em); $tool->dropSchema($metadata); $tool->createSchema($metadata);
        $u=(new User())->setUsername('CoachStats')->setPassword('unused');
        $t=(new Team())->setOwner($u)->setName('Équipe stats'); $p=(new Player())->setTeam($t)->setFirstName('Alex')->setLastName('Martin')->setLicenseNumber('ABC');
        $g=(new MatchGame())->setTeam($t)->setOpponent('Saison actuelle')->setPlayedAt(new \DateTimeImmutable('2026-06-30T22:00:00Z'))->setStatus('finished')->setTeamScore(80)->setOpponentScore(60);
        $old=(new MatchGame())->setTeam($t)->setOpponent('Saison passée')->setPlayedAt(new \DateTimeImmutable('2026-06-30T21:59:59Z'))->setStatus('finished')->setTeamScore(40)->setOpponentScore(70);
        $next=(new MatchGame())->setTeam($t)->setOpponent('Saison suivante')->setPlayedAt(new \DateTimeImmutable('2027-06-30T22:00:00Z'));
        $s=(new PlayerMatchStat())->setPlayer($p)->setMatchGame($g)->setPoints(12)->setSecondsPlayed(1200)->setFouls(0)->setJerseyNumber(8);
        foreach ([$u,$t,$p,$g,$old,$next,$s] as $e) { $em->persist($e); } $em->flush();
        $this->teamId=$t->getId(); $this->playerId=$p->getId(); $this->gameId=$g->getId(); $this->client->loginUser($u);
    }
    public function testTeamProfileSeasonFilterAndRecalculation(): void
    {
        $url='/teams/'.$this->teamId;
        $this->client->request('GET',$url.'?season=2026'); self::assertResponseIsSuccessful(); self::assertSelectorTextContains('main','1 V / 0 D');
        self::assertSelectorTextContains('.stats-table','12'); self::assertSelectorTextNotContains('main','Saison passée');
        $this->client->request('GET',$url.'/players/'.$this->playerId.'?season=2026'); self::assertResponseIsSuccessful(); self::assertSelectorTextContains('h1','Alex Martin'); self::assertSelectorTextContains('main','20:00');
        $em=$this->em(); $stat=$em->getRepository(PlayerMatchStat::class)->findOneBy(['player'=>$this->playerId]); $stat->setPoints(24); $em->flush();
        $season=static::getContainer()->get(SeasonStatistics::class)->load($em->find(Team::class,$this->teamId),'2026'); self::assertSame(24,$season['ranking'][0]['metrics']['points']['total']); self::assertCount(1,$season['summary']['games']);
        self::assertSame(1,$em->getRepository(PlayerMatchStat::class)->count([]));
        $this->client->request('GET',$url.'?season=2025'); self::assertResponseIsSuccessful(); self::assertSelectorTextContains('main','0 V / 1 D'); self::assertSelectorTextContains('main','Saison passée');
        $this->client->request('GET',$url.'?season=bad'); self::assertResponseStatusCodeSame(400);
        $this->client->request('GET',$url.'?sort=bad'); self::assertResponseStatusCodeSame(400);
    }
    public function testProfileOwnershipAndPlayerTeamAssociation(): void
    {
        $em=$this->em(); $other=(new User())->setUsername('OtherStats')->setPassword('unused'); $team=(new Team())->setOwner($other)->setName('Autre'); $p=(new Player())->setTeam($team)->setFirstName('Autre')->setLastName('Joueur');
        foreach ([$other,$team,$p] as $e) { $em->persist($e); } $em->flush();
        $otherPlayer=$p->getId(); $this->client->request('GET','/teams/'.$this->teamId.'/players/'.$otherPlayer); self::assertResponseStatusCodeSame(404);
        $this->client->loginUser($this->em()->find(User::class,$other->getId()));
        $this->client->request('GET','/teams/'.$this->teamId.'/players/'.$this->playerId); self::assertResponseStatusCodeSame(403);
    }
    public function testDatabaseRejectsDuplicateGamePlayer(): void
    {
        $em=$this->em(); $s=(new PlayerMatchStat())->setPlayer($em->find(Player::class,$this->playerId))->setMatchGame($em->find(MatchGame::class,$this->gameId))->setJerseyNumber(99);
        $em->persist($s); $this->expectException(UniqueConstraintViolationException::class); $em->flush();
    }
    public function testInconsistentAbsenceCannotBeSaved(): void
    {
        $crawler=$this->client->request('GET','/teams/'.$this->teamId.'/matches/'.$this->gameId);
        $this->client->submit($crawler->selectButton('Enregistrer les statistiques')->form(['form[stats]['.$this->playerId.'][participation]'=>'did_not_play']));
        self::assertResponseStatusCodeSame(422); self::assertSelectorTextContains('main','déclaré non entré en jeu');
        $em=$this->em(); $em->clear(); self::assertSame('unknown',$em->getRepository(PlayerMatchStat::class)->findOneBy(['player'=>$this->playerId])->getParticipation());
    }
}
