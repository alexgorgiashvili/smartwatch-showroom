<?php

namespace App\Services\Chatbot;

class UnifiedAiPolicyService
{
    private const TRANSLITERATION_MAP = [
        'gamarjoba' => 'გამარჯობა',
        'gamarjveba' => 'გამარჯობა',
        'rogor xar' => 'როგორ ხარ',
        'rogor khar' => 'როგორ ხარ',
        'madloba' => 'მადლობა',
        'naxvamdis' => 'ნახვამდის',
        'nakhvamdis' => 'ნახვამდის',
        'ra ghirs' => 'რა ღირს',
        'fasia' => 'ფასია',
        'fasi' => 'ფასი',
        'modeli' => 'მოდელი',
        'modelebi' => 'მოდელები',
        'mitana' => 'მიტანა',
        'miwodeba' => 'მიწოდება',
        'maragi' => 'მარაგი',
        'aris' => 'არის',
        'rame' => 'რამე',
    ];

    private const TYPO_REPLACEMENTS = [
        '/გაქვტ/u' => 'გაქვთ',
        '/ამისპას/u' => 'ამის ფას',
        '/ამისფას/u' => 'ამის ფას',
        '/ფასალარია/u' => 'ფასი რა არის',
    ];

    private const INTENT_PATTERNS = [
        '/(ფასი|ღირს|ღირებულ|რამდენი|რა ღირს)/u',
        '/\b(price|cost|pricing|how much)\b/i',
        '/(მოდელ|მოდელები|ვერსია)/u',
        '/\b(model|models|version)\b/i',
        '/(მარაგ|საწყობ|დარჩენილი|ხელმისაწვდომ)/u',
        '/\b(stock|available|availability)\b/i',
        '/(მიწოდ|მიტან|კურიერ|ჩამოტანა|დრო)/u',
        '/\b(delivery|shipping|courier|arrive)\b/i',
        '/(გარანტი|დაბრუნებ|შეცვლა)/u',
        '/\b(warranty|return|exchange)\b/i',
        '/(gps|sim|ზარი|კამერა|ბატარეა)/u',
    ];

    private const GREETING_ONLY_PATTERNS = [
        '/^\s*(hi|hello|hey)\s*[!.?]*\s*$/i',
        '/^\s*(გამარჯობა|სალამი|ჰაი)\s*[!.?]*\s*$/u',
        '/^\s*(gamarjoba|salami)\s*[!.?]*\s*$/i',
    ];

    /**
     * Build the full master system prompt from config sections.
     *
     * @param  string[]  $sections  Config keys from chatbot-prompt.php to include
     */
    private function buildMasterPrompt(array $sections): string
    {
        $parts = [];

        foreach ($sections as $key) {
            $value = config("chatbot-prompt.{$key}");

            if (is_string($value) && trim($value) !== '') {
                $parts[] = trim($value);
            }
        }

        return implode("\n\n", $parts);
    }

    /**
     * Master system prompt for the website widget chatbot.
     * Includes all persona, linguistic, RAG integration, and formatting rules.
     */
    public function websiteSystemPrompt(): string
    {
        return $this->buildMasterPrompt([
            'identity',
            'language_core',
            'linguistic_rules',
            'price_formatting',
            'rag_integration',
            'output_format',
            'ecommerce_rules',
            'guardrails',
            'greetings',
        ]);
    }

