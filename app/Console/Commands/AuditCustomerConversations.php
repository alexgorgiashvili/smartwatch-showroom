<?php

namespace App\Console\Commands;

use App\Services\Chatbot\ConversationAudit\ConversationAuditBuilder;
use App\Services\Chatbot\ConversationAudit\ConversationSignalClassifier;
use App\Services\Chatbot\ConversationAudit\MetaConversationReadClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only customer conversation audit. The only writes are de-identified
 * aggregate/scenario JSON files chosen with --output-dir.
 */
class AuditCustomerConversations extends Command
{
    protected $signature = 'chatbot:audit-conversations
        {--meta : Read historical Messenger/Instagram metadata and messages with configured GET-only Meta credentials}
        {--output-dir= : Directory for safe aggregate report and frozen synthetic scenarios}
        {--max-conversations=500 : Maximum conversations per Meta channel}
        {--max-messages=200 : Maximum messages to inspect per Meta conversation}
        {--max-meta-requests=1200 : Hard cap on total Meta GET requests}
        {--target-cases=120 : Maximum frozen synthetic scenarios}';

    protected $description = 'Audit existing conversations without DB writes, paid AI calls, or raw transcript output.';

    public function handle(): int
    {
        $key = (string) config('app.key', '');
        if ($key === '') {
            $this->error('APP_KEY is required for non-reversible source hashes.');
            return self::FAILURE;
        }

        $maxConversations = max(1, min(10000, (int) $this->option('max-conversations')));
        $maxMessages = max(1, min(5000, (int) $this->option('max-messages')));
        $maxRequests = max(1, min(50000, (int) $this->option('max-meta-requests')));
        $targetCases = max(1, min(1000, (int) $this->option('target-cases')));
        $builder = new ConversationAuditBuilder($key, new ConversationSignalClassifier());
        $coverage = ['local' => $this->readLocalMessages($builder)];
        $coverage['verified_webhook_echoes'] = $this->readVerifiedWebhookEchoes($builder);

        if ((bool) $this->option('meta')) {
            $client = new MetaConversationReadClient($maxRequests);
            $pageId = trim((string) config('services.facebook.page_id', ''));
            $pageToken = trim((string) config('services.facebook.page_access_token', ''));
            $igId = trim((string) config('services.facebook.instagram_account_id', ''));
            $igToken = trim((string) config('services.facebook.instagram_access_token', ''));

            $this->line('Reading Messenger history via GET requests. No message content will be printed.');
            $coverage['facebook_graph'] = $client->fetchMessenger(
                $builder, $pageId, $pageToken, $maxConversations, $maxMessages
            );
            $this->line('Checking Instagram history via GET requests.');
            $coverage['instagram_graph'] = $client->fetchInstagram(
                $builder, $pageId, $pageToken, $igId, $igToken, $maxConversations, $maxMessages
            );
            $coverage['whatsapp_graph'] = $client->probeWhatsApp(
                trim((string) config('services.whatsapp.phone_number_id', '')),
                trim((string) config('services.whatsapp.access_token', ''))
            );
            $coverage['meta_get_requests'] = $client->requestsMade();
        } else {
            $coverage['facebook_graph'] = ['status' => 'not_requested'];
            $coverage['instagram_graph'] = ['status' => 'not_requested'];
            $coverage['whatsapp_graph'] = ['status' => 'not_requested'];
        }

        $result = $builder->finish($coverage, $targetCases);
        $outputDir = trim((string) $this->option('output-dir'));
        if ($outputDir === '') {
            $outputDir = database_path('data');
        }
        if (!is_dir($outputDir) && !mkdir($outputDir, 0700, true) && !is_dir($outputDir)) {
            $this->error('Could not create the output directory.');
            return self::FAILURE;
        }

        $reportPath = rtrim($outputDir, '\\/') . DIRECTORY_SEPARATOR . 'chatbot_conversation_audit.json';
        $datasetPath = rtrim($outputDir, '\\/') . DIRECTORY_SEPARATOR . 'chatbot_conversation_scenarios.json';
        $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;
        file_put_contents($reportPath, json_encode($result['report'], $flags) . PHP_EOL);
        file_put_contents($datasetPath, json_encode($result['scenarios'], $flags) . PHP_EOL);

        $this->info('Aggregate audit saved. No raw messages or platform identifiers were written.');
        $this->line('Local messages: ' . ($coverage['local']['messages_seen'] ?? 0));
        $this->line('Messenger Graph conversations: ' . ($coverage['facebook_graph']['conversations'] ?? 0));
        $this->line('Frozen scenarios: ' . count($result['scenarios']));
        $this->line('Report: ' . $reportPath);
        $this->line('Dataset: ' . $datasetPath);

        return self::SUCCESS;
    }

    private function readLocalMessages(ConversationAuditBuilder $builder): array
    {
        if (!Schema::hasTable('messages') || !Schema::hasTable('conversations')) {
            return ['status' => 'tables_missing', 'messages_seen' => 0];
        }

        $count = 0;
        try {
            $rows = DB::table('messages as m')
                ->join('conversations as c', 'c.id', '=', 'm.conversation_id')
                ->select('m.id as local_message_id', 'm.platform_message_id', 'm.sender_type',
                    'm.content', 'm.created_at', 'c.platform')
                ->orderBy('m.id')
                ->cursor();
            foreach ($rows as $row) {
                $channel = strtolower((string) $row->platform);
                if ($channel === 'messenger') {
                    $channel = 'facebook';
                }
                $sourceKey = trim((string) ($row->platform_message_id ?? ''));
                if ($sourceKey === '') {
                    $sourceKey = 'local:' . (string) $row->local_message_id;
                }
                $builder->ingest(
                    $channel,
                    (string) $row->sender_type,
                    (string) $row->content,
                    $sourceKey,
                    (string) $row->created_at,
                    'local_messages'
                );
                $count++;
            }
        } catch (\Throwable $exception) {
            return ['status' => 'read_failed', 'exception_class' => $exception::class, 'messages_seen' => $count];
        }

        return ['status' => 'complete', 'messages_seen' => $count];
    }

    private function readVerifiedWebhookEchoes(ConversationAuditBuilder $builder): array
    {
        if (!Schema::hasTable('webhook_logs')) {
            return ['status' => 'table_missing', 'echoes_seen' => 0];
        }

        $count = 0;
        try {
            foreach (DB::table('webhook_logs')
                ->where('verified', true)
                ->select('platform', 'payload', 'created_at')
                ->orderBy('id')
                ->cursor() as $row) {
                $payload = json_decode((string) $row->payload, true);
                if (!is_array($payload)) {
                    continue;
                }
                foreach ((array) ($payload['entry'] ?? []) as $entry) {
                    foreach ((array) ($entry['messaging'] ?? []) as $event) {
                        $message = $event['message'] ?? null;
                        if (!is_array($message) || ($message['is_echo'] ?? false) !== true) {
                            continue;
                        }
                        $builder->ingest(
                            (string) $row->platform,
                            'page',
                            (string) ($message['text'] ?? ''),
                            (string) ($message['mid'] ?? ''),
                            (string) $row->created_at,
                            'verified_webhook_echo'
                        );
                        $count++;
                    }
                }
            }
        } catch (\Throwable $exception) {
            return ['status' => 'read_failed', 'exception_class' => $exception::class, 'echoes_seen' => $count];
        }

        return ['status' => 'complete', 'echoes_seen' => $count];
    }
}
