<?php

declare(strict_types=1);

namespace ShopSavvy\Laravel\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;
use ShopSavvy\Laravel\Exceptions\ShopSavvyAuthenticationException;
use ShopSavvy\Laravel\Exceptions\ShopSavvyNotFoundException;
use ShopSavvy\Laravel\Exceptions\ShopSavvyRateLimitException;
use ShopSavvy\Laravel\ShopSavvyClient;
use ShopSavvy\Laravel\ShopSavvyFacade;
use ShopSavvy\Laravel\ShopSavvyManager;
use ShopSavvy\Laravel\ShopSavvyServiceProvider;

class ShopSavvyClientTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [ShopSavvyServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['ShopSavvy' => ShopSavvyFacade::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('shopsavvy.api_key', 'ss_test_validkey1234567890');
        $app['config']->set('shopsavvy.cache.enabled', false); // disable cache for tests
    }

    // -------------------------------------------------------------------------
    // Constructor / configuration
    // -------------------------------------------------------------------------

    public function test_client_created_successfully_with_valid_config(): void
    {
        $client = new ShopSavvyClient([
            'api_key'  => 'ss_test_validkey1234567890',
            'base_url' => 'https://api.shopsavvy.com/v1',
            'timeout'  => 30,
            'cache'    => ['enabled' => false],
            'retry'    => ['times' => 1, 'sleep' => 0],
        ]);

        $this->assertInstanceOf(ShopSavvyClient::class, $client);
    }

    public function test_constructor_throws_on_empty_api_key(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('API key is required');

        new ShopSavvyClient(['api_key' => '']);
    }

    public function test_constructor_throws_on_null_api_key(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ShopSavvyClient(['api_key' => null]);
    }

    // -------------------------------------------------------------------------
    // Service provider / container bindings
    // -------------------------------------------------------------------------

    public function test_manager_resolves_from_container(): void
    {
        $manager = $this->app->make(ShopSavvyManager::class);
        $this->assertInstanceOf(ShopSavvyManager::class, $manager);
    }

    public function test_client_resolves_from_container(): void
    {
        $client = $this->app->make(ShopSavvyClient::class);
        $this->assertInstanceOf(ShopSavvyClient::class, $client);
    }

    public function test_facade_resolves_correctly(): void
    {
        $manager = ShopSavvyFacade::getFacadeRoot();
        $this->assertInstanceOf(ShopSavvyManager::class, $manager);
    }

    // -------------------------------------------------------------------------
    // search()
    // -------------------------------------------------------------------------

    public function test_search_sends_correct_request(): void
    {
        Http::fake([
            'api.shopsavvy.com/v1/products/search*' => Http::response([
                'success'    => true,
                'data'       => [['title' => 'AirPods Pro', 'shopsavvy' => 'products/abc', 'barcode' => 194253397168]],
                'pagination' => ['total' => 1, 'limit' => 5, 'offset' => 0, 'returned' => 1],
            ], 200),
        ]);

        $manager = $this->app->make(ShopSavvyManager::class);
        $result  = $manager->search('airpods', 5);

        $this->assertArrayHasKey('data', $result);
        $this->assertCount(1, $result['data']);
        $this->assertSame('AirPods Pro', $result['data'][0]['title']);

        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), '/products/search')
                && $request->data()['q'] === 'airpods'
                && $request->data()['limit'] === 5
                && $request->hasHeader('Authorization', 'Bearer ss_test_validkey1234567890');
        });
    }

    public function test_search_with_offset(): void
    {
        Http::fake([
            'api.shopsavvy.com/v1/products/search*' => Http::response(['success' => true, 'data' => [], 'pagination' => ['total' => 0, 'limit' => 10, 'offset' => 20, 'returned' => 0]], 200),
        ]);

        $manager = $this->app->make(ShopSavvyManager::class);
        $manager->search('iphone', 10, 20);

        Http::assertSent(function (Request $request) {
            return $request->data()['offset'] === 20;
        });
    }

    // -------------------------------------------------------------------------
    // offers()
    // -------------------------------------------------------------------------

    public function test_offers_sends_correct_request(): void
    {
        Http::fake([
            'api.shopsavvy.com/v1/products/offers*' => Http::response([
                'success' => true,
                'data'    => [[
                    'title'     => 'AirPods Pro',
                    'shopsavvy' => 'products/abc',
                    'offers'    => [
                        ['id' => 'o1', 'retailer' => 'Amazon', 'price' => 199.99, 'availability' => 'in', 'condition' => 'new', 'URL' => 'https://www.amazon.com/dp/B0BSHF7WHW'],
                        ['id' => 'o2', 'retailer' => 'Best Buy', 'price' => 209.99, 'availability' => 'in', 'condition' => 'new', 'URL' => 'https://www.bestbuy.com/site/1'],
                    ],
                ]],
            ], 200),
        ]);

        $manager = $this->app->make(ShopSavvyManager::class);
        $result  = $manager->offers('B0BSHF7WHW');

        $this->assertArrayHasKey('data', $result);
        $this->assertCount(2, $result['data'][0]['offers']);

        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), '/products/offers')
                && $request->data()['ids'] === 'B0BSHF7WHW';
        });
    }

    public function test_offers_passes_retailer_filter(): void
    {
        Http::fake([
            'api.shopsavvy.com/v1/products/offers*' => Http::response(['data' => []], 200),
        ]);

        $manager = $this->app->make(ShopSavvyManager::class);
        $manager->offers('B0BSHF7WHW', 'amazon.com');

        Http::assertSent(function (Request $request) {
            return $request->data()['retailer'] === 'amazon.com';
        });
    }

    // -------------------------------------------------------------------------
    // priceHistory()
    // -------------------------------------------------------------------------

    public function test_price_history_sends_correct_request(): void
    {
        Http::fake([
            'api.shopsavvy.com/v1/products/offers/history*' => Http::response([
                'success' => true,
                'data'    => [[
                    'title'  => 'AirPods Pro',
                    'offers' => [[
                        'id'       => 'o1',
                        'retailer' => 'Amazon',
                        'price'    => 189.99,
                        'history'  => [['timestamp' => '2024-01-15T00:00:00Z', 'price' => 179.99, 'currency' => 'USD', 'availability' => 'in']],
                    ]],
                ]],
            ], 200),
        ]);

        $manager = $this->app->make(ShopSavvyManager::class);
        $result  = $manager->priceHistory('B0BSHF7WHW', '2024-01-01', '2024-01-31');

        $this->assertArrayHasKey('data', $result);

        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), '/products/offers/history')
                && $request->data()['ids'] === 'B0BSHF7WHW'
                && $request->data()['start'] === '2024-01-01'
                && $request->data()['end'] === '2024-01-31';
        });
    }

    // -------------------------------------------------------------------------
    // Error handling
    // -------------------------------------------------------------------------

    public function test_throws_authentication_exception_on_401(): void
    {
        Http::fake([
            'api.shopsavvy.com/*' => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        $this->expectException(ShopSavvyAuthenticationException::class);

        $manager = $this->app->make(ShopSavvyManager::class);
        $manager->search('test');
    }

    public function test_throws_authentication_exception_on_403(): void
    {
        Http::fake([
            'api.shopsavvy.com/*' => Http::response(['message' => 'Forbidden'], 403),
        ]);

        $this->expectException(ShopSavvyAuthenticationException::class);

        $manager = $this->app->make(ShopSavvyManager::class);
        $manager->offers('B0BSHF7WHW');
    }

    public function test_throws_not_found_exception_on_404(): void
    {
        Http::fake([
            'api.shopsavvy.com/*' => Http::response(['message' => 'Product not found'], 404),
        ]);

        $this->expectException(ShopSavvyNotFoundException::class);

        $manager = $this->app->make(ShopSavvyManager::class);
        $manager->offers('DOESNOTEXIST');
    }

    public function test_throws_rate_limit_exception_on_429(): void
    {
        Http::fake([
            'api.shopsavvy.com/*' => Http::response(['message' => 'Too many requests'], 429),
        ]);

        // Disable retry for this test so it fails fast
        $client = new ShopSavvyClient([
            'api_key'  => 'ss_test_validkey1234567890',
            'base_url' => 'https://api.shopsavvy.com/v1',
            'cache'    => ['enabled' => false],
            'retry'    => ['times' => 1, 'sleep' => 0],
        ]);

        $this->expectException(ShopSavvyRateLimitException::class);

        $client->search('test');
    }

    // -------------------------------------------------------------------------
    // Caching
    // -------------------------------------------------------------------------

    public function test_caching_returns_same_result_on_second_call(): void
    {
        $callCount = 0;

        Http::fake([
            'api.shopsavvy.com/v1/products/search*' => function () use (&$callCount) {
                $callCount++;

                return Http::response(['success' => true, 'data' => [['title' => 'Cached Product']], 'pagination' => ['total' => 1, 'limit' => 10, 'offset' => 0, 'returned' => 1]], 200);
            },
        ]);

        // Override with caching enabled and short TTL
        $this->app['config']->set('shopsavvy.cache.enabled', true);
        $this->app['config']->set('shopsavvy.cache.ttl', 60);
        $this->app->forgetInstance(ShopSavvyClient::class);
        $this->app->forgetInstance(ShopSavvyManager::class);

        $manager = $this->app->make(ShopSavvyManager::class);
        $result1 = $manager->search('cached-query');
        $result2 = $manager->search('cached-query');

        $this->assertSame($result1, $result2);
        // Second call should be served from cache, not make a new HTTP request
        $this->assertSame(1, $callCount);
    }

    // -------------------------------------------------------------------------
    // Config publishing / structure
    // -------------------------------------------------------------------------

    public function test_config_has_required_keys(): void
    {
        $config = include __DIR__ . '/../config/shopsavvy.php';

        $this->assertArrayHasKey('api_key', $config);
        $this->assertArrayHasKey('base_url', $config);
        $this->assertArrayHasKey('timeout', $config);
        $this->assertArrayHasKey('cache', $config);
        $this->assertArrayHasKey('routes', $config);
        $this->assertArrayHasKey('retry', $config);
    }

    public function test_config_cache_section_has_required_keys(): void
    {
        $config = include __DIR__ . '/../config/shopsavvy.php';

        $this->assertArrayHasKey('enabled', $config['cache']);
        $this->assertArrayHasKey('ttl', $config['cache']);
        $this->assertArrayHasKey('store', $config['cache']);
        $this->assertArrayHasKey('prefix', $config['cache']);
    }

    public function test_config_routes_section_has_required_keys(): void
    {
        $config = include __DIR__ . '/../config/shopsavvy.php';

        $this->assertArrayHasKey('enabled', $config['routes']);
        $this->assertArrayHasKey('prefix', $config['routes']);
        $this->assertArrayHasKey('middleware', $config['routes']);
    }
}
