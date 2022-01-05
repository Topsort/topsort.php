# Topsort Promoted Listings SDK for PHP

A PHP Software Development Kit for Topsort Promoted Listings API.

## Installation

The recommended way to install Topsort's SDK for PHP is with Composer. Composer
is a dependency management tool for PHP that allows you to declare the
dependencies your project needs and installs them into your project.

```json
{
  "require": {
    "topsort/php-sdk": "1.0.0"
  }
}
```

Or with the command line:
```bash
composer require topsort/php-sdk
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

$topsort_client = new SDK('my_marketplace', 'my_api_key');

// An array of objects, each describing a product that should participate in
// the auction.
$products = [
  [
    "productId" => "i8bfHPJaxcAb3",
    "quality" => "0.5"
  ],
  [
    "productId" => "gDG0HV97ed2s",
    "quality" => "0.6"
  ]
];

// Identifiers describing the consumer's session on the e-commerce site.
$session = [
  "sessionId" => "igwq0hEGZ8W56"
];

// The Slots object specifies how many auctions winners should be returned for
// each promotion type. The promotion types depend on the marketplace configuration.
// "listings", "videoAds" and "bannerAds" are common.
$slots = [
  "listings" => 1
];

// Run an auction.
$auction_result = $topsort_client->auction($slots, $products, $session)->wait();

// => [
// "slots" => [
//   "listings" => [
//     "auctionId": "AKFU78",
//     "winners": [
//       [
//         "rank" => 0,
//         "productId" => "gDG0HV97ed2s"
//       ]
//     ]
//   ]
//  ]
//]
```


## Usage: Reporting click events
Tracks whenever a product, promoted or not, had a click.

`Topsort\SDK\SDK::report_click` requires one argument, an array with the following keys:

- session (**required**): An array describing the user on the current session.
- placement (**required**): An array describing where the click happened
- productId (**required**): The product that was clicked.
- auctionId: Required for promoted products. Must be the ID for the auction the product won.

```php
<?php

use Topsort\SDK;

$topsort_client = new SDK('my_marketplace', 'my_api_key');

// session
$session = [
  "sessionId" => "igwq0hEGZ8W56"
];

$placement = [
  // A marketplace assigned name for a page.
  "page" => "/categories/shoes",
  // A marketplace defined name for a page part.
  "location" => "position_1"
];

// Report the click
$topsort_client->report_click([
  "session" => $session,
  "placement" => $placement,
  "productId" => "gDG0HV97ed2s",
  "auctionId" => "AKFU78"
]);
```

## Usage: Reporting impression events
Tracks the product impressions on the site, and if any auction winners were
rendered on the site.

`Topsort\SDK\SDK::report_impressions` requires one argument, an array with the following keys:

- session (**required**): An array describing the user on the current session.
- id: The marketplace's ID for the event. If present, it should be unique. Topsort may use this field to de-duplicate events. Also useful for correlating marketplace and Topsort events.
- impressions (**required**): An array of impression arrays, each containg data from the product rendered

```php
<?php

use Topsort\SDK;

$topsort_client = new SDK('my_marketplace', 'my_api_key');

$session = [
  "sessionId" => "igwq0hEGZ8W56"
];

$impressions = [
  [
    "placement" => [
      "page" => "/categories/shoes",
      "location" => "position_1"
    ],
    "productId" => "gDG0HV97ed2s",
    "auctionId" => "AKFU78",
  ],
  [
    "placement" => [
      "page" => "/categories/shoes",
      "location" => "position_2"
    ],
    "productId" => "i8bfHPJaxcAb3"
  ]
];

// Report the impressions
$topsort_client->report_impression([
  "session" => $session,
  "impressions" => $impressions
]);
```

## Usage: Reporting purchases events


`Topsort\SDK\SDK::report_purchase` requires one argument, an array with the following keys:

- session (**required**): An array describing the user on the current session.
- purchasedAt (**required**): A DateTime, from when the purchase happened.
- currency: (**required**): The currency used in the purchase.
- items: (**required**): An array of product data.

```php
<?php

use Topsort\SDK;

$topsort_client = new SDK('my_marketplace', 'my_api_key');

$session = [
  "sessionId" => "igwq0hEGZ8W56"
];

$items = [
  [
    "productId" => "gDG0HV97ed2s",
    "auctionId" => "AKFU78",
    "quantity" => 2,
    "unitPrice" => 10000
  ]
];

// Report the purchase
$topsort_client->report_purchase([
  "session" => $session,
  "purchasedAt" => new DateTime(),
  "currency" => "USD",
  "items" => $items
]);
```
