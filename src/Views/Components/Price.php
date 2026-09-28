<?php

declare(strict_types=1);

namespace ShopSavvy\Laravel\Views\Components;

use Illuminate\View\Component;
use Illuminate\View\View;
use ShopSavvy\Laravel\Exceptions\ShopSavvyException;
use ShopSavvy\Laravel\ShopSavvyManager;

/**
 * Blade component that renders current prices for a product.
 *
 * Usage:
 *   <x-shopsavvy-price identifier="B0BSHF7WHW" />
 *   <x-shopsavvy-price identifier="B0BSHF7WHW" :limit="5" retailer="amazon.com" />
 */
class Price extends Component
{
    /** @var array<int, mixed> */
    public array $offers = [];

    /** @var string|null */
    public ?string $errorMessage = null;

    public function __construct(
        private ShopSavvyManager $shopsavvy,
        public string $identifier,
        public int $limit = 5,
        public ?string $retailer = null,
        public string $currency = 'USD',
    ) {
        $this->loadOffers();
    }

    private function loadOffers(): void
    {
        try {
            // { data: [ product + { offers: [...] } ] } -> every offer, cheapest first.
            $result = $this->shopsavvy->offers($this->identifier, $this->retailer);
            $all    = [];
            foreach ($result['data'] ?? [] as $product) {
                foreach ($product['offers'] ?? [] as $offer) {
                    if (isset($offer['price'])) {
                        $all[] = $offer;
                    }
                }
            }
            usort($all, fn (array $a, array $b) => $a['price'] <=> $b['price']);
            $this->offers = array_slice($all, 0, $this->limit);
        } catch (ShopSavvyException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render(): View
    {
        return view('shopsavvy::components.price');
    }
}
