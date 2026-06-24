<?php

use App\Enums\DomainStatus;
use App\Enums\ReminderResourceType;
use App\Enums\ServiceStatus;
use App\Livewire\Admin\Domains\Index as DomainsIndex;
use App\Livewire\Admin\Reminders\Index;
use App\Livewire\Admin\Services\Index as ServicesIndex;
use App\Mail\ExpiryReminderMail;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\Domain;
use App\Models\ReminderLog;
use App\Models\ReminderRule;
use App\Support\ReminderTemplate;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;

describe('tenant isolation', function () {
    test('the company scope hides reminder rules belonging to another company', function () {
        $admin = grantPermissions(companyAdmin(), ['reminders.view']);
        $other = companyAdmin();

        ReminderRule::factory()->count(2)->create(['company_id' => $admin->company_id]);
        ReminderRule::factory()->count(3)->create(['company_id' => $other->company_id]);

        actingAs($admin);

        expect(ReminderRule::count())->toBe(2);
    });

    test('a rule created via the form auto-stamps the company id', function () {
        $admin = grantPermissions(companyAdmin(), ['reminders.view', 'reminders.manage']);

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('openCreateModal')
            ->set('resourceType', ReminderResourceType::Service->value)
            ->set('daysBefore', '7')
            ->set('subject', 'Heads up')
            ->set('body', 'Body')
            ->call('save')
            ->assertHasNoErrors();

        $rule = ReminderRule::withoutGlobalScopes()->firstWhere('subject', 'Heads up');

        expect($rule)->not->toBeNull()
            ->and($rule->company_id)->toBe($admin->company_id)
            ->and($rule->days_before)->toBe(7);
    });
});

describe('access control', function () {
    test('a company admin without reminders.view is forbidden the screen', function () {
        actingAs(companyAdmin())
            ->get(route('admin.reminders'))
            ->assertForbidden();
    });

    test('a company admin with reminders.view can see the screen', function () {
        actingAs(grantPermissions(companyAdmin(), ['reminders.view']))
            ->get(route('admin.reminders'))
            ->assertOk();
    });

    test('creating a rule without reminders.manage is forbidden', function () {
        actingAs(grantPermissions(companyAdmin(), ['reminders.view']));

        Livewire::test(Index::class)
            ->call('openCreateModal')
            ->assertForbidden();
    });

    test('deleting a rule without reminders.manage is forbidden', function () {
        $admin = grantPermissions(companyAdmin(), ['reminders.view']);
        $rule = ReminderRule::factory()->create(['company_id' => $admin->company_id]);

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('confirmDelete', $rule->id)
            ->assertForbidden();
    });
});

describe('rule validation & crud', function () {
    test('resource type, days before, subject and body are required', function () {
        actingAs(grantPermissions(companyAdmin(), ['reminders.view', 'reminders.manage']));

        Livewire::test(Index::class)
            ->call('openCreateModal')
            ->set('daysBefore', '')
            ->set('subject', '')
            ->set('body', '')
            ->call('save')
            ->assertHasErrors(['daysBefore', 'subject', 'body']);
    });

    test('editing a rule updates its fields', function () {
        $admin = grantPermissions(companyAdmin(), ['reminders.view', 'reminders.manage']);
        $rule = ReminderRule::factory()->create([
            'company_id' => $admin->company_id,
            'days_before' => 30,
        ]);

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('openEditModal', $rule->id)
            ->set('daysBefore', '14')
            ->set('notifyAdmin', true)
            ->call('save')
            ->assertHasNoErrors();

        $rule->refresh();

        expect($rule->days_before)->toBe(14)
            ->and($rule->notify_admin)->toBeTrue();
    });

    test('deleting a rule soft-deletes it', function () {
        $admin = grantPermissions(companyAdmin(), ['reminders.view', 'reminders.manage']);
        $rule = ReminderRule::factory()->create(['company_id' => $admin->company_id]);

        actingAs($admin);

        Livewire::test(Index::class)
            ->call('confirmDelete', $rule->id)
            ->call('delete')
            ->assertHasNoErrors();

        expect(ReminderRule::count())->toBe(0)
            ->and(ReminderRule::withTrashed()->count())->toBe(1);
    });
});

