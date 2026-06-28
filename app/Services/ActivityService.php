<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Lead;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ActivityService
{
    public function create(Lead $lead, array $data): Activity
    {
        return DB::transaction(function () use ($lead, $data) {
            // Service-layer injection: lead_id, branch_id, user_id luôn lấy từ
            // context (Lead cha + auth user), bỏ qua mọi giá trị client gửi.
            $data['lead_id'] = $lead->id;
            $data['branch_id'] = $lead->branch_id;
            $data['user_id'] = Auth::id();

            return Activity::create($data);
        });
    }

    public function update(Activity $activity, array $data): Activity
    {
        return DB::transaction(function () use ($activity, $data) {
            // Chặn override các field auto-set qua input người dùng.
            unset($data['lead_id'], $data['branch_id'], $data['user_id']);

            $activity->update($data);

            return $activity->refresh();
        });
    }

    public function delete(Activity $activity): void
    {
        DB::transaction(fn () => $activity->delete());
    }
}
