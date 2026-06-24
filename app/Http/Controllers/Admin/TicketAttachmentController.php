<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TicketAttachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * What: Streams a ticket reply attachment back to an authorized admin as a download.
 * Why: Attachments live on the private `local` disk and are never web-served; this route is the only way to
 *      retrieve one. Authorization runs against the parent ticket's `view` policy so an admin can only fetch
 *      attachments on their own company's tickets. Route-model binding is tenant-scoped by the
 *      BelongsToCompany global scope, so cross-tenant ids 404 before the gate even runs.
 * When: Hit at `/admin/ticket-attachments/{attachment}/download` from a link in the ticket thread.
 */
class TicketAttachmentController extends Controller
{
    public function __invoke(TicketAttachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment->reply->ticket);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }
}
