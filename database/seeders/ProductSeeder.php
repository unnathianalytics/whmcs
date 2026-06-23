<?php

namespace Database\Seeders;

use App\Enums\BillingCycle;
use App\Enums\ServiceStatus;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * What: Seeds a demo product catalog (3 groups, 8 products with per-cycle pricing) and a handful of client
 *       services for the demo company so the Products & Services modules have data on first run.
 * Why: The idea.md development sample data calls for 3 product groups, 8 products and demo services; this
 *      gives populated lists/profile cards to verify the UI without manual entry.
 * When: Called from DatabaseSeeder after ClientSeeder. Sets `company_id` explicitly because the seeder
 *       runs without an authenticated user (the BelongsToCompany scope no-ops there).
 */
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('slug', 'demo-company')->first();

        if ($company === null) {
            return;
        }

        $catalog = [
            'Shared Hosting' => ['Starter Hosting', 'Business Hosting', 'Pro Hosting'],
            'VPS' => ['VPS Small', 'VPS Medium', 'VPS Large'],
            'Email & SSL' => ['Email Hosting', 'SSL Certificate'],
        ];

        $products = collect();

        foreach ($catalog as $groupName => $productNames) {
            $group = ProductGroup::create([
                'company_id' => $company->id,
                'name' => $groupName,
                'slug' => Str::slug($groupName),
                'description' => "Demo {$groupName} plans.",
                'sort_order' => 0,
                'is_active' => true,
            ]);

            foreach ($productNames as $productName) {
                $product = Product::create([
                    'company_id' => $company->id,
                    'product_group_id' => $group->id,
                    'name' => $productName,
                    'description' => "{$productName} — demo product.",
                    'setup_fee' => 0,
                    'is_active' => true,
                ]);

                foreach ([BillingCycle::Monthly, BillingCycle::Annual] as $cycle) {
                    $product->pricings()->create([
                        'company_id' => $company->id,
                        'cycle' => $cycle,
                        'price' => $cycle === BillingCycle::Annual ? 4999 : 499,
                        'currency' => 'INR',
                    ]);
                }

                $products->push($product);
            }
        }

        // Assign a few services to the demo clients, including some nearing expiry so the urgency
        // colours are visible out of the box.
        $clients = Client::where('company_id', $company->id)->take(5)->get();

        foreach ($clients as $index => $client) {
            $product = $products->random();

            ClientService::create([
                'company_id' => $company->id,
                'client_id' => $client->id,
                'product_id' => $product->id,
                'label' => "service-{$client->id}.demo.test",
                'status' => ServiceStatus::Active,
                'billing_cycle' => BillingCycle::Annual,
                'price' => 4999,
                'currency' => 'INR',
                'starts_at' => now()->subMonths(11),
                // Stagger expiries: a couple are due within a week/month, the rest later.
                'expires_at' => now()->addDays([5, 20, 60, 120, 300][$index] ?? 90),
                'next_due_date' => now()->addDays([5, 20, 60, 120, 300][$index] ?? 90),
            ]);
        }
    }
}
