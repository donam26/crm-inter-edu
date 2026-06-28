@props([
    'colspan' => 1,
    'message' => 'Chưa có dữ liệu.',
    'icon' => 'inbox',
])

{{-- Hàng empty-state bên trong <x-table>. --}}
<tr>
    <td colspan="{{ $colspan }}" class="px-4 py-12 text-center">
        <div class="flex flex-col items-center justify-center text-center">
            <span class="inline-flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 mb-3">
                <x-icon :name="$icon" class="h-5 w-5" />
            </span>
            <p class="text-sm text-gray-500">{{ $slot->isEmpty() ? $message : $slot }}</p>
        </div>
    </td>
</tr>
