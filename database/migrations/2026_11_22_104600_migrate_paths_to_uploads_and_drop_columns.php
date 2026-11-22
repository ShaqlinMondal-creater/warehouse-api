<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Legacy single cover → gallery image #1
        if (Schema::hasColumn('work_projects', 'cover_path')) {
            $projects = DB::table('work_projects')
                ->whereNotNull('cover_path')
                ->where('cover_path', '!=', '')
                ->get(['id', 'user_id', 'cover_path']);

            foreach ($projects as $project) {
                $exists = DB::table('uploads')
                    ->where('uploadable_type', 'work_project')
                    ->where('uploadable_id', $project->id)
                    ->where('collection', 'gallery')
                    ->where('path', $project->cover_path)
                    ->exists();

                if (! $exists) {
                    DB::table('uploads')->insert([
                        'user_id' => $project->user_id,
                        'disk' => 'public',
                        'path' => $project->cover_path,
                        'original_name' => null,
                        'mime' => null,
                        'size' => null,
                        'collection' => 'gallery',
                        'uploadable_type' => 'work_project',
                        'uploadable_id' => $project->id,
                        'sort_order' => 1,
                        'meta' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            Schema::table('work_projects', function (Blueprint $table) {
                $table->dropColumn('cover_path');
            });
        }

        // Drop unused leftover column from early local experiments (never kept)
        if (Schema::hasColumn('portfolio_profiles', 'avatar_path')) {
            Schema::table('portfolio_profiles', function (Blueprint $table) {
                $table->dropColumn('avatar_path');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('work_projects', 'cover_path')) {
            Schema::table('work_projects', function (Blueprint $table) {
                $table->string('cover_path')->nullable()->after('repo_url');
            });
        }
    }
};
