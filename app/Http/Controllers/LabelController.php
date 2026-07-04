<?php

namespace App\Http\Controllers;

use App\Http\Requests\Label\StoreLabelRequest;
use App\Http\Requests\Label\UpdateLabelRequest;
use App\Models\Branch;
use App\Models\Label;
use App\Services\LabelService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LabelController extends Controller
{
    /** Màu badge cho phép (đồng bộ hệ badgeVariant của enum). */
    public const COLORS = ['secondary', 'primary', 'success', 'warning', 'danger'];

    public function __construct(private LabelService $service) {}

    public function index()
    {
        $this->authorize('viewAny', Label::class);

        return view('labels.index', [
            'labels' => Label::with('branch')->orderBy('name')->paginate(30),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', Label::class);

        if (! $this->wantsModalForm()) {
            return redirect()->route('labels.index');
        }

        return view('labels.create', [
            'colors' => self::COLORS,
            'branches' => $request->user()->hasRole('super-admin')
                ? Branch::orderBy('name')->get()
                : collect(),
        ]);
    }

    public function store(StoreLabelRequest $request)
    {
        try {
            $this->service->create($request->validated());
        } catch (ValidationException $e) {
            return $this->validationResponse($e);
        }

        return $this->modalRedirect(route('labels.index'), 'Đã tạo nhãn.');
    }

    public function edit(Label $label)
    {
        $this->authorize('update', $label);

        if (! $this->wantsModalForm()) {
            return redirect()->route('labels.index');
        }

        return view('labels.edit', [
            'label' => $label,
            'colors' => self::COLORS,
        ]);
    }

    public function update(UpdateLabelRequest $request, Label $label)
    {
        try {
            $this->service->update($label, $request->validated());
        } catch (ValidationException $e) {
            return $this->validationResponse($e);
        }

        return $this->modalRedirect(route('labels.index'), 'Đã cập nhật nhãn.');
    }

    public function destroy(Label $label)
    {
        $this->authorize('delete', $label);
        $this->service->delete($label);

        return redirect()->route('labels.index')->with('success', 'Đã xoá nhãn.');
    }
}
