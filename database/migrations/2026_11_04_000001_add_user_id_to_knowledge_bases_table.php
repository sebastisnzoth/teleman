<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Knowledge bases had no owner, so every customer could list, read, edit and
 * delete every other customer's. A knowledge base with no owner is visible
 * to admins only.
 *
 * Existing ones are given to the owner of the first AI agent using them
 * (archived agents count). One no agent uses has no owner to give it to, and
 * stays with the admin. Nothing is deleted. Safe to run again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('knowledge_bases', 'user_id')) {
            Schema::table('knowledge_bases', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            });
        }

        foreach (DB::table('knowledge_bases')->whereNull('user_id')->pluck('id') as $knowledgeBaseId) {
            $ownerId = DB::table('ai_agents')
                ->where('knowledge_base_id', $knowledgeBaseId)
                ->whereNotNull('user_id')
                ->orderBy('id')
                ->value('user_id');

            if ($ownerId !== null) {
                DB::table('knowledge_bases')->where('id', $knowledgeBaseId)->update(['user_id' => $ownerId]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('knowledge_bases', 'user_id')) {
            Schema::table('knowledge_bases', function (Blueprint $table) {
                $table->dropConstrainedForeignId('user_id');
            });
        }
    }
};
