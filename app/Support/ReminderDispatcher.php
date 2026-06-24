<?php

namespace App\Support;

use App\Enums\ReminderResourceType;
use App\Mail\ExpiryReminderMail;
use App\Models\ClientService;
use App\Models\Company;
use App\Models\Domain;
use App\Models\ReminderLog;
use App\Models\ReminderRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Mail;

/**
 * What: The reusable engine that evaluates reminder rules against expiring resources, sends the emails, and
 *       writes the dedupe/audit log rows.
 * Why: Both the daily `reminders:send` command and the manual "send reminder now" action need identical
 *       logic (render template → mail client/admin → log), so it lives here once. Because it runs in the
 *       console (no authenticated tenant) it scopes EVERY query explicitly by company and bypasses the
 *       `BelongsToCompany` global scope with `withoutGlobalScopes()` — the same pattern the seeders use —
 *       so it never silently filters all rows nor leaks across tenants.
 * When: `dispatchForCompany()` is called per company by the scheduled command; `sendNow()` is called by the
 *       Services/Domains manual action for a single resource.
 */
class ReminderDispatcher
{
    /**
     * What: Run every active rule for one company against today's expiry windows.
     * Why: For each active rule we find resources whose remaining days exactly equal `days_before`, then send
     *      the client and/or admin copy — skipping any (resource, interval, channel) already logged so a
     *      second run the same day (or after the command re-runs) sends nothing.
     * When: Called once per company by the scheduled command.
     *
     * @return int The number of reminder emails sent.
     */
    public function dispatchForCompany(Company $company): int
    {
        $sent = 0;

        foreach (ReminderResourceType::cases() as $type) {
            $rules = ReminderRule::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->activeFor($type)
                ->get();

            foreach ($rules as $rule) {
                foreach ($this->resourcesExpiringIn($company, $type, $rule->days_before) as $resource) {
                    $sent += $this->send($company, $rule, $resource, $rule->days_before);
                }
            }
        }

        return $sent;
    }

    /**
     * What: Force a reminder for a single resource right now, across all the company's active rules of its
     *       type, ignoring the days_before window match.
     * Why: Admins sometimes need to nudge a specific client immediately ("send reminder now"); this honours
     *      the rule's channels and templates but bypasses the interval gate. It still records a log row so
     *      the send is auditable and the dedupe ledger stays consistent.
     * When: Called by the manual action on the Services/Domains detail rows.
     *
     * @return int The number of reminder emails sent.
     */
    public function sendNow(Company $company, ClientService|Domain $resource): int
    {
        $type = $resource instanceof Domain
            ? ReminderResourceType::Domain
            : ReminderResourceType::Service;

        $rules = ReminderRule::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->activeFor($type)
            ->get();

        $daysLeft = max(0, $resource->daysUntilExpiry() ?? 0);
        $sent = 0;

        foreach ($rules as $rule) {
            $sent += $this->send($company, $rule, $resource, $daysLeft, forced: true);
        }

        return $sent;
    }

    /**
     * What: Resources of `$type` in `$company` whose remaining days equal `$daysBefore`.
     * Why: A `whereDate('expires_at', today + days_before)` window is cheaper and clearer than loading every
     *      row and filtering in PHP, and matches the indexed `expires_at` column.
     * When: Called per rule by `dispatchForCompany()`.
     *
     * @return Collection<int, ClientService>|Collection<int, Domain>
     */
    protected function resourcesExpiringIn(Company $company, ReminderResourceType $type, int $daysBefore): Collection
    {
        $target = now()->startOfDay()->addDays($daysBefore)->toDateString();

        $query = $type === ReminderResourceType::Domain
            ? Domain::withoutGlobalScopes()->with('client')
            : ClientService::withoutGlobalScopes()->with(['client', 'product']);

        return $query
            ->where('company_id', $company->id)
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', $target)
            ->get();
    }

    /**
     * What: Send the client and/or admin copy for one rule + resource and log each send.
     * Why: Centralises channel handling, the dedupe guard, template rendering and logging so both entry
     *      points behave identically. A forced send skips the dedupe guard but still upserts a log row.
     * When: Called by both `dispatchForCompany()` and `sendNow()`.
     *
     * @return int Emails sent for this rule + resource.
     */
    protected function send(Company $company, ReminderRule $rule, ClientService|Domain $resource, int $daysBefore, bool $forced = false): int
    {
        $subject = ReminderTemplate::render($rule->subject, $resource);
        $body = ReminderTemplate::render($rule->body, $resource);
        $sent = 0;

        if ($rule->notify_client && ($clientEmail = $resource->client?->email)) {
            $sent += $this->deliver(
                $company, $rule, $resource, $daysBefore,
                ReminderLog::CHANNEL_CLIENT, $clientEmail, $subject, $body, $forced,
            );
        }

        if ($rule->notify_admin && ($adminEmail = $company->email)) {
            $sent += $this->deliver(
                $company, $rule, $resource, $daysBefore,
                ReminderLog::CHANNEL_ADMIN, $adminEmail, $subject, $body, $forced,
            );
        }

        return $sent;
    }

    /**
     * What: Mail one channel and write/refresh its log row, honouring the dedupe guard for scheduled sends.
     * Why: The `(remindable, days_before, channel)` unique key is the idempotency contract; a normal run
     *      checks it first, while a forced send updates `sent_at` on any existing row rather than failing the
     *      unique constraint.
     * When: Called by `send()` once per active channel.
     *
     * @return int 1 if mailed, 0 if skipped by dedupe.
     */
    protected function deliver(
        Company $company,
        ReminderRule $rule,
        ClientService|Domain $resource,
        int $daysBefore,
        string $channel,
        string $recipient,
        string $subject,
        string $body,
        bool $forced,
    ): int {
        $exists = ReminderLog::withoutGlobalScopes()
            ->where('remindable_type', $resource->getMorphClass())
            ->where('remindable_id', $resource->getKey())
            ->where('days_before', $daysBefore)
            ->where('channel', $channel)
            ->exists();

        if ($exists && ! $forced) {
            return 0;
        }

        Mail::to($recipient)->queue(new ExpiryReminderMail($subject, $body));

        // `company_id` and `remindable_*` are guarded (not in $fillable), so set them on the instance
        // directly rather than passing through the mass-assignable arrays of updateOrCreate().
        $log = ReminderLog::withoutGlobalScopes()->firstOrNew([
            'remindable_type' => $resource->getMorphClass(),
            'remindable_id' => $resource->getKey(),
            'days_before' => $daysBefore,
            'channel' => $channel,
        ]);

        $log->company_id = $company->id;
        $log->reminder_rule_id = $rule->id;
        $log->client_id = $resource->client_id;
        $log->recipient = $recipient;
        $log->sent_at = now();
        $log->save();

        return 1;
    }
}
