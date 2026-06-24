<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * What: A billing document raised against a client — the header plus cached money totals.
 * Why: This is the core of Phase 4 billing. Line items live in `invoice_items` and payments in
 *      `transactions`; the header caches `subtotal`/`tax_total`/`total` (recalculated from the items) so
 *      lists and reports never re-sum on the fly. Balance and paid state are derived from transactions.
 *      Tenant isolation is automatic via `BelongsToCompany`.
 * When: Created/edited from `/admin/invoices`; read on the list, the detail/builder page and the PDF.
 *
 * @property int $id
 * @property int $company_id
 * @property int $client_id
 * @property string $number
 * @property InvoiceStatus $status
 * @property Carbon $issue_date
 * @property Carbon $due_date
 * @property string $currency
 * @property string $subtotal
 * @property string $tax_total
 * @property string $total
 * @property Carbon|null $paid_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use BelongsToCompany, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'client_id',
        'number',
        'status',
        'issue_date',
        'due_date',
        'currency',
        'subtotal',
        'tax_total',
        'total',
        'paid_at',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'issue_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_at' => 'datetime',
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
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class)->latest('paid_at');
    }

    /**
     * What: The next per-company invoice number (e.g. "INV-000007").
     * Why: Numbers must be unique and sequential within a tenant. Trashed invoices are counted so a number
     *      is never reused after a soft-delete; the unique `(company_id, number)` index is the backstop.
     * When: Called when creating a new invoice on the Invoices list.
     */
    public static function nextNumber(int $companyId): string
    {
        $count = static::withoutGlobalScopes()
            ->withTrashed()
            ->where('company_id', $companyId)
            ->count();

        return 'INV-'.str_pad((string) ($count + 1), 6, '0', STR_PAD_LEFT);
    }

    /**
     * What: Recompute and persist the cached money totals from the current line items.
     * Why: Totals are stored on the header for cheap reads, so any line add/edit/remove must refresh them.
     * When: Called by the invoice builder after every line-item mutation.
     */
    public function recalculateTotals(): void
    {
        $items = $this->items()->get();

        $this->subtotal = (string) $items->sum(fn (InvoiceItem $item): float => (float) $item->line_subtotal);
        $this->tax_total = (string) $items->sum(fn (InvoiceItem $item): float => (float) $item->tax_amount);
        $this->total = (string) ($this->subtotal + $this->tax_total);
        $this->save();
    }

    /**
     * What: Total amount paid across all recorded transactions.
     * Why: Drives the outstanding balance and the auto-Paid transition.
     * When: Read on the invoice detail page and after recording/deleting a payment.
     */
    public function amountPaid(): float
    {
        return (float) $this->transactions()->sum('amount');
    }

    /**
     * What: The outstanding balance (total minus amount paid).
     * Why: The headline figure admins act on; a balance of zero on a positive invoice means it is settled.
     * When: Shown on the detail page and used by `isPaid()` and the payment flow.
     */
    public function balance(): float
    {
        return (float) $this->total - $this->amountPaid();
    }

    /**
     * What: Whether the invoice is fully settled.
     * Why: A positive invoice with no remaining balance is Paid; an empty (zero-total) invoice is not.
     * When: Used when recording a payment to decide the auto status transition.
     */
    public function isPaid(): bool
    {
        return (float) $this->total > 0 && $this->balance() <= 0;
    }

    /**
     * What: Whether an unpaid invoice's due date has passed.
     * Why: Surfaces overdue invoices on the list and dashboard without storing a derived flag.
     * When: Read when rendering status and (Phase 7) by reminder scans.
     */
    public function isOverdue(): bool
    {
        return $this->status === InvoiceStatus::Unpaid
            && $this->due_date->isPast()
            && ! $this->isPaid();
    }

    /**
     * What: Configure the spatie activity log for invoices.
     * Why: Invoices are the most audit-sensitive billing records; track status, dates and totals.
     * When: Invoked automatically by the LogsActivity trait on create/update/delete.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'client_id', 'number', 'status', 'issue_date', 'due_date',
                'currency', 'subtotal', 'tax_total', 'total', 'paid_at',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
