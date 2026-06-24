<?php

use App\Http\Controllers\Admin\InvoicePdfController;
use App\Http\Controllers\DashboardRedirectController;
use App\Livewire\Admin\Clients\Index as AdminClients;
use App\Livewire\Admin\Clients\Show as AdminClientShow;
use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\Admin\Invoices\Index as AdminInvoices;
use App\Livewire\Admin\Invoices\Show as AdminInvoiceShow;
use App\Livewire\Admin\Products\Index as AdminProducts;
use App\Livewire\Admin\Services\Index as AdminServices;
use App\Livewire\Admin\TaxRates\Index as AdminTaxRates;
use App\Livewire\Saas\Companies\Index as SaasCompanies;
use App\Livewire\Saas\Dashboard as SaasDashboard;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Fortify lands every user on `dashboard`; this hop sends each tier to its real home.
    Route::get('dashboard', DashboardRedirectController::class)->name('dashboard');

    // SaaS Admin area — platform owner only.
    Route::middleware('saas_admin')->prefix('saas')->name('saas.')->group(function () {
        Route::livewire('/', SaasDashboard::class)->name('dashboard');
        Route::livewire('companies', SaasCompanies::class)->name('companies');
    });

    // Company Admin area — tenant-scoped admins.
    Route::middleware('company_admin')->prefix('admin')->name('admin.')->group(function () {
        Route::livewire('/', AdminDashboard::class)->name('dashboard');
        Route::livewire('clients', AdminClients::class)->name('clients');
        Route::livewire('clients/{client}', AdminClientShow::class)->name('clients.show');
        Route::livewire('products', AdminProducts::class)->name('products');
        Route::livewire('services', AdminServices::class)->name('services');
        Route::livewire('invoices', AdminInvoices::class)->name('invoices');
        Route::livewire('invoices/{invoice}', AdminInvoiceShow::class)->name('invoices.show');
        Route::get('invoices/{invoice}/pdf', InvoicePdfController::class)->name('invoices.pdf');
        Route::livewire('tax-rates', AdminTaxRates::class)->name('tax-rates');
    });
});

require __DIR__.'/settings.php';
