<?php

namespace Tests\Unit;

use App\Services\Chatbot\EmbeddingService;
use App\Services\Chatbot\IntentResult;
use App\Services\Chatbot\MultiLayerCacheService;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class MultiLayerCacheScopeTest extends TestCase
{
    public function testWidgetModelAndKnowledgeChangesCannotReuseSocialResponseCache(): void
    {
        $previous = Container::getInstance();
        $container = new Container();
        $container->instance('config', new Repository([
            'chatbot' => ['supervisor' => ['model' => 'gpt-4.1-mini']],
            'chatbot-prompt' => ['system' => 'version-one'],
        ]));
        Container::setInstance($container);

        try {
            $service = new MultiLayerCacheService(new EmbeddingService());
            $method = new ReflectionMethod($service, 'hashQuery');
            $intent = IntentResult::fallback('მიტანა');
            $legacy = $method->invoke($service, 'მიტანა', $intent, []);
            $this->assertSame(md5('v5|მიტანა|general||'), $legacy);

            $widget = ['channel' => 'widget', 'model' => 'gpt-4.1-mini', 'knowledge_version' => 'one'];
            $candidate = ['channel' => 'widget', 'model' => 'gpt-6-luna', 'knowledge_version' => 'one'];
            $knowledgeChange = ['channel' => 'widget', 'model' => 'gpt-6-luna', 'knowledge_version' => 'two'];
            $this->assertNotSame($legacy, $method->invoke($service, 'მიტანა', $intent, $widget));
            $this->assertNotSame(
                $method->invoke($service, 'მიტანა', $intent, $widget),
                $method->invoke($service, 'მიტანა', $intent, $candidate)
            );
            $this->assertNotSame(
                $method->invoke($service, 'მიტანა', $intent, $candidate),
                $method->invoke($service, 'მიტანა', $intent, $knowledgeChange)
            );
        } finally {
            Container::setInstance($previous);
        }
    }
}
