# Nâng cấp module Công việc lên chuẩn quản trị (đối chuẩn Jira)

> Bản audit trực quan kèm gap matrix + lộ trình:
> https://claude.ai/code/artifact/399706ba-bf40-4b69-b1db-becee0d3cd86

## Mục tiêu

Biến module `Công việc` từ **danh sách follow-up khoác Kanban** thành **công cụ quản trị công việc chuyên nghiệp của CRM** — mượn tính kỷ luật work-item của Jira (chi tiết, lịch sử, cộng tác, SLA), **bỏ** cỗ máy agile phần mềm (sprint, story point, velocity).

**Phạm vi đã chốt: P1–P2 ("Chuẩn hoá gọn").** Giữ định vị là task-tool của đội sales, không phình thành nền tảng PM đầy đủ.

## Nguyên tắc (bám pattern hiện có, KHÔNG theo rule generic)

Codebase thực tế khác vài điểm với `.claude/rules/development-rules.md` — **theo codebase**:

| Chủ đề | Pattern thực tế trong repo (tuân theo) |
|---|---|
| Khóa chính | `$table->id()` bigint + `foreignId()->constrained()` — **KHÔNG** dùng UUID/HasUuids |
| Controller | Phẳng trong `app/Http/Controllers/` (vd `TaskController`) — không namespace theo role |
| Request | `app/Http/Requests/Task/` theo domain — giữ nguyên |
| Đa tenant | Global scope `App\Models\Scopes\BranchScope`; super-admin bypass |
| RBAC | Spatie Permission, team = `branch_id`; permission khai báo ở `App\Support\PermissionCatalog` |
| Policy | Dùng concern `ChecksBranchOwnership` (before → super-admin bypass) + `$user->can('...')` |
| Enum | backed enum có `label()` + `badgeVariant()` + `values()` |
| Service | `DB::transaction()`, guard cross-branch, constructor DI |
| Ngôn ngữ | Comment code + UI tiếng Việt (bám giọng comment hiện tại) |

**Hạ tầng sẵn sàng:** queue = `database` (đã có `queue:listen` trong script dev) → notification queued chạy ngay. Mail default = `log` (dev; prod cần cấu hình SMTP). Có sẵn component: `x-modal`, `x-card`, `x-badge`, `x-button`, `x-icon`, `x-textarea`, `topbar` (chỗ đặt chuông), `sidebar`.

## Kiến trúc dữ liệu (toàn cảnh P1–P2)

**Bảng mới:**
- `task_comments` — luồng bình luận (P1)
- `task_checklist_items` — checklist có % (P1)
- `labels` + `label_task` — nhãn tự do, branch-scoped (P1)
- `activity_log` — audit (spatie/activitylog) (P1)
- `task_watchers` — người theo dõi (pivot) (P2)
- `saved_filters` — bộ lọc lưu (P2)
- `notifications` — bảng chuẩn Laravel cho in-app bell (P2)

**Thêm cột `tasks`:**
- `start_at` timestamp nullable (P1) — ngày bắt đầu, cặp với `due_at`
- `parent_id` FK self nullable (P1, tùy chọn) — sub-task nhẹ; mặc định dùng checklist trước

**KHÔNG làm (ngoài phạm vi, để P3–P4 nếu cần sau):** workflow tuỳ biến, WIP/swimlane cấu hình, đính kèm tệp, phụ thuộc việc, dự án/chiến dịch cha, mẫu việc/việc lặp, dashboard năng suất, sprint/story point.

## Phân rã giai đoạn

### [x] Giai đoạn 1 — Chiều sâu work-item ✅ (nhánh `feat/tasks-work-item-depth`)
Chi tiết: [phase-01-work-item-depth.md](phase-01-work-item-depth.md)
Bình luận · lịch sử/audit · checklist · nhãn · `start_at` · trang chi tiết task dựng lại · badge trên thẻ Kanban.

**Điều chỉnh KISS khi thực thi (so với plan gốc):**
- Bỏ `parent_id`/sub-task — checklist đã đủ để chia nhỏ việc ở P1 (thêm khi thật cần).
- Bỏ `done_by`/`done_at` trên checklist item — chỉ giữ `is_done` + `position`.
- Gán nhãn làm **inline ở trang chi tiết** (form checkbox, endpoint `tasks.labels.sync`) thay vì nhồi multi-select vào form tạo/sửa task.
- Audit dùng `spatie/laravel-activitylog` v5 (namespace `Models\Concerns\LogsActivity`, `Support\LogOptions`, method `dontLogEmptyChanges()`).

**Kiểm chứng:** 266/266 test pass (9 test work-item mới), pint sạch, toàn bộ blade compile OK.

### [ ] Giai đoạn 2 — Thông báo, SLA, bộ lọc lưu, bulk
Chi tiết: [phase-02-notifications-sla.md](phase-02-notifications-sla.md)
Kích hoạt `remind_at` (scheduler) · Laravel Notifications (in-app + email) · watcher + auto-watch · @nhắc-tên trong bình luận · digest quá hạn/sắp đến hạn · bộ lọc lưu "Việc của tôi" · thao tác hàng loạt · chuông thông báo ở topbar.

## Rủi ro & lưu ý

- **`User` cần trait `Notifiable`** — kiểm tra/thêm trước khi làm P2.
- **Mail dev = `log`** — email chỉ ghi log; test in-app (database channel) trước, email verify khi có SMTP.
- **BranchScope cho bảng mới**: `labels`, `saved_filters` phải branch-scoped; `task_comments`/`checklist` kế thừa phạm vi qua task cha (policy kiểm tra qua task).
- **@mention**: parse token `@tên` server-side đối chiếu user cùng branch — giữ đơn giản, không cần rich editor.
- **activitylog**: thêm dependency `spatie/laravel-activitylog` (đồng bộ hệ sinh thái Spatie đang dùng) thay vì tự viết bảng audit — ít code hơn, đã kiểm chứng.

## Quy ước kiểm thử (mỗi phase)

1. `php -l` mọi file PHP đổi.
2. `./vendor/bin/pint` các file đổi.
3. `php artisan test` cho vùng liên quan (viết feature test: policy phạm vi branch + happy-path service).
4. Verify thủ công theo checklist cuối mỗi phase file.

## Trạng thái

| Giai đoạn | Trạng thái | Ghi chú |
|---|---|---|
| P1 — Work-item depth | ✅ Hoàn thành | 267 test pass · nhánh `feat/tasks-work-item-depth` · code review 2026-07-04: HIGH (RBAC) + MEDIUM (N+1) đã sửa — xem `plans/reports/code-reviewer-260704-1635-tasks-work-item-depth.md` |
| P2 — Notifications & SLA | ⬜ Chưa bắt đầu | phụ thuộc P1 (bình luận → @mention/notify) |

_Cập nhật checkbox + bảng này khi hoàn thành từng phase._
