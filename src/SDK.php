<?php 
declare(strict_types=1);
namespace Topsort;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\RequestResponse;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;

/**
*  A sample class
*
*  Use this section to define what this class is doing, the PHPDocumentator will use this
*  to automatically generate an API documentation using this information.
*
*  @author Pablo Reszczynski
*/
class SDK {

   // TODO: make it work with staging or demo envs
   /** @var string */
   private static $base_url = '.topsort.com';
   /** @var string */
   private $marketplace;
   /** @var string */
   private $api_key;
   /** @var Client */
   private $client;


   public function __construct(string $marketplace, string $api_key) {
      $this->marketplace = $marketplace;
      $this->api_key = $api_key;
      $this->client = new Client([
         'base_uri' => 'localhost:8080',
        //'base_uri' => 'https://topsort.com',
        'headers' => [
            'Authorization' => $api_key,
        ],
      ]);
   }

   /**
    * Creates an auction between products for promotion slots. The winners are returned. 
    * The winners should be promoted on the website by moving the products up in the results 
    * list or rendering them in a special location on the page.
    *
    * @psalm-type Slots=array{sponsoredListings?: int, videoAds?: int, bannerAds?: int}
    * @psalm-type Product=array{productId: string, quality?: string}
    * @psalm-type Session=array{sessionId: string, consumerId?: string, orderIntentId?: string, orderId?: string}
    *
    * @param Slots $slots
    * @param array<Product> $products
    * @param Session $session
    * @return PromiseInterface
    */
   public function create_auction(array $slots, array $products, array $session) {
      $body = [
         'slots' => $slots,
         'products' => $products,
         'session' => $session,
      ];
      return $this->client->requestAsync('POST', '/v1/auctions', [
         'json' => $body
     ])->then(
       $this->handleResponse(),
       $this->handleException('Auction creation failed')
     );
   }

   /**
    * Returns an earlier auction result.
    *
    * @return PromiseInterface
    */
   public function get_auction(string $auction_id) {
      return $this->client->requestAsync('GET', '/v1/auctions' . $auction_id)->then(
         $this->handleResponse(),
         $this->handleException('Failed to get auction')
      );
   }

   /**
    * All events are described by a single JSON object, an ImpressionEvent, ClickEvent 
    * or PurchaseEvent. All event types have an eventType field and an id field. 
    * id is supplied by the marketplace.
    *
    * @param 'ImpressionEvent'|'ClickEvent'|'PurchaseEvent' $event_type
    * @param array $data
    * @return PromiseInterface
    */
   public function create_event(string $event_type, array $data) {
      return $this->client->requestAsync('POST', '/v1/events', [
         'json' => array_merge([ 'eventType' => $event_type ], $data)
      ])->then(
         $this->handleResponse(),
         $this->handleException('Event creation failed')
      );
   }

   /**  
    * @return callable(ResponseInterface): array
   */
   private function handleResponse() {
      return function(ResponseInterface $res) {
         return json_decode($res->getBody()->getContents());
      };
   }

   /**  
    * @return callable(RequestException): void
   */
   private function handleException(string $message) {
      return function(RequestException $err) use ($message) {
         throw new \Exception($message . ': ' . $err->getMessage());
      };
   }
}
