<?php

namespace App\Console\Commands;

use App\Models\Call;
use App\Models\Campaign;
use App\Models\TwilioCredential;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SystemStatus extends Command
{
    protected $signature = 'system:status';
    protected $description = 'Show complete system status for testing';

    public function handle(): int
    {
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('   🎯 TELEMAN AI - SYSTEM STATUS');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->newLine();

        // Twilio Configuration
        $this->info('📞 TWILIO CONFIGURATION');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        
        $credential = TwilioCredential::where('user_id', 1)->first();
        if ($credential) {
            $this->line("  ✓ Account SID:     {$credential->account_sid}");
            $this->line("  ✓ Phone Number:    {$credential->phone_number}");
            $this->line("  ✓ TwiML App SID:   " . ($credential->twiml_app_sid ?? 'Not created'));
            $this->line("  ✓ Status:          " . ($credential->is_active ? 'Active' : 'Inactive'));
            $this->line("  ✓ Verified:        " . ($credential->verified_at ? 'Yes' : 'No'));
        } else {
            $this->error("  ✗ No credentials configured");
        }
        $this->newLine();

        // Webhook Configuration
        $this->info('🌐 WEBHOOK CONFIGURATION');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $webhookUrl = env('TWILIO_WEBHOOK_URL', config('app.url'));
        $this->line("  ✓ Webhook URL:     {$webhookUrl}");
        $this->line("  ✓ TwiML Endpoint:  {$webhookUrl}/twiml/manual-call");
        $this->newLine();

        // User Status
        $this->info('👤 USER STATUS');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $user = User::find(1);
        if ($user) {
            $this->line("  ✓ Email:           {$user->email}");
            $this->line("  ✓ Twilio Setup:    " . ($user->hasTwilioConfigured() ? 'YES' : 'NO'));
            $this->line("  ✓ 2FA Enabled:     " . ($user->two_factor_secret ? 'Yes' : 'No'));
        }
        $this->newLine();

        // Campaign Status
        $this->info('📢 CAMPAIGNS');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $campaigns = Campaign::withCount('campaignContacts')->latest()->take(3)->get();
        if ($campaigns->count() > 0) {
            foreach ($campaigns as $campaign) {
                $this->line("  #{$campaign->id}: {$campaign->name}");
                $this->line("    Status: {$campaign->status} | Type: {$campaign->type} | Contacts: {$campaign->campaign_contacts_count}");
            }
        } else {
            $this->line("  No campaigns created yet");
        }
        $this->newLine();

        // Call History
        $this->info('📞 RECENT CALLS');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $calls = Call::latest()->take(5)->get();
        if ($calls->count() > 0) {
            foreach ($calls as $call) {
                $duration = $call->duration ? $call->duration . 's' : '-';
                $this->line("  {$call->to_number} | {$call->status} | {$duration} | " . $call->created_at->diffForHumans());
            }
        } else {
            $this->line("  No calls yet - Time to make your first call!");
        }
        $this->newLine();

        // Queue Status
        $this->info('⚙️  QUEUE STATUS');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $pendingJobs = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();
        
        $this->line("  Pending Jobs:     {$pendingJobs}");
        $this->line("  Failed Jobs:      {$failedJobs}");
        
        if ($failedJobs > 0) {
            $this->warn("  ⚠️  You have failed jobs. Run: php artisan queue:retry all");
        }
        $this->newLine();

        // System Health
        $this->info('💚 SYSTEM HEALTH');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        
        $checks = [
            'Database' => $this->checkDatabase(),
            'Twilio Credentials' => $credential && $credential->is_active,
            'Queue Connection' => config('queue.default') === 'database',
            'Webhook URL Set' => !empty(env('TWILIO_WEBHOOK_URL')),
        ];

        foreach ($checks as $check => $status) {
            $icon = $status ? '✓' : '✗';
            $color = $status ? 'info' : 'error';
            $this->line("  {$icon} {$check}", $color);
        }
        $this->newLine();

        // Testing URLs
        $this->info('🔗 TESTING URLS');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $baseUrl = config('app.url');
        $this->line("  Dashboard:        {$baseUrl}/dashboard");
        $this->line("  Softphone:        {$baseUrl}/calls/softphone");
        $this->line("  Call History:     {$baseUrl}/calls");
        $this->line("  Campaigns:        {$baseUrl}/campaigns");
        $this->line("  Twilio Setup:     {$baseUrl}/twilio");
        $this->newLine();

        // Quick Commands
        $this->info('⚡ QUICK COMMANDS');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line("  Test Token:       php artisan twilio:test-token 1");
        $this->line("  Test Connection:  php artisan twilio:test");
        $this->line("  Start Queue:      php artisan queue:work");
        $this->line("  Monitor Queue:    php artisan queue:monitor");
        $this->line("  View Logs:        Get-Content storage\\logs\\laravel.log -Tail 50");
        $this->newLine();

        $this->info('═══════════════════════════════════════════════════════════');
        $this->line('  All systems operational! Ready for testing 🚀');
        $this->info('═══════════════════════════════════════════════════════════');

        return 0;
    }

    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
