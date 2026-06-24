<?php

use App\Models\User;
use App\Services\Saas\Impersonation;

use function Pest\Laravel\actingAs;

describe('impersonation', function () {
    test('a saas admin can start impersonating a company admin', function () {
        $admin = saasAdmin();
        $tenant = companyAdmin();

        actingAs($admin)
            ->post(route('saas.impersonate', $tenant))
            ->assertRedirect(route('admin.dashboard'));

        expect(auth()->id())->toBe($tenant->id)
            ->and(session()->get(Impersonation::SESSION_KEY))->toBe($admin->id);
    });

    test('stopping restores the original saas admin and clears the stash', function () {
        $admin = saasAdmin();
        $tenant = companyAdmin();

        actingAs($admin)->post(route('saas.impersonate', $tenant));

        $this->post(route('impersonate.stop'))
            ->assertRedirect(route('saas.companies'));

        expect(auth()->id())->toBe($admin->id)
            ->and(session()->has(Impersonation::SESSION_KEY))->toBeFalse();
    });

    test('an impersonated tenant can reach the admin area', function () {
        $admin = saasAdmin();
        $tenant = companyAdmin();

        actingAs($admin)->post(route('saas.impersonate', $tenant));

        $this->get(route('admin.dashboard'))->assertOk();
    });

    test('a company admin cannot start impersonation', function () {
        $tenant = companyAdmin();
        $other = companyAdmin();

        actingAs($tenant)
            ->post(route('saas.impersonate', $other))
            ->assertForbidden();
    });

    test('a saas admin cannot impersonate another saas admin', function () {
        $admin = saasAdmin();
        $otherAdmin = User::factory()->create(['is_saas_admin' => true]);

        actingAs($admin)
            ->post(route('saas.impersonate', $otherAdmin))
            ->assertForbidden();

        expect(session()->has(Impersonation::SESSION_KEY))->toBeFalse();
    });

    test('a saas admin cannot impersonate a user without a company', function () {
        $admin = saasAdmin();
        $orphan = User::factory()->create(['is_saas_admin' => false, 'company_id' => null]);

        actingAs($admin)
            ->post(route('saas.impersonate', $orphan))
            ->assertForbidden();
    });

    test('stop is a safe no-op when not impersonating', function () {
        actingAs(saasAdmin())
            ->post(route('impersonate.stop'))
            ->assertRedirect(route('saas.companies'));
    });
});
