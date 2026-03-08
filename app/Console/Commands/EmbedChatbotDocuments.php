<?php

namespace App\Console\Commands;

use App\Models\ChatbotDocument;
use App\Services\Chatbot\DocumentChunker;
use App\Services\Chatbot\EmbeddingService;
use App\Services\Chatbot\PineconeService;
use Illuminate\Console\Command;

class EmbedChatbotDocuments extends Command
{
    protected $signature = 'chatbot:embed-documents {--fresh : Regenerate embeddings for all active documents} {--limit= : Limit number of documents}';
    protected $description = 'Generate embeddings for chatbot documents and upsert into Pinecone.';

    public function handle(EmbeddingService $embedding, PineconeService $pinecone, DocumentChunker $chunker): int
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

        $query->chunk(20, function ($documents) use ($embedding, $pinecone, $chunker, $bar) {
            $vectors = [];

            foreach ($documents as $document) {

                $pineconeId = $document->pinecone_id ?: $this->resolvePineconeId($document);

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

                $documentMetadata = is_array($document->metadata) ? $document->metadata : [];

                if ($document->type === 'product') {
                    $chunks = $chunker->chunk((string) $document->content_ka, 'product');
                    $inputs = array_map(static function (array $chunk): string {
                        return (string) ($chunk['section'] ?? 'product') . "\n" . (string) ($chunk['text'] ?? '');
                    }, $chunks);

                    $embeddings = $inputs !== [] ? $embedding->embedMany($inputs) : [];
                    $chunkCount = count($chunks);

                    if ($chunkCount > 0) {
                        $documentMetadata['chunk_count'] = $chunkCount;
                        $document->metadata = $documentMetadata;
                        $document->save();
                    }

                    foreach ($chunks as $chunkIndex => $chunk) {
                        $embeddingVector = $embeddings[$chunkIndex] ?? [];

                        if ($embeddingVector === []) {
                            continue;
                        }

                        $vectors[] = [
                            'id' => $pineconeId . '#chunk-' . $chunkIndex,
                            'values' => $embeddingVector,
                            'metadata' => array_filter(array_merge($documentMetadata, $metadata, [
                                'chunk_index' => $chunkIndex,
                                'section' => (string) ($chunk['section'] ?? 'product'),
                                'text' => (string) ($chunk['text'] ?? ''),
                            ]), static fn ($value) => $value !== null),
                        ];
                    }
                } else {
                    $embeddingVector = $embedding->embed((string) $document->content_ka);

                    if ($embeddingVector === []) {
                        $bar->advance();
                        continue;
                    }

                    $vectors[] = [
                        'id' => $pineconeId,
                        'values' => $embeddingVector,
                        'metadata' => array_filter(array_merge($documentMetadata, $metadata), static fn ($value) => $value !== null),
                    ];
                }

                $bar->advance();
            }

            $pinecone->upsert($vectors);
        });

        $bar->finish();
        $this->newLine();
        $this->info('Embedding completed.');

        return Command::SUCCESS;
    }

    private function resolvePineconeId(ChatbotDocument $document): string
    {
        if ($document->type === 'product') {
            $metadata = is_array($document->metadata) ? $document->metadata : [];
            $category = (string) ($metadata['category'] ?? 'smartwatch');
            $category = trim(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $category) ?? 'smartwatch'), '-');

            if ($category === '') {
                $category = 'smartwatch';
            }

            return 'product#' . $category . '#' . $document->product_id;
        }

        return 'doc_' . $document->id;
    }
}
