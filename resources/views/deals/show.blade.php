<x-layouts.app title="Chi tiết Deal" :breadcrumbs="[
    ['label' => 'Dashboard', 'url' => route('dashboard')],
    ['label' => 'Cơ hội bán hàng', 'url' => route('deals.index')],
    ['label' => $deal->code],
]">
    <div class="max-w-4xl">
        <x-page-header :title="$deal->title" :subtitle="$deal->code">
            @can('update', $deal)
                <x-button variant="secondary" data-modal-form="{{ route('deals.edit', $deal) }}" data-modal-title="Sửa deal">Sửa</x-button>
            @endcan
            @can('delete', $deal)
                <form method="POST" action="{{ route('deals.destroy', $deal) }}" onsubmit="return confirm('Xoá deal?');" class="inline">
                    @csrf @method('DELETE')
                    <x-button type="submit" variant="danger">Xoá</x-button>
                </form>
            @endcan
            @can('close', $deal)
                @if ($deal->stage?->isOpen())
                    <form method="POST" action="{{ route('deals.win', $deal) }}" class="inline" onsubmit="return confirm('Đánh dấu deal này thắng?');">
                        @csrf
                        <x-button type="submit" variant="primary"><x-icon name="check" class="h-4 w-4" /> Won</x-button>
                    </form>
                    <form method="POST" action="{{ route('deals.lose', $deal) }}" class="inline" onsubmit="return confirm('Đánh dấu deal này mất?');">
                        @csrf
                        <x-button type="submit" variant="danger"><x-icon name="x-mark" class="h-4 w-4" /> Lost</x-button>
                    </form>
                @else
                    <form method="POST" action="{{ route('deals.reopen', $deal) }}" class="inline">
                        @csrf
                        <x-button type="submit" variant="secondary">Mở lại</x-button>
                    </form>
                @endif
            @endcan
        </x-page-header>

        {{-- Tóm tắt --}}
        <x-card title="Thông tin">
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                <div>
                    <dt class="text-gray-500">Stage</dt>
                    <dd class="mt-0.5"><x-badge :variant="$deal->stage?->badgeVariant() ?? 'secondary'">{{ $deal->stage?->label() }}</x-badge></dd>
                </div>
                <div>
                    <dt class="text-gray-500">Lead</dt>
                    <dd class="mt-0.5">
                        <a href="{{ route('customers.show', $deal->customer) }}" class="text-brand-600 hover:underline">{{ $deal->customer?->name }}</a>
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500">Người phụ trách</dt>
                    <dd class="text-gray-900 mt-0.5">{{ $deal->owner?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Tạm tính</dt>
                    <dd class="text-gray-900 mt-0.5 tabular-nums">{{ number_format($deal->subtotal_amount) }} đ</dd>
                </div>
                <div>
                    <dt class="text-gray-500">VAT</dt>
                    <dd class="text-gray-900 mt-0.5 tabular-nums">{{ number_format($deal->tax_amount) }} đ</dd>
                </div>
                <div>
                    <dt class="text-gray-500 font-semibold">Tổng</dt>
                    <dd class="font-semibold text-lg text-gray-900 mt-0.5 tabular-nums">{{ number_format($deal->total_amount) }} đ</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Dự kiến chốt</dt>
                    <dd class="text-gray-900 mt-0.5">{{ $deal->expected_close_date?->format('d/m/Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Đã chốt</dt>
                    <dd class="text-gray-900 mt-0.5">{{ $deal->actual_close_date?->format('d/m/Y') ?? '—' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-gray-500">Ghi chú</dt>
                    <dd class="text-gray-900 mt-0.5 whitespace-pre-line">{{ $deal->note ?? '—' }}</dd>
                </div>
            </dl>
        </x-card>

        {{-- Items --}}
        <x-card title="Sản phẩm / Gói" class="mt-6">
            @can('create', [\App\Models\DealItem::class, $deal])
                @if ($deal->stage?->isOpen())
                    <x-slot:actions>
                        <x-button variant="secondary" size="sm" data-modal-form="{{ route('deals.items.create', $deal) }}" data-modal-title="Thêm dòng">
                            <x-icon name="plus" class="h-4 w-4" /> Thêm dòng
                        </x-button>
                    </x-slot:actions>
                @endif
            @endcan

            <x-table :headers="['#', 'Sản phẩm', 'SL', 'Đơn giá', 'CK', 'VAT', 'Thành tiền', '']">
                @forelse ($deal->items->sortBy('position') as $item)
                    <tr>
                        <td class="px-4 py-3 text-gray-500">{{ $item->position }}</td>
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ $item->name }}</div>
                            @if ($item->description)
                                <div class="text-xs text-gray-500">{{ $item->description }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $item->quantity }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($item->unit_price) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($item->discount_amount) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $item->tax_rate }}%</td>
                        <td class="px-4 py-3 text-right tabular-nums font-medium">{{ number_format($item->line_total) }}</td>
                        <td class="px-4 py-3 text-right">
                            @can('update', $item)
                                @if ($deal->stage?->isOpen())
                                    <button type="button" data-modal-form="{{ route('deal-items.edit', $item) }}" data-modal-title="Sửa dòng" class="text-xs text-gray-600 hover:text-gray-900">Sửa</button>
                                @endif
                            @endcan
                            @can('delete', $item)
                                @if ($deal->stage?->isOpen())
                                    <form method="POST" action="{{ route('deal-items.destroy', $item) }}" onsubmit="return confirm('Xoá dòng?');" class="inline ml-2">
                                        @csrf @method('DELETE')
                                        <button class="text-xs text-red-600 hover:text-red-800">Xoá</button>
                                    </form>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="8" message="Chưa có sản phẩm." icon="product" />
                @endforelse
            </x-table>
        </x-card>

        {{-- Invoices --}}
        <x-card title="Hoá đơn" class="mt-6">
            @can('create', [\App\Models\Invoice::class, $deal])
                @if (!$deal->stage?->value || $deal->stage->value !== 'closed_lost')
                    <x-slot:actions>
                        <x-button variant="secondary" size="sm" data-modal-form="{{ route('deals.invoices.create', $deal) }}" data-modal-title="Tạo hoá đơn">
                            <x-icon name="plus" class="h-4 w-4" /> Tạo hoá đơn
                        </x-button>
                    </x-slot:actions>
                @endif
            @endcan

            @forelse ($deal->invoices as $inv)
                <div class="flex items-center justify-between border-b border-gray-100 py-2 last:border-0 text-sm">
                    <div>
                        <a href="{{ route('invoices.show', $inv) }}" class="font-mono text-brand-600 hover:underline">{{ $inv->code }}</a>
                        <div class="text-xs text-gray-500 mt-0.5 flex items-center gap-2">
                            <x-badge :variant="$inv->status?->badgeVariant() ?? 'secondary'">{{ $inv->status?->label() }}</x-badge>
                            @if ($inv->issued_at)<span>Phát hành: {{ $inv->issued_at->format('d/m/Y') }}</span>@endif
                            @if ($inv->due_at)<span>· Hạn: {{ $inv->due_at->format('d/m/Y') }}</span>@endif
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="font-semibold tabular-nums">{{ number_format($inv->total_amount) }} đ</div>
                        <div class="text-xs text-gray-500 tabular-nums">Đã thu: {{ number_format($inv->paid_amount) }} đ</div>
                    </div>
                </div>
            @empty
                <x-empty-state message="Chưa có hoá đơn." icon="invoice" />
            @endforelse
        </x-card>

        <div class="mt-6">
            <a href="{{ route('deals.index') }}" class="inline-flex items-center gap-1 text-sm text-brand-600 hover:underline">
                <x-icon name="arrow-left" class="h-4 w-4" /> Quay lại danh sách
            </a>
        </div>
    </div>
</x-layouts.app>
