<?php

namespace App\Tests;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SecurityTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertStringEndsWith('auth_test.db', $em->getConnection()->getParams()['path']);
        $tool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
        static::getContainer()->get('cache.rate_limiter')->clear();
        $user = (new User())->setUsername('ExistingUser');
        $user->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, 'A long test passphrase 2026'));
        $em->persist($user); $em->flush();
    }

    private function login(string $password = 'A long test passphrase 2026'): void
    {
        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->selectButton('Se connecter')->form([
            '_username' => 'EXISTINGuser', '_password' => $password,
        ]));
    }

    public function testPublicPresentationDoesNotExposeTheWorkspace(): void
    {
        $crawler = $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'L’analyse commence.');
        self::assertSelectorNotExists('.app-sidebar');
        self::assertSelectorTextNotContains('main', 'ExistingUser');
        self::assertSelectorExists('a[href="/register"]');
        self::assertSelectorExists('a[href="/login"]');
        self::assertSelectorExists('#fonctionnement');
        self::assertSelectorExists('#analyses');
        self::assertSelectorCount(5, '.landing-faq-list details');
        self::assertSelectorExists('meta[name="description"]');
        $this->client->click($crawler->selectLink('Découvrir le fonctionnement')->link());
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/decouvrir');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('X-Frame-Options', 'DENY');

        $this->client->request('GET', '/teams');
        self::assertResponseRedirects('http://localhost/login');
        $this->client->request('GET', '/teams/1/imports/new');
        self::assertResponseRedirects('http://localhost/login');

        $user = static::getContainer()->get(EntityManagerInterface::class)->getRepository(User::class)->findOneBy(['canonicalUsername'=>'existinguser']);
        $this->client->loginUser($user);
        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Vue d’ensemble');
        self::assertSelectorExists('.app-sidebar');
        $this->client->request('GET', '/decouvrir');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.landing-hero-actions', 'Accéder à mon espace');
        self::assertSelectorNotExists('a[href="/register"]');
    }

    public function testPrivatePageAndLoginLogout(): void
    {
        $this->client->request('GET', '/teams');
        self::assertResponseRedirects('http://localhost/login');
        $this->login();
        self::assertResponseRedirects('http://localhost/teams');
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'ExistingUser');
        self::assertResponseHeaderSame('X-Frame-Options', 'DENY');
        $this->client->submit($crawler->selectButton('Se déconnecter')->form());
        self::assertResponseRedirects('/login');
        $this->client->request('GET', '/teams');
        self::assertResponseRedirects('http://localhost/login');
    }

    public function testBadPasswordAndMissingCsrfAreRejected(): void
    {
        $this->login('Wrong password');
        $this->client->followRedirect();
        self::assertSelectorExists('[role="alert"]');
        $this->client->request('POST', '/login', ['_username' => 'ExistingUser', '_password' => 'A long test passphrase 2026']);
        $this->client->followRedirect();
        self::assertSelectorExists('[role="alert"]');
        $this->client->request('GET', '/teams');
        self::assertResponseRedirects('http://localhost/login');
    }

    public function testRegistrationHashesPasswordAndRejectsDuplicate(): void
    {
        $crawler = $this->client->request('GET', '/register');
        $this->client->submit($crawler->selectButton('Créer mon compte')->form([
            'registration[username]' => 'NewPlayer',
            'registration[plainPassword][first]' => 'A strong new password 2026',
            'registration[plainPassword][second]' => 'A strong new password 2026',
        ]));
        self::assertResponseRedirects('/login', 303);
        $user = static::getContainer()->get(EntityManagerInterface::class)->getRepository(User::class)->findOneBy(['canonicalUsername' => 'newplayer']);
        self::assertNotNull($user);
        self::assertNotSame('A strong new password 2026', $user->getPassword());
        self::assertTrue(static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, 'A strong new password 2026'));
        self::assertSame(['ROLE_USER'], $user->getRoles());
        $crawler = $this->client->request('GET', '/register');
        $this->client->submit($crawler->selectButton('Créer mon compte')->form([
            'registration[username]' => 'NEWPLAYER',
            'registration[plainPassword][first]' => 'A strong new password 2026',
            'registration[plainPassword][second]' => 'A strong new password 2026',
        ]));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Cet identifiant est indisponible');
    }

    public function testRegistrationValidationAndCsrf(): void
    {
        $crawler = $this->client->request('GET', '/register');
        $this->client->submit($crawler->selectButton('Créer mon compte')->form([
            'registration[username]' => 'WeakUser',
            'registration[plainPassword][first]' => 'short',
            'registration[plainPassword][second]' => 'short',
        ]));
        self::assertResponseStatusCodeSame(422);
        $this->client->request('POST', '/register', ['registration' => [
            'username' => 'NoToken', 'plainPassword' => ['first' => 'A strong password 2026', 'second' => 'A strong password 2026'],
        ]]);
        self::assertResponseStatusCodeSame(422);
        self::assertNull(static::getContainer()->get(EntityManagerInterface::class)->getRepository(User::class)->findOneBy(['canonicalUsername' => 'notoken']));
    }

    public function testLoginThrottling(): void
    {
        for ($i = 0; $i < 6; ++$i) { $this->login('Wrong password'); }
        $this->login();
        self::assertResponseRedirects('/login');
        $this->client->request('GET', '/teams');
        self::assertResponseRedirects('http://localhost/login');
    }

    public function testLogoutRequiresPostAndCsrf(): void
    {
        $this->login();
        $this->client->request('GET', '/logout');
        self::assertResponseStatusCodeSame(405);
        $this->client->request('POST', '/logout');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/teams');
        self::assertResponseIsSuccessful();
    }

    public function testRegistrationThrottling(): void
    {
        for ($i = 0; $i < 6; ++$i) { $this->client->request('POST', '/register'); }
        self::assertResponseStatusCodeSame(429);
    }
}
