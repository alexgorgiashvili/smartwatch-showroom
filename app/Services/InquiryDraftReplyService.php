<?php

namespace App\Services;

use App\Models\Inquiry;
use App\Services\Chatbot\RagContextBuilder;
use App\Services\Chatbot\UnifiedAiPolicyService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InquiryDraftReplyService
{
    public function __construct(
        private UnifiedAiPolicyService $policy,
        private RagContextBuilder $ragBuilder
    ) {
    }

    public function generate(Inquiry $inquiry): ?string
    {
        $question = trim((string) $inquiry->message);

        if ($question === '') {
            return null;
        }

        $apiKey = config('services.openai.key');
        $model = config('services.openai.model', 'gpt-4.1-mini');
        $baseUrl = rtrim(config('services.openai.base_url', 'https://api.openai.com/v1'), '/');

        if (! $apiKey) {
            return $this->policy->strictGeorgianFallback();
        }

        $normalizedQuestion = $this->policy->normalizeIncomingMessage($question);
        $ragContext = $this->ragBuilder->build($normalizedQuestion);

        $contextLines = [
            'Customer name: ' . ($inquiry->name ?: '-'),
            'Customer question: ' . $normalizedQuestion,
            'Product: ' . (optional($inquiry->product)->name ?: '-'),
            'Selected color: ' . ($inquiry->selected_color ?: '-'),
        ];

        if ($ragContext) {
            $contextLines[] = 'Knowledge base:';
            $contextLines[] = $ragContext;
        }

        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->policy->websiteSystemPrompt() . "\n\nOUTPUT MODE: Draft a WhatsApp-ready reply for a human operator. Keep it concise (1-3 sentences), actionable, and in Georgian.",
                ],
                [
                    'role' => 'user',
                    'content' => implode("\n", $contextLines),
                ],
            ],
            'temperature' => 0.4,
            'max_tokens' => 220,
        ];

        try {
            $response = Http::withToken($apiKey)
                ->timeout(20)
                ->post($baseUrl . '/chat/completions', $payload);

            if (! $response->successful()) {
                Log::warning('Inquiry draft generation failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'inquiry_id' => $inquiry->id,
                ]);

                return $this->policy->strictGeorgianFallback();
            }

            $reply = trim((string) data_get($response->json(), 'choices.0.message.content', ''));

            if ($reply === '') {
                return $this->policy->strictGeorgianFallback();
            }

            if (! $this->policy->passesStrictGeorgianQa($reply)) {
                return $this->policy->strictGeorgianFallback();
            }

            return $reply;
        } catch (\Throwable $exception) {
            Log::warning('Inquiry draft generation exception', [
                'error' => $exception->getMessage(),
                'inquiry_id' => $inquiry->id,
            ]);

            return $this->policy->strictGeorgianFallback();
        }
    }
}
