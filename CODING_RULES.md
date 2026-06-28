# CODING_RULES.md — CRM Inter-Edu

Tài liệu này là single source of truth về quy ước code cho dự án CRM Inter-Edu (Laravel 12, Blade + Alpine.js + Tailwind 4, MySQL 8). Mọi developer trong nhóm bắt buộc tuân theo các quy ước dưới đây.

Mô tả viết bằng tiếng Việt; các định danh kỹ thuật (tên class, tên trường, tên role, tên permission, tên route...) giữ nguyên tiếng Anh để đồng bộ với mã nguồn.

> **Sample module chuẩn: `Branch_Module`** — toàn bộ module nghiệp vụ về sau (Users, Leads, Contacts, Activities, Dashboard...) **bắt buộc** copy cấu trúc của `Branch_Module`. Khi có thay đổi quy ước, cập nhật trên `Branch_Module` trước rồi áp dụng ngược lại các module khác.

---

## 1. Architecture

### 1.1 Layered Architecture bắt buộc

```
Routes → Middleware → Controller (mỏng) → FormRequest → Service → Eloquent Model
                                       ↘ Policy (qua $this->authorize)
                                       ↘ Blade View (response)
```

Trách nhiệm từng lớp:

| Lớp | Trách nhiệm | Cấm |
|---|---|---|
| Routes | Khai báo URL → `Controller@action`, áp middleware (`auth`, `verified`, `role:...`) | Logic |
| Controller | Nhận `FormRequest`, gọi `$this->authorize`, gọi Service, trả `view()`/`redirect()` | Validate, query DB trực tiếp, business logic |
| FormRequest | `rules()`, `authorize()`, `prepareForValidation()`, `messages()` | Gọi DB write, gọi Service |
| Service | Business logic, transactions, gọi nhiều Model, set `branch_id` từ auth context | Đọc `request()`, render view |
| Policy | `viewAny`, `view`, `create`, `update`, `delete` theo `Branch_Policy_Pattern` | Đọc input, gọi Service |
| Model | Quan hệ, casts, scope, accessor/mutator | Business logic phức tạp |
| Resource | Format output JSON (chỉ tạo khi có API) | Logic |

### 1.2 Quy ước cứng

- **KHÔNG dùng Repository pattern.** Service là điểm tập trung duy nhất của business logic.
- **KHÔNG dùng Action class.** Mỗi nghiệp vụ là một method trên Service tương ứng.
- **KHÔNG dùng Livewire / Inertia / Filament / SPA framework.** UI thuần Blade + Alpine.js + Tailwind.
- **Controller mỏng:** mỗi action ≤ 10 dòng, không có `if` business logic. Pattern duy nhất:
  1. `$this->authorize(...)` (nếu chưa do FormRequest đảm nhiệm).
  2. Gọi Service.
  3. Trả `view()` hoặc `redirect()->with(...)`.
- **Service không phụ thuộc HTTP:** chỉ nhận array đã validate hoặc Model instance, **không bao giờ** đọc `request()`, không gọi `auth()` để lấy input — chỉ dùng `auth()` để lấy `branch_id`/`user_id` cho multi-tenant.
- **Transactions:** mọi thao tác ghi liên quan nhiều bảng, hoặc cần rollback khi fail, phải bọc trong `DB::transaction()` ở Service.
- **Authorization tập trung trong Policy.** Controller gọi `$this->authorize(...)`, FormRequest gọi `$this->user()->can(...)` trong `authorize()`. Không kiểm tra role thủ công bằng `if ($user->hasRole(...))` rải rác trong Controller/Service.

### 1.3 Ví dụ Controller mỏng

```php
class BranchController extends Controller
{
    public function __construct(private BranchService $service) {}

    public function store(StoreBranchRequest $request)
    {
        $branch = $this->service->create($request->validated());
        return redirect()->route('branches.show', $branch)
            ->with('success', 'Đã tạo branch.');
    }
}
```