describe('template substitution', function () {
    test('service variables resolve product name, dates and days left', function () {
        $client = Client::factory()->create(['name' => 'Acme Ltd']);
        $service = ClientService::factory()->for($client)->create([
            'label' => 'Gold Hosting',
            'expires_at' => now()->addDays(7)->toDateString(),
        ]);

        $rendered = ReminderTemplate::render(
            '{client_name}: {product_name} expires {expires_at} ({days_left})',
            $service->load('client'),
        );

        expect($rendered)->toContain('Acme Ltd')
            ->toContain('Gold Hosting')
            ->toContain('7');
    });

    test('domain variables resolve the domain name', function () {
        $client = Client::factory()->create(['name' => 'Acme Ltd']);
        $domain = Domain::factory()->for($client)->create([
            'domain_name' => 'acme.com',
            'expires_at' => now()->addDays(3)->toDateString(),
        ]);

        $rendered = ReminderTemplate::render('{domain_name} for {client_name}', $domain->load('client'));

        expect($rendered)->toBe('acme.com for Acme Ltd');
    });
});

describe('reminders:send command', function () {
    test('it queues a client mail for a service expiring on the rule interval and logs it', function () {
        Mail::fake();

        $admin = companyAdmin();
        $client = Client::factory()->for($admin->company)->create(['email' => 'client@x.test']);
        $service = ClientService::factory()->for($client)->create([
            'company_id' => $admin->company_id,
            'status' => ServiceStatus::Active,
            'expires_at' => now()->addDays(7)->toDateString(),
        ]);
        ReminderRule::factory()->daysBefore(7)->create(['company_id' => $admin->company_id]);

        artisan('reminders:send')->assertSuccessful();

        Mail::assertQueued(ExpiryReminderMail::class, 1);
        expect(ReminderLog::withoutGlobalScopes()->where('channel', 'client')->count())->toBe(1);
    });

    test('it also notifies the company admin email when notify_admin is set', function () {
        Mail::fake();

        $admin = companyAdmin();
        $admin->company->update(['email' => 'owner@x.test']);
        $client = Client::factory()->for($admin->company)->create(['email' => 'client@x.test']);
        Domain::factory()->for($client)->create([
            'company_id' => $admin->company_id,
            'status' => DomainStatus::Active,
            'expires_at' => now()->addDays(14)->toDateString(),
        ]);
        ReminderRule::factory()->forDomains()->daysBefore(14)->notifyingAdmin()
            ->create(['company_id' => $admin->company_id]);

        artisan('reminders:send')->assertSuccessful();

        Mail::assertQueued(ExpiryReminderMail::class, 2);
        expect(ReminderLog::withoutGlobalScopes()->where('channel', 'admin')->count())->toBe(1);
    });

    test('a second run sends nothing for the same interval (dedupe)', function () {
        Mail::fake();

        $admin = companyAdmin();
        $client = Client::factory()->for($admin->company)->create(['email' => 'client@x.test']);
        ClientService::factory()->for($client)->create([
            'company_id' => $admin->company_id,
            'status' => ServiceStatus::Active,
            'expires_at' => now()->addDays(7)->toDateString(),
        ]);
        ReminderRule::factory()->daysBefore(7)->create(['company_id' => $admin->company_id]);

        artisan('reminders:send')->assertSuccessful();
        artisan('reminders:send')->assertSuccessful();

        Mail::assertQueued(ExpiryReminderMail::class, 1);
    });

    test('inactive rules and non-matching intervals send nothing', function () {
        Mail::fake();

        $admin = companyAdmin();
        $client = Client::factory()->for($admin->company)->create(['email' => 'client@x.test']);
        ClientService::factory()->for($client)->create([
            'company_id' => $admin->company_id,
            'status' => ServiceStatus::Active,
            'expires_at' => now()->addDays(7)->toDateString(),
        ]);
        // Right interval but inactive.
        ReminderRule::factory()->daysBefore(7)->inactive()->create(['company_id' => $admin->company_id]);
        // Active but wrong interval.
        ReminderRule::factory()->daysBefore(30)->create(['company_id' => $admin->company_id]);

        artisan('reminders:send')->assertSuccessful();

        Mail::assertNothingQueued();
    });

    test('notify_client false skips the client mail', function () {
        Mail::fake();

        $admin = companyAdmin();
        $client = Client::factory()->for($admin->company)->create(['email' => 'client@x.test']);
        ClientService::factory()->for($client)->create([
            'company_id' => $admin->company_id,
            'status' => ServiceStatus::Active,
            'expires_at' => now()->addDays(7)->toDateString(),
        ]);
        ReminderRule::factory()->daysBefore(7)->create([
            'company_id' => $admin->company_id,
            'notify_client' => false,
            'notify_admin' => false,
        ]);

        artisan('reminders:send')->assertSuccessful();

        Mail::assertNothingQueued();
    });
});

