# Giai đoạn 1 — Chiều sâu work-item

**Mục tiêu:** biến 1 dòng task thành hồ sơ công việc có trách nhiệm giải trình: bình luận, lịch sử thay đổi, checklist, nhãn, ngày bắt đầu — và trang chi tiết + thẻ Kanban thể hiện được.

**Tiêu chí nghiệm thu**
- [x] Mở 1 task thấy: mô tả, checklist (tick được, hiện %), nhãn, bình luận (thêm/xoá), timeline lịch sử (ai đổi trạng thái/assignee/hạn lúc nào).
- [x] Thẻ Kanban hiện: vạch màu ưu tiên, nhãn, tiến độ checklist (vd `3/5`), cờ quá hạn.
- [x] Mọi thao tác tôn trọng phạm vi branch + RBAC (sales chỉ thao tác trên task trong tầm; policy chặn cross-branch). **Đã đạt** — finding #1 (HIGH) đã sửa: `TaskCommentPolicy::create()`/`delete()` uỷ quyền qua `TaskPolicy::view()` (view-all / assignee / phụ trách lead); test `test_sales_cannot_comment_on_task_outside_scope` khoá lại.
- [x] `php -l` + `pint` sạch; feature test policy + service xanh.

**Code review (2026-07-04):** báo cáo đầy đủ tại `plans/reports/code-reviewer-260704-1635-tasks-work-item-depth.md`. **Đã xử lý:** HIGH (policy gap — fixed + test), MEDIUM (N+1 activity feed — batch-fetch user 1 query). LOW còn lại (chấp nhận): reorder checklist là scope-cut có chủ đích (hoãn); `labels.*` cần chạy `php artisan db:seed --class=RolePermissionSeeder` khi deploy lên env đã seed sẵn. **267/267 test pass sau fix.**

---

## 1. Dependency

```bash
composer require spatie/laravel-activitylog
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
```
→ tạo bảng `activity_log`. Dùng cho audit thay vì tự viết (đồng bộ hệ Spatie đang dùng).

## 2. Migrations (theo đúng style `create_tasks_table`)

**`xxxx_add_work_item_columns_to_tasks_table.php`**
```php
Schema::table('tasks', function (Blueprint $table) {
    $table->timestamp('start_at')->nullable()->after('description');
    // Sub-task nhẹ (tùy chọn — mặc định dùng checklist). Cùng branch với cha.
    $table->foreignId('parent_id')->nullable()->after('lead_id')
        ->constrained('tasks')->nullOnDelete();
    $table->index(['parent_id']);
});
```

**`xxxx_create_task_comments_table.php`**
```php
Schema::create('task_comments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
    $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
    $table->text('body');
    $table->timestamps();
    $table->softDeletes();               // giữ lịch sử, ẩn khi xoá
    $table->index(['task_id', 'created_at']);
});
```

**`xxxx_create_task_checklist_items_table.php`**
```php
Schema::create('task_checklist_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
    $table->string('title');
    $table->boolean('is_done')->default(false);
    $table->unsignedInteger('position')->default(0);
    $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('done_at')->nullable();
    $table->timestamps();
    $table->index(['task_id', 'position']);
});
```

**`xxxx_create_labels_table.php`** (branch-scoped config)
```php
Schema::create('labels', function (Blueprint $table) {
    $table->id();
    $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
    $table->string('name');
    $table->string('color', 20)->default('secondary'); // map badgeVariant
    $table->timestamps();
    $table->unique(['branch_id', 'name']);
});
```

**`xxxx_create_label_task_table.php`** (pivot)
```php
Schema::create('label_task', function (Blueprint $table) {
    $table->foreignId('label_id')->constrained('labels')->cascadeOnDelete();
    $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
    $table->primary(['label_id', 'task_id']);
});
```

## 3. Models

**`app/Models/TaskComment.php`** — `belongsTo(Task)`, `belongsTo(User,'user_id')` as `author`, `SoftDeletes`. Không cần BranchScope riêng (phạm vi kế thừa qua task; policy kiểm tra qua `$comment->task`).

**`app/Models/TaskChecklistItem.php`** — `belongsTo(Task)`, `belongsTo(User,'done_by')`. Cast `is_done` bool, `done_at` datetime.

**`app/Models/Label.php`** — `addGlobalScope(new BranchScope)`, `belongsToMany(Task, 'label_task')`. Có `badgeVariant()` = trả `color` (đồng bộ với hệ badge). Fillable: `branch_id`, `name`, `color`.

