<?php

namespace App\Controller;

use App\Entity\{Team, Player, AiReport};
use App\Service\Ai\{AnalysisDataBuilder, GeminiAnalysisService, AnalysisException, AnalysisReportFormatter};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

final class AnalysisController extends AbstractController
{
    #[Route('/teams/{teamId}/analysis/{scope}/{target}', name: 'app_analysis', requirements: ['teamId'=>'\d+','scope'=>'match|season|player','target'=>'\d+'], methods: ['GET','POST'])]
    public function show(int $teamId,string $scope,int $target,Request $request,EntityManagerInterface $em,AnalysisDataBuilder $builder,GeminiAnalysisService $gemini,RateLimiterFactoryInterface $aiLimiter): Response
    {
        $team=$em->find(Team::class,$teamId)??throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted('TEAM_MANAGE',$team);
        if ($scope==='player') {
            $player=$em->find(Player::class,$target)??throw $this->createNotFoundException();
            if ($player->getTeam()->getId()!==$teamId) { throw $this->createNotFoundException(); }
            $context=$builder->buildPlayer($team,$player,$request->query->get('season'));
        } else { $context=$builder->build($team,$scope,$target); }
        $criteria=['team'=>$team,'scope'=>$scope,'target'=>$target,'season'=>$context['season']];
        $report=$em->getRepository(AiReport::class)->findOneBy($criteria,['generatedAt'=>'DESC','id'=>'DESC']);
        $error=null; $status=200;
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('analysis_'.$scope.'_'.$target.'_'.$context['season'],$request->request->getString('_token'))) { throw $this->createAccessDeniedException(); }
            try {
                if (!$context['hasData']) { throw new AnalysisException('Aucun match terminé dans cette période : ajoutez des données avant de demander une analyse.'); }
                if (!$gemini->isConfigured()) { throw new AnalysisException('Configurez GEMINI_API_KEY et GEMINI_MODEL dans .env.local pour activer l’analyse.'); }
                if (!$aiLimiter->create((string)$this->getUser()->getId())->consume()->isAccepted()) { $status=429; throw new AnalysisException('Limite de demandes atteinte. Réessayez dans une heure.'); }
                $content=$gemini->generate($context['data']);
                $new=new AiReport($team,$scope,$target,$context['season'],$gemini->getModel(),GeminiAnalysisService::PROMPT_VERSION,$context['hash'],$content);
                $em->persist($new); $em->flush();
                $this->addFlash('success','Analyse générée et enregistrée.');
                return $this->redirectToRoute('app_analysis',['teamId'=>$teamId,'scope'=>$scope,'target'=>$target,'season'=>$context['season']],303);
            } catch (AnalysisException $e) { $error=$e->getMessage(); $status=$status===429?429:422; }
        }
        return $this->render('analysis/show.html.twig',['team'=>$team,'scope'=>$scope,'target'=>$target,'context'=>$context,'report'=>$report,'reportContent'=>$report ? (new AnalysisReportFormatter())->format($report->getContent(),$context['displayNames']) : null,'configured'=>$gemini->isConfigured(),'error'=>$error,'stale'=>$report && ($report->getDataHash()!==$context['hash'] || $report->getPromptVersion()!==GeminiAnalysisService::PROMPT_VERSION || $report->getModel()!==$gemini->getModel())],new Response(status:$status));
    }
}
