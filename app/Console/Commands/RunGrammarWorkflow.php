<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RunGrammarWorkflow extends Command
{
    protected $signature = 'chatbot:grammar-workflow
                            {--no-cache : Bypass cache when collecting responses}
                            {--force : Skip confirmation prompts}';

    protected $description = 'Run complete grammar testing workflow: collect, analyze, and update';

    public function handle(): int
    {
        $this->info('🚀 Starting Complete Grammar Workflow');
        $this->info('=====================================');
        $this->newLine();

        // Step 1: Collect Responses
        $this->info('📝 Step 1/3: Collecting chatbot responses...');
        $this->newLine();

        $collectOptions = [];
        if ($this->option('no-cache')) {
            $collectOptions['--no-cache'] = true;
        }

        $collectResult = $this->call('chatbot:collect-responses', $collectOptions);

        if ($collectResult !== self::SUCCESS) {
            $this->error('❌ Failed to collect responses. Workflow aborted.');
            return self::FAILURE;
        }

        $this->newLine(2);

        // Step 2: Display for Cascade Analysis
        $this->info('🔍 Step 2/3: Displaying responses for Cascade analysis...');
        $this->newLine();

        $analyzeResult = $this->call('chatbot:analyze-grammar');

        if ($analyzeResult !== self::SUCCESS) {
            $this->error('❌ Failed to analyze grammar. Workflow aborted.');
            return self::FAILURE;
        }

        $this->newLine(2);

        // Step 3: Update Prompt
        $this->info('📝 Step 3/3: Updating prompt...');
        $this->newLine();

        $updateOptions = [];
        if ($this->option('force')) {
            $updateOptions['--force'] = true;
        }

        $updateResult = $this->call('chatbot:update-prompt', $updateOptions);

        if ($updateResult !== self::SUCCESS) {
            $this->warn('⚠️  Prompt update was cancelled or failed.');
            return self::FAILURE;
        }

        $this->newLine(2);
        $this->info('🎉 Grammar Workflow Completed Successfully!');
        $this->info('===========================================');
        $this->newLine();

        $this->table(
            ['Step', 'Status'],
            [
                ['1. Collect Responses', '✅ Success'],
                ['2. Analyze Grammar', '✅ Success'],
                ['3. Update Prompt', '✅ Success'],
            ]
        );

        $this->newLine();
        $this->info('💡 Next steps:');
        $this->line('  - Test the chatbot with the same questions to verify improvements');
        $this->line('  - Review the updated prompt in config/chatbot-prompt.php');
        $this->line('  - Check backups in config/ directory if you need to revert');

        return self::SUCCESS;
    }
}
