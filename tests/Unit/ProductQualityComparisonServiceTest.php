<?php

namespace Tests\Unit;

use App\Models\ProductQualityAnalysis;
use App\Services\Chatbot\ModelCompletionService;
use App\Services\ProductQuality\ProductQualityComparisonService;
use Mockery;
use Tests\TestCase;

class ProductQualityComparisonServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testComparisonFallsBackToStoredPayloadRankingWhenAiReplyIsUnavailable(): void
    {
        $modelCompletion = Mockery::mock(ModelCompletionService::class);
        $modelCompletion->shouldReceive('complete')
            ->once()
            ->andReturn([
                'reply' => '',
                'reason' => 'chatbot_disabled',
                'usage' => [],
            ]);

        $service = new ProductQualityComparisonService($modelCompletion);

        $winner = new ProductQualityAnalysis([
            'research_target_id' => 11,
            'status' => 'completed',
            'verdict' => 'strong_buy',
            'confidence_score' => 72,
            'comparison_ready_payload' => [
                'display_name' => 'Wonlex KT20',
                'evidence_count' => 6,
                'confidence_score' => 72,
                'weaknesses' => ['App setup still looks noisy.'],
                'risk_flags' => ['Supplier feedback is still present in the mix.'],
                'evidence_gaps' => [],
                'rubric' => [
                    'reliability' => 4.1,
                    'battery' => 3.8,
                    'gps' => 4.0,
                    'app' => 2.9,
                ],
            ],
        ]);

        $runnerUp = new ProductQualityAnalysis([
            'research_target_id' => 22,
            'status' => 'completed',
            'verdict' => 'conditional_buy',
            'confidence_score' => 54,
            'comparison_ready_payload' => [
                'display_name' => 'YQT Q12',
                'evidence_count' => 4,
                'confidence_score' => 54,
                'risk_flags' => ['GPS accuracy repeats as a concern.'],
                'evidence_gaps' => ['Coverage is still thin.'],
                'rubric' => [
                    'reliability' => 3.0,
                    'battery' => 3.1,
                    'gps' => 2.4,
                    'app' => 3.2,
                ],
            ],
        ]);

        $result = $service->compare([$winner, $runnerUp]);

        $this->assertSame(11, $result['winner_target_id']);
        $this->assertSame('firm', $result['firmness']);
        $this->assertNotEmpty($result['key_differences']);
        $this->assertStringContainsString('Wonlex KT20', $result['comparison_summary']);
    }
}
