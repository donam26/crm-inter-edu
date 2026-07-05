<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Scopes\BranchScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use InteractsWithRbac, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRbac();
    }

    // ───────────────────── guest ─────────────────────

    public function test_guest_redirected_when_creating_contact(): void
    {
        $branch = Branch::factory()->create();
        $customer = Customer::factory()->forBranch($branch)->create();

        $this->get(route('customers.contacts.create', $customer))
            ->assertRedirect(route('login'));
    }

    // ───────────────────── super-admin ─────────────────────

    public function test_super_admin_can_create_contact(): void
    {
        $admin = $this->makeUser('super-admin');
        $branch = Branch::factory()->create();
        $customer = Customer::factory()->forBranch($branch)->create();

        $this->actingAs($admin)
            ->post(route('customers.contacts.store', $customer), [
                'full_name' => 'Nguyễn Văn A',
                'email' => 'a@example.com',
            ])
            ->assertRedirect(route('customers.show', $customer));

        $this->assertDatabaseHas('contacts', [
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'full_name' => 'Nguyễn Văn A',
            'email' => 'a@example.com',
        ]);
    }

    public function test_super_admin_can_update_contact(): void
    {
        $admin = $this->makeUser('super-admin');
        $branch = Branch::factory()->create();
        $customer = Customer::factory()->forBranch($branch)->create();
        $contact = Contact::factory()->forLead($customer)->create([
            'full_name' => 'Old Name',
            'email' => 'old@example.com',
        ]);

        $this->actingAs($admin)
            ->put(route('contacts.update', $contact), [
                'full_name' => 'New Name',
                'email' => 'old@example.com',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
            'full_name' => 'New Name',
        ]);
    }

    // ───────────────────── branch-manager ─────────────────────

    public function test_branch_manager_can_create_contact_in_own_branch(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $customer = Customer::factory()->forBranch($branch)->create();

        $this->actingAs($mgr)
            ->post(route('customers.contacts.store', $customer), [
                'full_name' => 'Trần Thị B',
                'phone' => '0901234567',
                'is_primary' => '1',
            ])
            ->assertRedirect(route('customers.show', $customer));

        $this->assertDatabaseHas('contacts', [
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'full_name' => 'Trần Thị B',
            'is_primary' => true,
        ]);
    }

    public function test_branch_manager_cannot_view_contact_in_other_branch(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branchA);
        $customer = Customer::factory()->forBranch($branchB)->create();
        $contact = Contact::factory()->forLead($customer)->create();

        // BranchScope ẩn contact thuộc branch khác → 404 model not found.
        $this->actingAs($mgr)
            ->get(route('contacts.show', $contact))
            ->assertNotFound();
    }

    public function test_branch_manager_can_destroy_contact(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $customer = Customer::factory()->forBranch($branch)->create();
        $contact = Contact::factory()->forLead($customer)->create();

        $this->actingAs($mgr)
            ->delete(route('contacts.destroy', $contact))
            ->assertRedirect(route('customers.show', $customer));

        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
    }

    // ───────────────────── sales ─────────────────────

    public function test_sales_can_create_contact_for_assigned_lead(): void
    {
        $branch = Branch::factory()->create();
        $sales = $this->makeUser('sales', $branch);
        $customer = Customer::factory()->forBranch($branch)->create([
            'assigned_user_id' => $sales->id,
        ]);

        $this->actingAs($sales)
            ->post(route('customers.contacts.store', $customer), [
                'full_name' => 'My Contact',
                'email' => 'mine@example.com',
            ])
            ->assertRedirect(route('customers.show', $customer));

        $this->assertDatabaseHas('contacts', [
            'customer_id' => $customer->id,
            'full_name' => 'My Contact',
        ]);
    }

    public function test_sales_cannot_create_contact_for_unassigned_lead(): void
    {
        $branch = Branch::factory()->create();
        $sales = $this->makeUser('sales', $branch);
        $other = $this->makeUser('sales', $branch);
        $customer = Customer::factory()->forBranch($branch)->create([
            'assigned_user_id' => $other->id,
        ]);

        $this->actingAs($sales)
            ->post(route('customers.contacts.store', $customer), [
                'full_name' => 'Cannot create',
                'email' => 'no@example.com',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('contacts', [
            'customer_id' => $customer->id,
            'full_name' => 'Cannot create',
        ]);
    }

    // ───────────────────── validation ─────────────────────

    public function test_validation_requires_email_or_phone(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $customer = Customer::factory()->forBranch($branch)->create();

        $this->actingAs($mgr)
            ->from(route('customers.contacts.create', $customer))
            ->post(route('customers.contacts.store', $customer), [
                'full_name' => 'No Contact Method',
            ])
            ->assertRedirect(route('customers.contacts.create', $customer))
            ->assertSessionHasErrors(['email', 'phone']);
    }

    public function test_validation_full_name_required(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $customer = Customer::factory()->forBranch($branch)->create();

        $this->actingAs($mgr)
            ->from(route('customers.contacts.create', $customer))
            ->post(route('customers.contacts.store', $customer), [
                'email' => 'a@example.com',
            ])
            ->assertSessionHasErrors('full_name');
    }

    // ───────────────────── primary uniqueness ─────────────────────

    public function test_creating_primary_contact_demotes_existing_primary(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $customer = Customer::factory()->forBranch($branch)->create();
        $existing = Contact::factory()->forLead($customer)->primary()->create();

        $this->actingAs($mgr)
            ->post(route('customers.contacts.store', $customer), [
                'full_name' => 'New Primary',
                'phone' => '0900000000',
                'is_primary' => '1',
            ])
            ->assertRedirect();

        $existing->refresh();
        $this->assertFalse($existing->is_primary);

        $primaryCount = Contact::withoutGlobalScope(BranchScope::class)
            ->where('customer_id', $customer->id)
            ->where('is_primary', true)
            ->count();
        $this->assertSame(1, $primaryCount);
    }

    public function test_updating_contact_to_primary_demotes_others(): void
    {
        $branch = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $branch);
        $customer = Customer::factory()->forBranch($branch)->create();
        $existingPrimary = Contact::factory()->forLead($customer)->primary()->create();
        $other = Contact::factory()->forLead($customer)->create();

        $this->actingAs($mgr)
            ->put(route('contacts.update', $other), [
                'full_name' => $other->full_name,
                'email' => $other->email,
                'is_primary' => '1',
            ])
            ->assertRedirect();

        $existingPrimary->refresh();
        $other->refresh();
        $this->assertFalse($existingPrimary->is_primary);
        $this->assertTrue($other->is_primary);
    }

    // ───────────────────── cascade delete ─────────────────────

    public function test_deleting_lead_cascades_to_contacts(): void
    {
        $branch = Branch::factory()->create();
        $customer = Customer::factory()->forBranch($branch)->create();
        Contact::factory()->forLead($customer)->count(3)->create();

        $this->assertSame(
            3,
            Contact::withoutGlobalScope(BranchScope::class)
                ->where('customer_id', $customer->id)
                ->count()
        );

        $customer->delete();

        $this->assertSame(
            0,
            Contact::withoutGlobalScope(BranchScope::class)
                ->where('customer_id', $customer->id)
                ->count()
        );
    }

    // ───────────────────── service injection ─────────────────────

    public function test_service_layer_ignores_user_supplied_lead_and_branch_id(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $mgr = $this->makeUser('branch-manager', $own);
        $customer = Customer::factory()->forBranch($own)->create();
        $foreignLead = Customer::factory()->forBranch($other)->create();

        $this->actingAs($mgr)
            ->post(route('customers.contacts.store', $customer), [
                'full_name' => 'Hack Attempt',
                'email' => 'hack@example.com',
                'customer_id' => $foreignLead->id,
                'branch_id' => $other->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('contacts', [
            'full_name' => 'Hack Attempt',
            'customer_id' => $customer->id,
            'branch_id' => $own->id,
        ]);
    }
}
