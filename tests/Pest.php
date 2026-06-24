<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\SaasPlan;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Create a platform owner (SaaS admin) with global, cross-tenant scope.
 */
function saasAdmin(): User
{
    return User::factory()->create(['is_saas_admin' => true]);
}

/**
 * Create a company admin attached to an active, non-suspended company.
 *
 * @param  array<string, mixed>  $companyAttributes
 */
function companyAdmin(array $companyAttributes = []): User
{
    $company = Company::factory()->create(array_merge([
        'suspended_at' => null,
        'trial_ends_at' => now()->addDays(14),
    ], $companyAttributes));

    CompanySubscription::factory()->create([
        'company_id' => $company->id,
        'saas_plan_id' => SaasPlan::factory(),
        'status' => 'active',
        'ends_at' => now()->addMonth(),
    ]);

    return User::factory()->create([
        'is_saas_admin' => false,
        'company_id' => $company->id,
    ]);
}

/**
 * Grant the given permissions to a company admin within their company's team context, and bind the
 * spatie permission team id to that company for the rest of the test (mirrors EnsureCompanyAdmin,
 * which does not run in Livewire::test).
 *
 * @param  list<string>  $permissions
 */
function grantPermissions(User $user, array $permissions): User
{
    app(PermissionRegistrar::class)->setPermissionsTeamId($user->company_id);

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user->givePermissionTo($permissions);

    return $user;
}
