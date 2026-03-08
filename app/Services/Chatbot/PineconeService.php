<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PineconeService
{
    public function isConfigured(): bool
    {
        return (bool) config('services.pinecone.api_key') && (bool) config('services.pinecone.host');
    }

    public function upsert(array $vectors, ?string $namespace = null): void
    {
        if ($vectors === []) {
            return;
        }

        $apiKey = config('services.pinecone.api_key');
        $baseUrl = $this->baseUrl();

        if (!$apiKey || !$baseUrl) {
            throw new \RuntimeException('Pinecone configuration is missing.');
        }

        $payload = ['vectors' => $vectors];
        $namespace = $namespace ?? config('services.pinecone.namespace');

        if ($namespace) {
            $payload['namespace'] = $namespace;
        }

        $response = Http::withHeaders([
                'Api-Key' => $apiKey,
                'Content-Type' => 'application/json',
            ])
            ->timeout(20)
            ->post($baseUrl . '/vectors/upsert', $payload);

        if (!$response->successful()) {
            Log::warning('Pinecone upsert failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException('Pinecone upsert failed.');
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function query(
        array $vector,
        int $topK = 5,
        array $filter = [],
        ?string $namespace = null,
        array $sparseVector = [],
        ?float $alpha = null
    ): array
    {
        $apiKey = config('services.pinecone.api_key');
        $baseUrl = $this->baseUrl();

        if (!$apiKey || !$baseUrl) {
            return [];
        }

        if ($alpha !== null && $sparseVector !== []) {
            $alpha = max(0.0, min(1.0, $alpha));
            $vector = array_map(static fn ($value) => (float) $value * $alpha, $vector);

            if (isset($sparseVector['values']) && is_array($sparseVector['values'])) {
                $sparseVector['values'] = array_map(
                    static fn ($value) => (float) $value * (1 - $alpha),
                    $sparseVector['values']
                );
            }
        }

        $payload = [
            'vector' => array_values($vector),
            'topK' => $topK,
            'includeMetadata' => true,
        ];

        if ($sparseVector !== [] && isset($sparseVector['indices'], $sparseVector['values'])) {
            $payload['sparseVector'] = [
                'indices' => array_values($sparseVector['indices']),
                'values' => array_values($sparseVector['values']),
            ];
        }

        if ($filter !== []) {
            $payload['filter'] = $filter;
        }

        $namespace = $namespace ?? config('services.pinecone.namespace');

        if ($namespace) {
            $payload['namespace'] = $namespace;
        }

        $response = Http::withHeaders([
                'Api-Key' => $apiKey,
                'Content-Type' => 'application/json',
            ])
            ->timeout(20)
            ->post($baseUrl . '/query', $payload);

        if (!$response->successful()) {
            Log::warning('Pinecone query failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        }

        return $response->json('matches', []);
    }

    public function deleteByIds(array $ids, ?string $namespace = null): void
    {
        $ids = array_values(array_filter($ids));

        if ($ids === []) {
            return;
        }

        $apiKey = config('services.pinecone.api_key');
        $baseUrl = $this->baseUrl();

        if (!$apiKey || !$baseUrl) {
            throw new \RuntimeException('Pinecone configuration is missing.');
        }

        $payload = ['ids' => $ids];
        $namespace = $namespace ?? config('services.pinecone.namespace');

        if ($namespace) {
            $payload['namespace'] = $namespace;
        }

        $response = Http::withHeaders([
                'Api-Key' => $apiKey,
                'Content-Type' => 'application/json',
            ])
            ->timeout(20)
            ->post($baseUrl . '/vectors/delete', $payload);

        if ($response->status() === 404 && str_contains($response->body(), 'Namespace not found')) {
            Log::info('Pinecone namespace missing during delete; treating as no-op', [
                'namespace' => $namespace,
                'ids' => $ids,
            ]);

            return;
        }

        if (!$response->successful()) {
            Log::warning('Pinecone delete failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'ids' => $ids,
            ]);

            throw new \RuntimeException('Pinecone delete failed.');
        }
    }

    private function baseUrl(): string
    {
        return rtrim(config('services.pinecone.host', ''), '/');
    }
}
