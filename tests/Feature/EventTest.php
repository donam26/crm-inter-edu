<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Models\Branch;
use App\Models\Event;
use App\Models\Lead;
use App\Models\Scopes\BranchScope;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class EventTest extends TestCase
{
    use InteractsWithRbac, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRbac();
    }

    private function basePayload(array $override = []): array
    {
        $start = now()->addDay()->startOfHour();
        $end = $start->copy()->addHour();

        return array_merge([
            'title' => 'Họp giới thiệu chương trình',
            'description' => 'Trao đổi với khách hàng',
            'type' => EventType::Meeting->value,
            'starts_at' => $start->format('Y-m-d H:i:s'),
            'ends_at' => $end->format('Y-m-d H:i:s'),
            'is_online' => 0,
            'all_day' => 0,
            'location' => 'Văn phòng Hà Nội',
        ], $override);
    }

    // ───────────────────── guest ─────────────────────

    public function test_guest_redirected_from_index(): void
    {
        $this->get(route('events.index'))->assertRedirect(route('login'));
    }

    public function test_guest_redirected_from_store(): void
    {
        $this->post(route('events.store'), [])->assertRedirect(route('login'));
    }

    // ───────────────────── super-admin ─────────────────────

    public function test_super_admin_can_create_event_for_any_branch(): void
    {
        $admin = $this->makeUser('super-admin');
        $branch = Branch::factory()->create();
        $sales = $this->makeUser('sales', $branch);

        $this->actingAs($admin)
            ->post(route('events.store'), $this->basePayload([
                'organizer_user_id' => $sales->id,
            ]))
            ->assertRedirect();

        $event = Event::withoutGlobalScope(BranchScope::class)
            ->where('title', 'Họp giới thiệu chương trình')->firstOrFail();

        $this->assertSame($sales->id, $event->organizer_user_id);
        $this->assertSame($branch->id, $event->branch_id);
        $this->assertSame($admin->id, $event->created_by);
        $this->assertSame(EventStatus::Scheduled, $event->status);
    }

    public function test_super_admin_can_view_event_in_any_branch(): void
    {
        $admin = $this->makeUser('super-admin');
        $branch = Branch::factory()->create();
        $sales = $this->makeUser('sales', $branch);
        $event = Event::factory()->forBranchUser($sales)->create();

        $this->actingAs($admin)
            ->get(route('events.show', $event))
            ->assertOk();
    }

    // ───────────────────── branch-manager ─────────────────────

    public function test_branch_manager_can_create_event(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);

        $this->actingAs($mgr)
            ->post(route('events.store'), $this->basePayload([
                'organizer_user_id' => $mgr->id,
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('events', [
            'title' => 'Họp giới thiệu chương trình',
            'organizer_user_id' => $mgr->id,
            'branch_id' => $branch->id,
            'created_by' => $mgr->id,
            'status' => EventStatus::Scheduled->value,
        ]);
    }

    public function test_branch_manager_cannot_pick_organizer_from_other_branch(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $own);
        $foreign = $this->makeUser('sales', $other);

        $this->actingAs($mgr)
            ->from(route('events.create'))
            ->post(route('events.store'), $this->basePayload([
                'organizer_user_id' => $foreign->id,
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('organizer_user_id');

        $this->assertDatabaseMissing('events', ['title' => 'Họp giới thiệu chương trình']);
    }

    public function test_branch_manager_cannot_view_event_from_other_branch(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $own);
        $foreignSales = $this->makeUser('sales', $other);
        $event = Event::factory()->forBranchUser($foreignSales)->create();

        $this->actingAs($mgr)
            ->get(route('events.show', $event))
            ->assertNotFound(); // BranchScope ẩn → 404.
    }

    public function test_branch_manager_can_delete_event(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $sales = $this->makeUser('sales', $branch);
        $event = Event::factory()->forBranchUser($sales)->create();

        $this->actingAs($mgr)
            ->delete(route('events.destroy', $event))
            ->assertRedirect(route('events.index'));

        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    // ───────────────────── sales ─────────────────────

    public function test_sales_can_create_own_event(): void
    {
        $branch = Branch::factory()->create();
        $sales = $this->makeUser('sales', $branch);

        $this->actingAs($sales)
            ->post(route('events.store'), $this->basePayload([
                'organizer_user_id' => $sales->id,
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('events', [
            'organizer_user_id' => $sales->id,
            'created_by' => $sales->id,
        ]);
    }

    public function test_sales_cannot_view_other_sales_event_without_invitation(): void
    {
        $branch = Branch::factory()->create();
        $salesA = $this->makeUser('sales', $branch);
        $salesB = $this->makeUser('sales', $branch);
        $event = Event::factory()->forBranchUser($salesB)->create();

        $this->actingAs($salesA)
            ->get(route('events.show', $event))
            ->assertForbidden();
    }

    public function test_sales_can_view_event_when_invited(): void
    {
        $branch = Branch::factory()->create();
        $organizer = $this->makeUser('sales', $branch);
        $invitee = $this->makeUser('sales', $branch);
        $event = Event::factory()->forBranchUser($organizer)->create();
        $event->attendees()->attach($invitee->id, ['response' => 'pending']);

        $this->actingAs($invitee)
            ->get(route('events.show', $event))
            ->assertOk();
    }

    public function test_sales_can_view_event_for_lead_they_own(): void
    {
        $branch = Branch::factory()->create();
        $owner = $this->makeUser('sales', $branch);
        $organizer = $this->makeUser('sales', $branch);
        $lead = Lead::factory()->forBranch($branch)->create([
            'assigned_user_id' => $owner->id,
        ]);
        $event = Event::factory()->forBranchUser($organizer)->create([
            'lead_id' => $lead->id,
        ]);

        $this->actingAs($owner)
            ->get(route('events.show', $event))
            ->assertOk();
    }

    // ───────────────────── validation ─────────────────────

    public function test_validation_title_required(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);

        $this->actingAs($mgr)
            ->from(route('events.create'))
            ->post(route('events.store'), $this->basePayload([
                'title' => '',
                'organizer_user_id' => $mgr->id,
            ]))
            ->assertSessionHasErrors('title');
    }

    public function test_validation_starts_at_cannot_be_past_on_create(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);

        $this->actingAs($mgr)
            ->from(route('events.create'))
            ->post(route('events.store'), $this->basePayload([
                'starts_at' => now()->subDay()->format('Y-m-d H:i:s'),
                'ends_at' => now()->format('Y-m-d H:i:s'),
                'organizer_user_id' => $mgr->id,
            ]))
            ->assertSessionHasErrors('starts_at');
    }

    public function test_validation_ends_at_must_be_after_starts_at(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $start = now()->addDay();

        $this->actingAs($mgr)
            ->from(route('events.create'))
            ->post(route('events.store'), $this->basePayload([
                'starts_at' => $start->format('Y-m-d H:i:s'),
                'ends_at' => $start->copy()->subHour()->format('Y-m-d H:i:s'),
                'organizer_user_id' => $mgr->id,
            ]))
            ->assertSessionHasErrors('ends_at');
    }

    public function test_validation_lead_must_be_same_branch_as_organizer(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $admin = $this->makeUser('super-admin');
        $sales = $this->makeUser('sales', $branchA);
        $foreignLead = Lead::factory()->forBranch($branchB)->create();

        $this->actingAs($admin)
            ->from(route('events.create'))
            ->post(route('events.store'), $this->basePayload([
                'organizer_user_id' => $sales->id,
                'lead_id' => $foreignLead->id,
            ]))
            ->assertSessionHasErrors('lead_id');
    }

    public function test_validation_attendees_must_be_distinct(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $sales = $this->makeUser('sales', $branch);

        $this->actingAs($mgr)
            ->from(route('events.create'))
            ->post(route('events.store'), $this->basePayload([
                'organizer_user_id' => $mgr->id,
                'attendee_ids' => [$sales->id, $sales->id],
            ]))
            ->assertSessionHasErrors('attendee_ids.0');
    }

    public function test_validation_attendees_must_be_same_branch_as_organizer(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $admin = $this->makeUser('super-admin');
        $organizer = $this->makeUser('sales', $own);
        $foreign = $this->makeUser('sales', $other);

        $this->actingAs($admin)
            ->from(route('events.create'))
            ->post(route('events.store'), $this->basePayload([
                'organizer_user_id' => $organizer->id,
                'attendee_ids' => [$foreign->id],
            ]))
            ->assertSessionHasErrors('attendee_ids');
    }

    // ───────────────────── service injection ─────────────────────

    public function test_service_layer_ignores_user_supplied_branch_id_and_created_by(): void
    {
        $branch = Branch::factory()->create();
        $other = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $intruder = $this->makeUser('sales', $other);

        $this->actingAs($mgr)
            ->post(route('events.store'), $this->basePayload([
                'organizer_user_id' => $mgr->id,
                'branch_id' => $other->id,
                'created_by' => $intruder->id,
            ]))
            ->assertRedirect();

        $event = Event::withoutGlobalScope(BranchScope::class)
            ->where('title', 'Họp giới thiệu chương trình')->firstOrFail();

        $this->assertSame($branch->id, $event->branch_id);
        $this->assertSame($mgr->id, $event->created_by);
    }

    // ───────────────────── attendees attach ─────────────────────

    public function test_attendees_are_attached_with_pending_response(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $a = $this->makeUser('sales', $branch);
        $b = $this->makeUser('sales', $branch);

        $this->actingAs($mgr)
            ->post(route('events.store'), $this->basePayload([
                'organizer_user_id' => $mgr->id,
                'attendee_ids' => [$a->id, $b->id],
            ]))
            ->assertRedirect();

        $event = Event::withoutGlobalScope(BranchScope::class)
            ->where('title', 'Họp giới thiệu chương trình')->firstOrFail();

        $this->assertCount(2, $event->attendees);
        foreach ($event->attendees as $u) {
            $this->assertSame('pending', $u->pivot->response);
        }
    }

    public function test_attendee_pivot_is_unique(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $sales = $this->makeUser('sales', $branch);

        $event = Event::factory()->forBranchUser($mgr)->create();
        $event->attendees()->attach($sales->id, ['response' => 'pending']);

        // Attach lần thứ hai cùng (event_id, user_id) phải vi phạm unique.
        $this->expectException(QueryException::class);
        $event->attendees()->attach($sales->id, ['response' => 'accepted']);
    }

    // ───────────────────── lifecycle ─────────────────────

    public function test_mark_done_changes_status(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $event = Event::factory()->forBranchUser($mgr)->create();

        $this->actingAs($mgr)
            ->post(route('events.done', $event))
            ->assertRedirect();

        $this->assertSame(EventStatus::Done, $event->fresh()->status);
    }

    public function test_cannot_mark_done_when_cancelled(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $event = Event::factory()->forBranchUser($mgr)->status(EventStatus::Cancelled)->create();

        $this->actingAs($mgr)
            ->post(route('events.done', $event))
            ->assertSessionHasErrors('status');

        $this->assertSame(EventStatus::Cancelled, $event->fresh()->status);
    }

    public function test_cancel_changes_status(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $event = Event::factory()->forBranchUser($mgr)->create();

        $this->actingAs($mgr)
            ->post(route('events.cancel', $event))
            ->assertRedirect();

        $this->assertSame(EventStatus::Cancelled, $event->fresh()->status);
    }

    public function test_cannot_cancel_done_event(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $event = Event::factory()->forBranchUser($mgr)->status(EventStatus::Done)->create();

        $this->actingAs($mgr)
            ->post(route('events.cancel', $event))
            ->assertSessionHasErrors('status');
    }

    // ───────────────────── respond ─────────────────────

    public function test_attendee_can_respond_accepted(): void
    {
        $branch = Branch::factory()->create();
        $organizer = $this->makeUser('branch-manager', $branch);
        $invitee = $this->makeUser('sales', $branch);
        $event = Event::factory()->forBranchUser($organizer)->create();
        $event->attendees()->attach($invitee->id, ['response' => 'pending']);

        $this->actingAs($invitee)
            ->post(route('events.respond', $event), ['response' => 'accepted'])
            ->assertRedirect();

        $pivot = $event->attendees()->where('users.id', $invitee->id)->first()?->pivot;
        $this->assertSame('accepted', $pivot?->response);
        $this->assertNotNull($pivot?->responded_at);
    }

    public function test_non_attendee_cannot_respond(): void
    {
        $branch = Branch::factory()->create();
        $organizer = $this->makeUser('branch-manager', $branch);
        $bystander = $this->makeUser('sales', $branch);
        $event = Event::factory()->forBranchUser($organizer)->create();

        $this->actingAs($bystander)
            ->post(route('events.respond', $event), ['response' => 'accepted'])
            ->assertForbidden();
    }

    // ───────────────────── BranchScope isolation ─────────────────────

    public function test_index_does_not_leak_events_from_other_branch(): void
    {
        $branch = Branch::factory()->create();
        $other = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $foreignMgr = $this->makeUser('branch-manager', $other);

        Event::factory()->forBranchUser($mgr)->create(['title' => 'OWN_EVENT_X']);
        Event::factory()->forBranchUser($foreignMgr)->create(['title' => 'FOREIGN_EVENT_X']);

        $response = $this->actingAs($mgr)->get(route('events.index'))->assertOk();
        $this->assertStringContainsString('OWN_EVENT_X', $response->getContent());
        $this->assertStringNotContainsString('FOREIGN_EVENT_X', $response->getContent());
    }

    // ───────────────────── overdue ─────────────────────

    public function test_is_overdue_attribute_reflects_status_and_ends_at(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);

        $past = Event::factory()->forBranchUser($mgr)->create([
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
            'status' => EventStatus::Scheduled,
        ]);
        $future = Event::factory()->forBranchUser($mgr)->create([
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'status' => EventStatus::Scheduled,
        ]);
        $done = Event::factory()->forBranchUser($mgr)->create([
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
            'status' => EventStatus::Done,
        ]);

        $this->assertTrue($past->is_overdue);
        $this->assertFalse($future->is_overdue);
        $this->assertFalse($done->is_overdue);
    }

    // ───────────────────── lead cascade ─────────────────────

    public function test_deleting_lead_cascades_to_events(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $lead = Lead::factory()->forBranch($branch)->create();

        Event::factory()->forBranchUser($mgr)->count(2)->create(['lead_id' => $lead->id]);

        $this->assertSame(2, Event::withoutGlobalScope(BranchScope::class)
            ->where('lead_id', $lead->id)->count());

        $lead->delete();

        $this->assertSame(0, Event::withoutGlobalScope(BranchScope::class)
            ->where('lead_id', $lead->id)->count());
    }
}
