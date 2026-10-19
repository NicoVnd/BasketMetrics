<?php

namespace App\Service\Import;

use App\Entity\{Team, ImportJob};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Smalot\PdfParser\Parser;

final class ImportPreparer
{
    public function __construct(private SafeZipReader $zipReader, private PdfOcrReader $ocr, private FfbbParser $parser, private ImportStorage $storage, private EntityManagerInterface $em) {}

    public function prepare(Team $team, string $zipPath, string $side): ImportJob
    {
        if (!in_array($side,['home','away'],true)) { throw new ImportException('Choisissez votre équipe dans le document.'); }
        $files = $this->zipReader->read($zipPath);
        // Content hash is insensitive to ZIP order, timestamps and directory names.
        $pdfHashes=array_map(fn($content)=>hash('sha256',$content),array_values($files)); sort($pdfHashes);
        $hash=hash('sha256',implode('|',$pdfHashes));
        $existing=$this->em->getRepository(ImportJob::class)->findOneBy(['team'=>$team,'sourceHash'=>$hash]);
        if ($existing) {
            if ($existing->getStatus()==='pending' && ($existing->getPayload()['side']??null)!==$side) { throw new ImportException('Ce ZIP est déjà en prévisualisation avec l’autre côté. Reprenez ou annulez cet import avant de changer de côté.'); }
            return $existing;
        }
        $job=(new ImportJob())->setTeam($team)->setSourceHash($hash);
        $directory=$this->storage->create($job->getId());
        try {
            $documents=[]; $summaryPath=null; $sheetPath=null; $totalPages=0;
            foreach ($files as $name=>$contents) {
                $index=count($documents);
                $pdf=$this->storage->document($job->getId(),$index);
                file_put_contents($pdf,$contents,LOCK_EX); chmod($pdf,0600);
                try { $count=count((new Parser())->parseContent($contents)->getPages()); }
                catch (\Throwable $error) { throw new ImportException('Un PDF est illisible ou protégé.',previous:$error); }
                $totalPages+=$count;
                if ($count < 1 || $count>20 || $totalPages>30) { throw new ImportException('Trop de pages dans les PDF (20 par document, 30 au total).'); }
                $type=str_starts_with(strtolower($name),'resume')?'summary':(str_starts_with(strtolower($name),'feuillematch')?'sheet':'other');
                if ($type==='summary') { if ($summaryPath!==null) { throw new ImportException('Plusieurs résumés détectés : importez un seul match à la fois.'); } $summaryPath=[$pdf,min(2,$count)]; }
                if ($type==='sheet') { if ($sheetPath!==null) { throw new ImportException('Plusieurs feuilles détectées : importez un seul match à la fois.'); } $sheetPath=$pdf; }
                $documents[]=['name'=>$name,'index'=>$index,'pages'=>$count,'type'=>$type];
            }
            if ($summaryPath===null) { throw new ImportException('Le ZIP doit contenir le PDF « resume…pdf » de l’export e-Marque.'); }
            $summary=$this->ocr->read($summaryPath[0],$summaryPath[1],$directory,'summary');
            $sheet=$sheetPath?$this->ocr->read($sheetPath,1,$directory,'sheet'):[];
            $payload=$this->parser->parse($summary,$sheet,$documents);
            $payload['side']=$side;
            $job->setPayload($payload);
            $this->em->persist($job); $this->em->flush();
            return $job;
        } catch (UniqueConstraintViolationException $error) {
            $this->storage->remove($job->getId());
            throw new ImportException('Cet import vient déjà d’être préparé. Revenez à l’équipe puis reprenez l’import.',previous:$error);
        } catch (\Throwable $error) {
            $this->storage->remove($job->getId());
            if ($error instanceof ImportException) { throw $error; }
            throw new ImportException('Impossible de préparer cet import. Aucun match n’a été créé.',previous:$error);
        }
    }
}
