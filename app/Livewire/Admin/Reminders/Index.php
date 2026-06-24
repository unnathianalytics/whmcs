<?php

namespace App\Livewire\Admin\Reminders;

use App\Enums\ReminderResourceType;
use App\Models\ReminderLog;
use App\Models\ReminderRule;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * What: Company-admin screen to manage expiry reminder rules and review the sent-reminder history.
 * Why: Rules drive the daily `reminders:send` command; this is where admins configure the lead times,
 *      channels and email templates per resource type, and audit what has actually been sent. All queries
 *      are tenant-isolated automatically by the BelongsToCompany scope. Rules gate on `reminders.manage`;
 *      viewing (rules + log) gates on `reminders.view`.
 * When: Rendered at `/admin/reminders` for company admins holding `reminders.view`.
 */
#[Title('Reminders')]
class Index extends Component
{
    use WithPagination;

    /** Active tab: 'rules' | 'logs'. */
    #[Url]
    public string $tab = 'rules';

    // --- Form modal state ---
    public bool $showFormModal = false;

    public ?int $editingId = null;

    public string $resourceType = ReminderResourceType::Service->value;

    public string $daysBefore = '30';

    public string $subject = '';

    public string $body = '';

    public bool $notifyClient = true;

    public bool $notifyAdmin = false;

    public bool $isActive = true;

    // --- Delete modal state ---
    public bool $showDeleteModal = false;

    public ?int $deletingId = null;

    /**
     * What: Authorize that the admin may view the reminders screen at all.
     * Why: The screen is gated on `reminders.view`; without it it 403s rather than leaking data.
     * When: On component mount, before any query runs.
     */
    public function mount(): void
    {
        $this->authorize('viewAny', ReminderRule::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'resourceType' => ['required', Rule::enum(ReminderResourceType::class)],
            'daysBefore' => ['required', 'integer', 'min:0', 'max:3650'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'notifyClient' => ['boolean'],
            'notifyAdmin' => ['boolean'],
            'isActive' => ['boolean'],
        ];
    }

    public function openCreateModal(): void
    {
        $this->authorize('create', ReminderRule::class);
        $this->resetForm();
        $this->showFormModal = true;
    }

    public function openEditModal(int $ruleId): void
    {
        $rule = ReminderRule::findOrFail($ruleId);
        $this->authorize('update', $rule);

        $this->editingId = $rule->id;
        $this->resourceType = $rule->resource_type->value;
        $this->daysBefore = (string) $rule->days_before;
        $this->subject = $rule->subject;
        $this->body = $rule->body;
        $this->notifyClient = $rule->notify_client;
        $this->notifyAdmin = $rule->notify_admin;
        $this->isActive = $rule->is_active;

        $this->resetValidation();
        $this->showFormModal = true;
    }

    /**
     * What: Persist the rule — creating a new one or updating the one being edited.
     * Why: `company_id` is auto-stamped on create by BelongsToCompany; on edit the scoped lookup keeps
     *      tenants isolated.
     * When: Triggered on submit of the form modal.
     */
    public function save(): void
    {
        $validated = $this->validate();

        $attributes = [
            'resource_type' => $validated['resourceType'],
            'days_before' => (int) $validated['daysBefore'],
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'notify_client' => $validated['notifyClient'],
            'notify_admin' => $validated['notifyAdmin'],
            'is_active' => $validated['isActive'],
        ];

        if ($this->editingId !== null) {
            $rule = ReminderRule::findOrFail($this->editingId);
            $this->authorize('update', $rule);
            $rule->update($attributes);
            Flux::toast(variant: 'success', text: __('Reminder rule updated.'));
        } else {
            $this->authorize('create', ReminderRule::class);
            ReminderRule::create($attributes);
            Flux::toast(variant: 'success', text: __('Reminder rule created.'));
        }

        $this->showFormModal = false;
        $this->resetForm();
    }

    public function confirmDelete(int $ruleId): void
    {
        $rule = ReminderRule::findOrFail($ruleId);
        $this->authorize('delete', $rule);

        $this->deletingId = $rule->id;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        $rule = ReminderRule::findOrFail($this->deletingId);
        $this->authorize('delete', $rule);
        $rule->delete();

        $this->showDeleteModal = false;
        $this->deletingId = null;

        Flux::toast(variant: 'success', text: __('Reminder rule deleted.'));
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'subject', 'body']);
        $this->resourceType = ReminderResourceType::Service->value;
        $this->daysBefore = '30';
        $this->notifyClient = true;
        $this->notifyAdmin = false;
        $this->isActive = true;
        $this->resetValidation();
    }

    /**
     * @return LengthAwarePaginator<int, ReminderRule>
     */
    #[Computed]
    public function reminderRules(): LengthAwarePaginator
    {
        return ReminderRule::query()
            ->orderBy('resource_type')
            ->orderBy('days_before', 'desc')
            ->paginate(15, pageName: 'rulesPage');
    }

    /**
     * @return LengthAwarePaginator<int, ReminderLog>
     */
    #[Computed]
    public function reminderLogs(): LengthAwarePaginator
    {
        return ReminderLog::query()
            ->with(['client', 'remindable'])
            ->latest('sent_at')
            ->paginate(15, pageName: 'logsPage');
    }

    /**
     * @return array<int, ReminderResourceType>
     */
    #[Computed]
    public function resourceTypes(): array
    {
        return ReminderResourceType::cases();
    }

    public function render()
    {
        return view('livewire.admin.reminders.index');
    }
}
