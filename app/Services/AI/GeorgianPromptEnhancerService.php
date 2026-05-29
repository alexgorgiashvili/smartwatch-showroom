<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\PromptEnhancementLog;

class GeorgianPromptEnhancerService
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.openai.key');
        // Using a capable model for complex linguistic tasks
        $this->model = config('services.openai.model', 'gpt-4o');
        $this->baseUrl = config('services.openai.base_url', 'https://api.openai.com/v1');
    }

    /**
     * Enhances a raw user prompt focusing on Georgian grammar, syntax, and cultural context.
     *
     * @param string $rawPrompt The original user instructions
     * @param string $targetRegister "formal" (თქვენობითი) or "informal" (შენობითი)
     * @return array
     */
    public function enhancePrompt(string $rawPrompt, string $targetRegister = 'professional'): array
    {
        $systemPrompt = $this->buildSystemPrompt();

        $userContent = json_encode([
            'original_prompt' => $rawPrompt,
            'target_tone_register' => $targetRegister
        ], JSON_UNESCAPED_UNICODE);

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])->timeout(45)->post("{$this->baseUrl}/chat/completions", [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userContent],
                ],
                'temperature' => 0.3, // Low temperature for consistent linguistic corrections
                'response_format' => ['type' => 'json_object']
            ]);

            if ($response->failed()) {
                Log::error('GeorgianPromptEnhancerService failed', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);
                return ['success' => false, 'error' => 'API Error'];
            }

            $content = $response->json('choices.0.message.content', '');
            $result = json_decode($content, true);

            if (!isset($result['enhanced_prompt'])) {
                return ['success' => false, 'error' => 'Invalid format returned by AI'];
            }

            // Save to feedback loop database
            $log = PromptEnhancementLog::create([
                'original_prompt' => $rawPrompt,
                'enhanced_prompt' => $result['enhanced_prompt'],
                'analysis_metadata' => [
                    'detected_dialect_or_slang' => $result['detected_issues'] ?? [],
                    'grammar_corrections' => $result['grammar_corrections'] ?? [],
                    'cultural_notes' => $result['cultural_notes'] ?? '',
                    'register_applied' => $targetRegister
                ]
            ]);

            return [
                'success' => true,
                'enhanced_prompt' => $result['enhanced_prompt'],
                'metadata' => $result,
                'log_id' => $log->id
            ];

        } catch (\Exception $e) {
            Log::error('GeorgianPromptEnhancerService exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Connection failed'];
        }
    }

    /**
     * Submit user feedback for a specific enhancement log
     */
    public function submitFeedback(int $logId, bool $isAccepted, ?string $feedback = null): void
    {
        $log = PromptEnhancementLog::find($logId);
        if ($log) {
            $log->update([
                'is_accepted' => $isAccepted,
                'feedback' => $feedback
            ]);
        }
    }

    private function buildSystemPrompt(): string
    {
        return <<<PROMPT
You are an expert Georgian Linguist and Prompt Engineer. Your task is to analyze, correct, and enhance user prompts written in Georgian, preparing them to be processed by another AI for marketing content generation.

Georgian Linguistic Standards & Rules to enforce:
1. **Grammar & Syntax**:
   - Correct Subject-Verb-Object agreement.
   - Enforce proper use of the Ergative case (მოთხრობითი ბრუნვა) for transitive verbs in the aorist series.
   - Fix incorrect preverbs (ზმნისწინები).
   - Ensure proper pluralization (e.g., numbers > 1 take singular nouns: "5 საათი" not "5 საათები").
2. **Register & Tone (ფორმა/ტონი)**:
   - If target_tone_register is "professional" or "formal", use the polite 'თქვენ' (V-form) and avoid slang.
   - If target_tone_register is "casual", "friendly", or "informal", use 'შენ' (T-form) but keep it grammatically correct.
3. **Barbarisms & Slang (ბარბარიზმები)**:
   - Detect and remove Russian/English barbarisms commonly used in Georgian slang (e.g., "პროსტა" -> "უბრალოდ", "ვაბშე" -> "საერთოდ", "კაროჩე" -> "მოკლედ", "სვეჟი" -> "ახალი/კარგი"), unless the context heavily implies a Gen-Z meme tone.
4. **Clarity & Prompt Engineering**:
   - Expand vague instructions into clear, actionable constraints.
   - Make the prompt highly specific.

Input Format (JSON):
{"original_prompt": "...", "target_tone_register": "..."}

Output Format: You MUST return a valid JSON object with the following structure:
{
  "enhanced_prompt": "The fully rewritten, grammatically perfect, and structurally optimized Georgian prompt",
  "detected_issues": ["list of slang, barbarisms, or vague points found in original"],
  "grammar_corrections": ["list of specific grammatical fixes applied, e.g., 'fixed ergative case on noun X'"],
  "cultural_notes": "Brief note on why certain words were chosen for the Georgian market"
}
PROMPT;
    }
}
