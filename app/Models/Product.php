<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * What: A sellable plan within a product group (e.g. "Pro Hosting"), with an optional setup fee.
 * Why: Products are what clients subscribe to; their per-cycle prices live in `product_pricings` so one
 *      product can be offered monthly, annually, etc. Tenant isolation is automatic via `BelongsToCompany`.
 * When: Managed by company admins from `/admin/products`; referenced when assigning a client service.
 *
 * @property int $id
 * @property int $company_id
 * @property int $product_group_id
 * @property string $name
 * @property string|null $description
 * @property string $setup_fee
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use BelongsToCompany, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'product_group_id',
        'name',
        'description',
        'setup_fee',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'setup_fee' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ProductGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(ProductGroup::class, 'product_group_id');
    }

    /**
     * @return HasMany<ProductPricing, $this>
     */
    public function pricings(): HasMany
    {
        return $this->hasMany(ProductPricing::class);
    }

    /**
     * @return HasMany<ClientService, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(ClientService::class);
    }

    /**
     * What: Configure the spatie activity log for products.
     * Why: Product changes affect what is sold and how it is priced; track the editable fields only.
     * When: Invoked automatically by the LogsActivity trait on create/update/delete.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['product_group_id', 'name', 'description', 'setup_fee', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
