<?php

namespace App\Livewire\Admin\Invoices;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\TaxRate;
use App\Models\Transaction;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * What: The invoice builder/detail page — edit the header, manage line items, and record payments.
 * Why: Invoices carry line items and payments, so (unlike simple CRUD) they get a dedicated page rather
 *      than a modal. Each line snapshots its tax percent (per-line tax decision) and the header totals are
 *      recalculated after every mutation. Recording a payment that clears the balance auto-flips the
 *      invoice to Paid. Tenant isolation is automatic: route-model binding resolves only same-company
 *      invoices via the BelongsToCompany global scope (404 otherwise).
 * When: Rendered at `/admin/invoices/{invoice}` for company admins holding `invoices.view`.
 */
#[Title('Invoice')]
class Show extends Component
{
    public Invoice $invoice;

    // --- Header edit modal ---
    public bool $showHeaderModal = false;

    public string $status = '';

    public string $issueDate = '';

    public string $dueDate = '';

    public string $notes = '';

    // --- Line item modal ---
    public bool $showItemModal = false;

    public ?int $editingItemId = null;

    public string $itemDescription = '';

    public string $itemQuantity = '1';

    public string $itemUnitPrice = '0';

    public string $itemTaxRateId = '';

    // --- Payment modal ---
    public bool $showPaymentModal = false;

    public string $paymentAmount = '0';

    public string $paymentMethod = PaymentMethod::BankTransfer->value;

    public string $paymentReference = '';

    public string $paymentPaidAt = '';

    public string $paymentNotes = '';

    // --- Delete modals ---
    public bool $showDeleteItemModal = false;

    public ?int $deletingItemId = null;

    public bool $showDeletePaymentModal = false;

    public ?int $deletingPaymentId = null;

    /**
     * What: Bind the invoice and authorize viewing it.
     * Why: The page is gated on `invoices.view`; binding is already tenant-scoped by the global scope.
     * When: On component mount.
     */
    public function mount(Invoice $invoice): void
    {
        $this->authorize('view', $invoice);
        $this->invoice = $invoice;
    }

    // =========================================================================
    // Header
    // =========================================================================

    public function openHeaderModal(): void
    {
        $this->authorize('update', $this->invoice);

        $this->status = $this->invoice->status->value;
        $this->issueDate = $this->invoice->issue_date->toDateString();
        $this->dueDate = $this->invoice->due_date->toDateString();
        $this->notes = (string) $this->invoice->notes;

        $this->resetValidation();
        $this->showHeaderModal = true;
    }

