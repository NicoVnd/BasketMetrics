<?php

namespace App\Tests;

use App\Service\Import\{FfbbParser,IdentityNormalizer,ImportException};
use PHPUnit\Framework\TestCase;

final class FfbbParserTest extends TestCase
{
    public function testAnonymizedPortraitLayout(): void
    {
        $f=json_decode(file_get_contents(__DIR__.'/Fixtures/ffbb/portrait.json'),true);
        $data=(new FfbbParser(new IdentityNormalizer()))->parse($f['summary'],$f['sheet'],[]);
        self::assertCount(8,$data['players']['home']); self::assertCount(10,$data['players']['away']);
        self::assertSame(['home'=>75,'away'=>34],$data['scores']);
        self::assertSame([12,29,19,15],array_column($data['periods'],'homePoints'));
        self::assertSame([8,7,10,9],array_column($data['periods'],'awayPoints'));
        self::assertSame('AA000001',$data['players']['home'][0]['licenseNumber']);
        self::assertSame(1262,$data['players']['home'][0]['secondsPlayed']); self::assertSame(0,$data['players']['home'][0]['points']);
        self::assertNull($data['players']['away'][5]['secondsPlayed']);
        self::assertSame(44,$data['metrics']['maxLead']['home']);
    }
    public function testUnsupportedLayoutIsRejected(): void
    {
        $this->expectException(ImportException::class);
        (new FfbbParser(new IdentityNormalizer()))->parse([['text'=>'autre document','words'=>[],'height'=>2500,'width'=>1767]],[],[]);
    }

    public function testDedicatedTeamReadingSurvivesLowConfidenceHeader(): void
    {
        $f=json_decode(file_get_contents(__DIR__.'/Fixtures/ffbb/portrait.json'),true);
        foreach ($f['summary'][0]['words'] as &$word) {
            if ($word['y'] < 100 && $word['x'] > 1100) {
                $word['confidence']=14;
                $word['text']='CLUBALPHA';
            }
        }
        unset($word);
        $f['summary'][0]['teamNames']=['home'=>'CLUB ALPHA','away'=>'CLUB BETA - 2'];
        $data=(new FfbbParser(new IdentityNormalizer()))->parse($f['summary'],$f['sheet'],[]);
        self::assertSame('CLUB ALPHA',$data['homeName']);
        self::assertSame('CLUB BETA - 2',$data['awayName']);
        self::assertSame(['home'=>75,'away'=>34],$data['scores']);
    }

    public function testUnreadableTeamsAreStillRejected(): void
    {
        $f=json_decode(file_get_contents(__DIR__.'/Fixtures/ffbb/portrait.json'),true);
        $f['summary'][0]['words']=array_values(array_filter($f['summary'][0]['words'],
            static fn(array $word): bool => $word['y'] >= 100));
        $this->expectException(ImportException::class);
        $this->expectExceptionMessage('Les noms des équipes ne sont pas lisibles');
        (new FfbbParser(new IdentityNormalizer()))->parse($f['summary'],$f['sheet'],[]);
    }
}
