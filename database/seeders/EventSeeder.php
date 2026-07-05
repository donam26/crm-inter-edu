<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        // Bypass BranchScope vì seeder chạy ngoài auth context. Mọi user thuộc
        // branch (branch_id != null) đều là user nội bộ; super-admin (null) bị loại.
        $users = User::query()
            ->whereNotNull('branch_id')
            ->get();

        if ($users->isEmpty()) {
            return;
        }

        // Mỗi user nội bộ có khoảng 2-4 event sắp tới.
        foreach ($users as $u) {
            $count = random_int(2, 4);

            for ($i = 0; $i < $count; $i++) {
                $event = Event::factory()->forBranchUser($u)->create();

                // Mời thêm 1-2 user cùng branch.
                $invitees = User::query()
                    ->where('branch_id', $u->branch_id)
                    ->where('id', '!=', $u->id)
                    ->inRandomOrder()
                    ->limit(random_int(0, 2))
                    ->pluck('id');

                foreach ($invitees as $inviteeId) {
                    $event->attendees()->attach($inviteeId, [
                        'response' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // Thêm vài event gắn với Customer.
        Customer::withoutGlobalScopes()->inRandomOrder()->limit(5)->get()
            ->each(fn (Customer $customer) => Event::factory()->forLead($customer)->create());
    }
}
