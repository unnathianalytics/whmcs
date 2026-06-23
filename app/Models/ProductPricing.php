<?php

namespace App\Models;

use App\Enums\BillingCycle;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\ProductPricingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * What: One price for a product on a specific billing cycle (e.g. "Pro Hosting, annual, $99.00").
 * Why: A product can be sold on several cycles at different prices; each combination is a row here.
 *      Isolation is inherited from `BelongsToCompany` so a tenant's pricing can never leak across companies.
 * When: Created/edited from the product modal; read when pre-filling a client service's price/cycle.
 *
 * @property int $id
 * @property int $company_id
 * @property int $product_id
 * @property BillingCycle $cycle
 * @property string $price
 * @property string $currency
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ProductPricing extends Model
{
    /** @use HasFactory<ProductPricingFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'product_id',
        'cycle',
        'price',
        'currency',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cycle' => BillingCycle::class,
            'price' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
