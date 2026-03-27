<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
        });

        $this->backfillTable('users');

        Schema::table('story_projects', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
        });

        $this->backfillTable('story_projects');

        Schema::table('story_pages', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
        });

        $this->backfillTable('story_pages');

        Schema::table('story_ai_jobs', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
        });

        $this->backfillTable('story_ai_jobs');
    }

    public function down(): void
    {
        Schema::table('story_ai_jobs', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });

        Schema::table('story_pages', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });

        Schema::table('story_projects', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }

    private function backfillTable(string $table): void
    {
        foreach (DB::table($table)->whereNull('uuid')->cursor() as $row) {
            DB::table($table)->where('id', $row->id)->update(['uuid' => (string) Str::uuid()]);
        }
    }
};
