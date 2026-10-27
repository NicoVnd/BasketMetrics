<?php
namespace App\Tests;

use App\Entity\{User, Team, MatchGame};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DashboardTest extends WebTestCase
{
    public function testDashboardUsesSelectedOwnersTeamAndKnownSeasonData(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertStringEndsWith('auth_test.db', $em->getConnection()->getParams()['path']);
        $schema = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata); $schema->createSchema($metadata);
        $owner = (new User())->setUsername('DashboardCoach')->setPassword('unused');
        $other = (new User())->setUsername('OtherCoach')->setPassword('unused');
        $team = (new Team())->setName('Collectif test')->setOwner($owner);
        $private = (new Team())->setName('Équipe privée')->setOwner($other);
        $game = (new MatchGame())->setTeam($team)->setOpponent('Adversaire test')->setPlayedAt(new \DateTimeImmutable('2026-10-15'))->setStatus('finished')->setTeamScore(80)->setOpponentScore(60);
        $unknown = (new MatchGame())->setTeam($team)->setOpponent('Score inconnu')->setPlayedAt(new \DateTimeImmutable('2026-10-16'))->setStatus('finished');
        foreach ([$owner, $other, $team, $private, $game, $unknown] as $entity) $em->persist($entity);
        $em->flush();
        $teamId = $team->getId(); $privateId = $private->getId();
        $client->loginUser($owner);
        $client->request('GET', '/?team='.$teamId.'&season=2026');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Vue d’ensemble');
        self::assertSelectorTextContains('.kpi-grid', '100,0');
        self::assertSelectorTextContains('.kpi-grid', '80,0');
        self::assertSelectorTextContains('.kpi-grid', '60,0');
        self::assertSelectorTextContains('.match-list', 'Score inconnu');
        self::assertSelectorTextNotContains('main', 'Équipe privée');
        $client->request('GET', '/?team='.$privateId);
        self::assertResponseStatusCodeSame(404);
        $client->request('GET', '/?team='.$teamId.'&season=2025');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.chart-empty', 'La saison reste à écrire');
        self::assertSelectorTextContains('.kpi-grid', '—');

        // Follow the actual sidebar links: each destination has its own content and active item.
        $crawler = $client->request('GET', '/?team='.$teamId.'&season=2026');
        $client->click($crawler->selectLink('Mes équipes')->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Mes équipes');
        self::assertSelectorTextContains('.sidebar-nav a[aria-current="page"]', 'Mes équipes');
        self::assertSelectorNotExists('.kpi-grid');
        self::assertSelectorTextNotContains('main', 'Équipe privée');

        foreach (['Matchs' => 'matchs', 'Joueurs' => 'joueurs', 'Statistiques de saison' => 'saison'] as $label => $section) {
            $crawler = $client->request('GET', '/?team='.$teamId.'&season=2026');
            $client->click($crawler->selectLink($label)->link());
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('h1', $label);
            self::assertSelectorCount(1, '.sidebar-nav a[aria-current="page"]');
            self::assertSelectorTextContains('.sidebar-nav a[aria-current="page"]', $label);
            self::assertSelectorExists('#'.$section);
            self::assertSelectorExists('option[value="2026"][selected]');
            foreach (array_diff(['matchs', 'joueurs', 'saison'], [$section]) as $absent) {
                self::assertSelectorNotExists('#'.$absent);
            }
        }
        foreach (['matches', 'players', 'season'] as $section) {
            $client->request('GET', '/teams/'.$privateId.'/'.$section);
            self::assertResponseStatusCodeSame(403);
        }
    }
}
