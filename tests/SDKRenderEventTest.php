<?php
declare(strict_types=1);
namespace Topsort;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

final class SDKRenderEventTest extends TestCase
{
    private function makeSdkWithMockClient(array &$requestHistory): SDK
    {
        $mock = new MockHandler([
            new Response(204),
        ]);
        $history = Middleware::history($requestHistory);
        $stack = HandlerStack::create($mock);
        $stack->push($history);

        $client = new Client([
            'base_uri' => 'https://api.topsort.com',
            'handler' => $stack,
        ]);

        $sdk = new SDK('test-api-key');
        $clientProperty = new \ReflectionProperty(SDK::class, 'client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($sdk, $client);

        return $sdk;
    }

    public function testReportRenderSendsRendersArray(): void
    {
        $requestHistory = [];
        $sdk = $this->makeSdkWithMockClient($requestHistory);

        $sdk->report_render('bid-123', [
            'opaqueUserId' => 'user-456',
        ])->wait();

        $this->assertCount(1, $requestHistory);

        $request = $requestHistory[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/v2/events', $request->getUri()->getPath());

        $body = json_decode((string) $request->getBody(), true);
        $this->assertArrayHasKey('renders', $body);
        $this->assertArrayNotHasKey('impressions', $body);
        $this->assertArrayNotHasKey('clicks', $body);
        $this->assertCount(1, $body['renders']);

        $render = $body['renders'][0];
        $this->assertSame('bid-123', $render['resolvedBidId']);
        $this->assertSame('user-456', $render['opaqueUserId']);
    }

    public function testReportRenderFillsDefaults(): void
    {
        $requestHistory = [];
        $sdk = $this->makeSdkWithMockClient($requestHistory);

        $sdk->report_render('bid-789', ['opaqueUserId' => 'user-123'])->wait();

        $request = $requestHistory[0]['request'];
        $body = json_decode((string) $request->getBody(), true);
        $render = $body['renders'][0];

        $this->assertArrayHasKey('id', $render);
        $this->assertNotEmpty($render['id']);
        $this->assertArrayHasKey('occurredAt', $render);
        $this->assertIsString($render['occurredAt']);
        $this->assertNotFalse(
            \DateTime::createFromFormat(\DateTimeInterface::RFC3339_EXTENDED, $render['occurredAt'])
        );
        $this->assertSame('cookie-user-123', $render['opaqueUserId']);
    }

    public function testReportRenderSerializesProvidedOccurredAt(): void
    {
        $requestHistory = [];
        $sdk = $this->makeSdkWithMockClient($requestHistory);

        $sdk->report_render('bid-123', [
            'opaqueUserId' => 'user-456',
            'occurredAt' => new \DateTime('2026-10-19T12:00:00.250Z'),
        ])->wait();

        $body = json_decode((string) $requestHistory[0]['request']->getBody(), true);

        $this->assertSame('2026-10-19T12:00:00.250+00:00', $body['renders'][0]['occurredAt']);
    }
}
