<?php

namespace App\Service\Import;

final class SafeZipReader
{
    public const MAX_UPLOAD_BYTES = 20 * 1024 * 1024;

    /** @return array<string, string> PDF contents keyed by a safe basename. No archive paths are extracted. */
    public function read(string $path): array
    {
        if (!is_file($path) || filesize($path) > self::MAX_UPLOAD_BYTES) { throw new ImportException('Le ZIP dépasse la limite de 20 Mo.'); }
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::RDONLY) !== true) { throw new ImportException('Ce fichier n’est pas un ZIP lisible.'); }
        try {
            if ($zip->numFiles > 40) { throw new ImportException('L’archive contient trop de fichiers (40 maximum).'); }
            $files = []; $total = 0;
            for ($i = 0; $i < $zip->numFiles; ++$i) {
                $stat = $zip->statIndex($i);
                $name = str_replace('\\', '/', $stat['name']);
                if (str_contains($name, "\0") || str_starts_with($name, '/') || preg_match('~(^|/)\.\.(/|$)|^[A-Za-z]:~', $name)) { throw new ImportException('L’archive contient un chemin interdit.'); }
                $opsys = $attributes = 0;
                $zip->getExternalAttributesIndex($i, $opsys, $attributes);
                if ((($attributes >> 16) & 0170000) === 0120000) { throw new ImportException('Les liens symboliques ne sont pas acceptés.'); }
                $total += $stat['size'];
                if ($total > 64 * 1024 * 1024 || $stat['size'] > 10 * 1024 * 1024 || ($stat['size'] > 1024 * 1024 && $stat['size'] > max(1, $stat['comp_size']) * 100)) {
                    throw new ImportException('Archive trop volumineuse une fois décompressée.');
                }
                if (($stat['encryption_method'] ?? 0) !== 0) { throw new ImportException('Les archives chiffrées ne sont pas prises en charge.'); }
                if (str_ends_with($name, '/') || str_starts_with($name, '__MACOSX/')) { continue; }
                if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'pdf') { throw new ImportException('Le ZIP doit contenir uniquement des PDF.'); }
                $safeName = basename($name);
                if (isset($files[$safeName])) { throw new ImportException('Deux documents portent le même nom dans le ZIP.'); }
                $content = $zip->getFromIndex($i, 10 * 1024 * 1024 + 1);
                if ($content === false || strlen($content) !== $stat['size'] || !str_starts_with($content, '%PDF-')) { throw new ImportException('Un document n’est pas un PDF valide.'); }
                $files[$safeName] = $content;
            }
            if (!$files || count($files) > 8) { throw new ImportException('Le ZIP doit contenir de 1 à 8 PDF.'); }
            return $files;
        } finally { $zip->close(); }
    }
}
