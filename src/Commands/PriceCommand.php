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
        {--retailer= : Only offers from this retailer domain (e.g. amazon.com)}
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
        // GET /products/offers answers { data: [ product + { offers: [...] } ] }; an
        // identifier can match more than one product, so offers are gathered from all.

        $offers = [];
        foreach ($offersResult['data'] ?? [] as $product) {
            foreach ($product['offers'] ?? [] as $offer) {
                $offers[] = $offer;
            }
        }
        usort($offers, fn (array $a, array $b) => ($a['price'] ?? PHP_FLOAT_MAX) <=> ($b['price'] ?? PHP_FLOAT_MAX));

        if (empty($offers)) {
            $this->warn('  No offers found for this product.');
        } else {
            $rows = [];
            foreach ($offers as $offer) {
                $rows[] = [
                    $offer['retailer'] ?? '—',
                    isset($offer['price']) ? '$' . number_format((float) $offer['price'], 2) : '—',
                    $offer['condition'] ?? '—',
                    match ($offer['availability'] ?? null) {
                        'in'    => '<fg=green>Yes</>',
                        'out'   => '<fg=red>No</>',
                        default => '—',
                    },
                    $this->truncate($offer['URL'] ?? '—', 40),
                ];
            }

            $this->table(
                ['Retailer', 'Price', 'Condition', 'In Stock', 'URL'],
                $rows
            );

            $best = $offers[0];
            if (isset($best['price'])) {
                $this->line('  Best price: <info>$' . number_format((float) $best['price'], 2) . '</info> at ' . ($best['retailer'] ?? '—'));
            }
        }

        // ---- Optionally render price history ----
        // GET /products/offers/history answers { data: [ product + { offers: [ offer + { history: [ {timestamp, price, currency, availability} ] } ] } ] }.

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

                $historyRows = [];
                foreach ($historyResult['data'] ?? [] as $product) {
                    foreach ($product['offers'] ?? [] as $offer) {
                        foreach ($offer['history'] ?? [] as $point) {
                            $historyRows[] = [
                                $point['timestamp'] ?? '—',
                                $offer['retailer'] ?? '—',
                                isset($point['price'])
                                    ? number_format((float) $point['price'], 2) . ' ' . ($point['currency'] ?? '')
                                    : '—',
                            ];
                        }
                    }
                }
                usort($historyRows, fn (array $a, array $b) => strcmp((string) $a[0], (string) $b[0]));

                if (empty($historyRows)) {
                    $this->warn('  No price history available.');
                } else {
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
