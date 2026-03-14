<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AnalyzeChatbotGrammar extends Command
{
    protected $signature = 'chatbot:analyze-grammar 
                            {file? : Specific response file to analyze (defaults to latest)}';

    protected $description = 'Display chatbot responses for manual grammar analysis by Cascade';

    public function handle(): int
    {
        $this->info('🔍 Preparing responses for Cascade analysis...');
        $this->newLine();

        // Find response file
        $file = $this->argument('file');
        
        if (!$file) {
            $responsesDir = storage_path('chatbot-grammar-tests/responses');
            $files = File::files($responsesDir);
            
            if (empty($files)) {
                $this->error('❌ No response files found. Run chatbot:collect-responses first.');
                return self::FAILURE;
            }

            usort($files, fn($a, $b) => $b->getMTime() <=> $a->getMTime());
            $file = $files[0]->getPathname();
            $this->info('📁 Using latest response file: ' . basename($file));
        } else {
            if (!File::exists($file)) {
                $file = storage_path('chatbot-grammar-tests/responses/' . $file);
            }
            
            if (!File::exists($file)) {
                $this->error('❌ Response file not found: ' . $file);
                return self::FAILURE;
            }
        }

        $responseData = json_decode(File::get($file), true);
        $responses = $responseData['responses'] ?? [];

        if (empty($responses)) {
            $this->error('❌ No responses found in file');
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('📊 Displaying ' . count($responses) . ' responses for Cascade analysis');
        $this->line('════════════════════════════════════════════════════════════════════════════');
        $this->newLine();

        // Display all responses
        foreach ($responses as $i => $response) {
            $this->line('<fg=cyan>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</>');
            $this->line('<fg=yellow>კითხვა #' . $response['question_id'] . '</>: <fg=white>' . $response['question'] . '</>');
            $this->line('<fg=gray>კატეგორია: ' . $response['category'] . ' | Intent: ' . ($response['intent'] ?? 'N/A') . '</>');
            
            if (isset($response['cache_hit'])) {
                $cacheStatus = $response['cache_hit'] ? '<fg=green>✅ Cache Hit</>' : '<fg=red>❌ Cache Miss</>';
                $this->line('<fg=gray>' . $cacheStatus . ' | Duration: ' . ($response['duration_ms'] ?? 'N/A') . 'ms</>');
            }
            
            $this->newLine();
            $this->line('<fg=magenta>📝 პასუხი:</>');
            $this->line('<fg=white>' . $response['response'] . '</>');
            $this->newLine();
        }

        $this->line('<fg=cyan>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</>');
        $this->newLine();
        
        $this->info('✅ All responses displayed!');
        $this->info('📁 Source file: ' . basename($file));
        $this->newLine();
        $this->line('<fg=yellow>💡 Cascade will now analyze these responses for Georgian grammar errors.</>');
        $this->line('<fg=yellow>   Focus areas: ბრუნვები, ნაცილობელები, ზმნის ფორმები, კალკირება</>');
        
        return self::SUCCESS;
    }
}
