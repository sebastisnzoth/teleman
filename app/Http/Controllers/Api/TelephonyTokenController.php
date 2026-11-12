<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Telephony\IssueSoftphoneTokenAction;
use App\Exceptions\Telephony\SoftphoneUnavailableException;
use App\Http\Controllers\Controller;
use App\Services\Telephony\TelephonyManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The provider-neutral endpoints the browser softphone calls before it can
 * register a device. Neither accepts a provider from the client — see
 * IssueSoftphoneTokenAction.
 */
class TelephonyTokenController extends Controller
{
    /**
     * `hidden` repeats what the shared `telephony` Inertia prop already
     * carries, for the one surface that cannot read it: the embeddable
     * widget build mounts with createRoot and no Inertia, so the prop is
     * not on its page at all, and the widget was left showing controls
     * this provider has no way to honour. This reports
     * App\Enums\TelephonyProvider's decision; it never re-derives it.
     */
    public function status(TelephonyManager $telephony, IssueSoftphoneTokenAction $action): JsonResponse
    {
        $provider = $telephony->provider();

        return response()->json([
            'provider' => $provider->value,
            'configured' => $action->isConfigured(),
            'hidden' => $provider->hiddenFeatures(),
        ]);
    }

    public function token(Request $request, IssueSoftphoneTokenAction $action): JsonResponse
    {
        try {
            return response()->json($action->execute($request->user()));
        } catch (SoftphoneUnavailableException $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }
    }
}
