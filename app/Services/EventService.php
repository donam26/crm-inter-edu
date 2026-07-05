<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventService
{
    /**
     * Liệt kê event có filter + phân trang.
     *
     * Filter hỗ trợ:
     *  - status / type
     *  - organizer_user_id, customer_id, branch_id (super-admin only)
     *  - from, to: khoảng thời gian (ISO date string)
     *  - q: search title/description/location
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        return Event::query()
            ->with(['branch', 'organizer', 'customer'])
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filters['organizer_user_id'] ?? null, fn ($q, $v) => $q->where('organizer_user_id', $v))
            ->when($filters['customer_id'] ?? null, fn ($q, $v) => $q->where('customer_id', $v))
            ->when($filters['branch_id'] ?? null, fn ($q, $v) => $q->where('branch_id', $v))
            ->when(($filters['from'] ?? null) && ($filters['to'] ?? null),
                fn ($q) => $q->between(
                    Carbon::parse($filters['from']),
                    Carbon::parse($filters['to'])
                ))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(function ($q2) use ($v) {
                $q2->where('title', 'like', "%{$v}%")
                    ->orWhere('description', 'like', "%{$v}%")
                    ->orWhere('location', 'like', "%{$v}%");
            }))
            ->orderBy('starts_at')
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * Tạo event mới.
     *
     * Service-layer injection bắt buộc:
     *  - branch_id luôn lấy từ organizer (cùng branch với người chủ trì).
     *  - created_by luôn = auth user (không nhận từ input).
     *  - status mặc định Scheduled khi tạo mới.
     *
     * Cross-branch guards:
     *  - organizer phải có branch_id.
     *  - non-super-admin: organizer phải cùng branch với auth user.
     *  - customer (nếu có) phải cùng branch với organizer.
     *  - tất cả attendees phải cùng branch với organizer.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Event
    {
        return DB::transaction(function () use ($data) {
            $authUser = Auth::user();
            $organizer = User::findOrFail($data['organizer_user_id']);

            $this->guardOrganizerBranch($authUser, $organizer);

            $branchId = (int) $organizer->branch_id;
            $data['branch_id'] = $branchId;

            if (! empty($data['customer_id'])) {
                $this->guardCustomerBranch((int) $data['customer_id'], $branchId);
            } else {
                $data['customer_id'] = null;
            }

            $attendeeIds = $this->normalizeAttendeeIds($data['attendee_ids'] ?? []);
            $this->guardAttendeesBranch($attendeeIds, $branchId);

            // Service-layer injection: chặn override các field auto-set.
            $data['created_by'] = $authUser?->id;
            $data['status'] = EventStatus::Scheduled->value;

            unset($data['attendee_ids']);

            $event = Event::create($data);

            if ($attendeeIds !== []) {
                $this->syncAttendees($event, $attendeeIds);
            }

            return $event->fresh(['attendees']);
        });
    }

    /**
     * Cập nhật event.
     *
     * Đảm bảo:
     *  - branch_id luôn đồng bộ với organizer.branch_id.
     *  - customer (nếu có) cùng branch với organizer.
     *  - attendees cùng branch với organizer.
     *  - Khi đổi status sang Done/Cancelled từ Scheduled: chỉ cập nhật trường,
     *    không có side-effect khác (giữ đơn giản).
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Event $event, array $data): Event
    {
        return DB::transaction(function () use ($event, $data) {
            // Chặn user override các field auto-set qua mass-assign.
            unset($data['branch_id'], $data['created_by']);

            $organizer = isset($data['organizer_user_id'])
                ? User::findOrFail($data['organizer_user_id'])
                : $event->organizer;

            $this->guardOrganizerBranch(Auth::user(), $organizer);

            $branchId = (int) $organizer->branch_id;
            $data['branch_id'] = $branchId;

            if (array_key_exists('customer_id', $data)) {
                if (! empty($data['customer_id'])) {
                    $this->guardCustomerBranch((int) $data['customer_id'], $branchId);
                } else {
                    $data['customer_id'] = null;
                }
            } elseif ($event->customer_id !== null) {
                $this->guardCustomerBranch((int) $event->customer_id, $branchId);
            }

            $hasAttendeesPayload = array_key_exists('attendee_ids', $data);
            $attendeeIds = $hasAttendeesPayload
                ? $this->normalizeAttendeeIds($data['attendee_ids'] ?? [])
                : null;

            if ($attendeeIds !== null) {
                $this->guardAttendeesBranch($attendeeIds, $branchId);
            }

            unset($data['attendee_ids']);

            $event->update($data);

            if ($attendeeIds !== null) {
                $this->syncAttendees($event, $attendeeIds);
            }

            return $event->fresh(['attendees']);
        });
    }

    public function delete(Event $event): void
    {
        DB::transaction(fn () => $event->delete());
    }

    /**
     * Đánh dấu event đã diễn ra.
     */
    public function markDone(Event $event): Event
    {
        return DB::transaction(function () use ($event) {
            if ($event->status === EventStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'status' => 'Không thể đánh dấu đã diễn ra cho lịch đã huỷ.',
                ]);
            }

            $event->update(['status' => EventStatus::Done]);

            return $event->fresh();
        });
    }

    /**
     * Huỷ event.
     */
    public function cancel(Event $event): Event
    {
        return DB::transaction(function () use ($event) {
            if ($event->status === EventStatus::Done) {
                throw ValidationException::withMessages([
                    'status' => 'Không thể huỷ lịch đã diễn ra.',
                ]);
            }

            $event->update(['status' => EventStatus::Cancelled]);

            return $event->fresh();
        });
    }

    /**
     * Cập nhật phản hồi tham dự của một attendee.
     *
     * @param  string  $response  pending | accepted | declined | tentative
     */
    public function respond(Event $event, User $user, string $response): void
    {
        $valid = ['pending', 'accepted', 'declined', 'tentative'];

        if (! in_array($response, $valid, true)) {
            throw ValidationException::withMessages([
                'response' => 'Phản hồi không hợp lệ.',
            ]);
        }

        if (! $event->attendees()->where('users.id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'response' => 'Bạn không nằm trong danh sách tham dự.',
            ]);
        }

        DB::transaction(function () use ($event, $user, $response) {
            $event->attendees()->updateExistingPivot($user->id, [
                'response' => $response,
                'responded_at' => now(),
            ]);
        });
    }

    /**
     * Phát hiện xung đột lịch (overlapping events) trong cùng khoảng thời gian
     * cho organizer + danh sách attendees. Trả về collection event xung đột.
     *
     * @param  array<int, int>  $attendeeIds
     */
    public function detectConflicts(
        int $organizerId,
        array $attendeeIds,
        Carbon $startsAt,
        Carbon $endsAt,
        ?int $excludeEventId = null,
    ): Collection {
        $userIds = array_values(array_unique(array_merge([$organizerId], $attendeeIds)));

        return Event::query()
            ->where('status', EventStatus::Scheduled->value)
            ->between($startsAt, $endsAt)
            ->when($excludeEventId, fn ($q, $id) => $q->where('id', '!=', $id))
            ->where(function ($q) use ($userIds) {
                $q->whereIn('organizer_user_id', $userIds)
                    ->orWhereHas('attendees', fn ($q2) => $q2->whereIn('users.id', $userIds));
            })
            ->with('organizer')
            ->orderBy('starts_at')
            ->get();
    }

    // ───────────────────── helpers ─────────────────────

    /**
     * @return array<int, int>
     */
    private function normalizeAttendeeIds(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        return collect($raw)
            ->filter(fn ($v) => is_numeric($v))
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Sync attendees giữ nguyên response/responded_at cho user đã tồn tại,
     * chỉ insert pivot mới với response='pending' cho user mới.
     *
     * @param  array<int, int>  $userIds
     */
    private function syncAttendees(Event $event, array $userIds): void
    {
        $existing = $event->attendees()->pluck('users.id')->all();

        $toAttach = array_diff($userIds, $existing);
        $toDetach = array_diff($existing, $userIds);

        if ($toDetach !== []) {
            $event->attendees()->detach($toDetach);
        }

        foreach ($toAttach as $id) {
            $event->attendees()->attach($id, [
                'response' => 'pending',
                'responded_at' => null,
            ]);
        }
    }

    private function guardOrganizerBranch(?User $authUser, User $organizer): void
    {
        if ($organizer->branch_id === null) {
            throw ValidationException::withMessages([
                'organizer_user_id' => 'Người chủ trì phải thuộc một chi nhánh.',
            ]);
        }

        if ($authUser === null || $authUser->hasRole('super-admin')) {
            return;
        }

        if ((int) $authUser->branch_id !== (int) $organizer->branch_id) {
            throw ValidationException::withMessages([
                'organizer_user_id' => 'Không thể chọn người chủ trì ngoài chi nhánh.',
            ]);
        }
    }

    private function guardCustomerBranch(int $customerId, int $branchId): void
    {
        $customer = Customer::withoutGlobalScopes()->find($customerId);

        if ($customer === null) {
            throw ValidationException::withMessages([
                'customer_id' => 'Customer không tồn tại.',
            ]);
        }

        if ((int) $customer->branch_id !== $branchId) {
            throw ValidationException::withMessages([
                'customer_id' => 'Customer phải thuộc cùng chi nhánh với người chủ trì.',
            ]);
        }
    }

    /**
     * @param  array<int, int>  $attendeeIds
     */
    private function guardAttendeesBranch(array $attendeeIds, int $branchId): void
    {
        if ($attendeeIds === []) {
            return;
        }

        $foreignCount = User::query()
            ->whereIn('id', $attendeeIds)
            ->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id')->orWhere('branch_id', '!=', $branchId);
            })
            ->count();

        if ($foreignCount > 0) {
            throw ValidationException::withMessages([
                'attendee_ids' => 'Tất cả người tham gia phải thuộc cùng chi nhánh.',
            ]);
        }
    }
}
