<?php

namespace App\Controller;

use App\Entity\Team;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/teams', name: 'app_team_index', methods: ['GET'])]
    public function teams(EntityManagerInterface $em): Response
    {
        return $this->render('team/index.html.twig', [
            'teams' => $em->getRepository(Team::class)->findBy(['owner' => $this->getUser()], ['name' => 'ASC', 'id' => 'ASC']),
        ]);
    }

    #[Route('/', name: 'app_dashboard', methods: ['GET'])]
    public function index(EntityManagerInterface $em, \Symfony\Component\HttpFoundation\Request $request, \App\Service\Statistics\SeasonStatistics $statistics, \App\Service\Statistics\StatisticsCharts $charts): Response
    {
        if (!$this->getUser()) {
            return $this->render('landing/index.html.twig');
        }
        $teams = $em->getRepository(Team::class)->findBy(['owner' => $this->getUser()], ['name' => 'ASC', 'id' => 'ASC']);
        $team = $teams[0] ?? null;
        if ($request->query->has('team')) {
            $team = null;
            foreach ($teams as $candidate) {
                if ((string) $candidate->getId() === $request->query->get('team')) { $team = $candidate; break; }
            }
            if (!$team) { throw $this->createNotFoundException(); }
        }
        $season = $team ? $statistics->load($team, $request->query->get('season')) : null;
        return $this->render('dashboard/index.html.twig', [
            'teams' => $teams, 'team' => $team, 'season' => $season,
            'summary' => $season['summary'] ?? null,
            'teamChart' => $season ? $charts->team($season['summary']) : null,
        ]);
    }
}
