<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\TicketDepartmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * What: A helpdesk routing bucket (e.g. Sales, Technical, Billing) belonging to a tenant company.
 * Why: Tickets are grouped by department so the queue can be filtered and routed. Departments are
 *      per-company and soft-deletable; an inactive department stays valid on existing tickets but is hidden
 *      from the create form. Tenant isolation is automatic via `BelongsToCompany`.
 * When: Managed by company admins from `/admin/ticket-departments`; selected when opening a ticket.
 *
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TicketDepartment extends Model
{
    /** @use HasFactory<TicketDepartmentFactory> */
    use BelongsToCompany, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'department_id');
    }

    /**
     * What: Configure the spatie activity log for ticket departments.
     * Why: Department changes affect helpdesk routing; track the editable fields only.
     * When: Invoked automatically by the LogsActivity trait on create/update/delete.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
