<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use App\Services\ThemeService;

// CSRF Cookie Route (for refreshing CSRF tokens)
Route::get('/sanctum/csrf-cookie', function () {
    return response()->json(['message' => 'CSRF cookie set']);
})->middleware('web');

Route::get('/', function (ThemeService $themeService) {
    try {
        $immediateData = $themeService->getImmediateData();
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('ThemeService error on home route: ' . $e->getMessage());
        $immediateData = [
            'settings' => null,
            'hero' => null,
            'stats' => null,
            'benefits' => null,
            'footer' => null,
        ];
    }

    return Inertia::render('welcome', array_merge($immediateData, [
        'canRegister' => Features::enabled(Features::registration()),
        // Deferred data - loads when visible (lazy loading)
        'features' => Inertia::defer(fn() => $themeService->getFeatures()),
        'useCases' => Inertia::defer(fn() => $themeService->getUseCases()),
        'pricing' => Inertia::defer(fn() => $themeService->getPricing()),
        'faqs' => Inertia::defer(fn() => $themeService->getFaqs()),
    ]));
})->name('home');

Route::get('/documentation', function () {
    return view('documentation');
})->name('documentation');

// Public legal pages (Privacy Policy, Terms, etc.)
Route::get('/page/{slug}', [App\Http\Controllers\PageController::class, 'show'])->name('page.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');

    // API Routes for AJAX requests
    Route::prefix('api')->group(function () {
        // Global Search
        Route::get('/search', [App\Http\Controllers\Api\SearchController::class, 'search'])->name('api.search');
        
        // Notifications
        Route::get('/notifications', [App\Http\Controllers\Api\NotificationController::class, 'index'])->name('api.notifications.index');
        Route::post('/notifications/{id}/read', [App\Http\Controllers\Api\NotificationController::class, 'markAsRead'])->name('api.notifications.read');
        Route::post('/notifications/read-all', [App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead'])->name('api.notifications.read-all');
    });

    // Twilio Configuration Routes (Admin Only) - Redirect to Settings
    // Gated the same as its destination (/settings/twilio) — otherwise a
    // Plivo-locked admin gets a 302 followed by a 404 instead of a clean 404.
    Route::prefix('twilio')->middleware(['role:admin', 'telephony.feature:account_migration'])->group(function () {
        Route::get('/', function () {
            return redirect('/settings/twilio');
        });
        // Note: Twilio token endpoint is defined in routes/twilio.php
    });

    // Admin Call Logs
    Route::get('/admin/call-logs', [App\Http\Controllers\Admin\CallLogsController::class, 'index'])
        ->middleware('role:admin')
        ->name('admin.call-logs');

    // Admin Error Logs
    Route::get('/admin/error-logs', [App\Http\Controllers\Admin\ErrorLogsController::class, 'index'])
        ->middleware('role:admin')
        ->name('admin.error-logs');

    // Softphone Route (standalone)
    Route::get('/softphone', [App\Http\Controllers\CallController::class, 'softphone'])->name('softphone');

    // Softphone numbers API (no KYC required - used by WebRTC softphone)
    Route::get('/softphone/api/my-numbers', [App\Http\Controllers\CallController::class, 'softphoneNumbers'])->name('softphone.numbers');

    // Call Management Routes (Requires Basic KYC)
    Route::middleware(['kyc:basic'])->prefix('calls')->name('calls.')->group(function () {
        Route::post('/initiate', [App\Http\Controllers\CallController::class, 'initiate'])->name('initiate');
        Route::post('/{call}/end', [App\Http\Controllers\CallController::class, 'end'])->name('end');
        // telephony.feature gate: hold redirects the PSTN leg through the raw
        // Twilio SDK to twiml.hold-music and back via twiml.resume-call,
        // neither of which has a plivo-xml sibling — see
        // config('telephony.hidden_features'). The softphone stops rendering
        // the button for the same reason (components/calls/call-controls.tsx),
        // but this is the enforcement: a stale page can still post here.
        Route::post('/{call}/hold', [App\Http\Controllers\CallController::class, 'hold'])
            ->middleware('telephony.feature:call_hold')
            ->name('hold');
        Route::post('/{call}/disposition', [App\Http\Controllers\CallController::class, 'saveDisposition'])->name('disposition');
        Route::get('/{call}/status', [App\Http\Controllers\CallController::class, 'status'])->name('status');
    });

    // Campaign Routes (Requires Basic KYC)
    Route::middleware(['kyc:basic'])->group(function () {
        Route::resource('campaigns', App\Http\Controllers\CampaignController::class);
        Route::get('/campaigns/{campaign}/queue', [App\Http\Controllers\CampaignController::class, 'queue'])->name('campaigns.queue');
        Route::post('/campaigns/{campaign}/launch', [App\Http\Controllers\CampaignController::class, 'launch'])->name('campaigns.launch');
        Route::post('/campaigns/{campaign}/pause', [App\Http\Controllers\CampaignController::class, 'pause'])->name('campaigns.pause');
        Route::post('/campaigns/{campaign}/resume', [App\Http\Controllers\CampaignController::class, 'resume'])->name('campaigns.resume');
        Route::post('/campaigns/{campaign}/on-demand-call', [App\Http\Controllers\CampaignController::class, 'makeOnDemandCall'])->name('campaigns.on-demand-call');
        Route::post('/campaigns/{campaign}/contacts/upload', [App\Http\Controllers\CampaignController::class, 'uploadContacts'])->name('campaigns.contacts.upload');
    });
    
    // Campaign Contact Management Routes (Requires Basic KYC)
    Route::middleware(['kyc:basic'])->group(function () {
        Route::post('/campaigns/{campaign}/contacts', [App\Http\Controllers\CampaignContactController::class, 'store'])->name('campaigns.contacts.store');
        Route::put('/campaigns/{campaign}/contacts/{contact}', [App\Http\Controllers\CampaignContactController::class, 'update'])->name('campaigns.contacts.update');
        Route::delete('/campaigns/{campaign}/contacts/{contact}', [App\Http\Controllers\CampaignContactController::class, 'destroy'])->name('campaigns.contacts.destroy');
        Route::get('/campaigns/{campaign}/variables', [App\Http\Controllers\CampaignContactController::class, 'getVariableKeys'])->name('campaigns.variables');
    });
    
    // Campaign Analytics Routes (Requires Basic KYC)
    Route::middleware(['kyc:basic'])->group(function () {
        Route::get('/campaigns/analytics/summary', [App\Http\Controllers\CampaignAnalyticsController::class, 'index'])->name('campaigns.analytics.summary');
        Route::get('/campaigns/{campaign}/analytics', [App\Http\Controllers\CampaignAnalyticsController::class, 'show'])->name('campaigns.analytics.show');
        Route::get('/campaigns/{campaign}/export', [App\Http\Controllers\CampaignAnalyticsController::class, 'export'])->name('campaigns.analytics.export');
    });
    
    // AI Message Assistant Routes (Requires Basic KYC)
    Route::middleware(['kyc:basic'])->prefix('api/ai/message')->name('api.ai.message.')->group(function () {
        Route::post('/generate', [App\Http\Controllers\Api\AiMessageController::class, 'generate'])->name('generate');
        Route::post('/enhance', [App\Http\Controllers\Api\AiMessageController::class, 'enhance'])->name('enhance');
        Route::get('/status', [App\Http\Controllers\Api\AiMessageController::class, 'status'])->name('status');
    });
    
    // Campaign Templates Routes (Requires Basic KYC)
    Route::middleware(['kyc:basic'])->group(function () {
        Route::get('/campaign-templates', [App\Http\Controllers\CampaignTemplateController::class, 'index'])->name('campaign-templates.index');
        Route::get('/campaign-templates/create', [App\Http\Controllers\CampaignTemplateController::class, 'create'])->name('campaign-templates.create');
        Route::get('/campaign-templates/{template}', [App\Http\Controllers\CampaignTemplateController::class, 'show'])->name('campaign-templates.show');
        Route::post('/campaign-templates', [App\Http\Controllers\CampaignTemplateController::class, 'store'])->name('campaign-templates.store');
        Route::put('/campaign-templates/{template}', [App\Http\Controllers\CampaignTemplateController::class, 'update'])->name('campaign-templates.update');
        Route::delete('/campaign-templates/{template}', [App\Http\Controllers\CampaignTemplateController::class, 'destroy'])->name('campaign-templates.destroy');
        Route::post('/campaign-templates/{template}/use', [App\Http\Controllers\CampaignTemplateController::class, 'use'])->name('campaign-templates.use');
        Route::post('/campaign-templates/from-campaign', [App\Http\Controllers\CampaignTemplateController::class, 'createFromCampaign'])->name('campaign-templates.from-campaign');
    });

    // Contact Management Routes (Requires Basic KYC)
    Route::middleware(['kyc:basic'])->group(function () {
        Route::get('/contacts/calling-session', [App\Http\Controllers\ContactController::class, 'callingSession'])->name('contacts.calling-session');
        Route::get('/contacts/{contact}/json', [App\Http\Controllers\ContactController::class, 'showJson'])->name('contacts.json');
        Route::resource('contacts', App\Http\Controllers\ContactController::class);
        Route::get('/contacts-import', [App\Http\Controllers\ContactController::class, 'import'])->name('contacts.import');
        Route::post('/contacts/import/preview', [App\Http\Controllers\ContactController::class, 'importPreview'])->name('contacts.import.preview');
        Route::post('/contacts-import', [App\Http\Controllers\ContactController::class, 'importProcess'])->name('contacts.import.process');
        Route::get('/contacts/import/{id}/status', [App\Http\Controllers\ContactController::class, 'importStatus'])->name('contacts.import.status');
        Route::get('/contacts-export', [App\Http\Controllers\ContactController::class, 'export'])->name('contacts.export');
        Route::post('/contacts/bulk/tag', [App\Http\Controllers\ContactController::class, 'bulkTag'])->name('contacts.bulk.tag');
        Route::post('/contacts/bulk/list', [App\Http\Controllers\ContactController::class, 'bulkAddToList'])->name('contacts.bulk.list');
        Route::post('/contacts/bulk/delete', [App\Http\Controllers\ContactController::class, 'bulkDelete'])->name('contacts.bulk.delete');
        Route::post('/contacts/validate-phone-numbers', [App\Http\Controllers\ContactController::class, 'validatePhoneNumbers'])->name('contacts.validate-phone-numbers');

        // Contact Lists Routes
        Route::resource('contact-lists', App\Http\Controllers\ContactListController::class);
        Route::post('/contact-lists/{contactList}/add', [App\Http\Controllers\ContactListController::class, 'addContacts'])->name('contact-lists.add');
        Route::post('/contact-lists/{contactList}/remove', [App\Http\Controllers\ContactListController::class, 'removeContacts'])->name('contact-lists.remove');

        // Contact Tags Routes
        Route::resource('contact-tags', App\Http\Controllers\ContactTagController::class)->except(['create', 'edit', 'show']);
    });

    // Audio File Routes (Requires Basic KYC)
    Route::middleware(['kyc:basic'])->group(function () {
        Route::get('/audio-files', [App\Http\Controllers\AudioFileController::class, 'index'])->name('audio-files.index');
        Route::post('/audio-files', [App\Http\Controllers\AudioFileController::class, 'store'])->name('audio-files.store');
        Route::delete('/audio-files/{audioFile}', [App\Http\Controllers\AudioFileController::class, 'destroy'])->name('audio-files.destroy');
        Route::get('/audio-files/{audioFile}/stream', [App\Http\Controllers\AudioFileController::class, 'stream'])->name('audio-files.stream');
    });

    // Call History Routes (Requires Basic KYC)
    Route::middleware(['kyc:basic'])->group(function () {
        Route::get('/calls', [App\Http\Controllers\CallController::class, 'index'])->name('calls.index');
        Route::get('/calls/export', [App\Http\Controllers\CallController::class, 'export'])->name('calls.export');
        Route::get('/calls/{call}', [App\Http\Controllers\CallController::class, 'show'])->name('calls.show');
        Route::post('/calls/sync', [App\Http\Controllers\CallController::class, 'syncWithTwilio'])->name('calls.sync');
        Route::post('/calls/{call}/sync', [App\Http\Controllers\CallController::class, 'syncCall'])->name('calls.sync.single');
    });

    // Knowledge Base Routes (Requires Business KYC - Conversational AI)
    Route::middleware(['kyc:business'])->prefix('knowledge-bases')->name('knowledge-bases.')->group(function () {
        Route::get('/', [App\Http\Controllers\KnowledgeBaseController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\KnowledgeBaseController::class, 'create'])->name('create');
        Route::get('/{id}/edit', [App\Http\Controllers\KnowledgeBaseController::class, 'edit'])->name('edit');
    });

    // AI Agent Routes (Requires Business KYC - Advanced Feature)
    Route::middleware(['kyc:business'])->prefix('ai-agents')->name('ai-agents.')->group(function () {
        Route::get('/', [App\Http\Controllers\AiAgentController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\AiAgentController::class, 'create'])->name('create');
        Route::get('/live', [App\Http\Controllers\AiAgentController::class, 'live'])->name('live');
        Route::get('/calls', [App\Http\Controllers\AiAgentController::class, 'calls'])->name('calls');
        Route::get('/{id}', [App\Http\Controllers\AiAgentController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [App\Http\Controllers\AiAgentController::class, 'edit'])->name('edit');
    });

    // Analytics Routes (Requires Basic KYC)
    Route::middleware(['kyc:basic'])->prefix('analytics')->name('analytics.')->group(function () {
        Route::get('/dashboard', [App\Http\Controllers\AnalyticsController::class, 'dashboard'])->name('dashboard');
        Route::get('/hot-leads', function () {
            return inertia('analytics/hot-leads');
        })->name('hot-leads');
        Route::get('/smart-scheduling', function () {
            $user = auth()->user();
            $ownerId = $user->isAgent() ? $user->parent_user_id : $user->id;
            $campaigns = App\Models\Campaign::where('user_id', $ownerId)
                ->orderBy('created_at', 'desc')
                ->get(['id', 'name']);
            return inertia('analytics/smart-scheduling', [
                'campaigns' => $campaigns
            ]);
        })->name('smart-scheduling');
        Route::get('/campaigns/{campaign}', [App\Http\Controllers\AnalyticsController::class, 'campaignAnalytics'])->name('campaign');
        Route::get('/campaigns/{campaign}/data', [App\Http\Controllers\AnalyticsController::class, 'getCampaignAnalyticsData'])->name('campaign.data');
        Route::get('/campaigns/{campaign}/export/calls', [App\Http\Controllers\AnalyticsController::class, 'exportCalls'])->name('campaign.export.calls');
        Route::get('/campaigns/{campaign}/export/contacts', [App\Http\Controllers\AnalyticsController::class, 'exportContacts'])->name('campaign.export.contacts');
    });

    // Follow-Up Sequences Routes (Requires Basic KYC)
    Route::middleware(['kyc:basic'])->prefix('sequences')->name('sequences.')->group(function () {
        Route::get('/', [App\Http\Controllers\SequenceController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\SequenceController::class, 'create'])->name('create');
        Route::get('/{sequence}', [App\Http\Controllers\SequenceController::class, 'show'])->name('show');
        Route::get('/{sequence}/edit', [App\Http\Controllers\SequenceController::class, 'edit'])->name('edit');
    });

    // CRM Integrations Routes (Requires Basic KYC)
    Route::middleware(['kyc:basic'])->prefix('integrations')->name('integrations.')->group(function () {
        Route::get('/', [App\Http\Controllers\IntegrationController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\IntegrationController::class, 'create'])->name('create');
        Route::get('/{id}/edit', [App\Http\Controllers\IntegrationController::class, 'edit'])->name('edit');
        Route::get('/{id}/logs', [App\Http\Controllers\IntegrationController::class, 'logs'])->name('logs');
        Route::get('/logs', [App\Http\Controllers\IntegrationController::class, 'allLogs'])->name('logs.all');
    });

    // User Management Routes (Admin + Customer)
    Route::middleware(['role:admin,customer'])->group(function () {
        Route::resource('users', App\Http\Controllers\UserController::class);
        Route::post('/users/{user}/toggle-status', [App\Http\Controllers\UserController::class, 'toggleStatus'])->name('users.toggle-status');
    });

    // Impersonation Routes
    Route::middleware(['role:admin'])->group(function () {
        Route::post('/users/{user}/impersonate', [App\Http\Controllers\UserController::class, 'impersonate'])->name('users.impersonate');
    });
    
    // Leave impersonation - accessible when impersonating (no role check needed)
    Route::post('/leave-impersonate', [App\Http\Controllers\UserController::class, 'leaveImpersonate'])->name('users.leave-impersonate');

    // Agent Management Routes (Admin + Customer)
    Route::middleware(['role:admin,customer'])->group(function () {
        Route::resource('agents', App\Http\Controllers\AgentController::class);
        Route::post('/agents/{agent}/toggle-status', [App\Http\Controllers\AgentController::class, 'toggleStatus'])->name('agents.toggle-status');
    });

    // Role & Permission Management Routes (Admin only for modifications, all can view)
    Route::prefix('roles')->name('roles.')->group(function () {
        Route::get('/', [App\Http\Controllers\RoleController::class, 'index'])->name('index');
        Route::get('/permissions', [App\Http\Controllers\RoleController::class, 'permissions'])->name('permissions');
        Route::get('/{role}', [App\Http\Controllers\RoleController::class, 'show'])->name('show');
        Route::get('/{role}/available-permissions', [App\Http\Controllers\RoleController::class, 'availablePermissions'])->name('available-permissions');
        
        // Admin only routes
        Route::middleware(['role:admin'])->group(function () {
            Route::put('/{role}/permissions', [App\Http\Controllers\RoleController::class, 'updatePermissions'])->name('update-permissions');
        });
    });

    // Phone Number Management Routes (Requires Basic KYC)
    Route::middleware(['kyc:basic'])->prefix('numbers')->name('numbers.')->group(function () {
        // Customer routes
        Route::get('/available', [App\Http\Controllers\CustomerNumberController::class, 'available'])->name('available');
        Route::get('/countries', [App\Http\Controllers\CustomerNumberController::class, 'countries'])->name('countries');
        Route::get('/my-numbers', [App\Http\Controllers\CustomerNumberController::class, 'myNumbers'])->name('my-numbers');
        Route::get('/my-requests', [App\Http\Controllers\CustomerNumberController::class, 'myRequests'])->name('my-requests');
        Route::get('/{phoneNumber}/price', [App\Http\Controllers\CustomerNumberController::class, 'getPurchasePrice'])->name('price');
        Route::post('/{phoneNumber}/request', [App\Http\Controllers\CustomerNumberController::class, 'request'])->name('request');
        Route::post('/requests/{numberRequest}/cancel', [App\Http\Controllers\CustomerNumberController::class, 'cancel'])->name('cancel');
        
        // API endpoint for softphone (excludes AI agent numbers)
        Route::get('/api/my-numbers', [App\Http\Controllers\CustomerNumberController::class, 'myNumbersApi'])->name('api.my-numbers');
        // API endpoint for AI agent assignment (includes all numbers)
        Route::get('/api/all-numbers', [App\Http\Controllers\CustomerNumberController::class, 'allNumbersApi'])->name('api.all-numbers');
    });
    
    // Pricing Calculator (Customer - Requires Basic KYC)
    Route::middleware(['kyc:basic'])->prefix('pricing')->name('pricing.')->group(function () {
        Route::get('/calculator', function () {
            return Inertia::render('Customer/PricingCalculator');
        })->name('calculator');
    });
    
    // Agent-specific phone number routes (no KYC required)
    Route::middleware(['role:agent'])->prefix('agent')->name('agent.')->group(function () {
        Route::get('/my-numbers', [App\Http\Controllers\AgentNumberController::class, 'myNumbers'])->name('my-numbers');
        Route::get('/api/my-numbers', [App\Http\Controllers\AgentNumberController::class, 'myNumbersApi'])->name('api.my-numbers');
    });

    // Customer Call Logs
    Route::get('/my-calls', [App\Http\Controllers\Customer\CallLogsController::class, 'index'])->name('my-calls');

    // Admin Number Management Routes
    Route::prefix('admin')->name('admin.')->middleware(['role:admin'])->group(function () {
        // System Configuration (Password Protected)
        Route::middleware(['password.confirm'])->group(function () {
            Route::get('/system-config', [App\Http\Controllers\Admin\SystemConfigController::class, 'index'])->name('system-config');
            Route::post('/system-config', [App\Http\Controllers\Admin\SystemConfigController::class, 'update'])->name('system-config.update');
            Route::post('/system-config/test-mail', [App\Http\Controllers\Admin\SystemConfigController::class, 'testMail'])->name('system-config.test-mail');
            Route::get('/system-config/live-rates', [App\Http\Controllers\Admin\SystemConfigController::class, 'fetchLiveRates'])->name('system-config.live-rates');
        });

        // Cron Job Monitoring
        Route::get('/cron-monitor', [App\Http\Controllers\Admin\CronMonitorController::class, 'index'])->name('cron-monitor');
        Route::post('/cron-monitor/cleanup', [App\Http\Controllers\Admin\CronMonitorController::class, 'cleanup'])->name('cron-monitor.cleanup');

        // Telephony provider settings — which provider this installation is
        // locked to, and (for Plivo) the credentials for it.
        Route::prefix('telephony')->name('telephony.')->group(function () {
            Route::get('/', [App\Http\Controllers\Admin\TelephonySettingsController::class, 'index'])->name('index');
            Route::post('/credentials', [App\Http\Controllers\Admin\TelephonySettingsController::class, 'storeCredentials'])->name('credentials.store');
            Route::post('/verify', [App\Http\Controllers\Admin\TelephonySettingsController::class, 'verify'])->name('verify');

            // Changing the locked provider: the destructive reset, then
            // the chooser it lands on. The chooser renders the installer's
            // provider-step component at an admin route — an installed
            // application must never re-enter the install wizard.
            //
            // The reset is additionally behind password.confirm, the same
            // gate the system-config page uses. Destroying a customer's
            // telephony setup must not ask for less than reading their
            // configuration does.
            Route::post('/reset', [App\Http\Controllers\Admin\TelephonySettingsController::class, 'reset'])
                ->middleware('password.confirm')->name('reset');
            Route::get('/choose', [App\Http\Controllers\Admin\TelephonySettingsController::class, 'choose'])->name('choose');
            Route::post('/choose', [App\Http\Controllers\Admin\TelephonySettingsController::class, 'lockProvider'])->name('choose.lock');
        });

        // Phone number inventory
        Route::get('/numbers', [App\Http\Controllers\Admin\PhoneNumberController::class, 'index'])->name('numbers.index');
        Route::get('/numbers/supported-countries', [App\Http\Controllers\Admin\PhoneNumberController::class, 'getSupportedCountries'])->name('numbers.supported-countries');
        Route::post('/numbers/sync', [App\Http\Controllers\Admin\PhoneNumberController::class, 'sync'])->name('numbers.sync');
        Route::post('/numbers/sync-pricing', [App\Http\Controllers\Admin\PhoneNumberController::class, 'syncPricing'])->name('numbers.sync-pricing');
        Route::post('/numbers/configure', [App\Http\Controllers\Admin\PhoneNumberController::class, 'configure'])->name('numbers.configure');
        Route::post('/numbers/assign', [App\Http\Controllers\Admin\PhoneNumberController::class, 'assignNumber'])->name('numbers.assign');
        Route::post('/numbers/assign-price', [App\Http\Controllers\Admin\PhoneNumberController::class, 'getAssignPrice'])->name('numbers.assign-price');
        Route::delete('/numbers/{phoneNumber}/release', [App\Http\Controllers\Admin\PhoneNumberController::class, 'release'])->name('numbers.release');
        Route::post('/numbers/{phoneNumber}/revoke', [App\Http\Controllers\Admin\PhoneNumberController::class, 'revoke'])->name('numbers.revoke');
        Route::get('/numbers/available', [App\Http\Controllers\Admin\PhoneNumberController::class, 'available'])->name('numbers.available');
        Route::get('/numbers/statistics', [App\Http\Controllers\Admin\PhoneNumberController::class, 'statistics'])->name('numbers.statistics');

        // Number requests
        Route::get('/number-requests', [App\Http\Controllers\Admin\NumberRequestController::class, 'index'])->name('number-requests.index');
        Route::get('/number-requests/{numberRequest}', [App\Http\Controllers\Admin\NumberRequestController::class, 'show'])->name('number-requests.show');
        Route::post('/number-requests/{numberRequest}/approve', [App\Http\Controllers\Admin\NumberRequestController::class, 'approve'])->name('number-requests.approve');
        Route::post('/number-requests/{numberRequest}/reject', [App\Http\Controllers\Admin\NumberRequestController::class, 'reject'])->name('number-requests.reject');
        Route::get('/number-requests/pending/count', [App\Http\Controllers\Admin\NumberRequestController::class, 'pendingCount'])->name('number-requests.pending-count');

        // Credit Management Routes
        Route::prefix('credit-management')->name('credit-management.')->group(function () {
            Route::get('/', [App\Http\Controllers\Admin\CreditManagementController::class, 'index'])->name('index');
            Route::get('/users/{user}', [App\Http\Controllers\Admin\CreditManagementController::class, 'show'])->name('show');
            Route::post('/users/{user}/adjust', [App\Http\Controllers\Admin\CreditManagementController::class, 'adjustCredit'])->name('adjust');
            Route::get('/transactions', [App\Http\Controllers\Admin\CreditManagementController::class, 'allTransactions'])->name('transactions');
            Route::get('/reports', [App\Http\Controllers\Admin\CreditManagementController::class, 'reports'])->name('reports');
            Route::get('/reports/export', [App\Http\Controllers\Admin\CreditManagementController::class, 'exportReport'])->name('reports.export');
        });

        // Pricing Rules Management
        Route::prefix('pricing')->name('pricing.')->group(function () {
            Route::get('/', [App\Http\Controllers\Admin\PricingRuleController::class, 'index'])->name('index');
            Route::get('/calculator', function () {
                return Inertia::render('Admin/Pricing/Calculator');
            })->name('calculator');
            Route::get('/create', [App\Http\Controllers\Admin\PricingRuleController::class, 'create'])->name('create');
            Route::post('/', [App\Http\Controllers\Admin\PricingRuleController::class, 'store'])->name('store');
            Route::get('/{pricingRule}', [App\Http\Controllers\Admin\PricingRuleController::class, 'show'])->name('show');
            Route::get('/{pricingRule}/edit', [App\Http\Controllers\Admin\PricingRuleController::class, 'edit'])->name('edit');
            Route::put('/{pricingRule}', [App\Http\Controllers\Admin\PricingRuleController::class, 'update'])->name('update');
            Route::delete('/{pricingRule}', [App\Http\Controllers\Admin\PricingRuleController::class, 'destroy'])->name('destroy');
            Route::post('/{pricingRule}/toggle', [App\Http\Controllers\Admin\PricingRuleController::class, 'toggleActive'])->name('toggle');
            Route::post('/bulk-import', [App\Http\Controllers\Admin\PricingRuleController::class, 'bulkImport'])->name('bulk-import');
            Route::post('/bulk-update-markup', [App\Http\Controllers\Admin\PricingRuleController::class, 'bulkUpdateMarkup'])->name('bulk-update-markup');
            Route::post('/bulk-delete', [App\Http\Controllers\Admin\PricingRuleController::class, 'bulkDelete'])->name('bulk-delete');
            Route::post('/toggle-pin', [App\Http\Controllers\Admin\PricingRuleController::class, 'togglePin'])->name('toggle-pin');
            Route::post('/fetch-twilio', [App\Http\Controllers\Admin\PricingRuleController::class, 'fetchTwilioPricing'])->name('fetch-twilio');
        });

        // Profit Dashboard
        Route::prefix('profit')->name('profit.')->group(function () {
            Route::get('/', [App\Http\Controllers\Admin\ProfitDashboardController::class, 'index'])->name('dashboard');
            Route::get('/export', [App\Http\Controllers\Admin\ProfitDashboardController::class, 'export'])->name('export');
            Route::get('/api/stats', [App\Http\Controllers\Admin\ProfitDashboardController::class, 'realTimeStats'])->name('api.stats');
            Route::get('/api/daily-trend', [App\Http\Controllers\Admin\ProfitDashboardController::class, 'dailyTrend'])->name('api.daily-trend');
            Route::get('/api/service-breakdown', [App\Http\Controllers\Admin\ProfitDashboardController::class, 'serviceBreakdown'])->name('api.service-breakdown');
        });

        // Profit Analytics
        Route::prefix('profit-analytics')->name('profit-analytics.')->group(function () {
            Route::get('/', [App\Http\Controllers\Admin\ProfitAnalyticsController::class, 'index'])->name('index');
            Route::get('/export', [App\Http\Controllers\Admin\ProfitAnalyticsController::class, 'export'])->name('export');
        });

        // Theme Management
        Route::prefix('theme')->name('theme.')->group(function () {
            Route::get('/', [App\Http\Controllers\Admin\ThemeController::class, 'index'])->name('index');
            
            // Settings
            Route::get('/settings', [App\Http\Controllers\Admin\ThemeController::class, 'settingsEdit'])->name('settings.edit');
            Route::put('/settings', [App\Http\Controllers\Admin\ThemeController::class, 'settingsUpdate'])->name('settings.update');
            Route::post('/logo/upload', [App\Http\Controllers\Admin\ThemeController::class, 'uploadLogo'])->name('logo.upload');
            Route::delete('/logo', [App\Http\Controllers\Admin\ThemeController::class, 'deleteLogo'])->name('logo.delete');
            Route::post('/og-image/upload', [App\Http\Controllers\Admin\ThemeController::class, 'uploadOgImage'])->name('og-image.upload');
            Route::delete('/og-image', [App\Http\Controllers\Admin\ThemeController::class, 'deleteOgImage'])->name('og-image.delete');
            
            // Hero
            Route::get('/hero', [App\Http\Controllers\Admin\ThemeController::class, 'heroEdit'])->name('hero.edit');
            Route::put('/hero', [App\Http\Controllers\Admin\ThemeController::class, 'heroUpdate'])->name('hero.update');
            
            // Stats
            Route::get('/stats', [App\Http\Controllers\Admin\ThemeController::class, 'statsIndex'])->name('stats.index');
            Route::post('/stats', [App\Http\Controllers\Admin\ThemeController::class, 'statsStore'])->name('stats.store');
            Route::put('/stats/{stat}', [App\Http\Controllers\Admin\ThemeController::class, 'statsUpdate'])->name('stats.update');
            Route::delete('/stats/{stat}', [App\Http\Controllers\Admin\ThemeController::class, 'statsDestroy'])->name('stats.destroy');
            
            // Features
            Route::get('/features', [App\Http\Controllers\Admin\ThemeController::class, 'featuresIndex'])->name('features.index');
            Route::post('/features', [App\Http\Controllers\Admin\ThemeController::class, 'featuresStore'])->name('features.store');
            Route::put('/features/{feature}', [App\Http\Controllers\Admin\ThemeController::class, 'featuresUpdate'])->name('features.update');
            Route::delete('/features/{feature}', [App\Http\Controllers\Admin\ThemeController::class, 'featuresDestroy'])->name('features.destroy');
            
            // Benefits
            Route::get('/benefits', [App\Http\Controllers\Admin\ThemeController::class, 'benefitsIndex'])->name('benefits.index');
            Route::post('/benefits', [App\Http\Controllers\Admin\ThemeController::class, 'benefitsStore'])->name('benefits.store');
            Route::put('/benefits/{benefit}', [App\Http\Controllers\Admin\ThemeController::class, 'benefitsUpdate'])->name('benefits.update');
            Route::delete('/benefits/{benefit}', [App\Http\Controllers\Admin\ThemeController::class, 'benefitsDestroy'])->name('benefits.destroy');
            
            // Use Cases
            Route::get('/use-cases', [App\Http\Controllers\Admin\ThemeController::class, 'useCasesIndex'])->name('use-cases.index');
            Route::post('/use-cases', [App\Http\Controllers\Admin\ThemeController::class, 'useCasesStore'])->name('use-cases.store');
            Route::put('/use-cases/{useCase}', [App\Http\Controllers\Admin\ThemeController::class, 'useCasesUpdate'])->name('use-cases.update');
            Route::delete('/use-cases/{useCase}', [App\Http\Controllers\Admin\ThemeController::class, 'useCasesDestroy'])->name('use-cases.destroy');
            
            // Pricing
            Route::get('/pricing', [App\Http\Controllers\Admin\ThemeController::class, 'pricingIndex'])->name('pricing.index');
            Route::post('/pricing', [App\Http\Controllers\Admin\ThemeController::class, 'pricingStore'])->name('pricing.store');
            Route::put('/pricing/{pricing}', [App\Http\Controllers\Admin\ThemeController::class, 'pricingUpdate'])->name('pricing.update');
            Route::delete('/pricing/{pricing}', [App\Http\Controllers\Admin\ThemeController::class, 'pricingDestroy'])->name('pricing.destroy');
            
            // FAQs
            Route::get('/faqs', [App\Http\Controllers\Admin\ThemeController::class, 'faqsIndex'])->name('faqs.index');
            Route::post('/faqs', [App\Http\Controllers\Admin\ThemeController::class, 'faqsStore'])->name('faqs.store');
            Route::put('/faqs/{faq}', [App\Http\Controllers\Admin\ThemeController::class, 'faqsUpdate'])->name('faqs.update');
            Route::delete('/faqs/{faq}', [App\Http\Controllers\Admin\ThemeController::class, 'faqsDestroy'])->name('faqs.destroy');
            
            // Footer
            Route::get('/footer', [App\Http\Controllers\Admin\ThemeController::class, 'footerEdit'])->name('footer.edit');
            Route::put('/footer', [App\Http\Controllers\Admin\ThemeController::class, 'footerUpdate'])->name('footer.update');
        });

        // Page Management (Privacy Policy, Terms, etc.)
        Route::prefix('pages')->name('pages.')->group(function () {
            Route::get('/', [App\Http\Controllers\Admin\PageController::class, 'index'])->name('index');
            Route::get('/{slug}', [App\Http\Controllers\Admin\PageController::class, 'edit'])->name('edit');
            Route::put('/{slug}', [App\Http\Controllers\Admin\PageController::class, 'update'])->name('update');
        });

        // Language Management
        Route::prefix('languages')->name('languages.')->group(function () {
            Route::get('/', [App\Http\Controllers\Admin\LanguageController::class, 'index'])->name('index');
            Route::get('/create', [App\Http\Controllers\Admin\LanguageController::class, 'create'])->name('create');
            Route::post('/', [App\Http\Controllers\Admin\LanguageController::class, 'store'])->name('store');
            Route::get('/{language}/edit', [App\Http\Controllers\Admin\LanguageController::class, 'edit'])->name('edit');
            Route::put('/{language}', [App\Http\Controllers\Admin\LanguageController::class, 'update'])->name('update');
            Route::delete('/{language}', [App\Http\Controllers\Admin\LanguageController::class, 'destroy'])->name('destroy');
            Route::post('/{language}/toggle', [App\Http\Controllers\Admin\LanguageController::class, 'toggleActive'])->name('toggle');

            // Translation management
            Route::get('/{language}/translations', [App\Http\Controllers\Admin\TranslationController::class, 'index'])->name('translations.index');
            Route::put('/{language}/translations', [App\Http\Controllers\Admin\TranslationController::class, 'update'])->name('translations.update');
            Route::post('/{language}/translations/import', [App\Http\Controllers\Admin\TranslationController::class, 'import'])->name('translations.import');
            Route::get('/{language}/translations/export', [App\Http\Controllers\Admin\TranslationController::class, 'export'])->name('translations.export');
        });
    });

    // Language Switching (all authenticated users)
    Route::post('/api/set-locale', function (\Illuminate\Http\Request $request) {
        $request->validate(['locale' => 'required|string|max:10']);

        $validLocales = \Illuminate\Support\Facades\Cache::remember('active_locale_codes', 3600, fn () =>
            \App\Models\Language::active()->pluck('code')->toArray()
        );

        if (!in_array($request->locale, $validLocales, true)) {
            return back()->with('error', 'Invalid locale.');
        }

        $request->session()->put('locale', $request->locale);

        if ($request->user()) {
            $request->user()->update(['locale' => $request->locale]);
        }

        app()->setLocale($request->locale);

        return back();
    })->name('api.set-locale');

    // Credit & Payment Routes (Admin + Customer only - agents cannot manage credits)
    Route::middleware(['role:admin,customer'])->group(function () {
    Route::prefix('credit')->name('credit.')->group(function () {
        Route::get('/', [App\Http\Controllers\CreditController::class, 'index'])->name('index');
        Route::get('/top-up', [App\Http\Controllers\CreditController::class, 'topUp'])->name('top-up');
        Route::get('/history', [App\Http\Controllers\CreditController::class, 'history'])->name('history');
        Route::post('/settings', [App\Http\Controllers\CreditController::class, 'updateSettings'])->name('settings.update');
        Route::get('/export', [App\Http\Controllers\CreditController::class, 'export'])->name('export');
        Route::get('/exchange-rate', [App\Http\Controllers\CreditController::class, 'exchangeRate'])->name('exchange-rate');
    });

    Route::prefix('payment')->name('payment.')->group(function () {
        Route::post('/checkout', [App\Http\Controllers\PaymentController::class, 'createCheckout'])->name('checkout');
        Route::get('/success', [App\Http\Controllers\PaymentController::class, 'success'])->name('success');
        Route::get('/cancel', [App\Http\Controllers\PaymentController::class, 'cancel'])->name('cancel');
        Route::post('/refund/{transaction}', [App\Http\Controllers\PaymentController::class, 'refund'])->name('refund');
    });

    Route::prefix('credit/razorpay')->name('credit.razorpay.')->group(function () {
        Route::get('/checkout', [App\Http\Controllers\CreditController::class, 'razorpayCheckout'])->name('checkout');
    });
    }); // end role:admin,customer for credit/payment
});

// Twilio Webhook Routes (exclude from CSRF, validated by Twilio signature + rate limited)
Route::prefix('webhooks/twilio')->name('webhooks.twilio.')->middleware(['twilio.signature', 'throttle:120,1'])->group(function () {
    Route::post('/calls/{call_id}/status', [App\Http\Controllers\CallController::class, 'webhookStatus'])->name('call.status');
    Route::post('/calls/{call_id}/recording', [App\Http\Controllers\CallController::class, 'webhookRecording'])->name('call.recording');
    Route::post('/calls/{call_id}/transcription', [App\Http\Controllers\CallController::class, 'webhookTranscription'])->name('call.transcription');
    Route::post('/calls/{call_id}/dtmf', [App\Http\Controllers\CallController::class, 'webhookDtmf'])->name('call.dtmf');

    // Generic status callback for inbound calls (when call_id is not in URL)
    Route::post('/call-status', [App\Http\Controllers\CallController::class, 'webhookStatus'])->name('call-status');

    // AI Agent call status callback (Twilio sends CallSid in request body)
    Route::post('/call/status', [App\Http\Controllers\Api\AiAgentCallController::class, 'handleCallStatus'])->name('ai-agent.call.status');

    // AI Agent recording callback — Twilio POSTs here when the recording is ready.
    // Saves the recording URL on the AiAgentCall so it can be played back from the UI.
    Route::post('/ai-agent-call/recording', [App\Http\Controllers\Api\AiAgentCallController::class, 'recordingCallback'])->name('ai-agent.call.recording');
});

// Payment Webhook Routes (exclude from CSRF verification)
Route::prefix('webhooks/payment')->name('webhooks.payment.')->group(function () {
    Route::post('/{gateway}', [App\Http\Controllers\PaymentController::class, 'handleWebhook'])->name('webhook');
});

// TwiML Generation Routes (exclude from CSRF, validated by Twilio signature)
Route::prefix('twiml')->name('twiml.')->middleware(['twilio.signature'])->group(function () {
    Route::match(['get', 'post'], '/test-call', function() {
        return response('<?xml version="1.0" encoding="UTF-8"?><Response><Say>Hello! This is a test call from Teleman AI. Your Twilio setup is working correctly.</Say></Response>', 200)->header('Content-Type', 'text/xml');
    })->name('test-call');
    Route::match(['get', 'post'], '/inbound-call', [App\Http\Controllers\TwimlController::class, 'inboundCall'])->name('inbound.call');
    Route::match(['get', 'post'], '/manual-call', [App\Http\Controllers\TwimlController::class, 'manualCall'])->name('manual.call');
    Route::match(['get', 'post'], '/ai-agent-call', [App\Http\Controllers\TwimlController::class, 'aiAgentCall'])->name('ai-agent.call');
    Route::match(['get', 'post'], '/campaign/tts', [App\Http\Controllers\TwimlController::class, 'campaignCallTts'])->name('campaign.tts');
    Route::match(['get', 'post'], '/campaign/voice', [App\Http\Controllers\TwimlController::class, 'campaignCallVoice'])->name('campaign.voice');
    Route::match(['get', 'post'], '/campaign/ai-agent', [App\Http\Controllers\TwimlController::class, 'campaignCallAiAgent'])->name('campaign.ai-agent');
    
    // DTMF & Recording Webhooks
    Route::post('/dtmf', [App\Http\Controllers\DtmfWebhookController::class, 'handle'])->name('webhook.dtmf');
    Route::post('/recording', [App\Http\Controllers\TwimlController::class, 'recordingCallback'])->name('webhook.recording');

    // Call Queue TwiML endpoints
    Route::match(['get', 'post'], '/queue-wait', [App\Http\Controllers\TwimlController::class, 'queueWait'])->name('queue-wait');
    Route::match(['get', 'post'], '/queue-result', [App\Http\Controllers\TwimlController::class, 'queueResult'])->name('queue-result');
    Route::match(['get', 'post'], '/dequeue-call', [App\Http\Controllers\TwimlController::class, 'dequeueCall'])->name('dequeue-call');

    // Hold music TwiML (plays music loop while caller is on hold)
    Route::match(['get', 'post'], '/hold-music', function () {
        $twiml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Response>'
            . '<Play loop="0">http://com.twilio.sounds.music.s3.amazonaws.com/ClockworkWaltz.mp3</Play>'
            . '</Response>';
        return response($twiml, 200)->header('Content-Type', 'text/xml');
    })->name('hold-music');

    // Dial complete TwiML: returned when <Dial> action completes (hangup, timeout, hold redirect, etc.)
    // Twilio requires TwiML from action URLs — this replaces using the JSON status webhook as action.
    // Returns empty <Response/> to silently end the leg (avoids confusing audio during hold transitions).
    Route::match(['get', 'post'], '/dial-complete/{call}', function (App\Models\Call $call) {
        $twiml = '<?xml version="1.0" encoding="UTF-8"?><Response/>';
        return response($twiml, 200)->header('Content-Type', 'text/xml');
    })->name('dial-complete');

    // Resume call TwiML: reconnects the PSTN leg back to the browser (Twilio Client).
    // Called when the PSTN child leg is redirected away from hold music.
    // Uses <Dial><Client> to bridge the PSTN leg to the agent's browser softphone.
    Route::match(['get', 'post'], '/resume-call/{call}', function (App\Models\Call $call) {
        // The browser identity matches what was used when the call token was generated
        $clientIdentity = 'user_' . $call->user_id;
        $twiml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Response>'
            . '<Dial timeout="30">'
            . '<Client>' . htmlspecialchars($clientIdentity, ENT_XML1) . '</Client>'
            . '</Dial>'
            . '</Response>';
        return response($twiml, 200)->header('Content-Type', 'text/xml');
    })->name('resume-call');
});

// Plivo Webhook Routes — mirrors the "Twilio Webhook Routes" group above:
// same handlers (CallController::webhookStatus/webhookRecording/
// webhookDtmf), different URL and signature scheme. plivo.signature is
// listed first here for readability, but the array position alone does
// NOT decide execution order — Laravel sorts the whole resolved
// middleware stack by $middlewarePriority, which is why
// bootstrap/app.php's prependToPriorityList() call is what actually
// makes plivo.signature run ahead of throttling, session and model
// binding (see that call's own comment, and
// PlivoWebhookRoutesTest::test_the_signature_middleware_resolves_before_throttling_session_and_binding
// for the proof against the real, sorted pipeline). No
// telephony.feature gate: unlike sip/trunk and byoc/trunk (opt-in,
// Twilio-only ancillary features, correctly hidden on a Plivo lock — see
// EnsureTelephonyFeature), this is the core surface a live call's status
// and recording depend on, exactly like webhooks/twilio/* above, which
// carries no such gate either. Gating a live call's own webhook behind a
// feature flag would turn a lock misconfiguration into a dropped call
// instead of a merely-missing feature.
Route::prefix('webhooks/plivo')->name('webhooks.plivo.')->middleware(['plivo.signature', 'throttle:120,1'])->group(function () {
    Route::post('/calls/{call_id}/status', [App\Http\Controllers\CallController::class, 'webhookStatus'])->name('call.status')->defaults('provider', 'plivo');
    Route::post('/calls/{call_id}/recording', [App\Http\Controllers\CallController::class, 'webhookRecording'])->name('call.recording')->defaults('provider', 'plivo');
    Route::post('/calls/{call_id}/dtmf', [App\Http\Controllers\CallController::class, 'webhookDtmf'])->name('call.dtmf')->defaults('provider', 'plivo');

    // Generic status callback for inbound calls (call_id not in the URL):
    // the Plivo Application's hangup_url is fixed at the application
    // level and cannot carry a per-call segment, mirroring
    // webhooks.twilio.call-status's identical role above.
    Route::post('/call-status', [App\Http\Controllers\CallController::class, 'webhookStatus'])->name('call-status')->defaults('provider', 'plivo');

    // AI Agent call status callback — the sibling of
    // webhooks.twilio.ai-agent.call.status above, and the only writer of
    // AiAgentCall's status, duration, ended_at and the provider's own cost.
    // Without it an AI-agent call placed on Plivo stays 'in-progress' for
    // ever with no duration and no provider cost, which makes
    // ProfitAnalyticsService report AI-agent margin as higher than it is.
    Route::post('/call/status', [App\Http\Controllers\Api\AiAgentCallController::class, 'handleCallStatus'])->name('ai-agent.call.status')->defaults('provider', 'plivo');

    // AI Agent recording callback — sibling of
    // webhooks.twilio.ai-agent.call.recording, the only writer of
    // AiAgentCall.recording_url.
    Route::post('/ai-agent-call/recording', [App\Http\Controllers\Api\AiAgentCallController::class, 'recordingCallback'])->name('ai-agent.call.recording')->defaults('provider', 'plivo');
});

// Plivo XML Generation Routes — mirrors the "TwiML Generation Routes"
// group above: same handlers (TwimlController), different URL and
// signature scheme, no throttle (matching the twiml group's own
// unthrottled shape). Not every twiml/* route has a mirror here:
// dtmf/recording have no live caller on either provider (nothing points
// at them — campaigns build their DTMF/recording callback URLs straight
// at webhooks.{provider}.call.dtmf/recording instead); and hold-music/
// resume-call belong to the Twilio-only browser-hold feature
// (CallController::hold(), a hardcoded new Twilio\Rest\Client() call, no
// TelephonyManager involved). The call-queue endpoints DO have mirrors
// now: the queue works on Plivo, built from a conference room rather
// than from Twilio's <Enqueue>, and these three URLs are what its
// waitSound, its conference action and its agent's answer URL point at
// (see CallQueueService). dial-complete DOES need a mirror,
// despite sitting in that same TwiML Generation Routes group above: both
// manualCall() and inboundCall() pass it as the <Dial> action, which
// PlivoCallXmlGenerator maps onto Plivo's own action attribute
// (defaulting redirect="true") — so on Plivo, TwimlController reaches it
// through TelephonyManager just like every other route here, not
// through the Twilio-only hold feature.
Route::prefix('plivo-xml')->name('plivo-xml.')->middleware(['plivo.signature'])->group(function () {
    Route::match(['get', 'post'], '/inbound-call', [App\Http\Controllers\TwimlController::class, 'inboundCall'])->name('inbound.call')->defaults('provider', 'plivo');
    Route::match(['get', 'post'], '/manual-call', [App\Http\Controllers\TwimlController::class, 'manualCall'])->name('manual.call')->defaults('provider', 'plivo');
    Route::match(['get', 'post'], '/dial-complete/{call}', function (App\Models\Call $call) {
        $twiml = '<?xml version="1.0" encoding="UTF-8"?><Response/>';

        return response($twiml, 200)->header('Content-Type', 'text/xml');
    })->name('dial-complete');
    Route::match(['get', 'post'], '/ai-agent-call', [App\Http\Controllers\TwimlController::class, 'aiAgentCall'])->name('ai-agent.call')->defaults('provider', 'plivo');
    Route::match(['get', 'post'], '/campaign/tts', [App\Http\Controllers\TwimlController::class, 'campaignCallTts'])->name('campaign.tts')->defaults('provider', 'plivo');
    Route::match(['get', 'post'], '/campaign/voice', [App\Http\Controllers\TwimlController::class, 'campaignCallVoice'])->name('campaign.voice')->defaults('provider', 'plivo');
    Route::match(['get', 'post'], '/campaign/ai-agent', [App\Http\Controllers\TwimlController::class, 'campaignCallAiAgent'])->name('campaign.ai-agent')->defaults('provider', 'plivo');

    // Call queue endpoints — the siblings of twiml/queue-wait,
    // twiml/queue-result and twiml/dequeue-call above, reaching the same
    // TwimlController methods. queue-wait answers the conference's
    // waitSound; queue-result its action URL, requested when the caller
    // leaves the room; dequeue-call answers the agent's own leg and joins
    // them to the caller's room.
    //
    // queue-agent-hangup has no Twilio sibling because it has no Twilio
    // job: a Twilio caller waits in a provider-side queue and the next
    // <Dial><Queue> takes them from its head, so an agent who never picks
    // up costs them nothing. A conference room belongs to one caller, so
    // here the end of an unanswered agent leg is what puts that caller
    // back in the queue. It is a notification, not a document request,
    // and it is in this group for the same signature check as the rest —
    // it moves a queue entry. The agent's leg is given this URL as a
    // hangup notification only, never as a ring one, so a request to it
    // means the leg has ended and nothing in the body has to be read to
    // know that (see PlivoVoiceProvider::makeCall()'s hangupCallback).
    Route::match(['get', 'post'], '/queue-wait', [App\Http\Controllers\TwimlController::class, 'queueWait'])->name('queue-wait')->defaults('provider', 'plivo');
    Route::match(['get', 'post'], '/queue-result', [App\Http\Controllers\TwimlController::class, 'queueResult'])->name('queue-result')->defaults('provider', 'plivo');
    Route::match(['get', 'post'], '/dequeue-call', [App\Http\Controllers\TwimlController::class, 'dequeueCall'])->name('dequeue-call')->defaults('provider', 'plivo');
    Route::match(['get', 'post'], '/queue-agent-hangup', [App\Http\Controllers\TwimlController::class, 'queueAgentHangup'])->name('queue-agent-hangup')->defaults('provider', 'plivo');
});

// Call Queue API Routes
Route::middleware(['auth'])->prefix('call-queue')->name('call-queue.')->group(function () {
    Route::get('/status', [App\Http\Controllers\CallQueueController::class, 'status'])->name('status');
    Route::post('/dequeue', [App\Http\Controllers\CallQueueController::class, 'dequeue'])->name('dequeue');
    Route::put('/settings', [App\Http\Controllers\CallQueueController::class, 'updateSettings'])->name('settings');
});

// SMS Management Routes (Requires Basic KYC, Admin + Customer only)
Route::middleware(['auth', 'verified', 'kyc:basic', 'role:admin,customer'])->prefix('sms')->name('sms.')->group(function () {
    Route::get('/', [App\Http\Controllers\SMS\SmsController::class, 'index'])->name('index');
    Route::get('/compose', [App\Http\Controllers\SMS\SmsController::class, 'compose'])->name('compose');
    Route::post('/send', [App\Http\Controllers\SMS\SmsController::class, 'send'])->name('send');
    Route::post('/contact-list-numbers', [App\Http\Controllers\SMS\SmsController::class, 'getContactListNumbers'])->name('contact-list-numbers');
    
    // Phone number management - users now use assigned phone numbers from the main phone number system
    // Route::get('/phone-numbers/create', [App\Http\Controllers\SMS\SmsController::class, 'create'])->name('phone-numbers.create');
    // Route::post('/phone-numbers', [App\Http\Controllers\SMS\SmsController::class, 'store'])->name('phone-numbers.store');
    // Route::get('/phone-numbers/{phoneNumber}/check-config', [App\Http\Controllers\SMS\SmsController::class, 'checkConfig'])->name('phone-numbers.check-config');
    // Route::post('/phone-numbers/{phoneNumber}/configure', [App\Http\Controllers\SMS\SmsController::class, 'configureWebhook'])->name('phone-numbers.configure');
    // Route::post('/phone-numbers/{phoneNumber}/test', [App\Http\Controllers\SMS\SmsController::class, 'testConnection'])->name('phone-numbers.test');
    // Route::delete('/phone-numbers/{phoneNumber}', [App\Http\Controllers\SMS\SmsController::class, 'destroy'])->name('phone-numbers.destroy');
    
    Route::post('/phone-numbers/{phoneNumber}/assign-agent', [App\Http\Controllers\SMS\SmsController::class, 'assignAgent'])->name('phone-numbers.assign-agent');
    
    Route::get('/conversations/{phoneNumber?}', [App\Http\Controllers\SMS\SmsController::class, 'conversations'])->name('conversations');
    Route::get('/conversations/{conversation}/messages', [App\Http\Controllers\SMS\SmsController::class, 'conversationMessages'])->name('conversations.messages');
    Route::post('/conversations/{conversation}/send', [App\Http\Controllers\SMS\SmsController::class, 'sendMessage'])->name('conversations.send');
    
    Route::get('/templates', [App\Http\Controllers\SMS\SmsController::class, 'templates'])->name('templates');
    Route::get('/analytics', [App\Http\Controllers\SMS\SmsController::class, 'analytics'])->name('analytics');
});

// SIP Trunk Management Routes (Admin Only) — Twilio-only, hidden on Plivo
Route::middleware(['auth', 'verified', 'role:admin', 'telephony.feature:sip_trunk'])->prefix('sip-trunk')->group(function () {
    Route::get('/', [App\Http\Controllers\TrunkManagementController::class, 'index'])->name('sip-trunk.index');
    Route::get('/setup', [App\Http\Controllers\TrunkManagementController::class, 'create'])->name('sip-trunk.create');
    Route::post('/setup', [App\Http\Controllers\TrunkManagementController::class, 'store'])->name('sip-trunk.store');
    Route::get('/{trunk}', [App\Http\Controllers\TrunkManagementController::class, 'show'])->name('sip-trunk.show');
    Route::delete('/{trunk}', [App\Http\Controllers\TrunkManagementController::class, 'destroy'])->name('sip-trunk.destroy');
    
    // AJAX endpoints
    Route::get('/{trunk}/setup-status', [App\Http\Controllers\TrunkManagementController::class, 'setupStatus'])->name('sip-trunk.setup-status');
    Route::post('/{trunk}/sync', [App\Http\Controllers\TrunkManagementController::class, 'sync'])->name('sip-trunk.sync');
    Route::post('/{trunk}/health', [App\Http\Controllers\TrunkManagementController::class, 'health'])->name('sip-trunk.health');

    // Phone number assignment
    Route::post('/{trunk}/numbers/{number}/assign', [App\Http\Controllers\TrunkManagementController::class, 'assignNumber'])->name('sip-trunk.assign-number');
    Route::get('/{trunk}/assignment-options', [App\Http\Controllers\TrunkManagementController::class, 'getAssignmentOptions'])->name('sip-trunk.assignment-options');
});

// SIP Trunk Webhook Routes (validated by Twilio signature + webhook_token) — Twilio-only, hidden on Plivo
// telephony.feature MUST run first: ValidateTwilioSignature only skips
// validation in the testing environment, so in production a request with
// no signature header would otherwise reach (and 403 from) that middleware
// before this gate ever ran, breaking the 404-not-403 promise.
Route::prefix('sip/trunk')->middleware(['telephony.feature:sip_trunk', 'twilio.signature', 'throttle:120,1'])->group(function () {
    Route::post('/voice/{webhook_token}', [App\Http\Controllers\TrunkWebhookController::class, 'handleInboundCall'])->name('sip.trunk.voice');
    Route::post('/call-status/{trunk}', [App\Http\Controllers\TrunkWebhookController::class, 'handleStatusCallback'])->name('sip.trunk.status');
    Route::post('/disaster-recovery', [App\Http\Controllers\TrunkWebhookController::class, 'handleDisasterRecovery'])->name('sip.trunk.disaster');
});

// BYOC (Bring Your Own Carrier) Trunk Management Routes (Admin Only) — Twilio-only, hidden on Plivo
Route::middleware(['auth', 'verified', 'role:admin', 'telephony.feature:byoc'])->prefix('byoc')->group(function () {
    Route::get('/', [App\Http\Controllers\ByocTrunkController::class, 'index'])->name('byoc.index');
    Route::get('/setup', [App\Http\Controllers\ByocTrunkController::class, 'setupWizard'])->name('byoc.setup');
    Route::post('/setup', [App\Http\Controllers\ByocTrunkController::class, 'setup'])->name('byoc.store');
    Route::get('/{trunk}', [App\Http\Controllers\ByocTrunkController::class, 'show'])->name('byoc.show');
    Route::delete('/{trunk}', [App\Http\Controllers\ByocTrunkController::class, 'destroy'])->name('byoc.destroy');
    
    // AJAX endpoints
    Route::get('/{trunk}/setup-status', [App\Http\Controllers\ByocTrunkController::class, 'setupStatus'])->name('byoc.setup-status');
    
    // SIP provider management
    Route::post('/{trunk}/providers', [App\Http\Controllers\ByocTrunkController::class, 'addProvider'])->name('byoc.provider.add');
    Route::put('/providers/{target}', [App\Http\Controllers\ByocTrunkController::class, 'updateProvider'])->name('byoc.provider.update');
    Route::delete('/providers/{target}', [App\Http\Controllers\ByocTrunkController::class, 'removeProvider'])->name('byoc.provider.delete');
});

// BYOC Trunk Webhook Routes (validated by Twilio signature) — Twilio-only, hidden on Plivo
// telephony.feature MUST run first — see the identical note on the SIP
// trunk webhook group above.
Route::prefix('byoc/trunk')->middleware(['telephony.feature:byoc', 'twilio.signature', 'throttle:120,1'])->group(function () {
    Route::post('/voice/{user}', [App\Http\Controllers\ByocTrunkController::class, 'voiceWebhook'])->name('byoc.trunk.voice');
});

// Provider-neutral browser softphone endpoints. Unlike routes/twilio.php's
// token routes these are not hidden on any provider: they serve whichever
// provider the installation is locked to, and read that from the lock rather
// than from the request. Session-authenticated, like the Twilio routes they
// replace, because the softphone calls them from the signed-in page.
Route::middleware(['auth', 'throttle:30,1'])->prefix('api/telephony')->group(function () {
    Route::get('/status', [App\Http\Controllers\Api\TelephonyTokenController::class, 'status'])
        ->name('telephony.status');
    Route::get('/token', [App\Http\Controllers\Api\TelephonyTokenController::class, 'token'])
        ->name('telephony.token');
});

require __DIR__.'/settings.php';
require __DIR__.'/twilio.php';
require __DIR__.'/kyc.php';
