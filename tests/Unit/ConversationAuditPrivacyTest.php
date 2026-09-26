<?php

namespace Tests\Unit;

use App\Services\Chatbot\ConversationAudit\ConversationAuditBuilder;
use App\Services\Chatbot\ConversationAudit\ConversationSignalClassifier;
use PHPUnit\Framework\TestCase;

class ConversationAuditPrivacyTest extends TestCase
{
    public function testFrozenCasesContainNoRawQuestionContactOrPlatformId(): void
    {
        $builder = new ConversationAuditBuilder('test-only-local-hmac-key', new ConversationSignalClassifier());
        for ($index = 1; $index <= 120; $index++) {
            $question = $index % 2 === 0
                ? 'რა ღირს? პირადი ფრაზა alice@example.invalid'
                : 'მიტანა როდისაა? პირადი ფრაზა alice@example.invalid';
            $builder->ingest('facebook', 'customer', $question, 'platform-secret-' . $index, '2026-09-01', 'meta_graph');
        }

        $result = $builder->finish(['facebook_graph' => ['conversations' => 120]], 120);
        $json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $this->assertCount(120, $result['scenarios']);
        $this->assertStringNotContainsString('alice@example.invalid', $json);
        $this->assertStringNotContainsString('platform-secret-', $json);
        $this->assertStringNotContainsString('პირადი ფრაზა', $json);
        $this->assertSame(120, $result['report']['messages_with_detectable_pii']);
        foreach ($result['scenarios'] as $scenario) {
            $this->assertDoesNotMatchRegularExpression('/ი-(?:ის|ს|ით|ზე|იდან)/u', $scenario['question']);
            $this->assertDoesNotMatchRegularExpression('/რა ფასი აქვს [^?]*ი\?/u', $scenario['question']);
            $this->assertDoesNotMatchRegularExpression('/საათს რა ფასი აქვს/u', $scenario['question']);
        }
    }
}
