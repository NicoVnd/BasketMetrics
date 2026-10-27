<?php

namespace App\Controller;

use App\Entity\{Team, Player, PlayerMatchStat, MatchGame, ImportJob, AiReport};
use App\Form\{TeamType, PlayerType};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response, RedirectResponse};
use Symfony\Component\Routing\Attribute\Route;

final class TeamController extends AbstractController
{
    #[Route('/teams/new', name: 'app_team_new', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        $team = (new Team())->setOwner($this->getUser());
        $form = $this->createForm(TeamType::class, $team)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($team); $em->flush();
            $this->addFlash('success', 'Votre équipe est créée. Ajoutez maintenant ses joueurs.');
            return $this->redirectToRoute('app_team_show', ['id' => $team->getId()], 303);
        }
        return $this->render('team/form.html.twig', ['form' => $form, 'title' => 'Créer une équipe', 'back' => $this->generateUrl('app_dashboard')], new Response(status: $form->isSubmitted() ? 422 : 200));
    }

    #[Route('/teams/{id}', name: 'app_team_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[Route('/teams/{id}/matches', name: 'app_team_matches', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[Route('/teams/{id}/players', name: 'app_team_players', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[Route('/teams/{id}/season', name: 'app_team_season', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, Request $request, EntityManagerInterface $em, \App\Service\Statistics\SeasonStatistics $statistics, \App\Service\Statistics\StatisticsCharts $charts): Response
    {
        $team = $em->find(Team::class, $id) ?? throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted('TEAM_MANAGE', $team);
        $season = $statistics->load($team, $request->query->get('season'), $request->query->get('sort', 'points'));
        return $this->render('team/show.html.twig', [
            'team' => $team,
            'teamView' => match ($request->attributes->get('_route')) {
                'app_team_matches' => 'matches',
                'app_team_players' => 'players',
                'app_team_season' => 'season',
                default => 'all',
            },
            'season' => $season, 'summary' => $season['summary'], 'teamChart' => $charts->team($season['summary']),
            'players' => $season['players'],
            'matches' => array_reverse($season['summary']['games']),
            'pendingImports' => $em->getRepository(ImportJob::class)->findBy(['team'=>$team,'status'=>'pending'],['createdAt'=>'DESC']),
        ]);
    }

    #[Route('/teams/{id}/players/new', name: 'app_player_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function addPlayer(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $team = $em->find(Team::class, $id) ?? throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted('TEAM_MANAGE', $team);
        $player = (new Player())->setTeam($team);
        $form = $this->createForm(PlayerType::class, $player)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($player); $em->flush();
            $this->addFlash('success', 'Joueur ajouté à l’équipe.');
            return $this->redirectToRoute('app_team_show', ['id' => $id], 303);
        }
        return $this->render('team/form.html.twig', ['form' => $form, 'title' => 'Ajouter un joueur — '.$team->getName(), 'back' => $this->generateUrl('app_team_show', ['id' => $id])], new Response(status: $form->isSubmitted() ? 422 : 200));
    }

    #[Route('/teams/{teamId}/players/{id}', name: 'app_player_delete', requirements: ['teamId'=>"\d+", 'id'=>"\d+"], methods: ['POST'])]
    public function delete(int $teamId, int $id, Request $request, EntityManagerInterface $em): RedirectResponse
    {
        $team = $em->find(Team::class, $teamId) ?? throw $this->createNotFoundException('Équipe inconnue');
        $this->denyAccessUnlessGranted('TEAM_MANAGE', $team);

        $player = $em->find(Player::class, $id) ?? throw $this->createNotFoundException('Joueur inconnu');
        if ($player->getTeam()->getId() !== $teamId) { throw $this->createNotFoundException('Le joueur n\'appartient pas à cette équipe'); }

        if (!$this->isCsrfTokenValid('delete_player_'.$id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        if ($em->getRepository(PlayerMatchStat::class)->count(['player' => $player]) > 0) {
            $this->addFlash('error', 'Ce joueur possède des statistiques de match et ne peut pas être supprimé afin de préserver l’historique.');
            return $this->redirectToRoute('app_team_show', ['id' => $teamId], 303);
        }

        foreach ($em->getRepository(AiReport::class)->findBy(['team'=>$team,'scope'=>'player','target'=>$id]) as $report) { $em->remove($report); }
        $em->remove($player);
        $em->flush();

        $this->addFlash('success', 'Joueur supprimé avec succès.');
        return $this->redirectToRoute('app_team_show', ['id' => $teamId], 303);
    }
}
