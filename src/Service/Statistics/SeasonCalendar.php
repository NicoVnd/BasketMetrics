<?php

namespace App\Service\Statistics;

final class SeasonCalendar
{
    public function year(\DateTimeInterface $date): int
    {
        $local=\DateTimeImmutable::createFromInterface($date)->setTimezone(new \DateTimeZone('Europe/Paris'));
        return (int)$local->format('Y')-((int)$local->format('n')<7?1:0);
    }
    public function currentYear(): int { return $this->year(new \DateTimeImmutable('now',new \DateTimeZone('Europe/Paris'))); }
    public function bounds(int $year): array
    {
        if ($year<1900 || $year>2200) { throw new \InvalidArgumentException('Saison invalide.'); }
        $start=new \DateTimeImmutable($year.'-07-01 00:00:00',new \DateTimeZone('Europe/Paris'));
        return [$start->setTimezone(new \DateTimeZone('UTC')),$start->modify('+1 year')->setTimezone(new \DateTimeZone('UTC'))];
    }
    public function label(int $year): string { return $year.'–'.($year+1); }
}
