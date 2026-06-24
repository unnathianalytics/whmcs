<?php

namespace App\Services\Settings;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;

/**
 * What: A typed read/write facade over the tenant-scoped `settings` key/value table.
 * Why: Callers (the settings UI, invoice generation, the reminders scheduler, mail config) need a
 *      single, safe place to read configuration with sensible defaults and to persist changes. It
 *      centralises the canonical default map so an unset key always resolves to a usable value, and it
 *      transparently encrypts/decrypts the handful of secret keys (gateway API keys, SMTP password) so
 *      secrets never sit in plaintext. Resolved values are memoised per request to avoid re-querying.
 * When: Resolved per company. The settings screen calls `all()`/`fill()`; the rest of the app calls
 *       `get()` for individual values.
 */
class CompanySettings
{
    /**
     * Canonical default values for every known setting key.
     *
     * @var array<string, mixed>
     */
    public const DEFAULTS = [
        // Company
        'company_name' => null,
        'company_email' => null,
        'company_phone' => null,
        'company_address' => null,

        // Billing
        'currency' => 'USD',
        'tax_label' => 'Tax',
        'invoice_prefix' => 'INV-',
        'invoice_due_days' => 14,

        // Email
        'mail_from_name' => null,
        'mail_from_email' => null,
        'smtp_host' => null,
        'smtp_port' => 587,
        'smtp_username' => null,
        'smtp_password' => null,
        'smtp_encryption' => 'tls',

        // Localisation
        'date_format' => 'Y-m-d',
        'timezone' => 'UTC',
        'default_language' => 'en',

        // Payment gateways
        'stripe_enabled' => false,
        'stripe_key' => null,
        'stripe_secret' => null,
        'paypal_enabled' => false,
        'paypal_client_id' => null,
        'paypal_secret' => null,

        // Reminders
        'reminders_enabled' => true,
        'reminder_lead_times' => [30, 14, 7, 1],
    ];

    /**
     * Keys whose values are encrypted at rest. Never expose decrypted values to logs or the audit trail.
     *
     * @var list<string>
     */
    public const ENCRYPTED_KEYS = [
        'smtp_password',
        'stripe_secret',
        'paypal_secret',
    ];

    /**
     * Memoised resolved values for this request, keyed by setting key.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $cache = null;

    public function __construct(public int $companyId) {}

    /**
     * What: Read a single setting value, falling back to its declared default.
     * Why: Callers should never branch on "is it set?" — an unset key resolves to a usable default.
     * When: Anywhere a configuration value is needed (mail config, invoice prefix, reminder lead times).
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $values = $this->all();

        return $values[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    /**
     * What: Return every known setting resolved to its stored value or default.
     * Why: The settings screen hydrates all of its form fields from one call; secrets are decrypted here.
     * When: On render of the settings UI and on the first `get()` of a request (memoised thereafter).
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $stored = Setting::query()
            ->where('company_id', $this->companyId)
            ->pluck('value', 'key')
            ->all();

        $resolved = self::DEFAULTS;

        foreach ($stored as $key => $value) {
            $resolved[$key] = in_array($key, self::ENCRYPTED_KEYS, true)
                ? $this->decrypt($value)
                : $value;
        }

        return $this->cache = $resolved;
    }

    /**
     * What: Persist a single setting value, upserting on `(company_id, key)`.
     * Why: One write path keeps encryption and the per-tenant unique constraint consistent.
     * When: Called by `fill()` for each changed key when the settings form is saved.
     */
    public function set(string $key, mixed $value): void
    {
        if (in_array($key, self::ENCRYPTED_KEYS, true) && $value !== null && $value !== '') {
            $value = Crypt::encryptString((string) $value);
        }

        // `company_id` is not mass-assignable, so target the row explicitly and forceFill the value.
        $setting = Setting::query()->firstOrNew([
            'company_id' => $this->companyId,
            'key' => $key,
        ]);

        $setting->company_id = $this->companyId;
        $setting->key = $key;
        $setting->value = $value;
        $setting->save();

        $this->cache = null;
    }

    /**
     * What: Persist a batch of settings, ignoring unknown keys.
     * Why: The settings screen submits a whole group at once; restricting to known keys prevents the form
     *      from writing arbitrary keys. A null/empty secret leaves the stored secret untouched so the
     *      masked UI never wipes a value the admin didn't retype.
     * When: On save of any settings tab.
     *
     * @param  array<string, mixed>  $values
     */
    public function fill(array $values): void
    {
        foreach ($values as $key => $value) {
            if (! array_key_exists($key, self::DEFAULTS)) {
                continue;
            }

            if (in_array($key, self::ENCRYPTED_KEYS, true) && ($value === null || $value === '')) {
                continue;
            }

            $this->set($key, $value);
        }

        $this->cache = null;
    }

    /**
     * What: Safely decrypt a stored secret, returning null if it cannot be read.
     * Why: A value stored before encryption (or a rotated app key) must not throw and break the screen.
     * When: While resolving encrypted keys in `all()`.
     */
    protected function decrypt(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * What: Build a settings instance for a given company id.
     * Why: Convenience factory so callers read `CompanySettings::forCompany($id)->get(...)`.
     * When: Anywhere a company id is in hand.
     */
    public static function forCompany(int $companyId): self
    {
        return new self($companyId);
    }
}
