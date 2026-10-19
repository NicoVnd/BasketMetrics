<?php

namespace App\Tests;

use App\Service\Ai\{GeminiAnalysisService, AnalysisException};
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GeminiAnalysisServiceTest extends TestCase
{
    public function testRequestAndResponse(): void
    {
        $client=new MockHttpClient(function ($method,$url,$options) {
            self::assertSame('POST',$method);
            self::assertSame('https://generativelanguage.googleapis.com/v1beta/models/test-model:generateContent',$url);
            self::assertStringNotContainsString('private-key',$url);
            $body=json_decode($options['body'],true,512,JSON_THROW_ON_ERROR);
            self::assertSame(GeminiAnalysisService::PROMPT,$body['systemInstruction']['parts'][0]['text']);
            self::assertSame(['points'=>null],json_decode($body['contents'][0]['parts'][0]['text'],true));
            return new MockResponse(json_encode(['candidates'=>[['finishReason'=>'STOP','content'=>['parts'=>[['text'=>'Faits mesurés : données insuffisantes.']]]]]],JSON_THROW_ON_ERROR));
        });
        $service=new GeminiAnalysisService($client,'private-key','test-model');
        self::assertSame('Faits mesurés : données insuffisantes.',$service->generate(['points'=>null]));
    }

    public function testMissingConfigurationDoesNotCallProvider(): void
    {
        $service=new GeminiAnalysisService(new MockHttpClient(fn()=>self::fail('No network expected')),'','');
        self::assertFalse($service->isConfigured());
        $this->expectException(AnalysisException::class);
        $service->generate([]);
    }

    public function testProviderFailuresAndIncompleteResponses(): void
    {
        foreach ([[429,'{}'],[403,'{}'],[404,'{}'],[503,'{}'],[200,'invalid json'],[200,'{}'],[200,'{"candidates":[{"finishReason":"MAX_TOKENS","content":{"parts":[{"text":"partial"}]}}]}']] as [$status,$body]) {
            $service=new GeminiAnalysisService(new MockHttpClient(new MockResponse($body,['http_code'=>$status])),'key','model');
            try { $service->generate([]); self::fail('Invalid response must be rejected.'); }
            catch (AnalysisException $e) { self::assertStringNotContainsString($body,$e->getMessage()); }
        }
    }
}
