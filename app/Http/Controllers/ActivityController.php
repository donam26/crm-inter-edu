<?php

namespace App\Http\Controllers;

use App\Enums\ActivityType;
use App\Http\Requests\Activity\StoreActivityRequest;
use App\Http\Requests\Activity\UpdateActivityRequest;
use App\Models\Activity;
use App\Models\Lead;
use App\Services\ActivityService;

class ActivityController extends Controller
{
    public function __construct(private ActivityService $service) {}

    public function create(Lead $lead)
    {
        $this->authorize('create', [Activity::class, $lead]);

        if (! $this->wantsModalForm()) {
            return redirect()->route('leads.show', $lead);
        }

        return view('activities.create', [
            'lead' => $lead,
            'types' => ActivityType::cases(),
        ]);
    }

    public function store(StoreActivityRequest $request, Lead $lead)
    {
        $this->service->create($lead, $request->validated());

        return $this->modalRedirect(route('leads.show', $lead), 'Đã thêm hoạt động.');
    }

    public function show(Activity $activity)
    {
        $this->authorize('view', $activity);
        $activity->load(['lead', 'branch', 'user']);

        return view('activities.show', compact('activity'));
    }

    public function edit(Activity $activity)
    {
        $this->authorize('update', $activity);
        $activity->load('lead');

        if (! $this->wantsModalForm()) {
            return redirect()->route('leads.show', $activity->lead);
        }

        return view('activities.edit', [
            'activity' => $activity,
            'types' => ActivityType::cases(),
        ]);
    }

    public function update(UpdateActivityRequest $request, Activity $activity)
    {
        $this->service->update($activity, $request->validated());

        return $this->modalRedirect(route('leads.show', $activity->lead_id), 'Đã cập nhật hoạt động.');
    }

    public function destroy(Activity $activity)
    {
        $this->authorize('delete', $activity);
        $leadId = $activity->lead_id;
        $this->service->delete($activity);

        return redirect()->route('leads.show', $leadId)
            ->with('success', 'Đã xóa hoạt động.');
    }
}
