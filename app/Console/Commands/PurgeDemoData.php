<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Xoá dữ liệu DEMO do seeder tạo ra khỏi một môi trường đang chạy (local hoặc
 * production đã lỡ chạy `db:seed`). "Demo" được nhận diện chắc chắn qua:
 *  - User có email @inter-edu.local (BranchUserSeeder + SuperAdminSeeder).
 *  - Sản phẩm mẫu theo bộ mã catalog của ProductSeeder.
 * Cùng toàn bộ lead/deal/task/event/hoá đơn/... GẮN VỚI user demo (hoặc lead
 * demo). KHÔNG đụng tới: user thật, chi nhánh, permission/role, hay bất kỳ bản
 * ghi nào của người dùng thật.
 *
 * An toàn: mặc định chạy DRY-RUN (chỉ đếm, không xoá). Phải thêm --force để xoá
 * thật; khi đó mọi thao tác nằm trong một transaction, xoá theo đúng thứ tự khoá
 * ngoại (lá → gốc) để tránh vướng restrictOnDelete.
 */
class PurgeDemoData extends Command
{
    protected $signature = 'demo:purge
        {--force : Thực sự xoá (mặc định chỉ dry-run, không xoá gì)}
        {--with-super-admin : Xoá luôn tài khoản super-admin demo (admin@inter-edu.local)}';

    protected $description = 'Xoá dữ liệu demo do seeder tạo (user @inter-edu.local, sản phẩm mẫu, và dữ liệu nghiệp vụ của user demo). Giữ nguyên user thật, chi nhánh và phân quyền.';

    /** Bộ mã sản phẩm mẫu của ProductSeeder — mã thật do người dùng tạo sẽ khác. */
    private const DEMO_PRODUCT_CODE_REGEXP = '^(TFL-PRI|TFL-JNR|CAM-YLE|IELTS-G|TOEIC)-[0-9]+$';

    public function handle(): int
    {
        $demoUserIds = DB::table('users')
            ->where('email', 'like', '%@inter-edu.local')
            ->pluck('id')->all();

        if ($demoUserIds === []) {
            $this->info('Không tìm thấy user demo (@inter-edu.local) — không có gì để xoá.');

            return self::SUCCESS;
        }

        // Super-admin demo (branch_id null): giữ lại trừ khi --with-super-admin,
        // vì đây thường là tài khoản quản trị duy nhất còn đăng nhập được.
        $superAdminIds = DB::table('users')
            ->where('email', 'like', '%@inter-edu.local')
            ->whereNull('branch_id')
            ->pluck('id')->all();

        $deletableUserIds = $this->option('with-super-admin')
            ? $demoUserIds
            : array_values(array_diff($demoUserIds, $superAdminIds));

        // Chi nhánh demo = chi nhánh có ít nhất một user demo. Dùng để bắt cả
        // lead demo CHƯA gán ai (assigned_user_id null) mà scope-theo-user bỏ sót.
        $demoBranchIds = DB::table('users')
            ->whereIn('id', $demoUserIds)
            ->whereNotNull('branch_id')
            ->distinct()->pluck('branch_id')->all();

        // Chụp trước tập id cha để thứ tự xoá không làm sai lệch truy vấn con.
        // Mọi bản ghi nghiệp vụ "chạm" tới user demo hoặc lead demo đều được gom.
        // Lead demo: thuộc chi nhánh demo VÀ không gán cho user thật (giữ an toàn
        // nếu người dùng thật lỡ được gán lead trong chi nhánh demo).
        $leadIds = DB::table('leads')
            ->whereIn('branch_id', $demoBranchIds ?: [0])
            ->where(fn ($q) => $q->whereNull('assigned_user_id')->orWhereIn('assigned_user_id', $demoUserIds))
            ->pluck('id')->all();
        $taskIds = DB::table('tasks')
            ->whereIn('created_by', $demoUserIds)
            ->orWhereIn('assigned_user_id', $demoUserIds)
            ->orWhereIn('lead_id', $leadIds ?: [0])
            ->pluck('id')->all();
        $eventIds = DB::table('events')
            ->whereIn('created_by', $demoUserIds)
            ->orWhereIn('organizer_user_id', $demoUserIds)
            ->orWhereIn('lead_id', $leadIds ?: [0])
            ->pluck('id')->all();
        $dealIds = DB::table('deals')
            ->whereIn('created_by', $demoUserIds)
            ->orWhereIn('owner_user_id', $demoUserIds)
            ->orWhereIn('lead_id', $leadIds ?: [0])
            ->pluck('id')->all();
        $invoiceIds = DB::table('invoices')
            ->whereIn('created_by', $demoUserIds)
            ->orWhereIn('deal_id', $dealIds ?: [0])
            ->pluck('id')->all();
        $productIds = DB::table('products')
            ->where('code', 'REGEXP', self::DEMO_PRODUCT_CODE_REGEXP)
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
            ['activities', DB::table('activities')->whereIn('user_id', $demoUserIds)->orWhereIn('lead_id', $safe($leadIds))],
            ['contacts', DB::table('contacts')->whereIn('lead_id', $safe($leadIds))],
            ['leads', DB::table('leads')->whereIn('id', $safe($leadIds))],
            ['products', DB::table('products')->whereIn('id', $safe($productIds))],
            ['model_has_roles', DB::table('model_has_roles')->where('model_type', (new User)->getMorphClass())->whereIn('model_id', $safe($deletableUserIds))],
            ['users', DB::table('users')->whereIn('id', $safe($deletableUserIds))],
        ];

        $force = (bool) $this->option('force');

        $this->newLine();
        $this->line($force
            ? '<fg=red;options=bold>CHẾ ĐỘ XOÁ THẬT</> — dữ liệu demo sẽ bị xoá vĩnh viễn.'
            : '<fg=yellow;options=bold>DRY-RUN</> — chỉ liệt kê, không xoá. Thêm <options=bold>--force</> để xoá thật.');

        $rows = [];
        $total = 0;

        $execute = function () use ($steps, $force, &$rows, &$total) {
            foreach ($steps as [$table, $query]) {
                $affected = $force ? $query->delete() : $query->count();
                $rows[] = [$table, $affected];
                $total += $affected;
            }
        };

        if ($force) {
            DB::transaction($execute);
        } else {
            $execute();
        }

        $this->newLine();
        $this->table(['Bảng', $force ? 'Đã xoá' : 'Sẽ xoá'], $rows);

        $keptSuperAdmin = ! $this->option('with-super-admin') && $superAdminIds !== [];
        $this->info(sprintf(
            '%s %d dòng · %d user demo%s · giữ nguyên user thật, chi nhánh, phân quyền.',
            $force ? 'Đã xoá tổng' : 'Sẽ xoá tổng',
            $total,
            count($deletableUserIds),
            $keptSuperAdmin ? ' (giữ super-admin demo)' : '',
        ));

        if (! $force) {
            $this->newLine();
            $this->comment('Chạy lại với --force để thực hiện xoá.');
        }

        return self::SUCCESS;
    }
}
