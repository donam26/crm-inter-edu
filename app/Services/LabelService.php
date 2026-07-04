<?php

namespace App\Services;

use App\Models\Label;
use App\Models\Task;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LabelService
{
    /**
     * Tạo nhãn. branch_id: super-admin chỉ định qua input; user thường luôn lấy
     * theo branch của mình (không cho vượt phạm vi).
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Label
    {
        return DB::transaction(function () use ($data) {
            $user = Auth::user();

            $branchId = $user?->hasRole('super-admin')
                ? ($data['branch_id'] ?? null)
                : $user?->branch_id;

            if ($branchId === null) {
                throw ValidationException::withMessages([
                    'branch_id' => 'Nhãn phải thuộc một chi nhánh.',
                ]);
            }

            $data['branch_id'] = $branchId;

            return Label::create($data);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Label $label, array $data): Label
    {
        return DB::transaction(function () use ($label, $data) {
            unset($data['branch_id']); // không cho đổi branch qua update
            $label->update($data);

            return $label->fresh();
        });
    }

    public function delete(Label $label): void
    {
        DB::transaction(fn () => $label->delete());
    }

    /**
     * Gán tập nhãn cho task. Chỉ chấp nhận nhãn CÙNG branch với task (guard
     * chống gán chéo chi nhánh — where branch_id là chốt chặn thật cho cả
     * super-admin vốn bypass BranchScope).
     *
     * @param  array<int, int|string>  $labelIds
     */
    public function sync(Task $task, array $labelIds): void
    {
        DB::transaction(function () use ($task, $labelIds) {
            $valid = Label::withoutGlobalScopes()
                ->whereIn('id', $labelIds)
                ->where('branch_id', $task->branch_id)
                ->pluck('id')
                ->all();

            $task->labels()->sync($valid);
        });
    }
}
