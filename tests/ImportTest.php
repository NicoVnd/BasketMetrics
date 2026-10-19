<?php

namespace App\Tests;

use App\Entity\{User,Team,Player,MatchGame,PlayerMatchStat,ImportJob,PeriodScore};
use App\Service\Import\{FfbbParser,IdentityNormalizer,PlayerMatcher};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ImportTest extends WebTestCase
{
    private $client;
    private int $teamId;
    private int $otherId;

    protected function setUp(): void
    {
        $this->client=static::createClient(); $em=$this->em();
        self::assertStringEndsWith('auth_test.db',$em->getConnection()->getParams()['path']);
        $tool=new SchemaTool($em); $metadata=$em->getMetadataFactory()->getAllMetadata(); $tool->dropSchema($metadata); $tool->createSchema($metadata);
        $owner=(new User())->setUsername('ImportCoach')->setPassword('test');
        $other=(new User())->setUsername('OtherCoach')->setPassword('test');
        $team=(new Team())->setName('Club Alpha')->setOwner($owner);
        foreach ([$owner,$other,$team] as $entity) { $em->persist($entity); } $em->flush();
        $this->teamId=$team->getId(); $this->otherId=$other->getId();
        $this->client->loginUser($owner);
    }
    private function em(): EntityManagerInterface { return static::getContainer()->get(EntityManagerInterface::class); }
    private function payload(): array
    {
        $fixture=json_decode(file_get_contents(__DIR__.'/Fixtures/ffbb/portrait.json'),true);
        $p=(new FfbbParser(new IdentityNormalizer()))->parse($fixture['summary'],$fixture['sheet'],[]);
        $p['side']='home'; return $p;
    }
    private function job(?array $payload=null): string
    {
        $em=$this->em();
        $job=(new ImportJob())->setTeam($em->find(Team::class,$this->teamId))->setSourceHash(bin2hex(random_bytes(32)))->setPayload($payload??$this->payload());
        $em->persist($job); $em->flush(); return $job->getId();
    }
    private function submit(string $id,array $values=[]): void
    {
        $crawler=$this->client->request('GET','/imports/'.$id);
        self::assertResponseIsSuccessful();
        $form=$crawler->selectButton('Valider et enregistrer le match')->form(['import_review[acknowledge]'=>'1']+$values);
        $this->client->submit($form);
    }

    public function testPreviewThenCommitCreatesMatchPlayersPeriodsAndNulls(): void
    {
        $jobId=$this->job();
        $this->client->request('GET','/imports/'.$jobId);
        self::assertResponseIsSuccessful(); self::assertSame(0,$this->em()->getRepository(Player::class)->count([]));
        self::assertSame(0,$this->em()->getRepository(MatchGame::class)->count([]));
        $this->submit($jobId);
        self::assertResponseStatusCodeSame(303);
        self::assertSame(8,$this->em()->getRepository(Player::class)->count([]));
        self::assertSame(8,$this->em()->getRepository(PlayerMatchStat::class)->count([]));
        self::assertSame(4,$this->em()->getRepository(PeriodScore::class)->count([]));
        $game=$this->em()->getRepository(MatchGame::class)->findOneBy([]);
        self::assertSame(75,$game->getTeamScore()); self::assertSame(34,$game->getOpponentScore());
        self::assertSame('2026-10-10 18:30',$game->getPlayedAt()->format('Y-m-d H:i'));
        self::assertSame(10,count($game->getImportMetadata()['opponentPlayers']));
        $this->client->followRedirect(); self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body','Scores par période');
        $this->client->request('GET','/imports/'.$jobId);
        self::assertResponseStatusCodeSame(302); self::assertSame(1,$this->em()->getRepository(MatchGame::class)->count([]));
    }

    public function testLicenseAndNameMatchingAcrossChangingJerseys(): void
    {
        $em=$this->em();
        $existing=(new Player())->setTeam($em->find(Team::class,$this->teamId))->setFirstName('Autre prénom')->setLastName('Nom corrigé')->setLicenseNumber('AA000001')->setJerseyNumber(99);
        $em->persist($existing); $em->flush(); $playerId=$existing->getId();
        $payload=$this->payload(); $payload['players']['home'][0]['secondsPlayed']=null;
        $this->submit($this->job($payload)); self::assertResponseStatusCodeSame(303);
        self::assertSame(8,$this->em()->getRepository(Player::class)->count([]));
        $stat=$this->em()->getRepository(PlayerMatchStat::class)->findOneBy(['player'=>$playerId]);
        self::assertSame(5,$stat->getJerseyNumber()); self::assertNull($stat->getSecondsPlayed()); self::assertSame(0,$stat->getPoints());
        $payload['reference']='1421'; $payload['playedAt']='2026-10-17T18:30:00+00:00'; $payload['players']['home'][0]['jerseyNumber']=77;
        $this->submit($this->job($payload)); self::assertResponseStatusCodeSame(303);
        self::assertSame(8,$this->em()->getRepository(Player::class)->count([]));
        self::assertSame(99,$this->em()->find(Player::class,$playerId)->getJerseyNumber());
        $stats=$this->em()->getRepository(PlayerMatchStat::class)->findBy(['player'=>$playerId],['id'=>'ASC']);
        self::assertSame([5,77],array_map(fn($s)=>$s->getJerseyNumber(),$stats));
        self::assertSame(2,$this->em()->getRepository(MatchGame::class)->count([]));
    }

    public function testExistingManualMatchIsCompletedWithoutDuplication(): void
    {
        $em=$this->em(); $team=$em->find(Team::class,$this->teamId);
        $game=(new MatchGame())->setTeam($team)->setOpponent('CLUB BETA')->setPlayedAt(new \DateTimeImmutable('2026-10-10 18:30:00'))->setHome(true);
        $player=(new Player())->setTeam($team)->setFirstName('Prenom01')->setLastName('JOUEUR01')->setJerseyNumber(35);
        $em->persist($game);$em->persist($player);$em->flush(); $gameId=$game->getId(); $playerId=$player->getId();
        $this->submit($this->job()); self::assertResponseStatusCodeSame(303);
        self::assertSame(1,$this->em()->getRepository(MatchGame::class)->count([]));
        self::assertSame(8,$this->em()->getRepository(Player::class)->count([]));
        self::assertSame(75,$this->em()->find(MatchGame::class,$gameId)->getTeamScore());
        self::assertSame('AA000001',$this->em()->find(Player::class,$playerId)->getLicenseNumber());
    }

    public function testSameEncounterDifferentZipIsRejectedAtomically(): void
    {
        $this->submit($this->job()); self::assertResponseStatusCodeSame(303);
        $otherJob=$this->job(); $this->submit($otherJob);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body','déjà été importée');
        $this->client->request('GET','/');
        self::assertSame(1,$this->em()->getRepository(MatchGame::class)->count([]));
        self::assertSame(8,$this->em()->getRepository(Player::class)->count([]));
    }

    public function testNicknameRequiresExplicitChoiceAndFailureRollsBack(): void
    {
        $em=$this->em();
        $player=(new Player())->setTeam($em->find(Team::class,$this->teamId))->setFirstName('P.')->setLastName('JOUEUR01');
        $em->persist($player); $em->flush();
        $id=$this->job(); $this->submit($id);
        self::assertResponseStatusCodeSame(422); self::assertSelectorTextContains('body','Choisissez le joueur correspondant');
        $this->client->request('GET','/');
        self::assertSame(1,$this->em()->getRepository(Player::class)->count([])); self::assertSame(0,$this->em()->getRepository(MatchGame::class)->count([]));
        $this->submit($id,['import_review[players][0][existingPlayer]'=>(string)$player->getId()]); self::assertResponseStatusCodeSame(303);
        self::assertSame(8,$this->em()->getRepository(Player::class)->count([]));
    }

    public function testCsrfAcknowledgementAndForeignAccess(): void
    {
        $id=$this->job();
        $this->client->request('POST','/imports/'.$id,['import_review'=>['acknowledge'=>'1']]); self::assertResponseStatusCodeSame(422);
        $crawler=$this->client->request('GET','/imports/'.$id);
        $this->client->submit($crawler->selectButton('Valider et enregistrer le match')->form()); self::assertResponseStatusCodeSame(422);
        self::assertSame(0,$this->em()->getRepository(Player::class)->count([]));
        $this->client->loginUser($this->em()->find(User::class,$this->otherId));
        foreach (['/imports/'.$id,'/imports/'.$id.'/documents/0','/teams/'.$this->teamId.'/imports/new'] as $url) { $this->client->request('GET',$url); self::assertResponseStatusCodeSame(403); }
        $this->client->request('POST','/imports/'.$id.'/cancel'); self::assertResponseStatusCodeSame(403);
    }

    public function testCannotMapTwoRowsToOnePlayer(): void
    {
        $em=$this->em();
        $player=(new Player())->setTeam($em->find(Team::class,$this->teamId))->setFirstName('Prenom01')->setLastName('JOUEUR01');
        $em->persist($player); $em->flush();
        $this->submit($this->job(),['import_review[players][0][licenseNumber]'=>'','import_review[players][1][licenseNumber]'=>'','import_review[players][1][existingPlayer]'=>(string)$player->getId()]);
        self::assertResponseStatusCodeSame(422); self::assertSelectorTextContains('body','Deux lignes correspondent au même joueur');
        $this->client->request('GET','/'); self::assertSame(0,$this->em()->getRepository(MatchGame::class)->count([]));
    }
}
