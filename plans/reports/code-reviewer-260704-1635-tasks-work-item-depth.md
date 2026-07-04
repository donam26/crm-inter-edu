# Code Review — feat/tasks-work-item-depth (Phase 1: work-item depth)

Date: 2026-07-04 · Reviewer: code-reviewer (read-only) · Plan: `plans/260704-1547-tasks-jira-uplift/phase-01-work-item-depth.md`

## Scope

- Uncommitted working-tree changes on `feat/tasks-work-item-depth` (15 modified + 24 new files: comments, checklist, labels, `start_at`, spatie/activitylog audit, redesigned task show page, kanban badges).
- ~900 lines changed. Focus: multi-tenant/RBAC holes, authz gaps on new routes, N+1, Eloquent/Laravel-12 correctness, Blade correctness, migration safety. Style/lint skipped per instructions (pint already clean, 266/266 tests passing).
- Verification method: read every new/changed file + full diffs, traced route → FormRequest/controller → policy → service → model for every new endpoint, and empirically confirmed two ambiguous behaviors by running (and then deleting) a throwaway `php artisan tinker`/PHPUnit probe rather than guessing from memory.

## Overall assessment

Solid, well-scoped implementation that mostly follows the codebase's existing patterns (thin controllers, service-layer DB::transaction, BranchScope + policy layering, FormRequest-based authorization). One real RBAC gap found and confirmed exploitable (below). Everything else checked out — migrations match the vendor stub byte-for-byte, kanban/board eager-loading is correct, Blade output is properly escaped, activity-log integration matches spatie v5 API.

## Findings

### 1. [HIGH] `TaskCommentPolicy::create()` missing per-task ownership check — confirmed exploitable
**File:** `app/Policies/TaskCommentPolicy.php:17-20`
```php
public function create(User $user, Task $task): bool
{
    return $this->sameBranch($user, $task) && $user->can('tasks.view');
}
```
`TaskPolicy::view()` (the ability that gates the task's own show page) additionally requires `tasks.view-all` OR `assigned_user_id === user` OR `lead->assigned_user_id === user`. `TaskCommentPolicy::create()` drops that last condition — it only checks branch + the blanket `tasks.view` permission, which every `sales` role has (`RolePermissionSeeder::salesPermissions()`). Net effect: any sales user can `POST /tasks/{task}/comments` on **any task in their own branch**, including ones assigned to a colleague with no lead relation to them — a task they cannot even open (`GET /tasks/{task}` correctly 403s via `TaskPolicy::view()`). This also diverges from the plan's own suggested implementation (`phase-01-work-item-depth.md` §7: `app(TaskPolicy::class)->view($user, $task)`), so it reads as an implementation slip rather than an intentional KISS cut (the other 4 scope cuts are explicitly logged in plan.md; this one isn't).

**Confirmed exploitable:** wrote a throwaway feature test (sales user, same branch, task assigned to a different sales user, no lead) — `GET tasks.show` correctly 403s, but `POST tasks.comments.store` returned **302 (success)**, not 403. Test deleted after confirming; not left in the tree.

**Fix:**
```php
public function create(User $user, Task $task): bool
{
    return app(TaskPolicy::class)->view($user, $task);
}
```
Confirmed this doesn't regress existing tests: `test_assignee_can_add_comment` still passes (`assigned_user_id === user` branch of `view()`), super-admin still bypasses (via `TaskCommentPolicy`'s own `before()`, which short-circuits through Gate before `create()` body ever runs — direct-call semantics differ, but that path is never taken by an authenticated super-admin anyway since `before()` intercepts first).

**Not found to be issues (checked, ruled out):**
- `TaskCommentPolicy::delete()` — correctly requires own-comment or `tasks.view-all` (matches `TaskPolicy` pattern, covered by `test_sales_cannot_delete_others_comment`/`test_manager_can_delete_others_comment_via_view_all`).
- `TaskChecklistController::store` — authorized via `StoreChecklistItemRequest::authorize()` → `TaskPolicy::update()`, which does have the ownership check. Cross-branch confirmed 404 (BranchScope hides the task at route-model-binding time) via existing `test_foreign_sales_cannot_add_checklist`.
- `TaskChecklistController::update`/`destroy` passing `$item->task` (a `BelongsTo` with no scope override) to `$this->authorize('update', ...)`: if the parent task is in a different branch, `$item->task` resolves to `null` (BranchScope on `Task` filters the relation query too) → empirically verified `Gate`/policy-type-mismatch is caught internally and denies with a clean `AuthorizationException` (403), not a 500 or a bypass.
- `LabelController`/`LabelPolicy`/`LabelService::sync` — branch-scoped correctly; `sync()` re-validates label branch server-side (`Label::withoutGlobalScopes()->where('branch_id', $task->branch_id)`) so a tampered POST with a foreign label id is silently dropped, not accepted (covered by `test_sync_only_accepts_same_branch_labels`).
- `TaskComment`/`TaskChecklistItem`/`Label` policies aren't registered in `AuthServiceProvider::$policies` — verified via `Gate::getPolicyFor()` that Laravel's naming-convention auto-discovery resolves them correctly anyway (`TaskComment`→`TaskCommentPolicy`, `Label`→`LabelPolicy`). Not a defect.

### 2. [MEDIUM] N+1 in task activity feed for `assigned_user_id` changes
**File:** `app/Http/Controllers/TaskController.php:337-349` (`formatActivityValue`), called from `taskActivityFeed()` (lines 292-335)

`Activity::...->with('causer')->get()->map(...)` avoids N+1 for the causer, but inside the per-row/per-field loop, every time the changed field is `assigned_user_id`, it does `User::withoutGlobalScopes()->find($value)` — once for the old value, once for the new value. Up to 2 extra queries per log row that touched the assignee, on top of the up-to-50 rows fetched. Bounded by `limit(50)` so not catastrophic, but wasteful and easy to fix.

**Fix:** collect all distinct `assigned_user_id` old/new values across the fetched `$logs` collection first, do one `User::withoutGlobalScopes()->whereIn('id', $ids)->pluck('name', 'id')`, then have `formatActivityValue` read from that map instead of querying per row.

### 3. [LOW] Checklist reorder silently dropped, undocumented
**Files:** `routes/web.php`, `app/Http/Controllers/TaskChecklistController.php`, `app/Services/TaskChecklistService.php`

`phase-01-work-item-depth.md` §6/§5 specs a `tasks.checklist.reorder` route and `TaskChecklistService::reorder()`; neither exists. Unlike the other 4 documented "Điều chỉnh KISS" cuts (dropped `parent_id`, dropped `done_by`/`done_at`, inline label form, activitylog v5 method rename), this one isn't called out in plan.md. Not a code bug — nothing references the missing method — just a plan/doc gap. Either implement minimal reorder or add it to the documented-cuts list.

### 4. [LOW] Operational: reseed required for existing (non-fresh) environments
**File:** `database/seeders/RolePermissionSeeder.php`

New `labels.view`/`labels.manage` permissions won't retroactively attach to already-seeded `branch-manager`/`sales` roles until `RolePermissionSeeder` is rerun (it's idempotent by design, per its own docblock). Add "rerun RolePermissionSeeder" to the deploy checklist for this branch; otherwise existing users get 403 on the new Labels feature until reseeded.

