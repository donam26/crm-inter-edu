<?php

namespace Tests\Unit;

use App\Exceptions\BranchHasDependenciesException;
use App\Models\Branch;
use App\Models\User;
use App\Services\BranchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for BranchService.
 *
 * Validates: Requirements 5.3
 *
 * Phạm vi:
 *  - `create` insert đúng dữ liệu
 *  - `update` cập nhật và trả `fresh()` (instance mới, dữ liệu mới)
 *  - `delete` happy path xóa branch
 *  - `delete` rollback toàn bộ transaction khi exception ném ra (kiểm chứng
 *    transaction): branch vẫn tồn tại và transaction level đã unwind về 0.
 */
class BranchServiceTest extends TestCase
{
    use RefreshDatabase;

    private BranchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BranchService;
    }

    public function test_create_persists_new_branch_with_given_data(): void
    {
        $payload = [
            'name' => 'Sài Gòn Campus',
            'code' => 'BR-SG01',
            'address' => '123 Đồng Khởi',
            'phone' => '02800000000',
            'is_active' => true,
        ];

        $branch = $this->service->create($payload);

        $this->assertInstanceOf(Branch::class, $branch);
        $this->assertNotNull($branch->id);
        $this->assertSame('Sài Gòn Campus', $branch->name);
        $this->assertSame('BR-SG01', $branch->code);
        $this->assertTrue($branch->is_active);

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'name' => 'Sài Gòn Campus',
            'code' => 'BR-SG01',
            'address' => '123 Đồng Khởi',
            'phone' => '02800000000',
            'is_active' => true,
        ]);
    }

    public function test_update_modifies_existing_branch_and_returns_fresh_instance(): void
    {
        $branch = Branch::factory()->create([
            'name' => 'Old Name',
            'code' => 'BR-OLD',
        ]);

        $updated = $this->service->update($branch, [
            'name' => 'New Name',
            'code' => 'BR-NEW',
        ]);

        // Trả instance mới (fresh), không phải reference cũ
        $this->assertInstanceOf(Branch::class, $updated);
        $this->assertNotSame($branch, $updated);
        $this->assertTrue($updated->is($branch));

        // Dữ liệu mới đã được persist và load lại
        $this->assertSame('New Name', $updated->name);
        $this->assertSame('BR-NEW', $updated->code);

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'name' => 'New Name',
            'code' => 'BR-NEW',
        ]);
        $this->assertDatabaseMissing('branches', [
            'id' => $branch->id,
            'code' => 'BR-OLD',
        ]);
    }

    public function test_delete_removes_branch_when_no_dependencies(): void
    {
        $branch = Branch::factory()->create();

        $this->service->delete($branch);

        $this->assertDatabaseMissing('branches', ['id' => $branch->id]);
    }

    public function test_delete_throws_and_rolls_back_transaction_when_branch_has_users(): void
    {
        $branch = Branch::factory()->create();
        User::factory()->create(['branch_id' => $branch->id]);

        // Snapshot transaction level trước khi gọi để xác minh sau khi exception
        // ném ra, transaction count vẫn ở mức ban đầu (đã được rollback gọn gàng).
        $beforeLevel = DB::transactionLevel();

        $caught = null;
        try {
            $this->service->delete($branch);
        } catch (BranchHasDependenciesException $e) {
            $caught = $e;
        }

        $this->assertInstanceOf(
            BranchHasDependenciesException::class,
            $caught,
            'BranchService::delete phải ném BranchHasDependenciesException khi branch còn user.'
        );

        // Transaction phải được rollback hoàn toàn (level về như trước khi gọi).
        $this->assertSame($beforeLevel, DB::transactionLevel());

        // Branch vẫn còn nguyên trong DB → rollback đã thực sự undo mọi thay đổi.
        $this->assertDatabaseHas('branches', ['id' => $branch->id]);
    }
}
