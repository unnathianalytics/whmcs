<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Livewire\Admin\Invoices\Index;
use App\Livewire\Admin\Invoices\Show;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\TaxRate;
use App\Models\Transaction;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

describe('tenant isolation', function () {
    test('the company scope hides invoices belonging to another company', function () {
        $admin = grantPermissions(companyAdmin(), ['invoices.view']);
        $other = companyAdmin();

        Invoice::factory()->for(Client::factory()->for($admin->company))->count(2)->create([
            'company_id' => $admin->company_id,
        ]);
        Invoice::factory()->for(Client::factory()->for($other->company))->count(3)->create([
            'company_id' => $other->company_id,
        ]);

        actingAs($admin);

        expect(Invoice::count())->toBe(2);
    });

    test('an invoice created as a company admin auto-stamps the company id and a number', function () {
        $admin = grantPermissions(companyAdmin(), ['invoices.view', 'invoices.create']);
        $client = Client::factory()->for($admin->company)->create();

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('openCreateModal')
            ->set('clientId', (string) $client->id)
            ->set('issueDate', now()->toDateString())
            ->set('dueDate', now()->addDays(14)->toDateString())
            ->call('create')
            ->assertHasNoErrors();

        $invoice = Invoice::withoutGlobalScopes()->firstWhere('client_id', $client->id);

        expect($invoice)->not->toBeNull()
            ->and($invoice->company_id)->toBe($admin->company_id)
            ->and($invoice->number)->toBe('INV-000001')
            ->and($invoice->status)->toBe(InvoiceStatus::Draft);
    });
});

describe('access control', function () {
    test('a company admin without invoices.view is forbidden the list', function () {
        actingAs(companyAdmin())
            ->get(route('admin.invoices'))
            ->assertForbidden();
    });

    test('a company admin with invoices.view can see the list', function () {
        $admin = grantPermissions(companyAdmin(), ['invoices.view']);

        actingAs($admin)
            ->get(route('admin.invoices'))
            ->assertOk();
    });

    test('creating an invoice without invoices.create is forbidden', function () {
        $admin = grantPermissions(companyAdmin(), ['invoices.view']);

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('openCreateModal')
            ->assertForbidden();
    });

    test('adding a line item without invoices.update is forbidden', function () {
        $admin = grantPermissions(companyAdmin(), ['invoices.view']);
        $invoice = Invoice::factory()->for(Client::factory()->for($admin->company))->create([
            'company_id' => $admin->company_id,
        ]);

        actingAs($admin);

        Livewire::test(Show::class, ['invoice' => $invoice])
            ->call('openCreateItemModal')
            ->assertForbidden();
    });
});

describe('validation', function () {
    test('an invoice requires a client and a due date on or after the issue date', function () {
        $admin = grantPermissions(companyAdmin(), ['invoices.view', 'invoices.create']);

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('openCreateModal')
            ->set('clientId', '')
            ->set('issueDate', now()->toDateString())
            ->set('dueDate', now()->subDay()->toDateString())
            ->call('create')
            ->assertHasErrors(['clientId' => 'required', 'dueDate']);
    });

    test('a line item requires a description, quantity and unit price', function () {
        $admin = grantPermissions(companyAdmin(), ['invoices.view', 'invoices.update']);
        $invoice = Invoice::factory()->for(Client::factory()->for($admin->company))->create([
            'company_id' => $admin->company_id,
        ]);

        actingAs($admin);

        Livewire::test(Show::class, ['invoice' => $invoice])
            ->call('openCreateItemModal')
            ->set('itemDescription', '')
            ->set('itemQuantity', '0')
            ->call('saveItem')
            ->assertHasErrors(['itemDescription' => 'required', 'itemQuantity' => 'min']);
    });
});

describe('line items and totals', function () {
    test('adding a taxed line item recalculates the invoice totals', function () {
        $admin = grantPermissions(companyAdmin(), ['invoices.view', 'invoices.update']);
        $invoice = Invoice::factory()->for(Client::factory()->for($admin->company))->create([
            'company_id' => $admin->company_id,
        ]);
        $taxRate = TaxRate::factory()->create(['company_id' => $admin->company_id, 'rate' => 18]);

        actingAs($admin);

        Livewire::test(Show::class, ['invoice' => $invoice])
            ->call('openCreateItemModal')
            ->set('itemDescription', 'Hosting')
            ->set('itemQuantity', '2')
            ->set('itemUnitPrice', '100')
            ->set('itemTaxRateId', (string) $taxRate->id)
            ->call('saveItem')
            ->assertHasNoErrors();

        $invoice->refresh();
        $item = $invoice->items()->first();

        expect((float) $item->line_subtotal)->toBe(200.0)
            ->and((float) $item->tax_amount)->toBe(36.0)
            ->and((float) $item->tax_rate)->toBe(18.0)
            ->and((float) $invoice->subtotal)->toBe(200.0)
            ->and((float) $invoice->tax_total)->toBe(36.0)
            ->and((float) $invoice->total)->toBe(236.0);
    });

    test('removing a line item recalculates the totals back down', function () {
        $admin = grantPermissions(companyAdmin(), ['invoices.view', 'invoices.update']);
        $invoice = Invoice::factory()->for(Client::factory()->for($admin->company))->create([
            'company_id' => $admin->company_id,
        ]);
        $item = InvoiceItem::factory()->for($invoice)->create([
            'company_id' => $admin->company_id,
            'quantity' => 1,
            'unit_price' => 500,
            'tax_rate' => 0,
            'line_subtotal' => 500,
            'tax_amount' => 0,
            'line_total' => 500,
        ]);
        $invoice->recalculateTotals();

        actingAs($admin);

        Livewire::test(Show::class, ['invoice' => $invoice])
            ->call('confirmDeleteItem', $item->id)
            ->call('deleteItem')
            ->assertHasNoErrors();

        expect((float) $invoice->fresh()->total)->toBe(0.0)
            ->and($invoice->items()->count())->toBe(0);
    });
});

