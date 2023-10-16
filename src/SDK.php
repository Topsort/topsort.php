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
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\TransferException;

define("TOPSORT_SDK_VERSION", "v3.0.0");

/**
*  A sample class
*
*  Use this section to define what this class is doing, the PHPDocumentator will use this
*  to automatically generate an API documentation using this information.
*
*  @author Pablo Reszczynski
*/
class SDK
{
    /**
     * Types
     * @psalm-type Placement=array{path: string, position?: int, page?: int, pageSize?: int, productId?: string, categoryIds?: array<string>, searchQuery?: string}
     * @psalm-type Entity=array{type: string, id: string}
     * @psalm-type Impression=array{placement: Placement, entity?: Entity, resolvedBidId?: string, id?: string, opaqueUserId?: string, ocurredAt?: \DateTime}
     * @psalm-type Click=array{placement?: Placement, entity?: Entity, resolvedBidId?: string, id?: string, opaqueUserId?: string, ocurredAt?: \DateTime}
     * @psalm-type PurchaseItem=array{productId: string, quantity?: int, unitPrice: int}
     * @psalm-type Purchase=array{ocurredAt?: \DateTime, id?: string, opaqueUserId?: string, items?: array<PurchaseItem>}
     * @psalm-type BannerOptions=array{slots: int, slotId: string, category?: string, searchQuery?: string, device?: string}
     */

    /** @var string */
    private $api_key;
    /** @var Client */
    private $client;


    /**
     * @param string $api_key
     */
    public function __construct(string $api_key)
    {
        $this->api_key = $api_key;
        $this->client = new Client([
        'base_uri' => "https://api.topsort.com",
        'headers' => [
          'Authorization' => "Bearer {$api_key}",
          'User-Agent' => "Topsort/PHP-SDK {TOPSORT_SDK_VERSION}",
          'X-User-Agent' => "Topsort/PHP-SDK {TOPSORT_SDK_VERSION}"
        ]
      ]);
    }

    /**
     * Creates an auction between products for promotion slots. The winners are returned.
     * The winners should be promoted on the website by moving the products up in the results
     * list or rendering them in a special location on the page.
     *
     * @param int $slots
     * @param array<string> $products
     * @param array<float> $qualityScores
     * @param string $category
     * @param string $searchQuery
     * @return PromiseInterface
     */
    public function create_auction($slots, array $products = null, array $qualityScores = null, $category = null, $searchQuery = null)
    {
        $auction = [
          "type" => "listings",
          "slots" => $slots,
        ];
        if ($products) {
            $auction["products"] = [
            "ids" => $products,
          ];
          if ($qualityScores) {
              $auction["products"]["qualityScores"] = $qualityScores;
          }
        } else if ($category) {
          $auction["category"] = [
            "id" => $category,
          ];
        } else if ($searchQuery) {
          $auction["searchQuery"] = $searchQuery;
        }
        $body = [
          "auctions" => [
            $auction
          ]
        ];
        return $this->client->requestAsync('POST', '/v2/auctions', [
         'json' => $body
     ])->then(
         $this->handleResponse(),
         $this->handleException('Auction creation failed')
     );
    }

    /**
     *  Creates a banner auction
     *
     *  @param BannerOptions $bannerOptions
     *  @return PromiseInterface
     */
    public function create_banner_auction(array $bannerOptions)
    {
        $auction = array_merge([
       'type' => 'banners',
     ], $bannerOptions);
        $body = [
       'auctions' => [
         $auction
       ]
     ];
        return $this->client->requestAsync('POST', '/v2/auctions', [
       'json' => $body
     ])->then(
         $this->handleResponse(),
         $this->handleException('Auction creation failed')
     );
    }

    /**
     * All events are described by a single JSON object, an ImpressionEvent, ClickEvent
     * or PurchaseEvent. All event types have an eventType field and an id field.
     * id is supplied by the marketplace.
     *
     * @param 'impression'|'click'|'purchase' $event_type
     * @param Impression|Click|Purchase $data
     * @return PromiseInterface | null
     */
    private function create_event(string $event_type, array $data)
    {
        if (!isset($data["ocurredAt"])) {
            $data["ocurredAt"] = new \DateTime();
        }
        if (!isset($data["id"])) {
            $data["id"] = uniqid();
        }
        if (!isset($data["opaqueUserId"])) {
            $data["opaqueUserId"] = $this->getOpaqueUserId();
        }

        if (strtolower($event_type) === 'impression') {
          $payload = [
            'impressions' => [$data],
          ];
        } else if (strtolower($event_type) === 'click') {
          $payload = [
            'clicks' => [$data],
          ];
        } else if (strtolower($event_type) === 'purchase') {
          $payload = [
            'purchases' => [$data],
          ];
        } else {
          throw new \Exception('Invalid event type: {$event_type}');
        }
        return $this->client->requestAsync('POST', '/v2/events', [
         'json' => $payload
      ])->then(
          $this->handleResponse(),
          $this->handleException('Event creation failed')
      );
    }

    /**
     * @param Click $data
     * @return PromiseInterface | null
     */
    public function report_click(array $data)
    {
        return $this->create_event('click', $data);
    }

    /**
     * @param Impression $data
     * @return PromiseInterface | null
     */
    public function report_impressions(array $data)
    {
        return $this->create_event('impression', $data);
    }

    /**
     * @param Purchase $data
     * @return PromiseInterface | null
     */
    public function report_purchase(array $data)
    {
        return $this->create_event('purchase', $data);
    }

    /**
     * @return callable(ResponseInterface): array
    */
    private function handleResponse()
    {
        return function (ResponseInterface $res) {
            return json_decode($res->getBody()->getContents(), true);
        };
    }

    /**
     * @param string $message
     * @return callable(TransferException): void
    */
    private function handleException(string $message)
    {
        return function (TransferException $err) use ($message) {
            if ($err instanceof RequestException) {
                $error_response = $err->getResponse();
                $error_response_content = $error_response && $error_response->getBody()->getContents();
                $error_message = ($error_response_content && $error_response_content != '')
            ? 'Content: ' . $error_response_content
            : 'Message:' . $err->getMessage();
                throw new TopsortException($message . ": " . $error_message, 0, $err);
            } elseif ($err instanceof ConnectException) {
                $url = $err->getRequest()->getUri();
                throw new TopsortException($message . ": Could not connect to " . $url, 0, $err);
            }
        };
    }

    /**
     * @return string
     */
    private function getOpaqueUserId()
    {
      if (isset($_COOKIE["ts_opaque_user_id"])) {
        return $_COOKIE["ts_opaque_user_id"];
      } else {
        $opaque_user_id = uniqid();
        setcookie("ts_opaque_user_id", $opaque_user_id, time() + (86400 * 30), "/");
        return $opaque_user_id;
      }
    }
}

class TopsortException extends \Exception
{
    /**
     * @param string $message
     * @param int $code
     * @param \Throwable $previous
     */
    public function __construct(string $message, int $code=0, \Throwable $previous=null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return \string
     */
    public function __toString()
    {
        $previous = $this->getPrevious();
        $previous_message = $previous ? $previous->getMessage() : '';
        return __CLASS__ . " {$this->message}: {$previous_message}";
    }
}