---

## 2. Naming Conventions

| Loại | Convention | Ví dụ |
|---|---|---|
| Controller | `{Module}Controller` (singular PascalCase) | `BranchController` |
| FormRequest | `Store{Module}Request` / `Update{Module}Request`, namespace `App\Http\Requests\{Module}` | `App\Http\Requests\Branch\StoreBranchRequest` |
| Service | `{Module}Service`, namespace `App\Services` | `BranchService` |
| Policy | `{Module}Policy`, namespace `App\Policies` | `BranchPolicy` |
| Model | `{Module}` (singular PascalCase), namespace `App\Models` | `Branch` |
| Migration | `YYYY_MM_DD_NNNNNN_create_{modules}_table.php` (plural snake_case) | `2025_01_01_000001_create_branches_table.php` |
| Factory | `{Module}Factory` | `BranchFactory` |
| Seeder | `{Module}Seeder` | `BranchSeeder` |
| View directory | `resources/views/{modules}/` (plural snake_case) | `resources/views/branches/` |
| Blade file | `index.blade.php`, `create.blade.php`, `edit.blade.php`, `show.blade.php` | `branches/index.blade.php` |
| Route name | `{modules}.{action}` | `branches.index`, `branches.store` |
| Component | `x-{kebab-case}` | `<x-button>`, `<x-table>` |
| Layout | `resources/views/layouts/{name}.blade.php` | `layouts/app.blade.php`, `layouts/guest.blade.php` |
| Test class (Feature) | `{Module}Test`, file `tests/Feature/{Module}Test.php` | `BranchTest` |
| Test class (Unit) | `{Module}ServiceTest`, file `tests/Unit/{Module}ServiceTest.php` | `BranchServiceTest` |
| Enum | `{Name}` (PascalCase, singular), namespace `App\Enums` | `LeadStatus`, `SchoolLevel` |
| Trait | `{VerbObject}` PascalCase, namespace `App\Models\Concerns` hoặc `App\Policies\Concerns` | `ChecksBranchOwnership` |
| Scope | `{Name}Scope`, namespace `App\Models\Scopes` | `BranchScope` |
| Exception | `{Subject}{Verb}Exception`, namespace `App\Exceptions` | `BranchHasDependenciesException` |

### 2.1 Quy ước biến và method

- Method test: snake_case, bắt đầu bằng `test_` — ví dụ `test_super_admin_can_create_branch`. Hoặc dùng attribute `#[Test]` của PHPUnit 11.
- Tên Service method: động từ tiếng Anh ngắn gọn — `list`, `create`, `update`, `delete`, `assign`, `setPrimary`. Tránh tên dạng `doXxx`, `processXxx`.
- Tên route resource: dùng `Route::resource('branches', BranchController::class)` để tự động sinh 7 route chuẩn (`index/create/store/show/edit/update/destroy`).
- Permission name (Spatie): dạng `{module}.{action}` (CRUD-level) — ví dụ `leads.view`, `leads.create`, `leads.update`, `leads.delete`. Action đặc biệt: `{module}.view-all` (xem MỌI bản ghi trong branch; không có → chỉ bản ghi của mình) và các action nghiệp vụ như `leads.assign`, `deals.close`, `invoices.issue`/`invoices.void`, `payments.confirm`. **Single source of truth: `App\Support\PermissionCatalog`** — thêm/sửa permission CHỈ tại đây (seeder + UI gán quyền đều đọc từ đó).
- Role name (Spatie): kebab-case cho role hệ thống — `super-admin`, `branch-manager`, `sales`. Role tùy chỉnh do branch tạo có thể đặt tên tự do (vd "Tư vấn viên").

---

## 3. Multi-tenant Rules

CRM Inter-Edu vận hành đa chi nhánh. Mọi quy ước dưới đây là **bắt buộc** để đảm bảo dữ liệu giữa các branch không rò rỉ.