### 5. [LOW] Minor race on checklist item `position`
**File:** `app/Services/TaskChecklistService.php:14-24`

`$position = (int) $task->checklistItems()->max('position') + 1` inside `DB::transaction()` without `lockForUpdate()`. Two concurrent "add item" requests on the same task could read the same max and insert duplicate `position` values. No unique constraint on `(task_id, position)`, so no error — just a possible cosmetic ordering tie. Given single-user-editing-a-task-at-a-time is the realistic case for a CRM task checklist, not worth fixing unless concurrent editing becomes common.

## Verified sound (no write-up needed beyond this line)
- Migrations: all 5 new migrations + hand-copied `activity_log` migration — checked column-for-column against the installed `vendor/spatie/laravel-activitylog` stub (byte-identical), FK cascade/restrict choices are consistent with existing branch-scoped-resource patterns, `down()` present and correct, ordering is FK-safe.
- `Task`/`Label` global `BranchScope` interplay with new child tables (`task_comments`, `task_checklist_items`, no scope — access controlled through parent-task policy checks, exactly as plan.md specifies) — correct.
- Kanban card + list-view eager loading (`TaskService::buildQuery()` now loads `labels` and `withCount` for checklist totals) — shared by both `board()` and `list()`, no N+1 on cards.
- Blade: `tasks/show.blade.php`, `tasks/_kanban_card.blade.php`, `labels/*.blade.php` — all output auto-escaped (no `{!! !!}`), `@csrf`/`@method` present on every mutating form, `@can` gates match the underlying policies.
- Mass-assignment: every new `::create()`/`->update()` call site passes an explicit array (service layer), never spreads raw request input into a model; `$fillable` on `Label`/`TaskComment`/`TaskChecklistItem` is a second layer, not the only one.
- Route-model binding + implicit binding param names verified against controller signatures for all 6 new routes — all match.

## Unresolved questions
- Is the RBAC gap in finding #1 an oversight, or was "sales can comment branch-wide" actually intended and the plan's own policy snippet (§7) is just stale? If intended, phase-01's acceptance criterion #3 ("sales chỉ thao tác trên task trong tầm") needs rewording instead of the policy needing a fix.
- Any appetite for implementing checklist reorder in this same branch, or defer to a follow-up/P2 and log it as an explicit scope cut like the other four?
