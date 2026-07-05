<?php

namespace App\Http\Controllers;

use App\Http\Requests\Contact\StoreContactRequest;
use App\Http\Requests\Contact\UpdateContactRequest;
use App\Models\Contact;
use App\Models\Customer;
use App\Services\ContactService;

class ContactController extends Controller
{
    public function __construct(private ContactService $service) {}

    public function create(Customer $customer)
    {
        $this->authorize('create', [Contact::class, $customer]);

        if (! $this->wantsModalForm()) {
            return redirect()->route('customers.show', $customer);
        }

        return view('contacts.create', compact('customer'));
    }

    public function store(StoreContactRequest $request, Customer $customer)
    {
        $this->service->create($customer, $request->validated());

        return $this->modalRedirect(route('customers.show', $customer), 'Đã thêm người liên hệ.');
    }

    public function show(Contact $contact)
    {
        $this->authorize('view', $contact);
        $contact->load(['customer', 'branch']);

        return view('contacts.show', compact('contact'));
    }

    public function edit(Contact $contact)
    {
        $this->authorize('update', $contact);
        $contact->load('customer');

        if (! $this->wantsModalForm()) {
            return redirect()->route('customers.show', $contact->customer);
        }

        return view('contacts.edit', compact('contact'));
    }

    public function update(UpdateContactRequest $request, Contact $contact)
    {
        $this->service->update($contact, $request->validated());

        return $this->modalRedirect(route('customers.show', $contact->customer_id), 'Đã cập nhật người liên hệ.');
    }

    public function destroy(Contact $contact)
    {
        $this->authorize('delete', $contact);
        $customerId = $contact->customer_id;
        $this->service->delete($contact);

        return redirect()->route('customers.show', $customerId)
            ->with('success', 'Đã xóa người liên hệ.');
    }
}