describe('auto-expiry', function () {
    test('it flips active past-due services and domains to expired and leaves others', function () {
        Mail::fake();

        $admin = companyAdmin();
        $client = Client::factory()->for($admin->company)->create();

        $pastService = ClientService::factory()->for($client)->create([
            'company_id' => $admin->company_id,
            'status' => ServiceStatus::Active,
            'expires_at' => now()->subDay()->toDateString(),
        ]);
        $futureService = ClientService::factory()->for($client)->create([
            'company_id' => $admin->company_id,
            'status' => ServiceStatus::Active,
            'expires_at' => now()->addDay()->toDateString(),
        ]);
        $cancelled = ClientService::factory()->for($client)->create([
            'company_id' => $admin->company_id,
            'status' => ServiceStatus::Cancelled,
            'expires_at' => now()->subDay()->toDateString(),
        ]);
        $pastDomain = Domain::factory()->for($client)->create([
            'company_id' => $admin->company_id,
            'status' => DomainStatus::Active,
            'expires_at' => now()->subDay()->toDateString(),
        ]);

        artisan('reminders:send')->assertSuccessful();

        expect($pastService->refresh()->status)->toBe(ServiceStatus::Expired)
            ->and($futureService->refresh()->status)->toBe(ServiceStatus::Active)
            ->and($cancelled->refresh()->status)->toBe(ServiceStatus::Cancelled)
            ->and($pastDomain->refresh()->status)->toBe(DomainStatus::Expired);
    });
});

describe('manual send now', function () {
    test('it forces a service reminder regardless of interval and logs it', function () {
        Mail::fake();

        $admin = grantPermissions(companyAdmin(), ['services.view', 'reminders.view', 'reminders.manage']);
        $client = Client::factory()->for($admin->company)->create(['email' => 'client@x.test']);
        $service = ClientService::factory()->for($client)->create([
            'company_id' => $admin->company_id,
            'expires_at' => now()->addDays(99)->toDateString(),
        ]);
        ReminderRule::factory()->daysBefore(7)->create(['company_id' => $admin->company_id]);

        actingAs($admin);

        Livewire::test(ServicesIndex::class)
            ->call('sendReminder', $service->id)
            ->assertHasNoErrors();

        Mail::assertQueued(ExpiryReminderMail::class, 1);
        expect(ReminderLog::withoutGlobalScopes()->count())->toBe(1);
    });

    test('manual send is forbidden without reminders.manage', function () {
        $admin = grantPermissions(companyAdmin(), ['domains.view', 'reminders.view']);
        $domain = Domain::factory()->for(Client::factory()->for($admin->company))->create([
            'company_id' => $admin->company_id,
        ]);

        actingAs($admin);

        Livewire::test(DomainsIndex::class)
            ->call('sendReminder', $domain->id)
            ->assertForbidden();
    });
});

describe('renewal re-arms reminders', function () {
    test('renewing a domain clears its prior reminder logs', function () {
        $admin = grantPermissions(companyAdmin(), ['domains.view', 'domains.update']);
        $domain = Domain::factory()->for(Client::factory()->for($admin->company))->create([
            'company_id' => $admin->company_id,
        ]);
        ReminderLog::factory()->create([
            'company_id' => $admin->company_id,
            'remindable_type' => $domain->getMorphClass(),
            'remindable_id' => $domain->id,
            'client_id' => $domain->client_id,
        ]);

        actingAs($admin);

        Livewire::test(DomainsIndex::class)
            ->call('openRenewModal', $domain->id)
            ->set('renewExpiresAt', now()->addYear()->toDateString())
            ->call('renew')
            ->assertHasNoErrors();

        expect(ReminderLog::withoutGlobalScopes()->count())->toBe(0);
    });
});
