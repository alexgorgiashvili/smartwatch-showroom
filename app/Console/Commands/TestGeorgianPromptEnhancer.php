<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AI\GeorgianPromptEnhancerService;

class TestGeorgianPromptEnhancer extends Command
{
    protected $signature = 'ai:test-georgian-enhancer';
    protected $description = 'Runs a suite of testing protocols to verify the Georgian prompt enhancement system';

    public function handle(GeorgianPromptEnhancerService $enhancer)
    {
        $this->info('Starting Georgian Linguistic Quality Tests...');

        $testCases = [
            [
                'name' => 'Barbarisms and Slang',
                'prompt' => 'გთხოვთ დამიწერო პოსტი სმარტ საათზე, ძაან მაგარი რო იყოს და იაფია პროსტა, ვაბშე არ ჭედავს.',
                'tone' => 'professional'
            ],
            [
                'name' => 'Grammar (Pluralization after Numbers)',
                'prompt' => 'გვაქვს 5 ახალი მოდელები და მინდა რო კლიენტებმა იყიდოს.',
                'tone' => 'casual'
            ],
            [
                'name' => 'Grammar (Ergative Case)',
                'prompt' => 'გუშინ კლიენტი იყიდა საათი და მინდა მადლობის პოსტი დავწერო.',
                'tone' => 'professional'
            ]
        ];

        $totalScore = 0;

        foreach ($testCases as $case) {
            $this->warn("\n--- Testing: {$case['name']} ---");
            $this->line("Original: " . $case['prompt']);
            $this->line("Target Tone: " . $case['tone']);

            $start = microtime(true);
            $result = $enhancer->enhancePrompt($case['prompt'], $case['tone']);
            $time = round(microtime(true) - $start, 2);

            if (!$result['success']) {
                $this->error("Failed to enhance prompt: " . $result['error']);
                continue;
            }

            $this->info("Enhanced ({$time}s): " . $result['enhanced_prompt']);
            
            $this->line("Detected Issues:");
            foreach ($result['metadata']['detected_issues'] as $issue) {
                $this->line("  - " . $issue);
            }
            
            $this->line("Corrections Made:");
            foreach ($result['metadata']['grammar_corrections'] as $corr) {
                $this->line("  - " . $corr);
            }
            
            $this->line("Cultural Notes: " . $result['metadata']['cultural_notes']);

            $totalScore++;
        }

        $this->info("\nTest Suite Completed. Success Rate: " . $totalScore . "/" . count($testCases));
        return 0;
    }
}

