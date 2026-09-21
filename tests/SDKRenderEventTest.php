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

        $sdk->report_render([
            'resolvedBidId' => 'bid-123',
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
        // getOpaqueUserId() falls back to setcookie() when $_COOKIE isn't set, which fails
        // under the CLI SAPI once PHPUnit has already produced output. Seeding the cookie
        // takes the same short-circuit the SDK uses for a returning visitor.
        $_COOKIE['ts_opaque_user_id'] = 'cookie-user-123';

        $requestHistory = [];
        $sdk = $this->makeSdkWithMockClient($requestHistory);

        $sdk->report_render(['resolvedBidId' => 'bid-789'])->wait();

        unset($_COOKIE['ts_opaque_user_id']);

        $request = $requestHistory[0]['request'];
        $body = json_decode((string) $request->getBody(), true);
        $render = $body['renders'][0];

        $this->assertArrayHasKey('id', $render);
        $this->assertNotEmpty($render['id']);
        $this->assertArrayHasKey('occurredAt', $render);
        $this->assertNotEmpty($render['occurredAt']);
        $this->assertSame('cookie-user-123', $render['opaqueUserId']);
    }

    /**
     * @dataProvider missingResolvedBidIdProvider
     */
    public function testReportRenderSkipsWithoutResolvedBidId(array $data): void
    {
        $requestHistory = [];
        $sdk = $this->makeSdkWithMockClient($requestHistory);

        $result = $sdk->report_render($data);

        $this->assertNull($result);
        $this->assertCount(0, $requestHistory);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public function missingResolvedBidIdProvider(): array
    {
        return [
            'no resolvedBidId key' => [['opaqueUserId' => 'user-456']],
            'empty resolvedBidId' => [['resolvedBidId' => '', 'opaqueUserId' => 'user-456']],
        ];
    }
}
