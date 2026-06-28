<?php

namespace Tests\Unit;

use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Models\Branch;
use App\Models\Event;
use App\Models\Lead;
use App\Models\Scopes\BranchScope;
use App\Services\EventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

/**
 * Unit tests for EventService.
 *
 * Phạm vi:
 *  - branch_id luôn lấy từ organizer
 *  - created_by = auth user
 *  - cross-branch guards (organizer, lead, attendees)
 *  - attendee sync giữ pivot cũ, không tạo bản trùng
 *  - markDone / cancel guards
 *  - detectConflicts trả đúng các event chạm khoảng thời gian
 *  - rollback transaction khi exception
 */
class EventServiceTest extends TestCase
{
    use InteractsWithRbac, RefreshDatabase;

    private EventService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EventService;
        $this->setUpRbac();
    }

    private function basePayload(int $organizerId, array $extra = []): array
    {
        $start = now()->addDay()->startOfHour();

        return array_merge([
            'title' => 'Sample event',
            'description' => null,
            'type' => EventType::Meeting->value,
            'location' => 'Office',
            'is_online' => false,
            'online_url' => null,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHour(),
            'all_day' => false,
            'reminder_at' => null,
            'organizer_user_id' => $organizerId,
            'lead_id' => null,
            'attendee_ids' => [],
        ], $extra);
    }

    // ───────────────────── create ─────────────────────

    public function test_create_sets_branch_id_from_organizer_not_from_input(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $admin = $this->makeUser('super-admin');
        $organizer = $this->makeUser('sales', $branchA);

        Auth::login($admin);

        $event = $this->service->create($this->basePayload($organizer->id, [
            'branch_id' => $branchB->id,
        ]));

        $this->assertSame($branchA->id, $event->branch_id);
    }

    public function test_create_sets_created_by_from_auth_user(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $intruder = $this->makeUser('sales', $branch);

        Auth::login($mgr);

        $event = $this->service->create($this->basePayload($mgr->id, [
            'created_by' => $intruder->id,
        ]));

        $this->assertSame($mgr->id, $event->created_by);
    }

    public function test_create_initializes_status_as_scheduled(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);

        Auth::login($mgr);

        $event = $this->service->create($this->basePayload($mgr->id, [
            // Cố tình gửi status sai → bị overwrite về Scheduled.
            'status' => EventStatus::Done->value,
        ]));

        $this->assertSame(EventStatus::Scheduled, $event->status);
    }

    public function test_create_rejects_cross_branch_organizer_for_non_super_admin(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $own);
        $foreign = $this->makeUser('sales', $other);

        Auth::login($mgr);

        $this->expectException(ValidationException::class);
        $this->service->create($this->basePayload($foreign->id));
    }

    public function test_create_allows_super_admin_to_choose_organizer_in_any_branch(): void
    {
        $admin = $this->makeUser('super-admin');
        $branch = Branch::factory()->create();
        $sales = $this->makeUser('sales', $branch);

        Auth::login($admin);

        $event = $this->service->create($this->basePayload($sales->id));

        $this->assertSame($branch->id, $event->branch_id);
    }

    public function test_create_rejects_lead_from_other_branch(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $admin = $this->makeUser('super-admin');
        $organizer = $this->makeUser('sales', $branchA);
        $foreignLead = Lead::factory()->forBranch($branchB)->create();

        Auth::login($admin);

        $this->expectException(ValidationException::class);
        $this->service->create($this->basePayload($organizer->id, [
            'lead_id' => $foreignLead->id,
        ]));
    }

    public function test_create_rejects_attendees_from_other_branch(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $admin = $this->makeUser('super-admin');
        $organizer = $this->makeUser('sales', $own);
        $foreign = $this->makeUser('sales', $other);

        Auth::login($admin);

        $this->expectException(ValidationException::class);
        $this->service->create($this->basePayload($organizer->id, [
            'attendee_ids' => [$foreign->id],
        ]));
    }

    public function test_create_attaches_attendees_with_pending_response(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $a = $this->makeUser('sales', $branch);
        $b = $this->makeUser('sales', $branch);

        Auth::login($mgr);

        $event = $this->service->create($this->basePayload($mgr->id, [
            'attendee_ids' => [$a->id, $b->id, $a->id], // duplicate, phải dedupe
        ]));

        $this->assertCount(2, $event->attendees);
        foreach ($event->attendees as $u) {
            $this->assertSame('pending', $u->pivot->response);
            $this->assertNull($u->pivot->responded_at);
        }
    }

    // ───────────────────── update ─────────────────────

    public function test_update_keeps_branch_id_in_sync_with_organizer_branch(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $admin = $this->makeUser('super-admin');
        $userA = $this->makeUser('sales', $branchA);
        $userB = $this->makeUser('sales', $branchB);

        Auth::login($admin);

        $event = $this->service->create($this->basePayload($userA->id));
        $this->assertSame($branchA->id, $event->branch_id);

        $payload = $this->basePayload($userB->id, [
            'status' => EventStatus::Scheduled->value,
        ]);
        $payload['attendee_ids'] = []; // reset attendees để không vướng guard branch cũ.
        $updated = $this->service->update($event, $payload);

        $this->assertSame($branchB->id, $updated->branch_id);
    }

    public function test_update_preserves_existing_attendee_response_when_resyncing(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $a = $this->makeUser('sales', $branch);
        $b = $this->makeUser('sales', $branch);

        Auth::login($mgr);

        $event = $this->service->create($this->basePayload($mgr->id, [
            'attendee_ids' => [$a->id],
        ]));
        $event->attendees()->updateExistingPivot($a->id, [
            'response' => 'accepted', 'responded_at' => now(),
        ]);

        // Update mở rộng attendees: thêm $b, vẫn giữ $a.
        $payload = $this->basePayload($mgr->id, [
            'status' => EventStatus::Scheduled->value,
            'attendee_ids' => [$a->id, $b->id],
        ]);
        $this->service->update($event, $payload);

        $event->refresh()->load('attendees');
        $aPivot = $event->attendees->firstWhere('id', $a->id)?->pivot;
        $bPivot = $event->attendees->firstWhere('id', $b->id)?->pivot;

        $this->assertSame('accepted', $aPivot?->response);
        $this->assertSame('pending', $bPivot?->response);
    }

    public function test_update_detaches_attendees_no_longer_in_payload(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $a = $this->makeUser('sales', $branch);
        $b = $this->makeUser('sales', $branch);

        Auth::login($mgr);

        $event = $this->service->create($this->basePayload($mgr->id, [
            'attendee_ids' => [$a->id, $b->id],
        ]));
        $this->assertCount(2, $event->attendees);

        $payload = $this->basePayload($mgr->id, [
            'status' => EventStatus::Scheduled->value,
            'attendee_ids' => [$a->id], // bỏ $b
        ]);
        $this->service->update($event, $payload);

        $event->refresh()->load('attendees');
        $this->assertCount(1, $event->attendees);
        $this->assertSame($a->id, $event->attendees->first()->id);
    }

    // ───────────────────── markDone / cancel ─────────────────────

    public function test_mark_done_throws_for_cancelled(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);

        Auth::login($mgr);
        $event = Event::factory()->forBranchUser($mgr)->status(EventStatus::Cancelled)->create();

        $this->expectException(ValidationException::class);
        $this->service->markDone($event);
    }

    public function test_cancel_throws_for_done(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);

        Auth::login($mgr);
        $event = Event::factory()->forBranchUser($mgr)->status(EventStatus::Done)->create();

        $this->expectException(ValidationException::class);
        $this->service->cancel($event);
    }

    // ───────────────────── respond ─────────────────────

    public function test_respond_updates_pivot_for_attendee(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $invitee = $this->makeUser('sales', $branch);

        Auth::login($mgr);
        $event = $this->service->create($this->basePayload($mgr->id, [
            'attendee_ids' => [$invitee->id],
        ]));

        $this->service->respond($event, $invitee, 'accepted');

        $pivot = $event->attendees()->where('users.id', $invitee->id)->first()?->pivot;
        $this->assertSame('accepted', $pivot?->response);
        $this->assertNotNull($pivot?->responded_at);
    }

    public function test_respond_throws_for_non_attendee(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $stranger = $this->makeUser('sales', $branch);

        Auth::login($mgr);
        $event = $this->service->create($this->basePayload($mgr->id));

        $this->expectException(ValidationException::class);
        $this->service->respond($event, $stranger, 'accepted');
    }

    public function test_respond_throws_for_invalid_response_value(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $invitee = $this->makeUser('sales', $branch);

        Auth::login($mgr);
        $event = $this->service->create($this->basePayload($mgr->id, [
            'attendee_ids' => [$invitee->id],
        ]));

        $this->expectException(ValidationException::class);
        $this->service->respond($event, $invitee, 'maybe-someday');
    }

    // ───────────────────── conflicts ─────────────────────

    public function test_detect_conflicts_returns_overlapping_events_for_organizer(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);

        Auth::login($mgr);

        $start = Carbon::now()->addDay()->startOfHour();
        $end = $start->copy()->addHour();

        // Event hiện hữu của cùng organizer chạm vào khoảng [start, end).
        Event::factory()->forBranchUser($mgr)
            ->startingAt($start->copy()->subMinutes(30), 60)
            ->create(['title' => 'CONFLICT_EVENT', 'status' => EventStatus::Scheduled]);

        // Event không chạm.
        Event::factory()->forBranchUser($mgr)
            ->startingAt($start->copy()->addHours(3), 60)
            ->create(['title' => 'NON_CONFLICT', 'status' => EventStatus::Scheduled]);

        // Event cancelled không tính.
        Event::factory()->forBranchUser($mgr)
            ->startingAt($start->copy()->subMinutes(15), 60)
            ->status(EventStatus::Cancelled)
            ->create(['title' => 'CANCELLED_NO_COUNT']);

        $conflicts = $this->service->detectConflicts($mgr->id, [], $start, $end);

        $this->assertCount(1, $conflicts);
        $this->assertSame('CONFLICT_EVENT', $conflicts->first()->title);
    }

    public function test_detect_conflicts_excludes_event_being_updated(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);

        Auth::login($mgr);

        $start = Carbon::now()->addDay()->startOfHour();
        $end = $start->copy()->addHour();

        $event = Event::factory()->forBranchUser($mgr)
            ->startingAt($start, 60)
            ->create(['status' => EventStatus::Scheduled]);

        $conflicts = $this->service->detectConflicts(
            $mgr->id, [], $start, $end, excludeEventId: $event->id
        );

        $this->assertCount(0, $conflicts);
    }

    public function test_detect_conflicts_includes_attendee_overlap(): void
    {
        $branch = Branch::factory()->create();
        $organizer = $this->makeUser('branch-manager', $branch);
        $other = $this->makeUser('sales', $branch);

        Auth::login($organizer);

        $start = Carbon::now()->addDay()->startOfHour();
        $end = $start->copy()->addHour();

        // Event của user $other chạm vào khoảng — phải bị flag là conflict
        // khi $other có mặt trong attendees mới.
        Event::factory()->forBranchUser($other)
            ->startingAt($start, 60)
            ->create(['title' => 'OTHER_BUSY', 'status' => EventStatus::Scheduled]);

        $conflicts = $this->service->detectConflicts(
            $organizer->id, [$other->id], $start, $end
        );

        $this->assertCount(1, $conflicts);
        $this->assertSame('OTHER_BUSY', $conflicts->first()->title);
    }

    // ───────────────────── transaction safety ─────────────────────

    public function test_create_rolls_back_when_lead_validation_fails(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $admin = $this->makeUser('super-admin');
        $organizer = $this->makeUser('sales', $branchA);
        $foreignLead = Lead::factory()->forBranch($branchB)->create();

        Auth::login($admin);

        $beforeLevel = DB::transactionLevel();
        $caught = null;

        try {
            $this->service->create($this->basePayload($organizer->id, [
                'lead_id' => $foreignLead->id,
            ]));
        } catch (ValidationException $e) {
            $caught = $e;
        }

        $this->assertInstanceOf(ValidationException::class, $caught);
        $this->assertSame($beforeLevel, DB::transactionLevel());
        $this->assertSame(0, Event::withoutGlobalScope(BranchScope::class)->count());
    }
}
