<?php

namespace App\Services\Chatbot;

final class WidgetKnowledgeService
{
    public function __construct(private ?string $sourcePath = null)
    {
        $this->sourcePath ??= database_path('data/chatbot_widget_knowledge.json');
    }

    public function version(): string
    {
        if (!is_file($this->sourcePath)) {
            return 'missing';
        }

        return substr(hash_file('sha256', $this->sourcePath) ?: 'missing', 0, 16);
    }

    public function contextFor(string $question, ?IntentResult $intent = null): string
    {
        $query = $this->normalize($question . ' ' . ($intent?->standaloneQuery() ?? ''));
        if ($query === '') {
            return '';
        }

        $matches = [];
        foreach ($this->entries() as $entry) {
            if (($entry['status'] ?? '') !== 'verified') {
                continue;
            }

            $answer = trim((string) ($entry['answer_ka'] ?? ''));
            $source = trim((string) ($entry['source_ref'] ?? ''));
            if ($answer === '' || $source === '') {
                continue;
            }

            $score = 0;
            foreach (($entry['triggers'] ?? []) as $trigger) {
                if (!is_string($trigger)) {
                    continue;
                }

                $normalizedTrigger = $this->normalize($trigger);
                if (mb_strlen($normalizedTrigger) >= 4 && str_contains($query, $normalizedTrigger)) {
                    $score++;
                }
            }

            if ($score > 0) {
                $matches[] = ['score' => $score, 'answer' => $answer];
            }
        }

        usort($matches, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        $answers = array_map(
            static fn (array $match): string => $match['answer'],
            array_slice($matches, 0, 2)
        );

        return mb_substr(implode("\n", $answers), 0, 1000);
    }

    /** @return list<array<string, mixed>> */
    private function entries(): array
    {
        if (!is_file($this->sourcePath)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($this->sourcePath), true);
        $entries = is_array($decoded) ? ($decoded['entries'] ?? []) : [];

        return is_array($entries) ? array_values(array_filter($entries, 'is_array')) : [];
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower($value);
        $value = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
}
