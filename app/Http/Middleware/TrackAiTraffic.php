<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class TrackAiTraffic
{
    /**
     * Known AI bot user agents
     */
    private const AI_BOTS = [
        'GPTBot' => 'openai-gpt-family',
        'ChatGPT-User' => 'openai-gpt-family',
        'Claude-Web' => 'anthropic-claude-family',
        'anthropic-ai' => 'anthropic-claude-family',
        'Claude-Bot' => 'anthropic-claude-family',
        'Google-Extended' => 'google-gemini-family',
        'GoogleOther' => 'google-gemini-family',
        'Gemini-Bot' => 'google-gemini-family',
        'Bard-Bot' => 'google-gemini-family',
        'PerplexityBot' => 'perplexity-family',
        'Perplexity-AI' => 'perplexity-family',
        'Meta-ExternalAgent' => 'meta-llama-family',
        'Llama-Bot' => 'meta-llama-family',
        'BingPreview' => 'microsoft-copilot-family',
        'Copilot-Bot' => 'microsoft-copilot-family',
        'Bing-Chat' => 'microsoft-copilot-family',
        'cohere-ai' => 'cohere-command-family',
        'Cohere-Bot' => 'cohere-command-family',
        'Mistral-Bot' => 'mistral-family',
        'MistralAI' => 'mistral-family',
        'AI21-Bot' => 'ai21-jurassic-family',
        'Jurassic-Bot' => 'ai21-jurassic-family',
        'YouBot' => 'you-family',
        'You-AI' => 'you-family',
        'Pi-Bot' => 'inflection-pi-family',
        'Character-AI' => 'character-ai-family',
        'HuggingFaceBot' => 'huggingface-family',
        'StabilityAI-Bot' => 'stability-family',
        'Amazon-Bot' => 'amazon-titan-family',
        'ERNIE-Bot' => 'baidu-ernie-family',
        'Qwen-Bot' => 'alibaba-qwen-family',
        'YaLM-Bot' => 'yandex-yalm-family',
        'SearchGPT' => 'searchgpt-family',
        'Phind-Bot' => 'phind-family',
        'Kagi-Bot' => 'kagi-family',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $userAgent = $request->userAgent();
        $aiBot = $this->detectAiBot($userAgent);

        if ($aiBot) {
            $this->logAiTraffic($request, $aiBot);
        }

        return $next($request);
    }

    /**
     * Detect if request is from an AI bot
     */
    private function detectAiBot(?string $userAgent): ?array
    {
        if (!$userAgent) {
            return null;
        }

        foreach (self::AI_BOTS as $botName => $family) {
            if (stripos($userAgent, $botName) !== false) {
                return [
                    'bot_name' => $botName,
                    'family' => $family,
                    'user_agent' => $userAgent,
                ];
            }
        }

        return null;
    }

    /**
     * Log AI traffic for analytics
     */
    private function logAiTraffic(Request $request, array $aiBot): void
    {
        $logData = [
            'timestamp' => now()->toIso8601String(),
            'ai_bot' => $aiBot['bot_name'],
            'ai_family' => $aiBot['family'],
            'user_agent' => $aiBot['user_agent'],
            'url' => $request->fullUrl(),
            'path' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'referer' => $request->header('referer'),
        ];

        // Log to dedicated AI traffic channel
        Log::channel('daily')->info('AI_TRAFFIC', $logData);

        // Store in database for analytics (async to avoid performance impact)
        try {
            DB::table('ai_traffic')->insert([
                'ai_bot' => $aiBot['bot_name'],
                'ai_family' => $aiBot['family'],
                'user_agent' => $aiBot['user_agent'],
                'url' => $request->fullUrl(),
                'path' => $request->path(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'referer' => $request->header('referer'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Silently fail to avoid breaking the request
            Log::warning('Failed to store AI traffic in database', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
