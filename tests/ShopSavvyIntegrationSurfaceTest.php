<?php

declare(strict_types=1);

namespace ShopSavvy\Laravel\Tests;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;
use ShopSavvy\Laravel\Exceptions\ShopSavvyException;
use ShopSavvy\Laravel\Exceptions\ShopSavvyValidationException;
use ShopSavvy\Laravel\ShopSavvyClient;
use ShopSavvy\Laravel\ShopSavvyManager;
use ShopSavvy\Laravel\ShopSavvyServiceProvider;

/**
 * Exercises the Artisan commands, Blade components and optional routes against
 * responses in the exact shape the Data API returns
 * (https://shopsavvy.com/data/documentation): search results under `data` with
 * `pagination`, and offers nested per product under `data[].offers`.
 */
class ShopSavvyIntegrationSurfaceTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [ShopSavvyServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('shopsavvy.api_key', 'ss_test_0123456789abcdef0123456789abcdef');
        $app['config']->set('shopsavvy.cache.enabled', false);
        $app['config']->set('shopsavvy.retry', ['times' => 1, 'sleep' => 0]);
        $app['config']->set('shopsavvy.routes.enabled', true);
    }

    /**
     * @return array<string, mixed>
     */
    private static function offersResponse(): array
    {
        return [
            'success' => true,
            'data'    => [[
                'title'     => 'Sony WH-1000XM5',
                'shopsavvy' => 'products/sony123',
                'barcode'   => 27242923379,
                'offers'    => [
                    ['id' => 'a', 'retailer' => 'Best Buy', 'price' => 299.99, 'availability' => 'in', 'condition' => 'new', 'URL' => 'https://www.bestbuy.com/site/xm5'],
                    ['id' => 'b', 'retailer' => 'Amazon', 'price' => 279.99, 'availability' => 'in', 'condition' => 'new', 'URL' => 'https://www.amazon.com/dp/B09XS7JWHH'],
                    ['id' => 'c', 'retailer' => 'Walmart', 'price' => 289.00, 'availability' => 'out', 'condition' => 'refurbished', 'URL' => 'https://www.walmart.com/ip/1'],
                ],
            ]],
            'meta' => ['request_id' => 'r1'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function searchResponse(): array
    {
        return [
            'success' => true,
            'data'    => [
                ['title' => 'Sony WH-1000XM5', 'brand' => 'Sony', 'shopsavvy' => 'products/sony123', 'barcode' => 27242923379, 'amazon' => 'B09XS7JWHH', 'images' => ['https://img.example/xm5.jpg']],
                ['title' => 'Sony WH-1000XM4', 'brand' => 'Sony', 'shopsavvy' => 'products/sony456', 'barcode' => 27242919075],
            ],
            'pagination' => ['total' => 37, 'limit' => 10, 'offset' => 0, 'returned' => 2],
        ];
    }

    // -------------------------------------------------------------------------
    // Artisan commands
    // -------------------------------------------------------------------------

    public function test_price_command_lists_offers_cheapest_first_with_real_fields(): void
    {
        Http::fake(['api.shopsavvy.com/v1/products/offers*' => Http::response(self::offersResponse())]);

        $this->artisan('shopsavvy:price', ['identifier' => 'B09XS7JWHH'])
            ->expectsTable(
                ['Retailer', 'Price', 'Condition', 'In Stock', 'URL'],
                [
                    ['Amazon', '$279.99', 'new', '<fg=green>Yes</>', 'https://www.amazon.com/dp/B09XS7JWHH'],
                    ['Walmart', '$289.00', 'refurbished', '<fg=red>No</>', 'https://www.walmart.com/ip/1'],
                    ['Best Buy', '$299.99', 'new', '<fg=green>Yes</>', 'https://www.bestbuy.com/site/xm5'],
                ]
            )
            ->expectsOutputToContain('Best price: $279.99 at Amazon')
            ->assertSuccessful();
    }

    public function test_price_command_history_reads_nested_history_points(): void
    {
        Http::fake([
            'api.shopsavvy.com/v1/products/offers/history*' => Http::response([
                'success' => true,
                'data'    => [[
                    'title'  => 'Sony WH-1000XM5',
                    'offers' => [[
                        'id'       => 'b',
                        'retailer' => 'Amazon',
                        'history'  => [
                            ['timestamp' => '2026-09-02T00:00:00Z', 'price' => 289.99, 'currency' => 'USD', 'availability' => 'in'],
                            ['timestamp' => '2026-09-01T00:00:00Z', 'price' => 299.99, 'currency' => 'USD', 'availability' => 'in'],
                        ],
                    ]],
                ]],
            ]),
            'api.shopsavvy.com/v1/products/offers*' => Http::response(self::offersResponse()),
        ]);

        $this->artisan('shopsavvy:price', ['identifier' => 'B09XS7JWHH', '--history' => true])
            ->expectsTable(
                ['Date', 'Retailer', 'Price'],
                [
                    ['2026-09-01T00:00:00Z', 'Amazon', '299.99 USD'],
                    ['2026-09-02T00:00:00Z', 'Amazon', '289.99 USD'],
                ]
            )
            ->assertSuccessful();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/products/offers/history')
            && isset($request->data()['start'], $request->data()['end']));
    }

    public function test_search_command_renders_identifiers_and_total(): void
    {
        Http::fake(['api.shopsavvy.com/v1/products/search*' => Http::response(self::searchResponse())]);

        $this->artisan('shopsavvy:search', ['query' => 'sony headphones'])
            ->expectsTable(
                ['Product', 'Brand', 'Barcode', 'ASIN'],
                [
                    ['Sony WH-1000XM5', 'Sony', '27242923379', 'B09XS7JWHH'],
                    ['Sony WH-1000XM4', 'Sony', '27242919075', '—'],
                ]
            )
            ->expectsOutputToContain('Found 37 results. Showing 2.')
            ->assertSuccessful();
    }

    public function test_command_reports_api_errors_and_fails(): void
    {
        Http::fake(['api.shopsavvy.com/*' => Http::response(['success' => false, 'error' => 'API key not found or has been revoked.'], 401)]);

        $this->artisan('shopsavvy:search', ['query' => 'x'])
            ->expectsOutputToContain('API key not found or has been revoked.')
            ->assertFailed();
    }

    // -------------------------------------------------------------------------
    // Blade components
    // -------------------------------------------------------------------------

    public function test_price_component_renders_offers_cheapest_first(): void
    {
        Http::fake(['api.shopsavvy.com/v1/products/offers*' => Http::response(self::offersResponse())]);

        $html = Blade::render('<x-shopsavvy-price identifier="B09XS7JWHH" :limit="2" />');

        $this->assertStringContainsString('$279.99', $html);
        $this->assertStringContainsString('$289.00', $html);
        $this->assertStringNotContainsString('$299.99', $html, 'limit=2 keeps only the two cheapest offers');
        $this->assertLessThan(strpos($html, 'Walmart'), strpos($html, 'Amazon'));
        $this->assertStringContainsString('href="https://www.amazon.com/dp/B09XS7JWHH"', $html);
        $this->assertStringContainsString('Out of stock', $html, 'availability "out" must render as out of stock');
    }

    public function test_search_component_renders_products_images_links_and_total(): void
    {
        Http::fake(['api.shopsavvy.com/v1/products/search*' => Http::response(self::searchResponse())]);

        $html = Blade::render('<x-shopsavvy-search query="sony headphones" />');

        $this->assertStringContainsString('Showing 2 of 37 results', $html);
        $this->assertStringContainsString('Sony WH-1000XM5', $html);
        $this->assertStringContainsString('src="https://img.example/xm5.jpg"', $html);
        $this->assertStringContainsString('href="https://shopsavvy.com/products/sony123"', $html);
    }

    // -------------------------------------------------------------------------
    // Optional routes
    // -------------------------------------------------------------------------

    public function test_routes_proxy_the_api_when_enabled(): void
    {
        Http::fake(['api.shopsavvy.com/v1/products/offers*' => Http::response(self::offersResponse())]);

        $this->getJson('/api/shopsavvy/offers/B09XS7JWHH?retailer=amazon.com')
            ->assertOk()
            ->assertJsonPath('data.0.offers.0.retailer', 'Best Buy');

        Http::assertSent(fn ($request) => $request->data()['retailer'] === 'amazon.com');
    }

    public function test_route_maps_upstream_not_found_to_404(): void
    {
        Http::fake(['api.shopsavvy.com/*' => Http::response(['success' => false, 'error' => 'Product not found'], 404)]);

        $this->getJson('/api/shopsavvy/product/nope')
            ->assertStatus(404)
            ->assertJson(['error' => 'Product not found']);
    }

    // -------------------------------------------------------------------------
    // Error mapping
    // -------------------------------------------------------------------------

    public function test_400_invalid_params_is_a_validation_exception_and_not_retried(): void
    {
        $calls = 0;
        Http::fake(['api.shopsavvy.com/*' => function () use (&$calls) {
            $calls++;

            return Http::response(['success' => false, 'error' => "Invalid sort parameter."], 400);
        }]);

        $client = new ShopSavvyClient([
            'api_key' => 'ss_test_0123456789abcdef0123456789abcdef',
            'cache'   => ['enabled' => false],
            'retry'   => ['times' => 3, 'sleep' => 0],
        ]);

        try {
            $client->deals();
            $this->fail('Expected ShopSavvyValidationException');
        } catch (ShopSavvyValidationException $e) {
            $this->assertSame(400, $e->getCode());
            $this->assertSame('Invalid sort parameter.', $e->getMessage());
        }
        $this->assertSame(1, $calls);
    }

    public function test_connection_failures_surface_as_shopsavvy_exceptions(): void
    {
        Http::fake(['api.shopsavvy.com/*' => fn () => throw new ConnectionException('cURL error 6: Could not resolve host')]);

        $this->expectException(ShopSavvyException::class);
        $this->expectExceptionMessage('Could not reach the ShopSavvy API');

        $this->app->make(ShopSavvyManager::class)->usage();
    }
}
