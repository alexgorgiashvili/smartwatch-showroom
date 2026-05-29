<?php

namespace App\Services;

use App\Models\FacebookCompetitorAnalysis;
use App\Models\FacebookCompetitorInsight;
use App\Models\FacebookCompetitorPage;
use App\Models\FacebookCompetitorPost;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookCompetitorAiService
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = (string) config('services.openai.key', '');
        $this->baseUrl = (string) config('services.openai.base_url', 'https://api.openai.com/v1');
    }

    /**
     * Filter a batch of posts for relevance to kids smartwatches with SIM cards.
     * Uses gpt-4.1-nano for speed and cost efficiency.
     */
    public function filterRelevantPosts(Collection $posts): int
    {
        $filtered = 0;

        // Process in batches of 10 for efficiency
        foreach ($posts->chunk(10) as $batch) {
            $postTexts = $batch->map(fn (FacebookCompetitorPost $post) => [
                'id' => $post->id,
                'text' => mb_substr((string) $post->text, 0, 500),
            ])->values()->toArray();

            $prompt = $this->buildRelevancePrompt($postTexts);

            try {
                $response = $this->callOpenAi(
                    (string) config('services.openai.intent_model', 'gpt-4.1-nano'),
                    $prompt,
                    0.1,
                    2000
                );

                Log::info('FacebookCompetitorAi: OpenAI response', [
                    'response' => mb_substr($response, 0, 500),
                ]);

                $results = json_decode($response, true);

                if (!is_array($results)) {
                    Log::warning('FacebookCompetitorAi: Failed to parse relevance response', [
                        'response' => mb_substr($response, 0, 500),
                        'json_error' => json_last_error_msg(),
                    ]);
                    continue;
                }

                foreach ($results as $result) {
                    $post = $batch->firstWhere('id', $result['id'] ?? null);
                    if (!$post) {
                        continue;
                    }

                    $post->update([
                        'is_relevant' => (bool) ($result['is_relevant'] ?? false),
                        'relevance_score' => (int) ($result['relevance_score'] ?? 0),
                        'relevance_reason' => $result['reason'] ?? null,
                        'product_mentions_json' => $result['product_mentions'] ?? null,
                    ]);

                    $filtered++;
                }
            } catch (\Throwable $e) {
                Log::error('FacebookCompetitorAi: Relevance filtering failed', [
                    'error' => $e->getMessage(),
                    'batch_size' => $batch->count(),
                ]);
            }
        }

        return $filtered;
    }

    /**
     * Run comprehensive weekly analysis on relevant posts.
     */
    public function runWeeklyAnalysis(): ?FacebookCompetitorAnalysis
    {
        $pages = FacebookCompetitorPage::active()->get();

        if ($pages->isEmpty()) {
            Log::info('FacebookCompetitorAi: No active pages for analysis');
            return null;
        }

        $relevantPosts = FacebookCompetitorPost::query()
            ->whereIn('competitor_page_id', $pages->pluck('id'))
            ->where('is_relevant', true)
            ->where('scraped_at', '>=', now()->subWeek())
            ->with('competitorPage')
            ->get();

        if ($relevantPosts->isEmpty()) {
            Log::info('FacebookCompetitorAi: No relevant posts for weekly analysis');
            return null;
        }

        $postsData = $relevantPosts->map(fn (FacebookCompetitorPost $post) => [
            'competitor' => $post->competitorPage->name ?? 'Unknown',
            'text' => mb_substr((string) $post->text, 0, 800),
            'posted_at' => $post->posted_at?->toDateTimeString(),
            'likes' => $post->likes_count,
            'comments' => $post->comments_count,
            'shares' => $post->shares_count,
            'product_mentions' => $post->product_mentions_json,
        ])->toArray();

        $prompt = $this->buildAnalysisPrompt($postsData, $pages);

        try {
            $response = $this->callOpenAi(
                (string) config('services.openai.model', 'gpt-4.1-mini'),
                $prompt,
                0.3,
                4000
            );

            $analysisData = json_decode($response, true);

            if (!is_array($analysisData)) {
                Log::error('FacebookCompetitorAi: Failed to parse analysis response');
                return null;
            }

            $analysis = FacebookCompetitorAnalysis::create([
                'analysis_date' => now()->toDateString(),
                'analysis_type' => 'weekly',
                'competitor_page_ids_json' => $pages->pluck('id')->toArray(),
                'posts_analyzed_count' => $relevantPosts->count(),
                'competitive_intelligence_json' => $analysisData['competitive_intelligence'] ?? null,
                'content_strategy_json' => $analysisData['content_strategy'] ?? null,
                'sentiment_analysis_json' => $analysisData['sentiment_analysis'] ?? null,
                'trend_analysis_json' => $analysisData['trend_analysis'] ?? null,
                'recommendations_json' => $analysisData['recommendations'] ?? null,
                'ai_model_used' => config('services.openai.model', 'gpt-4.1-mini'),
                'tokens_used' => 0,
            ]);

            $this->generateInsights($analysisData, $pages);

            Log::info('FacebookCompetitorAi: Weekly analysis complete', [
                'analysis_id' => $analysis->id,
                'posts_analyzed' => $relevantPosts->count(),
            ]);

            return $analysis;
        } catch (\Throwable $e) {
            Log::error('FacebookCompetitorAi: Weekly analysis failed', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Generate insights from analysis and store them.
     */
    private function generateInsights(array $analysisData, Collection $pages): void
    {
        $recommendations = $analysisData['recommendations'] ?? [];

        foreach ($recommendations as $rec) {
            $priority = $rec['priority'] ?? 'medium';
            $category = $rec['category'] ?? 'general';

            $typeMap = [
                'pricing' => 'price_alert',
                'content' => 'content_opportunity',
                'sentiment' => 'sentiment_shift',
                'trend' => 'trend_emerging',
            ];

            FacebookCompetitorInsight::create([
                'insight_type' => $typeMap[$category] ?? 'content_opportunity',
                'priority' => $priority,
                'status' => 'new',
                'title' => $rec['title'] ?? $rec['action'] ?? 'New insight',
                'description' => $rec['reasoning'] ?? $rec['description'] ?? null,
                'data_json' => $rec,
                'competitor_page_id' => $pages->first()?->id,
            ]);
        }
    }

    private function buildRelevancePrompt(array $postTexts): string
    {
        $postsJson = json_encode($postTexts, JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
You are a product relevance classifier. Analyze each Facebook post and determine if it's related to kids smartwatches with SIM cards (საბავშვო სიმ-ბარათიანი სმარტ საათები).

Relevant signals include:
- Kids/children's smartwatches, GPS watches, tracking watches
- SIM card enabled watches for kids
- Features: GPS tracking, video calls, SOS button, geofencing
- Brands: Wonlex, Q12, Q50, X5 Play, or similar kids watch brands
- Price mentions related to kids watches
- Promotions or offers on these products

Posts in Georgian (ქართული) or English.

Input posts:
{$postsJson}

Respond with ONLY a JSON array (no markdown, no explanation):
[
  {
    "id": <post_id>,
    "is_relevant": true/false,
    "relevance_score": 0-100,
    "reason": "brief reason in English",
    "product_mentions": [{"model": "name", "features": ["feature1"]}]
  }
]
PROMPT;
    }

    private function buildAnalysisPrompt(array $postsData, Collection $pages): string
    {
        $postsJson = json_encode($postsData, JSON_UNESCAPED_UNICODE);
        $competitors = $pages->pluck('name')->implode(', ');

        return <<<PROMPT
You are a competitive intelligence analyst specializing in the Georgian kids smartwatch market. Analyze these Facebook posts from competitors: {$competitors}.

Posts data:
{$postsJson}

Provide a comprehensive analysis as JSON (no markdown wrapping):
{
  "competitive_intelligence": {
    "pricing_insights": [{"competitor": "name", "product": "model", "price": "amount", "trend": "up/down/stable"}],
    "feature_highlights": [{"competitor": "name", "features_promoted": ["GPS", "Video call"]}],
    "market_positioning": [{"competitor": "name", "positioning": "description"}]
  },
  "content_strategy": {
    "best_performing_types": [{"type": "video/image/text", "avg_engagement": 0, "competitor": "name"}],
    "posting_frequency": [{"competitor": "name", "posts_per_week": 0}],
    "optimal_times": ["18:00-20:00"],
    "engagement_patterns": "summary"
  },
  "sentiment_analysis": {
    "overall": "positive/neutral/negative",
    "by_competitor": [{"competitor": "name", "sentiment": "positive", "score": 0.8}],
    "common_complaints": ["issue1"],
    "common_praise": ["praise1"]
  },
  "trend_analysis": {
    "emerging_topics": ["topic1"],
    "declining_interests": ["topic1"],
    "viral_content_patterns": "description"
  },
  "recommendations": [
    {
      "priority": "high/medium/low",
      "category": "pricing/content/sentiment/trend",
      "title": "Short title in Georgian",
      "action": "What to do",
      "reasoning": "Why this matters"
    }
  ]
}
PROMPT;
    }

    private function callOpenAi(string $model, string $prompt, float $temperature, int $maxTokens): string
    {
        if ($this->apiKey === '') {
            throw new \RuntimeException('OpenAI API key is missing.');
        }

        $response = Http::withToken($this->apiKey)
            ->timeout(120)
            ->post("{$this->baseUrl}/chat/completions", [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a data analyst. Always respond with valid JSON only, no markdown formatting.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
            ]);

        if (!$response->successful()) {
            Log::error('FacebookCompetitorAi: OpenAI API failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('OpenAI API call failed: ' . $response->status());
        }

        return $response->json('choices.0.message.content', '{}');
    }
}
