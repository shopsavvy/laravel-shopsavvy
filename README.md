# ShopSavvy for Laravel

[![Packagist Version](https://img.shields.io/packagist/v/shopsavvy/laravel-shopsavvy.svg)](https://packagist.org/packages/shopsavvy/laravel-shopsavvy)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D%208.1-blue.svg)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-10%2B-red.svg)](https://laravel.com/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

Official Laravel package for the [ShopSavvy Data API](https://shopsavvy.com/data). Add product search, real-time pricing, and price history to your Laravel application in minutes.

## Features

- **Facade** — `ShopSavvy::search()`, `ShopSavvy::offers()`, `ShopSavvy::priceHistory()`
- **Blade components** — `<x-shopsavvy-price>`, `<x-shopsavvy-search>`
- **Artisan commands** — `shopsavvy:search`, `shopsavvy:price`
- **Optional API routes** — `/api/shopsavvy/search`, `/api/shopsavvy/offers/{id}`, etc.
- **Laravel cache integration** — responses cached via any configured cache store
- **Rate limit aware** — retries with exponential back-off
- **Typed exceptions** — `ShopSavvyAuthenticationException`, `ShopSavvyRateLimitException`, etc.
- **Auto-discovery** — service provider and facade registered automatically

## Requirements

- PHP 8.1+ (or newer, as your Laravel version requires)
- Laravel 10, 11, 12, or 13

## Installation

```bash
composer require shopsavvy/laravel-shopsavvy
```

Auto-discovery registers the service provider and `ShopSavvy` facade automatically. The package talks to the API through Laravel's HTTP client and has no other ShopSavvy dependency.

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=shopsavvy-config
```

Then add your API key to `.env`:

```env
SHOPSAVVY_API_KEY=ss_live_your_key_here
```

Get your API key at [shopsavvy.com/data](https://shopsavvy.com/data).

## Usage

### Facade

```php
use ShopSavvy\Laravel\ShopSavvyFacade as ShopSavvy;

// Search products
$results = ShopSavvy::search('AirPods Pro');
$results = ShopSavvy::search('iPhone 16', limit: 5);

// Get current offers across retailers
$offers = ShopSavvy::offers('B0BSHF7WHW');
$offers = ShopSavvy::offers('B0BSHF7WHW', retailer: 'amazon.com'); // retailer domain

// Get price history
$history = ShopSavvy::priceHistory('B0BSHF7WHW', '2024-01-01', '2024-12-31');

// Get trending deals
$deals = ShopSavvy::deals(limit: 20);

// Check API usage
$usage = ShopSavvy::usage();
```

Every method returns the decoded API response as an array, in the shape documented at [shopsavvy.com/data/documentation](https://shopsavvy.com/data/documentation):

```php
$results = ShopSavvy::search('AirPods Pro');
foreach ($results['data'] as $product) {
    echo $product['title'] . ' ' . ($product['barcode'] ?? '');
}
echo $results['pagination']['total'];

// Offers are grouped per matched product
$response = ShopSavvy::offers('B0BSHF7WHW');
foreach ($response['data'] as $product) {
    foreach ($product['offers'] as $offer) {
        // id, retailer, price, availability ('in' | 'out'), condition, seller, URL, timestamp
        echo "{$offer['retailer']}: {$offer['price']}\n";
    }
}
```

### Blade Components

Display current prices for a product, cheapest first:

```blade
<x-shopsavvy-price identifier="B0BSHF7WHW" />

{{-- With options --}}
<x-shopsavvy-price identifier="B0BSHF7WHW" :limit="3" retailer="amazon.com" />
```

Display product search results:

```blade
<x-shopsavvy-search query="AirPods Pro" />

{{-- With options --}}
<x-shopsavvy-search query="iPhone 16" :limit="8" />
```

Publish the views to customize them:

```bash
php artisan vendor:publish --tag=shopsavvy-views
```

### Artisan Commands

```bash
# Search products
php artisan shopsavvy:search "AirPods Pro"
php artisan shopsavvy:search "iPhone 16" --limit=5
php artisan shopsavvy:search "MacBook" --json

# Look up prices for a product
php artisan shopsavvy:price B0BSHF7WHW
php artisan shopsavvy:price B0BSHF7WHW --retailer=amazon.com
php artisan shopsavvy:price B0BSHF7WHW --history
php artisan shopsavvy:price B0BSHF7WHW --json
```

### Optional API Routes

Enable the built-in API routes by setting `SHOPSAVVY_ROUTES_ENABLED=true` in `.env`:

```env
SHOPSAVVY_ROUTES_ENABLED=true
SHOPSAVVY_ROUTES_PREFIX=api/shopsavvy
```

This registers:

| Method | Route | Description |
|--------|-------|-------------|
| GET | `/api/shopsavvy/search?q=...` | Search products |
| GET | `/api/shopsavvy/offers/{identifier}` | Current prices |
| GET | `/api/shopsavvy/history/{identifier}?start=...&end=...` | Price history |
| GET | `/api/shopsavvy/product/{identifier}` | Product details |
| GET | `/api/shopsavvy/deals` | Trending deals |

### Dependency Injection

Inject `ShopSavvyManager` or `ShopSavvyClient` directly:

```php
use ShopSavvy\Laravel\ShopSavvyManager;

class ProductController extends Controller
{
    public function __construct(private ShopSavvyManager $shopsavvy) {}

    public function show(string $identifier)
    {
        $offers = $this->shopsavvy->offers($identifier);

        return view('products.show', compact('offers'));
    }
}
```

### Caching

Caching is enabled by default and uses your app's default cache store. Configure via `config/shopsavvy.php` or `.env`:

```env
SHOPSAVVY_CACHE_ENABLED=true
SHOPSAVVY_CACHE_TTL=300
SHOPSAVVY_CACHE_STORE=redis
```

### Error Handling

```php
use ShopSavvy\Laravel\Exceptions\ShopSavvyAuthenticationException;
use ShopSavvy\Laravel\Exceptions\ShopSavvyNotFoundException;
use ShopSavvy\Laravel\Exceptions\ShopSavvyRateLimitException;
use ShopSavvy\Laravel\Exceptions\ShopSavvyValidationException;
use ShopSavvy\Laravel\Exceptions\ShopSavvyException;

try {
    $offers = ShopSavvy::offers('B0BSHF7WHW');
} catch (ShopSavvyAuthenticationException $e) {
    // Invalid or missing API key (401/403)
} catch (ShopSavvyValidationException $e) {
    // Invalid parameters (400/422), not retried
} catch (ShopSavvyNotFoundException $e) {
    // Product not found
} catch (ShopSavvyRateLimitException $e) {
    // Rate limited — back off and retry
} catch (ShopSavvyException $e) {
    // Other API error, or the API could not be reached
}
```

## Configuration Reference

```php
// config/shopsavvy.php

return [
    'api_key'  => env('SHOPSAVVY_API_KEY'),
    'base_url' => env('SHOPSAVVY_BASE_URL', 'https://api.shopsavvy.com/v1'),
    'timeout'  => env('SHOPSAVVY_TIMEOUT', 30),

    'cache' => [
        'enabled' => env('SHOPSAVVY_CACHE_ENABLED', true),
        'ttl'     => env('SHOPSAVVY_CACHE_TTL', 300),   // seconds
        'store'   => env('SHOPSAVVY_CACHE_STORE', null), // null = default store
        'prefix'  => env('SHOPSAVVY_CACHE_PREFIX', 'shopsavvy'),
    ],

    'routes' => [
        'enabled'    => env('SHOPSAVVY_ROUTES_ENABLED', false),
        'prefix'     => env('SHOPSAVVY_ROUTES_PREFIX', 'api/shopsavvy'),
        'middleware' => ['api'],
    ],

    'retry' => [
        'times' => env('SHOPSAVVY_RETRY_TIMES', 3),
        'sleep' => env('SHOPSAVVY_RETRY_SLEEP', 500), // milliseconds
    ],
];
```

## Testing

Run the test suite:

```bash
./test.sh
```

Run with real API integration tests:

```bash
SHOPSAVVY_API_KEY=ss_live_... ./test.sh --integration
```

## Links

- [Integration page](https://shopsavvy.com/integrations/laravel)
- [Data API documentation](https://shopsavvy.com/data/documentation)
- [Plain PHP SDK](https://github.com/shopsavvy/sdk-php) (`shopsavvy/shopsavvy-sdk-php`) for non-Laravel projects

## License

MIT — see [LICENSE](LICENSE).
