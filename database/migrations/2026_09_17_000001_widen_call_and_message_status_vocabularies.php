<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two status columns, one defect: the delivery outcomes providers send are
 * wider than the vocabulary the columns declare.
 *
 *  - `sms_messages.status` had no `undelivered`. Twilio documents it, and
 *    sends it for a message an operator rejected or could not deliver. The
 *    delivery-receipt handler wrote the provider's value straight in, so
 *    the write was refused (MySQL in strict mode) or silently stored as
 *    the empty string (MySQL without it) — see the down() note below and
 *    the task report for what each of those did to a customer. That is a
 *    live defect on Twilio installations today, not something Plivo
 *    introduces; Plivo reaches the same column with `undelivered` and
 *    `rejected`. This column genuinely gains a member.
 *
 *  - `calls.status` gains nothing. Its eight members already cover every
 *    value either provider can produce once the normalisers have mapped
 *    them, and its narrowness is a safety property worth keeping:
 *    ProcessCampaignJob counts a call as active only when it is
 *    `initiated`, `ringing` or `in-progress`, while
 *    ResetTelephonyProviderAction::BLOCKING_CALL_STATUSES also counts
 *    `queued`. Those two lists disagree, and the column is what keeps the
 *    disagreement inert — a value no row can hold cannot produce a call
 *    that is invisible to campaign concurrency yet still blocks the
 *    destructive provider reset. The column is restated here with exactly
 *    the members it already had, and only where the grammar has lost them
 *    (see up()).
 *
 * The widened column is widened, not opened. Application code maps every
 * provider value into a declared member and logs anything it cannot map
 * (SmsWebhookController::mapMessageStatus(),
 * PlivoWebhookNormalizer::mapCallStatus()), so nothing arbitrary reaches
 * either column; `undelivered` is added because it is a real outcome
 * nothing else could stand in for.
 *
 * `undelivered` is APPENDED to the end of the existing list, and the
 * existing members keep their order and spelling. That is what lets MySQL
 * and MariaDB apply it in place, without rebuilding the table:
 * `sms_messages` grows without bound on a busy installation, and this runs
 * during an upgrade on shared hosting where a full table copy would be an
 * outage. Nothing is renamed, reordered or removed, so no stored value
 * changes meaning and no row is rewritten.
 *
 * Sibling migrations (2025_10_29_172947 for campaign_contacts.status,
 * 2026_09_16_000008 for phone_numbers.source) solved the same class of
 * problem by widening the column to a plain string. That is the better
 * shape for a small table; it is the wrong one here, because ENUM to
 * VARCHAR changes the stored representation and therefore forces exactly
 * the full rebuild this migration is written to avoid.
 */
return new class extends Migration
{
    /** The members `calls.status` already has, and keeps. */
    private const CALL_STATUSES = [
        'initiated', 'ringing', 'in-progress', 'completed',
        'busy', 'no-answer', 'failed', 'canceled',
    ];

    /** The members `sms_messages.status` ends up with, in column order. */
    private const MESSAGE_STATUSES = [
        'queued', 'sent', 'delivered', 'failed', 'received', 'undelivered',
    ];

    public function up(): void
    {
        $this->setEnum('sms_messages', 'status', self::MESSAGE_STATUSES, 'queued');

        // `calls.status` is restated only where the grammar has lost the
        // constraint, which in practice means SQLite. SQLite has no ALTER
        // for a column, so Laravel rebuilds the whole table from what
        // PRAGMA table_info can see — and a CHECK constraint is not
        // something it can see. Every later migration that added a column
        // with a foreign key to this table therefore dropped the enum's
        // enforcement silently, and by the end of the migration set the
        // column was accepting anything. Restating it puts the constraint
        // back.
        //
        // On MySQL and MariaDB the enum is intact and already enforcing,
        // so there is nothing to fix and the statement is skipped
        // outright. Issuing an ALTER that changes no member on hundreds of
        // live `calls` tables would be pure risk for no change at all.
        if (! $this->isMysql()) {
            $this->setEnum('calls', 'status', self::CALL_STATUSES, 'initiated');
        }
    }

    /**
     * Narrowing a column cannot silently discard the rows that made the
     * widening worth doing, so anything holding the newly-legal member is
     * first moved to the closest member the original column had. An
     * undelivered message becomes a failed one — the outcome the customer
     * was owed either way, and the one that column could express before.
     * No row is deleted.
     *
     * MIND THE COST: unlike up(), this is NOT an in-place alter on MySQL
     * or MariaDB. Removing a member is not an append, so the engine
     * rebuilds `sms_messages` — a full table copy, which on a large table
     * on shared hosting is the outage up() exists to avoid. A buyer who
     * hits trouble mid-upgrade and reaches for `migrate:rollback` should
     * know that before running it, and should prefer rolling forward.
     *
     * `calls` is not touched at all: up() changed no member there, so
     * there is nothing to reverse. On the grammars where up() restated the
     * column, the restored CHECK constraint is deliberately left in place
     * — it holds exactly the vocabulary the column was always declared
     * with, and "no enforcement at all" is not a state anyone wants to
     * roll back to.
     */
    public function down(): void
    {
        if (! Schema::hasTable('sms_messages')) {
            return;
        }

        DB::table('sms_messages')->where('status', 'undelivered')->update(['status' => 'failed']);

        $this->setEnum(
            'sms_messages',
            'status',
            array_values(array_diff(self::MESSAGE_STATUSES, ['undelivered'])),
            'queued'
        );
    }

    /**
     * MySQL and MariaDB get the ALTER directly: the schema builder's
     * change() would work, but it re-states the whole column from its own
     * introspection, and only a literal MODIFY guarantees the member order
     * that keeps the operation in place. No ALGORITHM/LOCK clause is
     * named — an append is in-place on every MySQL 5.7+ and MariaDB 10.x a
     * buyer might be on, and naming an algorithm one of them does not
     * support would fail the upgrade outright rather than run slower.
     *
     * Everything else (SQLite in tests, Postgres if a buyer has one) goes
     * through the schema builder, which knows each grammar's own way of
     * expressing a closed set — a CHECK constraint on SQLite.
     *
     * @param  list<string>  $members
     */
    private function setEnum(string $table, string $column, array $members, string $default): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        if ($this->isMysql()) {
            $list = implode(', ', array_map(
                static fn (string $member): string => "'".str_replace("'", "''", $member)."'",
                $members
            ));

            DB::statement(
                "ALTER TABLE `{$table}` MODIFY `{$column}` ENUM({$list}) NOT NULL DEFAULT '{$default}'"
            );

            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $members, $default): void {
            $blueprint->enum($column, $members)->default($default)->change();
        });
    }

    private function isMysql(): bool
    {
        return in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
};
