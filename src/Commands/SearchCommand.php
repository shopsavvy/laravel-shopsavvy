<?php

declare(strict_types=1);

namespace ShopSavvy\Laravel\Commands;

use Illuminate\Console\Command;
use ShopSavvy\Laravel\Exceptions\ShopSavvyException;
use ShopSavvy\Laravel\ShopSavvyManager;

class SearchCommand extends Command
{
    protected $signature = 'shopsavvy:search
        {query : Search terms (e.g. "AirPods Pro")}
        {--limit=10 : Maximum number of results}
        {--offset=0 : Pagination offset}
        {--json : Output raw JSON}';

    protected $description = 'Search for products using the ShopSavvy Data API';

    public function handle(ShopSavvyManager $shopsavvy): int
    {
        $query  = $this->argument('query');
        $limit  = (int) $this->option('limit');
        $offset = (int) $this->option('offset');

        $this->line('');
        $this->line("  Searching for: <info>{$query}</info>");
        $this->line('');

        try {
            $result = $shopsavvy->search($query, $limit, $offset);
        } catch (ShopSavvyException $e) {
            $this->error('ShopSavvy API error: ' . $e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        // GET /products/search answers { data: [product, ...], pagination: { total, ... } }.
        // Search results carry product details only (no prices); use shopsavvy:price for offers.
        $products = $result['data'] ?? [];

        if (empty($products)) {
            $this->warn('  No results found.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($products as $product) {
            $rows[] = [
                $this->truncate((string) ($product['title'] ?? '—'), 50),
                $product['brand'] ?? '—',
                isset($product['barcode']) ? (string) $product['barcode'] : '—',
                $product['amazon'] ?? '—',
            ];
        }

        $this->table(
            ['Product', 'Brand', 'Barcode', 'ASIN'],
            $rows
        );

        $total = $result['pagination']['total'] ?? count($products);
        $this->line('');
        $this->line("  Found <comment>{$total}</comment> results. Showing " . count($products) . '.');
        $this->line('');

        return self::SUCCESS;
    }

    private function truncate(string $value, int $max): string
    {
        return mb_strlen($value) > $max ? mb_substr($value, 0, $max - 3) . '...' : $value;
    }
}
