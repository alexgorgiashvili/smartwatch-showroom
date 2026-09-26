<?php

namespace Tests\Unit;

use App\Services\Chatbot\WidgetKnowledgeService;
use PHPUnit\Framework\TestCase;

class WidgetKnowledgeServiceTest extends TestCase
{
    public function test_returns_only_relevant_verified_policy_context(): void
    {
        $service = $this->service();

        $delivery = $service->contextFor('მიტანა რამდენ ხანშია?');
        $this->assertStringContainsString('მიწოდება უფასოა', $delivery);
        $this->assertStringContainsString('1 სამუშაო დღე', $delivery);
        $this->assertStringNotContainsString('გარანტია 1 თვეა', $delivery);

        $this->assertSame('', $service->contextFor('Q21 რა ფერისაა?'));
        $this->assertSame('', $service->contextFor('რომელი SIM ბარათით მუშაობს?'));
        $this->assertSame('', $service->contextFor('აპლიკაციაში პაროლის შეცვლა როგორ შეიძლება?'));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{16}$/', $service->version());
    }

    public function test_exchange_context_does_not_promise_unconditional_returns(): void
    {
        $context = $this->service()->contextFor('საათი თუ არ მომეწონა, დავაბრუნებ?');

        $this->assertStringContainsString('მხოლოდ მოდელის გაცვლა', $context);
        $this->assertStringContainsString('დაუდასტურებელ პირობას ნუ დაჰპირდები', $context);
    }

    private function service(): WidgetKnowledgeService
    {
        return new WidgetKnowledgeService(__DIR__ . '/../../database/data/chatbot_widget_knowledge.json');
    }
}
