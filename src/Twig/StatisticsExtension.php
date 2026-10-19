<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class StatisticsExtension extends AbstractExtension
{
    public function getFilters(): array { return [new TwigFilter('game_time',[$this,'time'])]; }
    public function time(int|float|null $seconds): string
    {
        if ($seconds===null) { return '—'; }
        $value=(int)round($seconds);
        return sprintf('%d:%02d',intdiv($value,60),$value%60);
    }
}
