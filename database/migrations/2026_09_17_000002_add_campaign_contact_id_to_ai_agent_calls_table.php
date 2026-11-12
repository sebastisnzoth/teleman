<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Only if it is missing. The symptom of the bug this fixes is a
        // log line naming the column, so an installation may already have
        // been patched by hand — and on MySQL, adding a column that is
        // already there is error 1060, which aborts the whole migrate run
        // half-applied and blocks every later release behind it.
        if (Schema::hasColumn('ai_agent_calls', 'campaign_contact_id')) {
            return;
        }

        Schema::table('ai_agent_calls', function (Blueprint $table) {
            // The campaign contact this agent call was placed to.
            //
            // The campaign answer URL has always written this value, and
            // AiAgentCall has always listed it as fillable, but the column
            // itself was never created — so opening the call row threw on
            // every campaign call to an AI agent, on either provider, and
            // the caller got the apology that endpoint's catch produces
            // instead of a conversation.
            //
            // Nullable and set null on delete, matching the campaign_id
            // and contact_id columns beside it: a call is a record of
            // something that happened and outlives the contact row it was
            // placed to.
            $table->foreignId('campaign_contact_id')
                ->nullable()
                ->after('campaign_id')
                ->constrained()
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('ai_agent_calls', 'campaign_contact_id')) {
            return;
        }

        Schema::table('ai_agent_calls', function (Blueprint $table) {
            $table->dropConstrainedForeignId('campaign_contact_id');
        });
    }
};
