<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('generated_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
            $table->string('layout', 32)->index(); // e.g., interstitial, advertorial
            $table->enum('status', ['success', 'error'])->default('success')->index();
            $table->longText('content')->nullable();
            $table->text('error')->nullable();
            $table->string('ai_model', 100)->nullable();
            $table->unsignedInteger('tokens_input')->nullable();
            $table->unsignedInteger('tokens_output')->nullable();
            $table->float('temperature', 3, 2)->nullable();
            $table->string('provider', 50)->nullable(); // e.g., openai
            $table->string('prompt_version', 50)->nullable();
            $table->timestamps();

            $table->index(['page_id', 'layout']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generated_contents');
    }
};
