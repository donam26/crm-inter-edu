# Requirements Document

## Introduction

CRM Inter-Edu là hệ thống quản lý quan hệ khách hàng dành riêng cho lĩnh vực khảo thí (educational assessment platform). Khách hàng (Lead) trong hệ thống là các trường học/cơ sở giáo dục đối tác. Tổ chức vận hành theo mô hình nhiều chi nhánh (Branch), mỗi chi nhánh có một quản lý (Branch Manager) và nhiều nhân viên kinh doanh (Sales).

Phạm vi MVP v1 bao gồm: Authentication, Users & Roles & Permissions, Branches, Leads, Contacts, Activities/Notes, Dashboard cơ bản, cùng với nền tảng kỹ thuật (coding rules + sample module Branches) và data isolation theo branch.

Tài liệu này được viết bằng tiếng Việt cho phần mô tả; các định danh kỹ thuật (tên bảng, tên class, tên trường, tên role, tên permission) được giữ nguyên tiếng Anh để đồng bộ với mã nguồn Laravel.

## Glossary

- **System**: Toàn bộ ứng dụng CRM Inter-Edu (Laravel 12 + Blade + Alpine.js + Tailwind 4).
- **Auth_Module**: Thành phần xử lý đăng nhập, đăng xuất, quên mật khẩu.
- **User_Module**: Thành phần quản lý tài khoản người dùng cùng với gán role.
- **Permission_Module**: Thành phần quản lý roles và permissions, dựa trên Spatie Laravel Permission.
- **Branch_Module**: Thành phần quản lý chi nhánh, đồng thời là sample module chuẩn cho mọi module nghiệp vụ về sau.
- **Lead_Module**: Thành phần quản lý lead, mỗi lead tương ứng với một trường học/cơ sở giáo dục.
- **Contact_Module**: Thành phần quản lý người liên hệ thuộc về một Lead.
- **Activity_Module**: Thành phần quản lý lịch sử tương tác (call/email/meeting/note) gắn với Lead.
- **Dashboard_Module**: Thành phần hiển thị thống kê tổng quan.
- **UI_Library**: Tập các Blade component dùng chung (button, input, modal, table, badge, alert) cùng layout sidebar/topbar/main.
- **BranchScope**: Eloquent global scope tự động lọc query theo `branch_id` của user hiện tại.
- **Branch_Policy_Pattern**: Quy ước Policy kiểm tra `$user->branch_id === $model->branch_id`, super-admin được bypass.
- **Super_Admin**: Role có quyền cao nhất, xem và thao tác trên dữ liệu của mọi branch.
- **Branch_Manager**: Role quản lý một branch, chỉ thao tác trên dữ liệu thuộc branch đó.
- **Sales**: Role nhân viên kinh doanh, chỉ thao tác trên các Lead được assign cho mình trong branch của mình.
- **Service_Layer**: Lớp class chứa business logic, được Controller gọi sau khi FormRequest đã validate.
- **FormRequest**: Class Laravel xử lý validate dữ liệu đầu vào cho từng action của Controller.
- **Coding_Rules_Document**: File `CODING_RULES.md` ở thư mục gốc dự án, mô tả quy ước code chuẩn của project.
- **Sample_Module**: Branch_Module được dùng làm mẫu tham chiếu cho mọi module nghiệp vụ sau.
- **Lead_Status**: Trạng thái pipeline của một Lead (ví dụ: new, contacted, qualified, proposal, negotiation, won, lost).

## Requirements

### Requirement 1 — Project Foundation & Coding Standards

**User Story:** Là một developer trong nhóm, tôi muốn có sẵn tài liệu coding rules và một sample module hoàn chỉnh, để các module sau được code đồng bộ về kiến trúc và phong cách.

#### Acceptance Criteria