### 3.1 Schema

- Mọi business table **bắt buộc** có cột `branch_id` (foreign key tới `branches.id`).
- Áp dụng cho: `users` (nullable, super-admin null), `leads`, `contacts`, `activities` (NOT NULL).
- Quy tắc FK:
  - `users.branch_id` → `branches.id`, `nullOnDelete()`.
  - `leads.branch_id`, `contacts.branch_id`, `activities.branch_id` → `branches.id`, `restrictOnDelete()` (không xóa branch còn dữ liệu nghiệp vụ).
- Cột `branch_id` trên `Contact` và `Activity` được denormalize từ Lead cha; Service luôn đồng bộ giá trị này khi tạo bản ghi.

### 3.2 BranchScope

- Class `App\Models\Scopes\BranchScope` (Eloquent global scope) tự động thêm `where branch_id = ?` cho user không phải `super-admin`.
- **Áp dụng cho:** `Lead`, `Contact`, `Activity` qua `static::booted()`.
- **KHÔNG áp dụng cho:** `Branch` (chính là tenant), `User` (super-admin cần xem mọi user; non-super-admin chặn ở `UserPolicy`).
- Quy tắc xử lý đặc biệt trong `BranchScope::apply()`:
  - Guest (CLI seed, queue worker, request chưa auth) → không filter (tránh vỡ seeder).
  - User có role `super-admin` → không filter.
  - User có `branch_id === null` và không phải super-admin → `whereRaw('1 = 0')` (không trả gì, an toàn theo mặc định).
  - Còn lại → `where(branch_id, $user->branch_id)`.

### 3.3 Service-layer `branch_id` Injection

- Service **luôn** set `branch_id` từ auth context hoặc parent entity, **không bao giờ** lấy từ input người dùng.
- `FormRequest` **không** đưa `branch_id` vào `rules()` (Laravel auto-strip; nếu attacker gửi lên, Service overwrite trước khi insert).

```php
// Lead: branch_id từ auth user
class LeadService
{
    public function create(array $data): Lead
    {
        $data['branch_id'] = Auth::user()->branch_id;
        return Lead::create($data);
    }
}

// Contact/Activity: branch_id thừa kế từ Lead cha
class ContactService
{
    public function create(Lead $lead, array $data): Contact
    {
        $data['lead_id']   = $lead->id;
        $data['branch_id'] = $lead->branch_id;
        return Contact::create($data);
    }
}
```

### 3.4 Branch_Policy_Pattern

- Mọi Policy của business model **bắt buộc** dùng trait `App\Policies\Concerns\ChecksBranchOwnership`.
- Trait cung cấp:
  - `before(User $user, string $ability): ?bool` — super-admin pass mọi ability tự động.
  - `protected sameBranch(User $user, Model $model): bool` — kiểm tra `$user->branch_id === $model->branch_id`.
- **Authorization theo PERMISSION, KHÔNG theo tên role.** Mỗi Policy method gọi `$this->sameBranch(...)` rồi kiểm tra `$user->can('{module}.{action}')`. Phân biệt "của mình vs toàn branch" qua `{module}.view-all`. **KHÔNG** dùng `hasRole('branch-manager'/'sales')` trong Policy — nhờ vậy role tùy chỉnh do branch tạo cũng hoạt động đúng.

```php
class LeadPolicy
{
    use ChecksBranchOwnership; // before() tự bypass super-admin

    public function view(User $user, Lead $lead): bool
    {
        if (! $this->sameBranch($user, $lead) || ! $user->can('leads.view')) {
            return false;
        }
        // view-all → mọi lead trong branch; nếu không → chỉ lead của mình.
        return $user->can('leads.view-all')
            || $lead->assigned_user_id === $user->id;
    }
}
```

### 3.5 Defense-in-depth

Multi-tenant được bảo vệ ở **3 tầng**, không tầng nào được phép thiếu:

