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
    /** @var MockHandler */
    private $mock;

    private function makeSdkWithMockClient(array &$requestHistory): SDK
    {
        // The handler shifts a response off this queue as soon as a request is dispatched,
        // even if the promise is never waited on, so its count shows whether anything was sent.
        $this->mock = new MockHandler([
            new Response(204),
        ]);
        $history = Middleware::history($requestHistory);
        $stack = HandlerStack::create($this->mock);
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
    private static function events(): array
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
     * @return array<string, array{callable(SDK, array): mixed, array}>
     */
    public function eventProvider(): array
    {
        return self::events();
    }

    /**
     * @return array<string, array{callable(SDK, array): mixed, array}>
     */
    public function invalidOpaqueUserIdProvider(): array
    {
        $invalid = [
            'missing' => [],
            'null' => ['opaqueUserId' => null],
            'empty' => ['opaqueUserId' => ''],
            'blank' => ['opaqueUserId' => '   '],
            'int' => ['opaqueUserId' => 12345],
            'false' => ['opaqueUserId' => false],
            'array' => ['opaqueUserId' => ['user-123']],
        ];
        $cases = [];
        foreach (self::events() as $event => [$report, $data]) {
            foreach ($invalid as $name => $extra) {
                $cases["$event, $name"] = [$report, $extra + $data];
            }
        }
        return $cases;
    }

    /**
     * @dataProvider invalidOpaqueUserIdProvider
     */
    public function testInvalidOpaqueUserIdThrowsWithoutSending(callable $report, array $data): void
    {
        $requestHistory = [];
        $sdk = $this->makeSdkWithMockClient($requestHistory);

        try {
            $report($sdk, $data);
            $this->fail('Expected InvalidEventException');
        } catch (InvalidEventException $e) {
            $this->assertStringContainsString('opaqueUserId', $e->getMessage());
        }
        $this->assertCount(1, $this->mock, 'no request should have been dispatched');
    }

    public function testRenderWithoutOptionsThrowsInvalidEventException(): void
    {
        $requestHistory = [];
        $sdk = $this->makeSdkWithMockClient($requestHistory);

        $this->expectException(InvalidEventException::class);
        $sdk->report_render('bid-1');
    }

    public function testInvalidEventExceptionIsATopsortException(): void
    {
        // Callers following the README catch TopsortException around report_*()->wait().
        $this->assertInstanceOf(TopsortException::class, new InvalidEventException('x'));
    }

    /**
     * @dataProvider eventProvider
     */
    public function testProvidedOpaqueUserIdIsSent(callable $report, array $data): void
    {
        $requestHistory = [];
        $sdk = $this->makeSdkWithMockClient($requestHistory);

        $report($sdk, $data + ['opaqueUserId' => 'user-123'])->wait();

        $this->assertCount(0, $this->mock);
        $body = json_decode((string) $requestHistory[0]['request']->getBody(), true);
        $event = array_values($body)[0][0];
        $this->assertSame('user-123', $event['opaqueUserId']);
    }
}
