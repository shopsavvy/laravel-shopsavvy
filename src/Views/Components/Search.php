<?php

declare(strict_types=1);

namespace ShopSavvy\Laravel\Views\Components;

use Illuminate\View\Component;
use Illuminate\View\View;
use ShopSavvy\Laravel\Exceptions\ShopSavvyException;
use ShopSavvy\Laravel\ShopSavvyManager;

/**
 * Blade component that renders product search results.
 *
 * Usage:
 *   <x-shopsavvy-search query="airpods" />
 *   <x-shopsavvy-search query="iPhone 16" :limit="8" />
 */
class Search extends Component
{
    /** @var array<int, mixed> */
    public array $products = [];

    /** @var int */
    public int $total = 0;

    /** @var string|null */
    public ?string $errorMessage = null;

    public function __construct(
        private ShopSavvyManager $shopsavvy,
        public string $query,
        public int $limit = 10,
        public int $offset = 0,
    ) {
        $this->loadResults();
    }

    private function loadResults(): void
    {
        try {
            $result          = $this->shopsavvy->search($this->query, $this->limit, $this->offset);
            $this->products  = $result['data'] ?? $result['products'] ?? $result['results'] ?? $result ?? [];
            $this->total     = (int) ($result['total'] ?? count($this->products));
        } catch (ShopSavvyException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render(): View
    {
        return view('shopsavvy::components.search');
    }
}
