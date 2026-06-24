<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * What: Streams a downloadable PDF rendering of an invoice.
 * Why: idea.md calls for downloadable invoice PDFs; DomPDF renders a print-friendly Blade template that
 *      doesn't depend on the Tailwind/Flux pipeline. Route-model binding is tenant-scoped by the
 *      BelongsToCompany global scope, so an admin can only ever fetch their own company's invoices.
 * When: Hit at `/admin/invoices/{invoice}/pdf` by company admins holding `invoices.view`.
 */
class InvoicePdfController extends Controller
{
    public function __invoke(Invoice $invoice): Response
    {
        Gate::authorize('view', $invoice);

        $invoice->load(['client', 'items', 'transactions', 'company']);

        $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $invoice]);

        return $pdf->download($invoice->number.'.pdf');
    }
}
