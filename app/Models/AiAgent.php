<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class AiAgent extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'phone_number',
        'knowledge_base_id',
        'twilio_auto_configured',
        'twilio_webhook_sid',
        'description',
        'type',
        'system_prompt',
        'first_message',
        'goodbye_message',
        'knowledge_base',
        'model',
        'voice',
        'max_tokens',
        'temperature',
        'active',
        'settings',
        'enable_transfer',
        'transfer_number',
        'enable_recording',
        'max_duration',
        'silence_timeout',
        'response_timeout',
        'trigger_keywords',
    ];

    protected $casts = [
        'active' => 'boolean',
        'twilio_auto_configured' => 'boolean',
        'enable_transfer' => 'boolean',
        'enable_recording' => 'boolean',
        'max_tokens' => 'integer',
        'max_duration' => 'integer',
        'silence_timeout' => 'integer',
        'response_timeout' => 'integer',
        'temperature' => 'decimal:2',
        'settings' => 'array',
        'trigger_keywords' => 'array',
    ];

    /**
     * Get the user who owns this agent
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the selected knowledge base (optional)
     */
    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBase::class);
    }

    /**
     * Get effective KB content: from selected KB or inline knowledge_base text
     */
    public function getEffectiveKnowledgeBaseContent(): ?string
    {
        if ($this->knowledge_base_id && $this->relationLoaded('knowledgeBase') && $this->knowledgeBase) {
            return trim((string) $this->knowledgeBase->content) ?: null;
        }

        return ! empty(trim((string) $this->knowledge_base)) ? trim((string) $this->knowledge_base) : null;
    }

    /**
     * Get all calls for this agent
     */
    public function calls(): HasMany
    {
        return $this->hasMany(AiAgentCall::class);
    }

    /**
     * Get active calls for this agent
     */
    public function activeCalls(): HasMany
    {
        return $this->hasMany(AiAgentCall::class)
            ->whereIn('status', ['initiated', 'ringing', 'in-progress']);
    }

    /**
     * Get completed calls for this agent
     */
    public function completedCalls(): HasMany
    {
        return $this->hasMany(AiAgentCall::class)
            ->where('status', 'completed');
    }

    /**
     * Get campaigns using this agent
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    /**
     * Scope for active agents
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * The active agent answering on a phone number, or null.
     *
     * This is the one place that answers that question. It used to be asked
     * ad hoc — `AiAgent::where('phone_number', $number)` — in the two places
     * that attach an agent to a campaign, while the campaign screen asked a
     * different question entirely (`phone_numbers.ai_agent_id`, a column
     * nothing in the application ever writes). The screen therefore showed
     * no agent on any number while the save attached one, so the choice was
     * made blind.
     *
     * The match is on the number's digits, not its spelling: a number is
     * stored as `+15551234567` while an agent's is typed by hand, so
     * `15551234567` and `+1 555 123 4567` are the same number to everyone
     * except a string comparison — and a silent miss here is a campaign
     * that quietly runs without the agent it was meant to use.
     *
     * A number written without its country code is NOT matched to one that
     * has it: `555-123-4567` could belong to any country, and guessing is
     * how a campaign ends up on the wrong agent. The campaign screen shows
     * which agent a number has, so a mismatch is visible and fixable rather
     * than silent.
     */
    public static function servingNumber(?string $number): ?self
    {
        $digits = self::digitsOf($number);

        if ($digits === '') {
            return null;
        }

        return self::activeByNumber()->get($digits);
    }

    /**
     * Every active agent that answers on a number, keyed by that number's
     * digits.
     *
     * Built once and reused: the campaign screen asks this for each of a
     * customer's numbers, and an installation with sixty of them should not
     * make sixty passes over the same small table.
     *
     * @return Collection<string, self>
     */
    public static function activeByNumber(): Collection
    {
        return once(fn () => static::query()
            ->active()
            ->whereNotNull('phone_number')
            ->where('phone_number', '!=', '')
            ->get()
            ->keyBy(fn (self $agent) => self::digitsOf($agent->phone_number))
            ->forget('')
        );
    }

    /**
     * A number reduced to the digits it is made of, so that the separators
     * someone typed cannot decide whether two numbers are the same.
     */
    private static function digitsOf(?string $number): string
    {
        return (string) preg_replace('/\D+/', '', (string) $number);
    }

    /**
     * Scope for inbound agents
     */
    public function scopeInbound($query)
    {
        return $query->where('type', 'inbound');
    }

    /**
     * Scope for outbound agents
     */
    public function scopeOutbound($query)
    {
        return $query->where('type', 'outbound');
    }

    /**
     * Get agent statistics
     */
    public function getStats(): array
    {
        $totalCalls = $this->calls()->count();
        $completedCalls = $this->completedCalls()->count();
        $averageDuration = $this->completedCalls()->avg('duration');
        $totalCost = $this->calls()->sum('cost_estimate');

        return [
            'total_calls' => $totalCalls,
            'completed_calls' => $completedCalls,
            'success_rate' => $totalCalls > 0 ? round(($completedCalls / $totalCalls) * 100, 2) : 0,
            'average_duration' => round($averageDuration ?? 0, 2),
            'total_cost' => round($totalCost, 4),
        ];
    }

    /**
     * Check if agent should transfer based on keywords
     */
    public function shouldTransfer(string $userMessage): bool
    {
        if (! $this->enable_transfer || empty($this->trigger_keywords ?? [])) {
            return false;
        }

        $message = strtolower($userMessage);
        foreach ($this->trigger_keywords ?? [] as $keyword) {
            if (str_contains($message, strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }
}
