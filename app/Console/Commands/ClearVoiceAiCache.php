<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearVoiceAiCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'voice-ai:clear-cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear the cached Voice AI base instruction (forces reload from file)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $cacheKey = 'voice_ai_base_instruction';
        
        Cache::forget($cacheKey);
        
        $this->info('✓ Voice AI base instruction cache cleared!');
        $this->info('Next AI conversation will reload from: public/voice-ai-base-instruction.md');
        
        return Command::SUCCESS;
    }
}
