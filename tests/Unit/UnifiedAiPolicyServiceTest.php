<?php

namespace Tests\Unit;

use App\Services\Chatbot\UnifiedAiPolicyService;
use Tests\TestCase;

class UnifiedAiPolicyServiceTest extends TestCase
{
    public function testNormalizeIncomingMessageConvertsCommonTransliteration(): void
    {
        $policy = new UnifiedAiPolicyService();

        $normalized = $policy->normalizeIncomingMessage('gamarjoba, ra ghirs es modeli?');

        $this->assertStringContainsString('გამარჯობა', $normalized);
        $this->assertStringContainsString('რა ღირს', $normalized);
        $this->assertStringContainsString('მოდელი', $normalized);
    }

    public function testLooksGeorgianOrTransliteratedDetectsLatinGeorgian(): void
    {
        $policy = new UnifiedAiPolicyService();

        $this->assertTrue($policy->looksGeorgianOrTransliterated('gamarjoba'));
        $this->assertTrue($policy->looksGeorgianOrTransliterated('გამარჯობა'));
        $this->assertFalse($policy->looksGeorgianOrTransliterated('hello, can you help me with smartwatch specs?'));
    }
}
