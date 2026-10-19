<?php
declare(strict_types=1);
namespace Topsort;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

final class SDKErrorMessageTest extends TestCase
{
    private function makeSdkWithMockResponse(Response $response): SDK
    {
        $client = new Client([
            'base_uri' => 'https://api.topsort.com',
            'handler' => HandlerStack::create(new MockHandler([$response])),
        ]);

        $sdk = new SDK('test-api-key');
        $clientProperty = new \ReflectionProperty(SDK::class, 'client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($sdk, $client);

        return $sdk;
    }

    public function testApiErrorBodyIsIncludedInExceptionMessage(): void
    {
        $errorBody = '{"errors":[{"error":"bad_request","message":"invalid resolvedBidId"}]}';
        $sdk = $this->makeSdkWithMockResponse(new Response(400, [], $errorBody));

        try {
            $sdk->report_click(['resolvedBidId' => 'bid-123', 'opaqueUserId' => 'user-456'])->wait();
            $this->fail('Expected TopsortException');
        } catch (TopsortException $e) {
            $this->assertSame('Event creation failed: Content: ' . $errorBody, $e->getMessage());
        }
    }

    public function testInvalidEventTypeIsNamedInExceptionMessage(): void
    {
        $sdk = $this->makeSdkWithMockResponse(new Response(204));
        $createEvent = new \ReflectionMethod(SDK::class, 'create_event');
        $createEvent->setAccessible(true);

        $this->expectExceptionMessage('Invalid event type: pageview');

        $createEvent->invoke($sdk, 'pageview', ['opaqueUserId' => 'user-456']);
    }
}
