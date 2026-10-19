<?php

namespace App\Service\Import;

/** Adapter for the FFBB e-Marque portrait summary / scoresheet layout, using word coordinates. */
final class FfbbParser
{
    private array $warnings = [];

    public function __construct(private IdentityNormalizer $identity) {}

    public function parse(array $summaryPages, array $sheetPages, array $files): array
    {
        $this->warnings = ['Lecture OCR : vérifiez les identités, les rapprochements et les chiffres avant validation. Une cellule illisible reste vide.'];
        $summary = $summaryPages[0] ?? throw new ImportException('Le résumé statistique est absent.');
        if (!str_contains($this->identity->name($summary['text']), 'locaux') || !str_contains($this->identity->name($summary['text']), 'visiteurs')) {
            throw new ImportException('La disposition de ce résumé FFBB n’est pas reconnue. Aucun match n’a été créé.');
        }
        $dateText = $this->zone($summary, .35, .06, .49, .09);
        $timeText = $this->zone($summary, .54, .06, .62, .09);
        preg_match('~(\d{2}/\d{2}/(?:\d{4}|\d{2}))~', $dateText, $date);
        preg_match('~(\d{1,2}:\d{2})~', $timeText, $time);
        $dateTime = null;
        if (isset($date[1], $time[1])) {
            $format = strlen($date[1]) === 8 ? '!d/m/y H:i' : '!d/m/Y H:i';
            $value = \DateTimeImmutable::createFromFormat($format, $date[1].' '.$time[1], new \DateTimeZone('Europe/Paris'));
            $errors = \DateTimeImmutable::getLastErrors();
            if ($value && (!$errors || (!$errors['warning_count'] && !$errors['error_count']))) { $dateTime = $value->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM); }
        }
        if ($dateTime === null) { $this->warnings[] = 'Date ou heure illisible : renseignez-les avant validation.'; }
        $reference = $this->zone($summary, .185, .075, .25, .095);
        $reference = preg_match('/\b\d{1,8}\b/', $reference, $ref) ? $ref[0] : null;
        $home = trim($this->zone($summary, .674, 0, .985, .019));
        $away = trim($this->zone($summary, .674, .019, .985, .039));
        $away = preg_replace('/\s+-\s*$/', '', $away);
        if (!$home || !$away) { throw new ImportException('Les noms des équipes ne sont pas lisibles dans le résumé.'); }
        $visitorY = .54;
        foreach ($summary['words'] as $word) { if ($this->identity->name($word['text']) === 'visiteurs' && $word['y'] / $summary['height'] > .3) { $visitorY = $word['y'] / $summary['height']; break; } }
        $players = ['home' => [], 'away' => []];
        foreach ($this->nameRows($summary) as $row) {
            $side = $row['y'] < $visitorY ? 'home' : 'away';
            $name = $this->zone($summary, .078, $row['y']-.006, .41, $row['y']+.006);
            if (!str_contains($name, ',')) { continue; }
            [$lastName, $firstName] = array_map('trim', explode(',', $name, 2));
            if (!$lastName || !$firstName) { $this->warnings[] = 'Une identité incomplète a été ignorée dans le résumé.'; continue; }
            $y = $row['y'];
            $stat = ['lastName' => $lastName, 'firstName' => $firstName, 'licenseNumber' => null, 'existingPlayer' => '', 'include' => true,
                'jerseyNumber' => $this->number($summary, .016, .078, $y),
                'secondsPlayed' => $this->duration($this->zone($summary, .475, $y-.006, .538, $y+.006)),
                'points' => $this->number($summary, .538, .602, $y), 'fieldGoalsMade' => $this->number($summary, .602, .666, $y),
                'threePtMade' => $this->number($summary, .666, .73, $y), 'twoPtInsideMade' => $this->number($summary, .73, .793, $y),
                'twoPtOutsideMade' => $this->number($summary, .793, .857, $y), 'freeThrowsMade' => $this->number($summary, .857, .921, $y),
                'fouls' => $this->number($summary, .921, .986, $y), 'starter' => null, 'captain' => null,
            ];
            $marker = $this->zone($summary, .41, $y-.006, .475, $y+.006);
            // A blank checkbox isn't inferred to be false after OCR.
            if (preg_match('/[xX×]/', $marker)) { $stat['starter'] = true; }
            $stat['twoPtMade'] = $stat['twoPtInsideMade'] !== null && $stat['twoPtOutsideMade'] !== null ? $stat['twoPtInsideMade'] + $stat['twoPtOutsideMade'] : null;
            foreach (['points', 'fouls', 'twoPtMade', 'threePtMade', 'freeThrowsMade'] as $field) {
                if ($stat[$field] === null) { $this->warnings[] = $lastName.' '.$firstName.' : « '.$field.' » illisible ou absent.'; }
            }
            $players[$side][] = $stat;
        }
        if (!$players['home'] || !$players['away']) { throw new ImportException('Les tableaux de joueurs de ce format ne sont pas reconnus. Aucun enregistrement automatique n’est possible.'); }
        $sheet = $sheetPages[0] ?? null;
        $periods = [];
        $scores = ['home' => null, 'away' => null];
        if ($sheet) {
            // The official sheet provides license IDs. Match by side + jersey only within this SAME source match.
            foreach ($players as $side => &$rows) {
                foreach ($rows as &$player) {
                    $licenseRow = $this->sheetLicenseRow($sheet, $side, $player);
                    if ($licenseRow) {
                        $player['licenseNumber'] = $licenseRow['license'];
                        $player['captain'] = str_contains($this->identity->name($licenseRow['name']), 'cap') ? true : null;
                    }
                }
                unset($player);
            }
            unset($rows);
            foreach ([[1,.127,.22,.868], [2,.355,.449,.868], [3,.127,.22,.885], [4,.355,.449,.885]] as [$number,$homeX,$awayX,$y]) {
                $periods[] = ['number' => $number, 'homePoints' => $this->number($sheet,$homeX-.025,$homeX+.025,$y,.008), 'awayPoints' => $this->number($sheet,$awayX-.025,$awayX+.025,$y,.008)];
            }
            $scores['home'] = $this->number($sheet,.632,.688,.868,.009);
            $scores['away'] = $this->number($sheet,.858,.914,.868,.009);
        }
        // Summary total rows provide an independent fallback and consistency check.
        foreach (['home', 'away'] as $side) {
            foreach ($this->totalRows($summary) as $y) {
                if (($y < $visitorY ? 'home' : 'away') !== $side) { continue; }
                $total = $this->number($summary,.538,.602,$y);
                if ($scores[$side] === null) { $scores[$side] = $total; }
                elseif ($total !== null && $total !== $scores[$side]) { $this->warnings[] = 'OCR : le score final de la feuille et celui du résumé diffèrent pour '.$side.'. Le total du résumé est proposé : vérifiez-le.'; $scores[$side] = $total; }
                break;
            }
        }
        $metrics = $this->metrics($summaryPages[1] ?? null);
        $details=[];
        if ($sheet) {
            foreach (['home'=>[.144,.452],'away'=>[.508,.816]] as $side=>[$clubY,$coachY]) {
                $clubRaw=$this->zone($sheet,.172,$clubY-.007,.417,$clubY+.007);
                $code=strtoupper(preg_replace('/[^A-Za-z0-9]/','',$clubRaw));
                $coach=$this->zone($sheet,.19,$coachY-.008,.56,$coachY+.008);
                $details[$side]=['clubCodeOcr'=>$code?:null,'color'=>$this->zone($sheet,.49,$clubY-.007,.594,$clubY+.007),
                    'coach'=>str_contains($coach,':')?trim(explode(':',$coach,2)[1]):null];
            }
        }
        return ['version' => 'ffbb-portrait-1', 'homeName' => $home, 'awayName' => $away, 'playedAt' => $dateTime,
            'reference' => $reference, 'venue' => $this->zone($summary,.675,.06,.98,.09),
            'competition' => $this->zone($summary,.02,.06,.177,.115), 'pool' => $this->zone($summary,.23,.094,.25,.111),
            'officials' => [$this->zone($summary,.405,.094,.534,.112), $this->zone($summary,.62,.094,.75,.112)],
            'scores' => $scores, 'periods' => $periods, 'players' => $players, 'metrics' => $metrics, 'teamDetails'=>$details,
            'files' => $files, 'warnings' => array_values(array_unique($this->warnings)),
            'limits' => ['Les positions de tirs et les courbes restent consultables dans les PDF originaux ; aucune tentative, passe ou rebond n’est déduit des images.'],
        ];
    }

    private function nameRows(array $page): array
    {
        $rows = [];
        foreach ($page['words'] as $word) {
            $x = ($word['x'] + $word['width']/2) / $page['width'];
            $y = ($word['y'] + $word['height']/2) / $page['height'];
            if ($x > .078 && $x < .41 && $y > .18 && $y < .80 && str_contains($word['text'], ',')) { $rows[(string) round($y, 3)] = ['y' => $y]; }
        }
        return array_values($rows);
    }

    private function totalRows(array $page): array
    {
        $ys = [];
        foreach ($page['words'] as $word) {
            if (strtolower($word['text']) === 'total' && $word['x'] / $page['width'] < .1) {
                $y = ($word['y'] + $word['height']/2) / $page['height'];
                if (str_contains($this->identity->name($this->zone($page, .015, $y-.006, .3, $y+.006)), 'totalequipe')) { $ys[] = $y; }
            }
        }
        sort($ys); return $ys;
    }

    public function zone(array $page, float $left, float $top, float $right, float $bottom, bool $numeric = false): string
    {
        $words = [];
        foreach (($numeric ? ($page['numericWords'] ?? []) : $page['words']) as $word) {
            if (!$numeric && $word['y']/$page['height'] < .12 && isset($word['confidence']) && $word['confidence'] < 15) { continue; }
            $x = ($word['x'] + $word['width']/2) / $page['width'];
            $y = ($word['y'] + $word['height']/2) / $page['height'];
            if ($x >= $left && $x <= $right && $y >= $top && $y <= $bottom) { $words[] = $word; }
        }
        usort($words, static fn($a,$b) => abs($a['y']-$b['y']) > max($a['height'],$b['height']) * .7 ? $a['y'] <=> $b['y'] : $a['x'] <=> $b['x']);
        return trim(implode(' ', array_column($words, 'text')));
    }

    private function number(array $page, float $left, float $right, float $y, float $tolerance = .006): ?int
    {
        foreach ($page['cells'] ?? [] as $cell) {
            $cx = ($cell['left']+$cell['right'])/2;
            $cy = ($cell['top']+$cell['bottom'])/2;
            if ($cx >= $left && $cx <= $right && abs($cy-$y) <= $tolerance && preg_match('/^\d{1,4}$/D',$cell['text'])) { return (int)$cell['text']; }
        }
        $text = $this->zone($page,$left,$y-$tolerance,$right,$y+$tolerance);
        // Only known zero glyph confusions are proposed as zero; an empty/ambiguous cell stays null.
        $clean = trim($text, " \t\n\r\0\x0B.()[]");
        if (preg_match('/^[oO0©]+$/u',$clean)) { return 0; }
        if (preg_match('/^\d{1,4}$/D',$clean)) { return (int)$clean; }
        $numeric = $this->zone($page,$left,$y-$tolerance,$right,$y+$tolerance,true);
        return preg_match('/^\d{1,4}$/D',$numeric) ? (int)$numeric : null;
    }

    private function duration(string $value): ?int
    {
        return preg_match('/\b(\d{1,3}):([0-5]\d)\b/',$value,$m) ? (int)$m[1]*60+(int)$m[2] : null;
    }

    private function sheetLicenseRow(array $sheet, string $side, array $player): ?array
    {
        $jersey = $player['jerseyNumber'];
        foreach ($sheet['words'] as $word) {
            $y = ($word['y']+$word['height']/2)/$sheet['height'];
            if ($word['x']/$sheet['width'] < .075 || $word['x']/$sheet['width'] > .183 || ($side === 'home' ? ($y < .245 || $y > .38) : ($y < .61 || $y > .78))) { continue; }
            $license = strtoupper(trim($word['text'],'-| '));
            if (strlen($license) === 8) { $license = substr($license,0,2).strtr(substr($license,2),['O'=>'0','I'=>'1']); }
            if (!preg_match('/^[A-Z]{2}\d{6}$/D',$license)) { continue; }
            $number = $this->number($sheet,.417,.451,$y,.005);
            $name = $this->zone($sheet,.185,$y-.005,.417,$y+.005);
            $expected = $this->identity->name($player['lastName']).$this->identity->name(mb_substr($player['firstName'],0,1));
            if (($jersey !== null && $number === $jersey) || str_starts_with($this->identity->name($name),$expected)) { return ['license'=>$license,'name'=>$name]; }
        }
        return null;
    }

    private function metrics(?array $page): array
    {
        if (!$page) { return []; }
        $values=[];
        foreach (['maxLead'=>.193,'maxRun'=>.208,'benchPoints'=>.223] as $key=>$y) {
            $values[$key]=['home'=>$this->number($page,.47,.53,$y,.007),'away'=>$this->number($page,.79,.85,$y,.007)];
        }
        $values['leadSeconds']=['home'=>$this->duration($this->zone($page,.46,.247,.54,.262)),'away'=>$this->duration($this->zone($page,.78,.247,.86,.262))];
        $values['ties']=$this->number($page,.64,.68,.24,.007);
        return $values;
    }
}
