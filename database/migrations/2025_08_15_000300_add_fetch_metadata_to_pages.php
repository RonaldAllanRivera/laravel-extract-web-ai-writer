<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dateTime('last_fetched_at')->nullable()->after('meta');
            $table->integer('http_status')->nullable()->after('last_fetched_at');
            $table->integer('content_length')->nullable()->after('http_status');
            $table->text('fetch_error')->nullable()->after('content_length');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn(['last_fetched_at', 'http_status', 'content_length', 'fetch_error']);
        });
    }
};
