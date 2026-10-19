<?php

namespace App\Controller;

use App\Entity\{Team, Player, MatchGame, PlayerMatchStat, PeriodScore, ImportJob, AiReport};
use App\Form\{MatchGameType, PlayerMatchStatType};
use App\Service\Import\ImportStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Valid;

final class MatchController extends AbstractController
{
    #[Route('/teams/{teamId}/matches/{id}/delete', name: 'app_match_delete', requirements: ['teamId' => '\d+', 'id' => '\d+'], methods: ['POST'])]
    public function delete(int $teamId, int $id, Request $request, EntityManagerInterface $em, ImportStorage $storage): Response
    {
        $game = $em->find(MatchGame::class, $id) ?? throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted('TEAM_MANAGE', $game->getTeam());
        if ($game->getTeam()->getId() !== $teamId) { throw $this->createNotFoundException(); }
        if (!$this->isCsrfTokenValid('delete_match_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $jobs = $em->getRepository(ImportJob::class)->findBy(['matchGame' => $game]);
        $em->wrapInTransaction(function () use ($em, $game, $jobs): void {
            foreach ([PlayerMatchStat::class, PeriodScore::class] as $entityClass) {
                foreach ($em->getRepository($entityClass)->findBy(['matchGame' => $game]) as $entity) {
                    $em->remove($entity);
                }
            }
            foreach ($jobs as $job) { $em->remove($job); }
            foreach ($em->getRepository(AiReport::class)->findBy(['team'=>$game->getTeam(),'scope'=>'match','target'=>$game->getId()]) as $report) { $em->remove($report); }
            $em->remove($game);
        });
        foreach ($jobs as $job) { $storage->remove($job->getId()); }

        $this->addFlash('success', 'Match supprimé avec ses statistiques et ses documents importés.');
        return $this->redirectToRoute('app_team_show', ['id' => $teamId], 303);
    }

    #[Route('/teams/{id}/matches/new', name: 'app_match_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function create(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $team = $em->find(Team::class, $id) ?? throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted('TEAM_MANAGE', $team);
        $game = (new MatchGame())->setTeam($team);
        $form = $this->createForm(MatchGameType::class, $game)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($game); $em->flush();
            $this->addFlash('success', 'Match créé. Vous pouvez saisir les statistiques des joueurs.');
            return $this->redirectToRoute('app_match_show', ['teamId' => $id, 'id' => $game->getId()], 303);
        }
        return $this->render('team/form.html.twig', ['form' => $form, 'title' => 'Créer un match — '.$team->getName(), 'back' => $this->generateUrl('app_team_show', ['id' => $id])], new Response(status: $form->isSubmitted() ? 422 : 200));
    }

    #[Route('/teams/{teamId}/matches/{id}', name: 'app_match_show', requirements: ['teamId' => '\d+', 'id' => '\d+'], methods: ['GET', 'POST'])]
    public function show(int $teamId, int $id, Request $request, EntityManagerInterface $em): Response
    {
        $game = $em->find(MatchGame::class, $id) ?? throw $this->createNotFoundException();
        $team = $game->getTeam();
        $this->denyAccessUnlessGranted('TEAM_MANAGE', $team);
        if ($team->getId() !== $teamId) { throw $this->createNotFoundException(); }
        $players = $em->getRepository(Player::class)->findBy(['team' => $team], ['lastName' => 'ASC', 'firstName' => 'ASC', 'id' => 'ASC']);
        $existing = [];
        foreach ($em->getRepository(PlayerMatchStat::class)->findBy(['matchGame' => $game]) as $stat) {
            $existing[$stat->getPlayer()->getId()] = $stat;
        }
        $stats = [];
        foreach ($players as $player) {
            $stats[$player->getId()] = $existing[$player->getId()] ?? (new PlayerMatchStat())->setMatchGame($game)->setPlayer($player);
        }
        $form = $this->createFormBuilder(['stats' => $stats], ['csrf_token_id' => 'match_stats_'.$id])
            ->add('stats', CollectionType::class, ['entry_type' => PlayerMatchStatType::class, 'entry_options' => ['label' => false], 'allow_add' => false, 'allow_delete' => false, 'constraints' => [new Valid()]])
            ->getForm()->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($stats as $stat) { $em->persist($stat); }
            $em->flush();
            $this->addFlash('success', 'Statistiques enregistrées.');
            return $this->redirectToRoute('app_match_show', ['teamId' => $teamId, 'id' => $id], 303);
        }
        $warnings = [];
        $sum = 0;
        $known = 0;
        foreach ($stats as $stat) {
            $warnings = array_merge($warnings, $stat->getWarnings());
            if ($stat->getPoints() !== null) { $sum += $stat->getPoints(); ++$known; }
        }
        if ($game->getTeamScore() !== null && $known > 0 && $sum !== $game->getTeamScore()) {
            $warnings[] = sprintf('Les points individuels renseignés totalisent %d, pour un score d’équipe de %d. Vérifiez la saisie et les joueurs manquants.', $sum, $game->getTeamScore());
        }
        $periods=$em->getRepository(PeriodScore::class)->findBy(['matchGame'=>$game],['periodNumber'=>'ASC']);
        $importJob=$em->getRepository(ImportJob::class)->findOneBy(['matchGame'=>$game,'status'=>'complete']);
        return $this->render('match/show.html.twig', ['team' => $team, 'game' => $game, 'stats' => $stats, 'form' => $form, 'warnings' => $warnings,'periods'=>$periods,'importJob'=>$importJob], new Response(status: $form->isSubmitted() ? 422 : 200));
    }

    #[Route('/teams/{teamId}/matches/{id}/edit', name: 'app_match_edit', requirements: ['teamId' => '\d+', 'id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $teamId, int $id, Request $request, EntityManagerInterface $em): Response
    {
        $game = $em->find(MatchGame::class, $id) ?? throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted('TEAM_MANAGE', $game->getTeam());
        if ($game->getTeam()->getId() !== $teamId) { throw $this->createNotFoundException(); }
        $form = $this->createForm(MatchGameType::class, $game)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Match mis à jour.');
            return $this->redirectToRoute('app_match_show', ['teamId' => $teamId, 'id' => $id], 303);
        }
        return $this->render('team/form.html.twig', ['form' => $form, 'title' => 'Modifier le match', 'back' => $this->generateUrl('app_match_show', ['teamId' => $teamId, 'id' => $id])], new Response(status: $form->isSubmitted() ? 422 : 200));
    }
}