1. **BranchScope** — tự động lọc query, cross-branch trả 404.
2. **Branch_Policy_Pattern** — kiểm tra ownership rõ ràng, cross-branch trả 403.
3. **Service injection** — set `branch_id` từ auth/parent, chặn user gửi `branch_id` lên.

### 3.6 Filter `branch_id` trong UI

- Chỉ `super-admin` được filter danh sách theo `branch_id`. Controller bắt buộc check `$user->isSuperAdmin()` (tương đương `hasRole('super-admin')`) trước khi truyền filter này vào Service.

### 3.7 RBAC đa tenant với Spatie Teams (Role do branch tự quản lý)

CRM dùng **teams feature** của `spatie/laravel-permission`: `team_foreign_key = branch_id`. Mỗi branch tự tạo và quản lý role riêng; branch khác không thấy.

- **Team context:** middleware `App\Http\Middleware\SetPermissionsTeamFromBranch` (đăng ký trong `web` group) đặt `setPermissionsTeamId(auth()->user()?->branch_id)` mỗi request. Ngoài HTTP (seeder, queue, test) phải tự gọi `setPermissionsTeamId()` trước khi tạo/gán role.
- **Role scope:**
  - `super-admin`: role TOÀN CỤC (`branch_id = NULL`), gán cho user `branch_id = null`. Bỏ qua mọi quyền qua `Gate::before` (`AuthServiceProvider`).
  - `branch-manager`, `sales`: role hệ thống được seed **cho từng branch** (`branch_id = <branch>`, `is_system = true`). Branch tạo thêm role tùy chỉnh (`is_system = false`).
- **`App\Models\Role`** mở rộng Spatie Role: thêm cột `branch_id` (= team), `is_system`, quan hệ `branch()`. Bảng pivot (`model_has_roles`/`model_has_permissions`) có `branch_id` **nullable** (super-admin = NULL), KHÔNG nằm trong primary key.
- **Module Vai trò** (sample như Branch_Module): `RoleController` (resource trừ `show`), `RoleService`, `RolePolicy`, `Store/UpdateRoleRequest`, views `roles/`. Quy tắc bắt buộc:
  - Branch chỉ thao tác role thuộc branch mình (`RolePolicy::sameTenant`); super-admin thao tác mọi role.
  - Role `is_system` **không** cho sửa tên/xóa qua UI — `RoleService` ném `RoleIsSystemException`; xóa role đang gán cho user ném `RoleInUseException`.
  - `RoleService` set `branch_id` từ auth (manager → branch mình, super-admin → null) và **lọc** permission về tập actor được phép gán (`PermissionCatalog::branchAssignable()` cho branch user — không cho gán quyền global `branches.*`).
- **Gán role cho user:** `UserService` gán role trong đúng team của user; branch-manager bị ép `branch_id` về branch mình và chỉ gán được role của branch đó (không thể leo thang lên `super-admin`).
- **Test:** dùng trait `Tests\Concerns\InteractsWithRbac` (`setUpRbac()`, `makeUser($role, $branch)`, `makeRole(...)`). Trait nạp sẵn quan hệ role/permission theo đúng team để tránh stale instance khi `actingAs` qua nhiều branch.

---

## 4. UI Rules

### 4.1 Stack

- **Tailwind CSS 4** qua plugin `@tailwindcss/vite` — **không** dùng `tailwind.config.js`. Cấu hình custom đặt trong `resources/css/app.css` qua directive `@theme` của Tailwind 4.
- **Design tokens (single source of truth):** màu thương hiệu khai báo trong `@theme` dưới dạng thang `--color-brand-*` (Emerald) và `--color-accent-*` (Teal). **Mọi view/component bắt buộc dùng class `brand-*`** (vd `bg-brand-600`, `text-brand-700`) — **KHÔNG** hardcode `indigo-*`/`emerald-*`. Đổi nhận diện = sửa token trong `app.css`, không sửa view.
- `app.css` cũng định nghĩa `[x-cloak]{display:none}` (chống nháy modal/dropdown), scrollbar mảnh, và utility `card-hover` / `animate-fade-in-up`.
- **Alpine.js 3** cho interaction phía client. Cài qua npm và import trong `resources/js/app.js`:

  ```js
  import Alpine from 'alpinejs';
  window.Alpine = Alpine;
  Alpine.start();
  ```

