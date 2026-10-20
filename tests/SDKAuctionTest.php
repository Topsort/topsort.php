<?php
declare(strict_types=1);
namespace Topsort;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

final class SDKAuctionTest extends TestCase
{
    private function makeSdkWithMockClient(array &$requestHistory): SDK
    {
        $mock = new MockHandler([
            new Response(200, [], '{"results":[]}'),
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

    private static function sentAuction(array $requestHistory): array
    {
        $body = json_decode((string) $requestHistory[0]['request']->getBody(), true);
        return $body['auctions'][0];
    }

    public function testCreateAuctionSendsOpaqueUserId(): void
    {
        $requestHistory = [];
        $sdk = $this->makeSdkWithMockClient($requestHistory);

        $sdk->create_auction(1, ['p-1', 'p-2'], null, null, null, 'user-123')->wait();

        $auction = self::sentAuction($requestHistory);
        $this->assertSame('user-123', $auction['opaqueUserId']);
        $this->assertSame(['ids' => ['p-1', 'p-2']], $auction['products']);
    }

    public function testCreateAuctionWithoutOpaqueUserIdOmitsTheField(): void
    {
        $requestHistory = [];
        $sdk = $this->makeSdkWithMockClient($requestHistory);

        $sdk->create_auction(1, ['p-1'])->wait();

        $this->assertArrayNotHasKey('opaqueUserId', self::sentAuction($requestHistory));
    }

    public function testCreateBannerAuctionSendsOpaqueUserId(): void
    {
        $requestHistory = [];
        $sdk = $this->makeSdkWithMockClient($requestHistory);

        $sdk->create_banner_auction(['slots' => 1, 'slotId' => 'home-top', 'opaqueUserId' => 'user-123'])->wait();

        $auction = self::sentAuction($requestHistory);
        $this->assertSame('banners', $auction['type']);
        $this->assertSame('user-123', $auction['opaqueUserId']);
    }
}
