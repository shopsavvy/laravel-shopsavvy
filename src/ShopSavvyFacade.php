<?php

declare(strict_types=1);

namespace ShopSavvy\Laravel;

use Illuminate\Support\Facades\Facade;

/**
 * ShopSavvy facade for convenient static access in Laravel applications.
 *
 * @method static array search(string $query, int $limit = 10, int $offset = 0)
 * @method static array product(string $identifier)
 * @method static array offers(string $identifier, ?string $retailer = null)
 * @method static array priceHistory(string $identifier, string $startDate, string $endDate, ?string $retailer = null)
 * @method static array deals(int $limit = 10)
 * @method static array usage()
 * @method static ShopSavvyClient getClient()
 *
 * @see ShopSavvyManager
 */
class ShopSavvyFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ShopSavvyManager::class;
    }
}
