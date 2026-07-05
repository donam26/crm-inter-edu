<?php

namespace App\Http\Controllers;

use App\Enums\ActivityType;
use App\Http\Requests\Activity\StoreActivityRequest;
use App\Http\Requests\Activity\UpdateActivityRequest;
use App\Models\Activity;
use App\Models\Customer;
use App\Services\ActivityService;

class ActivityController extends Controller
{
    public function __construct(private ActivityService $service) {}

    public function create(Customer $customer)
    {
        $this->authorize('create', [Activity::class, $customer]);

        if (! $this->wantsModalForm()) {
            return redirect()->route('customers.show', $customer);
        }

        return view('activities.create', [
            'customer' => $customer,
            'types' => ActivityType::cases(),
        ]);
    }

    public function store(StoreActivityRequest $request, Customer $customer)
    {
        $this->service->create($customer, $request->validated());

        return $this->modalRedirect(route('customers.show', $customer), 'Đã thêm hoạt động.');
    }

    public function show(Activity $activity)
    {
        $this->authorize('view', $activity);
        $activity->load(['customer', 'branch', 'user']);

        return view('activities.show', compact('activity'));
    }

    public function edit(Activity $activity)
    {
        $this->authorize('update', $activity);
        $activity->load('customer');

        if (! $this->wantsModalForm()) {
            return redirect()->route('customers.show', $activity->customer);
        }

        return view('activities.edit', [
            'activity' => $activity,
            'types' => ActivityType::cases(),
        ]);
    }

    public function update(UpdateActivityRequest $request, Activity $activity)
    {
        $this->service->update($activity, $request->validated());

        return $this->modalRedirect(route('customers.show', $activity->customer_id), 'Đã cập nhật hoạt động.');
    }

    public function destroy(Activity $activity)
    {
        $this->authorize('delete', $activity);
        $customerId = $activity->customer_id;
        $this->service->delete($activity);

        return redirect()->route('customers.show', $customerId)
            ->with('success', 'Đã xóa hoạt động.');
    }
}