1. THE System SHALL cung cấp file `CODING_RULES.md` tại thư mục gốc của repository.
2. THE Coding_Rules_Document SHALL mô tả kiến trúc Service Layer + FormRequest + Policy với Controller mỏng, không sử dụng Repository pattern và không sử dụng Action class.
3. THE Coding_Rules_Document SHALL liệt kê quy ước đặt tên cho Controller, FormRequest, Service, Policy, Model, Migration, Factory, Seeder, Blade view và route.
4. THE Coding_Rules_Document SHALL mô tả quy ước multi-tenant theo branch, gồm trường `branch_id` trên mọi business table, BranchScope global scope, và Branch_Policy_Pattern.
5. THE Coding_Rules_Document SHALL mô tả quy ước UI gồm Blade + Alpine.js + Tailwind 4, danh sách Blade component dùng chung, và layout sidebar + topbar + main.
6. THE Coding_Rules_Document SHALL mô tả quy ước test với PHPUnit 11.5, gồm cấu trúc thư mục test và quy ước đặt tên test class.
7. THE System SHALL hiện thực Branch_Module như Sample_Module, áp dụng đầy đủ các quy ước trong Coding_Rules_Document.
8. WHERE một module nghiệp vụ mới được thêm vào sau v1, THE System SHALL tuân theo cấu trúc của Sample_Module.

### Requirement 2 — Database & MySQL Setup

**User Story:** Là một developer, tôi muốn dự án dùng MySQL thay cho SQLite mặc định, để môi trường phát triển khớp với môi trường production.

#### Acceptance Criteria