    /**
     * What: Persist the editable header fields (status, dates, notes).
     * Why: Lets admins correct dates, add notes, or move the invoice through its lifecycle by hand.
     * When: Triggered on submit of the header modal.
     */
    public function saveHeader(): void
    {
        $this->authorize('update', $this->invoice);

        $validated = $this->validate([
            'status' => ['required', Rule::enum(InvoiceStatus::class)],
            'issueDate' => ['required', 'date'],
            'dueDate' => ['required', 'date', 'after_or_equal:issueDate'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->invoice->update([
            'status' => $validated['status'],
            'issue_date' => $validated['issueDate'],
            'due_date' => $validated['dueDate'],
            'notes' => $validated['notes'] ?: null,
            // Clear the paid timestamp if the admin moves it away from Paid.
            'paid_at' => $validated['status'] === InvoiceStatus::Paid->value ? ($this->invoice->paid_at ?? now()) : null,
        ]);

        $this->invoice->refresh();
        $this->showHeaderModal = false;

        Flux::toast(variant: 'success', text: __('Invoice updated.'));
    }

    // =========================================================================
    // Line items
    // =========================================================================

    public function openCreateItemModal(): void
    {
        $this->authorize('update', $this->invoice);
        $this->resetItemForm();
        $this->showItemModal = true;
    }

    public function openEditItemModal(int $itemId): void
    {
        $this->authorize('update', $this->invoice);
        $item = $this->invoice->items()->findOrFail($itemId);

        $this->editingItemId = $item->id;
        $this->itemDescription = $item->description;
        $this->itemQuantity = (string) $item->quantity;
        $this->itemUnitPrice = (string) $item->unit_price;
        $this->itemTaxRateId = (string) ($item->tax_rate_id ?? '');

        $this->resetValidation();
        $this->showItemModal = true;
    }

    /**
     * What: Create or update a line item, snapshotting its tax percent, then refresh invoice totals.
     * Why: Per-line tax means the chosen rate's percent is copied onto the item so later catalog edits
     *      never rewrite history. The derived money columns and header totals are recomputed on save.
     * When: Triggered on submit of the line-item modal.
     */
    public function saveItem(): void
    {
        $this->authorize('update', $this->invoice);

        $validated = $this->validate([
            'itemDescription' => ['required', 'string', 'max:255'],
            'itemQuantity' => ['required', 'numeric', 'min:0.01'],
            'itemUnitPrice' => ['required', 'numeric', 'min:0'],
            'itemTaxRateId' => ['nullable', Rule::exists('tax_rates', 'id')],
        ]);

        $taxRate = $validated['itemTaxRateId'] !== ''
            ? TaxRate::query()->whereKey($validated['itemTaxRateId'])->first()
            : null;

        $attributes = [
            'tax_rate_id' => $taxRate?->id,
            'description' => $validated['itemDescription'],
            'quantity' => $validated['itemQuantity'],
            'unit_price' => $validated['itemUnitPrice'],
            'tax_rate' => $taxRate ? (string) $taxRate->rate : '0',
        ];

        if ($this->editingItemId !== null) {
            $item = $this->invoice->items()->findOrFail($this->editingItemId);
            $item->fill($attributes);
        } else {
            $item = $this->invoice->items()->make($attributes);
            $item->company_id = $this->invoice->company_id;
        }

        $item->recalculate();
        $item->save();

        $this->invoice->recalculateTotals();
        $this->invoice->refresh();

        $this->showItemModal = false;
        $this->resetItemForm();

        Flux::toast(variant: 'success', text: __('Line item saved.'));
    }

    public function confirmDeleteItem(int $itemId): void
    {
        $this->authorize('update', $this->invoice);
        $this->deletingItemId = $itemId;
        $this->showDeleteItemModal = true;
    }

    public function deleteItem(): void
    {
        $this->authorize('update', $this->invoice);
        $this->invoice->items()->findOrFail($this->deletingItemId)->delete();

        $this->invoice->recalculateTotals();
        $this->invoice->refresh();

        $this->showDeleteItemModal = false;
        $this->deletingItemId = null;

        Flux::toast(variant: 'success', text: __('Line item removed.'));
    }

    // =========================================================================
    // Payments
    // =========================================================================

    public function openPaymentModal(): void
    {
        $this->authorize('update', $this->invoice);
        $this->resetPaymentForm();
        // Default to the outstanding balance for a quick "mark paid".
        $this->paymentAmount = (string) max(0, $this->invoice->balance());
        $this->showPaymentModal = true;
    }

    /**
     * What: Record a payment against the invoice and auto-settle it when the balance clears.
     * Why: Payments are logged manually in v1; once the sum of transactions covers the total the invoice
     *      flips to Paid with a `paid_at` stamp so the list and dashboard reflect reality.
     * When: Triggered on submit of the record-payment modal.
     */
    public function savePayment(): void
    {
        $this->authorize('update', $this->invoice);

        $validated = $this->validate([
            'paymentAmount' => ['required', 'numeric', 'min:0.01'],
            'paymentMethod' => ['required', Rule::enum(PaymentMethod::class)],
            'paymentReference' => ['nullable', 'string', 'max:255'],
            'paymentPaidAt' => ['required', 'date'],
            'paymentNotes' => ['nullable', 'string', 'max:2000'],
        ]);

        $transaction = $this->invoice->transactions()->make([
            'amount' => $validated['paymentAmount'],
            'method' => $validated['paymentMethod'],
            'reference' => $validated['paymentReference'] ?: null,
            'paid_at' => $validated['paymentPaidAt'],
            'notes' => $validated['paymentNotes'] ?: null,
        ]);
        $transaction->company_id = $this->invoice->company_id;
        $transaction->save();

        $this->invoice->refresh();

        if ($this->invoice->isPaid()) {
            $this->invoice->update([
                'status' => InvoiceStatus::Paid,
                'paid_at' => $this->invoice->paid_at ?? now(),
            ]);
        } elseif ($this->invoice->status === InvoiceStatus::Draft) {
            // A part-payment on a draft moves it into the unpaid lifecycle.
            $this->invoice->update(['status' => InvoiceStatus::Unpaid]);
        }

        $this->invoice->refresh();
        $this->showPaymentModal = false;
        $this->resetPaymentForm();

        Flux::toast(variant: 'success', text: __('Payment recorded.'));
    }

    public function confirmDeletePayment(int $transactionId): void
    {
        $this->authorize('update', $this->invoice);
        $this->deletingPaymentId = $transactionId;
        $this->showDeletePaymentModal = true;
    }

    /**
     * What: Remove a recorded payment and re-evaluate whether the invoice is still settled.
     * Why: Correcting a mis-recorded payment must re-open a previously-Paid invoice.
     * When: Triggered on confirm of the delete-payment modal.
     */
    public function deletePayment(): void
    {
        $this->authorize('update', $this->invoice);
        $this->invoice->transactions()->findOrFail($this->deletingPaymentId)->delete();

        $this->invoice->refresh();

        if (! $this->invoice->isPaid() && $this->invoice->status === InvoiceStatus::Paid) {
            $this->invoice->update(['status' => InvoiceStatus::Unpaid, 'paid_at' => null]);
        }

        $this->invoice->refresh();
        $this->showDeletePaymentModal = false;
        $this->deletingPaymentId = null;

        Flux::toast(variant: 'success', text: __('Payment removed.'));
    }

    // =========================================================================
    // Helpers & computed data
    // =========================================================================

    protected function resetItemForm(): void
    {
        $this->reset(['editingItemId', 'itemDescription', 'itemTaxRateId']);
        $this->itemQuantity = '1';
        $this->itemUnitPrice = '0';
        $this->resetValidation();
    }

    protected function resetPaymentForm(): void
    {
        $this->reset(['paymentReference', 'paymentNotes']);
        $this->paymentAmount = '0';
        $this->paymentMethod = PaymentMethod::BankTransfer->value;
        $this->paymentPaidAt = now()->toDateString();
        $this->resetValidation();
    }

    /**
     * @return Collection<int, InvoiceItem>
     */
    #[Computed]
    public function items(): Collection
    {
        return $this->invoice->items()->orderBy('id')->get();
    }

    /**
     * @return Collection<int, Transaction>
     */
    #[Computed]
    public function transactions(): Collection
    {
        return $this->invoice->transactions()->get();
    }

    /**
     * @return Collection<int, TaxRate>
     */
    #[Computed]
    public function taxRates(): Collection
    {
        return TaxRate::query()->where('is_active', true)->orderBy('name')->get();
    }

    /**
     * @return array<int, InvoiceStatus>
     */
    #[Computed]
    public function statuses(): array
    {
        return InvoiceStatus::cases();
    }

    /**
     * @return array<int, PaymentMethod>
     */
    #[Computed]
    public function methods(): array
    {
        return PaymentMethod::cases();
    }

    public function render()
    {
        return view('livewire.admin.invoices.show');
    }
}