- **Vite 7** build asset; layout dùng `@vite(['resources/css/app.css', 'resources/js/app.js'])`.

### 4.2 Layout chuẩn

- `resources/views/layouts/app.blade.php` — sidebar trái + topbar trên + vùng main ở giữa. Dùng cho mọi trang sau khi đăng nhập.
- `resources/views/layouts/guest.blade.php` — layout đơn giản (không sidebar/topbar) cho `login`, `forgot-password`, `reset-password`.

### 4.3 Component dùng chung

| Component | Mục đích | Props chính |
|---|---|---|
| `<x-button>` | Nút | `variant` (`primary`/`secondary`/`danger`/`success`/`ghost`), `size` (`sm`/`md`/`lg`), `type`, `disabled` |
| `<x-input>` | Input text + auto hiển thị error từ `$errors` | `name`, `label`, `type`, `required`, `value`, `placeholder`, `margin` |
| `<x-select>` | Select + auto error; `<option>` truyền qua slot | `name`, `label`, `required`, `placeholder`, `margin` |
| `<x-textarea>` | Textarea + auto error | `name`, `label`, `rows`, `required`, `value`, `margin` |
| `<x-card>` | Khối nội dung chuẩn (thay panel `bg-white p-6 rounded-lg border...`) | `title`, `padding`, slot `actions` |
| `<x-page-header>` | Tiêu đề trang (H1 chuẩn) + vùng nút (slot) | `title`, `subtitle` |
| `<x-stat-card>` | Thẻ KPI cho dashboard/report | `label`, `value`, `icon`, `variant`, `hint` |
| `<x-empty-state>` | Trạng thái rỗng cho list dạng card | `message`, `icon` |
| `<x-table>` | Bảng list (row hover sẵn) | `headers` |
| `<x-table.empty>` | Hàng rỗng bên trong `<x-table>` | `colspan`, `message`, `icon` |
| `<x-badge>` | Nhãn trạng thái | `variant` (`primary`/`success`/`warning`/`danger`/`info`/`secondary`), `dot` |
| `<x-alert>` | Flash message (có icon + transition) | `type` (`success`/`error`/`warning`/`info`), `dismissible` |
| `<x-icon>` | Icon SVG dùng chung (Heroicons) | `name` |
| `<x-nav-link>` | Item điều hướng sidebar (icon + active) | `href`, `icon`, `active` |
| `<x-modal>` | Modal Alpine `x-data="{ open: false }"` | `name`, `title` |
| `<x-sidebar>` | Navigation có icon + nhóm mục, role-aware qua `@can` | `open` (Alpine state) |
| `<x-topbar>` | Breadcrumb/tên trang + user dropdown + logout | `title`, `breadcrumbs` |

### 4.4 Quy tắc viết view

- Mọi form **bắt buộc** có `@csrf`.
- Mọi field **bắt buộc** dùng component tương ứng để auto hiển thị error: text → `<x-input>`, chọn → `<x-select>`, nhiều dòng → `<x-textarea>`. **KHÔNG** viết raw `<input>/<select>/<textarea>` kèm `$errors->first(...)` thủ công trong view nghiệp vụ.
- Mọi trang nghiệp vụ mở đầu bằng `<x-page-header title="...">` (H1 đồng nhất + vùng nút); mọi panel/khối nội dung dùng `<x-card>` (không lặp chuỗi `bg-white p-6 rounded-lg border...`).
- Form/select trong **filter bar ngang** truyền `margin=""` và bọc trong `<div class="w-44">` để căn đáy đều (xem `leads/index.blade.php`).
- Danh sách rỗng: trong `<x-table>` dùng `<x-table.empty :colspan="N">`, list dạng card dùng `<x-empty-state>`.
- Truyền `:breadcrumbs` cho `<x-layouts.app>` ở các trang con để topbar hiển thị đường dẫn điều hướng.

