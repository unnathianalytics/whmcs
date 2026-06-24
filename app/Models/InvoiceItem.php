<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\InvoiceItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * What: A single priced line on an invoice (description, quantity, unit price, optional tax).
 * Why: Per-line-item tax (the Phase 4 decision) means each line carries its own snapshotted `tax_rate`
 *      percent, so editing/deleting a catalog rate never rewrites a historical invoice. The derived money
 *      columns (`line_subtotal`, `tax_amount`, `line_total`) are computed once on save so the header totals
 *      and the PDF read them directly. Tenant isolation is automatic via `BelongsToCompany`.
 * When: Created/edited inline on the invoice builder; read on the detail page and the PDF.
 *
 * @property int $id
 * @property int $company_id
 * @property int $invoice_id
 * @property int|null $tax_rate_id
 * @property string $description
 * @property string $quantity
 * @property string $unit_price
 * @property string $tax_rate
 * @property string $line_subtotal
 * @property string $tax_amount
 * @property string $line_total
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class InvoiceItem extends Model
{
    /** @use HasFactory<InvoiceItemFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'invoice_id',
        'tax_rate_id',
        'description',
        'quantity',
        'unit_price',
        'tax_rate',
        'line_subtotal',
        'tax_amount',
        'line_total',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'line_subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
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
     * @return BelongsTo<TaxRate, $this>
     */
    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    /**
     * What: Compute the derived money columns from quantity, unit price and tax percent.
     * Why: Centralises the line math so the builder and seeder/factory share one formula and the stored
     *      values always agree with the inputs.
     * When: Called before persisting a line item (on create and edit) by the invoice builder.
     */
    public function recalculate(): void
    {
        $subtotal = round((float) $this->quantity * (float) $this->unit_price, 2);
        $taxAmount = round($subtotal * ((float) $this->tax_rate / 100), 2);

        $this->line_subtotal = (string) $subtotal;
        $this->tax_amount = (string) $taxAmount;
        $this->line_total = (string) ($subtotal + $taxAmount);
    }
}
