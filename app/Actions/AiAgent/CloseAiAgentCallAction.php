<?php

declare(strict_types=1);

namespace App\Actions\AiAgent;

use App\Models\AiAgentCall;
use App\Models\Call;

/**
 * Close the AiAgentCall row a finished Call owns.
 *
 * A campaign AI-agent call produces two rows: the Call the campaign
 * placed, and the AiAgentCall the answer URL creates the moment the
 * conversation starts (TwimlController::campaignCallAiAgent()). Its
 * status callback goes to webhooks/{provider}/calls/{id}/status ->
 * CallController::webhookStatus, which knows only about Call; the one
 * writer of AiAgentCall.status, .duration, .ended_at and the provider's
 * own cost is the AI-agent status webhook, which no campaign call has
 * ever pointed at, on either provider. The agent row therefore stayed
 * 'in-progress' for ever, with no duration and no provider cost, so every
 * AI-agent cost surface (the agent's own stats, the calls stats endpoint,
 * the CSV export) reported less than the call actually cost to run.
 *
 * The row is found by the provider's own call identifier, which both rows
 * carry and which is the only thing that ties them together: the answer
 * URL writes it to AiAgentCall.call_sid and, through
 * Call::shouldAdoptProviderCallId(), to the Call's own columns.
 *
 * Both of the Call's identifier columns are searched for one reason: a
 * status notification need not carry a call identifier at all. One that
 * does is adopted into provider_call_sid by UpdateCallStatusAction before
 * this runs, so the two columns agree; one that does not — the call is
 * then found by the call_id in the webhook's own URL instead — leaves
 * provider_call_sid holding whatever the row already had, and on a row
 * created before that column existed that is null while twilio_call_sid
 * holds the identifier the agent row is keyed by. Searching only the
 * newer column would silently close nothing for exactly those calls,
 * which is the defect this action exists to end.
 *
 * A provider cost is written only when the provider actually reported
 * one. Twilio's status callback carries no Price — SyncTwilioCallsCommand
 * and AiAgentCallController::show() exist to fetch it from the REST
 * resource afterwards — so on Twilio this normally records no cost, and
 * must not invent a zero: it would be indistinguishable from a call that
 * genuinely cost nothing, and would overwrite a figure already fetched.
 *
 * What is written here is provider-cost bookkeeping only. Nothing a
 * customer is charged goes through AiAgentCall — that is the Call row's
 * own price and UpdateCallStatusAction::deductCallCost(), neither of
 * which this touches. Callers must treat a failure here as costing them
 * nothing: UpdateCallStatusAction contains it so that the campaign
 * accounting alongside it cannot be taken down by a bookkeeping write.
 */
class CloseAiAgentCallAction
{
    /**
     * The statuses that mean the provider is done with this call. Same
     * list UpdateCallStatusAction uses to stamp Call.ended_at, so the two
     * rows can never disagree about whether the call is over.
     *
     * @var array<int, string>
     */
    private const TERMINAL_STATUSES = ['completed', 'failed', 'busy', 'no-answer', 'canceled'];

    /**
     * $status is the already-mapped Call status, not the provider's raw
     * one — both providers' vocabularies are reconciled well before this
     * runs, so there is nothing provider-specific left to do here.
     */
    public function execute(Call $call, string $status): void
    {
        if (! in_array($status, self::TERMINAL_STATUSES, true)) {
            return;
        }

        $identifiers = array_values(array_unique(array_filter([
            $call->provider_call_sid,
            $call->twilio_call_sid,
        ])));

        if ($identifiers === []) {
            return;
        }

        $agentCall = AiAgentCall::whereIn('call_sid', $identifiers)->first();

        if (! $agentCall) {
            return;
        }

        $updates = [
            'status' => $status,
            // Keep the first ending we recorded: a later notification for
            // the same call must not move the moment it ended.
            'ended_at' => $agentCall->ended_at ?? now(),
        ];

        // Only overwrite the duration when the provider has actually
        // reported one — an absent CallDuration must not blank a
        // duration an earlier notification already established.
        if ($call->duration_seconds !== null) {
            $updates['duration'] = (int) $call->duration_seconds;
        }

        $agentCall->update($updates);

        // The provider's real carrier cost for this call, as
        // UpdateCallStatusAction just stored it on the Call. The column
        // is still named twilio_cost for both providers — renaming it is
        // a schema change for its own change, not this one.
        if ($call->price !== null) {
            $agentCall->updateCostBreakdown(twilioCost: abs((float) $call->price));
        }
    }
}
