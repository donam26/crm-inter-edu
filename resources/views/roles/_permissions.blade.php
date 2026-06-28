{{-- Ma trận quyền theo nhóm module. Nhận $groups (PermissionCatalog::groupsFor)
     và $assigned (list tên quyền đã gán). Dùng chung cho create + edit. --}}
@php($checked = old('permissions', $assigned ?? []))

<div class="max-h-[55vh] space-y-3 overflow-y-auto pr-1">
    @foreach ($groups as $group)
        <fieldset
            x-data="{ toggleAll(e) { this.$root.querySelectorAll('input[type=checkbox][data-perm]').forEach(c => c.checked = e.target.checked) } }"
            class="rounded-lg border border-gray-200 p-3"
        >
            <legend class="flex items-center gap-2 px-1">
                <span class="text-sm font-semibold text-gray-700">{{ $group['label'] }}</span>
                <label class="ml-auto inline-flex items-center text-[11px] text-gray-400">
                    <input type="checkbox" @change="toggleAll($event)"
                        class="mr-1 rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                    Chọn tất cả
                </label>
            </legend>
            <div class="mt-1 grid grid-cols-1 gap-1.5 sm:grid-cols-2">
                @foreach ($group['permissions'] as $name => $label)
                    <label class="inline-flex items-center text-sm">
                        <input type="checkbox" name="permissions[]" value="{{ $name }}" data-perm
                            @checked(in_array($name, $checked, true))
                            class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                        <span class="ml-2 text-gray-700">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>
    @endforeach
</div>

@error('permissions')
    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
@enderror
@error('permissions.*')
    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
@enderror
