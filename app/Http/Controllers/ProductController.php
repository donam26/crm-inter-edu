<?php

namespace App\Http\Controllers;

use App\Exceptions\RevenueWorkflowException;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Models\Branch;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private ProductService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Product::class);

        $filters = $request->only(['q', 'is_active', 'branch_id']);

        if (! $request->user()?->hasRole('super-admin')) {
            unset($filters['branch_id']);
        }

        return view('products.index', [
            'products' => $this->service->list($filters),
            'branches' => $request->user()?->hasRole('super-admin')
                ? Branch::orderBy('name')->get()
                : collect(),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', Product::class);

        if (! $this->wantsModalForm()) {
            return redirect()->route('products.index');
        }

        return view('products.create', [
            'branches' => $request->user()?->hasRole('super-admin')
                ? Branch::orderBy('name')->get()
                : collect(),
        ]);
    }

    public function store(StoreProductRequest $request)
    {
        try {
            $product = $this->service->create($request->validated());
        } catch (RevenueWorkflowException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return $this->modalRedirect(route('products.show', $product), 'Đã tạo sản phẩm.');
    }

    public function show(Product $product)
    {
        $this->authorize('view', $product);

        return view('products.show', compact('product'));
    }

    public function edit(Product $product)
    {
        $this->authorize('update', $product);

        if (! $this->wantsModalForm()) {
            return redirect()->route('products.show', $product);
        }

        return view('products.edit', compact('product'));
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $this->service->update($product, $request->validated());

        return $this->modalRedirect(route('products.show', $product), 'Đã cập nhật sản phẩm.');
    }

    public function destroy(Product $product)
    {
        $this->authorize('delete', $product);

        try {
            $this->service->delete($product);
        } catch (RevenueWorkflowException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('products.index')
            ->with('success', 'Đã xoá sản phẩm.');
    }
}
