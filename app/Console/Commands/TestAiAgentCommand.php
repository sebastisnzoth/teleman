<?php

namespace App\Console\Commands;

use App\Models\AiAgent;
use App\Services\AiAgent\DeepgramService;
use App\Services\AiAgent\ConversationEngine;
use Illuminate\Console\Command;

class TestAiAgentCommand extends Command
{
    protected $signature = 'ai-agent:test {agent_id? : The ID of the AI agent to test}';
    protected $description = 'Test AI Agent system APIs and configuration';

    public function handle()
    {
        $this->info('🤖 Testing AI Agent System...');
        $this->newLine();

        // Get agent ID from argument or use first available agent
        $agentId = $this->argument('agent_id');
        
        if ($agentId) {
            $agent = AiAgent::find($agentId);
        } else {
            $agent = AiAgent::first();
        }

        if (!$agent) {
            $this->error('❌ No AI agent found. Please create an agent first.');
            return 1;
        }

        $this->info("Testing with agent: {$agent->name} (ID: {$agent->id})");
        $this->newLine();

        // Test 1: Deepgram STT
        $this->info('1️⃣  Testing Deepgram API...');
        try {
            $deepgram = app(DeepgramService::class);
            $deepgram->test();
            $this->info('   ✅ Deepgram API is working');
        } catch (\Exception $e) {
            $this->error('   ❌ Deepgram API failed: ' . $e->getMessage());
        }
        $this->newLine();

        // Test 2: OpenRouter API (Text Generation)
        $this->info('2️⃣  Testing OpenRouter API (Text Generation)...');
        try {
            $conversation = new ConversationEngine($agent);
            $conversation->test();
            $this->info('   ✅ OpenRouter API is working');
        } catch (\Exception $e) {
            $this->error('   ❌ OpenRouter API failed: ' . $e->getMessage());
        }
        $this->newLine();

        // Test 3: Database
        $this->info('3️⃣  Testing Database...');
        try {
            \DB::connection()->getPdo();
            $this->info('   ✅ Database connection is working');
            
            // Check tables exist
            $tables = ['ai_agents', 'ai_agent_calls', 'ai_agent_conversations'];
            foreach ($tables as $table) {
                if (\Schema::hasTable($table)) {
                    $count = \DB::table($table)->count();
                    $this->info("   ✅ Table '{$table}' exists ({$count} records)");
                } else {
                    $this->error("   ❌ Table '{$table}' does not exist");
                }
            }
        } catch (\Exception $e) {
            $this->error('   ❌ Database failed: ' . $e->getMessage());
        }
        $this->newLine();

        // Test 4: Configuration
        $this->info('4️⃣  Checking Configuration...');

        $configs = [
            'deepgram.api_key' => 'Deepgram API Key',
            'openrouter.api_key' => 'OpenRouter API Key',
            'ai-agent.models' => 'AI Models',
            'ai-agent.voices' => 'Voices',
        ];

        foreach ($configs as $key => $name) {
            $value = config($key);
            if ($value && (is_array($value) || strlen($value) > 10)) {
                $this->info("   ✅ {$name} configured");
            } else {
                $this->error("   ❌ {$name} missing or invalid");
            }
        }
        $this->newLine();

        // Summary
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('✨ AI Agent System Test Complete');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();
        
        $this->info('📚 Documentation: docs/AI_AGENT_CALLING_README.md');
        $this->info('🎯 Create agent: php artisan tinker');
        $this->info('   > App\Models\AiAgent::create([...])');
        $this->newLine();

        return 0;
    }
}
