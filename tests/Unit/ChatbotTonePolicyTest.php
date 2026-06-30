<?php

namespace Tests\Unit;

use App\Services\Chatbot\Agents\GeneralAgent;
use App\Services\Chatbot\ChatbotFallbackStrategyService;
use App\Services\Chatbot\ConditionalReflectionService;
use App\Services\Chatbot\IntentResult;
use App\Services\Chatbot\ModelCompletionService;
use App\Services\Chatbot\ProductContextService;
use App\Services\Chatbot\PromptBuilderService;
use App\Services\Chatbot\ResponseValidatorService;
use App\Services\Chatbot\SearchContext;
use App\Services\Chatbot\UnifiedAiPolicyService;
use App\Services\Chatbot\WidgetTraceLogger;
use Mockery;
use Tests\TestCase;

class ChatbotTonePolicyTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testWebsiteSystemPromptUsesPreferredOfferFormulation(): void
    {
        $prompt = (new UnifiedAiPolicyService())->websiteSystemPrompt();

        $this->assertStringContainsString('### პროდუქტის შეთავაზების ტონი', $prompt);
        $this->assertStringContainsString('როცა მოდელებს, ვარიანტებს ან ალტერნატივებს სთავაზობ, გამოიყენე „შემოგთავაზოთ" და არა „გთავაზოთ".', $prompt);
    }

    public function testGeneralAgentModeInstructionUsesPreferredOfferFormulation(): void
    {
        $productContext = Mockery::mock(ProductContextService::class);
        $productContext->shouldReceive('selectForPrompt')->once()->andReturn(collect());
        $productContext->shouldReceive('buildValidationContext')->once()->andReturn([]);

        $promptBuilder = Mockery::mock(PromptBuilderService::class);
        $promptBuilder->shouldReceive('buildSystemPrompt')->once()->andReturn('BASE PROMPT');
        $promptBuilder->shouldReceive('buildUserContext')->once()->andReturn('USER CONTEXT');

        $capturedMessages = null;

        $modelCompletion = Mockery::mock(ModelCompletionService::class);
        $modelCompletion->shouldReceive('complete')
            ->once()
            ->withArgs(function (string $model, array $messages, array $options) use (&$capturedMessages): bool {
                $capturedMessages = $messages;

                return $model === config('chatbot.supervisor.model', 'gpt-4.1-mini')
                    && $options['max_tokens'] === 300;
            })
            ->andReturn([
                'reply' => 'ოკ',
                'reason' => null,
                'usage' => [],
            ]);

        $reflection = Mockery::mock(ConditionalReflectionService::class);
        $reflection->shouldReceive('shouldReflect')->once()->andReturn(false);

        $fallbackStrategy = Mockery::mock(ChatbotFallbackStrategyService::class);
        $widgetTrace = Mockery::mock(WidgetTraceLogger::class);
        $widgetTrace->shouldReceive('enabled')->andReturn(false);
        $widgetTrace->shouldReceive('payloadsEnabled')->andReturn(false);

        $agent = new GeneralAgent(
            $productContext,
            $promptBuilder,
            $modelCompletion,
            $reflection,
            $fallbackStrategy,
            $widgetTrace
        );

        $result = $agent->handle(
            'რომელი მოდელები გაქვთ?',
            123,
            IntentResult::fallback('რომელი მოდელები გაქვთ?'),
            new SearchContext('', collect(), null, null),
            collect(),
            [],
            []
        );

        $this->assertTrue($result['success']);
        $this->assertSame('ოკ', $result['response']);
        $this->assertNotNull($capturedMessages);

        $systemPrompt = $capturedMessages[0]['content'] ?? '';
        $this->assertStringContainsString('BASE PROMPT', $systemPrompt);
        $this->assertStringContainsString('როცა მოდელებს ან ვარიანტებს სთავაზობ, გამოიყენე „შემოგთავაზოთ" და არა „გთავაზოთ".', $systemPrompt);
    }

    public function testOfferToneMismatchTriggersValidationAndReflection(): void
    {
        $validator = new ResponseValidatorService();

        $invalidResult = $validator->validateAll('თუ გსურთ, შემიძლია რამდენიმე მოდელი გთავაზოთ ორივე ტიპიდან.', []);
        $this->assertFalse($invalidResult->isValid());
        $this->assertSame('offer_tone_mismatch', $invalidResult->violations()[0]['type'] ?? null);

        $validResult = $validator->validateAll('თუ გსურთ, შემიძლია რამდენიმე მოდელი შემოგთავაზოთ ორივე ტიპიდან.', []);
        $this->assertTrue($validResult->isValid());

        $reflection = new ConditionalReflectionService(
            new ResponseValidatorService(),
            Mockery::mock(ModelCompletionService::class),
            Mockery::mock(PromptBuilderService::class),
            new UnifiedAiPolicyService()
        );

        $this->assertTrue(
            $reflection->shouldReflect(
                'თუ გსურთ, შემიძლია რამდენიმე მოდელი გთავაზოთ ორივე ტიპიდან.',
                0.95,
                IntentResult::fallback('რომელი მოდელები გაქვთ?')
            )
        );
    }
}
