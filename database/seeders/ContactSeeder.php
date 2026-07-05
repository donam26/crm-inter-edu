<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\Customer;
use Illuminate\Database\Seeder;

class ContactSeeder extends Seeder
{
    public function run(): void
    {
        // Bypass BranchScope vì seeder chạy ngoài auth context.
        Customer::withoutGlobalScopes()->get()->each(function (Customer $customer) {
            $count = random_int(2, 4);

            $contacts = Contact::factory()
                ->count($count)
                ->forLead($customer)
                ->create();

            // Đảm bảo mỗi customer có đúng 1 primary.
            $contacts->first()->update(['is_primary' => true]);
        });
    }
}
