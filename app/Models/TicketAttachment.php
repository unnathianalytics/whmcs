<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\TicketAttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * What: A file uploaded against a ticket reply.
 * Why: Admins attach screenshots/logs to a reply. The file itself lives on the private `local` disk (never
 *      web-served); this row stores its `disk`/`path` plus the original name, mime type and byte size so the
 *      authorized download route can stream it back with the right filename. Tenant isolation is automatic
 *      via `BelongsToCompany`.
 * When: Created when a reply with attachments is posted; read on the thread and the download route.
 *
 * @property int $id
 * @property int $company_id
 * @property int $ticket_reply_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string|null $mime_type
 * @property int $size
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TicketAttachment extends Model
{
    /** @use HasFactory<TicketAttachmentFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'ticket_reply_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<TicketReply, $this>
     */
    public function reply(): BelongsTo
    {
        return $this->belongsTo(TicketReply::class, 'ticket_reply_id');
    }

    /**
     * What: A human-readable file size (e.g. "1.2 MB").
     * Why: The raw byte count is shown next to each attachment in the thread; this formats it for display.
     * When: Read when rendering the attachment list.
     */
    public function humanSize(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 1).' '.$units[$i];
    }
}
