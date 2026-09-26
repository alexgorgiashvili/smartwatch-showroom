<?php

namespace Tests\Unit;

use App\Services\Chatbot\Agents\SupervisorAgent;
use App\Services\Chatbot\IntentResult;
use App\Services\Chatbot\WidgetKnowledgeService;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class SupervisorRuntimeIsolationTest extends TestCase
{
    public function testVerifiedKnowledgeAndCandidateModelStayInsideOptedInWidgetCohort(): void
    {
        $previous = Container::getInstance();
        $container = new Container();
        $container->instance('config', new Repository([
            'chatbot' => [
                'supervisor' => ['model' => 'gpt-4.1-mini'],
                'widget_v2' => ['rollout_percent' => 100, 'model' => 'gpt-6-luna'],
            ],
        ]));
        $container->instance(WidgetKnowledgeService::class, new WidgetKnowledgeService(
            __DIR__ . '/../../database/data/chatbot_widget_knowledge.json'
        ));
        Container::setInstance($container);

        try {
            $supervisor = (new ReflectionClass(SupervisorAgent::class))->newInstanceWithoutConstructor();
            $runtimeFor = new ReflectionMethod(SupervisorAgent::class, 'runtimeFor');
            $intent = IntentResult::fallback('მიტანა');

            $widget = $runtimeFor->invoke($supervisor, 42, 'მიტანა რამდენი ღირს?', $intent, ['channel' => 'widget']);
            $this->assertSame('v2', $widget['cohort']);
            $this->assertSame('gpt-6-luna', $widget['model']);
            $this->assertStringContainsString('მიწოდება უფასოა', $widget['knowledge_context']);

            $social = $runtimeFor->invoke($supervisor, 42, 'მიტანა რამდენი ღირს?', $intent, []);
            $this->assertSame('gpt-4.1-mini', $social['model']);
            $this->assertSame('', $social['knowledge_context']);

            config()->set('chatbot.widget_v2.rollout_percent', 0);
            $disabled = $runtimeFor->invoke($supervisor, 42, 'მიტანა რამდენი ღირს?', $intent, ['channel' => 'widget']);
            $this->assertSame('gpt-4.1-mini', $disabled['model']);
            $this->assertSame('', $disabled['knowledge_context']);
        } finally {
            Container::setInstance($previous);
        }
    }
}
