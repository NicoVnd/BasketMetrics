<?php

namespace App\Tests;

use App\Entity\{User, Team, Player, MatchGame, PlayerMatchStat, PeriodScore, ImportJob};
use App\Service\Import\ImportStorage;
use App\Service\Ai\{AnalysisDataBuilder, GeminiAnalysisService};
use App\Entity\AiReport;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class TeamWorkflowTest extends WebTestCase
{
    private $client;
    private int $ownerId;
    private int $otherId;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertStringEndsWith('auth_test.db', $em->getConnection()->getParams()['path']);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($em); $tool->dropSchema($metadata); $tool->createSchema($metadata);
        $owner = (new User())->setUsername('Coach')->setPassword('not-used-by-loginUser');
        $other = (new User())->setUsername('OtherCoach')->setPassword('not-used-by-loginUser');
        $em->persist($owner); $em->persist($other); $em->flush();
        $this->ownerId = $owner->getId(); $this->otherId = $other->getId();
        $this->client->loginUser($owner);
    }

    private function em(): EntityManagerInterface { return static::getContainer()->get(EntityManagerInterface::class); }

    private function fixtures(): array
    {
        $em = $this->em();
        $team = (new Team())->setOwner($em->find(User::class, $this->ownerId))->setName('Lezennes');
        $player = (new Player())->setTeam($team)->setFirstName('Alex')->setLastName('Martin')->setJerseyNumber(0);
        $game = (new MatchGame())->setTeam($team)->setOpponent('Lille Sud')->setPlayedAt(new \DateTimeImmutable('2026-10-15 18:00:00'))->setStatus('finished')->setTeamScore(75)->setOpponentScore(34);
        foreach ([$team, $player, $game] as $entity) { $em->persist($entity); } $em->flush();
        return [$team->getId(), $player->getId(), $game->getId()];
    }

    public function testAnalysisPayloadPreservesUnknownsAndExcludesFutureAndPersonalData(): void
    {
        [$teamId,$playerId,$gameId]=$this->fixtures();
        $em=$this->em(); $team=$em->find(Team::class,$teamId); $player=$em->find(Player::class,$playerId);
        $player->setLicenseNumber('PRIVATE-LICENCE');
        $future=(new MatchGame())->setTeam($team)->setOpponent('Future')->setPlayedAt(new \DateTimeImmutable('2026-11-01'))->setStatus('finished')->setTeamScore(90)->setOpponentScore(70);
        $stat=(new PlayerMatchStat())->setPlayer($player)->setMatchGame($em->find(MatchGame::class,$gameId))->setPoints(0)->setSecondsPlayed(60);
        $em->persist($future); $em->persist($stat); $em->flush();
        $builder=static::getContainer()->get(AnalysisDataBuilder::class);
        $context=$builder->build($team,'match',$gameId);
        self::assertCount(1,$context['data']['history']);
        self::assertSame(0,$context['data']['history'][0]['players'][0]['points']);
        self::assertNull($context['data']['history'][0]['players'][0]['fouls']);
        $json=json_encode($context['data']);
        foreach (['Alex','Martin','PRIVATE-LICENCE','Lezennes'] as $private) { self::assertStringNotContainsString($private,$json); }
        self::assertCount(2,$builder->build($team,'season',2026)['data']['history']);
        self::assertCount(1,$builder->buildPlayer($team,$player,'2026')['data']['players']);
    }

    public function testAnalysisGenerationPersistenceStalenessAndFailureKeepsReport(): void
    {
        [$teamId,,$gameId]=$this->fixtures();
        $this->client->disableReboot();
        static::getContainer()->get('cache.rate_limiter')->clear();
        $http=new MockHttpClient([
            new MockResponse('{"candidates":[{"finishReason":"STOP","content":{"parts":[{"text":"Faits mesurés : 75 points. <script>alert(1)</script>"}]}}]}'),
            new MockResponse('{}',['http_code'=>429]),
        ]);
        static::getContainer()->set(GeminiAnalysisService::class,new GeminiAnalysisService($http,'secret-test','mock-model'));
        $url='/teams/'.$teamId.'/analysis/match/'.$gameId;
        $crawler=$this->client->request('GET',$url);
        self::assertResponseIsSuccessful(); self::assertSame(0,$http->getRequestsCount());
        $this->client->submit($crawler->selectButton('Générer l’analyse')->form());
        self::assertResponseStatusCodeSame(303);
        self::assertSame(1,$this->em()->getRepository(AiReport::class)->count([]));
        $this->client->followRedirect();
        self::assertSelectorTextContains('main','75 points');
        self::assertSelectorNotExists('.workspace-content script');
        $game=$this->em()->find(MatchGame::class,$gameId); $game->setTeamScore(76); $this->em()->flush();
        $crawler=$this->client->request('GET',$url);
        self::assertSelectorTextContains('[role="alert"]','ont changé');
        $this->client->submit($crawler->selectButton('Régénérer l’analyse')->form());
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main','Quota Gemini atteint');
        self::assertSelectorTextContains('main','75 points');
        self::assertSame(1,$this->em()->getRepository(AiReport::class)->count([]));
    }

    public function testAnalysisPermissionsCsrfAndNoConfiguration(): void
    {
        [$teamId,$playerId,$gameId]=$this->fixtures();
        $this->client->disableReboot();
        static::getContainer()->set(GeminiAnalysisService::class,new GeminiAnalysisService(new MockHttpClient(fn()=>self::fail('No API call expected')),'',''));
        foreach (['match/'.$gameId,'season/2026','player/'.$playerId.'?season=2026'] as $suffix) {
            $url='/teams/'.$teamId.'/analysis/'.$suffix;
            $this->client->request('GET',$url); self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('main','L’IA est désactivée');
            $this->client->request('POST',$url,['_token'=>'invalid']); self::assertResponseStatusCodeSame(403);
        }
        $this->client->request('GET','/teams/'.$teamId.'/analysis/season/9999'); self::assertResponseStatusCodeSame(400);
        $this->client->request('GET','/teams/'.($teamId+100).'/analysis/match/'.$gameId); self::assertResponseStatusCodeSame(404);
        $this->client->loginUser($this->em()->find(User::class,$this->otherId));
        $this->client->request('GET','/teams/'.$teamId.'/analysis/match/'.$gameId); self::assertResponseStatusCodeSame(403);
        self::assertSame(0,$this->em()->getRepository(AiReport::class)->count([]));
    }

    public function testMatchDeletionRemovesRelatedDataAndKeepsPlayersAndOtherMatches(): void
    {
        [$teamId, $playerId, $gameId] = $this->fixtures();
        $em = $this->em();
        $game = $em->find(MatchGame::class, $gameId);
        $game->setImportMetadata(['competition' => 'Championnat', 'pool' => '', 'venue' => 'Salle', 'officials' => [], 'warnings' => [], 'opponentPlayers' => []]);
        $player = $em->find(Player::class, $playerId);
        $other = (new MatchGame())->setTeam($game->getTeam())->setOpponent('Autre adversaire')->setPlayedAt(new \DateTimeImmutable());
        $stat = (new PlayerMatchStat())->setMatchGame($game)->setPlayer($player)->setPoints(12);
        $otherStat = (new PlayerMatchStat())->setMatchGame($other)->setPlayer($player)->setPoints(8);
        $period = (new PeriodScore())->setMatchGame($game)->setPeriodNumber(1)->setTeamPoints(12);
        $job = (new ImportJob())->setTeam($game->getTeam())->setMatchGame($game)->setSourceHash(str_repeat('a', 64))->setStatus('complete')->setPayload(['files' => []]);
        foreach ([$other, $stat, $otherStat, $period, $job] as $entity) { $em->persist($entity); }
        $em->flush();
        $otherId = $other->getId(); $jobId = $job->getId();
        $storage = static::getContainer()->get(ImportStorage::class);
        $directory = $storage->create($jobId);
        file_put_contents($storage->document($jobId, 0), 'test document');
        try {
            $crawler = $this->client->request('GET', '/teams/'.$teamId.'/matches/'.$gameId);
            $this->client->submit($crawler->selectButton('Supprimer le match')->form());
            self::assertResponseRedirects('/teams/'.$teamId, 303);
            self::assertNull($this->em()->find(MatchGame::class, $gameId));
            self::assertNull($this->em()->find(ImportJob::class, $jobId));
            self::assertSame(0, $this->em()->getRepository(PeriodScore::class)->count([]));
            self::assertSame(1, $this->em()->getRepository(PlayerMatchStat::class)->count([]));
            self::assertNotNull($this->em()->find(MatchGame::class, $otherId));
            self::assertNotNull($this->em()->find(Player::class, $playerId));
            self::assertDirectoryDoesNotExist($directory);
            $this->client->followRedirect();
            self::assertSelectorTextContains('[role="status"]', 'Match supprimé');
        } finally { $storage->remove($jobId); }
    }

    public function testMatchDeletionRequiresPostValidCsrfAndOwnership(): void
    {
        [$teamId, , $gameId] = $this->fixtures();
        $url = '/teams/'.$teamId.'/matches/'.$gameId.'/delete';
        $this->client->request('GET', $url);
        self::assertResponseStatusCodeSame(405);
        foreach ([[], ['_token' => 'invalid']] as $parameters) {
            $this->client->request('POST', $url, $parameters);
            self::assertResponseStatusCodeSame(403);
        }
        $crawler = $this->client->request('GET', '/teams/'.$teamId.'/matches/'.$gameId);
        $parameters = $crawler->selectButton('Supprimer le match')->form()->getPhpValues();
        $this->client->request('POST', '/teams/'.($teamId + 100).'/matches/'.$gameId.'/delete', $parameters);
        self::assertResponseStatusCodeSame(404);
        $this->client->loginUser($this->em()->find(User::class, $this->otherId));
        $this->client->request('POST', $url, $parameters);
        self::assertResponseStatusCodeSame(403);
        self::assertNotNull($this->em()->find(MatchGame::class, $gameId));
    }

    public function testPlayerDeletionFromTeamForm(): void
    {
        [$teamId, $playerId] = $this->fixtures();
        $crawler = $this->client->request('GET', '/teams/'.$teamId);
        $this->client->submit($crawler->filter('button[title="Supprimer"]')->form());
        self::assertResponseRedirects('/teams/'.$teamId, 303);
        self::assertNull($this->em()->find(Player::class, $playerId));
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="status"]', 'Joueur supprimé');
    }

    public function testPlayerDeletionRequiresValidCsrfAndOwnership(): void
    {
        [$teamId, $playerId] = $this->fixtures();
        $url = '/teams/'.$teamId.'/players/'.$playerId;
        foreach ([[], ['_token' => 'invalid']] as $parameters) {
            $this->client->request('POST', $url, $parameters);
            self::assertResponseStatusCodeSame(403);
            self::assertNotNull($this->em()->find(Player::class, $playerId));
        }
        $crawler = $this->client->request('GET', '/teams/'.$teamId);
        $parameters = $crawler->filter('button[title="Supprimer"]')->form()->getPhpValues();
        $otherTeam = (new Team())->setOwner($this->em()->find(User::class, $this->ownerId))->setName('Autre équipe');
        $this->em()->persist($otherTeam); $this->em()->flush();
        $this->client->request('POST', '/teams/'.$otherTeam->getId().'/players/'.$playerId, $parameters);
        self::assertResponseStatusCodeSame(404);
        $this->client->loginUser($this->em()->find(User::class, $this->otherId));
        $this->client->request('POST', $url, $parameters);
        self::assertResponseStatusCodeSame(403);
        self::assertNotNull($this->em()->find(Player::class, $playerId));
    }

    public function testPlayerWithStatsCannotBeDeleted(): void
    {
        [$teamId, $playerId, $gameId] = $this->fixtures();
        $stat = (new PlayerMatchStat())->setPlayer($this->em()->find(Player::class, $playerId))->setMatchGame($this->em()->find(MatchGame::class, $gameId))->setPoints(12);
        $this->em()->persist($stat); $this->em()->flush();
        $crawler = $this->client->request('GET', '/teams/'.$teamId);
        $this->client->submit($crawler->filter('button[title="Supprimer"]')->form());
        self::assertResponseRedirects('/teams/'.$teamId, 303);
        self::assertNotNull($this->em()->find(Player::class, $playerId));
        self::assertSame(1, $this->em()->getRepository(PlayerMatchStat::class)->count(['player' => $playerId]));
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'préserver l’historique');
    }

    public function testFullCreationAndStatsUpdates(): void
    {
        $this->client->request('GET', '/');
        self::assertSelectorTextContains('h2', 'Mes équipes');
        $crawler = $this->client->request('GET', '/teams/new');
        $this->client->submit($crawler->selectButton('Enregistrer')->form(['team[name]' => 'Lezennes', 'team[category]' => 'Seniors']));
        self::assertResponseStatusCodeSame(303);
        $team = $this->em()->getRepository(Team::class)->findOneBy(['name' => 'Lezennes']);
        self::assertSame($this->ownerId, $team->getOwner()->getId());
        $teamId = $team->getId();
        $crawler = $this->client->request('GET', '/teams/'.$teamId.'/players/new');
        $this->client->submit($crawler->selectButton('Enregistrer')->form(['player[firstName]' => 'Alex', 'player[lastName]' => 'Martin', 'player[jerseyNumber]' => '0']));
        self::assertResponseStatusCodeSame(303);
        $player = $this->em()->getRepository(Player::class)->findOneBy(['firstName' => 'Alex']);
        self::assertSame(0, $player->getJerseyNumber());
        $playerId = $player->getId();
        $crawler = $this->client->request('GET', '/teams/'.$teamId.'/matches/new');
        $this->client->submit($crawler->selectButton('Enregistrer')->form([
            'match_game[opponent]' => 'Lille Sud', 'match_game[playedAt]' => '2026-10-15T20:00',
            'match_game[home]' => '1', 'match_game[status]' => 'finished', 'match_game[teamScore]' => '75', 'match_game[opponentScore]' => '34',
        ]));
        self::assertResponseStatusCodeSame(303);
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Lille Sud');
        $prefix = 'form[stats]['.$playerId.']';
        $this->client->submit($crawler->selectButton('Enregistrer les statistiques')->form([
            $prefix.'[points]' => '12', $prefix.'[secondsPlayed]' => '23:45', $prefix.'[fouls]' => '0',
            $prefix.'[twoPtMade]' => '3', $prefix.'[threePtMade]' => '1', $prefix.'[freeThrowsMade]' => '3',
        ]));
        self::assertResponseStatusCodeSame(303);
        $stat = $this->em()->getRepository(PlayerMatchStat::class)->findOneBy(['player' => $playerId]);
        self::assertSame(12, $stat->getPoints()); self::assertSame(1425, $stat->getSecondsPlayed()); self::assertSame(0, $stat->getFouls());
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'totalisent 12');
        $this->client->submit($crawler->selectButton('Enregistrer les statistiques')->form([$prefix.'[points]' => '15', $prefix.'[fouls]' => '']));
        self::assertResponseStatusCodeSame(303);
        self::assertSame(1, $this->em()->getRepository(PlayerMatchStat::class)->count([]));
        $stat = $this->em()->getRepository(PlayerMatchStat::class)->findOneBy(['player' => $playerId]);
        self::assertSame(15, $stat->getPoints()); self::assertNull($stat->getFouls());
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'ne correspondent pas');
    }

    public function testOwnershipOnEveryRoute(): void
    {
        [$teamId, , $gameId] = $this->fixtures();
        $this->client->loginUser($this->em()->find(User::class, $this->otherId));
        $this->client->request('GET', '/');
        self::assertSelectorTextNotContains('main', 'Lezennes');
        foreach (['/teams/'.$teamId, '/teams/'.$teamId.'/players/new', '/teams/'.$teamId.'/matches/new', '/teams/'.$teamId.'/matches/'.$gameId, '/teams/'.$teamId.'/matches/'.$gameId.'/edit'] as $url) {
            $this->client->request('GET', $url); self::assertResponseStatusCodeSame(403);
            if ($url !== '/teams/'.$teamId) { $this->client->request('POST', $url); self::assertResponseStatusCodeSame(403); }
        }
        self::assertSame(0, $this->em()->getRepository(PlayerMatchStat::class)->count([]));
    }

    public function testCsrfAndInvalidStatsDoNotWrite(): void
    {
        [$teamId, $playerId, $gameId] = $this->fixtures();
        $url = '/teams/'.$teamId.'/matches/'.$gameId;
        $this->client->request('POST', '/teams/new', ['team' => ['name' => 'Forged']]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(1, $this->em()->getRepository(Team::class)->count([]));
        $crawler = $this->client->request('GET', $url);
        $prefix = 'form[stats]['.$playerId.']';
        $this->client->submit($crawler->selectButton('Enregistrer les statistiques')->form([$prefix.'[points]' => '-1', $prefix.'[secondsPlayed]' => '12:99']));
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em()->getRepository(PlayerMatchStat::class)->count([]));
        $this->client->request('POST', $url, ['form' => ['stats' => [$playerId => ['points' => '10']]]]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em()->getRepository(PlayerMatchStat::class)->count([]));
    }

    public function testBlankStatsRemainNullAndUnknownPlayerIsRejected(): void
    {
        [$teamId, $playerId, $gameId] = $this->fixtures();
        $url = '/teams/'.$teamId.'/matches/'.$gameId;
        $crawler = $this->client->request('GET', $url);
        $this->client->submit($crawler->selectButton('Enregistrer les statistiques')->form());
        self::assertResponseStatusCodeSame(303);
        $stat = $this->em()->getRepository(PlayerMatchStat::class)->findOneBy(['player' => $playerId]);
        self::assertNull($stat->getPoints()); self::assertNull($stat->getSecondsPlayed()); self::assertNull($stat->getFouls());
        $crawler = $this->client->followRedirect();
        $values = $crawler->selectButton('Enregistrer les statistiques')->form()->getPhpValues();
        $values['form']['stats'][999999] = ['points' => '20'];
        $this->client->request('POST', $url, $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(1, $this->em()->getRepository(PlayerMatchStat::class)->count([]));
        $this->client->request('GET', '/teams/'.($teamId + 100).'/matches/'.$gameId);
        self::assertResponseStatusCodeSame(404);
    }

    public function testFinishedMatchRequiresBothScores(): void
    {
        [$teamId, , $gameId] = $this->fixtures();
        $crawler = $this->client->request('GET', '/teams/'.$teamId.'/matches/'.$gameId.'/edit');
        $this->client->submit($crawler->selectButton('Enregistrer')->form(['match_game[teamScore]' => '']));
        self::assertResponseStatusCodeSame(422);
        $this->em()->clear();
        self::assertSame(75, $this->em()->find(MatchGame::class, $gameId)->getTeamScore());
    }
}