1. THE System SHALL sử dụng MySQL làm database driver mặc định.
2. THE System SHALL cập nhật `.env.example` với các biến `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
3. THE System SHALL cập nhật `.env` cục bộ để trỏ tới MySQL với database name `crm_inter_edu`.
4. THE System SHALL gỡ bỏ file `database/database.sqlite` khỏi repository.
5. WHEN chạy lệnh `php artisan migrate:fresh --seed`, THE System SHALL tạo schema và seed dữ liệu mẫu thành công trên MySQL.
6. THE System SHALL cài đặt package `spatie/laravel-permission` qua Composer và publish migration của package.
7. THE System SHALL tích hợp Alpine.js qua npm và import trong `resources/js/app.js`.

### Requirement 3 — Authentication

**User Story:** Là một người dùng hệ thống, tôi muốn đăng nhập bằng email và mật khẩu, đăng xuất, và khôi phục mật khẩu khi quên, để truy cập hệ thống an toàn.

#### Acceptance Criteria

1. WHEN người dùng truy cập route `/login` mà chưa đăng nhập, THE Auth_Module SHALL hiển thị form đăng nhập với hai trường email và password.
2. WHEN người dùng submit form đăng nhập với email và password đúng, THE Auth_Module SHALL tạo session đăng nhập và chuyển hướng tới `/dashboard`.
3. IF người dùng submit form đăng nhập với email hoặc password sai, THEN THE Auth_Module SHALL hiển thị thông báo lỗi và giữ người dùng ở trang đăng nhập.
4. WHEN người dùng đã đăng nhập gọi action logout, THE Auth_Module SHALL hủy session và chuyển hướng tới `/login`.
5. WHEN người dùng truy cập route `/forgot-password`, THE Auth_Module SHALL hiển thị form yêu cầu nhập email.
6. WHEN người dùng submit form quên mật khẩu với email tồn tại trong hệ thống, THE Auth_Module SHALL gửi email chứa link đặt lại mật khẩu tới địa chỉ đó.
7. IF người dùng submit form quên mật khẩu với email không tồn tại, THEN THE Auth_Module SHALL hiển thị thông báo trung lập (không tiết lộ email có tồn tại hay không) và không gửi email.
8. WHEN người dùng truy cập link đặt lại mật khẩu hợp lệ và submit mật khẩu mới, THE Auth_Module SHALL cập nhật mật khẩu và chuyển hướng tới `/login`.
9. IF người dùng chưa đăng nhập truy cập route được bảo vệ, THEN THE Auth_Module SHALL chuyển hướng tới `/login`.

### Requirement 4 — Users, Roles & Permissions

**User Story:** Là Super_Admin, tôi muốn quản lý tài khoản người dùng cùng với role của họ, để phân quyền truy cập theo đúng vai trò trong tổ chức.

#### Acceptance Criteria

1. THE Permission_Module SHALL định nghĩa ba role: `super-admin`, `branch-manager`, `sales`.
2. THE Permission_Module SHALL sử dụng package `spatie/laravel-permission` để lưu trữ và truy vấn role và permission.
3. THE User_Module SHALL bổ sung trường `branch_id` (nullable) vào bảng `users`, tham chiếu tới bảng `branches`.
4. WHERE user có role `super-admin`, THE User_Module SHALL cho phép `branch_id` để trống.
5. WHERE user có role `branch-manager` hoặc `sales`, THE User_Module SHALL yêu cầu `branch_id` không được để trống.
6. WHEN Super_Admin truy cập trang quản lý users, THE User_Module SHALL hiển thị danh sách tất cả users với cột email, name, role, branch.
7. WHEN Super_Admin tạo user mới, THE User_Module SHALL yêu cầu các trường: name, email, password, role, branch_id (theo điều kiện ở mục 4 và 5).
8. WHEN Super_Admin cập nhật role của một user, THE User_Module SHALL áp dụng role mới ngay sau khi lưu thành công.
9. IF người dùng không có role `super-admin` cố gắng truy cập trang quản lý users, THEN THE System SHALL trả về HTTP 403.
10. THE Permission_Module SHALL cung cấp seeder tạo sẵn ba role và một tài khoản Super_Admin mặc định cho môi trường phát triển.

### Requirement 5 — Branches Module (Sample Module)

**User Story:** Là Super_Admin, tôi muốn quản lý danh sách chi nhánh, để gán user và dữ liệu nghiệp vụ vào đúng chi nhánh.

#### Acceptance Criteria

1. THE Branch_Module SHALL cung cấp bảng `branches` với các trường: `id`, `name`, `code`, `address`, `phone`, `manager_user_id` (nullable), `is_active`, `created_at`, `updated_at`.
2. THE Branch_Module SHALL hiện thực đầy đủ CRUD: index, create, store, show, edit, update, destroy.
3. THE Branch_Module SHALL áp dụng kiến trúc Controller mỏng → FormRequest → Service → Eloquent Model theo Coding_Rules_Document.
4. THE Branch_Module SHALL có `BranchPolicy` kiểm soát quyền truy cập, chỉ Super_Admin được tạo, sửa, xóa branch.
5. WHERE người dùng có role `branch-manager` hoặc `sales`, THE Branch_Module SHALL chỉ cho phép xem branch mà người dùng đó thuộc về.
6. WHEN Super_Admin tạo branch mới với `code` trùng với branch đã tồn tại, THE Branch_Module SHALL trả về lỗi validate và không tạo bản ghi.
7. WHEN Super_Admin xóa một branch còn liên kết với user hoặc lead, THE Branch_Module SHALL từ chối xóa và hiển thị thông báo lỗi.
8. THE Branch_Module SHALL có factory và seeder để phục vụ test và môi trường phát triển.
9. THE Branch_Module SHALL có ít nhất một feature test bao phủ luồng index, store, update và authorization.

### Requirement 6 — Leads Module

**User Story:** Là Sales hoặc Branch_Manager, tôi muốn quản lý danh sách trường học là khách hàng tiềm năng, để theo dõi pipeline bán hàng.

#### Acceptance Criteria

1. THE Lead_Module SHALL cung cấp bảng `leads` với các trường: `id`, `branch_id`, `assigned_user_id` (nullable), `school_name`, `school_level` (mầm non/tiểu học/THCS/THPT/liên cấp/khác), `student_size` (số nguyên), `address`, `status` (Lead_Status), `note`, `created_at`, `updated_at`.
2. THE Lead_Module SHALL áp dụng BranchScope để tự động lọc query theo `branch_id` của user hiện tại.
3. THE Lead_Module SHALL hiện thực đầy đủ CRUD theo kiến trúc Controller mỏng → FormRequest → Service → Eloquent Model.
4. THE Lead_Module SHALL có `LeadPolicy` áp dụng Branch_Policy_Pattern.
5. WHERE user có role `sales`, THE Lead_Module SHALL chỉ cho phép xem và sửa các lead có `assigned_user_id` bằng id của user đó.
6. WHERE user có role `branch-manager`, THE Lead_Module SHALL cho phép xem và sửa mọi lead trong branch của user đó.
7. WHERE user có role `super-admin`, THE Lead_Module SHALL cho phép xem và sửa mọi lead ở mọi branch.
8. WHEN Branch_Manager assign một lead cho một sales, THE Lead_Module SHALL cập nhật `assigned_user_id` và chỉ chấp nhận sales thuộc cùng branch với lead.
9. IF Branch_Manager cố gắng assign lead cho một sales không cùng branch, THEN THE Lead_Module SHALL từ chối với lỗi validate.
10. THE Lead_Module SHALL hỗ trợ filter danh sách theo `status`, `school_level`, `assigned_user_id`, `branch_id` (chỉ Super_Admin filter được theo branch).
11. THE Lead_Module SHALL có factory, seeder, và feature test bao phủ CRUD cùng với phân quyền theo từng role.

### Requirement 7 — Contacts Module

**User Story:** Là Sales, tôi muốn lưu thông tin người liên hệ tại từng trường, để theo dõi đầu mối làm việc cụ thể.

#### Acceptance Criteria

1. THE Contact_Module SHALL cung cấp bảng `contacts` với các trường: `id`, `lead_id`, `branch_id`, `full_name`, `position`, `email`, `phone`, `is_primary`, `note`, `created_at`, `updated_at`.
2. THE Contact_Module SHALL có quan hệ nhiều-một với Lead (một Lead có nhiều Contact, một Contact thuộc đúng một Lead).
3. THE Contact_Module SHALL áp dụng BranchScope, đồng bộ `branch_id` với `branch_id` của Lead cha khi tạo Contact.
4. THE Contact_Module SHALL hiện thực đầy đủ CRUD theo kiến trúc Controller mỏng → FormRequest → Service → Eloquent Model.
5. THE Contact_Module SHALL có `ContactPolicy` áp dụng Branch_Policy_Pattern và kế thừa quyền từ Lead cha.
6. WHEN một Lead có nhiều contact và một contact được đánh dấu `is_primary=true`, THE Contact_Module SHALL bảo đảm chỉ có duy nhất một contact `is_primary=true` trên cùng một Lead.
7. WHEN Lead bị xóa, THE Contact_Module SHALL xóa toàn bộ contact thuộc Lead đó (cascade).
8. THE Contact_Module SHALL validate ít nhất một trong hai trường `email` hoặc `phone` được điền.
9. THE Contact_Module SHALL có factory, seeder, và feature test bao phủ CRUD cùng với phân quyền.

### Requirement 8 — Activities Module

**User Story:** Là Sales, tôi muốn ghi lại lịch sử tương tác với từng trường (gọi điện, email, họp, ghi chú), để theo dõi diễn biến quan hệ khách hàng.

#### Acceptance Criteria

1. THE Activity_Module SHALL cung cấp bảng `activities` với các trường: `id`, `lead_id`, `branch_id`, `user_id`, `type` (call/email/meeting/note), `subject`, `content`, `happened_at`, `created_at`, `updated_at`.
2. THE Activity_Module SHALL có quan hệ nhiều-một với Lead và nhiều-một với User (người tạo activity).
3. THE Activity_Module SHALL áp dụng BranchScope, đồng bộ `branch_id` với Lead cha.
4. THE Activity_Module SHALL hiện thực CRUD theo kiến trúc Controller mỏng → FormRequest → Service → Eloquent Model.
5. THE Activity_Module SHALL có `ActivityPolicy` áp dụng Branch_Policy_Pattern và kế thừa quyền từ Lead cha.
6. WHEN người dùng tạo activity, THE Activity_Module SHALL tự động set `user_id` bằng id của user đang đăng nhập và `branch_id` bằng `branch_id` của Lead.
7. WHEN xem chi tiết một Lead, THE Lead_Module SHALL hiển thị danh sách activities sắp xếp theo `happened_at` giảm dần.
8. THE Activity_Module SHALL validate `type` chỉ thuộc tập {call, email, meeting, note}.
9. THE Activity_Module SHALL có factory, seeder, và feature test bao phủ CRUD cùng với phân quyền.

### Requirement 9 — Dashboard

**User Story:** Là người dùng đã đăng nhập, tôi muốn xem dashboard với các chỉ số tổng quan, để nắm nhanh tình hình pipeline.

#### Acceptance Criteria

1. WHEN người dùng đã đăng nhập truy cập `/dashboard`, THE Dashboard_Module SHALL hiển thị thống kê số lượng Lead theo từng giá trị `status`.
2. WHERE user có role `super-admin`, THE Dashboard_Module SHALL hiển thị thêm thống kê số Lead theo từng branch.
3. WHERE user có role `branch-manager`, THE Dashboard_Module SHALL hiển thị thống kê chỉ trong phạm vi branch của user.
4. WHERE user có role `sales`, THE Dashboard_Module SHALL hiển thị thống kê chỉ với các Lead được assign cho user đó.
5. THE Dashboard_Module SHALL hiển thị tổng số Lead, số Activity trong 7 ngày gần nhất, và số Contact, theo cùng phạm vi phân quyền ở các mục 2 đến 4.
6. WHEN dữ liệu Lead thay đổi, THE Dashboard_Module SHALL phản ánh số liệu mới ở lần load trang kế tiếp.

### Requirement 10 — Multi-tenant Data Isolation

**User Story:** Là chủ hệ thống, tôi muốn dữ liệu giữa các branch được tách biệt tự động, để tránh rò rỉ dữ liệu giữa các chi nhánh.

#### Acceptance Criteria

1. THE System SHALL bổ sung trường `branch_id` (foreign key tới `branches.id`) vào mọi business table: `users`, `leads`, `contacts`, `activities`.
2. THE System SHALL cung cấp class `BranchScope` hiện thực `Illuminate\Database\Eloquent\Scope`, lọc query theo `branch_id` của user đăng nhập.
3. WHERE user đăng nhập có role `super-admin`, THE BranchScope SHALL không thêm điều kiện lọc.
4. WHERE user đăng nhập có role khác `super-admin`, THE BranchScope SHALL thêm điều kiện `where branch_id = ?` với tham số là `branch_id` của user.
5. THE System SHALL áp dụng BranchScope mặc định cho các Eloquent model: `Lead`, `Contact`, `Activity`.
6. THE System SHALL áp dụng Branch_Policy_Pattern trong mọi Policy của business model: kiểm tra `$user->branch_id === $model->branch_id`, super-admin được bypass.
7. WHEN một bản ghi business được tạo qua Service_Layer, THE Service_Layer SHALL set `branch_id` từ user đăng nhập (hoặc từ entity cha như Lead) thay vì nhận từ input người dùng.
8. IF một request cố gắng truy cập bản ghi thuộc branch khác qua route param, THEN THE System SHALL trả về HTTP 404 (do BranchScope) hoặc HTTP 403 (do Policy), không tiết lộ sự tồn tại của bản ghi.

### Requirement 11 — UI Component Library

**User Story:** Là một developer, tôi muốn có sẵn bộ Blade component dùng chung và layout chuẩn, để mọi màn hình trong hệ thống có giao diện đồng bộ.

#### Acceptance Criteria

1. THE UI_Library SHALL cung cấp các Blade component: `x-button`, `x-input`, `x-modal`, `x-table`, `x-badge`, `x-alert`.
2. THE UI_Library SHALL sử dụng Tailwind CSS 4.0 qua `@tailwindcss/vite` mà không cần file `tailwind.config.js`.
3. THE UI_Library SHALL sử dụng Alpine.js cho interaction phía client, không sử dụng Livewire, Inertia, Filament, hay framework SPA khác.
4. THE UI_Library SHALL cung cấp layout `app` gồm ba vùng: sidebar trái, topbar trên, vùng main ở giữa.
5. WHILE chiều rộng viewport nhỏ hơn 768px, THE UI_Library SHALL ẩn sidebar mặc định và cho phép mở qua nút toggle trên topbar.
6. THE UI_Library SHALL hiển thị thông tin user đang đăng nhập (tên, role, branch nếu có) trên topbar cùng với menu logout.
7. THE UI_Library SHALL hiển thị navigation trong sidebar chỉ với các mục mà user hiện tại có quyền truy cập theo role.
8. WHEN một component `x-alert` được render với type `success`, `error`, `warning`, hoặc `info`, THE UI_Library SHALL áp dụng màu sắc Tailwind tương ứng cho từng type.
9. THE UI_Library SHALL được tham chiếu từ Sample_Module (Branch_Module) như ví dụ chuẩn về cách dùng các component và layout.
