<?php

namespace Tests\Unit;

use App\Services\Chatbot\WidgetModelSelector;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

class WidgetModelSelectorTest extends TestCase
{
    private ?Container $previousContainer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $container = new Container();
        $container->instance('config', new Repository([
            'chatbot' => [
                'supervisor' => ['model' => 'gpt-4.1-mini'],
                'widget_v2' => ['model' => 'gpt-6-luna', 'rollout_percent' => 0],
            ],
        ]));
        Container::setInstance($container);
    }

    protected function tearDown(): void
    {
        Container::setInstance($this->previousContainer);
        parent::tearDown();
    }

    public function testOnlyWebsiteWidgetCanEnterCandidateCohort(): void
    {
        $selector = new WidgetModelSelector();
        $this->assertSame('gpt-4.1-mini', $selector->select(42, 'widget')['model']);

        config()->set('chatbot.widget_v2.rollout_percent', 100);
        $this->assertSame('gpt-6-luna', $selector->select(42, 'widget')['model']);
        $this->assertSame('v2', $selector->select(42, 'widget')['cohort']);
        $this->assertSame('gpt-4.1-mini', $selector->select(42, 'omnichannel')['model']);
        $this->assertSame('gpt-4.1-mini', $selector->select(42, 'instagram')['model']);
    }
}
