<?php

declare(strict_types=1);

namespace ShopSavvy\Laravel;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use ShopSavvy\Laravel\Exceptions\ShopSavvyException;
use ShopSavvy\Laravel\Exceptions\ShopSavvyAuthenticationException;
use ShopSavvy\Laravel\Exceptions\ShopSavvyNotFoundException;
use ShopSavvy\Laravel\Exceptions\ShopSavvyRateLimitException;
use ShopSavvy\Laravel\Exceptions\ShopSavvyValidationException;

/**
 * Low-level HTTP client for the ShopSavvy Data API.
 *
 * Wraps Laravel's HTTP facade with auth, caching, retry, and error handling.
 * Use ShopSavvyManager for the higher-level API surface, or the ShopSavvy
 * facade for the most ergonomic experience.
 */
class ShopSavvyClient
{
    public const VERSION = '1.0.0';

    private string $apiKey;
    private string $baseUrl;
    private int $timeout;
    private array $cache;
    private array $retry;

    public function __construct(array $config)
    {
        $apiKey = $config['api_key'] ?? null;

        if (empty($apiKey)) {
            throw new \InvalidArgumentException(
                'ShopSavvy API key is required. Set SHOPSAVVY_API_KEY in your .env file. ' .
                'Get your key at https://shopsavvy.com/data'
            );
        }

        $this->apiKey  = $apiKey;
        $this->baseUrl = rtrim($config['base_url'] ?? 'https://api.shopsavvy.com/v1', '/');
        $this->timeout = (int) ($config['timeout'] ?? 30);
        $this->cache   = $config['cache'] ?? ['enabled' => true, 'ttl' => 300, 'store' => null, 'prefix' => 'shopsavvy'];
        $this->retry   = $config['retry'] ?? ['times' => 3, 'sleep' => 500];
    }

    /**
     * Search for products by keyword or phrase.
     *
     * @param string $query  Search terms (e.g. "AirPods Pro")
     * @param int    $limit  Maximum number of results (default 10)
     * @param int    $offset Pagination offset (default 0)
     *
     * @throws ShopSavvyException
     */
    public function search(string $query, int $limit = 10, int $offset = 0): array
    {
        return $this->get('/products/search', [
            'q'      => $query,
            'limit'  => $limit,
            'offset' => $offset,
        ]);
    }

    /**
     * Look up product details by identifier.
     *
     * The identifier can be a UPC/EAN barcode, Amazon ASIN, product URL,
     * model number, MPN, or ShopSavvy product ID.
     *
     * @param string $identifier Product identifier
     *
     * @throws ShopSavvyException
     */
    public function product(string $identifier): array
    {
        return $this->get('/products', ['ids' => $identifier]);
    }

    /**
     * Get current offers (prices across retailers) for a product.
     *
     * @param string      $identifier Product identifier
     * @param string|null $retailer   Optional retailer filter
     *
     * @throws ShopSavvyException
     */
    public function offers(string $identifier, ?string $retailer = null): array
    {
        $query = ['ids' => $identifier];
        if ($retailer !== null) {
            $query['retailer'] = $retailer;
        }

        return $this->get('/products/offers', $query);
    }

    /**
     * Get price history for a product over a date range.
     *
     * @param string      $identifier Product identifier
     * @param string      $startDate  Start date in Y-m-d format
     * @param string      $endDate    End date in Y-m-d format
     * @param string|null $retailer   Optional retailer filter
     *
     * @throws ShopSavvyException
     */
    public function priceHistory(
        string $identifier,
        string $startDate,
        string $endDate,
        ?string $retailer = null
    ): array {
        $query = [
            'ids'        => $identifier,
            'start_date' => $startDate,
            'end_date'   => $endDate,
        ];
        if ($retailer !== null) {
            $query['retailer'] = $retailer;
        }

        return $this->get('/products/offers/history', $query);
    }

    /**
     * Get trending deals.
     *
     * @param int $limit Maximum number of deals to return
     *
     * @throws ShopSavvyException
     */
    public function deals(int $limit = 10): array
    {
        return $this->get('/deals', ['limit' => $limit]);
    }

    /**
     * Get current API usage information.
     *
     * @throws ShopSavvyException
     */
    public function usage(): array
    {
        return $this->get('/usage');
    }

    /**
     * Execute a GET request against the ShopSavvy API.
     *
     * Handles caching, retry, and maps HTTP errors to typed exceptions.
     *
     * @throws ShopSavvyException
     */
    public function get(string $path, array $query = []): array
    {
        $cacheKey = $this->cacheKey($path, $query);

        if ($this->cache['enabled'] ?? true) {
            $store = $this->cache['store'] ?? null;
            $ttl   = (int) ($this->cache['ttl'] ?? 300);

            return Cache::store($store)->remember($cacheKey, $ttl, function () use ($path, $query) {
                return $this->executeGet($path, $query);
            });
        }

        return $this->executeGet($path, $query);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function executeGet(string $path, array $query = []): array
    {
        $url      = $this->baseUrl . $path;
        $attempts = max(1, (int) ($this->retry['times'] ?? 3));
        $sleep    = (int) ($this->retry['sleep'] ?? 500);

        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = $this->buildRequest()->get($url, $query);

                if ($response->successful()) {
                    return $response->json();
                }

                $this->throwForStatus($response->status(), $response->json() ?? []);
            } catch (ShopSavvyAuthenticationException | ShopSavvyValidationException | ShopSavvyNotFoundException $e) {
                // Do not retry client errors — they will not resolve with retries
                throw $e;
            } catch (ShopSavvyRateLimitException $e) {
                // Respect rate limit and retry after a back-off
                if ($attempt < $attempts) {
                    usleep($sleep * 1000 * $attempt * 2);
                    $lastException = $e;
                    continue;
                }
                throw $e;
            } catch (RequestException $e) {
                $lastException = new ShopSavvyException(
                    'HTTP request failed: ' . $e->getMessage(),
                    $e->response?->status() ?? 0,
                    $e
                );
                if ($attempt < $attempts) {
                    usleep($sleep * 1000 * $attempt);
                    continue;
                }
                throw $lastException;
            } catch (ShopSavvyException $e) {
                $lastException = $e;
                if ($attempt < $attempts) {
                    usleep($sleep * 1000 * $attempt);
                    continue;
                }
                throw $e;
            }
        }

        throw $lastException ?? new ShopSavvyException('Request failed after ' . $attempts . ' attempts');
    }

    private function buildRequest(): PendingRequest
    {
        return Http::withToken($this->apiKey)
            ->withHeaders(['User-Agent' => 'ShopSavvy-Laravel/' . self::VERSION])
            ->timeout($this->timeout)
            ->acceptJson();
    }

    private function throwForStatus(int $status, array $body): void
    {
        $message = $body['message'] ?? $body['error'] ?? 'Unknown error';

        match (true) {
            $status === 401 || $status === 403 => throw new ShopSavvyAuthenticationException($message, $status),
            $status === 404                    => throw new ShopSavvyNotFoundException($message, $status),
            $status === 422                    => throw new ShopSavvyValidationException($message, $status),
            $status === 429                    => throw new ShopSavvyRateLimitException($message, $status),
            default                            => throw new ShopSavvyException($message, $status),
        };
    }

    private function cacheKey(string $path, array $query): string
    {
        $prefix = $this->cache['prefix'] ?? 'shopsavvy';
        $hash   = md5($path . http_build_query($query));

        return $prefix . ':' . $hash;
    }
}
