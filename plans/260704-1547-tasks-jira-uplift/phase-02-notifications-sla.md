# Giai đoạn 2 — Thông báo, SLA, bộ lọc lưu, thao tác hàng loạt

**Mục tiêu:** đưa việc đến đúng người đúng lúc (kích hoạt trường `remind_at` đang "chết"), cho quản lý theo dõi việc không phải của mình, và tăng tốc thao tác lặp.

**Phụ thuộc:** Giai đoạn 1 (bình luận đã có → cắm @mention + notify vào `TaskCommentService::create`).

**Tiêu chí nghiệm thu**
- [ ] Được giao việc / bị @nhắc-tên / việc sắp đến hạn / quá hạn → nhận thông báo (chuông in-app; email khi có SMTP).
- [ ] Chuông ở topbar hiện số chưa đọc + danh sách; bấm → tới task, đánh dấu đã đọc.
- [ ] Lệnh `php artisan tasks:dispatch-reminders` chạy theo lịch, gửi digest "quá hạn / sắp đến hạn" cho assignee + watcher.
- [ ] Có chip nhanh "Việc của tôi / Quá hạn / Đến hạn hôm nay" + lưu bộ lọc tuỳ chỉnh.
- [ ] Chọn nhiều task ở view Danh sách → đổi trạng thái / giao lại / gán nhãn hàng loạt (mỗi item vẫn qua policy).
- [ ] `php -l` + `pint` sạch; feature test xanh.

---

## 0. Tiền đề

- **`User` phải có trait `Notifiable`** — kiểm tra `app/Models/User.php`, thêm `use Illuminate\Notifications\Notifiable;` + `use Notifiable;` nếu thiếu.
- Bảng notifications:
```bash
php artisan make:notifications-table   # Laravel 11+/12
php artisan migrate
```
- Queue đã = `database` và script dev đã chạy `queue:listen` → notification `ShouldQueue` chạy ngay. Prod: đảm bảo worker (`php artisan queue:work`) trong Docker/supervisor.

## 1. Watchers (người theo dõi)

**Migration `xxxx_create_task_watchers_table.php`**
```php
Schema::create('task_watchers', function (Blueprint $table) {
    $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    $table->primary(['task_id', 'user_id']);
});
```
**`Task`**: `watchers(): BelongsToMany` (users). Helper `addWatcher(User)`, `removeWatcher(User)`.

**Auto-watch** (trong service): người trở thành watcher tự động khi — được giao (assignee), là người tạo (reporter), hoặc bình luận vào task. UI: nút "Theo dõi / Bỏ theo dõi" ở sidebar task (endpoint `tasks.watch`/`tasks.unwatch`).

## 2. Notifications

`php artisan make:notification ...` cho từng loại; mỗi class `implements ShouldQueue`, `via()` → `['database','mail']`, có `toArray()` (in-app) + `toMail()`.

| Notification | Kích hoạt | Người nhận |
|---|---|---|
| `TaskAssignedNotification` | tạo task / đổi assignee | assignee mới |
| `TaskMentionedNotification` | @nhắc-tên trong bình luận | user được nhắc |
| `TaskCommentedNotification` | có bình luận mới | watcher (trừ người viết) |
| `TaskStatusChangedNotification` | đổi trạng thái | watcher (trừ người đổi) |
| `TaskDueSoonNotification` | sắp đến hạn (digest) | assignee + watcher |
| `TaskOverdueNotification` | quá hạn (digest) | assignee + watcher + (tuỳ chọn) branch-manager |

`toArray()` tối thiểu: `task_id`, `title`, `type` (enum vd `assigned`/`mention`/...), `actor_name`, `url`. Dùng để render chuông.

**Điểm cắm (tái dụng service P1, KISS — không rải rác logic):**
- `TaskService::create/update` (khi assignee đổi) → gửi `TaskAssigned` + `TaskStatusChanged`.
- `TaskCommentService::create` → parse @mention → `TaskMentioned` cho user được nhắc + `TaskCommented` cho watcher. Auto-add commenter vào watcher.

## 3. @mention trong bình luận

- Textarea bình luận + JS autocomplete: gõ `@` → gợi ý user cùng branch (`branchUsers()` đã có ở `TaskController`), chèn `@Tên` và đẩy `user_id` vào hidden `mention_ids[]`.
- Server (`StoreTaskCommentRequest`): `mention_ids` array, `exists:users,id`; service lọc chỉ giữ user cùng branch với task (guard), gửi `TaskMentioned`.
- MVP tối giản nếu chưa làm JS: thay bằng multiselect "Nhắc ai" (`x-select` multiple) — cùng backend.

## 4. SLA / nhắc hạn — scheduled command

**`app/Console/Commands/DispatchTaskReminders.php`** (`tasks:dispatch-reminders`)
Logic (chạy idempotent, ví dụ mỗi 15 phút hoặc mỗi giờ):
1. **Nhắc theo `remind_at`**: task `reminder_enabled=true`, `remind_at <= now`, còn `open()`, chưa nhắc → gửi nhắc cho assignee; đánh dấu đã nhắc (thêm cột `reminded_at` nullable, hoặc so `remind_at` với lần chạy trước).
2. **Sắp đến hạn**: `open()` + `due_at` trong [now, now+24h] → `TaskDueSoon` (gộp digest theo user để tránh spam).
3. **Quá hạn**: `open()` + `due_at < now` → `TaskOverdue` (digest 1 lần/ngày, vd chạy nhánh này khi giờ = 8:00).

