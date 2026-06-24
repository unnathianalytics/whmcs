<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Services\Settings\CompanySettings;
use Illuminate\Database\Seeder;

/**
 * What: Seeds sensible default settings for every company.
 * Why: A new tenant should land on a populated settings screen (company name/email pulled from the
 *      Company record, a usable currency/prefix/timezone baseline) rather than bare defaults. Values
 *      are upserted via the CompanySettings service so re-running is idempotent and encryption rules
 *      are honoured.
 * When: Run from DatabaseSeeder after companies exist.
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        Company::all()->each(function (Company $company): void {
            CompanySettings::forCompany($company->id)->fill([
                'company_name' => $company->name,
                'company_email' => $company->email,
                'company_phone' => $company->phone,
                'company_address' => $company->address,
                'currency' => 'USD',
                'tax_label' => 'Tax',
                'invoice_prefix' => 'INV-',
                'invoice_due_days' => 14,
                'mail_from_name' => $company->name,
                'mail_from_email' => $company->email,
                'date_format' => 'Y-m-d',
                'timezone' => 'UTC',
                'default_language' => 'en',
                'reminders_enabled' => true,
                'reminder_lead_times' => [30, 14, 7, 1],
            ]);
        });
    }
}
