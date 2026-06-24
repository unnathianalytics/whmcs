<?php

namespace Database\Seeders;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\TaxRate;
use Illuminate\Database\Seeder;

/**
 * What: Seeds a demo tax-rate catalog plus ~20 invoices (mix of paid/unpaid/overdue) with line items and
 *       some recorded payments for the demo company so the Invoices module has data on first run.
 * Why: The idea.md development sample data calls for 20 invoices in mixed states; this populates the list,
 *      the client profiles and the PDF without manual entry. Totals are recalculated from the seeded lines.
 * When: Called from DatabaseSeeder after ProductSeeder. Sets `company_id` explicitly because the seeder
 *       runs without an authenticated user (the BelongsToCompany scope no-ops there).
 */
class InvoiceSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('slug', 'demo-company')->first();

        if ($company === null) {
            return;
        }

        $gst = TaxRate::create([
            'company_id' => $company->id,
            'name' => 'GST 18%',
            'rate' => 18,
            'is_active' => true,
        ]);

        TaxRate::create([
            'company_id' => $company->id,
            'name' => 'GST 5%',
            'rate' => 5,
            'is_active' => true,
        ]);

        $clients = Client::where('company_id', $company->id)->get();

        if ($clients->isEmpty()) {
            return;
        }

        for ($i = 1; $i <= 20; $i++) {
            $client = $clients->random();

            // Cycle through draft / unpaid / overdue / paid states for variety.
            $state = $i % 4;
            $issueDate = now()->subDays(($i % 6) * 10 + 5);

            $invoice = Invoice::create([
                'company_id' => $company->id,
                'client_id' => $client->id,
                'number' => Invoice::nextNumber($company->id),
                'status' => match ($state) {
                    0 => InvoiceStatus::Draft,
                    3 => InvoiceStatus::Paid,
                    default => InvoiceStatus::Unpaid,
                },
                'issue_date' => $issueDate,
                'due_date' => $state === 2
                    ? now()->subDays(5)               // overdue
                    : (clone $issueDate)->modify('+14 days'),
                'currency' => 'INR',
            ]);

            $lineCount = rand(1, 3);

            for ($l = 0; $l < $lineCount; $l++) {
                $quantity = rand(1, 4);
                $unitPrice = collect([499, 999, 1999, 4999])->random();
                $useTax = (bool) rand(0, 1);
                $rate = $useTax ? (float) $gst->rate : 0.0;

                $subtotal = round($quantity * $unitPrice, 2);
                $taxAmount = round($subtotal * ($rate / 100), 2);

                $invoice->items()->create([
                    'company_id' => $company->id,
                    'tax_rate_id' => $useTax ? $gst->id : null,
                    'description' => collect([
                        'Shared Hosting — Annual',
                        'VPS Medium — Monthly',
                        'SSL Certificate',
                        'Email Hosting — Annual',
                        'Domain Renewal (.com)',
                    ])->random(),
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'tax_rate' => $rate,
                    'line_subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'line_total' => $subtotal + $taxAmount,
                ]);
            }

            $invoice->recalculateTotals();

            // Record full payment for paid invoices, an occasional part-payment for unpaid ones.
            if ($invoice->status === InvoiceStatus::Paid) {
                $invoice->transactions()->create([
                    'company_id' => $company->id,
                    'amount' => $invoice->total,
                    'method' => PaymentMethod::BankTransfer,
                    'reference' => 'TXN-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                    'paid_at' => now()->subDays(2),
                ]);
                $invoice->update(['paid_at' => now()->subDays(2)]);
            } elseif ($state === 1) {
                $invoice->transactions()->create([
                    'company_id' => $company->id,
                    'amount' => round((float) $invoice->total / 2, 2),
                    'method' => PaymentMethod::Cash,
                    'paid_at' => now()->subDays(1),
                ]);
            }
        }
    }
}
