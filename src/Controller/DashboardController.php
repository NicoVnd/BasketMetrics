<?php

namespace App\Controller;

use App\Entity\Team;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        return $this->render('dashboard/index.html.twig', [
            'teams' => $em->getRepository(Team::class)->findBy(['owner' => $this->getUser()], ['name' => 'ASC', 'id' => 'ASC']),
        ]);
    }
}
