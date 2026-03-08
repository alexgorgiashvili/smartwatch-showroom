<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WidgetTraceLogger
{
    public function enabled(): bool
    {
        return (bool) config('chatbot-monitoring.widget_trace.enabled', false);
    }

    public function payloadsEnabled(): bool
    {
        return (bool) config('chatbot-monitoring.widget_trace.include_payloads', true);
    }

    public function newTraceId(): string
    {
        return 'widget_' . Str::lower(Str::random(12));
    }

    public function logStep(string $step, array $context = []): void
    {
        if (!$this->enabled()) {
            return;
        }

        Log::channel($this->channel())->info('chatbot.widget.trace', [
            'step' => $step,
            'context' => $this->normalize($context),
        ]);
    }

    private function channel(): string
    {
        $channel = trim((string) config('chatbot-monitoring.widget_trace.channel', 'chatbot_widget_trace'));

        return $channel !== '' ? $channel : 'chatbot_widget_trace';
    }

    private function normalize(mixed $value): mixed
    {
        if (is_string($value)) {
            $normalized = preg_replace('/\s+/u', ' ', trim($value));
            $normalized = $normalized ?? trim($value);
            $maxChars = max(120, (int) config('chatbot-monitoring.widget_trace.max_chars', 800));

            if (mb_strlen($normalized) <= $maxChars) {
                return $normalized;
            }

            return mb_substr($normalized, 0, $maxChars) . '... [truncated]';
        }

        if (is_array($value)) {
            $maxItems = max(3, (int) config('chatbot-monitoring.widget_trace.max_items', 8));
            $isList = array_is_list($value);
            $items = $value;
            $truncatedCount = 0;

            if ($isList && count($items) > $maxItems) {
                $truncatedCount = count($items) - $maxItems;
                $items = array_slice($items, 0, $maxItems);
            }

            $normalized = [];

            foreach ($items as $key => $item) {
                $normalized[$key] = $this->normalize($item);
            }

            if ($isList && $truncatedCount > 0) {
                $normalized['_truncated_items'] = $truncatedCount;
            }

            return $normalized;
        }

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return $this->normalize((string) $value);
            }

            return ['object_class' => $value::class];
        }

        return $value;
    }
}
