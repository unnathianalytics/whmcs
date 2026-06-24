<?php

namespace App\Livewire\Admin\Clients;

use App\Models\Client;
use App\Models\ClientNote;
use App\Models\ClientService;
use App\Models\Invoice;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * What: The client profile page — contact overview, related-module placeholders, and internal notes.
 * Why: Gives admins a single view of a client. Services/invoices/tickets sections are placeholders until
 *      those modules ship (Phases 3–5); the notes panel is fully functional now. The client is resolved
 *      through the tenant scope so an admin can only ever open a client in their own company.
 * When: Rendered at `/admin/clients/{client}` for company admins holding `clients.view`.
 */
#[Title('Client')]
class Show extends Component
{
    public Client $client;

    #[Validate('required|string|max:2000')]
    public string $noteBody = '';

    /**
     * What: Bind the route-model and authorize viewing it.
     * Why: Route-model binding already runs through the BelongsToCompany scope (so cross-tenant ids
     *      404), and the policy enforces the `clients.view` permission on top.
     * When: On component mount.
     */
    public function mount(Client $client): void
    {
        $this->authorize('view', $client);
        $this->client = $client;
    }

    /**
     * What: Attach a new internal note to the client, authored by the current admin.
     * Why: Notes are an admin-only running log; `company_id`/`client_id` come from the scoped client so
     *      a note can never be misfiled to another tenant. Requires `clients.update`.
     * When: Triggered on submit of the add-note form.
     */
    public function addNote(): void
    {
        $this->authorize('update', $this->client);
        $validated = $this->validate();

        $this->client->notes()->create([
            'company_id' => $this->client->company_id,
            'user_id' => auth()->id(),
            'body' => $validated['noteBody'],
        ]);

        $this->reset('noteBody');
        unset($this->notes);

        Flux::toast(variant: 'success', text: __('Note added.'));
    }

    /**
     * What: Delete one of the client's notes.
     * Why: Lets admins remove a note they no longer want; scoped lookup keeps it within the client.
     * When: Triggered by the delete action on a note. Requires `clients.update`.
     */
    public function deleteNote(int $noteId): void
    {
        $this->authorize('update', $this->client);

        $this->client->notes()->whereKey($noteId)->delete();
        unset($this->notes);

        Flux::toast(variant: 'success', text: __('Note deleted.'));
    }

    /**
     * @return Collection<int, ClientNote>
     */
    #[Computed]
    public function notes(): Collection
    {
        return $this->client->notes()->with('author')->get();
    }

    /**
     * What: The client's services, newest first, for the profile's Services card.
     * Why: Turns the Phase 2 placeholder into a real count + list with status and expiry urgency.
     * When: Read on render of the client profile.
     *
     * @return Collection<int, ClientService>
     */
    #[Computed]
    public function services(): Collection
    {
        return $this->client->services()->with('product')->get();
    }

    /**
     * What: The client's invoices, newest first, for the profile's Invoices card.
     * Why: Turns the Phase 2/3 placeholder into a real count + list with status badges and totals.
     * When: Read on render of the client profile.
     *
     * @return Collection<int, Invoice>
     */
    #[Computed]
    public function invoices(): Collection
    {
        return $this->client->invoices()->get();
    }

    public function render()
    {
        return view('livewire.admin.clients.show');
    }
}
