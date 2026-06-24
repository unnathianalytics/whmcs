<?php

use App\Livewire\Saas\Plans\Index;
use App\Models\CompanySubscription;
use App\Models\SaasPlan;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

describe('saas plans access', function () {
    test('saas admin can reach the plans screen', function () {
        actingAs(saasAdmin())->get(route('saas.plans'))->assertOk();
    });

    test('company admin cannot reach the plans screen', function () {
        actingAs(companyAdmin())->get(route('saas.plans'))->assertForbidden();
    });
});

describe('saas plans crud', function () {
    test('a saas admin can create a plan with limits', function () {
        actingAs(saasAdmin());

        Livewire::test(Index::class)
            ->set('name', 'Enterprise')
            ->set('price', '199')
            ->set('interval', 'monthly')
            ->set('maxClients', '1000')
            ->set('maxAdmins', '')
            ->call('save')
            ->assertHasNoErrors();

        $plan = SaasPlan::where('name', 'Enterprise')->firstOrFail();

        expect($plan->slug)->toBe('enterprise')
            ->and($plan->limits['max_clients'])->toBe(1000)
            ->and($plan->limits['max_admins'])->toBeNull();
    });

    test('plan creation requires a name and numeric price', function () {
        actingAs(saasAdmin());

        Livewire::test(Index::class)
            ->set('name', '')
            ->set('price', 'free')
            ->call('save')
            ->assertHasErrors(['name' => 'required', 'price' => 'numeric']);
    });

    test('a saas admin can edit a plan', function () {
        $plan = SaasPlan::factory()->create(['name' => 'Old', 'is_active' => true]);

        actingAs(saasAdmin());

        Livewire::test(Index::class)
            ->call('openEditModal', $plan->id)
            ->set('name', 'New')
            ->set('isActive', false)
            ->call('save')
            ->assertHasNoErrors();

        expect($plan->fresh()->name)->toBe('New')
            ->and($plan->fresh()->is_active)->toBeFalse();
    });

    test('a plan with no subscriptions can be deleted', function () {
        $plan = SaasPlan::factory()->create();

        actingAs(saasAdmin());

        Livewire::test(Index::class)
            ->call('confirmDelete', $plan->id)
            ->call('delete');

        expect(SaasPlan::find($plan->id))->toBeNull();
    });

    test('a plan with subscriptions cannot be deleted', function () {
        $plan = SaasPlan::factory()->create();
        CompanySubscription::factory()->create(['saas_plan_id' => $plan->id]);

        actingAs(saasAdmin());

        Livewire::test(Index::class)
            ->call('confirmDelete', $plan->id)
            ->call('delete');

        expect(SaasPlan::find($plan->id))->not->toBeNull();
    });

    test('slugs are unique across plans of the same name', function () {
        SaasPlan::factory()->create(['name' => 'Pro', 'slug' => 'pro']);

        actingAs(saasAdmin());

        Livewire::test(Index::class)
            ->set('name', 'Pro')
            ->set('price', '29')
            ->call('save')
            ->assertHasNoErrors();

        expect(SaasPlan::where('name', 'Pro')->pluck('slug')->all())
            ->toContain('pro', 'pro-1');
    });
});
