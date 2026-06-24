<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * What: A recorded payment against an invoice (amount, method, reference, date).
 * Why: Payments are logged manually in v1 (no gateway), and the sum of an invoice's transactions drives
 *      its amount-paid / balance and the auto-Paid transition. Tenant isolation is automatic via
 *      `BelongsToCompany`.
 * When: Created from the record-payment modal on the invoice builder; read on the detail page.
 *
 * @property int $id
 * @property int $company_id
 * @property int $invoice_id
 * @property string $amount
 * @property PaymentMethod $method
 * @property string|null $reference
 * @property Carbon $paid_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use BelongsToCompany, HasFactory, LogsActivity;

    protected $fillable = [
        'invoice_id',
        'amount',
        'method',
        'reference',
        'paid_at',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'paid_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * What: Configure the spatie activity log for transactions.
     * Why: Money movement must be auditable; track the recorded fields.
     * When: Invoked automatically by the LogsActivity trait on create/update/delete.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['invoice_id', 'amount', 'method', 'reference', 'paid_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
