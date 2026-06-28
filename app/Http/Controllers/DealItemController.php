<?php

namespace App\Http\Controllers;

use App\Exceptions\RevenueWorkflowException;
use App\Http\Requests\Deal\StoreDealItemRequest;
use App\Http\Requests\Deal\UpdateDealItemRequest;
use App\Models\Deal;
use App\Models\DealItem;
use App\Models\Product;
use App\Services\DealService;
use Illuminate\Validation\ValidationException;

class DealItemController extends Controller
{
    public function __construct(private DealService $service) {}

    public function create(Deal $deal)
    {
        $this->authorize('create', [DealItem::class, $deal]);

        if (! $this->wantsModalForm()) {
            return redirect()->route('deals.show', $deal);
        }

        return view('deal_items.create', [
            'deal' => $deal,
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreDealItemRequest $request, Deal $deal)
    {
        try {
            $this->service->addItem($deal, $request->validated());
        } catch (ValidationException $e) {
            return $this->validationResponse($e);
        } catch (RevenueWorkflowException $e) {
            return back()->with('error', $e->getMessage());
        }

        return $this->modalRedirect(route('deals.show', $deal), 'Đã thêm sản phẩm vào deal.');
    }

    public function edit(DealItem $item)
    {
        $this->authorize('update', $item);

        if (! $this->wantsModalForm()) {
            return redirect()->route('deals.show', $item->deal);
        }

        return view('deal_items.edit', [
            'item' => $item,
            'deal' => $item->deal,
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateDealItemRequest $request, DealItem $item)
    {
        try {
            $this->service->updateItem($item, $request->validated());
        } catch (RevenueWorkflowException $e) {
            return back()->with('error', $e->getMessage());
        }

        return $this->modalRedirect(route('deals.show', $item->deal_id), 'Đã cập nhật dòng sản phẩm.');
    }

    public function destroy(DealItem $item)
    {
        $this->authorize('delete', $item);
        $dealId = $item->deal_id;

        try {
            $this->service->removeItem($item);
        } catch (RevenueWorkflowException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('deals.show', $dealId)
            ->with('success', 'Đã xoá dòng sản phẩm.');
    }
}
