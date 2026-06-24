<?php

use App\Livewire\Saas\Companies\Index;
use App\Livewire\Saas\Companies\Show;
use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\SaasPlan;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

describe('company detail access', function () {
    test('saas admin can reach a company detail page', function () {
        $company = Company::factory()->create();

        actingAs(saasAdmin())->get(route('saas.companies.show', $company))->assertOk();
    });

    test('company admin cannot reach company detail pages', function () {
        $company = Company::factory()->create();

        actingAs(companyAdmin())->get(route('saas.companies.show', $company))->assertForbidden();
    });
});

describe('company management', function () {
    test('a saas admin can edit contact details', function () {
        $company = Company::factory()->create(['name' => 'Old Name']);

        actingAs(saasAdmin());

        Livewire::test(Show::class, ['company' => $company])
            ->set('name', 'New Name')
            ->set('email', 'new@tenant.test')
            ->call('saveDetails')
            ->assertHasNoErrors();

        expect($company->fresh()->name)->toBe('New Name')
            ->and($company->fresh()->email)->toBe('new@tenant.test');
    });

    test('suspending toggles the suspended state', function () {
        $company = Company::factory()->create(['suspended_at' => null]);

        actingAs(saasAdmin());

        Livewire::test(Show::class, ['company' => $company])->call('toggleSuspend');

        expect($company->fresh()->isSuspended())->toBeTrue();
    });

    test('deleting requires the typed name to match', function () {
        $company = Company::factory()->create(['name' => 'Acme']);

        actingAs(saasAdmin());

        Livewire::test(Show::class, ['company' => $company])
            ->set('deleteConfirmation', 'Wrong')
            ->call('delete')
            ->assertHasErrors('deleteConfirmation');

        expect($company->fresh()->trashed())->toBeFalse();
    });

    test('a saas admin can soft-delete a company with a matching name', function () {
        $company = Company::factory()->create(['name' => 'Acme']);

        actingAs(saasAdmin());

        Livewire::test(Show::class, ['company' => $company])
            ->set('deleteConfirmation', 'Acme')
            ->call('delete')
            ->assertHasNoErrors();

        expect($company->fresh()->trashed())->toBeTrue();
    });

    test('soft-deleted companies disappear from the company list', function () {
        $company = Company::factory()->create();
        $company->delete();

        actingAs(saasAdmin());

        Livewire::test(Index::class)
            ->assertDontSee($company->name);
    });
});

describe('subscription assignment', function () {
    test('assigning a plan creates a subscription and syncs companies.plan_id', function () {
        $company = Company::factory()->create(['plan_id' => null]);
        $plan = SaasPlan::factory()->create(['is_active' => true]);

        actingAs(saasAdmin());

        Livewire::test(Show::class, ['company' => $company])
            ->set('planId', $plan->id)
            ->set('status', 'active')
            ->set('startsAt', now()->toDateString())
            ->set('endsAt', now()->addMonth()->toDateString())
            ->call('saveSubscription')
            ->assertHasNoErrors();

        $company->refresh();

        expect($company->plan_id)->toBe($plan->id)
            ->and($company->subscription)->not->toBeNull()
            ->and($company->subscription->status)->toBe('active');
    });

    test('an existing subscription is updated rather than duplicated', function () {
        $plan = SaasPlan::factory()->create(['is_active' => true]);
        $company = Company::factory()->create(['plan_id' => $plan->id]);
        CompanySubscription::factory()->create([
            'company_id' => $company->id,
            'saas_plan_id' => $plan->id,
            'status' => 'trialing',
        ]);

        actingAs(saasAdmin());

        Livewire::test(Show::class, ['company' => $company])
            ->set('planId', $plan->id)
            ->set('status', 'active')
            ->call('saveSubscription')
            ->assertHasNoErrors();

        expect($company->subscriptions()->count())->toBe(1)
            ->and($company->fresh()->subscription->status)->toBe('active');
    });

    test('cancelling a subscription blocks the tenant from the admin area', function () {
        $plan = SaasPlan::factory()->create(['is_active' => true]);
        $company = Company::factory()->create([
            'plan_id' => $plan->id,
            'trial_ends_at' => now()->subDay(),
        ]);
        CompanySubscription::factory()->create([
            'company_id' => $company->id,
            'saas_plan_id' => $plan->id,
            'status' => 'active',
            'ends_at' => now()->addMonth(),
        ]);
        $tenantUser = User::factory()->create([
            'is_saas_admin' => false,
            'company_id' => $company->id,
        ]);

        // Tenant has access while active.
        actingAs($tenantUser)->get(route('admin.dashboard'))->assertOk();

        // SaaS admin cancels the subscription.
        actingAs(saasAdmin());
        Livewire::test(Show::class, ['company' => $company])
            ->set('planId', $plan->id)
            ->set('status', 'cancelled')
            ->set('startsAt', now()->subMonth()->toDateString())
            ->set('endsAt', now()->subDay()->toDateString())
            ->call('saveSubscription')
            ->assertHasNoErrors();

        // Tenant is now locked out (re-fetch so the relation cache from the first request is dropped).
        actingAs($tenantUser->fresh())->get(route('admin.dashboard'))->assertForbidden();
    });
});
