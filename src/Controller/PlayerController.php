<?php

namespace App\Controller;

use App\Entity\{Player, Team};
use App\Service\Statistics\{SeasonStatistics, StatisticsCharts};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

final class PlayerController extends AbstractController
{
    #[Route('/teams/{teamId}/players/{id}', name: 'app_player_show', requirements: ['teamId'=>'\d+', 'id'=>'\d+'], methods: ['GET'])]
    public function show(int $teamId, int $id, Request $request, EntityManagerInterface $em, SeasonStatistics $statistics, StatisticsCharts $charts): Response
    {
        $team=$em->find(Team::class,$teamId) ?? throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted('TEAM_MANAGE',$team);
        $player=$em->find(Player::class,$id) ?? throw $this->createNotFoundException();
        if ($player->getTeam()->getId()!==$teamId) { throw $this->createNotFoundException(); }
        $season=$statistics->load($team,$request->query->get('season'));
        $profile=$statistics->profile($player,$season);
        return $this->render('player/show.html.twig',['team'=>$team,'player'=>$player,'season'=>$season,'profile'=>$profile,'playerChart'=>$charts->player($profile['history'])]);
    }
}