### 4.5 CRUD bằng Modal (bắt buộc)

Tạo/sửa mọi module dùng **modal AJAX dùng chung** — **KHÔNG** có trang `create`/`edit` riêng. Hạ tầng đã có sẵn: modal shell trong `layouts/app`, JS trong `resources/js/app.js` (`Modal`), helper trong base `Controller`.

**View `create.blade.php` / `edit.blade.php` là BARE partial** (chỉ `<form>`, không bọc `<x-layouts.app>`):

```blade
{{-- branches/create.blade.php — nạp vào modal qua AJAX --}}
<form method="POST" action="{{ route('branches.store') }}">
    @csrf
    <x-input name="name" label="Tên branch" required />
    <x-input name="code" label="Mã branch" required />
    <x-input name="address" label="Địa chỉ" />
    <div class="flex items-center justify-end gap-3 pt-4 mt-2 border-t border-gray-100">
        <x-button type="button" variant="secondary" data-modal-close>Hủy</x-button>
        <x-button type="submit" variant="primary">Lưu</x-button>
    </div>
</form>
```

**Mở modal** bằng trigger (bất kỳ phần tử nào có `data-modal-form`):

```blade
<x-button variant="primary" data-modal-form="{{ route('branches.create') }}" data-modal-title="Thêm chi nhánh">
    <x-icon name="plus" class="h-4 w-4" /> Thêm
</x-button>
{{-- Sửa: data-modal-form="{{ route('branches.edit', $branch) }}" --}}
```

**Controller** (giữ thin) dùng helper của base `Controller`:

```php
public function create(Request $request)
{
    $this->authorize('create', Branch::class);
    if (! $this->wantsModalForm()) {           // truy cập trực tiếp → về index
        return redirect()->route('branches.index');
    }
    return view('branches.create', [...]);     // AJAX → trả bare partial
}

public function store(StoreBranchRequest $request)
{
    $branch = $this->service->create($request->validated());
    return $this->modalRedirect(route('branches.show', $branch), 'Đã tạo chi nhánh.');
}
```

- `wantsModalForm()` — true khi AJAX GET (modal nạp form); false → redirect (bỏ trang riêng).
- `modalRedirect($url, $msg)` — AJAX: flash + JSON `{redirect}` để JS điều hướng; thường: redirect như cũ.
- Nếu **Service ném `ValidationException`**, bắt và trả `return $this->validationResponse($e);` (AJAX → 422 JSON cho JS vẽ lỗi cạnh field; thường → `back()->withErrors`). FormRequest tự trả 422 JSON nên không cần xử lý thêm.

### 4.6 Kanban (task board)

- Kéo–thả bằng **SortableJS** (`resources/js/app.js`). Cột = `[data-kanban-column="{status}"]`, thẻ = `[data-task-id]` + `[data-status-url]`.
- Thả thẻ sang cột khác → POST `tasks.status` đổi trạng thái (revert nếu thất bại/thiếu quyền). Cursor `grab`/`grabbing` khai báo trong `app.css`.

### 4.7 Quy tắc chung khác

- Sidebar nav **bắt buộc** dùng `@can('permission-name')`; **không** hardcode `@if(hasRole(...))`.
- Trang `index` có nút tạo bọc trong `@can('create', Model::class)` (dạng trigger modal).
- Mọi trang **list/show** extend `<x-layouts.app title="...">` (auto render flash). Truyền `:breadcrumbs` cho trang con.
- Sidebar mobile: < 768px ẩn, mở qua toggle topbar (Alpine `sidebarOpen`).
- Ngôn ngữ UI: tiếng Việt.

