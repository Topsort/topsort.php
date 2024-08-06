# Topsort Promoted Listings SDK for PHP

A PHP Software Development Kit for Topsort Promoted Listings API.

## Installation

The recommended way to install Topsort's SDK for PHP is with Composer. Composer
is a dependency management tool for PHP that allows you to declare the
dependencies your project needs and installs them into your project.

```json
{
  "require": {
    "topsort/sdk": "3.0.0"
  }
}
```

Or with the command line:
```bash
composer require topsort/sdk
```

## How it works
All operations are driven by our [OpenAPI documentation](https://docs.topsort.com/openapi/topsort-reference/),
so all methods maps directly to the API description.

## Usage: Running an auction
`Topsort\SDK\SDK::auction` requires three arguments:

- slots: An array describing the product slots that are being auctioned.
- products: An array, with the id's of the participating products.
- session: An array, describing the user on the current session.


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

// Run an auction.
$auction_result = $topsort_client->create_auction($slots, $products)->wait();

// => [
// "results" => [
//    [
//      "resultType" => "listings",
//      "winners" => [
//         [
//            "rank" => 1,
//            "type" => "product",
//            "id" => "gDG0HV97ed2s",
//            "resovedBidId" => "AKFU78"
//         ]
//      ]
//    ]
//  ]
//]
```


## Usage: Reporting click events
Tracks whenever a product, promoted or not, had a click.

`Topsort\SDK\SDK::report_click` requires one argument, an array with the following keys:

- entity: Required for unpromoted products. Must be the ID for the product that was clicked.
- resolvedBidId: Required for promoted products. Must be the ID for the auction the product won.
- placement: Optional. An array describing the placement of the product on the site.
- id: Optional. The marketplace's ID for the event. If present, it should be unique. Topsort may use this field to de-duplicate events.
- opaqueUserId: Optional. The marketplace's ID for the user. Defaults to a random UUID stored in a cookie.
- occurredAt: Optional. A DateTime, from when the click happened. Defaults to the current time.

```php
<?php

use Topsort\SDK;

$topsort_client = new SDK('my_api_key');

$placement = [
  // A marketplace assigned name for a page.
  "path" => "/categories/shoes",
];

// Report the click
$topsort_client->report_click([
  "placement" => $placement,
  "resolvedBidId" => "AKFU78",
]);
```

## Usage: Reporting impression events
Tracks the product impressions on the site, and if any auction winners were
rendered on the site.

`Topsort\SDK\SDK::report_impressions` requires one argument, an array with the following keys:

- entity: Required for unpromoted products. Must be the ID for the product that was rendered.
- resolvedBidId: Required for promoted products. Must be the ID for the auction the product won.
- placement: Optional. An array describing the placement of the product on the site.
- id: Optional. The marketplace's ID for the event. If present, it should be unique. Topsort may use this field to de-duplicate events.
- opaqueUserId: Optional. The marketplace's ID for the user. Defaults to a random UUID stored in a cookie.
- occurredAt: Optional. A DateTime, from when the impression happened. Defaults to the current time.

```php
<?php

use Topsort\SDK;

$topsort_client = new SDK('my_marketplace', 'my_api_key');

$impression = [
  "placement" => [
    "path" => "/categories/shoes",
  ],
  "resolvedBidId" => "AKFU78",
];

// Report the impressions
$topsort_client->report_impression($impression);
```

## Usage: Reporting purchases events


`Topsort\SDK\SDK::report_purchase` requires one argument, an array with the following keys:

- items: An array of product data.
- opaqueUserId: Optional. The marketplace's ID for the user. Defaults to a random UUID stored in a cookie.
- occurredAt: Optional. A DateTime, from when the purchase happened. Defaults to the current time.

```php
<?php

use Topsort\SDK;

$topsort_client = new SDK('my_api_key');

$items = [
  [
    "productId" => "gDG0HV97ed2s",
    "quantity" => 2,
    "unitPrice" => 10000,
  ]
];

// Report the purchase
$topsort_client->report_purchase([
  "occurredAt" => new DateTime(),
  "items" => $items,
]);
```
