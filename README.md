# topsort.php

A PHP Software Development Kit for interacting with the `auctions` and `events`
endpoints in the Topsort ecosystem.

## Installation

The recommended way to install Topsort's SDK for PHP is with Composer. Composer
is a dependency management tool for PHP that allows you to declare the
dependencies your project needs and installs them into your project.

```json
{
  "require": {
    "topsort/sdk": "^3.1"
  }
}
```

Or with the command line:
```bash
composer require topsort/sdk
```

## How it works
All operations are driven by our [OpenAPI documentation](https://docs.topsort.com/reference/integration-overview),
so all methods maps directly to the API description.

## Usage: Running an auction
`Topsort\SDK::create_auction` runs a sponsored listings auction and returns a promise. It takes:

- slots: Required. An integer, the number of winners to return.
- products: Optional. An array with the IDs of the participating products.
- qualityScores: Optional. An array of floats, one per product, in the same order.
- category: Optional. A category ID, used when no products are given.
- searchQuery: Optional. A search query, used when no products or category are given.
- opaqueUserId: Optional. The marketplace's anonymized ID for the user. Pass the same ID you send on events, so purchases can be attributed to this auction.


```php
<?php
use Topsort\SDK;

$topsort_client = new SDK("my_api_key");

// An array of product IDs, each describing a product that should participate in
// the auction.
$products = [
    "i8bfHPJaxcAb3",
    "gDG0HV97ed2s"
];

// The Slots number specifies how many auctions winners should be returned for
// the auction.
$slots = 1;

// The same ID you send on events for this user.
$opaque_user_id = "u_7f3a9c";

// Run an auction.
$auction_result = $topsort_client->create_auction($slots, $products, null, null, null, $opaque_user_id)->wait();

// => [
// "results" => [
//    [
//      "resultType" => "listings",
//      "winners" => [
//         [
//            "rank" => 1,
//            "type" => "product",
//            "id" => "gDG0HV97ed2s",
//            "resolvedBidId" => "AKFU78"
//         ]
//      ]
//    ]
//  ]
//]
```


## Usage: Sending events
The event methods (`report_click`, `report_impressions`, `report_render` and
`report_purchase`) return a promise, and nothing is sent until you call
`->wait()` on it. `wait()` blocks until Topsort responds and throws
`Topsort\TopsortException` on an API or connection error, so catch it. The
`report_*` call itself throws `Topsort\InvalidEventException` (also a
`TopsortException`) before sending anything when the event is invalid, so keep
the call inside the `try` as well. Where possible, report events after the
response has been sent to the shopper, for example after
`fastcgi_finish_request()`.

## Usage: Reporting click events
Tracks whenever a product, promoted or not, had a click.

`Topsort\SDK::report_click` requires one argument, an array with the following keys:

- entity: Required for unpromoted products. An array with the `type` (e.g. `"product"`) and `id` of the product that was clicked.
- resolvedBidId: Required for promoted products. Must be the ID for the auction the product won.
- placement: Optional. An array describing the placement of the product on the site.
- id: Optional. The marketplace's ID for the event. If present, it should be unique. Topsort may use this field to de-duplicate events.
- opaqueUserId: Required. The marketplace's anonymized ID for the user. It must stay the same across the user's sessions and match the ID passed to `create_auction`, or purchases cannot be attributed. See the [opaqueUserId guidance](https://docs.topsort.com/en/api-reference/events/report-events) for how to generate and store it. The SDK throws a `Topsort\InvalidEventException` (a `TopsortException`) when it is missing or not a non-empty string.
- occurredAt: Optional. A DateTime, from when the click happened. Defaults to the current time.

```php
<?php

use Topsort\SDK;
use Topsort\TopsortException;

$topsort_client = new SDK('my_api_key');

$placement = [
  // A marketplace assigned name for a page.
  "path" => "/categories/shoes",
];

// Report the click
try {
  $topsort_client->report_click([
    "placement" => $placement,
    "resolvedBidId" => "AKFU78",
    "opaqueUserId" => "u_7f3a9c",
  ])->wait();
} catch (TopsortException $e) {
  error_log($e->getMessage());
}
```

## Usage: Reporting impression events
Tracks when a product, promoted or not, becomes visible on the site. To record
that an auction winner was inserted into the page, use render events instead.

`Topsort\SDK::report_impressions` requires one argument, an array with the following keys:

- entity: Required for unpromoted products. An array with the `type` (e.g. `"product"`) and `id` of the product that was seen.
- resolvedBidId: Required for promoted products. Must be the ID for the auction the product won.
- placement: Optional. An array describing the placement of the product on the site.
- id: Optional. The marketplace's ID for the event. If present, it should be unique. Topsort may use this field to de-duplicate events.
- opaqueUserId: Required. The marketplace's anonymized ID for the user. It must stay the same across the user's sessions and match the ID passed to `create_auction`, or purchases cannot be attributed. See the [opaqueUserId guidance](https://docs.topsort.com/en/api-reference/events/report-events) for how to generate and store it. The SDK throws a `Topsort\InvalidEventException` (a `TopsortException`) when it is missing or not a non-empty string.
- occurredAt: Optional. A DateTime, from when the impression happened. Defaults to the current time.

```php
<?php

use Topsort\SDK;
use Topsort\TopsortException;

$topsort_client = new SDK('my_api_key');

$impression = [
  "placement" => [
    "path" => "/categories/shoes",
  ],
  "resolvedBidId" => "AKFU78",
  "opaqueUserId" => "u_7f3a9c",
];

// Report the impressions
try {
  $topsort_client->report_impressions($impression)->wait();
} catch (TopsortException $e) {
  error_log($e->getMessage());
}
```

## Usage: Reporting render events
Tracks whenever an auction winner was actually rendered on the site. Render events
are sponsored-only: unlike clicks and impressions there is no organic `entity`
fallback, so `resolvedBidId` is required.

`Topsort\SDK::report_render` requires a `resolvedBidId` argument, the ID for the
auction the product won, plus an array with the following keys:

- placement: Optional. An array describing the placement of the product on the site.
- id: Optional. The marketplace's ID for the event. If present, it should be unique. Topsort may use this field to de-duplicate events.
- opaqueUserId: Required. The marketplace's anonymized ID for the user. It must stay the same across the user's sessions and match the ID passed to `create_auction`, or purchases cannot be attributed. See the [opaqueUserId guidance](https://docs.topsort.com/en/api-reference/events/report-events) for how to generate and store it. The SDK throws a `Topsort\InvalidEventException` (a `TopsortException`) when it is missing or not a non-empty string.
- occurredAt: Optional. A DateTime, from when the render happened. Defaults to the current time.

```php
<?php

use Topsort\SDK;
use Topsort\TopsortException;

$topsort_client = new SDK('my_api_key');

$placement = [
  // A marketplace assigned name for a page.
  "path" => "/categories/shoes",
];

// Report the render
try {
  $topsort_client->report_render("AKFU78", [
    "placement" => $placement,
    "opaqueUserId" => "u_7f3a9c",
  ])->wait();
} catch (TopsortException $e) {
  error_log($e->getMessage());
}
```

## Usage: Reporting purchases events


`Topsort\SDK::report_purchase` requires one argument, an array with the following keys:

- items: An array of product data.
- opaqueUserId: Required. The marketplace's anonymized ID for the user. It must stay the same across the user's sessions and match the ID passed to `create_auction`, or purchases cannot be attributed. See the [opaqueUserId guidance](https://docs.topsort.com/en/api-reference/events/report-events) for how to generate and store it. The SDK throws a `Topsort\InvalidEventException` (a `TopsortException`) when it is missing or not a non-empty string.
- occurredAt: Optional. A DateTime, from when the purchase happened. Defaults to the current time.

```php
<?php

use Topsort\SDK;
use Topsort\TopsortException;

$topsort_client = new SDK('my_api_key');

$items = [
  [
    "productId" => "gDG0HV97ed2s",
    "quantity" => 2,
    "unitPrice" => 10000,
  ]
];

// Report the purchase
try {
  $topsort_client->report_purchase([
    "occurredAt" => new DateTime(),
    "opaqueUserId" => "u_7f3a9c",
    "items" => $items,
  ])->wait();
} catch (TopsortException $e) {
  error_log($e->getMessage());
}
```
