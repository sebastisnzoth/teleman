<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Telephony\ResetTelephonyProviderAction;
use App\Exceptions\Telephony\TelephonyResetRefusedException;
use App\Models\TelephonyProviderReset;
use App\Services\Telephony\TelephonyLock;
use Illuminate\Console\Command;

/**
 * Console access to the destructive telephony provider reset, for support
 * staff working on an installation whose admin cannot reach the UI.
 *
 * Runs the same Action, so the preconditions, the order of operations and
 * the audit row are identical to the admin page's. The only differences
 * are that the audit row's actor is null (a console session has no
 * authenticated user) and that --force replaces the interactive prompt.
 */
final class ResetTelephonyCommand extends Command
{
    protected $signature = 'telephony:reset
                            {--provider= : The provider currently locked, typed to confirm}
                            {--force : Skip the interactive confirmation}
                            {--end-work-in-flight : Hang up calls and stop campaigns that would otherwise refuse the reset}';

    protected $description = 'Reset this installation\'s telephony provider, clearing every provider\'s credentials and trunks and retiring its phone numbers';

    public function handle(TelephonyLock $lock, ResetTelephonyProviderAction $reset): int
    {
        $current = $lock->current();

        if ($current === null) {
            $this->error('This installation is not locked to a telephony provider, so there is nothing to reset.');

            return self::FAILURE;
        }

        $confirmation = (string) ($this->option('provider') ?? '');

        if (! $this->option('force')) {
            $this->warn("This will wipe the telephony configuration for [{$current->value}].");
            // Says the same thing the admin page's danger zone says, in
            // the same terms, because this prompt is the only warning a
            // support operator gets: credentials go for EVERY provider,
            // not only the locked one (an installation can hold saved
            // credentials for the provider it is not locked to), and
            // phone numbers are retired in place rather than deleted —
            // the rows stay, because customer history points at them.
            $this->line("Credentials for every telephony provider — not only {$current->label()} — are cleared, along with SIP and BYOC trunks, softphone endpoints and call queue entries.");
            $this->line('Phone numbers are NOT deleted: they are unassigned and retired in this installation, and campaigns and AI agents lose the number they called from.');
            $this->line('Number requests still awaiting a decision are cancelled.');
            $this->line('Users, credits, contacts, campaigns, call history and SMS history are kept.');
            $this->line("Nothing is released at {$current->label()} — that account keeps its numbers and keeps billing for them.");

            if (! $this->confirm('Continue?', false)) {
                $this->info('Aborted. Nothing was changed.');

                // Declining the prompt is the operator doing the right
                // thing, not a failure. Returning FAILURE here would make a
                // wrapper script unable to tell "the human said no" from
                // "a precondition refused the reset", which are opposite
                // situations.
                return self::SUCCESS;
            }
        }

        try {
            // No authenticated user on the console, so the audit row
            // records a null actor. It is never taken from an option:
            // "who ran this" must not be something the caller can assert.
            $record = $reset->execute(
                null,
                $confirmation,
                TelephonyProviderReset::SOURCE_CONSOLE,
                (bool) $this->option('end-work-in-flight'),
            );
        } catch (TelephonyResetRefusedException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Telephony reset complete. This installation is no longer locked to [{$current->value}].");

        $this->line('');
        $this->line('Deleted:');
        $this->table(['Table', 'Rows'], $this->rows($record->deletedCounts()));

        $this->line('Kept, but stripped of provider identity:');
        $this->table(['Table', 'Rows'], $this->rows($record->neutralisedCounts()));

        $retired = $record->retiredPhoneNumbers();

        if ($retired !== []) {
            $this->line('');
            $this->warn('These numbers are retired in this installation but are STILL LIVE and still billing at '.$current->label().'.');
            $this->warn('Cancel them in that account yourself — this reset never touched it.');
            $this->table(
                ['Phone number ID', 'Number'],
                array_map(fn (array $n): array => [$n['id'], $n['number']], $retired),
            );
        }

        $this->warn('No trunk or application was released at the provider either.');
        $this->warn('Queued jobs that referenced a detached number will fail; retry them once a new provider is configured.');
        $this->line("Audit row #{$record->id} records this reset.");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int>  $counts
     * @return list<array{0: string, 1: int}>
     */
    private function rows(array $counts): array
    {
        $rows = [];

        foreach ($counts as $table => $count) {
            $rows[] = [$table, $count];
        }

        return $rows;
    }
}
