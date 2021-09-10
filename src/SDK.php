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
   /**
    * Types
    * @psalm-type Slots=array{listings?: int, videoAds?: int, bannerAds?: int}
    * @psalm-type Product=array{productId: string, quality?: string}
    * @psalm-type Session=array{sessionId: string, consumerId?: string, orderIntentId?: string, orderId?: string}
    * @psalm-type Placement=array{page: string, location: string}
    * @psalm-type Impression=array{placement: Placement, productId: string, auctionId: string | null, id?: string}
    */

   // TODO: make it work with staging or demo envs
   /** @var string */
   private static $base_url = '.topsort.com';
   /** @var string */
   private $marketplace;
   /** @var string */
   private $api_key;
   /** @var Client */
   private $client;


   /**
    * @param string $marketplace
    * @param string $api_key
    * @param string $url
    */
   public function __construct($marketplace, $api_key, $url='https://topsort.com') {
      $this->marketplace = $marketplace;
      $this->api_key = $api_key;
      $this->client = new Client([
        'base_uri' => $url, 
        'headers' => [
          'Authorization' => "Bearer {$api_key}"
        ]
      ]);
   }

   /**
    * Creates an auction between products for promotion slots. The winners are returned. 
    * The winners should be promoted on the website by moving the products up in the results 
    * list or rendering them in a special location on the page.
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
    * @param 'Impression'|'Click'|'Purchase' $event_type
    * @param array $data
    * @return PromiseInterface
    */
   private function create_event($event_type, $data) {
      return $this->client->requestAsync('POST', '/v1/events', [
         'json' => array_merge([ 'eventType' => $event_type ], $data)
      ])->then(
         $this->handleResponse(),
         $this->handleException('Event creation failed')
      );
   }

   /**
    * @psalm-type ClickData=array{session: Session, placement: Placement, productId: string, auctionId: string, id?: string}
    * @param ClickData $data
    * @return PromiseInterface
    */
   public function report_click($data) {
      return $this->create_event('Click', $data);
   }

   /**
    * @psalm-type ImpressionData=array{session: Session, impressions: array<Impression>}
    * @param ImpressionData $data
    * @return PromiseInterface
    */
   public function report_impressions($data) {
      return $this->create_event('Impression', $data);
   }

   /**
    * @psalm-type PurchaseItem=array{productId: string, auctionId?: string, quantity?: int, unitPrice: int}
    * @psalm-type PurchaseData=array{session: Session, id: string, purchasedAt: \DateTime, items: array<PurchaseItem>}
    * @param PurchaseData $data
    * @return PromiseInterface
    */
   public function report_purchase($data) {
      return $this->create_event('Purchase', array_merge(
         $data,
         ['purchasedAt' => $data['purchasedAt']->format(\DateTime::RFC3339)]
      ));
   }

   /**  
    * @return callable(ResponseInterface): array
   */
   private function handleResponse() {
      return function(ResponseInterface $res) {
         return json_decode($res->getBody()->getContents(), true);
      };
   }

   /**  
    * @param string $message
    * @return callable(RequestException): void
   */
   private function handleException($message) {
      return function(RequestException $err) use ($message) {
         $error_response = $err->getResponse();
         $error_response_content = $error_response && $error_response->getBody()->getContents();
         $error_message = ($error_response_content && $error_response_content != '') 
            ? 'Content: ' . $error_response_content
            : 'Message:' . $err->getMessage();
         throw new TopsortException($message . ": " . $error_message, 0, $err);
      };
   }
}

class TopsortException extends \Exception {
   /**
    * @param string $message
    * @param int $code
    * @param \Throwable $previous
    */
   public function __construct($message, $code=0, $previous=null) {
      parent::__construct($message, $code, $previous);
   }

   /**
    * @return \string
    */
   public function __toString() {
      $previous = $this->getPrevious();
      $previous_message = $previous ? $previous->getMessage() : '';
      return __CLASS__ . " {$this->message}: {$previous_message}";
   }
}
