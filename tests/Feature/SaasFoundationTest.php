<?php

use App\Livewire\Saas\Companies\Index;
use App\Models\Company;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;

describe('saas admin area access', function () {
    test('saas admin can reach the saas dashboard', function () {
        $admin = User::factory()->create(['is_saas_admin' => true]);

        actingAs($admin)->get(route('saas.dashboard'))->assertOk();
    });

    test('company admin cannot reach the saas area', function () {
        actingAs(companyAdmin())->get(route('saas.dashboard'))->assertForbidden();
    });

    test('guests are redirected from the saas area to login', function () {
        $this->get(route('saas.dashboard'))->assertRedirect(route('login'));
    });
});

describe('company admin area access', function () {
    test('company admin can reach the admin dashboard', function () {
        actingAs(companyAdmin())->get(route('admin.dashboard'))->assertOk();
    });

    test('saas admin cannot reach the company admin area', function () {
        $admin = User::factory()->create(['is_saas_admin' => true]);

        actingAs($admin)->get(route('admin.dashboard'))->assertForbidden();
    });

    test('a user without a company cannot reach the admin area', function () {
        $user = User::factory()->create(['is_saas_admin' => false, 'company_id' => null]);

        actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
    });

    test('a suspended company blocks its admin', function () {
        $user = companyAdmin(['suspended_at' => now()]);

        actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
    });

    test('the permission team id is scoped to the user company on admin requests', function () {
        $user = companyAdmin();

        actingAs($user)->get(route('admin.dashboard'))->assertOk();

        expect(app(PermissionRegistrar::class)->getPermissionsTeamId())->toBe($user->company_id);
    });
});

describe('company creation', function () {
    test('a saas admin can create a company via livewire', function () {
        $admin = User::factory()->create(['is_saas_admin' => true]);

        actingAs($admin);

        Livewire::test(Index::class)
            ->set('name', 'Acme Hosting')
            ->set('email', 'ops@acme.test')
            ->call('createCompany')
            ->assertHasNoErrors();

        expect(Company::where('name', 'Acme Hosting')->exists())->toBeTrue();
    });

    test('company creation requires a name', function () {
        $admin = User::factory()->create(['is_saas_admin' => true]);

        actingAs($admin);

        Livewire::test(Index::class)
            ->set('name', '')
            ->call('createCompany')
            ->assertHasErrors(['name' => 'required']);
    });

    test('suspending a company toggles its suspended state', function () {
        $admin = User::factory()->create(['is_saas_admin' => true]);
        $company = Company::factory()->create(['suspended_at' => null]);

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('toggleSuspend', $company->id);

        expect($company->fresh()->isSuspended())->toBeTrue();
    });
});
