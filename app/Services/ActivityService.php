<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Customer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ActivityService
{
    public function create(Customer $customer, array $data): Activity
    {
        return DB::transaction(function () use ($customer, $data) {
            // Service-layer injection: customer_id, branch_id, user_id luôn lấy từ
            // context (Customer cha + auth user), bỏ qua mọi giá trị client gửi.
            $data['customer_id'] = $customer->id;
            $data['branch_id'] = $customer->branch_id;
            $data['user_id'] = Auth::id();

            return Activity::create($data);
        });
    }

    public function update(Activity $activity, array $data): Activity
    {
        return DB::transaction(function () use ($activity, $data) {
            // Chặn override các field auto-set qua input người dùng.
            unset($data['customer_id'], $data['branch_id'], $data['user_id']);

            $activity->update($data);

            return $activity->refresh();
        });
    }

    public function delete(Activity $activity): void
    {
        DB::transaction(fn () => $activity->delete());
    }
}