---

## 5. Testing Rules

### 5.1 Tooling

- **PHPUnit 11.5** (đã có sẵn trong skeleton).
- Trait `Illuminate\Foundation\Testing\RefreshDatabase` cho mọi test class chạm DB.
- **Factory bắt buộc** cho mọi seed data; **không** insert thủ công bằng `DB::table(...)->insert(...)` trong test.
- `actingAs($user)` để set auth context.

### 5.2 Phân loại

| Loại | Vị trí | Mục đích |
|---|---|---|
| Feature | `tests/Feature/{Module}Test.php` | HTTP layer, auth, authorization, validation end-to-end |
| Unit | `tests/Unit/{Module}ServiceTest.php` | Logic Service tách biệt khỏi HTTP |
| Auth Feature | `tests/Feature/Auth/{Action}Test.php` | Login / Logout / ForgotPassword / ResetPassword |
| Multi-tenant | `tests/Feature/BranchScopeTest.php` | BranchScope isolation |

### 5.3 Naming method

- snake_case bắt đầu bằng `test_`, format khuyến nghị: `test_{subject}_{condition}_{expectation}`.
  - Ví dụ: `test_sales_cannot_view_lead_assigned_to_other_user`, `test_super_admin_can_create_branch`.
- Hoặc dùng attribute `#[Test]` của PHPUnit 11 (tên method khi đó vẫn nên mô tả rõ ràng).

### 5.4 Coverage tối thiểu mỗi module nghiệp vụ

- Test mỗi action CRUD **success path**.
- Test **authorization** cho từng role: `super-admin`, `branch-manager`, `sales`, `guest`.
- Test các **validation fail case** quan trọng (uniqueness, conditional rule, cross-rule như `email_or_phone`).
- Test **BranchScope isolation** (cho `Lead`, `Contact`, `Activity`).
- Test các edge case nghiệp vụ chuyên biệt — ví dụ `Branch` refusal-if-linked, `Contact` `is_primary` uniqueness, `Lead::assign` cross-branch refusal.

### 5.5 Property-based tests

- Một số property quan trọng (xem mục "Correctness Properties" trong `design.md`) được implement dưới dạng property test, mỗi test chạy ≥ 100 iterations.
- Tag mỗi property test theo format: `Feature: crm-inter-edu, Property {N}: {tên property}`.
- Mỗi property test phải khai báo dòng `**Validates: Requirements X.Y**` ở đầu file/test method để liên kết với requirement.

### 5.6 Quy tắc viết test

- **KHÔNG** dùng mock cho Eloquent / DB — luôn dùng DB thật qua `RefreshDatabase`.
- **KHÔNG** dùng mock để làm test pass khi logic chưa đúng.
- Trước khi viết test mới, kiểm tra test hiện có để tránh trùng.

---

## 6. Git / Commit Rules

### 6.1 Branch naming

- `feature/{module}-{short-desc}` — tính năng mới. Ví dụ: `feature/branch-crud`, `feature/lead-assign`.
- `bugfix/{short-desc}` — sửa lỗi. Ví dụ: `bugfix/contact-primary-race`.
- `chore/{short-desc}` — refactor, cập nhật doc, bump deps.
- `hotfix/{short-desc}` — sửa lỗi production gấp.

### 6.2 Commit message — Conventional Commits

Format: `{type}({scope}): {short imperative description}`

- `type` ∈ `feat`, `fix`, `refactor`, `test`, `docs`, `chore`, `style`, `perf`.
- `scope` thường là tên module: `branch`, `lead`, `auth`, `ui`, `db`...
- Mô tả ngắn gọn, dạng mệnh lệnh, không kết thúc bằng dấu chấm.

Ví dụ:

