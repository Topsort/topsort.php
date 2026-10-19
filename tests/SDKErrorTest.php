<?php
declare(strict_types=1);
namespace Topsort;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

final class SDKErrorTest extends TestCase
{
    private function makeSdkWithMockClient(array $queue): SDK
    {
        $client = new Client([
            'base_uri' => 'https://api.topsort.com',
            'handler' => HandlerStack::create(new MockHandler($queue)),
        ]);

        $sdk = new SDK('test-api-key');
        $clientProperty = new \ReflectionProperty(SDK::class, 'client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($sdk, $client);

        return $sdk;
    }

    private function reportClick(SDK $sdk): void
    {
        $sdk->report_click([
            'placement' => ['path' => '/categories/shoes'],
            'resolvedBidId' => 'bid-1',
            'opaqueUserId' => 'user-123',
        ])->wait();
    }

    public function testApiErrorBodyIsInTheMessage(): void
    {
        $body = '{"errCode":"bad_request","message":"opaqueUserId is required"}';
        $sdk = $this->makeSdkWithMockClient([new Response(400, [], $body)]);

        try {
            $this->reportClick($sdk);
            $this->fail('Expected TopsortException');
        } catch (TopsortException $e) {
            $this->assertSame('Event creation failed: Content: ' . $body, $e->getMessage());
        }
    }

    public function testEmptyErrorBodyFallsBackToTheGuzzleMessage(): void
    {
        $sdk = $this->makeSdkWithMockClient([new Response(500)]);

        try {
            $this->reportClick($sdk);
            $this->fail('Expected TopsortException');
        } catch (TopsortException $e) {
            $this->assertStringStartsWith('Event creation failed: Message:', $e->getMessage());
            $this->assertStringContainsString('500 Internal Server Error', $e->getMessage());
        }
    }

    public function testConnectionErrorNamesTheUrl(): void
    {
        $request = new Request('POST', 'https://api.topsort.com/v2/events');
        $sdk = $this->makeSdkWithMockClient([new ConnectException('timed out', $request)]);

        $this->expectException(TopsortException::class);
        $this->expectExceptionMessage('Event creation failed: Could not connect to https://api.topsort.com/v2/events');
        $this->reportClick($sdk);
    }
}
