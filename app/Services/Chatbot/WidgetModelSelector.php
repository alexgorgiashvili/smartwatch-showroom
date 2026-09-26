<?php

namespace App\Services\Chatbot;

class WidgetModelSelector
{
    /**
     * @return array{channel: string, cohort: string, model: string}
     */
    public function select(int $conversationId, string $channel = 'omnichannel'): array
    {
        $baseline = (string) config('chatbot.supervisor.model', 'gpt-4.1-mini');

        if ($channel !== 'widget') {
            return ['channel' => 'omnichannel', 'cohort' => 'baseline', 'model' => $baseline];
        }

        $percent = max(0, min(100, (int) config('chatbot.widget_v2.rollout_percent', 0)));
        $bucket = hexdec(substr(hash('sha256', 'chatbot-widget-v2:' . $conversationId), 0, 8)) % 100;
        $v2 = $conversationId > 0 && $bucket < $percent;

        return [
            'channel' => 'widget',
            'cohort' => $v2 ? 'v2' : 'baseline',
            'model' => $v2
                ? (string) config('chatbot.widget_v2.model', 'gpt-6-luna')
                : $baseline,
        ];
    }
}
