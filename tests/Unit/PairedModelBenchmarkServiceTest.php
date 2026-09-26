<?php

namespace Tests\Unit;

use App\Services\Chatbot\PairedModelBenchmarkService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PairedModelBenchmarkServiceTest extends TestCase
{
    private array $prices = [
        'baseline' => ['input' => 0.4, 'cached_input' => 0.1, 'cache_write' => 0.4, 'output' => 1.6],
        'candidate' => ['input' => 0.1, 'cached_input' => 0.01, 'cache_write' => 0.125, 'output' => 0.5],
    ];

    public function testRecordedUsageAndLatencyAreCountedWithoutClaimingQualityWhenJudgeIsMissing(): void
    {
        $service = new PairedModelBenchmarkService();
        $result = $service->run(
            [['id' => 'case-1', 'category' => 'warranty']],
            'baseline',
            'candidate',
            $this->prices,
            fn (array $case, string $model, array $limits): array => [
                'response' => 'დადასტურებული პასუხი',
                'usage' => ['prompt_tokens' => 1000, 'completion_tokens' => 100, 'prompt_tokens_details' => ['cached_tokens' => 400]],
                'latency_ms' => $model === 'baseline' ? 900 : 600,
            ]
        );

        $this->assertSame(1, $result['completed_pairs']);
        $this->assertSame(0, $result['graded_pairs']);
        $this->assertSame('insufficient_evidence', $result['decision']);
        $this->assertSame(900, $result['models']['baseline']['p95_latency_ms']);
        $this->assertSame(600, $result['models']['candidate']['p95_latency_ms']);
        $this->assertEqualsWithDelta(0.00044, $result['models']['baseline']['cost_usd'], 0.00000001);
        $this->assertEqualsWithDelta(0.000114, $result['models']['candidate']['cost_usd'], 0.00000001);
        $this->assertArrayNotHasKey('response', $result['cases'][0]['baseline']);
    }

    public function testHundredFullyJudgedPairsAreCheckedAgainstEveryReleaseGate(): void
    {
        $cases = array_map(fn (int $id): array => ['id' => 'case-' . $id], range(1, 100));
        $result = (new PairedModelBenchmarkService())->run(
            $cases,
            'baseline',
            'candidate',
            $this->prices,
            fn (array $case, string $model, array $limits): array => [
                'response' => 'შემოწმებული',
                'usage' => ['input_tokens' => 500, 'output_tokens' => 50],
                'latency_ms' => $model === 'baseline' ? 500 : 400,
            ],
            function (array $case, array $baseline, array $candidate): array {
                $id = (int) substr($case['id'], 5);
                return [
                    'baseline' => [
                        'quality_score' => 4,
                        'grounded' => $id > 20,
                        'georgian_ok' => true,
                        'critical_unverified_claim' => false,
                        'fallback_ok' => true,
                    ],
                    'candidate' => [
                        'quality_score' => 4.5,
                        'grounded' => $id > 10,
                        'georgian_ok' => true,
                        'critical_unverified_claim' => false,
                        'fallback_ok' => true,
                    ],
                ];
            }
        );

        $this->assertSame('candidate_meets_gates', $result['decision']);
        $this->assertSame(100, $result['graded_pairs']);
        $this->assertSame(100, $result['release_graded_pairs']);
        $this->assertTrue($result['budget_verified']);
        $this->assertSame(4.5, $result['models']['candidate']['mean_quality_score']);
        $this->assertSame(10.0, $result['acceptance']['quality_delta_pp']);
    }

    public function testBudgetReservationStopsBeforeStartingAnUnpairedCase(): void
    {
        $calls = 0;
        $result = (new PairedModelBenchmarkService())->run(
            [['id' => 'case-1'], ['id' => 'case-2']],
            'baseline',
            'candidate',
            $this->prices,
            function () use (&$calls): array {
                $calls++;
                return ['response' => 'კი', 'usage' => ['input_tokens' => 10, 'output_tokens' => 10], 'latency_ms' => 1];
            },
            null,
            ['budget_usd' => 0.00005, 'max_input_tokens' => 20, 'max_completion_tokens' => 10]
        );

        $this->assertSame(2, $calls);
        $this->assertSame(1, $result['completed_pairs']);
        $this->assertSame('budget_reservation_exhausted', $result['stopped_reason']);
        $this->assertSame('insufficient_evidence', $result['decision']);
    }

    public function testOneUnsupportedCriticalClaimRejectsCandidateEvenWithBetterCostAndSpeed(): void
    {
        $cases = array_map(fn (int $id): array => ['id' => 'case-' . $id], range(1, 100));
        $result = (new PairedModelBenchmarkService())->run(
            $cases,
            'baseline',
            'candidate',
            $this->prices,
            fn (array $case, string $model, array $limits): array => [
                'response' => 'პასუხი',
                'usage' => ['input_tokens' => 500, 'output_tokens' => 50],
                'latency_ms' => $model === 'baseline' ? 500 : 400,
            ],
            function (array $case): array {
                $id = (int) substr($case['id'], 5);
                $grade = static fn (bool $grounded, bool $critical): array => [
                    'quality_score' => 4,
                    'grounded' => $grounded,
                    'georgian_ok' => true,
                    'critical_unverified_claim' => $critical,
                    'fallback_ok' => true,
                ];
                return [
                    'baseline' => $grade($id > 20, false),
                    'candidate' => $grade($id > 10, $id === 100),
                ];
            }
        );

        $this->assertSame('candidate_rejected', $result['decision']);
        $this->assertFalse($result['acceptance']['no_candidate_critical_claims']);
        $this->assertSame(1, $result['models']['candidate']['critical_unverified_claim_count']);
    }

    public function testUnknownUsageStopsFurtherCallsAndNeverVerifiesBudget(): void
    {
        $calls = 0;
        $result = (new PairedModelBenchmarkService())->run(
            [['id' => 'case-1'], ['id' => 'case-2']],
            'baseline',
            'candidate',
            $this->prices,
            function () use (&$calls): array {
                $calls++;
                return ['response' => 'კი', 'usage' => [], 'latency_ms' => 1];
            }
        );

        $this->assertSame(1, $calls);
        $this->assertFalse($result['budget_verified']);
        $this->assertNull($result['total_cost_usd']);
        $this->assertSame('usage_unavailable', $result['stopped_reason']);
    }

    public function testCallerCannotRaiseHardThreeDollarCap(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new PairedModelBenchmarkService())->run([], 'baseline', 'candidate', $this->prices, fn (): array => [], null, ['budget_usd' => 3.01]);
    }
}
