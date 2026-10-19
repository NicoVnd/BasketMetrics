<?php

namespace App\Service\Import;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;
use Psr\Log\LoggerInterface;

final class PdfOcrReader
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
        #[Autowire('%env(PDFTOPPM_BINARY)%')] private string $pdfToPpm,
        #[Autowire('%env(NODE_BINARY)%')] private string $node,
        private ?LoggerInterface $logger = null,
    ) {}

    public function read(string $pdf, int $pages, string $temporaryDirectory, string $mode): array
    {
        $prefix = $temporaryDirectory.'/page-'.bin2hex(random_bytes(5));
        $render = $this->process([$this->pdfToPpm, '-f', '1', '-l', (string) $pages, '-scale-to', '2500', '-png', $pdf, $prefix]);
        $render->setTimeout(15);
        try {
            $render->mustRun();
            $images = glob($prefix.'-*.png');
            natsort($images);
            if (count($images) !== $pages) { throw new \RuntimeException('Missing pages'); }
            $result = [];
            foreach ($images as $image) {
                $ocr = $this->process([$this->node, $this->projectDir.'/tools/ocr/recognize.cjs', $image, $result === [] ? $mode : 'other']);
                $ocr->setTimeout(45);
                $ocr->mustRun();
                $result[] = json_decode($ocr->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }
            return $result;
        } catch (\Throwable $error) {
            $this->logger?->error('Échec du processus OCR FFBB : {reason}', ['reason'=>$error->getMessage()]);
            throw new ImportException('La lecture OCR a échoué. Vérifiez Poppler, Node.js et les dépendances tools/ocr ; utilisez un export FFBB non protégé.', previous: $error);
        } finally {
            foreach (glob($prefix.'-*.png') as $file) { unlink($file); }
        }
    }

    private function process(array $command): Process
    {
        // OCR workers need OS paths (notably SystemRoot for Windows entropy), not application secrets.
        $environment=[];
        foreach (array_keys(array_merge(getenv(),$_ENV)) as $key) { $environment[$key]=false; }
        foreach (['PATH','Path','SystemRoot','SYSTEMROOT','WINDIR','TEMP','TMP','TMPDIR','HOME','USERPROFILE','APPDATA','LOCALAPPDATA','LANG'] as $key) {
            $value=getenv($key);
            if ($value!==false) { $environment[$key]=$value; }
        }
        return new Process($command,$this->projectDir,$environment);
    }
}
