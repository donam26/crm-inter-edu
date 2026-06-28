<?php

namespace App\Http\Controllers;

use App\Http\Requests\Contact\StoreContactRequest;
use App\Http\Requests\Contact\UpdateContactRequest;
use App\Models\Contact;
use App\Models\Lead;
use App\Services\ContactService;

class ContactController extends Controller
{
    public function __construct(private ContactService $service) {}

    public function create(Lead $lead)
    {
        $this->authorize('create', [Contact::class, $lead]);

        if (! $this->wantsModalForm()) {
            return redirect()->route('leads.show', $lead);
        }

        return view('contacts.create', compact('lead'));
    }

    public function store(StoreContactRequest $request, Lead $lead)
    {
        $this->service->create($lead, $request->validated());

        return $this->modalRedirect(route('leads.show', $lead), 'Đã thêm người liên hệ.');
    }

    public function show(Contact $contact)
    {
        $this->authorize('view', $contact);
        $contact->load(['lead', 'branch']);

        return view('contacts.show', compact('contact'));
    }

    public function edit(Contact $contact)
    {
        $this->authorize('update', $contact);
        $contact->load('lead');

        if (! $this->wantsModalForm()) {
            return redirect()->route('leads.show', $contact->lead);
        }

        return view('contacts.edit', compact('contact'));
    }

    public function update(UpdateContactRequest $request, Contact $contact)
    {
        $this->service->update($contact, $request->validated());

        return $this->modalRedirect(route('leads.show', $contact->lead_id), 'Đã cập nhật người liên hệ.');
    }

    public function destroy(Contact $contact)
    {
        $this->authorize('delete', $contact);
        $leadId = $contact->lead_id;
        $this->service->delete($contact);

        return redirect()->route('leads.show', $leadId)
            ->with('success', 'Đã xóa người liên hệ.');
    }
}
