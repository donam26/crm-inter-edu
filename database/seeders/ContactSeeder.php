<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\Lead;
use Illuminate\Database\Seeder;

class ContactSeeder extends Seeder
{
    public function run(): void
    {
        // Bypass BranchScope vì seeder chạy ngoài auth context.
        Lead::withoutGlobalScopes()->get()->each(function (Lead $lead) {
            $count = random_int(2, 4);

            $contacts = Contact::factory()
                ->count($count)
                ->forLead($lead)
                ->create();

            // Đảm bảo mỗi lead có đúng 1 primary.
            $contacts->first()->update(['is_primary' => true]);
        });
    }
}
