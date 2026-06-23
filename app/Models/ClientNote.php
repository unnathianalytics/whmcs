<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\ClientNoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * What: A private, admin-only note attached to a client.
 * Why: Admins keep internal context on a client that the client never sees; isolation is inherited from
 *      `BelongsToCompany` so notes can never leak across tenants.
 * When: Created from the client profile page; read in the client's notes panel.
 *
 * @property int $id
 * @property int $company_id
 * @property int $client_id
 * @property int|null $user_id
 * @property string $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ClientNote extends Model
{
    /** @use HasFactory<ClientNoteFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'client_id',
        'user_id',
        'body',
    ];

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