    /**
     * System prompt for omnichannel (WhatsApp / Instagram / Messenger) suggestions.
     * Uses the same master prompt with an additional conciseness constraint.
     */
    public function omnichannelSystemPrompt(): string
    {
        $master = $this->buildMasterPrompt([
            'identity',
            'language_core',
            'linguistic_rules',
            'price_formatting',
            'rag_integration',
            'output_format',
            'ecommerce_rules',
            'guardrails',
            'greetings',
        ]);

        $omnichannelOverride = implode("\n", [
            '',
            '## Omnichannel-სპეციფიკური წესები',
            '- თითოეული პასუხი მოკლე იყოს (1-3 წინადადება მაქსიმუმ)',
            '- WhatsApp/Instagram-ზე Markdown მინიმალურად გამოიყენე (*bold* მხოლოდ)',
            '- არ გაიმეორო წინა ასისტენტის შეტყობინებები სიტყვა-სიტყვით',
            '- წინა კონტექსტი გაითვალისწინე — ნუ იკითხავ იგივეს ხელახლა',
            '- უპასუხე კონკრეტულად მომხმარებლის ბოლო შეტყობინებას',
        ]);

        return $master . $omnichannelOverride;
    }

    public function normalizeIncomingMessage(string $text): string
    {
        $normalized = trim($text);

        if ($normalized === '') {
            return '';
        }

        $map = self::TRANSLITERATION_MAP;
        uksort($map, fn (string $a, string $b) => strlen($b) <=> strlen($a));

        foreach ($map as $latin => $georgian) {
            $pattern = '/\b' . preg_quote($latin, '/') . '\b/i';
            $normalized = preg_replace($pattern, $georgian, $normalized) ?? $normalized;
        }

        foreach (self::TYPO_REPLACEMENTS as $pattern => $replacement) {
            $normalized = preg_replace($pattern, $replacement, $normalized) ?? $normalized;
        }

        return preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
    }

    public function isGreetingOnly(string $text): bool
    {
        $normalized = trim($this->normalizeIncomingMessage($text));

        if ($normalized === '') {
            return false;
        }

        foreach (self::GREETING_ONLY_PATTERNS as $pattern) {
            if (preg_match($pattern, $normalized) === 1) {
                return true;
            }
        }

        return false;
    }

    public function websiteGreetingReply(): string
    {
        return 'გამარჯობა! სიამოვნებით დაგეხმარებით. მითხარით რა გაინტერესებთ: ფასი, მარაგი, GPS, SOS თუ კონკრეტული მოდელი.';
    }

    public function looksGeorgianOrTransliterated(string $text): bool
    {
        if (preg_match('/\p{Georgian}/u', $text) === 1) {
            return true;
        }

        $lowered = mb_strtolower($text);

        foreach (array_keys(self::TRANSLITERATION_MAP) as $token) {
            if (str_contains($lowered, $token)) {
                return true;
            }
        }

        return false;
    }

    public function passesStrictGeorgianQa(string $response): bool
    {
        $trimmed = trim($response);

        if ($trimmed === '' || mb_strlen($trimmed) < 12) {
            return false;
        }

        if (preg_match('/\p{Georgian}/u', $trimmed) !== 1) {
            return false;
        }

        $englishHeavy = preg_match('/\b(hello|hi|assist|how can i|please share|thanks for reaching out)\b/i', $trimmed) === 1;
        if ($englishHeavy) {
            return false;
        }

        return true;
    }

    public function strictGeorgianFallback(): string
    {
        return 'მადლობა შეტყობინებისთვის. სიამოვნებით დაგეხმარებით — მითხარით რომელი მოდელი ან ფუნქცია გაინტერესებთ (GPS, SOS, ზარები, კამერა) და ზუსტ ინფორმაციას მოგაწვდით.';
    }

    public function shouldAutoReplySelectively(string $messageText, bool $hasAttachments = false): bool
    {
        $raw = trim($messageText);
        if ($raw === '') {
            return false;
        }

        foreach (self::GREETING_ONLY_PATTERNS as $pattern) {
            if (preg_match($pattern, $raw) === 1) {
                return false;
            }
        }

        $normalized = $this->normalizeIncomingMessage($raw);

        if ($hasAttachments && mb_strlen($normalized) < 20) {
            return false;
        }

        foreach (self::INTENT_PATTERNS as $pattern) {
            if (preg_match($pattern, $normalized) === 1) {
                return true;
            }
        }

        return str_contains($normalized, '?') && mb_strlen($normalized) >= 18;
    }
}
