<?php

namespace App\Service\Ai;

final class AnalysisReportFormatter
{
    public function format(string $content, array $displayNames): string
    {
        return preg_replace_callback('/(?<![\p{L}\p{N}_])J\d+(?![\p{L}\p{N}_])/u',
            static fn(array $match): string => $displayNames[$match[0]] ?? $match[0], $content);
    }
}