**`app/Models/Task.php`** — thêm:
```php
protected $fillable = [/* ... */ 'start_at', 'parent_id'];
protected $casts   = [/* ... */ 'start_at' => 'datetime'];

public function comments(): HasMany
    { return $this->hasMany(TaskComment::class)->latest(); }
public function checklistItems(): HasMany
    { return $this->hasMany(TaskChecklistItem::class)->orderBy('position'); }
public function labels(): BelongsToMany
    { return $this->belongsToMany(Label::class, 'label_task'); }
public function parent(): BelongsTo   { return $this->belongsTo(Task::class, 'parent_id'); }
public function subtasks(): HasMany   { return $this->hasMany(Task::class, 'parent_id'); }

// % checklist cho badge
public function checklistProgress(): Attribute {
    return Attribute::get(function () {
        $total = $this->checklistItems()->count();
        $done  = $this->checklistItems()->where('is_done', true)->count();
        return ['done' => $done, 'total' => $total];
    });
}
```
Thêm trait activitylog:
```php
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

public function getActivitylogOptions(): LogOptions {
    return LogOptions::defaults()
        ->logOnly(['status', 'assigned_user_id', 'priority', 'due_at', 'start_at', 'title'])
        ->logOnlyDirty()
        ->dontSubmitEmptyLogs();
}
```

## 4. Permissions (`app/Support/PermissionCatalog.php`)

Giữ tối giản — tái dụng quyền task hiện có, thêm đúng cái cần:
- Bình luận: gate bằng `tasks.view` (đọc) + `tasks.update`/assignee (viết) → **không thêm quyền mới**.
- Checklist: gate bằng `tasks.update` (như sửa task).
- Nhãn — thêm nhóm mới trong catalog:
  - `labels.view` → "Xem nhãn"
  - `labels.manage` → "Quản lý nhãn" (tạo/sửa/xoá — mặc định branch-manager)
  - Gán nhãn vào task: `tasks.update`.
- Thêm `labels.view` vào bộ `salesPermissions()`; `labels.manage` vào `branchAssignable()` (branch-manager).
- Cập nhật `RolePermissionSeeder` chạy lại (hoặc migration seed bổ sung).

## 5. Services (mỗi sub-domain 1 service nhỏ, theo pattern TaskService)

**`app/Services/TaskCommentService.php`**
- `create(Task $task, string $body): TaskComment` — DB::transaction; set `user_id = Auth::id()`; (P2 sẽ cắm parse @mention + notify tại đây).
- `delete(TaskComment $comment): void`.

**`app/Services/TaskChecklistService.php`**
- `add(Task $task, string $title): TaskChecklistItem` — `position = max+1`.
- `toggle(TaskChecklistItem $item): TaskChecklistItem` — lật `is_done`, set/clear `done_by`/`done_at`.
- `remove(TaskChecklistItem $item): void`; `reorder(Task $task, array $orderedIds): void`.

**`app/Services/LabelService.php`**
- CRUD nhãn (branch-scoped: `branch_id = Auth::user()->branch_id`, super-admin chọn branch).
- `sync(Task $task, array $labelIds): void` — chỉ nhận label cùng branch với task (guard như `guardLeadBranch`).

## 6. Controllers · Requests · Routes

**Controllers mới** (phẳng, như `TaskController`):
- `TaskCommentController` → `store`, `destroy`
- `TaskChecklistController` → `store`, `update` (toggle/title), `destroy`, `reorder`
- `LabelController` → resource (index/create/store/edit/update/destroy) + `TaskLabelController@sync` (hoặc method `syncLabels` trên `TaskController`)

**Requests** (`app/Http/Requests/Task/`, `app/Http/Requests/Label/`):
- `StoreTaskCommentRequest` (`body` required, max 5000)
- `StoreChecklistItemRequest` (`title` required, max 255)
- `StoreLabelRequest`/`UpdateLabelRequest` (`name` required unique per branch, `color` in danh sách badgeVariant)
- Mở rộng `StoreTaskRequest`/`UpdateTaskRequest`: thêm `start_at` (nullable date, ≤ due_at), `label_ids` (array, exists), `parent_id` (nullable, exists tasks).

**Routes (`routes/web.php`)** — nested shallow như `leads.activities`:
```php
Route::resource('tasks.comments', TaskCommentController::class)->shallow()->only(['store','destroy']);
Route::resource('tasks.checklist', TaskChecklistController::class)->shallow()->only(['store','update','destroy']);
Route::post('/tasks/{task}/checklist/reorder', [TaskChecklistController::class,'reorder'])->name('tasks.checklist.reorder');
Route::post('/tasks/{task}/labels', [TaskController::class,'syncLabels'])->name('tasks.labels.sync');
Route::resource('labels', LabelController::class)->except(['show']);
```

