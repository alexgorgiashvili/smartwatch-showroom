<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmbeddingService
{
    public function isConfigured(): bool
    {
        return (bool) config('services.openai.key');
    }

    /**
     * @return array<float>
     */
    public function embed(string $input): array
    {
        $embeddings = $this->embedMany([$input]);

        return $embeddings[0] ?? [];
    }

    /**
     * @param array<int, string> $inputs
     * @return array<int, array<float>>
     */
    public function embedMany(array $inputs): array
    {
        $apiKey = config('services.openai.key');
        $model = config('services.openai.embedding_model', 'text-embedding-3-small');
        $baseUrl = rtrim(config('services.openai.base_url', 'https://api.openai.com/v1'), '/');

        if (!$apiKey) {
            throw new \RuntimeException('OpenAI API key is missing.');
        }

        $response = Http::withToken($apiKey)
            ->timeout(20)
            ->post($baseUrl . '/embeddings', [
                'model' => $model,
                'input' => array_values($inputs),
            ]);

        if (!$response->successful()) {
            Log::warning('OpenAI embeddings request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException('Embedding request failed.');
        }

        $data = $response->json('data', []);

        return array_map(static function ($item) {
            return $item['embedding'] ?? [];
        }, $data);
    }
}
