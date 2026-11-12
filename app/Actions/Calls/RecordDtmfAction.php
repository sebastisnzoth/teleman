<?php

declare(strict_types=1);

namespace App\Actions\Calls;

use App\Models\Call;
use App\Models\DtmfResponse;
use Illuminate\Support\Facades\Log;

class RecordDtmfAction
{
    /**
     * Record DTMF digit press from webhook
     */
    public function execute(Call $call, string $digits): void
    {
        // Save each digit individually
        for ($i = 0; $i < strlen($digits); $i++) {
            DtmfResponse::create([
                'call_id' => $call->id,
                'digit' => $digits[$i],
                'pressed_at' => now(),
            ]);
        }

        // Update call with concatenated digits
        $existingDigits = $call->dtmf_digits ?? '';
        $call->update([
            'dtmf_digits' => $existingDigits . $digits,
        ]);

        Log::info('DTMF recorded', [
            'call_id' => $call->id,
            'digits' => $digits,
        ]);
    }
}
