<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Remove duplicate URLs, keeping the earliest (smallest id) record
        // MySQL-specific DELETE JOIN
        DB::statement('DELETE p1 FROM pages p1 JOIN pages p2 ON p1.url = p2.url AND p1.id > p2.id');

        Schema::table('pages', function (Blueprint $table) {
            $table->unique('url');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropUnique('pages_url_unique');
        });
    }
};
