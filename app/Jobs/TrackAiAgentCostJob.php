<?php

namespace App\Jobs;

use App\Models\AiAgentCall;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TrackAiAgentCostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        private int $callId,
        private array $usage,
        private ?string $model = null
    ) {}

    public function handle(): void
    {
        $call = AiAgentCall::find($this->callId);

        if (!$call) {
            return;
        }

        $inputTokens = $this->usage['prompt_tokens'] ?? 0;
        $outputTokens = $this->usage['completion_tokens'] ?? 0;
        $totalTokens = $this->usage['total_tokens'] ?? ($inputTokens + $outputTokens);

        // OpenRouter pricing — look up from config, default to $0 (most models are free)
        $modelPricing = config('openrouter.model_pricing', []);
        $modelKey = strtolower($this->model ?? '');
        $inputCostPer1K = $modelPricing[$modelKey]['input'] ?? 0.0;
        $outputCostPer1K = $modelPricing[$modelKey]['output'] ?? 0.0;

        $turnCost = ($inputTokens / 1000 * $inputCostPer1K) + ($outputTokens / 1000 * $outputCostPer1K);

        $call->increment('input_tokens', $inputTokens);
        $call->increment('output_tokens', $outputTokens);
        $call->increment('total_tokens', $totalTokens);

        $newOpenAICost = (float) $call->openai_cost + $turnCost;
        $call->updateCostBreakdown(openaiCost: $newOpenAICost, tokens: (int) $call->total_tokens);

        Log::debug('Tracked AI agent cost (queued)', [
            'call_id' => $call->id,
            'model' => $this->model,
            'turn_cost' => round($turnCost, 6),
        ]);
    }
}
