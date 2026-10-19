<?php

namespace App\Service\Import;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

final class ImportStorage
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private string $projectDir) {}
    public function directory(string $id): string
    {
        if (!preg_match('/^[a-f0-9]{32}$/D',$id)) { throw new \InvalidArgumentException('Invalid storage ID'); }
        return $this->projectDir.'/var/imports/'.$id;
    }
    public function create(string $id): string
    {
        $directory=$this->directory($id); (new Filesystem())->mkdir($directory,0700); return $directory;
    }
    public function remove(string $id): void { (new Filesystem())->remove($this->directory($id)); }
    public function document(string $id, int $index): string { return $this->directory($id).'/document-'.$index.'.pdf'; }
}
