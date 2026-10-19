<?php

namespace App\Controller;

use App\Entity\{Team, ImportJob};
use App\Form\{ImportUploadType, ImportReviewType};
use App\Service\Import\{ImportException, ImportPreparer, ImportReviewBuilder, ImportCommitter, ImportConsistencyChecker, ImportStorage};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\{Request, Response, BinaryFileResponse};
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ImportController extends AbstractController
{
    #[Route('/teams/{id}/imports/new',name:'app_import_new',requirements:['id'=>'\d+'],methods:['GET','POST'])]
    public function upload(int $id, Request $request, EntityManagerInterface $em, ImportPreparer $preparer, RateLimiterFactoryInterface $importsLimiter): Response
    {
        $team=$em->find(Team::class,$id)??throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted('TEAM_MANAGE',$team);
        $form=$this->createForm(ImportUploadType::class,['side'=>'home'])->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if (!$importsLimiter->create((string)$this->getUser()->getId())->consume()->isAccepted()) {
                $form->addError(new FormError('Trop d’imports OCR. Réessayez dans 15 minutes.'));
            } else {
                /** @var UploadedFile $file */
                $file=$form->get('archive')->getData();
                try {
                    set_time_limit(180);
                    $job=$preparer->prepare($team,$file->getPathname(),$form->get('side')->getData());
                    if ($job->getStatus()==='complete') {
                        $this->addFlash('success','Ce ZIP a déjà été importé. Voici le match existant.');
                        return $this->redirectToRoute('app_match_show',['teamId'=>$id,'id'=>$job->getMatchGame()->getId()],303);
                    }
                    return $this->redirectToRoute('app_import_review',['id'=>$job->getId()],303);
                } catch (ImportException $error) { $form->addError(new FormError($error->getMessage())); }
            }
        }
        return $this->render('import/upload.html.twig',['team'=>$team,'form'=>$form],new Response(status:$form->isSubmitted()?422:200));
    }

    #[Route('/imports/{id}',name:'app_import_review',requirements:['id'=>'[a-f0-9]{32}'],methods:['GET','POST'])]
    public function review(string $id, Request $request, EntityManagerInterface $em, ImportReviewBuilder $builder, ImportCommitter $committer, ImportConsistencyChecker $checker): Response
    {
        $job=$em->find(ImportJob::class,$id)??throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted('TEAM_MANAGE',$job->getTeam());
        if ($job->getStatus()==='complete') { return $this->redirectToRoute('app_match_show',['teamId'=>$job->getTeam()->getId(),'id'=>$job->getMatchGame()->getId()]); }
        if ($job->getCreatedAt()<new \DateTimeImmutable('-24 hours')) { throw $this->createAccessDeniedException('Cet aperçu a expiré. Annulez-le depuis l’équipe et déposez à nouveau le ZIP.'); }
        $review=$builder->build($job);
        $form=$this->createForm(ImportReviewType::class,$review['data'],['player_choices'=>$review['player_choices'],'game_choices'=>$review['game_choices']])->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $game=$committer->commit($job,$form->getData());
                $this->addFlash('success','Import validé : le match, les joueurs et leurs statistiques sont enregistrés.');
                return $this->redirectToRoute('app_match_show',['teamId'=>$job->getTeam()->getId(),'id'=>$game->getId()],303);
            } catch (ImportException $error) { $form->addError(new FormError($error->getMessage())); }
        }
        $data=$form->getData();
        $warnings=$checker->readable(array_values(array_unique(array_merge($job->getPayload()['warnings'],$checker->warnings($data['game'],$data['players'],$data['periods'])))));
        return $this->render('import/review.html.twig',['job'=>$job,'team'=>$job->getTeam(),'payload'=>$job->getPayload(),'form'=>$form,'warnings'=>$warnings],new Response(status:$form->isSubmitted()?422:200));
    }

    #[Route('/imports/{id}/documents/{index}',name:'app_import_document',requirements:['id'=>'[a-f0-9]{32}','index'=>'\d+'],methods:['GET'])]
    public function document(string $id,int $index,EntityManagerInterface $em,ImportStorage $storage): Response
    {
        $job=$em->find(ImportJob::class,$id)??throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted('TEAM_MANAGE',$job->getTeam());
        $document=$job->getPayload()['files'][$index]??throw $this->createNotFoundException();
        $file=$storage->document($id,$index);
        if (!is_file($file)) { throw $this->createNotFoundException('Le document n’est plus disponible sur cet hébergement.'); }
        $response=new BinaryFileResponse($file);
        $response->headers->set('Content-Type','application/pdf');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE,'document-'.$index.'.pdf');
        return $response;
    }

    #[Route('/imports/{id}/cancel',name:'app_import_cancel',requirements:['id'=>'[a-f0-9]{32}'],methods:['POST'])]
    public function cancel(string $id,Request $request,EntityManagerInterface $em,ImportStorage $storage): Response
    {
        $job=$em->find(ImportJob::class,$id)??throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted('TEAM_MANAGE',$job->getTeam());
        if (!$this->isCsrfTokenValid('cancel_import_'.$id,$request->request->getString('_token'))) { throw $this->createAccessDeniedException(); }
        if ($job->getStatus()!=='pending') { throw $this->createAccessDeniedException('Un import validé ne peut pas être annulé ici.'); }
        $teamId=$job->getTeam()->getId();
        $em->remove($job); $em->flush(); $storage->remove($id);
        $this->addFlash('success','Aperçu annulé. Aucun match ni joueur n’a été créé.');
        return $this->redirectToRoute('app_team_show',['id'=>$teamId],303);
    }
}
