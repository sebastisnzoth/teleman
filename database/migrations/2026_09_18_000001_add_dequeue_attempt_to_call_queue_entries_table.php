<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guarded both ways: on MySQL, adding a column that is already
        // there is error 1060, which aborts the whole migrate run
        // half-applied and blocks every later release behind it.
        if (Schema::hasColumn('call_queue_entries', 'dequeue_attempt')) {
            return;
        }

        Schema::table('call_queue_entries', function (Blueprint $table) {
            // How many times this caller has been handed to an agent.
            //
            // It is a claim token, not a statistic. Taking a caller out of
            // the queue is one conditional UPDATE that moves them from
            // waiting to connecting and bumps this number, so exactly one
            // of two agents pressing dequeue at the same moment is given
            // them — before, both were, and both were dialled into the
            // caller's one conference room.
            //
            // The number then travels with that agent's leg, so the
            // callback reporting the leg's end names the attempt it
            // belongs to. A late one — a retry of a leg that ended two
            // agents ago — names a number this caller has moved past and
            // changes nothing, instead of taking them away from the agent
            // who is ringing them now.
            //
            // Existing rows default to 0, which is what an entry that has
            // never been dequeued has always effectively been.
            $table->unsignedInteger('dequeue_attempt')->default(0)->after('position');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('call_queue_entries', 'dequeue_attempt')) {
            return;
        }

        Schema::table('call_queue_entries', function (Blueprint $table) {
            $table->dropColumn('dequeue_attempt');
        });
    }
};
