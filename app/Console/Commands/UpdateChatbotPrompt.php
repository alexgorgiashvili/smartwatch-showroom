<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class UpdateChatbotPrompt extends Command
{
    protected $signature = 'chatbot:update-prompt 
                            {analysis-file? : Analysis file to use (defaults to latest)}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Update chatbot prompt with grammar corrections from analysis';

    public function handle(): int
    {
        $this->info('📝 Starting prompt update process...');
        $this->newLine();

        // Find analysis file
        $file = $this->argument('analysis-file');
        
        if (!$file) {
            $analysisDir = storage_path('chatbot-grammar-tests/analysis');
            $files = File::files($analysisDir);
            
            if (empty($files)) {
                $this->error('❌ No analysis files found. Run chatbot:analyze-grammar first.');
                return self::FAILURE;
            }

            usort($files, fn($a, $b) => $b->getMTime() <=> $a->getMTime());
            $file = $files[0]->getPathname();
            $this->info('📁 Using latest analysis file: ' . basename($file));
        } else {
            if (!File::exists($file)) {
                $file = storage_path('chatbot-grammar-tests/analysis/' . $file);
            }
            
            if (!File::exists($file)) {
                $this->error('❌ Analysis file not found: ' . $file);
                return self::FAILURE;
            }
        }

        $analysisData = json_decode(File::get($file), true);

        if (empty($analysisData['findings'])) {
            $this->info('✅ No grammar errors found in analysis. Nothing to update.');
            return self::SUCCESS;
        }

        $this->info('📊 Found ' . count($analysisData['findings']) . ' grammar issues to address');
        $this->newLine();

        // Display findings
        $this->displayFindings($analysisData['findings']);
        $this->newLine();

        // Show suggested prompt addition
        if (!empty($analysisData['suggested_prompt_section'])) {
            $this->info('📝 Suggested Prompt Addition:');
            $this->line('----------------------------------------');
            $this->line($analysisData['suggested_prompt_section']);
            $this->line('----------------------------------------');
            $this->newLine();
        }

        // Ask for confirmation
        if (!$this->option('force')) {
            if (!$this->confirm('Do you want to add these corrections to the chatbot prompt?', true)) {
                $this->warn('⚠️  Update cancelled by user');
                return self::SUCCESS;
            }
        }

        // Backup current prompt
        $promptPath = config_path('chatbot-prompt.php');
        $backupPath = config_path('chatbot-prompt.backup.' . now()->format('Y-m-d_H-i-s') . '.php');
        
        File::copy($promptPath, $backupPath);
        $this->info('💾 Backup created: ' . basename($backupPath));

        // Update prompt
        try {
            $this->updatePromptFile($promptPath, $analysisData);
            
            $this->newLine();
            $this->info('✅ Prompt updated successfully!');
            $this->info('🔄 Running config:cache...');
            
            $this->call('config:cache');
            
            $this->newLine();
            $this->info('🎉 All done! Grammar rules have been added to the chatbot prompt.');
            
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Failed to update prompt: ' . $e->getMessage());
            $this->warn('💾 Restoring from backup...');
            File::copy($backupPath, $promptPath);
            return self::FAILURE;
        }
    }

    private function displayFindings(array $findings): void
    {
        $tableData = [];
        foreach ($findings as $i => $finding) {
            $tableData[] = [
                '#' . ($i + 1),
                $finding['incorrect'] ?? 'N/A',
                $finding['correct'] ?? 'N/A',
                $finding['error_type'] ?? 'N/A',
            ];
        }

        $this->table(
            ['#', 'Incorrect', 'Correct', 'Error Type'],
            $tableData
        );
    }

    private function updatePromptFile(string $promptPath, array $analysisData): void
    {
        $content = File::get($promptPath);

        // Check if section 3.6 already exists
        if (str_contains($content, '### 3.6 — ხშირი გრამატიკული შეცდომები')) {
            // Section exists, append to it
            $this->appendToExistingSection($promptPath, $content, $analysisData);
        } else {
            // Section doesn't exist, create it
            $this->createNewSection($promptPath, $content, $analysisData);
        }
    }

    private function createNewSection(string $promptPath, string $content, array $analysisData): void
    {
        $newSection = $this->buildNewSection($analysisData);

        // Find the end of section 3.5 (თავაზიანობა)
        $pattern = '/(- \*\*არ გამოიყენო\*\* „ბატონო\/ქალბატონო" — ეს ზედმეტად ფორმალურია ჩატისთვის\nPROMPT,)/';
        
        if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            $insertPosition = $matches[1][1] + strlen($matches[1][0]);
            
            $before = substr($content, 0, $insertPosition);
            $after = substr($content, $insertPosition);
            
            $newContent = $before . "\n\n" . $newSection . $after;
            
            File::put($promptPath, $newContent);
            $this->info('✅ Created new section 3.6 in prompt');
        } else {
            throw new \Exception('Could not find insertion point in prompt file');
        }
    }

    private function appendToExistingSection(string $promptPath, string $content, array $analysisData): void
    {
        // Find the table in section 3.6
        $pattern = '/(### 3\.6 — ხშირი გრამატიკული შეცდომები.*?\n\|.*?\|.*?\|.*?\|\n\|---\|---\|---\|)(.*?)(\n\n\*\*წესი)/s';
        
        if (preg_match($pattern, $content, $matches)) {
            $tableHeader = $matches[1];
            $existingRows = $matches[2];
            $afterTable = $matches[3];
            
            // Build new rows
            $newRows = '';
            foreach ($analysisData['findings'] as $finding) {
                if (!empty($finding['prompt_rule'])) {
                    $newRows .= "\n" . $finding['prompt_rule'];
                }
            }
            
            // Replace the section
            $newTableSection = $tableHeader . $existingRows . $newRows . $afterTable;
            
            $newContent = preg_replace($pattern, $newTableSection, $content);
            
            File::put($promptPath, $newContent);
            $this->info('✅ Appended new rules to existing section 3.6');
        } else {
            throw new \Exception('Could not find section 3.6 table structure');
        }
    }

    private function buildNewSection(array $analysisData): string
    {
        $tableRows = '';
        foreach ($analysisData['findings'] as $finding) {
            if (!empty($finding['prompt_rule'])) {
                $tableRows .= "\n" . $finding['prompt_rule'];
            }
        }

        return <<<SECTION
    /*
    |--------------------------------------------------------------------------
    | 3.6 — ხშირი გრამატიკული შეცდომები (აკრძალული!)
    |--------------------------------------------------------------------------
    */
    'grammar_corrections' => <<<'PROMPT'
### 3.6 — ხშირი გრამატიკული შეცდომები (აკრძალული!)

| ❌ არასწორი | ✅ სწორი | მიზეზი |
|---|---|---|{$tableRows}

**წესი:** ყურადღებით გამოიყენე ქართული ბრუნვები და ნაცილობელები.
- **„ეს + სახელობითი"** როცა სუბიექტია (ეს მოდელი, ეს საათი)
- **„ამ + ნათესაობითი"** როცა ატრიბუტია (ამ მოდელის ფასი, ამ საათის ფუნქციები)

**მაგალითები:**
✅ „ეს მოდელი ოდნავ აღემატება თქვენს ბიუჯეტს"
❌ „ამ მოდელი ოდნავ აღემატება თქვენს ბიუჯეტს"
✅ „ამ მოდელის ფასი 200 ლარია"
✅ „ეს საათი GPS-ითაა აღჭურვილი"
❌ „ამ საათი GPS-ითაა აღჭურვილი"
PROMPT,
SECTION;
    }
}
