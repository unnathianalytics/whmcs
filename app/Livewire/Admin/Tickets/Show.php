<?php

namespace App\Livewire\Admin\Tickets;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\TicketReply;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * What: The ticket thread/detail page — read the conversation, post replies/notes with attachments, and
 *      manage the ticket header (status, priority, department, assignee).
 * Why: A ticket is a conversation, so (unlike simple CRUD) it gets a dedicated page. Replies are
 *      admin-authored; an `is_internal_note` reply is a private note that never advances the ticket. Posting
 *      a public reply bumps `last_reply_at` and moves an Open ticket to Answered. Tenant isolation is
 *      automatic: route-model binding resolves only same-company tickets via the BelongsToCompany scope.
 * When: Rendered at `/admin/tickets/{ticket}` for company admins holding `tickets.view`.
 */
#[Title('Ticket')]
class Show extends Component
{
    use WithFileUploads;

    public Ticket $ticket;

    // --- Reply composer ---
    public string $body = '';

    public bool $isInternalNote = false;

    /**
     * Files staged on the reply composer before posting.
     *
     * @var array<int, TemporaryUploadedFile>
     */
    public array $attachments = [];

    // --- Header edit modal ---
    public bool $showHeaderModal = false;

    public string $status = '';

    public string $priority = '';

    public string $departmentId = '';

    public string $assignedTo = '';

    // --- Delete reply modal ---
    public bool $showDeleteReplyModal = false;

    public ?int $deletingReplyId = null;

    /**
     * What: Bind the ticket and authorize viewing it.
     * Why: The page is gated on `tickets.view`; binding is already tenant-scoped by the global scope.
     * When: On component mount.
     */
    public function mount(Ticket $ticket): void
    {
        $this->authorize('view', $ticket);
        $this->ticket = $ticket;
    }

    // =========================================================================
    // Replies
    // =========================================================================

    /**
     * What: Post a reply or internal note, store any attachments, and advance the ticket when appropriate.
     * Why: A public reply represents admin action on the ticket, so it bumps `last_reply_at` and moves an
     *      Open ticket to Answered; an internal note is private and leaves status/activity untouched. Files
     *      are stored on the private `local` disk and recorded as attachment rows for the download route.
     * When: Triggered on submit of the reply composer.
     */
    public function postReply(): void
    {
        $this->authorize('update', $this->ticket);

        $validated = $this->validate([
            'body' => ['required', 'string', 'max:5000'],
            'isInternalNote' => ['boolean'],
            'attachments' => ['array', 'max:5'],
            'attachments.*' => ['file', 'max:5120', 'mimes:jpg,jpeg,png,gif,webp,pdf,txt,log,csv,zip'],
        ]);

        $reply = $this->ticket->replies()->make([
            'user_id' => auth()->id(),
            'body' => $validated['body'],
            'is_internal_note' => $validated['isInternalNote'],
        ]);
        $reply->company_id = $this->ticket->company_id;
        $reply->save();

        foreach ($this->attachments as $upload) {
            $path = $upload->store('tickets/'.$this->ticket->id, 'local');

            $reply->attachments()->create([
                'company_id' => $this->ticket->company_id,
                'disk' => 'local',
                'path' => $path,
                'original_name' => $upload->getClientOriginalName(),
                'mime_type' => $upload->getMimeType(),
                'size' => $upload->getSize(),
            ]);
        }

        // A public reply advances the ticket; an internal note must not.
        if (! $reply->is_internal_note) {
            $this->ticket->last_reply_at = now();

            if ($this->ticket->status === TicketStatus::Open) {
                $this->ticket->status = TicketStatus::Answered;
            }

            $this->ticket->save();
        }

        $this->ticket->refresh();
        $this->reset(['body', 'isInternalNote', 'attachments']);
        unset($this->replies);

        Flux::toast(variant: 'success', text: __('Reply posted.'));
    }

    public function confirmDeleteReply(int $replyId): void
    {
        $this->authorize('update', $this->ticket);
        $this->deletingReplyId = $replyId;
        $this->showDeleteReplyModal = true;
    }

    /**
     * What: Remove a reply (and its stored attachment files) from the thread.
     * Why: Lets admins retract a mistaken reply/note; the model's deleting hook cleans up the files so
     *      nothing is orphaned on disk. The opening message can be removed too — the ticket keeps its subject.
     * When: Triggered on confirm of the delete-reply modal.
     */
    public function deleteReply(): void
    {
        $this->authorize('update', $this->ticket);
        $this->ticket->replies()->findOrFail($this->deletingReplyId)->delete();

        $this->showDeleteReplyModal = false;
        $this->deletingReplyId = null;
        unset($this->replies);

        Flux::toast(variant: 'success', text: __('Reply removed.'));
    }

    // =========================================================================
    // Header
    // =========================================================================

    public function openHeaderModal(): void
    {
        $this->authorize('update', $this->ticket);

        $this->status = $this->ticket->status->value;
        $this->priority = $this->ticket->priority->value;
        $this->departmentId = (string) ($this->ticket->department_id ?? '');
        $this->assignedTo = (string) ($this->ticket->assigned_to ?? '');

        $this->resetValidation();
        $this->showHeaderModal = true;
    }

    /**
     * What: Persist the editable header fields and stamp/clear `closed_at` on a status change.
     * Why: Lets admins re-route, reassign, reprioritise or close a ticket. Moving to Closed records when it
     *      was resolved; moving away clears that stamp so reopened tickets aren't shown as closed.
     * When: Triggered on submit of the header modal.
     */
    public function saveHeader(): void
    {
        $this->authorize('update', $this->ticket);

        $validated = $this->validate([
            'status' => ['required', Rule::enum(TicketStatus::class)],
            'priority' => ['required', Rule::enum(TicketPriority::class)],
            'departmentId' => ['nullable', Rule::exists('ticket_departments', 'id')],
            'assignedTo' => ['nullable', Rule::exists('users', 'id')],
        ]);

        $isClosing = $validated['status'] === TicketStatus::Closed->value;

        $this->ticket->update([
            'status' => $validated['status'],
            'priority' => $validated['priority'],
            'department_id' => $validated['departmentId'] !== '' ? (int) $validated['departmentId'] : null,
            'assigned_to' => $validated['assignedTo'] !== '' ? (int) $validated['assignedTo'] : null,
            'closed_at' => $isClosing ? ($this->ticket->closed_at ?? now()) : null,
        ]);

        $this->ticket->refresh();
        $this->showHeaderModal = false;

        Flux::toast(variant: 'success', text: __('Ticket updated.'));
    }

    // =========================================================================
    // Computed data
    // =========================================================================

    /**
     * @return Collection<int, TicketReply>
     */
    #[Computed]
    public function replies(): Collection
    {
        return $this->ticket->replies()->with(['author', 'attachments'])->get();
    }

    /**
     * @return Collection<int, TicketDepartment>
     */
    #[Computed]
    public function departments(): Collection
    {
        return TicketDepartment::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function admins(): Collection
    {
        return User::query()
            ->where('company_id', auth()->user()->company_id)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @return array<int, TicketStatus>
     */
    #[Computed]
    public function statuses(): array
    {
        return TicketStatus::cases();
    }

    /**
     * @return array<int, TicketPriority>
     */
    #[Computed]
    public function priorities(): array
    {
        return TicketPriority::cases();
    }

    public function render()
    {
        return view('livewire.admin.tickets.show');
    }
}
