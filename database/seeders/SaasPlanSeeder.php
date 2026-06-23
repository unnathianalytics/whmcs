<?php

namespace Database\Seeders;

use App\Models\SaasPlan;
use Illuminate\Database\Seeder;

/**
 * What: Seeds the three default SaaS subscription tiers.
 * Why: The platform needs sellable plans before any company can be subscribed; the JSON limits
 *      establish the per-tenant capacity model used in later phases.
 * When: Run from DatabaseSeeder during initial setup and on `migrate:fresh --seed`.
 */
class SaasPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'price' => 9.00,
                'interval' => 'monthly',
                'limits' => ['max_clients' => 50, 'max_admins' => 2],
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'price' => 29.00,
                'interval' => 'monthly',
                'limits' => ['max_clients' => 500, 'max_admins' => 10],
            ],
            [
                'name' => 'Agency',
                'slug' => 'agency',
                'price' => 99.00,
                'interval' => 'monthly',
                'limits' => ['max_clients' => null, 'max_admins' => null],
            ],
        ];

        foreach ($plans as $plan) {
            SaasPlan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
