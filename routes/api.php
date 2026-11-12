<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CampaignController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\CallController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\Admin\MessageVariantController;
use App\Http\Controllers\Api\Admin\ContactQualityController;
use App\Http\Controllers\Api\SentimentAnalysisController;
use App\Http\Controllers\Api\SmartSchedulingController;
use App\Http\Controllers\Api\SequenceController;
use App\Http\Controllers\Api\V1\CrmIntegrationController;
use App\Http\Controllers\Api\AiAgentController;
use App\Http\Controllers\Api\AiAgentCallController;
use App\Http\Controllers\Api\KnowledgeBaseController as ApiKnowledgeBaseController;
use App\Http\Controllers\Api\WidgetController;
use App\Http\Controllers\Api\PricingCalculatorController;
use App\Http\Middleware\ThrottleApiRequests;

/*
|--------------------------------------------------------------------------
| API Routes - Version 1
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// API Version 1
Route::prefix('v1')->group(function () {
    
    // Public routes (no authentication required) - with strict rate limiting
    Route::middleware(ThrottleApiRequests::class . ':auth')->group(function () {
        Route::post('/auth/login', [AuthController::class, 'login']);
        Route::post('/auth/register', [AuthController::class, 'register']);
    });
    
    // Protected routes (require API token + rate limiting)
    Route::middleware(['auth:sanctum', ThrottleApiRequests::class . ':high'])->group(function () {
        
        // Authentication & User Management
        Route::prefix('auth')->group(function () {
            Route::get('/user', [AuthController::class, 'user']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/tokens', [AuthController::class, 'createToken']);
            Route::get('/tokens', [AuthController::class, 'listTokens']);
            Route::delete('/tokens/{tokenId}', [AuthController::class, 'revokeToken']);
        });
        
        // Campaigns API
        Route::prefix('campaigns')->group(function () {
            Route::get('/', [CampaignController::class, 'index']);
            Route::post('/', [CampaignController::class, 'store']);
            Route::get('/{campaign}', [CampaignController::class, 'show']);
            Route::put('/{campaign}', [CampaignController::class, 'update']);
            Route::delete('/{campaign}', [CampaignController::class, 'destroy']);
            
            // Campaign actions
            Route::post('/{campaign}/start', [CampaignController::class, 'start']);
            Route::post('/{campaign}/pause', [CampaignController::class, 'pause']);
            Route::post('/{campaign}/resume', [CampaignController::class, 'resume']);
            Route::post('/{campaign}/stop', [CampaignController::class, 'stop']);
            
            // Campaign statistics
            Route::get('/{campaign}/stats', [CampaignController::class, 'stats']);
            Route::get('/{campaign}/contacts', [CampaignController::class, 'contacts']);
            Route::get('/{campaign}/calls', [CampaignController::class, 'calls']);
            
            // Message Variants (AI A/B Testing)
            Route::post('/{campaign}/variants/generate', [MessageVariantController::class, 'generateVariants']);
            Route::get('/{campaign}/variants', [MessageVariantController::class, 'getVariants']);
            Route::post('/{campaign}/variants/{variant}/select-winner', [MessageVariantController::class, 'selectWinner']);
            Route::delete('/{campaign}/variants', [MessageVariantController::class, 'deleteVariants']);
            Route::patch('/{campaign}/variants/{variant}/toggle-active', [MessageVariantController::class, 'toggleActive']);
        });
        
        // Contacts API
        Route::prefix('contacts')->group(function () {
            Route::get('/', [ContactController::class, 'index']);
            Route::post('/', [ContactController::class, 'store']);
            Route::get('/{contact}', [ContactController::class, 'show']);
            Route::put('/{contact}', [ContactController::class, 'update']);
            Route::delete('/{contact}', [ContactController::class, 'destroy']);
            
            // Bulk operations
            Route::post('/bulk', [ContactController::class, 'bulkCreate']);
            Route::put('/bulk', [ContactController::class, 'bulkUpdate']);
            Route::delete('/bulk', [ContactController::class, 'bulkDelete']);
            
            // Export/Import
            Route::get('/export/csv', [ContactController::class, 'exportCsv']);
            Route::post('/import/csv', [ContactController::class, 'importCsv']);
            
            // Contact Quality (AI Data Cleaning)
            Route::post('/quality/preview', [ContactQualityController::class, 'previewQuality']);
            Route::post('/quality/batch-clean', [ContactQualityController::class, 'cleanBatch']);
            Route::get('/quality/statistics', [ContactQualityController::class, 'getStatistics']);
            Route::get('/{contact}/quality/check', [ContactQualityController::class, 'checkContact']);
            Route::post('/{contact}/quality/apply-suggestions', [ContactQualityController::class, 'applySuggestions']);
            Route::get('/{contact}/quality/duplicates', [ContactQualityController::class, 'findDuplicates']);
        });
        
        // Calls API (read-only)
        Route::prefix('calls')->group(function () {
            Route::get('/', [CallController::class, 'index']);
            Route::get('/{call}', [CallController::class, 'show']);
            Route::get('/{call}/recording', [CallController::class, 'recording']);
            
            // AI Sentiment Analysis
            Route::post('/{call}/analyze-sentiment', [SentimentAnalysisController::class, 'analyzeCall']);
        });
        
        // Hot Leads & Sentiment Analysis
        Route::get('/hot-leads', [SentimentAnalysisController::class, 'getAllHotLeads']);
        
        // Campaign-specific sentiment routes
        Route::prefix('campaigns/{campaign}')->group(function () {
            Route::post('/analyze-sentiment', [SentimentAnalysisController::class, 'analyzeCampaign']);
            Route::get('/hot-leads', [SentimentAnalysisController::class, 'getHotLeads']);
            Route::get('/sentiment-stats', [SentimentAnalysisController::class, 'getSentimentStats']);
            Route::get('/intent-analysis', [SentimentAnalysisController::class, 'getIntentAnalysis']);
            Route::get('/sentiment-trends', [SentimentAnalysisController::class, 'getSentimentTrends']);
            
            // Smart Scheduling routes
            Route::get('/optimal-times', [SmartSchedulingController::class, 'getCampaignOptimalTimes']);
            Route::post('/optimize-schedule', [SmartSchedulingController::class, 'optimizeCampaignSchedule']);
            Route::get('/timezone-schedule', [SmartSchedulingController::class, 'getTimezoneSchedule']);
            Route::post('/enable-smart-scheduling', [SmartSchedulingController::class, 'enableSmartScheduling']);
            Route::post('/disable-smart-scheduling', [SmartSchedulingController::class, 'disableSmartScheduling']);
        });
        
        // Contact-specific scheduling routes
        Route::get('/contacts/{contact}/optimal-time', [SmartSchedulingController::class, 'getContactOptimalTime']);
        Route::post('/contacts/batch-optimize', [SmartSchedulingController::class, 'batchOptimizeContacts']);
        
        // Answer rate analytics
        Route::get('/analytics/answer-rate-patterns', [SmartSchedulingController::class, 'getAnswerRatePatterns']);
        
        // Follow-Up Sequences API (Automated Follow-Ups)
        Route::prefix('sequences')->group(function () {
            Route::get('/', [SequenceController::class, 'index']);
            Route::post('/', [SequenceController::class, 'store']);
            Route::get('/{sequence}', [SequenceController::class, 'show']);
            Route::put('/{sequence}', [SequenceController::class, 'update']);
            Route::delete('/{sequence}', [SequenceController::class, 'destroy']);
            
            // Sequence actions
            Route::post('/{sequence}/activate', [SequenceController::class, 'activate']);
            Route::post('/{sequence}/deactivate', [SequenceController::class, 'deactivate']);
            
            // Sequence enrollments & analytics
            Route::get('/{sequence}/enrollments', [SequenceController::class, 'enrollments']);
            Route::get('/{sequence}/analytics', [SequenceController::class, 'analytics']);
        });
        
        // Contact enrollment management
        Route::post('/contacts/{contact}/enroll', [SequenceController::class, 'enrollContact']);
        Route::post('/enrollments/{enrollment}/pause', [SequenceController::class, 'pauseEnrollment']);
        Route::post('/enrollments/{enrollment}/resume', [SequenceController::class, 'resumeEnrollment']);
        Route::post('/enrollments/{enrollment}/stop', [SequenceController::class, 'stopEnrollment']);
        
        // CRM Integrations API
        Route::prefix('crm-integrations')->group(function () {
            Route::get('/', [CrmIntegrationController::class, 'index']);
            Route::post('/', [CrmIntegrationController::class, 'store']);
            Route::get('/stats', [CrmIntegrationController::class, 'stats']);
            Route::get('/logs', [CrmIntegrationController::class, 'allLogs']);
            Route::get('/{id}', [CrmIntegrationController::class, 'show']);
            Route::put('/{id}', [CrmIntegrationController::class, 'update']);
            Route::delete('/{id}', [CrmIntegrationController::class, 'destroy']);
            Route::post('/{id}/test', [CrmIntegrationController::class, 'test']);
            Route::get('/{id}/logs', [CrmIntegrationController::class, 'logs']);
            Route::post('/logs/{logId}/retry', [CrmIntegrationController::class, 'retry']);
        });
        
        // Users API (admin only)
        Route::middleware('role:admin')->prefix('users')->group(function () {
            Route::get('/', [UserController::class, 'index']);
            Route::post('/', [UserController::class, 'store']);
            Route::get('/{user}', [UserController::class, 'show']);
            Route::put('/{user}', [UserController::class, 'update']);
            Route::delete('/{user}', [UserController::class, 'destroy']);
        });
        
        // Analytics API
        Route::prefix('analytics')->group(function () {
            Route::get('/overview', [AnalyticsController::class, 'overview']);
            Route::get('/campaigns', [AnalyticsController::class, 'campaigns']);
            Route::get('/calls', [AnalyticsController::class, 'calls']);
        });
        
        // Knowledge Base API (for Conversational AI agents)
        Route::prefix('knowledge-bases')->group(function () {
            Route::get('/', [ApiKnowledgeBaseController::class, 'index']);
            Route::post('/', [ApiKnowledgeBaseController::class, 'store']);
            Route::post('/fetch-url', [ApiKnowledgeBaseController::class, 'fetchUrl']);
            Route::get('/{id}', [ApiKnowledgeBaseController::class, 'show']);
            Route::put('/{id}', [ApiKnowledgeBaseController::class, 'update']);
            Route::patch('/{id}', [ApiKnowledgeBaseController::class, 'update']);
            Route::delete('/{id}', [ApiKnowledgeBaseController::class, 'destroy']);
        });

        // AI Agent API
        Route::prefix('ai-agents')->group(function () {
            Route::get('/', [AiAgentController::class, 'index']);
            Route::post('/', [AiAgentController::class, 'store']);
            Route::get('/models', [AiAgentController::class, 'availableModels']);
            Route::get('/voices', [AiAgentController::class, 'availableVoices']);
            Route::get('/templates', [AiAgentController::class, 'availableTemplates']);
            Route::get('/{id}', [AiAgentController::class, 'show']);
            Route::get('/{id}/calls', [AiAgentController::class, 'calls']);
            Route::put('/{id}', [AiAgentController::class, 'update']);
            Route::patch('/{id}', [AiAgentController::class, 'update']);
            Route::delete('/{id}', [AiAgentController::class, 'destroy']);
            Route::post('/{id}/archive', [AiAgentController::class, 'archive']);
            Route::post('/{id}/restore', [AiAgentController::class, 'restore']);
            Route::post('/{id}/test', [AiAgentController::class, 'test']);
            // telephony.feature gate: this writes per-number voice/status
            // URLs through the raw Twilio SDK, an operation a Plivo number
            // has no equivalent of — see config('telephony.hidden_features').
            Route::post('/{id}/configure-webhooks', [AiAgentController::class, 'configureWebhooks'])
                ->middleware('telephony.feature:ai_agent_number_webhooks');
            Route::get('/{id}/test-configuration', [AiAgentController::class, 'testConfiguration']);
        });

        // AI Agent Calls API
        Route::prefix('ai-agent-calls')->group(function () {
            Route::get('/', [AiAgentCallController::class, 'index']);
            Route::get('/export', [AiAgentCallController::class, 'export']);
            Route::get('/active', [AiAgentCallController::class, 'active']);
            Route::get('/stats', [AiAgentCallController::class, 'stats']);
            // telephony.feature gate: the call itself is still placed with a
            // raw Twilio client, so on any other provider this answers with a
            // configuration error naming a brand the buyer never chose and
            // cannot configure — see config('telephony.hidden_features').
            Route::post('/initiate', [AiAgentCallController::class, 'initiate'])
                ->middleware('telephony.feature:ai_agent_outbound_call');
            Route::get('/{id}', [AiAgentCallController::class, 'show']);
            Route::post('/{id}/stop', [AiAgentCallController::class, 'stop']);
            Route::post('/{id}/sync', [AiAgentCallController::class, 'sync']);
            Route::get('/{id}/transcript', [AiAgentCallController::class, 'transcript']);
            Route::get('/{id}/recording', [AiAgentCallController::class, 'recording']);
        });
        
        // Pricing Calculator API (Customer - filtered response)
        Route::middleware('kyc:basic')->prefix('pricing')->group(function () {
            Route::get('/calculator', [PricingCalculatorController::class, 'calculate']);
            Route::get('/countries', [PricingCalculatorController::class, 'getCountries']);
        });
        
        // Admin Pricing Calculator API (Full details with profit margins)
        Route::middleware('role:admin')->prefix('admin/pricing')->group(function () {
            Route::get('/calculator', [PricingCalculatorController::class, 'calculateAdmin']);
        });
        
        // System health check
        Route::get('/health', function () {
            return response()->json([
                'status' => 'healthy',
                'timestamp' => now()->toIso8601String(),
                'version' => '1.0.0',
            ]);
        });
    });
});

// Widget API Routes (Public - API key authentication, rate limited)
Route::prefix('widget')->name('widget.')->middleware('throttle:30,1')->group(function () {
    Route::post('/validate', [WidgetController::class, 'validate'])->name('validate');
    Route::post('/token', [WidgetController::class, 'getToken'])->name('token');
    Route::post('/phone-numbers', [WidgetController::class, 'getPhoneNumbers'])->name('phone-numbers');
    Route::post('/call/initiate', [WidgetController::class, 'initiateCall'])->middleware('throttle:10,1')->name('call.initiate');
    Route::post('/call/{callId}/end', [WidgetController::class, 'endCall'])->name('call.end');
});

// Plivo SMS Webhook — mirrors the {provider} group below; reaches the
// same SmsWebhookController methods, just signed with
// X-Plivo-Signature-V3 and with `provider` defaulted rather than read
// from the URL. Registered BEFORE the {provider} wildcard group: Laravel
// matches routes in registration order, and /sms/webhook/plivo/incoming
// matches that wildcard's pattern too (with provider=plivo) — if that
// group were registered first it would shadow this one and hand every
// Plivo request to 'twilio.signature' instead, which only skips its own
// check in the testing environment and would otherwise 403 or, worse,
// silently let an unsigned request through in a real deployment because
// the wrong middleware never even considered rejecting it.
//
// The status/delivery-receipt route now exists alongside /incoming: it
// used to be deliberately withheld because it bills off a real
// per-message segment count that only the SMS provider classes could get
// right, and PlivoSMSProvider::send() now both requests this callback
// and reports that real count. See handleStatus()'s own docblock for how
// it maps Plivo's field names.
Route::prefix('sms/webhook/plivo')->name('sms.webhook.plivo.')->middleware(['plivo.signature', 'throttle:120,1'])->group(function () {
    Route::post('/incoming', [App\Http\Controllers\SMS\SmsWebhookController::class, 'handleIncoming'])->name('incoming')->defaults('provider', 'plivo');
    Route::post('/status', [App\Http\Controllers\SMS\SmsWebhookController::class, 'handleStatus'])->name('status')->defaults('provider', 'plivo');
});

// SMS Webhooks (validated by Twilio signature + rate limited)
//
// {provider} above is not actually provider-agnostic: 'twilio.signature'
// is hardcoded on the whole group regardless of what value the segment
// holds. See the Plivo group above (registered first on purpose) for why
// Plivo cannot simply post to /sms/webhook/plivo/incoming under this
// pattern.
Route::prefix('sms/webhook')->name('sms.webhook.')->middleware(['twilio.signature', 'throttle:120,1'])->group(function () {
    Route::post('/{provider}/incoming', [App\Http\Controllers\SMS\SmsWebhookController::class, 'handleIncoming'])->name('incoming');
    Route::post('/{provider}/status', [App\Http\Controllers\SMS\SmsWebhookController::class, 'handleStatus'])->name('status');
});
