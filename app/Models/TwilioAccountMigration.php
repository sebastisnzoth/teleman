<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TwilioAccountMigration extends Model
{
    protected $fillable = [
        'old_account_sid',
        'new_account_sid',
        'performed_by',
        'numbers_matched',
        'numbers_deactivated',
        'numbers_new',
        'campaigns_paused',
        'ai_agents_reset',
        'calls_marked_legacy',
        'sms_numbers_deactivated',
        'details',
    ];

    protected $casts = [
        'details' => 'array',
        'numbers_matched' => 'integer',
        'numbers_deactivated' => 'integer',
        'numbers_new' => 'integer',
        'campaigns_paused' => 'integer',
        'ai_agents_reset' => 'integer',
        'calls_marked_legacy' => 'integer',
        'sms_numbers_deactivated' => 'integer',
    ];

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
