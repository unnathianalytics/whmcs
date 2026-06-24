<?php

namespace App\Models;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * What: A support ticket — the thread header raised against a client.
 * Why: This is the core of Phase 5 helpdesk. The header carries the client, routing department, assigned
 *      admin, a per-company sequential `number`, the subject, and the status/priority that drive the queue
 *      filters. Replies live in `ticket_replies` (the opening message is the first reply). Tenant isolation
 *      is automatic via `BelongsToCompany`.
 * When: Created/edited from `/admin/tickets`; read on the list and the thread page.
 *
 * @property int $id
 * @property int $company_id
 * @property int $client_id
 * @property int|null $department_id
 * @property int|null $assigned_to
 * @property string $number
 * @property string $subject
 * @property TicketStatus $status
 * @property TicketPriority $priority
 * @property Carbon|null $last_reply_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use BelongsToCompany, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'client_id',
        'department_id',
        'assigned_to',
        'number',
        'subject',
        'status',
        'priority',
        'last_reply_at',
        'closed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'priority' => TicketPriority::class,
            'last_reply_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<TicketDepartment, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(TicketDepartment::class, 'department_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return HasMany<TicketReply, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class)->oldest();
    }

    /**
     * What: The next per-company ticket number (e.g. "TKT-000007").
     * Why: Numbers must be unique and sequential within a tenant. Trashed tickets are counted so a number is
     *      never reused after a soft-delete; the unique `(company_id, number)` index is the backstop.
     * When: Called when opening a new ticket on the Tickets list.
     */
    public static function nextNumber(int $companyId): string
    {
        $count = static::withoutGlobalScopes()
            ->withTrashed()
            ->where('company_id', $companyId)
            ->count();

        return 'TKT-'.str_pad((string) ($count + 1), 6, '0', STR_PAD_LEFT);
    }

    /**
     * What: Whether the ticket is still in play (anything but Closed).
     * Why: Drives the dashboard "open tickets" count (Phase 8) and gates whether the reply composer shows.
     * When: Read on the thread page and by later dashboard widgets.
     */
    public function isOpen(): bool
    {
        return $this->status !== TicketStatus::Closed;
    }

    /**
     * What: Configure the spatie activity log for tickets.
     * Why: Tickets are an auditable support record; track routing, assignment and lifecycle changes.
     * When: Invoked automatically by the LogsActivity trait on create/update/delete.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'client_id', 'department_id', 'assigned_to', 'number',
                'subject', 'status', 'priority', 'closed_at',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
