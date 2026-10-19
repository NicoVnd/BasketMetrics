<?php

namespace App\Tests;

use App\Service\Import\{SafeZipReader,ImportException};
use PHPUnit\Framework\TestCase;

final class SafeZipReaderTest extends TestCase
{
    private array $paths=[];
    protected function tearDown(): void { foreach ($this->paths as $path) { unlink($path); } }
    private function zip(array $entries): string
    {
        $path=tempnam(sys_get_temp_dir(),'bm-zip-'); $this->paths[]=$path;
        $zip=new \ZipArchive(); $zip->open($path,\ZipArchive::OVERWRITE);
        foreach ($entries as $name=>$content) { $zip->addFromString($name,$content); } $zip->close(); return $path;
    }
    public function testPdfContentsAreReadWithoutExtractingArchivePaths(): void
    {
        self::assertSame(['resume.pdf'=>'%PDF-1.7 test'],(new SafeZipReader())->read($this->zip(['folder/resume.pdf'=>'%PDF-1.7 test'])));
    }
    public function testTraversalIsRejected(): void
    {
        $this->expectException(ImportException::class); (new SafeZipReader())->read($this->zip(['../escape.pdf'=>'%PDF-1.7 test']));
    }
    public function testDisguisedPdfIsRejected(): void
    {
        $this->expectException(ImportException::class); (new SafeZipReader())->read($this->zip(['resume.pdf'=>'a script, not a PDF']));
    }
    public function testHighlyCompressedArchiveIsRejected(): void
    {
        $this->expectException(ImportException::class); (new SafeZipReader())->read($this->zip(['resume.pdf'=>'%PDF-'.str_repeat('0',2*1024*1024)]));
    }
    public function testDuplicateBasenamesAreRejected(): void
    {
        $this->expectException(ImportException::class); (new SafeZipReader())->read($this->zip(['a/resume.pdf'=>'%PDF-1.7','b/resume.pdf'=>'%PDF-1.7']));
    }
}