```
feat(branch): add Branch CRUD with sample policy
fix(lead): set branch_id from auth in LeadService::create
test(contact): add property test for is_primary uniqueness
docs(coding-rules): clarify multi-tenant rules
chore(deps): bump alpinejs to 3.14
```

### 6.3 Pull request

- Mỗi PR ≤ ~500 dòng code, chia nhỏ task theo `tasks.md`.
- Mô tả PR liên kết task ID (`Task 2.5: Tạo App\Models\Branch`) và requirement (`Requirements: 5.1, 5.3`).
- PR phải pass đầy đủ test trước khi merge.

---

## 7. Examples — Branch_Module là Sample_Module

`Branch_Module` là **sample module chuẩn**. Khi xây module nghiệp vụ mới (Users, Leads, Contacts, Activities, Dashboard, hoặc bất kỳ module v2 nào), **bắt buộc** copy cấu trúc của `Branch_Module` và chỉ sửa phần khác biệt nghiệp vụ.

### 7.1 Cấu trúc tham chiếu (copy từ Branch_Module)

```
app/
├── Http/
│   ├── Controllers/
│   │   └── BranchController.php           # 7 action resourceful, mỗi action ≤ 10 dòng
│   └── Requests/
│       └── Branch/
│           ├── StoreBranchRequest.php
│           └── UpdateBranchRequest.php
├── Models/
│   └── Branch.php                         # fillable, casts, quan hệ
├── Policies/
│   └── BranchPolicy.php                   # use ChecksBranchOwnership
└── Services/
    └── BranchService.php                  # list/create/update/delete trong DB::transaction

database/
├── factories/
│   └── BranchFactory.php
├── migrations/
│   └── 2025_01_01_000001_create_branches_table.php
└── seeders/
    └── BranchSeeder.php

resources/views/branches/
├── index.blade.php
├── create.blade.php
├── edit.blade.php
└── show.blade.php

routes/web.php           # Route::resource('branches', BranchController::class)

tests/
├── Feature/
│   └── BranchTest.php                     # CRUD + authorization từng role + validation
└── Unit/
    └── BranchServiceTest.php              # logic Service
```

### 7.2 Khi tạo module mới — checklist 12 bước

1. Migration tạo bảng (cộng thêm `branch_id` nếu là business table).
2. Model với `$fillable`, `$casts`, quan hệ; áp dụng `BranchScope` qua `static::booted()` nếu là business model (Lead/Contact/Activity).
3. `Store{Module}Request` + `Update{Module}Request` trong namespace `App\Http\Requests\{Module}`.
4. `{Module}Service` với các method `list/create/update/delete` (và method nghiệp vụ riêng nếu có).
5. `{Module}Policy` `use ChecksBranchOwnership`, đăng ký trong `AuthServiceProvider`.
6. `{Module}Controller` resourceful, inject Service qua constructor.
7. `Route::resource('{modules}', {Module}Controller::class)` trong nhóm middleware `auth`.
8. Blade views `index/create/edit/show.blade.php` dùng `<x-layouts.app>` + x-components.
9. `{Module}Factory` + `{Module}Seeder`, đăng ký trong `DatabaseSeeder`.
10. Cập nhật `<x-sidebar>` thêm nav item bọc trong `@can('{module}.view')`; khai báo permission của module trong `App\Support\PermissionCatalog`.
11. Feature test `{Module}Test` (CRUD success + authorization 4 role + validation fail case).
12. Unit test `{Module}ServiceTest` cho logic Service.

### 7.3 Quy tắc thay đổi quy ước

- Khi cần thay đổi quy ước (đổi pattern, thêm trait, đổi cấu trúc), **bắt buộc** cập nhật trên `Branch_Module` trước, viết lại tài liệu này, rồi mới rollout sang các module khác trong cùng PR (hoặc các PR liên tiếp có liên kết rõ ràng).
- **KHÔNG** để hai module dùng hai pattern khác nhau cho cùng một loại việc.
