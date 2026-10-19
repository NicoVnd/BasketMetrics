<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:create-test-user', description: 'Crée le compte BasketTest en développement uniquement.')]
final class CreateTestUserCommand extends Command
{
    public function __construct(private EntityManagerInterface $em, private UserPasswordHasherInterface $hasher, #[Autowire('%kernel.environment%')] private string $environment)
    { parent::__construct(); }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ($this->environment !== 'dev') { $io->error('Commande réservée au développement.'); return Command::FAILURE; }
        if ($this->em->getRepository(User::class)->findOneBy(['canonicalUsername' => 'baskettest'])) {
            $io->note('Le compte existe déjà, son mot de passe est conservé.'); return Command::SUCCESS;
        }
        $password = $io->askHidden('Mot de passe du compte de test');
        if (!is_string($password) || strlen($password) < 8 || strlen($password) > 128) {
            $io->error('Le mot de passe doit contenir entre 8 et 128 caractères.'); return Command::FAILURE;
        }
        $user = (new User())->setUsername('BasketTest');
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->em->persist($user); $this->em->flush();
        $io->success('Compte de test créé.');
        return Command::SUCCESS;
    }
}
