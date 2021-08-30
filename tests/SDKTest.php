<?php 
declare(strict_types=1);
namespace Topsort;

use PHPUnit\Framework\TestCase;

final class SDKTest extends TestCase {
    public function testAuction(): void {
        $sdk = new SDK('babytuto', 'api_key');
        $response = $sdk->create_auction([], [], []);
        $result = $response->wait();
        $this->assertTrue(false);
    }
}


