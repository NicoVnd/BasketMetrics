<?php

namespace App\Service\Import;

use Symfony\Component\String\Slugger\AsciiSlugger;

final class IdentityNormalizer
{
    public function name(string $name): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string) (new AsciiSlugger())->slug($name)));
    }
    public function license(?string $license): ?string
    {
        $value = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $license ?? ''));
        return $value === '' ? null : $value;
    }
}
