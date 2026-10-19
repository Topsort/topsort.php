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

final class SDKErrorMessageTest extends TestCase
{
    /**
     * @param Response|\Throwable $response
     */
    private function makeSdkWithMockResponse($response): SDK
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

    public function testEmptyErrorBodyFallsBackToTheGuzzleMessage(): void
    {
        $sdk = $this->makeSdkWithMockResponse(new Response(500));

        try {
            $sdk->report_click(['resolvedBidId' => 'bid-123', 'opaqueUserId' => 'user-456'])->wait();
            $this->fail('Expected TopsortException');
        } catch (TopsortException $e) {
            $this->assertStringStartsWith('Event creation failed: Message:', $e->getMessage());
            $this->assertStringContainsString('500 Internal Server Error', $e->getMessage());
        }
    }

    public function testConnectionErrorNamesTheUrl(): void
    {
        $request = new Request('POST', 'https://api.topsort.com/v2/events');
        $sdk = $this->makeSdkWithMockResponse(new ConnectException('timed out', $request));

        $this->expectException(TopsortException::class);
        $this->expectExceptionMessage('Event creation failed: Could not connect to https://api.topsort.com/v2/events');

        $sdk->report_click(['resolvedBidId' => 'bid-123', 'opaqueUserId' => 'user-456'])->wait();
    }
}
