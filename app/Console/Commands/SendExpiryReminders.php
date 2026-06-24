<?php

namespace App\Console\Commands;

use App\Enums\DomainStatus;
use App\Enums\ServiceStatus;
use App\Models\ClientService;
use App\Models\Company;
use App\Models\Domain;
use App\Support\ReminderDispatcher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * What: The daily job that sends expiry reminders for every tenant and auto-expires lapsed resources.
 * Why: idea.md §6 specifies a single scheduled command that scans all `expires_at` values, dispatches the
 *      configured reminders (deduped via reminder_logs), and — per the Phase 6 deferral — flips Active
 *      services/domains whose expiry has passed to Expired. It runs without an authenticated tenant, so it
 *      iterates companies explicitly and the dispatcher/auto-expiry queries bypass the tenant global scope.
 * When: Scheduled daily at 08:00 (routes/console.php). Also runnable on demand: `php artisan reminders:send`.
 */
#[Signature('reminders:send')]
#[Description('Send configured expiry reminders and auto-expire lapsed services and domains.')]
class SendExpiryReminders extends Command
{
    public function handle(ReminderDispatcher $dispatcher): int
    {
        $sent = 0;

        Company::query()->each(function (Company $company) use ($dispatcher, &$sent): void {
            $sent += $dispatcher->dispatchForCompany($company);
        });

        $expired = $this->autoExpire();

        $this->components->info("Sent {$sent} reminder(s); auto-expired {$expired} resource(s).");

        return self::SUCCESS;
    }

    /**
     * What: Flip Active services and domains whose `expires_at` has passed to the Expired status.
     * Why: Status was manual through Phase 6; this is the promised daily auto-expiry. Updating each model
     *      (rather than a mass `update()`) keeps the activity log entries the LogsActivity trait writes.
     *      Cancelled/Suspended/already-Expired rows are left untouched.
     * When: Called at the end of every `reminders:send` run.
     *
     * @return int The number of resources expired.
     */
    protected function autoExpire(): int
    {
        $count = 0;
        $today = now()->startOfDay()->toDateString();

        ClientService::withoutGlobalScopes()
            ->where('status', ServiceStatus::Active)
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<', $today)
            ->each(function (ClientService $service) use (&$count): void {
                $service->update(['status' => ServiceStatus::Expired]);
                $count++;
            });

        Domain::withoutGlobalScopes()
            ->where('status', DomainStatus::Active)
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<', $today)
            ->each(function (Domain $domain) use (&$count): void {
                $domain->update(['status' => DomainStatus::Expired]);
                $count++;
            });

        return $count;
    }
}
