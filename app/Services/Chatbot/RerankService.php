<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RerankService
{
    private const CIRCUIT_KEY = 'chatbot:cohere_rerank:circuit_open_until';
    private const CIRCUIT_LOG_KEY = 'chatbot:cohere_rerank:circuit_log_throttle';

    public function isConfigured(): bool
    {
        return (bool) config('services.cohere.enabled', true) && (bool) config('services.cohere.api_key');
    }

    /**
     * @param array<int, array{id: string, text: string, metadata?: array<string, mixed>}> $documents
     * @return array<int, array{id: string, text: string, score: float, metadata?: array<string, mixed>}>
     */
    public function rerank(string $query, array $documents, int $topN = 5): array
    {
        $topN = max(1, $topN);

        if ($documents === []) {
            return [];
        }

        if (!$this->isConfigured()) {
            return $this->fallbackDocuments($documents, $topN);
        }

        if ($this->isCircuitOpen()) {
            return $this->fallbackDocuments($documents, $topN);
        }

        try {
            $verify = config('services.cohere.verify', true);
            if (is_string($verify) && $verify !== '' && !str_starts_with($verify, DIRECTORY_SEPARATOR) && !preg_match('/^[A-Za-z]:\\\\/', $verify)) {
                $verify = base_path($verify);
            }

            $response = Http::withToken((string) config('services.cohere.api_key'))
                ->acceptJson()
                ->withOptions(['verify' => $verify])
                ->connectTimeout((int) config('services.cohere.connect_timeout', 3))
                ->timeout((int) config('services.cohere.timeout', 8))
                ->post('https://api.cohere.com/v1/rerank', [
                    'model' => config('services.cohere.model', 'rerank-english-v3.0'),
                    'query' => $query,
                    'documents' => array_map(static fn (array $doc): string => (string) ($doc['text'] ?? ''), $documents),
                    'top_n' => min($topN, count($documents)),
                    'return_documents' => false,
                ]);

            if (!$response->successful()) {
                Log::warning('Cohere rerank failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return $this->fallbackDocuments($documents, $topN);
            }

            $results = $response->json('results', []);

            if (!is_array($results) || $results === []) {
                return [];
            }

            $reranked = [];

            foreach ($results as $item) {
                $index = (int) ($item['index'] ?? -1);

                if ($index < 0 || !isset($documents[$index])) {
                    continue;
                }

                $doc = $documents[$index];
                $doc['score'] = (float) ($item['relevance_score'] ?? 0.0);
                $reranked[] = $doc;
            }

            return array_slice($reranked, 0, $topN);
        } catch (\Throwable $exception) {
            if ($this->isSslCertificateException($exception)) {
                $this->openCircuit();

                if (Cache::add(self::CIRCUIT_LOG_KEY, true, now()->addMinutes(5))) {
                    Log::warning('Cohere rerank disabled temporarily due to SSL certificate error', [
                        'cooldown_seconds' => (int) config('services.cohere.circuit_cooldown_seconds', 900),
                    ]);
                }
            }

            Log::warning('Cohere rerank exception', [
                'error' => $exception->getMessage(),
            ]);

            return $this->fallbackDocuments($documents, $topN);
        }
    }

    /**
     * @param array<int, array{id: string, text: string, metadata?: array<string, mixed>}> $documents
     * @return array<int, array{id: string, text: string, score: float, metadata?: array<string, mixed>}>
     */
    private function fallbackDocuments(array $documents, int $topN): array
    {
        return array_slice(array_map(static function (array $document): array {
            $document['score'] = 0.0;

            return $document;
        }, $documents), 0, $topN);
    }

    private function isCircuitOpen(): bool
    {
        $openUntil = Cache::get(self::CIRCUIT_KEY);

        return is_int($openUntil) && $openUntil > time();
    }

    private function openCircuit(): void
    {
        $cooldownSeconds = max(60, (int) config('services.cohere.circuit_cooldown_seconds', 900));
        Cache::put(self::CIRCUIT_KEY, time() + $cooldownSeconds, now()->addSeconds($cooldownSeconds));
    }

    private function isSslCertificateException(\Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'curl error 60') || str_contains($message, 'ssl certificate problem');
    }
}
