<?php
declare(strict_types=1);
namespace Topsort;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

final class SDKOpaqueUserIdTest extends TestCase
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

    /**
     * @return array<string, array{callable(SDK, array): mixed, array}>
     */
    public function eventProvider(): array
    {
        $placement = ['path' => '/categories/shoes'];
        return [
            'click' => [function (SDK $sdk, array $data) { return $sdk->report_click($data); }, ['placement' => $placement, 'resolvedBidId' => 'bid-1']],
            'impression' => [function (SDK $sdk, array $data) { return $sdk->report_impressions($data); }, ['placement' => $placement, 'resolvedBidId' => 'bid-1']],
            'render' => [function (SDK $sdk, array $data) { return $sdk->report_render('bid-1', $data); }, ['placement' => $placement]],
            'purchase' => [function (SDK $sdk, array $data) { return $sdk->report_purchase($data); }, ['items' => [['productId' => 'p-1', 'unitPrice' => 100]]]],
        ];
    }

    /**
     * @dataProvider eventProvider
     */
    public function testMissingOpaqueUserIdThrowsWithoutSending(callable $report, array $data): void
    {
        $requestHistory = [];
        $sdk = $this->makeSdkWithMockClient($requestHistory);

        try {
            $report($sdk, $data);
            $this->fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('opaqueUserId', $e->getMessage());
        }
        $this->assertCount(0, $requestHistory);
        $this->assertArrayNotHasKey('ts_opaque_user_id', $_COOKIE);
    }

    /**
     * @dataProvider eventProvider
     */
    public function testEmptyOpaqueUserIdThrows(callable $report, array $data): void
    {
        $requestHistory = [];
        $sdk = $this->makeSdkWithMockClient($requestHistory);

        $this->expectException(\InvalidArgumentException::class);
        $report($sdk, $data + ['opaqueUserId' => '']);
    }

    /**
     * @dataProvider eventProvider
     */
    public function testProvidedOpaqueUserIdIsSent(callable $report, array $data): void
    {
        $requestHistory = [];
        $sdk = $this->makeSdkWithMockClient($requestHistory);

        $report($sdk, $data + ['opaqueUserId' => 'user-123'])->wait();

        $body = json_decode((string) $requestHistory[0]['request']->getBody(), true);
        $event = array_values($body)[0][0];
        $this->assertSame('user-123', $event['opaqueUserId']);
    }
}
