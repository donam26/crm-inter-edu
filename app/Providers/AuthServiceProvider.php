<?php

namespace App\Providers;

use App\Models\Activity;
use App\Models\Branch;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\DealItem;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Policies\ActivityPolicy;
use App\Policies\BranchPolicy;
use App\Policies\ContactPolicy;
use App\Policies\DealItemPolicy;
use App\Policies\DealPolicy;
use App\Policies\EventPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\LeadPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ProductPolicy;
use App\Policies\RolePolicy;
use App\Policies\TaskPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Branch::class => BranchPolicy::class,
        User::class => UserPolicy::class,
        Role::class => RolePolicy::class,
        Lead::class => LeadPolicy::class,
        Contact::class => ContactPolicy::class,
        Activity::class => ActivityPolicy::class,
        Task::class => TaskPolicy::class,
        Event::class => EventPolicy::class,
        Product::class => ProductPolicy::class,
        Deal::class => DealPolicy::class,
        DealItem::class => DealItemPolicy::class,
        Invoice::class => InvoicePolicy::class,
        Payment::class => PaymentPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // Super-admin (role toàn cục) bỏ qua mọi kiểm tra quyền — kể cả các
        // @can('permission') trong view. Team context = null khi super-admin
        // đăng nhập nên hasRole hoạt động đúng.
        Gate::before(function (User $user, string $ability) {
            return $user->hasRole('super-admin') ? true : null;
        });
    }
}
