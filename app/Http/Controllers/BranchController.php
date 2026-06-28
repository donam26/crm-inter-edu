<?php

namespace App\Http\Controllers;

use App\Exceptions\BranchHasDependenciesException;
use App\Http\Requests\Branch\StoreBranchRequest;
use App\Http\Requests\Branch\UpdateBranchRequest;
use App\Models\Branch;
use App\Services\BranchService;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function __construct(private BranchService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Branch::class);
        $branches = $this->service->list($request->only(['is_active', 'q']));

        return view('branches.index', compact('branches'));
    }

    public function create()
    {
        $this->authorize('create', Branch::class);

        if (! $this->wantsModalForm()) {
            return redirect()->route('branches.index');
        }

        return view('branches.create');
    }

    public function store(StoreBranchRequest $request)
    {
        $branch = $this->service->create($request->validated());

        return $this->modalRedirect(route('branches.show', $branch), 'Đã tạo branch.');
    }

    public function show(Branch $branch)
    {
        $this->authorize('view', $branch);

        return view('branches.show', compact('branch'));
    }

    public function edit(Branch $branch)
    {
        $this->authorize('update', $branch);

        if (! $this->wantsModalForm()) {
            return redirect()->route('branches.show', $branch);
        }

        return view('branches.edit', compact('branch'));
    }

    public function update(UpdateBranchRequest $request, Branch $branch)
    {
        $this->service->update($branch, $request->validated());

        return $this->modalRedirect(route('branches.show', $branch), 'Đã cập nhật branch.');
    }

    public function destroy(Branch $branch)
    {
        $this->authorize('delete', $branch);

        try {
            $this->service->delete($branch);
        } catch (BranchHasDependenciesException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('branches.index')
            ->with('success', 'Đã xóa branch.');
    }
}
