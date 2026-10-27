<?php

namespace App\Tests;

use App\Service\Ai\AnalysisReportFormatter;
use PHPUnit\Framework\TestCase;

final class AnalysisReportFormatterTest extends TestCase
{
    public function testLocalNamesAndHomonymsPreserveExactReferences(): void
    {
        $names=['J1'=>'Julien (J1)','J10'=>'Julien (J10)','J2'=>'Alex'];
        self::assertSame('Julien (J1), Julien (J10) et Alex. J99 / AJ1 / J10bis',
            (new AnalysisReportFormatter())->format('J1, J10 et J2. J99 / AJ1 / J10bis',$names));
    }
}
