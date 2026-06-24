<?php

namespace App\Livewire\Admin\ActivityLog;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

/**
 * What: A read-only audit trail of admin actions for the current tenant.
 * Why: idea.md Phase 8 requires an activity-log viewer powered by spatie/laravel-activitylog. The
 *      `activity_log` table has no `company_id`, so the feed is scoped by causer: only activities caused
 *      by this company's own admin users are shown. This is both a safe tenant boundary (a causer always
 *      belongs to exactly one company) and the right semantics for an admin audit trail.
 * When: Rendered at `/admin/activity-log` for company admins holding `settings.view`.
 */
#[Title('Activity Log')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $event = '';

    #[Url(as: 'causer')]
    public ?int $causerId = null;

    /**
     * What: Authorize access to the audit trail.
     * Why: Gated on `settings.view` — the same governance bracket as roles/settings.
     * When: On component mount.
     */
    public function mount(): void
    {
        abort_unless(Auth::user()->can('settings.view'), 403);
    }

    public function updatingEvent(): void
    {
        $this->resetPage();
    }

    public function updatingCauserId(): void
    {
        $this->resetPage();
    }

    /**
     * What: The ids of users belonging to the current tenant.
     * Why: The scoping key for the feed and the causer filter dropdown.
     *
     * @return array<int, int>
     */
    protected function tenantUserIds(): array
    {
        return User::query()
            ->where('company_id', Auth::user()->company_id)
            ->pluck('id')
            ->all();
    }

    /**
     * What: The tenant's admin users, for the causer filter dropdown.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function causers(): Collection
    {
        return User::query()
            ->where('company_id', Auth::user()->company_id)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * What: The paginated, filtered activity feed for this tenant.
     * Why: Scoped to tenant causers; optional event and causer filters narrow the view.
     * When: Read by the table on render.
     *
     * @return LengthAwarePaginator<int, Activity>
     */
    #[Computed]
    public function activities(): LengthAwarePaginator
    {
        return Activity::query()
            ->with(['causer', 'subject'])
            ->where('causer_type', (new User)->getMorphClass())
            ->whereIn('causer_id', $this->tenantUserIds())
            ->when($this->event !== '', fn ($query) => $query->where('event', $this->event))
            ->when($this->causerId !== null, fn ($query) => $query->where('causer_id', $this->causerId))
            ->latest()
            ->paginate(20);
    }

    public function render()
    {
        return view('livewire.admin.activity-log.index');
    }
}
