<?php

namespace Tests\Unit;

use App\Services\Chatbot\AdaptiveLearningService;
use App\Services\Chatbot\ConditionalReflectionService;
use App\Services\Chatbot\IntentResult;
use App\Services\Chatbot\ModelCompletionService;
use App\Services\Chatbot\PromptBuilderService;
use App\Services\Chatbot\ResponseValidatorService;
use App\Services\Chatbot\SearchContext;
use App\Services\Chatbot\UnifiedAiPolicyService;
use Mockery;
use Tests\TestCase;

class ChatbotWarrantyPolicyTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testWarrantyQuestionsIncludeExplicitPolicyContext(): void
    {
        $builder = new PromptBuilderService(
            new UnifiedAiPolicyService(),
            new AdaptiveLearningService()
        );

        $intent = IntentResult::fallback('გარანტია თუგაქვთ და რამდენხნიანი?');

        $context = $builder->buildUserContext(
            'გარანტია თუგაქვთ და რამდენხნიანი?',
            $intent,
            new SearchContext('', collect(), null, null),
            [],
            collect(),
            ''
        );

        $this->assertStringContainsString('გარანტიის პოლიტიკა:', $context);
        $this->assertStringContainsString('2G მოდელებზე მოქმედებს 3 თვიანი გარანტია.', $context);
        $this->assertStringContainsString('4G მოდელებზე მოქმედებს 6 თვიანი გარანტია.', $context);
    }

    public function testWarrantyClaimsOutsidePolicyAreRejected(): void
    {
        $validator = new ResponseValidatorService();

        $result = $validator->validateAll(
            'ოფიციალური გარანტია 12 თვეა.',
            [
                'products' => [
                    [
                        'name' => 'Q21 2G',
                        'slug' => 'q21-2g',
                        'is_in_stock' => true,
                        'url' => 'http://127.0.0.1:8000/products/q21-2g',
                    ],
                ],
            ],
            IntentResult::fallback('გარანტია რამდენია?')
        );

        $this->assertFalse($result->isValid());
        $this->assertSame('warranty_mismatch', $result->violations()[0]['type'] ?? null);
        $this->assertSame(12, $result->violations()[0]['months'] ?? null);
    }

    public function testValidWarrantyClaimMatchesModelTypePolicy(): void
    {
        $validator = new ResponseValidatorService();

        $result = $validator->validateAll(
            'Q21 — 2G მოდელზე 3 თვიანი გარანტიაა.',
            [
                'products' => [
                    [
                        'name' => 'Q21 2G',
                        'slug' => 'q21-2g',
                        'is_in_stock' => true,
                        'url' => 'http://127.0.0.1:8000/products/q21-2g',
                    ],
                ],
            ],
            IntentResult::fallback('გარანტია რამდენია?')
        );

        $this->assertTrue($result->isValid());
    }

    public function testWarrantyClaimsTriggerReflection(): void
    {
        $reflection = new ConditionalReflectionService(
            new ResponseValidatorService(),
            Mockery::mock(ModelCompletionService::class),
            Mockery::mock(PromptBuilderService::class),
            new UnifiedAiPolicyService()
        );

        $this->assertTrue(
            $reflection->shouldReflect(
                'ოფიციალური გარანტია 12 თვეა.',
                0.95,
                IntentResult::fallback('გარანტია რამდენია?')
            )
        );
    }
}
