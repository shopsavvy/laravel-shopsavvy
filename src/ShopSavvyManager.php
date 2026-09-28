<?php

declare(strict_types=1);

namespace ShopSavvy\Laravel;

/**
 * High-level manager that provides the public API for the ShopSavvy facade.
 *
 * Delegates to ShopSavvyClient, adding any application-level conveniences.
 * All methods correspond directly to ShopSavvyFacade static calls.
 */
class ShopSavvyManager
{
    public function __construct(private ShopSavvyClient $client) {}

    /**
     * Search for products by keyword or phrase.
     *
     * @example ShopSavvy::search('AirPods Pro')
     * @example ShopSavvy::search('iPhone 16', limit: 5)
     *
     * @param string $query  Search terms
     * @param int    $limit  Maximum results (default 10)
     * @param int    $offset Pagination offset (default 0)
     */
    public function search(string $query, int $limit = 10, int $offset = 0): array
    {
        return $this->client->search($query, $limit, $offset);
    }

    /**
     * Look up product details by identifier.
     *
     * The identifier can be a UPC/EAN barcode, Amazon ASIN, product URL,
     * model number, MPN, or ShopSavvy product ID.
     *
     * @example ShopSavvy::product('B0BSHF7WHW')
     * @example ShopSavvy::product('012345678901')
     *
     * @param string $identifier Product identifier
     */
    public function product(string $identifier): array
    {
        return $this->client->product($identifier);
    }

    /**
     * Get current offers (prices across retailers) for a product.
     *
     * @example ShopSavvy::offers('B0BSHF7WHW')
     * @example ShopSavvy::offers('B0BSHF7WHW', retailer: 'amazon.com')
     *
     * @param string      $identifier Product identifier
     * @param string|null $retailer   Optional retailer filter
     */
    public function offers(string $identifier, ?string $retailer = null): array
    {
        return $this->client->offers($identifier, $retailer);
    }

    /**
     * Get price history for a product over a date range.
     *
     * @example ShopSavvy::priceHistory('B0BSHF7WHW', '2024-01-01', '2024-12-31')
     *
     * @param string      $identifier Product identifier
     * @param string      $startDate  Start date in Y-m-d format
     * @param string      $endDate    End date in Y-m-d format
     * @param string|null $retailer   Optional retailer filter
     */
    public function priceHistory(
        string $identifier,
        string $startDate,
        string $endDate,
        ?string $retailer = null
    ): array {
        return $this->client->priceHistory($identifier, $startDate, $endDate, $retailer);
    }

    /**
     * Get trending deals.
     *
     * @example ShopSavvy::deals()
     * @example ShopSavvy::deals(20)
     *
     * @param int $limit Maximum deals to return
     */
    public function deals(int $limit = 10): array
    {
        return $this->client->deals($limit);
    }

    /**
     * Get current API usage information.
     *
     * @example ShopSavvy::usage()
     */
    public function usage(): array
    {
        return $this->client->usage();
    }

    /**
     * Expose the underlying client for advanced use.
     */
    public function getClient(): ShopSavvyClient
    {
        return $this->client;
    }
}
