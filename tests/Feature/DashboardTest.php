<?php

use App\Livewire\Admin\Dashboard;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('saas admins are redirected from dashboard to the saas area', function () {
    $admin = User::factory()->create(['is_saas_admin' => true]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertRedirect(route('saas.dashboard'));
});

test('company admins are redirected from dashboard to the admin area', function () {
    $user = companyAdmin();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('admin.dashboard'));
});

describe('dashboard data wiring', function () {
    test('stats reflect the tenant own data', function () {
        $admin = companyAdmin();
        actingAs($admin);

        $client = Client::factory()->create(['company_id' => $admin->company_id]);
        ClientService::factory()->active()->count(2)->create(['client_id' => $client->id]);

        $invoice = Invoice::factory()->create(['client_id' => $client->id]);
        Transaction::factory()->create([
            'invoice_id' => $invoice->id,
            'amount' => 500,
            'paid_at' => now(),
        ]);

        $stats = Livewire::test(Dashboard::class)->instance()->stats();

        expect($stats['clients'])->toBe(1)
            ->and($stats['active_services'])->toBe(2)
            ->and($stats['revenue_this_month'])->toBe(500.0);
    });

    test('expiring soon lists services and domains within 7 days', function () {
        $admin = companyAdmin();
        actingAs($admin);

        $client = Client::factory()->create(['company_id' => $admin->company_id]);
        ClientService::factory()->active()->expiringInDays(3)->create(['client_id' => $client->id]);
        Domain::factory()->expiringInDays(5)->create(['client_id' => $client->id]);
        // Outside the window — should not appear.
        ClientService::factory()->active()->expiringInDays(40)->create(['client_id' => $client->id]);

        $rows = Livewire::test(Dashboard::class)->instance()->expiringSoon();

        expect($rows)->toHaveCount(2);
    });

    test('expired lists overdue active services and domains', function () {
        $admin = companyAdmin();
        actingAs($admin);

        $client = Client::factory()->create(['company_id' => $admin->company_id]);
        Domain::factory()->expired()->create(['client_id' => $client->id]);

        $rows = Livewire::test(Dashboard::class)->instance()->expired();

        expect($rows)->toHaveCount(1);
    });

    test('revenue series returns six months of paid totals', function () {
        $admin = companyAdmin();
        actingAs($admin);

        $client = Client::factory()->create(['company_id' => $admin->company_id]);
        $invoice = Invoice::factory()->create(['client_id' => $client->id]);
        Transaction::factory()->create([
            'invoice_id' => $invoice->id,
            'amount' => 250,
            'paid_at' => now(),
        ]);

        $series = Livewire::test(Dashboard::class)->instance()->revenueSeries();

        expect($series['labels'])->toHaveCount(6)
            ->and($series['values'])->toHaveCount(6)
            ->and(end($series['values']))->toBe(250.0);
    });

    test('dashboard data is tenant isolated', function () {
        $admin = companyAdmin();
        $other = companyAdmin();

        $otherClient = Client::factory()->create(['company_id' => $other->company_id]);
        ClientService::factory()->active()->create(['client_id' => $otherClient->id]);

        actingAs($admin);

        expect(Livewire::test(Dashboard::class)->instance()->stats()['clients'])->toBe(0);
    });
});
