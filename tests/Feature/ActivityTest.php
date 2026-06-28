<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Branch;
use App\Models\Lead;
use App\Models\Scopes\BranchScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class ActivityTest extends TestCase
{
    use InteractsWithRbac, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRbac();
    }

    // ───────────────────── guest ─────────────────────

    public function test_guest_redirected_when_creating_activity(): void
    {
        $branch = Branch::factory()->create();
        $lead = Lead::factory()->forBranch($branch)->create();

        $this->get(route('leads.activities.create', $lead))
            ->assertRedirect(route('login'));
    }

    // ───────────────────── super-admin ─────────────────────

    public function test_super_admin_can_create_activity(): void
    {
        $admin = $this->makeUser('super-admin');
        $branch = Branch::factory()->create();
        $lead = Lead::factory()->forBranch($branch)->create();

        $this->actingAs($admin)
            ->post(route('leads.activities.store', $lead), [
                'type' => 'meeting',
                'subject' => 'Họp tư vấn',
                'content' => 'Nội dung họp',
                'happened_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect(route('leads.show', $lead));

        $this->assertDatabaseHas('activities', [
            'lead_id' => $lead->id,
            'branch_id' => $branch->id,
            'user_id' => $admin->id,
            'type' => 'meeting',
            'subject' => 'Họp tư vấn',
        ]);
    }

    public function test_super_admin_can_destroy_activity(): void
    {
        $admin = $this->makeUser('super-admin');
        $branch = Branch::factory()->create();
        $sales = $this->makeUser('sales', $branch);
        $lead = Lead::factory()->forBranch($branch)->create();
        $activity = Activity::factory()->forLead($lead)->create(['user_id' => $sales->id]);

        $this->actingAs($admin)
            ->delete(route('activities.destroy', $activity))
            ->assertRedirect(route('leads.show', $lead->id));

        $this->assertDatabaseMissing('activities', ['id' => $activity->id]);
    }

    // ───────────────────── branch-manager ─────────────────────

    public function test_branch_manager_can_create_activity(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $lead = Lead::factory()->forBranch($branch)->create();

        $this->actingAs($mgr)
            ->post(route('leads.activities.store', $lead), [
                'type' => 'call',
                'subject' => 'Gọi tư vấn',
                'content' => 'Đã trao đổi 30 phút.',
                'happened_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect(route('leads.show', $lead));

        $this->assertDatabaseHas('activities', [
            'lead_id' => $lead->id,
            'branch_id' => $branch->id,
            'user_id' => $mgr->id,
            'type' => 'call',
            'subject' => 'Gọi tư vấn',
        ]);
    }

    public function test_branch_manager_can_update_activity(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $lead = Lead::factory()->forBranch($branch)->create();
        $activity = Activity::factory()->forLead($lead)->create([
            'user_id' => $mgr->id,
            'subject' => 'Old subject',
            'type' => 'note',
        ]);

        $this->actingAs($mgr)
            ->put(route('activities.update', $activity), [
                'type' => 'email',
                'subject' => 'New subject',
                'content' => 'Updated content',
                'happened_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect(route('leads.show', $lead->id));

        $this->assertDatabaseHas('activities', [
            'id' => $activity->id,
            'type' => 'email',
            'subject' => 'New subject',
        ]);
    }

    public function test_branch_manager_cannot_view_activity_in_other_branch(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branchA);
        $lead = Lead::factory()->forBranch($branchB)->create();
        $sales = $this->makeUser('sales', $branchB);
        $activity = Activity::factory()->forLead($lead)->create(['user_id' => $sales->id]);

        // BranchScope ẩn activity thuộc branch khác → 404 model not found.
        $this->actingAs($mgr)
            ->get(route('activities.show', $activity))
            ->assertNotFound();
    }

    // ───────────────────── sales ─────────────────────

    public function test_sales_can_create_activity_for_assigned_lead(): void
    {
        $branch = Branch::factory()->create();
        $sales = $this->makeUser('sales', $branch);
        $lead = Lead::factory()->forBranch($branch)->create([
            'assigned_user_id' => $sales->id,
        ]);

        $this->actingAs($sales)
            ->post(route('leads.activities.store', $lead), [
                'type' => 'call',
                'subject' => 'My call',
                'happened_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect(route('leads.show', $lead));

        $this->assertDatabaseHas('activities', [
            'lead_id' => $lead->id,
            'user_id' => $sales->id,
            'subject' => 'My call',
        ]);
    }

    public function test_sales_cannot_create_activity_for_unassigned_lead(): void
    {
        $branch = Branch::factory()->create();
        $sales = $this->makeUser('sales', $branch);
        $other = $this->makeUser('sales', $branch);
        $lead = Lead::factory()->forBranch($branch)->create([
            'assigned_user_id' => $other->id,
        ]);

        $this->actingAs($sales)
            ->post(route('leads.activities.store', $lead), [
                'type' => 'call',
                'subject' => 'Cannot create',
                'happened_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('activities', [
            'lead_id' => $lead->id,
            'subject' => 'Cannot create',
        ]);
    }

    // ───────────────────── validation ─────────────────────

    public function test_validation_type_must_be_in_enum(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $lead = Lead::factory()->forBranch($branch)->create();

        $this->actingAs($mgr)
            ->from(route('leads.activities.create', $lead))
            ->post(route('leads.activities.store', $lead), [
                'type' => 'invalid-type',
                'subject' => 'Bad type',
                'happened_at' => now()->toDateTimeString(),
            ])
            ->assertRedirect(route('leads.activities.create', $lead))
            ->assertSessionHasErrors('type');

        $this->assertDatabaseMissing('activities', ['subject' => 'Bad type']);
    }

    public function test_validation_subject_required(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $lead = Lead::factory()->forBranch($branch)->create();

        $this->actingAs($mgr)
            ->from(route('leads.activities.create', $lead))
            ->post(route('leads.activities.store', $lead), [
                'type' => 'call',
                'happened_at' => now()->toDateTimeString(),
            ])
            ->assertSessionHasErrors('subject');
    }

    public function test_validation_happened_at_required(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $lead = Lead::factory()->forBranch($branch)->create();

        $this->actingAs($mgr)
            ->from(route('leads.activities.create', $lead))
            ->post(route('leads.activities.store', $lead), [
                'type' => 'call',
                'subject' => 'No time',
            ])
            ->assertSessionHasErrors('happened_at');
    }

    // ───────────────────── service injection ─────────────────────

    public function test_service_layer_ignores_user_supplied_user_branch_and_lead_id(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $own);
        $intruder = $this->makeUser('sales', $other);
        $lead = Lead::factory()->forBranch($own)->create();
        $foreignLead = Lead::factory()->forBranch($other)->create();

        $this->actingAs($mgr)
            ->post(route('leads.activities.store', $lead), [
                'type' => 'note',
                'subject' => 'Hack Attempt',
                'happened_at' => now()->toDateTimeString(),
                'user_id' => $intruder->id,
                'branch_id' => $other->id,
                'lead_id' => $foreignLead->id,
            ])
            ->assertRedirect();

        $activity = Activity::withoutGlobalScope(BranchScope::class)
            ->where('subject', 'Hack Attempt')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame($mgr->id, $activity->user_id);
        $this->assertSame($own->id, $activity->branch_id);
        $this->assertSame($lead->id, $activity->lead_id);
    }

    public function test_update_ignores_user_supplied_user_branch_and_lead_id(): void
    {
        $branch = Branch::factory()->create();
        $other = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $intruder = $this->makeUser('sales', $other);
        $lead = Lead::factory()->forBranch($branch)->create();
        $foreignLead = Lead::factory()->forBranch($other)->create();
        $activity = Activity::factory()->forLead($lead)->create([
            'user_id' => $mgr->id,
            'subject' => 'Original',
            'type' => 'note',
        ]);

        $this->actingAs($mgr)
            ->put(route('activities.update', $activity), [
                'type' => 'note',
                'subject' => 'Updated',
                'happened_at' => now()->toDateTimeString(),
                'user_id' => $intruder->id,
                'branch_id' => $other->id,
                'lead_id' => $foreignLead->id,
            ])
            ->assertRedirect();

        $activity->refresh();
        $this->assertSame($mgr->id, $activity->user_id);
        $this->assertSame($branch->id, $activity->branch_id);
        $this->assertSame($lead->id, $activity->lead_id);
        $this->assertSame('Updated', $activity->subject);
    }

    // ───────────────────── ordering ─────────────────────

    public function test_lead_show_page_lists_activities_descending(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $lead = Lead::factory()->forBranch($branch)->create();

        Activity::factory()->forLead($lead)->create([
            'user_id' => $mgr->id,
            'subject' => 'Older activity',
            'happened_at' => now()->subDays(5),
        ]);
        Activity::factory()->forLead($lead)->create([
            'user_id' => $mgr->id,
            'subject' => 'Newer activity',
            'happened_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($mgr)
            ->get(route('leads.show', $lead))
            ->assertOk();
        $content = $response->getContent();

        $newerPos = strpos($content, 'Newer activity');
        $olderPos = strpos($content, 'Older activity');

        $this->assertNotFalse($newerPos, 'Newer activity should be present');
        $this->assertNotFalse($olderPos, 'Older activity should be present');
        $this->assertLessThan($olderPos, $newerPos, 'Newer activity must appear before older');
    }

    // ───────────────────── happened_at vs created_at ─────────────────────

    public function test_happened_at_and_created_at_are_distinct_fields(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $lead = Lead::factory()->forBranch($branch)->create();

        $past = now()->subDays(10)->startOfMinute();

        $this->actingAs($mgr)
            ->post(route('leads.activities.store', $lead), [
                'type' => 'note',
                'subject' => 'Backdated note',
                'happened_at' => $past->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect();

        $activity = Activity::withoutGlobalScope(BranchScope::class)
            ->where('subject', 'Backdated note')
            ->first();

        $this->assertNotNull($activity);
        // happened_at lưu đúng giá trị người dùng nhập (10 ngày trước),
        // còn created_at là thời điểm record được insert (≈ now).
        $this->assertSame(
            $past->toDateTimeString(),
            $activity->happened_at->toDateTimeString()
        );
        $this->assertNotEquals(
            $activity->created_at->toDateTimeString(),
            $activity->happened_at->toDateTimeString()
        );
        $this->assertTrue($activity->created_at->greaterThan($activity->happened_at));
    }

    // ───────────────────── cascade delete ─────────────────────

    public function test_deleting_lead_cascades_to_activities(): void
    {
        $branch = Branch::factory()->create();
        $sales = $this->makeUser('sales', $branch);
        $lead = Lead::factory()->forBranch($branch)->create();
        Activity::factory()->forLead($lead)->count(3)->create(['user_id' => $sales->id]);

        $this->assertSame(
            3,
            Activity::withoutGlobalScope(BranchScope::class)
                ->where('lead_id', $lead->id)
                ->count()
        );

        $lead->delete();

        $this->assertSame(
            0,
            Activity::withoutGlobalScope(BranchScope::class)
                ->where('lead_id', $lead->id)
                ->count()
        );
    }
}
