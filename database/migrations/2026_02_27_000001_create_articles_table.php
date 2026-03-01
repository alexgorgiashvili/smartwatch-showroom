<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title_ka');
            $table->string('title_en')->nullable();
            $table->text('excerpt_ka')->nullable();
            $table->text('excerpt_en')->nullable();
            $table->longText('body_ka');
            $table->longText('body_en')->nullable();
            $table->string('meta_title_ka', 160)->nullable();
            $table->string('meta_title_en', 160)->nullable();
            $table->string('meta_description_ka', 160)->nullable();
            $table->string('meta_description_en', 160)->nullable();
            $table->string('cover_image')->nullable();
            $table->string('schema_type')->default('Article'); // Article | HowTo | ItemList
            $table->boolean('is_published')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
