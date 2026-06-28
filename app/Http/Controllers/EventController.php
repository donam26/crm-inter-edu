<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Http\Requests\Event\StoreEventRequest;
use App\Http\Requests\Event\UpdateEventRequest;
use App\Models\Branch;
use App\Models\Event;
use App\Models\Lead;
use App\Models\User;
use App\Services\EventService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class EventController extends Controller
{
    public function __construct(private EventService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Event::class);

        $filters = $request->only([
            'status', 'type', 'organizer_user_id', 'lead_id',
            'branch_id', 'from', 'to', 'q',
        ]);

        // Sales mặc định chỉ thấy lịch của chính mình (organizer hoặc được
        // mời). Phía Service ngoài Policy@view, ta giới hạn ngay tại query.
        $user = $request->user();
        if ($user?->hasRole('sales')) {
            $filters['organizer_user_id'] = $user->id;
        }

        if (! $user?->hasRole('super-admin')) {
            unset($filters['branch_id']);
        }

        return view('events.index', [
            'events' => $this->service->list($filters),
            'statuses' => EventStatus::cases(),
            'types' => EventType::cases(),
            'branches' => $user?->hasRole('super-admin')
                ? Branch::orderBy('name')->get()
                : collect(),
            'branchUsers' => $this->branchUsers($user),
            'filters' => $filters,
        ]);
    }

    public function calendar(Request $request)
    {
        $this->authorize('viewAny', Event::class);

        $month = $request->input('month');
        $cursor = $month ? Carbon::parse($month.'-01') : Carbon::now()->startOfMonth();

        $from = $cursor->copy()->startOfMonth()->startOfWeek();
        $to = $cursor->copy()->endOfMonth()->endOfWeek();

        $events = Event::query()
            ->with(['organizer', 'lead'])
            ->between($from, $to)
            ->when($request->user()?->hasRole('sales'),
                fn ($q) => $q->where(function ($q2) use ($request) {
                    $q2->where('organizer_user_id', $request->user()->id)
                        ->orWhereHas('attendees', fn ($q3) => $q3->where('users.id', $request->user()->id));
                }))
            ->orderBy('starts_at')
            ->get();

        return view('events.calendar', [
            'cursor' => $cursor,
            'from' => $from,
            'to' => $to,
            'events' => $events,
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', Event::class);

        if (! $this->wantsModalForm()) {
            return redirect()->route('events.index');
        }

        return view('events.create', [
            'types' => EventType::cases(),
            'branchUsers' => $this->branchUsers($request->user()),
            'leads' => $this->branchLeads($request->user()),
            'preselectedLeadId' => $request->integer('lead_id') ?: null,
        ]);
    }

    public function store(StoreEventRequest $request)
    {
        try {
            $event = $this->service->create($request->validated());
        } catch (ValidationException $e) {
            return $this->validationResponse($e);
        }

        $conflicts = $this->service->detectConflicts(
            (int) $event->organizer_user_id,
            $event->attendees()->pluck('users.id')->all(),
            $event->starts_at,
            $event->ends_at,
            $event->id,
        );

        session()->flash('event_conflicts', $conflicts->map(fn ($e) => [
            'id' => $e->id,
            'title' => $e->title,
            'starts_at' => $e->starts_at?->format('d/m/Y H:i'),
            'ends_at' => $e->ends_at?->format('d/m/Y H:i'),
        ])->all());

        return $this->modalRedirect(route('events.show', $event), 'Đã tạo lịch.');
    }

    public function show(Event $event)
    {
        $this->authorize('view', $event);
        $event->load(['branch', 'organizer', 'creator', 'lead', 'attendees']);

        return view('events.show', [
            'event' => $event,
            'myAttendance' => $event->attendees->firstWhere('id', auth()->id()),
        ]);
    }

    public function edit(Request $request, Event $event)
    {
        $this->authorize('update', $event);

        if (! $this->wantsModalForm()) {
            return redirect()->route('events.show', $event);
        }

        $event->load('attendees');

        return view('events.edit', [
            'event' => $event,
            'statuses' => EventStatus::cases(),
            'types' => EventType::cases(),
            'branchUsers' => $this->branchUsers($request->user(), $event->branch_id),
            'leads' => $this->branchLeads($request->user(), $event->branch_id),
            'attendeeIds' => $event->attendees->pluck('id')->all(),
        ]);
    }

    public function update(UpdateEventRequest $request, Event $event)
    {
        try {
            $this->service->update($event, $request->validated());
        } catch (ValidationException $e) {
            return $this->validationResponse($e);
        }

        return $this->modalRedirect(route('events.show', $event), 'Đã cập nhật lịch.');
    }

    public function destroy(Event $event)
    {
        $this->authorize('delete', $event);
        $this->service->delete($event);

        return redirect()->route('events.index')
            ->with('success', 'Đã xoá lịch.');
    }

    public function markDone(Event $event)
    {
        $this->authorize('markDone', $event);

        try {
            $this->service->markDone($event);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Đã đánh dấu đã diễn ra.');
    }

    public function cancel(Event $event)
    {
        $this->authorize('cancel', $event);

        try {
            $this->service->cancel($event);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Đã huỷ lịch.');
    }

    public function respond(Request $request, Event $event)
    {
        $this->authorize('respond', $event);

        $data = $request->validate([
            'response' => ['required', 'string', 'in:pending,accepted,declined,tentative'],
        ]);

        try {
            $this->service->respond($event, $request->user(), $data['response']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Đã cập nhật phản hồi.');
    }

    /**
     * User cùng branch (sales / branch-manager) — danh sách candidate cho
     * organizer + attendees.
     */
    private function branchUsers(?User $user, ?int $forceBranchId = null)
    {
        if ($user === null) {
            return collect();
        }

        $branchId = $forceBranchId ?? $user->branch_id;

        if ($user->hasRole('super-admin') && $branchId === null) {
            return User::query()
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['sales', 'branch-manager']))
                ->orderBy('name')
                ->get();
        }

        if ($branchId === null) {
            return collect();
        }

        return User::query()
            ->where('branch_id', $branchId)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['sales', 'branch-manager']))
            ->orderBy('name')
            ->get();
    }

    private function branchLeads(?User $user, ?int $forceBranchId = null)
    {
        if ($user === null) {
            return collect();
        }

        $branchId = $forceBranchId ?? $user->branch_id;

        $query = Lead::query()->orderBy('school_name');

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        return $query->limit(500)->get(['id', 'school_name', 'branch_id']);
    }
}