**Đăng ký lịch** — Laravel 12 dùng `routes/console.php`:
```php
use Illuminate\Support\Facades\Schedule;
Schedule::command('tasks:dispatch-reminders')->everyFifteenMinutes()->withoutOverlapping();
```
Migration nhỏ: thêm `tasks.reminded_at` (nullable timestamp) để chống gửi trùng.

> Docker prod: đảm bảo `php artisan schedule:work` (hoặc cron gọi `schedule:run` mỗi phút) trong container — ghi vào `docker/` + deploy guide.

## 5. Bộ lọc lưu + chip nhanh

**Chip nhanh (không cần bảng)** — hàng chip trên `_filters.blade.php`: "Việc của tôi" (`assigned_user_id=me`), "Quá hạn" (`due=overdue`), "Đến hạn hôm nay" (`due=today`), "Tôi theo dõi" (watcher=me). Chỉ là link gắn query-string dựng sẵn.

**Bộ lọc tuỳ chỉnh (có bảng):**
```php
// xxxx_create_saved_filters_table.php
Schema::create('saved_filters', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    $table->string('name');
    $table->json('criteria');            // {status,priority,type,due,assigned_user_id,q,...}
    $table->timestamps();
});
```
- `SavedFilterController` (store/destroy) + nút "Lưu bộ lọc này" cạnh form lọc; danh sách bộ lọc đã lưu của user hiện dạng chip. Scope theo `user_id` (không cần BranchScope; user chỉ thấy filter của mình).

## 6. Thao tác hàng loạt (view Danh sách)

- `_list.blade.php`: thêm checkbox mỗi dòng + "chọn tất cả" + thanh công cụ nổi khi có chọn: **Đổi trạng thái · Giao lại · Gán nhãn · Đặt ưu tiên**.
- Endpoint `POST /tasks/bulk` → `TaskController@bulk`:
```php
$data = $request->validate([
    'action' => ['required', Rule::in(['status','assign','label','priority'])],
    'ids'    => ['required','array'], 'ids.*' => ['integer'],
    'value'  => ['required'],
]);
$this->service->bulk($data['action'], $data['ids'], $data['value']); // per-item authorize + guard branch
```
- `TaskService::bulk()`: lặp trong `DB::transaction`, mỗi task `Gate::authorize('update'|'complete', $task)`; bỏ qua (đếm) task ngoài quyền; tái dụng `setStatus()`/`update()`; sinh notification như luồng thường.

## 7. Chuông thông báo (topbar)

- `topbar.blade.php`: icon chuông + badge số `auth()->user()->unreadNotifications->count()`.
- Dropdown: 10 thông báo mới nhất (`unreadNotifications` + gần đây), mỗi dòng `actor · nội dung · thời gian`, link tới `data.url`.
- Endpoints: `notifications.index` (trang tất cả, phân trang), `notifications.read` (POST đánh dấu đã đọc 1 hoặc tất cả). Route nhóm `auth`.
- Component `x-notification-bell` để tái dùng; poll nhẹ (JS `setInterval` fetch số chưa đọc) hoặc chỉ cập nhật khi tải trang (MVP).

## 8. Tests (`tests/Feature/`)

- `TaskNotificationTest`: `Notification::fake()`; giao task → assignee nhận `TaskAssigned`; @mention → user nhận `TaskMentioned`; đổi status → watcher nhận, người đổi không nhận.
- `DispatchTaskRemindersTest`: seed task quá hạn + sắp đến hạn → chạy command → đúng notification; chạy lần 2 không gửi trùng (`reminded_at`).
- `SavedFilterTest`: lưu/xoá filter; user chỉ thấy filter của mình.
- `BulkActionTest`: bulk đổi status trên nhiều task; task ngoài branch/quyền bị bỏ qua, không đổi.
- `WatcherTest`: auto-watch khi bình luận/được giao; watch/unwatch thủ công.

## 9. Checklist verify thủ công

- [ ] Giao task cho user B → B thấy chuông +1, bấm vào ra đúng task.
- [ ] Bình luận `@A` → A nhận thông báo "được nhắc".
- [ ] Đặt `remind_at` quá khứ + `reminder_enabled` → chạy `php artisan tasks:dispatch-reminders` → assignee nhận nhắc; chạy lại không trùng.
- [ ] Chip "Việc của tôi" lọc đúng; lưu 1 bộ lọc tuỳ chỉnh → hiện lại sau reload.
- [ ] Chọn 3 task → "Hoàn thành" hàng loạt → cả 3 chuyển đúng, task ngoài quyền không đổi.
- [ ] (Có SMTP) email gửi đúng mẫu; (dev) kiểm `storage/logs` thấy mail log.
- [ ] `php -l` + `./vendor/bin/pint` + `php artisan test --filter='Notification|Reminder|SavedFilter|Bulk|Watcher'` xanh.

---

## Kết thúc P2 — Định nghĩa "Done"

Module Công việc lúc này đạt **chuẩn work-item chuyên nghiệp cho đội sales**: mỗi việc có bối cảnh (mô tả/checklist/nhãn), có trách nhiệm giải trình (lịch sử + bình luận), chủ động nhắc (SLA/thông báo), và vận hành nhanh (bộ lọc lưu + bulk) — mà **không** kéo theo gánh nặng agile của Jira. Các hạng mục P3–P4 (workflow tuỳ biến, chiến dịch cha, báo cáo năng suất) để mở cho giai đoạn sau nếu nhu cầu phát sinh.
