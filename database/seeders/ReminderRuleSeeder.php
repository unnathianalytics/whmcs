<?php

namespace Database\Seeders;

use App\Enums\ReminderResourceType;
use App\Models\Company;
use App\Models\ReminderRule;
use Illuminate\Database\Seeder;

/**
 * What: Seeds a sensible default set of expiry reminder rules for every company.
 * Why: A new tenant should have a working reminder cadence out of the box (30 / 14 / 7 / 1 days before
 *      expiry, for both services and domains) rather than an empty rules screen. Rules are upserted by
 *      (company, resource_type, days_before) so re-running the seeder is idempotent.
 * When: Run from DatabaseSeeder after the demo company, clients, services and domains exist.
 */
class ReminderRuleSeeder extends Seeder
{
    /** Default lead times, longest first. */
    private const DAYS = [30, 14, 7, 1];

    public function run(): void
    {
        Company::all()->each(function (Company $company): void {
            foreach (ReminderResourceType::cases() as $type) {
                foreach (self::DAYS as $days) {
                    $this->seedRule($company, $type, $days);
                }
            }
        });
    }

    /**
     * What: Upsert one default rule for a company / resource type / lead time.
     * Why: Domains use the {domain_name} token while services use {product_name}; everything else is shared.
     * When: Called for each of the four lead times per resource type per company.
     */
    private function seedRule(Company $company, ReminderResourceType $type, int $days): void
    {
        $name = $type === ReminderResourceType::Domain ? '{domain_name}' : '{product_name}';

        ReminderRule::withoutGlobalScopes()->updateOrCreate(
            [
                'company_id' => $company->id,
                'resource_type' => $type,
                'days_before' => $days,
            ],
            [
                'subject' => "{$name} expires in {days_left} days",
                'body' => "Hi {client_name},\n\nThis is a reminder that {$name} expires on {expires_at} "
                    .'— {days_left} day(s) from now. Please renew to avoid interruption.',
                'notify_client' => true,
                'notify_admin' => $days === 1, // BCC the admin only on the final notice.
                'is_active' => true,
            ],
        );
    }
}
