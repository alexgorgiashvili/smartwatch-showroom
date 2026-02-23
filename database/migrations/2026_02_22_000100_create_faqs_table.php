<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question', 255);
            $table->text('answer');
            $table->string('category', 120)->default('სხვა');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'category']);
            $table->index('sort_order');
        });

        if (Schema::hasTable('chatbot_documents')) {
            $faqDocuments = DB::table('chatbot_documents')
                ->where('type', 'faq')
                ->orderBy('id')
                ->get();

            foreach ($faqDocuments as $index => $document) {
                $metadata = json_decode((string) ($document->metadata ?? '{}'), true) ?: [];

                DB::table('faqs')->insert([
                    'question' => $document->title,
                    'answer' => $document->content_ka,
                    'category' => $metadata['category'] ?? 'სხვა',
                    'sort_order' => $index,
                    'is_active' => (bool) $document->is_active,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