## 7. Policies

**`TaskCommentPolicy`** + **`TaskChecklistPolicy`**: uỷ quyền qua task cha —
```php
public function create(User $user, Task $task): bool
    { return app(TaskPolicy::class)->view($user, $task) && $user->can('tasks.view'); }
public function delete(User $user, TaskComment $c): bool
    { return $user->can('tasks.view-all') || $c->user_id === $user->id; } // + sameBranch qua $c->task
```
Toggle/thêm checklist: yêu cầu `TaskPolicy@update` (hoặc assignee) trên task cha.

**`LabelPolicy`**: `ChecksBranchOwnership` + `labels.view` / `labels.manage`.

## 8. Views

**`resources/views/tasks/show.blade.php`** — dựng lại thành layout 2 cột:
- **Cột chính:** tiêu đề + mô tả → **Checklist** (list tick + progress bar + ô thêm) → **Bình luận** (timeline `author · thời gian · body`, form thêm ở cuối, nút xoá theo policy).
- **Cột phải (sidebar):** trạng thái, ưu tiên, loại, người giao/nhận, ngày bắt đầu → hạn, **nhãn** (chip + nút gán mở `x-modal` chọn label), chi nhánh, Lead.
- **Timeline lịch sử:** đọc `Activity::where('subject...')` (spatie) render "Ai đổi <field> từ X → Y lúc T". Có thể trộn chung với bình luận theo thời gian, hoặc tab riêng "Lịch sử".
- Form dùng POST thường (không bắt buộc JS/SPA); toggle checklist có thể submit inline hoặc fetch nhẹ.

**`resources/views/tasks/_kanban_card.blade.php`** — thêm badge:
- Vạch màu trái theo `priority->badgeVariant()`.
- Hàng nhãn: `@foreach($task->labels as $l) <x-badge :variant="$l->badgeVariant()">{{ $l->name }}</x-badge>`.
- Chân thẻ: `{{ $progress['done'] }}/{{ $progress['total'] }}` (ẩn nếu total=0) + cờ `Quá hạn` nếu `$task->is_overdue`.
- Eager-load ở `TaskService::board()`/`list()`: thêm `'labels'` và `withCount(['checklistItems','checklistItems as done_count' => fn($q)=>$q->where('is_done',true)])` để tránh N+1.

**`resources/views/labels/`** — index (bảng nhãn + màu) + form create/edit (dùng `x-input`, `x-select` màu).

## 9. Tests (`tests/Feature/`)

- `TaskCommentTest`: sales thêm/xoá bình luận trên task của mình; **không** xoá bình luận người khác (trừ view-all); chặn cross-branch.
- `TaskChecklistTest`: thêm/tick/xoá item; progress đúng; policy update.
- `LabelTest`: branch-manager tạo nhãn; sales không tạo được; sync chỉ nhận label cùng branch; unique per branch.
- `TaskActivityLogTest`: đổi status/assignee sinh 1 dòng activity_log với old→new đúng.

## 10. Checklist verify thủ công

> Chưa click-through thủ công trên browser (code review chỉ chạy `php artisan test` +
> đọc code); các mục dưới suy ra từ test tự động tương ứng — vẫn nên tự tay verify 1 lượt
> trước khi merge/deploy prod.

- [x] Tạo task → mở chi tiết → thêm 3 checklist → tick 2 → thẻ Kanban hiện `2/3`. (`test_add_and_toggle_checklist_item`, eager-load `checklist_items_count`/`checklist_done_count` xác nhận qua code)
- [x] Gán 2 nhãn → hiện trên thẻ + sidebar. (`test_sync_only_accepts_same_branch_labels`, view code xác nhận)
- [x] Thêm bình luận → hiện trong timeline; xoá được (soft delete). (`test_assignee_can_add_comment`, `test_sales_cannot_delete_others_comment`, `test_manager_can_delete_others_comment_via_view_all`)
- [x] Đổi trạng thái/hạn → mục "Lịch sử" ghi đúng old→new + người + thời gian. (`test_status_change_is_logged`)
- [ ] Đăng nhập sales branch khác → không thấy/không sửa được (403/404). Cross-branch: đúng (`test_foreign_sales_cannot_add_checklist` → 404 qua BranchScope). **Same-branch nhưng ngoài phạm vi (không phải assignee, không phải lead của mình): SAI với bình luận** — xem finding #1 HIGH.
- [x] `php -l` + `./vendor/bin/pint` + `php artisan test --filter='Task|Label'` xanh.
