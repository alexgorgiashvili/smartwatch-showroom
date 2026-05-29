<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AiPostGeneratorService;

class TestGeorgianContentGeneration extends Command
{
    protected $signature = 'ai:test-georgian-content';
    protected $description = 'Tests the dual-mode Georgian AI Content Generation and Quality Control';

    public function handle(AiPostGeneratorService $generator)
    {
        $this->info('Starting Georgian Content Generation & QC Tests...');

        $testCases = [
            [
                'name' => 'Custom Mode - Specific Instructions',
                'mode' => 'custom',
                'tone' => 'casual',
                'description' => 'დაწერე რამე ახალ Apple Watch-ზე, მაგრად რო ყიდის. გამოიყენე შენობითი ფორმა.'
            ],
            [
                'name' => 'Autonomous Mode - General Topic',
                'mode' => 'autonomous',
                'tone' => 'professional', // Should be ignored by autonomous mode
                'description' => 'სპორტული სმარტ საათი წყალგამძლეობით'
            ]
        ];

        foreach ($testCases as $case) {
            $this->warn("\n--- Testing: {$case['name']} ---");
            $this->line("Mode: {$case['mode']}");
            $this->line("Description: {$case['description']}");

            $start = microtime(true);
            $result = $generator->generateThreeVariants(null, 'ka', $case['description'], $case['mode'], $case['tone']);
            $time = round(microtime(true) - $start, 2);

            if (!$result['success']) {
                $this->error("Failed: " . $result['error']);
                continue;
            }

            $this->info("Generated in {$time}s");
            
            foreach ($result['variants'] as $key => $text) {
                $this->line("\n[Variant: {$key}]");
                $this->line($text);
            }
        }

        $this->info("\nContent Generation Tests Completed.");
        return 0;
    }
}
