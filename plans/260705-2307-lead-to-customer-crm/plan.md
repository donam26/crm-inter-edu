# Lead → Khách hàng: chuyển CRM từ "theo trường" sang "theo khách hàng"

Branch: feat/test · Ngày: 2026-07-05

## Quyết định (đã chốt với user)
1. Đổi **sâu code + DB**: `Lead`→`Customer`, bảng `leads`→`customers`, FK `lead_id`→`customer_id`, route `/leads`→`/customers`, quyền `leads.*`→`customers.*`, `LeadStatus`→`CustomerStatus`.
2. Field trường học: `school_name`→`name`; **bỏ** `school_level` + `student_size` + enum `SchoolLevel`; **thêm** `phone`, `email`; giữ `address`, `status`.
3. Dữ liệu thật → **migration additive** đổi tên bảng/cột (giữ nguyên migration cũ, không migrate:fresh).

## Phases
- **P1 Rename file (git mv):** Model, Controller, Service, Requests/Lead→Customer, LeadPolicy, LeadStatus enum, LeadFactory, LeadSeeder, views/leads→customers, tests/LeadTest. Xoá `SchoolLevel.php`.
- **P2 Token replace (script, word-boundary):** `lead_id→customer_id`, `LeadController/Service/Status/Factory/Policy/Seeder`, `Store/UpdateLeadRequest`, `\bLead\b→Customer`, `\bleads\b→customers`, `\blead\b→customer`, `school_name→name`. Bỏ qua `database/migrations`.
- **P3 Migration:** rename table + cột trong 1 migration mới bảo toàn dữ liệu.
- **P4 Semantic:** bỏ `school_level`/`student_size`, thêm `phone`/`email`, xoá `SchoolLevel` refs (model, requests, controller, service, factory, views, tests).
- **P5 Nhãn UI + chuỗi Việt:** "Khách hàng tiềm năng"→"Khách hàng", "Trường/Tên trường"→"Khách hàng/Tên khách hàng", flash "lead"→"khách hàng", PermissionCatalog labels.
- **P6 Verify:** `php -l`, pint, `migrate`, `route:list`, grep sót, `php artisan test`.

## Trạng thái: HOÀN THÀNH ✅
- P1–P6 xong. 275/275 test PASS. `php -l` + pint OK. Migration đã áp lên DB local (223ms, bảo toàn dữ liệu).
- Schema mới: `customers(id, branch_id, assigned_user_id, name, phone, email, address, status, note, timestamps)`; FK `customer_id` ở contacts/deals/tasks/events/activities.
- Migration cũ (2026_05_27) giữ nguyên tên `leads`/`lead_id`/`school_name` — cố ý (chạy trước rồi rename migration transform; bảo toàn lịch sử).
- Deploy prod: `php artisan migrate` (backup DB trước) → dữ liệu lead cũ đổi tên sang customers, không mất.
