<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Dọn USER ẢO (dữ liệu demo do seeder tạo) khỏi môi trường đang chạy.
 *
 * Nhận diện demo qua email @inter-edu.local (BranchUserSeeder / SuperAdminSeeder)
 * và bộ mã sản phẩm mẫu của ProductSeeder. GIỮ LẠI super-admin demo
 * (admin@inter-edu.local, branch_id NULL) vì đây là tài khoản admin toàn quyền
 * còn đăng nhập được — chỉ xoá các user ảo branch-manager/sales (có branch).
 *
 * Xoá luôn dữ liệu nghiệp vụ demo gắn với các user ảo đó (customer/deal/task/
 * event/hoá đơn/thanh toán/hoạt động...) theo đúng thứ tự khoá ngoại (lá → gốc).
 * KHÔNG đụng tới user thật, chi nhánh, hay phân quyền/role.
 *
 * Tương đương `php artisan demo:purge --force` (không kèm --with-super-admin),
 * đóng gói thành migration để tự chạy khi deploy. KHÔNG thể hoàn tác (down no-op).
 */
return new class extends Migration
{
    /** Bộ mã sản phẩm mẫu của ProductSeeder — mã thật do người dùng tạo sẽ khác. */
    private const DEMO_PRODUCT_CODE_REGEXP = '^(TFL-PRI|TFL-JNR|CAM-YLE|IELTS-G|TOEIC)-[0-9]+$';

    public function up(): void
    {
        $demoUserIds = DB::table('users')
            ->where('email', 'like', '%@inter-edu.local')
            ->pluck('id')->all();

        if ($demoUserIds === []) {
            return; // Không có user demo — không làm gì.
        }

        // Giữ super-admin demo (branch_id NULL): chỉ xoá user ảo có chi nhánh.
        $deletableUserIds = DB::table('users')
            ->where('email', 'like', '%@inter-edu.local')
            ->whereNotNull('branch_id')
            ->pluck('id')->all();

        // Chi nhánh demo = chi nhánh có ít nhất một user demo → bắt cả customer demo
        // chưa gán ai (assigned_user_id null) mà lọc-theo-user có thể bỏ sót.
        $demoBranchIds = DB::table('users')
            ->whereIn('id', $demoUserIds)
            ->whereNotNull('branch_id')
            ->distinct()->pluck('branch_id')->all();

        // Chụp trước tập id cha để thứ tự xoá không làm sai lệch truy vấn con.
        $customerIds = DB::table('customers')
            ->whereIn('branch_id', $demoBranchIds ?: [0])
            ->where(fn ($q) => $q->whereNull('assigned_user_id')->orWhereIn('assigned_user_id', $demoUserIds))
            ->pluck('id')->all();
        $taskIds = DB::table('tasks')
            ->whereIn('created_by', $demoUserIds)
            ->orWhereIn('assigned_user_id', $demoUserIds)
            ->orWhereIn('customer_id', $customerIds ?: [0])
            ->pluck('id')->all();
        $eventIds = DB::table('events')
            ->whereIn('created_by', $demoUserIds)
            ->orWhereIn('organizer_user_id', $demoUserIds)
            ->orWhereIn('customer_id', $customerIds ?: [0])
            ->pluck('id')->all();
        $dealIds = DB::table('deals')
            ->whereIn('created_by', $demoUserIds)
            ->orWhereIn('owner_user_id', $demoUserIds)
            ->orWhereIn('customer_id', $customerIds ?: [0])
            ->pluck('id')->all();
        $invoiceIds = DB::table('invoices')
            ->whereIn('created_by', $demoUserIds)
            ->orWhereIn('deal_id', $dealIds ?: [0])
            ->pluck('id')->all();

        $safe = fn (array $ids) => $ids ?: [0]; // whereIn rỗng → không khớp gì.

        // Thứ tự xoá bắt buộc theo FK: pivot/con trước, cha sau.
        $steps = [
            ['label_task', DB::table('label_task')->whereIn('task_id', $safe($taskIds))],
            ['task_watchers', DB::table('task_watchers')->whereIn('task_id', $safe($taskIds))],
            ['task_comments', DB::table('task_comments')->whereIn('task_id', $safe($taskIds))],
            ['task_checklist_items', DB::table('task_checklist_items')->whereIn('task_id', $safe($taskIds))],
            ['tasks', DB::table('tasks')->whereIn('id', $safe($taskIds))],
            ['event_user', DB::table('event_user')->whereIn('event_id', $safe($eventIds))],
            ['events', DB::table('events')->whereIn('id', $safe($eventIds))],
            ['payments', DB::table('payments')->whereIn('invoice_id', $safe($invoiceIds))->orWhereIn('created_by', $demoUserIds)],
            ['invoices', DB::table('invoices')->whereIn('id', $safe($invoiceIds))],
            ['deal_items', DB::table('deal_items')->whereIn('deal_id', $safe($dealIds))],
            ['deals', DB::table('deals')->whereIn('id', $safe($dealIds))],
            ['activities', DB::table('activities')->whereIn('user_id', $demoUserIds)->orWhereIn('customer_id', $safe($customerIds))],
            ['contacts', DB::table('contacts')->whereIn('customer_id', $safe($customerIds))],
            ['customers', DB::table('customers')->whereIn('id', $safe($customerIds))],
            // Sản phẩm demo: chỉ xoá bản ghi KHÔNG còn deal_item nào tham chiếu
            // (deal_items demo đã bị xoá phía trên) → tránh vướng khoá ngoại.
            ['products', DB::table('products')
                ->where('code', 'REGEXP', self::DEMO_PRODUCT_CODE_REGEXP)
                ->whereNotIn('id', DB::table('deal_items')->select('product_id'))],
            ['model_has_roles', DB::table('model_has_roles')
                ->where('model_type', (new User)->getMorphClass())
                ->whereIn('model_id', $safe($deletableUserIds))],
            ['users', DB::table('users')->whereIn('id', $safe($deletableUserIds))],
        ];

        DB::transaction(function () use ($steps) {
            foreach ($steps as [, $query]) {
                $query->delete();
            }
        });
    }

    public function down(): void
    {
        // Xoá dữ liệu demo là thao tác không thể hoàn tác.
    }
};
