@props([
    'open' => 'sidebarOpen',
])

<aside
    class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-white border-r border-gray-200 transform transition-transform duration-200 md:static md:translate-x-0"
    :class="{ 'translate-x-0': {{ $open }}, '-translate-x-full': !{{ $open }} }"
>
    {{-- Brand --}}
    <div class="h-16 flex items-center gap-2.5 px-5 border-b border-gray-200 shrink-0">
        <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-brand-600 text-white font-bold shadow-sm">IE</span>
        <div class="leading-tight">
            <div class="text-sm font-bold text-gray-900">CRM Inter-Edu</div>
            <div class="text-[11px] text-gray-400">Quản lý khách hàng</div>
        </div>
    </div>

    {{-- Navigation --}}
    <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
        @if (Route::has('dashboard'))
            <x-nav-link :href="route('dashboard')" icon="dashboard" :active="request()->routeIs('dashboard')">
                Dashboard
            </x-nav-link>
        @endif

        {{-- ───────── Bán hàng & CSKH ───────── --}}
        <p class="px-3 pt-5 pb-1 text-[11px] font-semibold uppercase tracking-wider text-gray-400">Bán hàng &amp; CSKH</p>

        @can('customers.view')
            @if (Route::has('customers.index'))
                <x-nav-link :href="route('customers.index')" icon="customers" :active="request()->routeIs('customers.*')">
                    Lead
                </x-nav-link>
            @endif
        @endcan

        @can('tasks.view')
            @if (Route::has('tasks.index'))
                <x-nav-link :href="route('tasks.index')" icon="tasks" :active="request()->routeIs('tasks.*')">
                    Công việc
                </x-nav-link>
            @endif
        @endcan

        @can('events.view')
            @if (Route::has('events.index'))
                <x-nav-link :href="route('events.index')" icon="calendar" :active="request()->routeIs('events.*')">
                    Lịch hẹn
                </x-nav-link>
            @endif
        @endcan

        {{-- ───────── Doanh thu ───────── --}}
        @canany(['deals.view', 'invoices.view', 'payments.view', 'products.view', 'revenues.view'])
            <p class="px-3 pt-5 pb-1 text-[11px] font-semibold uppercase tracking-wider text-gray-400">Doanh thu</p>
        @endcanany

        @can('deals.view')
            @if (Route::has('deals.index'))
                <x-nav-link :href="route('deals.index')" icon="deals" :active="request()->routeIs('deals.*')">
                    Cơ hội bán hàng
                </x-nav-link>
            @endif
        @endcan

        @can('invoices.view')
            @if (Route::has('invoices.index'))
                <x-nav-link :href="route('invoices.index')" icon="invoice" :active="request()->routeIs('invoices.*')">
                    Hoá đơn
                </x-nav-link>
            @endif
        @endcan

        @can('payments.view')
            @if (Route::has('payments.index'))
                <x-nav-link :href="route('payments.index')" icon="payment" :active="request()->routeIs('payments.*')">
                    Thanh toán
                </x-nav-link>
            @endif
        @endcan

        @can('products.view')
            @if (Route::has('products.index'))
                <x-nav-link :href="route('products.index')" icon="product" :active="request()->routeIs('products.*')">
                    Sản phẩm
                </x-nav-link>
            @endif
        @endcan

        @can('revenues.view')
            @if (Route::has('revenues.report'))
                <x-nav-link :href="route('revenues.report')" icon="chart" :active="request()->routeIs('revenues.*')">
                    Báo cáo doanh thu
                </x-nav-link>
            @endif
        @endcan

        {{-- ───────── Quản trị ───────── --}}
        @canany(['branches.view', 'users.view', 'roles.view'])
            <p class="px-3 pt-5 pb-1 text-[11px] font-semibold uppercase tracking-wider text-gray-400">Quản trị</p>
        @endcanany

        @can('branches.view')
            @if (Route::has('branches.index'))
                <x-nav-link :href="route('branches.index')" icon="building" :active="request()->routeIs('branches.*')">
                    Chi nhánh
                </x-nav-link>
            @endif
        @endcan

        @can('users.view')
            @if (Route::has('users.index'))
                <x-nav-link :href="route('users.index')" icon="users" :active="request()->routeIs('users.*')">
                    Người dùng
                </x-nav-link>
            @endif
        @endcan

        @can('roles.view')
            @if (Route::has('roles.index'))
                <x-nav-link :href="route('roles.index')" icon="shield" :active="request()->routeIs('roles.*')">
                    Vai trò
                </x-nav-link>
            @endif
        @endcan

        @can('labels.manage')
            @if (Route::has('labels.index'))
                <x-nav-link :href="route('labels.index')" icon="tasks" :active="request()->routeIs('labels.*')">
                    Nhãn công việc
                </x-nav-link>
            @endif
        @endcan
    </nav>
</aside>

{{-- Mobile backdrop --}}
<div
    x-show="{{ $open }}"
    @click="{{ $open }} = false"
    x-transition.opacity
    class="fixed inset-0 z-30 bg-black/40 md:hidden"
    x-cloak
></div>
