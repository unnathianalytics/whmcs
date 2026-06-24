<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * What: One tenant-scoped key/value setting row (JSON-encoded value).
 * Why: Backs the per-company configuration store (currency, invoice prefix, SMTP, timezone, gateway
 *      keys, reminder defaults). The `value` column is JSON so a single table holds strings, bools,
 *      ints, and arrays. Tenant isolation is automatic via `BelongsToCompany`. Not activity-logged —
 *      config churn would be noise and gateway secrets must never land in the audit trail.
 * When: Created/updated only through the CompanySettings service; never edited directly by callers.
 *
 * @property int $id
 * @property int $company_id
 * @property string $key
 * @property mixed $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Setting extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }
}
