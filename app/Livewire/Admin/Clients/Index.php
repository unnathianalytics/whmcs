<?php

namespace App\Livewire\Admin\Clients;

use App\Enums\ClientStatus;
use App\Models\Client;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * What: Company-admin screen to list, search, filter, create, edit and delete clients.
 * Why: Clients are the core entity admins work with daily; this is the module's main workspace. All
 *      queries are tenant-isolated automatically by the Client model's BelongsToCompany scope, so the
 *      component never filters `company_id` by hand.
 * When: Rendered at `/admin/clients` for authenticated company admins holding `clients.view`.
 */
#[Title('Clients')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $country = '';

    #[Url]
    public string $sortBy = 'name';

    #[Url]
    public string $sortDirection = 'asc';

    public bool $showFormModal = false;

    /**
     * The client currently being edited, or null when creating.
     */
    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $companyName = '';

    public string $address = '';

    public string $city = '';

    public string $state = '';

    public string $postcode = '';

    public string $country_code = '';

    public string $currency = 'USD';

    public string $language = 'en';

    public string $statusField = ClientStatus::Active->value;

    public bool $showDeleteModal = false;

    public ?int $deletingId = null;

    /**
     * What: Authorize that the admin may view the clients list at all.
     * Why: The list is gated on `clients.view`; without it the screen 403s rather than leaking data.
     * When: On component mount, before any query runs.
     */
    public function mount(): void
    {
        $this->authorize('viewAny', Client::class);
    }

    /**
     * What: Validation rules for the create/edit form.
     * Why: Email must be unique per tenant (ignoring the current row on edit); status must be a valid
     *      enum value. Defined as a method so the unique rule can reference `$this->editingId`.
     * When: Run on save.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('clients', 'email')
                    ->where('company_id', auth()->user()->company_id)
                    ->ignore($this->editingId),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'companyName' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'currency' => ['required', 'string', 'size:3'],
            'language' => ['required', 'string', 'max:5'],
            'statusField' => ['required', Rule::enum(ClientStatus::class)],
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingCountry(): void
    {
        $this->resetPage();
    }

    /**
     * What: Toggle the sort column/direction for the table.
     * Why: Lets admins reorder the list by name, email or status without a separate control.
     * When: Triggered by clicking a sortable column header.
     */
    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';

            return;
        }

        $this->sortBy = $column;
        $this->sortDirection = 'asc';
    }

    /**
     * What: Open the form modal in create mode with a clean form.
     * Why: Resetting prevents a previously-edited client's values leaking into a new create.
     * When: Triggered by the "New client" button.
     */
    public function openCreateModal(): void
    {
        $this->authorize('create', Client::class);
        $this->resetForm();
        $this->showFormModal = true;
    }

    /**
     * What: Open the form modal pre-filled for editing an existing client.
     * Why: Reuses one modal for create and edit; loads the row through the tenant scope so an admin can
     *      only ever edit a client in their own company.
     * When: Triggered by the edit action on a table row.
     */
    public function openEditModal(int $clientId): void
    {
        $client = Client::findOrFail($clientId);
        $this->authorize('update', $client);

        $this->editingId = $client->id;
        $this->name = $client->name;
        $this->email = $client->email;
        $this->phone = (string) $client->phone;
        $this->companyName = (string) $client->company_name;
        $this->address = (string) $client->address;
        $this->city = (string) $client->city;
        $this->state = (string) $client->state;
        $this->postcode = (string) $client->postcode;
        $this->country_code = (string) $client->country;
        $this->currency = $client->currency;
        $this->language = $client->language;
        $this->statusField = $client->status->value;

        $this->resetValidation();
        $this->showFormModal = true;
    }

    /**
     * What: Persist the client — creating a new row or updating the one being edited.
     * Why: `company_id` is auto-stamped on create by BelongsToCompany; on edit the scoped lookup keeps
     *      tenants isolated. Authorization is re-checked here, not just on modal open.
     * When: Triggered on submit of the form modal.
     */
    public function save(): void
    {
        $validated = $this->validate();

        $attributes = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?: null,
            'company_name' => $validated['companyName'] ?: null,
            'address' => $validated['address'] ?: null,
            'city' => $validated['city'] ?: null,
            'state' => $validated['state'] ?: null,
            'postcode' => $validated['postcode'] ?: null,
            'country' => $validated['country_code'] ? strtoupper($validated['country_code']) : null,
            'currency' => strtoupper($validated['currency']),
            'language' => $validated['language'],
            'status' => $validated['statusField'],
        ];

        if ($this->editingId !== null) {
            $client = Client::findOrFail($this->editingId);
            $this->authorize('update', $client);
            $client->update($attributes);
            Flux::toast(variant: 'success', text: __('Client updated.'));
        } else {
            $this->authorize('create', Client::class);
            Client::create($attributes);
            Flux::toast(variant: 'success', text: __('Client created.'));
        }

        $this->showFormModal = false;
        $this->resetForm();
    }

    /**
     * What: Open the delete-confirmation modal for a client.
     * Why: Deletion is destructive (soft delete) so it is confirmed before running.
     * When: Triggered by the delete action on a table row.
     */
    public function confirmDelete(int $clientId): void
    {
        $client = Client::findOrFail($clientId);
        $this->authorize('delete', $client);

        $this->deletingId = $client->id;
        $this->showDeleteModal = true;
    }

    /**
     * What: Soft-delete the confirmed client.
     * Why: Soft delete keeps related history recoverable; tenant scope guarantees same-company only.
     * When: Triggered on confirm of the delete modal.
     */
    public function delete(): void
    {
        $client = Client::findOrFail($this->deletingId);
        $this->authorize('delete', $client);
        $client->delete();

        $this->showDeleteModal = false;
        $this->deletingId = null;

        Flux::toast(variant: 'success', text: __('Client deleted.'));
    }

    /**
     * What: Reset every form field to its create-mode default.
     * Why: Shared modal must not carry state between create and edit sessions.
     * When: Called when opening create mode and after a successful save.
     */
    protected function resetForm(): void
    {
        $this->reset([
            'editingId', 'name', 'email', 'phone', 'companyName', 'address',
            'city', 'state', 'postcode', 'country_code',
        ]);
        $this->currency = 'USD';
        $this->language = 'en';
        $this->statusField = ClientStatus::Active->value;
        $this->resetValidation();
    }

    /**
     * @return LengthAwarePaginator<int, Client>
     */
    #[Computed]
    public function clients(): LengthAwarePaginator
    {
        $sortable = ['name', 'email', 'status', 'created_at'];
        $sortBy = in_array($this->sortBy, $sortable, true) ? $this->sortBy : 'name';
        $sortDirection = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        return Client::query()
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($q): void {
                    $q->where('name', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%")
                        ->orWhere('company_name', 'like', "%{$this->search}%");
                });
            })
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->country !== '', fn ($query) => $query->where('country', $this->country))
            ->orderBy($sortBy, $sortDirection)
            ->paginate(10);
    }

    /**
     * What: Distinct country codes present in this tenant's clients, for the filter dropdown.
     * Why: Only show countries that actually exist so the filter never offers empty results.
     * When: Read by the country filter on render.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function countries(): array
    {
        return Client::query()
            ->whereNotNull('country')
            ->distinct()
            ->orderBy('country')
            ->pluck('country')
            ->all();
    }

    /**
     * @return array<int, ClientStatus>
     */
    #[Computed]
    public function statuses(): array
    {
        return ClientStatus::cases();
    }

    public function render()
    {
        return view('livewire.admin.clients.index');
    }
}
