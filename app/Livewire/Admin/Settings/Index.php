<?php

namespace App\Livewire\Admin\Settings;

use App\Services\Settings\CompanySettings;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * What: The company-admin settings screen — a tabbed editor over the tenant key/value settings store.
 * Why: Phase 8 gives each tenant configurable Company / Billing / Email / Localisation / Gateway /
 *      Reminder settings. One Livewire component hydrates every field from CompanySettings and saves a
 *      single tab at a time, so a partial save never clobbers untouched groups. Secrets are write-only:
 *      they hydrate blank and are skipped on save when left empty, so the masked field never wipes a
 *      stored secret the admin didn't retype.
 * When: Rendered at `/admin/settings` for company admins holding `settings.view`; saving requires
 *       `settings.manage`.
 */
#[Title('Settings')]
class Index extends Component
{
    #[Url]
    public string $tab = 'company';

    // --- Company ---
    public string $company_name = '';

    public string $company_email = '';

    public string $company_phone = '';

    public string $company_address = '';

    // --- Billing ---
    public string $currency = 'USD';

    public string $tax_label = 'Tax';

    public string $invoice_prefix = 'INV-';

    public int $invoice_due_days = 14;

    // --- Email ---
    public string $mail_from_name = '';

    public string $mail_from_email = '';

    public string $smtp_host = '';

    public int $smtp_port = 587;

    public string $smtp_username = '';

    public string $smtp_password = '';

    public string $smtp_encryption = 'tls';

    // --- Localisation ---
    public string $date_format = 'Y-m-d';

    public string $timezone = 'UTC';

    public string $default_language = 'en';

    // --- Gateways ---
    public bool $stripe_enabled = false;

    public string $stripe_key = '';

    public string $stripe_secret = '';

    public bool $paypal_enabled = false;

    public string $paypal_client_id = '';

    public string $paypal_secret = '';

    // --- Reminders ---
    public bool $reminders_enabled = true;

    /** @var array<int, int> */
    public array $reminder_lead_times = [];

    /**
     * What: Authorize access and hydrate every field from the tenant's stored settings.
     * Why: Secrets stay blank (write-only) so the masked field never echoes a stored secret.
     * When: On component mount.
     */
    public function mount(): void
    {
        abort_unless(Auth::user()->can('settings.view'), 403);

        $settings = $this->settings();

        $this->company_name = (string) ($settings->get('company_name') ?? Auth::user()->company->name ?? '');
        $this->company_email = (string) $settings->get('company_email');
        $this->company_phone = (string) $settings->get('company_phone');
        $this->company_address = (string) $settings->get('company_address');

        $this->currency = (string) $settings->get('currency');
        $this->tax_label = (string) $settings->get('tax_label');
        $this->invoice_prefix = (string) $settings->get('invoice_prefix');
        $this->invoice_due_days = (int) $settings->get('invoice_due_days');

        $this->mail_from_name = (string) $settings->get('mail_from_name');
        $this->mail_from_email = (string) $settings->get('mail_from_email');
        $this->smtp_host = (string) $settings->get('smtp_host');
        $this->smtp_port = (int) $settings->get('smtp_port');
        $this->smtp_username = (string) $settings->get('smtp_username');
        $this->smtp_encryption = (string) $settings->get('smtp_encryption');
        // smtp_password intentionally not hydrated (write-only secret).

        $this->date_format = (string) $settings->get('date_format');
        $this->timezone = (string) $settings->get('timezone');
        $this->default_language = (string) $settings->get('default_language');

        $this->stripe_enabled = (bool) $settings->get('stripe_enabled');
        $this->stripe_key = (string) $settings->get('stripe_key');
        $this->paypal_enabled = (bool) $settings->get('paypal_enabled');
        $this->paypal_client_id = (string) $settings->get('paypal_client_id');
        // stripe_secret / paypal_secret intentionally not hydrated (write-only secrets).

        $this->reminders_enabled = (bool) $settings->get('reminders_enabled');
        $this->reminder_lead_times = $settings->get('reminder_lead_times') ?: [];
    }

