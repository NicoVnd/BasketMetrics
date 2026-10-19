<?php

namespace App\Command;

use App\Entity\ImportJob;
use App\Service\Import\ImportStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name:'app:imports:cleanup',description:'Supprime les aperçus non validés de plus de 24 heures et leurs fichiers privés.')]
final class CleanupImportsCommand extends Command
{
    public function __construct(private EntityManagerInterface $em,private ImportStorage $storage) { parent::__construct(); }
    protected function execute(InputInterface $input,OutputInterface $output): int
    {
        $jobs=$this->em->createQueryBuilder()->select('j')->from(ImportJob::class,'j')->where('j.status = :status')->andWhere('j.createdAt < :cutoff')->setParameter('status','pending')->setParameter('cutoff',new \DateTimeImmutable('-24 hours'))->getQuery()->getResult();
        $ids=[];
        foreach ($jobs as $job) { $ids[]=$job->getId(); $this->em->remove($job); }
        $this->em->flush();
        foreach ($ids as $id) { $this->storage->remove($id); }
        $output->writeln(count($ids).' aperçus expirés supprimés.'); return Command::SUCCESS;
    }
}
