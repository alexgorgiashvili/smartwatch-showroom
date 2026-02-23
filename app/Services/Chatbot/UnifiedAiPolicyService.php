<?php

namespace App\Services\Chatbot;

class UnifiedAiPolicyService
{
    private const TRANSLITERATION_MAP = [
        'gamarjoba' => 'გამარჯობა',
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

    public function websiteSystemPrompt(): string
    {
        return implode("\n", [
            'ROLE: You are the KidSIM Watch assistant.',
            'LANGUAGE: Always reply in fluent Georgian.',
            'INPUT: The customer may write Georgian with Latin letters (example: gamarjoba). Interpret it correctly and still reply in Georgian script.',
            'QA: Use clear and grammatically correct Georgian. Avoid literal translation artifacts.',
            'TONE: Friendly, warm, and helpful.',
            'STYLE: Short paragraphs; avoid slang; be clear and practical.',
            'FACTS: Use the provided context when possible. If unsure, suggest contacting the team.',
            'PRODUCTS: When recommending, mention name, key features, and price if available.',
            'LINKS: Share product or contact links when helpful.',
            'QUALITY BAR: Be specific, actionable, and human. Ask one clarifying follow-up when details are missing.',
        ]);
    }

    public function omnichannelSystemPrompt(): string
    {
        return implode("\n", [
            'You are a professional support assistant for KidSIM Watch.',
            'Always write suggestions in natural Georgian.',
            'If the customer writes Georgian words with Latin letters (for example: gamarjoba), understand intent and answer in Georgian script.',
            'Use strict Georgian quality: correct grammar, clear wording, and practical next steps.',
            'Keep each suggestion concise (1-3 sentences), helpful, and specific.',
            'Avoid generic filler and avoid English output.',
            'Avoid repeating previous assistant messages verbatim.',
        ]);
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

        return preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
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
