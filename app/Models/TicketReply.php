<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\TicketReplyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * What: A single message in a ticket thread, authored by an admin `User`.
 * Why: A ticket is a conversation; each reply is either a client-facing message or a private internal note
 *      (`is_internal_note`). There is no client portal in v1, so every reply is admin-authored. Files
 *      attached to a reply live in `ticket_attachments`. Tenant isolation is automatic via
 *      `BelongsToCompany`.
 * When: Created on the thread page (and as the opening message when a ticket is created); read when
 *       rendering the thread.
 *
 * @property int $id
 * @property int $company_id
 * @property int $ticket_id
 * @property int|null $user_id
 * @property string $body
 * @property bool $is_internal_note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TicketReply extends Model
{
    /** @use HasFactory<TicketReplyFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'body',
        'is_internal_note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_internal_note' => 'boolean',
        ];
    }

    /**
     * What: Delete each attachment's stored file when the reply is removed.
     * Why: Cascading the DB rows would orphan the underlying files on disk; this hook removes them so a
     *      deleted reply leaves nothing behind. Runs before the cascade clears the attachment rows.
     * When: Booted once per model lifecycle; fires on `delete()` of a reply.
     */
    protected static function booted(): void
    {
        static::deleting(function (TicketReply $reply): void {
            $reply->attachments()->each(function (TicketAttachment $attachment): void {
                Storage::disk($attachment->disk)->delete($attachment->path);
                $attachment->delete();
            });
        });
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<TicketAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }
}
