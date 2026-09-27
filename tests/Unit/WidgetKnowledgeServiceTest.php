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

        $this->assertStringContainsString('მოდელის გაცვლა', $context);
        $this->assertStringContainsString('თანხის დაბრუნების პირობა ამ წყაროთი არ დასტურდება', $context);
    }

    public function test_money_back_wording_retrieves_exchange_distinction(): void
    {
        $context = $this->service()->contextFor('თუ არ მომეწონა, თანხას დამიბრუნებთ?');

        $this->assertStringContainsString('მოდელის გაცვლა', $context);
        $this->assertStringContainsString('თანხის დაბრუნება და მოდელის გაცვლა სხვადასხვა საკითხია', $context);
    }

    public function test_take_back_wording_retrieves_conditional_exchange_policy(): void
    {
        $context = $this->service()->contextFor('თუ შეიძლება, დაიბრუნეთ და სხვას ვუყიდი');

        $this->assertStringContainsString('მოდელის გაცვლა მიღებიდან 14 კალენდარული დღის განმავლობაში', $context);
        $this->assertStringContainsString('თანხის დაბრუნების პირობა ამ წყაროთი არ დასტურდება', $context);
    }

    public function test_courier_and_arrival_question_includes_payment_and_delivery_terms(): void
    {
        $context = $this->service()->contextFor('დიახ კურიერთან და როდის ჩამოდის ამანათი?');

        $this->assertStringContainsString('მიწოდება უფასოა', $context);
        $this->assertStringContainsString('რეგიონულ ქალაქებში 1–3 სამუშაო დღე', $context);
        $this->assertStringContainsString('კურიერთან ნაღდი ანგარიშსწორება', $context);
    }

    private function service(): WidgetKnowledgeService
    {
        return new WidgetKnowledgeService(__DIR__ . '/../../database/data/chatbot_widget_knowledge.json');
    }
}
