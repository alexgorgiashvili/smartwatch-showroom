<?php

namespace App\Services\Chatbot;

use App\Models\ChatbotDocument;
use Illuminate\Support\Facades\Log;

class RagContextBuilder
{
    private const TYPE_LABELS = [
        'product' => 'პროდუქტი',
        'faq' => 'ხშირი კითხვა',
        'policy' => 'პოლიტიკა',
        'support' => 'ტექნიკური დახმარება',
    ];

    public function __construct(
        private EmbeddingService $embedding,
        private PineconeService $pinecone,
        private UnifiedAiPolicyService $policy
    ) {
    }

    public function build(string $question, int $topK = 5): ?string
    {
        if (!$this->embedding->isConfigured() || !$this->pinecone->isConfigured()) {
            return null;
        }

        try {
            $normalizedQuestion = $this->policy->normalizeIncomingMessage($question);
            $vector = $this->embedding->embed($normalizedQuestion !== '' ? $normalizedQuestion : $question);

            if ($vector === []) {
                return null;
            }

            $matches = $this->pinecone->query($vector, $topK);

            if ($matches === []) {
                return null;
            }

            $keys = collect($matches)
                ->map(fn ($match) => data_get($match, 'metadata.key'))
                ->filter()
                ->values()
                ->all();

            if ($keys === []) {
                return null;
            }

            $documents = ChatbotDocument::active()
                ->whereIn('key', $keys)
                ->get()
                ->keyBy('key');

            $parts = [];

            foreach ($matches as $match) {
                $key = data_get($match, 'metadata.key');

                if (!$key || !$documents->has($key)) {
                    continue;
                }

                $parts[] = $this->formatDocument($documents->get($key));
            }

            if ($parts === []) {
                return null;
            }

            return implode("\n\n", $parts);
        } catch (\Throwable $exception) {
            Log::warning('RAG context build failed', [
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function formatDocument(ChatbotDocument $document): string
    {
        $label = self::TYPE_LABELS[$document->type] ?? ucfirst($document->type);
        $header = $document->title ? $document->title : $label;
        $lines = [$header, $document->content_ka];
        $metadata = $document->metadata ?? [];

        if (!empty($metadata['slug'])) {
            $lines[] = 'ბმული: ' . url('/products/' . $metadata['slug']);
        }

        return implode("\n", array_filter($lines));
    }
}
