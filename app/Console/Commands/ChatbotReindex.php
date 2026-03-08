<?php

namespace App\Console\Commands;

use App\Models\ChatbotDocument;
use App\Models\Product;
use App\Services\Chatbot\ChatbotContentSyncService;
use App\Services\Chatbot\PineconeService;
use Illuminate\Console\Command;

class ChatbotReindex extends Command
{
    protected $signature = 'chatbot:reindex {--limit= : Limit number of products to reindex}';

    protected $description = 'Re-sync product vectors with structured Pinecone IDs and enriched metadata.';

    public function handle(ChatbotContentSyncService $syncService, PineconeService $pinecone): int
    {
        $limit = (int) ($this->option('limit') ?: 0);

        $query = Product::query()->orderBy('id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $products = $query->get();

        if ($products->isEmpty()) {
            $this->warn('No products found for reindexing.');
            return Command::SUCCESS;
        }

        $productIds = $products->pluck('id')->all();

        $documents = ChatbotDocument::query()
            ->where('type', 'product')
            ->whereIn('product_id', $productIds)
            ->get(['id', 'product_id', 'pinecone_id']);

        $pineconeIdsToDelete = $documents
            ->pluck('pinecone_id')
            ->filter()
            ->values()
            ->all();

        if ($pinecone->isConfigured() && $pineconeIdsToDelete !== []) {
            foreach (array_chunk($pineconeIdsToDelete, 100) as $chunk) {
                $pinecone->deleteByIds($chunk);
            }
        }

        ChatbotDocument::query()
            ->where('type', 'product')
            ->whereIn('product_id', $productIds)
            ->update(['pinecone_id' => null]);

        $bar = $this->output->createProgressBar($products->count());
        $bar->start();

        $failed = 0;

        foreach ($products as $product) {
            $ok = $syncService->syncProduct($product);

            if (!$ok) {
                $failed++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        if ($failed > 0) {
            $this->warn("Reindex completed with {$failed} failures.");
            return Command::FAILURE;
        }

        $this->info('Reindex completed successfully.');

        return Command::SUCCESS;
    }
}
