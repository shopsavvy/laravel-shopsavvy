<?php

declare(strict_types=1);

namespace ShopSavvy\Laravel\Commands;

use Illuminate\Console\Command;
use ShopSavvy\Laravel\Exceptions\ShopSavvyException;
use ShopSavvy\Laravel\ShopSavvyManager;

class PriceCommand extends Command
{
    protected $signature = 'shopsavvy:price
        {identifier : Product identifier (ASIN, barcode, URL, model number)}
        {--retailer= : Filter offers by retailer}
        {--history : Include price history for the past 30 days}
        {--json : Output raw JSON}';

    protected $description = 'Look up current prices and offers for a product via the ShopSavvy Data API';

    public function handle(ShopSavvyManager $shopsavvy): int
    {
        $identifier = $this->argument('identifier');
        $retailer   = $this->option('retailer') ?: null;

        $this->line('');
        $this->line("  Looking up: <info>{$identifier}</info>");
        $this->line('');

        try {
            $offersResult = $shopsavvy->offers($identifier, $retailer);
        } catch (ShopSavvyException $e) {
            $this->error('ShopSavvy API error: ' . $e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($offersResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            if ($this->option('history')) {
                $this->line('');
                try {
                    $historyResult = $shopsavvy->priceHistory(
                        $identifier,
                        date('Y-m-d', strtotime('-30 days')),
                        date('Y-m-d')
                    );
                    $this->line(json_encode($historyResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                } catch (ShopSavvyException $e) {
                    $this->error('Price history error: ' . $e->getMessage());
                }
            }

            return self::SUCCESS;
        }

        // ---- Render offers table ----

        $offers = $offersResult['data'] ?? $offersResult['offers'] ?? $offersResult ?? [];

        if (empty($offers)) {
            $this->warn('  No offers found for this product.');
        } else {
            $rows = [];
            foreach ($offers as $offer) {
                $retailerName = $offer['retailer'] ?? $offer['store'] ?? '—';
                $price        = isset($offer['price']) ? '$' . number_format((float) $offer['price'], 2) : '—';
                $condition    = $offer['condition'] ?? 'New';
                $inStock      = isset($offer['in_stock'])
                    ? ($offer['in_stock'] ? '<fg=green>Yes</>' : '<fg=red>No</>')
                    : '—';
                $url          = $this->truncate($offer['url'] ?? '—', 40);

                $rows[] = [$retailerName, $price, $condition, $inStock, $url];
            }

            $this->table(
                ['Retailer', 'Price', 'Condition', 'In Stock', 'URL'],
                $rows
            );

            // Highlight best price
            $prices = array_filter(array_column($offers, 'price'));
            if (!empty($prices)) {
                $best = min($prices);
                $this->line("  Best price: <info>\${$best}</info>");
            }
        }

        // ---- Optionally render price history ----

        if ($this->option('history')) {
            $this->line('');
            $this->line('  <comment>Price History (last 30 days)</comment>');
            $this->line('');

            try {
                $historyResult = $shopsavvy->priceHistory(
                    $identifier,
                    date('Y-m-d', strtotime('-30 days')),
                    date('Y-m-d'),
                    $retailer
                );

                $history = $historyResult['data'] ?? $historyResult['history'] ?? $historyResult ?? [];

                if (empty($history)) {
                    $this->warn('  No price history available.');
                } else {
                    $historyRows = [];
                    foreach ($history as $entry) {
                        $date         = $entry['date'] ?? $entry['timestamp'] ?? '—';
                        $retailerName = $entry['retailer'] ?? $entry['store'] ?? '—';
                        $price        = isset($entry['price']) ? '$' . number_format((float) $entry['price'], 2) : '—';

                        $historyRows[] = [$date, $retailerName, $price];
                    }

                    $this->table(['Date', 'Retailer', 'Price'], $historyRows);
                }
            } catch (ShopSavvyException $e) {
                $this->error('Price history error: ' . $e->getMessage());
            }
        }

        $this->line('');

        return self::SUCCESS;
    }

    private function truncate(string $value, int $max): string
    {
        return mb_strlen($value) > $max ? mb_substr($value, 0, $max - 3) . '...' : $value;
    }
}
