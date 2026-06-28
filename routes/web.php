<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DealController;
use App\Http\Controllers\DealItemController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RevenueReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('branches', BranchController::class);
    Route::resource('users', UserController::class);
    Route::resource('roles', RoleController::class)->except(['show']);
    Route::resource('leads', LeadController::class);
    Route::post('/leads/{lead}/assign', [LeadController::class, 'assign'])->name('leads.assign');
    Route::resource('leads.contacts', ContactController::class)
        ->shallow()
        ->except(['index']);
    Route::resource('leads.activities', ActivityController::class)
        ->shallow()
        ->except(['index']);

    Route::resource('tasks', TaskController::class);
    Route::post('/tasks/{task}/start', [TaskController::class, 'start'])->name('tasks.start');
    Route::post('/tasks/{task}/complete', [TaskController::class, 'complete'])->name('tasks.complete');
    Route::post('/tasks/{task}/reopen', [TaskController::class, 'reopen'])->name('tasks.reopen');
    Route::post('/tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('tasks.status');

    Route::get('/events/calendar', [EventController::class, 'calendar'])->name('events.calendar');
    Route::resource('events', EventController::class);
    Route::post('/events/{event}/done', [EventController::class, 'markDone'])->name('events.done');
    Route::post('/events/{event}/cancel', [EventController::class, 'cancel'])->name('events.cancel');
    Route::post('/events/{event}/respond', [EventController::class, 'respond'])->name('events.respond');

    // ───────────────────── Revenue Module ─────────────────────

    // Catalog sản phẩm/gói khảo thí.
    Route::resource('products', ProductController::class);

    // Pipeline doanh thu: Deal (1 lead = 1 deal).
    Route::resource('deals', DealController::class);
    Route::post('/deals/{deal}/win', [DealController::class, 'win'])->name('deals.win');
    Route::post('/deals/{deal}/lose', [DealController::class, 'lose'])->name('deals.lose');
    Route::post('/deals/{deal}/reopen', [DealController::class, 'reopen'])->name('deals.reopen');

    // Line items của deal (nested shallow).
    Route::get('/deals/{deal}/items/create', [DealItemController::class, 'create'])
        ->name('deals.items.create');
    Route::post('/deals/{deal}/items', [DealItemController::class, 'store'])
        ->name('deals.items.store');
    Route::get('/deal-items/{item}/edit', [DealItemController::class, 'edit'])
        ->name('deal-items.edit');
    Route::put('/deal-items/{item}', [DealItemController::class, 'update'])
        ->name('deal-items.update');
    Route::delete('/deal-items/{item}', [DealItemController::class, 'destroy'])
        ->name('deal-items.destroy');

    // Hoá đơn (nested under deal cho create/store, top-level cho phần còn lại).
    Route::get('/deals/{deal}/invoices/create', [InvoiceController::class, 'create'])
        ->name('deals.invoices.create');
    Route::post('/deals/{deal}/invoices', [InvoiceController::class, 'store'])
        ->name('deals.invoices.store');
    Route::resource('invoices', InvoiceController::class)->except(['create', 'store']);
    Route::post('/invoices/{invoice}/issue', [InvoiceController::class, 'issue'])
        ->name('invoices.issue');
    Route::post('/invoices/{invoice}/void', [InvoiceController::class, 'void'])
        ->name('invoices.void');

    // Thanh toán (nested under invoice cho create/store).
    Route::get('/invoices/{invoice}/payments/create', [PaymentController::class, 'create'])
        ->name('invoices.payments.create');
    Route::post('/invoices/{invoice}/payments', [PaymentController::class, 'store'])
        ->name('invoices.payments.store');
    Route::resource('payments', PaymentController::class)->except(['create', 'store']);
    Route::post('/payments/{payment}/confirm', [PaymentController::class, 'confirm'])
        ->name('payments.confirm');

    // Báo cáo doanh thu.
    Route::get('/revenues/report', [RevenueReportController::class, 'index'])
        ->name('revenues.report');
});

require __DIR__.'/auth.php';
