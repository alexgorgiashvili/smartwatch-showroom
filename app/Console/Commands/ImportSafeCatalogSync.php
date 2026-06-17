<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportSafeCatalogSync extends Command
{
    protected $signature = 'catalog:import-safe
        {file : Path to the exported JSON payload}
        {--dry-run : Validate payload and print counts without writing}
    ';

    protected $description = 'Import a safe catalog payload by upserting products and variants and replacing product images.';

    public function handle(): int
    {
        $file = (string) $this->argument('file');

        if (! is_file($file)) {
            $relativeCandidate = base_path($file);
            if (is_file($relativeCandidate)) {
                $file = $relativeCandidate;
            }
        }

        if (! is_file($file)) {
            $this->error("Payload file not found: {$file}");

            return self::FAILURE;
        }

        $payload = json_decode((string) file_get_contents($file), true);
        if (! is_array($payload)) {
            $this->error('Unable to decode catalog payload JSON.');

            return self::FAILURE;
        }

        $entries = collect($payload['products'] ?? [])
            ->filter(fn ($entry) => is_array($entry) && is_array($entry['product'] ?? null))
            ->values();

        if ($entries->isEmpty()) {
            $this->warn('No catalog rows found in payload.');

            return self::SUCCESS;
        }

        $productRows = [];
        $variantRows = [];
        $imagesByProductId = [];

        foreach ($entries as $entry) {
            $product = $entry['product'];
            $productId = (int) ($product['id'] ?? 0);

            if ($productId <= 0) {
                throw new RuntimeException('Payload contains a product without a valid id.');
            }

            $productRows[] = $product;
            $imagesByProductId[$productId] = collect($entry['images'] ?? [])
                ->filter(fn ($row) => is_array($row))
                ->map(fn (array $row) => Arr::except($row, ['id']))
                ->values()
                ->all();

            foreach (collect($entry['variants'] ?? [])->filter(fn ($row) => is_array($row)) as $variant) {
                $variantRows[] = $variant;
            }
        }

        if ($this->option('dry-run')) {
            $this->info(sprintf(
                'Dry run: %d product(s), %d image row(s), %d variant row(s) ready to import.',
                count($productRows),
                collect($imagesByProductId)->flatten(1)->count(),
                count($variantRows)
            ));

            return self::SUCCESS;
        }

        DB::transaction(function () use ($productRows, $variantRows, $imagesByProductId): void {
            if ($productRows !== []) {
                $productColumns = array_values(array_diff(array_keys($productRows[0]), ['id']));
                DB::table('products')->upsert($productRows, ['id'], $productColumns);
            }

            if ($variantRows !== []) {
                $variantColumns = array_values(array_diff(array_keys($variantRows[0]), ['id']));
                DB::table('product_variants')->upsert($variantRows, ['id'], $variantColumns);
            }

            foreach ($imagesByProductId as $productId => $rows) {
                DB::table('product_images')->where('product_id', $productId)->delete();

                if ($rows !== []) {
                    DB::table('product_images')->insert($rows);
                }
            }
        });

        $this->info(sprintf(
            'Imported %d product(s), %d image row(s), %d variant row(s) safely.',
            count($productRows),
            collect($imagesByProductId)->flatten(1)->count(),
            count($variantRows)
        ));

        return self::SUCCESS;
    }
}
