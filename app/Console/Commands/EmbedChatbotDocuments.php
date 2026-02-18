<?php

namespace App\Console\Commands;

use App\Models\ChatbotDocument;
use App\Services\Chatbot\EmbeddingService;
use App\Services\Chatbot\PineconeService;
use Illuminate\Console\Command;

class EmbedChatbotDocuments extends Command
{
    protected $signature = 'chatbot:embed-documents {--fresh : Regenerate embeddings for all active documents} {--limit= : Limit number of documents}';
    protected $description = 'Generate embeddings for chatbot documents and upsert into Pinecone.';

    public function handle(EmbeddingService $embedding, PineconeService $pinecone): int
    {
        if (!$embedding->isConfigured()) {
            $this->error('OPENAI_API_KEY is missing.');
            return Command::FAILURE;
        }

        if (!$pinecone->isConfigured()) {
            $this->error('Pinecone configuration is missing.');
            return Command::FAILURE;
        }

        if ($this->option('fresh')) {
            ChatbotDocument::query()->update(['pinecone_id' => null]);
        }

        $limit = $this->option('limit');
        $query = ChatbotDocument::active()->orderBy('id');

        if ($limit) {
            $query->limit((int) $limit);
        }

        $total = $query->count();
        $this->info('Embedding ' . $total . ' documents...');

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunk(20, function ($documents) use ($embedding, $pinecone, $bar) {
            $inputs = $documents->pluck('content_ka')->all();
            $vectors = [];

            $embeddings = $embedding->embedMany($inputs);

            foreach ($documents as $index => $document) {
                $embeddingVector = $embeddings[$index] ?? [];

                if ($embeddingVector === []) {
                    $bar->advance();
                    continue;
                }

                $pineconeId = $document->pinecone_id ?: 'doc_' . $document->id;

                if (!$document->pinecone_id) {
                    $document->pinecone_id = $pineconeId;
                    $document->save();
                }

                $metadata = [
                    'key' => $document->key,
                    'type' => $document->type,
                    'title' => $document->title,
                    'product_id' => $document->product_id,
                ];

                $vectors[] = [
                    'id' => $pineconeId,
                    'values' => $embeddingVector,
                    'metadata' => array_filter($metadata, static fn ($value) => $value !== null),
                ];

                $bar->advance();
            }

            $pinecone->upsert($vectors);
        });

        $bar->finish();
        $this->newLine();
        $this->info('Embedding completed.');

        return Command::SUCCESS;
    }
}
