@props([
    'title' => null,
    'breadcrumbs' => [],
])

@php
    $user = auth()->user();
    $roleName = $user?->roles?->first()?->name;
    $branchName = $user?->branch?->name;
    $initial = mb_strtoupper(mb_substr($user?->name ?? '?', 0, 1));
@endphp

<header class="bg-white border-b border-gray-200 h-16 flex items-center gap-3 px-4 md:px-6 sticky top-0 z-20">
    {{-- Mobile sidebar toggle --}}
    <button
        type="button"
        class="md:hidden -ml-1 p-2 rounded-md text-gray-600 hover:bg-gray-100 transition"
        @click="$dispatch('toggle-sidebar')"
    >
        <span class="sr-only">Mở menu</span>
        <x-icon name="menu" class="h-5 w-5" />
    </button>

    {{-- Breadcrumb trail. Mặc định dùng $title nếu trang không truyền breadcrumbs. --}}
    @php
        $crumbs = ! empty($breadcrumbs) ? $breadcrumbs : ($title ? [['label' => $title]] : []);
    @endphp
    <div class="min-w-0 flex-1">
        @if (! empty($crumbs))
            <nav class="flex items-center gap-1.5" aria-label="Breadcrumb">
                @foreach ($crumbs as $crumb)
                    @if (! $loop->first)
                        <x-icon name="chevron-right" class="h-3.5 w-3.5 text-gray-300" />
                    @endif
                    @if (! empty($crumb['url']) && ! $loop->last)
                        <a href="{{ $crumb['url'] }}" class="text-xs text-gray-400 hover:text-gray-600 transition truncate">{{ $crumb['label'] }}</a>
                    @elseif ($loop->last)
                        <span class="text-sm font-semibold text-gray-900 truncate">{{ $crumb['label'] }}</span>
                    @else
                        <span class="text-xs text-gray-400 truncate">{{ $crumb['label'] }}</span>
                    @endif
                @endforeach
            </nav>
        @endif
    </div>

    @auth
        {{-- User dropdown --}}
        <div class="relative" x-data="{ menuOpen: false }">
            <button
                type="button"
                @click="menuOpen = !menuOpen"
                class="flex items-center gap-2 rounded-md py-1 pl-1 pr-2 hover:bg-gray-100 text-sm transition focus:outline-none focus:ring-2 focus:ring-brand-500"
            >
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-brand-100 text-brand-700 font-semibold text-sm">{{ $initial }}</span>
                <span class="hidden md:flex flex-col items-start leading-tight">
                    <span class="font-medium text-gray-900">{{ $user->name }}</span>
                    @if ($roleName)
                        <span class="text-[11px] text-gray-400">{{ $roleName }}</span>
                    @endif
                </span>
                <x-icon name="chevron-down" class="h-4 w-4 text-gray-400" />
            </button>

            <div
                x-show="menuOpen"
                @click.outside="menuOpen = false"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0 scale-95"
                x-cloak
                class="absolute right-0 mt-2 w-60 origin-top-right bg-white rounded-lg border border-gray-200 shadow-lg py-1.5 text-sm"
            >
                <div class="px-4 py-3 border-b border-gray-100">
                    <div class="font-medium text-gray-900 truncate">{{ $user->name }}</div>
                    <div class="text-xs text-gray-500 truncate">{{ $user->email }}</div>
                    @if ($branchName)
                        <div class="mt-1.5 inline-flex items-center gap-1 text-[11px] text-gray-500">
                            <x-icon name="building" class="h-3.5 w-3.5 text-gray-400" />
                            {{ $branchName }}
                        </div>
                    @endif
                </div>

                @if (Route::has('logout'))
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button
                            type="submit"
                            class="w-full flex items-center gap-2 px-4 py-2 text-gray-700 hover:bg-gray-100 transition"
                        >
                            <x-icon name="logout" class="h-4 w-4 text-gray-400" />
                            Đăng xuất
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @endauth
</header>
