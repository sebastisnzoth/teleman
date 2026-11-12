<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\TwilioService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ErrorLogsController extends Controller
{
    public function __construct(
        private TwilioService $twilioService
    ) {}

    /**
     * Display Error Logs
     */
    public function index(Request $request): Response
    {
        $limit = $request->input('limit', 10);
        $limit = min(max($limit, 1), 100); // Between 1 and 100

        try {
            // Get global Twilio configuration
            $globalConfig = \App\Models\TwilioGlobalConfig::active();

            if (!$globalConfig) {
                return Inertia::render('Admin/ErrorLogs', [
                    'errorLogs' => [],
                    'error' => 'No active Twilio configuration found. Please configure Twilio in Settings.',
                    'limit' => $limit,
                ]);
            }

            // Create a temporary client directly with global config
            $client = new \Twilio\Rest\Client(
                $globalConfig->account_sid,
                $globalConfig->getDecryptedAuthToken()
            );
            
            // Fetch error logs directly
            $alerts = $client->monitor->v1->alerts->read(['limit' => $limit]);

            $errorLogs = [];
            foreach ($alerts as $alert) {
                $errorLogs[] = [
                    'sid' => $alert->sid,
                    'error_code' => $alert->errorCode,
                    'log_level' => $alert->logLevel,
                    'alert_text' => $alert->alertText,
                    'request_method' => $alert->requestMethod ?? null,
                    'request_url' => $alert->requestUrl ?? null,
                    'request_variables' => $alert->requestVariables ?? null,
                    'response_body' => $alert->responseBody ?? null,
                    'response_headers' => $alert->responseHeaders ?? null,
                    'service_sid' => $alert->serviceSid ?? null,
                    'resource_sid' => $alert->resourceSid ?? null,
                    'date_created' => $alert->dateCreated ? $alert->dateCreated->format('Y-m-d H:i:s') : null,
                    'date_generated' => $alert->dateGenerated ? $alert->dateGenerated->format('Y-m-d H:i:s') : null,
                    'date_updated' => $alert->dateUpdated ? $alert->dateUpdated->format('Y-m-d H:i:s') : null,
                ];
            }

            return Inertia::render('Admin/ErrorLogs', [
                'errorLogs' => $errorLogs,
                'count' => count($errorLogs),
                'limit' => $limit,
            ]);
        } catch (\Exception $e) {
            return Inertia::render('Admin/ErrorLogs', [
                'errorLogs' => [],
                'error' => $e->getMessage(),
                'limit' => $limit,
            ]);
        }
    }
}
