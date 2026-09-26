<?php

namespace Tests\Unit;

use App\Services\Chatbot\WidgetTraceLogger;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class WidgetTracePrivacyTest extends TestCase
{
    public function testDefaultOperationalTraceDropsMessagesAndIdentifiers(): void
    {
        $method = new ReflectionMethod(WidgetTraceLogger::class, 'safeContext');
        $safe = $method->invoke(new WidgetTraceLogger(), [
            'trace_id' => 'widget_123abc',
            'response_model' => 'gpt-6-luna',
            'model_cohort' => 'v2',
            'response_time_ms' => 430,
            'incoming_message' => 'პირადი შეტყობინება',
            'model_reply' => 'პირადი პასუხი',
            'customer_id' => 123,
            'ip' => '192.0.2.1',
            'session_id' => 'session-private',
            'selected_products' => [['name' => 'private']],
            'reason' => 'customer phone 599001122',
        ]);

        $this->assertSame([
            'trace_id' => 'widget_123abc',
            'response_model' => 'gpt-6-luna',
            'model_cohort' => 'v2',
            'response_time_ms' => 430,
        ], $safe);
    }
}
