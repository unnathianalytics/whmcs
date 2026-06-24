<?php

use App\Livewire\Admin\Settings\Index;
use App\Models\Setting;
use App\Services\Settings\CompanySettings;
use Illuminate\Support\Facades\Crypt;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

describe('CompanySettings service', function () {
    test('get falls back to the declared default when a key is unset', function () {
        $admin = companyAdmin();

        $settings = CompanySettings::forCompany($admin->company_id);

        expect($settings->get('currency'))->toBe('USD')
            ->and($settings->get('invoice_due_days'))->toBe(14);
    });

    test('set then get round-trips a value', function () {
        $admin = companyAdmin();
        actingAs($admin);

        $settings = CompanySettings::forCompany($admin->company_id);
        $settings->set('currency', 'EUR');

        expect(CompanySettings::forCompany($admin->company_id)->get('currency'))->toBe('EUR');
    });

    test('settings are isolated per company', function () {
        $a = companyAdmin();
        $b = companyAdmin();

        CompanySettings::forCompany($a->company_id)->set('currency', 'GBP');

        expect(CompanySettings::forCompany($b->company_id)->get('currency'))->toBe('USD');
    });

    test('secret keys are stored encrypted and decrypted on read', function () {
        $admin = companyAdmin();

        $settings = CompanySettings::forCompany($admin->company_id);
        $settings->set('stripe_secret', 'sk_live_123');

        // The stored value (after JSON cast) is the ciphertext, never the plaintext.
        $stored = Setting::query()
            ->where('company_id', $admin->company_id)
            ->where('key', 'stripe_secret')
            ->first()->value;

        expect($stored)->toBeString()
            ->and($stored)->not->toBe('sk_live_123')
            ->and(Crypt::decryptString($stored))->toBe('sk_live_123')
            ->and(CompanySettings::forCompany($admin->company_id)->get('stripe_secret'))->toBe('sk_live_123');
    });

    test('fill ignores unknown keys and skips blank secrets', function () {
        $admin = companyAdmin();

        $settings = CompanySettings::forCompany($admin->company_id);
        $settings->set('stripe_secret', 'sk_keep');
        $settings->fill([
            'currency' => 'CAD',
            'unknown_key' => 'nope',
            'stripe_secret' => '',
        ]);

        expect(Setting::query()->where('key', 'unknown_key')->exists())->toBeFalse()
            ->and(CompanySettings::forCompany($admin->company_id)->get('stripe_secret'))->toBe('sk_keep')
            ->and(CompanySettings::forCompany($admin->company_id)->get('currency'))->toBe('CAD');
    });
});

describe('settings screen access control', function () {
    test('a company admin without settings.view is forbidden', function () {
        actingAs(companyAdmin())
            ->get(route('admin.settings'))
            ->assertForbidden();
    });

    test('a company admin with settings.view can open the screen', function () {
        $admin = grantPermissions(companyAdmin(), ['settings.view']);

        actingAs($admin)
            ->get(route('admin.settings'))
            ->assertOk();
    });

    test('saving without settings.manage is forbidden', function () {
        $admin = grantPermissions(companyAdmin(), ['settings.view']);
        actingAs($admin);

        Livewire::test(Index::class)
            ->set('currency', 'EUR')
            ->call('saveBilling')
            ->assertForbidden();
    });
});

describe('settings screen save', function () {
    test('saving billing persists the values', function () {
        $admin = grantPermissions(companyAdmin(), ['settings.view', 'settings.manage']);
        actingAs($admin);

        Livewire::test(Index::class)
            ->set('currency', 'EUR')
            ->set('tax_label', 'VAT')
            ->set('invoice_prefix', 'BILL-')
            ->set('invoice_due_days', 30)
            ->call('saveBilling')
            ->assertHasNoErrors();

        $settings = CompanySettings::forCompany($admin->company_id);

        expect($settings->get('currency'))->toBe('EUR')
            ->and($settings->get('tax_label'))->toBe('VAT')
            ->and($settings->get('invoice_prefix'))->toBe('BILL-')
            ->and($settings->get('invoice_due_days'))->toBe(30);
    });

    test('reminder lead times are normalised to unique, descending integers', function () {
        $admin = grantPermissions(companyAdmin(), ['settings.view', 'settings.manage']);
        actingAs($admin);

        Livewire::test(Index::class)
            ->set('reminders_enabled', true)
            ->set('reminder_lead_times', [7, 30, 7, 1])
            ->call('saveReminders')
            ->assertHasNoErrors();

        expect(CompanySettings::forCompany($admin->company_id)->get('reminder_lead_times'))
            ->toBe([30, 7, 1]);
    });
});
