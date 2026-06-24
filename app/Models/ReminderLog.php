<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Database\Factories\ReminderLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * What: An immutable record of one reminder email actually sent for an expiring resource.
 * Why: This is both the audit trail shown in the log viewer and the dedupe ledger: the
 *      `(remindable, days_before, channel)` unique key is what makes `reminders:send` idempotent — the
 *      dispatcher skips any combination already logged. `client_id` is denormalised so the viewer can list
 *      by client without a polymorphic join. No soft-deletes and no activity log: the row IS the audit
 *      record. Tenant isolation is automatic via `BelongsToCompany`.
 * When: Written by the reminder dispatcher after each send; read by the dedupe check and the log viewer.
 *
 * @property int $id
 * @property int $company_id
 * @property int|null $reminder_rule_id
 * @property string $remindable_type
 * @property int $remindable_id
 * @property int $client_id
 * @property int $days_before
 * @property string $channel
 * @property string $recipient
 * @property CarbonImmutable $sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ReminderLog extends Model
{
    /** @use HasFactory<ReminderLogFactory> */
    use BelongsToCompany, HasFactory;

    /** Channel a reminder was sent on — the client mailbox or the company admin mailbox. */
    public const CHANNEL_CLIENT = 'client';

    public const CHANNEL_ADMIN = 'admin';

    protected $fillable = [
        'reminder_rule_id',
        'remindable_type',
        'remindable_id',
        'client_id',
        'days_before',
        'channel',
        'recipient',
        'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'days_before' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function remindable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<ReminderRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(ReminderRule::class, 'reminder_rule_id');
    }
}