    /**
     * What: Persist the Company tab.
     * When: Save button on the Company tab.
     */
    public function saveCompany(): void
    {
        $this->authorizeManage();

        $validated = $this->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:50'],
            'company_address' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->settings()->fill($validated);
        $this->saved();
    }

    /**
     * What: Persist the Billing tab.
     * When: Save button on the Billing tab.
     */
    public function saveBilling(): void
    {
        $this->authorizeManage();

        $validated = $this->validate([
            'currency' => ['required', 'string', 'size:3'],
            'tax_label' => ['required', 'string', 'max:50'],
            'invoice_prefix' => ['required', 'string', 'max:20'],
            'invoice_due_days' => ['required', 'integer', 'min:0', 'max:365'],
        ]);

        $this->settings()->fill($validated);
        $this->saved();
    }

    /**
     * What: Persist the Email tab.
     * Why: A blank password is skipped by `fill()` so the stored secret survives.
     * When: Save button on the Email tab.
     */
    public function saveEmail(): void
    {
        $this->authorizeManage();

        $validated = $this->validate([
            'mail_from_name' => ['nullable', 'string', 'max:255'],
            'mail_from_email' => ['nullable', 'email', 'max:255'],
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['required', 'integer', 'min:0', 'max:65535'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'smtp_encryption' => ['required', 'in:tls,ssl,none'],
        ]);

        $this->settings()->fill($validated);
        $this->saved();
    }

    /**
     * What: Persist the Localisation tab.
     * When: Save button on the Localisation tab.
     */
    public function saveLocalisation(): void
    {
        $this->authorizeManage();

        $validated = $this->validate([
            'date_format' => ['required', 'string', 'max:30'],
            'timezone' => ['required', 'timezone'],
            'default_language' => ['required', 'string', 'max:10'],
        ]);

        $this->settings()->fill($validated);
        $this->saved();
    }

    /**
     * What: Persist the Payment Gateways tab.
     * Why: Secret keys hydrate blank and are skipped on save when empty so they are never wiped.
     * When: Save button on the Gateways tab.
     */
    public function saveGateways(): void
    {
        $this->authorizeManage();

        $validated = $this->validate([
            'stripe_enabled' => ['boolean'],
            'stripe_key' => ['nullable', 'string', 'max:255'],
            'stripe_secret' => ['nullable', 'string', 'max:255'],
            'paypal_enabled' => ['boolean'],
            'paypal_client_id' => ['nullable', 'string', 'max:255'],
            'paypal_secret' => ['nullable', 'string', 'max:255'],
        ]);

        $this->settings()->fill($validated);
        $this->saved();
    }

    /**
     * What: Persist the Reminders tab.
     * Why: Lead times feed the reminders module's default rule cadence and the global on/off switch.
     * When: Save button on the Reminders tab.
     */
    public function saveReminders(): void
    {
        $this->authorizeManage();

        $validated = $this->validate([
            'reminders_enabled' => ['boolean'],
            'reminder_lead_times' => ['array'],
            'reminder_lead_times.*' => ['integer', 'min:1', 'max:365'],
        ]);

        // Normalise: unique, sorted descending, re-indexed.
        /** @var array<int, int|string> $rawLeadTimes */
        $rawLeadTimes = $validated['reminder_lead_times'] ?? [];

        $leadTimes = collect($rawLeadTimes)
            ->map(fn (int|string $day): int => (int) $day)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        $this->settings()->fill([
            'reminders_enabled' => $validated['reminders_enabled'],
            'reminder_lead_times' => $leadTimes,
        ]);

        $this->reminder_lead_times = $leadTimes;
        $this->saved();
    }

    protected function authorizeManage(): void
    {
        abort_unless(Auth::user()->can('settings.manage'), 403);
    }

    protected function saved(): void
    {
        Flux::toast(variant: 'success', text: __('Settings saved.'));
    }

    protected function settings(): CompanySettings
    {
        return CompanySettings::forCompany(Auth::user()->company_id);
    }

    public function render()
    {
        return view('livewire.admin.settings.index');
    }
}