describe('payments', function () {
    test('a full payment marks the invoice paid', function () {
        $admin = grantPermissions(companyAdmin(), ['invoices.view', 'invoices.update']);
        $invoice = Invoice::factory()->for(Client::factory()->for($admin->company))->create([
            'company_id' => $admin->company_id,
            'status' => InvoiceStatus::Unpaid,
        ]);
        InvoiceItem::factory()->for($invoice)->create([
            'company_id' => $admin->company_id,
            'quantity' => 1,
            'unit_price' => 1000,
            'tax_rate' => 0,
            'line_subtotal' => 1000,
            'tax_amount' => 0,
            'line_total' => 1000,
        ]);
        $invoice->recalculateTotals();

        actingAs($admin);

        Livewire::test(Show::class, ['invoice' => $invoice])
            ->call('openPaymentModal')
            ->set('paymentAmount', '1000')
            ->set('paymentMethod', PaymentMethod::Cash->value)
            ->set('paymentPaidAt', now()->toDateString())
            ->call('savePayment')
            ->assertHasNoErrors();

        $invoice->refresh();

        expect($invoice->status)->toBe(InvoiceStatus::Paid)
            ->and($invoice->paid_at)->not->toBeNull()
            ->and($invoice->balance())->toBe(0.0);
    });

    test('a partial payment leaves the invoice unpaid with a remaining balance', function () {
        $admin = grantPermissions(companyAdmin(), ['invoices.view', 'invoices.update']);
        $invoice = Invoice::factory()->for(Client::factory()->for($admin->company))->create([
            'company_id' => $admin->company_id,
            'status' => InvoiceStatus::Unpaid,
        ]);
        InvoiceItem::factory()->for($invoice)->create([
            'company_id' => $admin->company_id,
            'quantity' => 1,
            'unit_price' => 1000,
            'tax_rate' => 0,
            'line_subtotal' => 1000,
            'tax_amount' => 0,
            'line_total' => 1000,
        ]);
        $invoice->recalculateTotals();

        actingAs($admin);

        Livewire::test(Show::class, ['invoice' => $invoice])
            ->call('openPaymentModal')
            ->set('paymentAmount', '400')
            ->set('paymentPaidAt', now()->toDateString())
            ->call('savePayment')
            ->assertHasNoErrors();

        $invoice->refresh();

        expect($invoice->status)->toBe(InvoiceStatus::Unpaid)
            ->and($invoice->balance())->toBe(600.0);
    });

    test('removing the settling payment re-opens a paid invoice', function () {
        $admin = grantPermissions(companyAdmin(), ['invoices.view', 'invoices.update']);
        $invoice = Invoice::factory()->for(Client::factory()->for($admin->company))->paid()->create([
            'company_id' => $admin->company_id,
            'total' => 1000,
            'subtotal' => 1000,
        ]);
        $transaction = Transaction::factory()->for($invoice)->create([
            'company_id' => $admin->company_id,
            'amount' => 1000,
        ]);

        actingAs($admin);

        Livewire::test(Show::class, ['invoice' => $invoice])
            ->call('confirmDeletePayment', $transaction->id)
            ->call('deletePayment')
            ->assertHasNoErrors();

        $invoice->refresh();

        expect($invoice->status)->toBe(InvoiceStatus::Unpaid)
            ->and($invoice->paid_at)->toBeNull();
    });
});

describe('overdue detection', function () {
    test('an unpaid invoice past its due date is overdue', function () {
        $invoice = Invoice::factory()->overdue()->make(['total' => 500]);

        expect($invoice->isOverdue())->toBeTrue();
    });

    test('a paid invoice is never overdue', function () {
        $invoice = Invoice::factory()->paid()->make([
            'due_date' => now()->subWeek(),
            'total' => 500,
        ]);

        expect($invoice->isOverdue())->toBeFalse();
    });
});

describe('pdf', function () {
    test('an admin with invoices.view can download the invoice pdf', function () {
        $admin = grantPermissions(companyAdmin(), ['invoices.view']);
        $invoice = Invoice::factory()->for(Client::factory()->for($admin->company))->create([
            'company_id' => $admin->company_id,
        ]);

        actingAs($admin)
            ->get(route('admin.invoices.pdf', $invoice))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    });

    test('a company admin without invoices.view is forbidden the pdf', function () {
        $admin = companyAdmin();
        $invoice = Invoice::factory()->for(Client::factory()->for($admin->company))->create([
            'company_id' => $admin->company_id,
        ]);

        actingAs($admin)
            ->get(route('admin.invoices.pdf', $invoice))
            ->assertForbidden();
    });
});

describe('crud', function () {
    test('an admin can soft-delete an invoice', function () {
        $admin = grantPermissions(companyAdmin(), ['invoices.view', 'invoices.delete']);
        $invoice = Invoice::factory()->for(Client::factory()->for($admin->company))->create([
            'company_id' => $admin->company_id,
        ]);

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('confirmDelete', $invoice->id)
            ->call('delete')
            ->assertHasNoErrors();

        expect(Invoice::find($invoice->id))->toBeNull()
            ->and(Invoice::withTrashed()->find($invoice->id)?->trashed())->toBeTrue();
    });
});
