<?php 
declare(strict_types=1);
namespace Topsort;

use PHPUnit\Framework\TestCase;

final class SDKTest extends TestCase {
    public function testAuction(): void {
        // my localhost engine
        $sdk = new SDK('babytuto', '6f62f7fd-8c6f-40f2-aa6e-10649638b2f9');
        $slots = [
            'sponsoredListings' => 1,
        ];
        $products = [
            [
                'productId' => '135333',
            ],
            [
                'productId' => '50761',
            ],
            [
                'productId' => '135317',
            ],
            [
                'productId' => '158661',
            ],
            [
                'productId' => '638695',
            ],
            [
                'productId' => 'MvWtm3pQigd5cWl',
            ],
            [
                'productId' => '9vwUuL8j6kFRZUp',
            ],
            [
                'productId' => 'UIYxpgBK70YHIe6',
            ],
            [
                'productId' => 'pXSs4VR5hRqQq6t',
            ],
            [
                'productId' => 'BqtJEJFdidkGIc0'
            ],
            [
                'productId' => 'W2yS70J0PYbVjg1'
            ]
        ]; 
        $session = [
            'sessionId' => '1234'
        ];
        $response = $sdk->create_auction($slots, $products, $session)->wait();
        print_r(json_encode($response, JSON_PRETTY_PRINT));
        $this->assertTrue(true);
    }
}


