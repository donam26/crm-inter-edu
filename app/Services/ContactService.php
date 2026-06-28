<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\Scopes\BranchScope;
use Illuminate\Support\Facades\DB;

class ContactService
{
    public function create(Lead $lead, array $data): Contact
    {
        return DB::transaction(function () use ($lead, $data) {
            // Service-layer injection: lead_id và branch_id luôn lấy từ Lead cha,
            // bỏ qua mọi giá trị mà client gửi lên.
            $data['lead_id'] = $lead->id;
            $data['branch_id'] = $lead->branch_id;

            $isPrimary = (bool) ($data['is_primary'] ?? false);
            // Tạm tạo với is_primary=false, sau đó setPrimary nếu cần để đảm bảo
            // chỉ có duy nhất 1 primary trên mỗi lead.
            $data['is_primary'] = false;

            /** @var Contact $contact */
            $contact = Contact::create($data);

            if ($isPrimary) {
                $this->setPrimary($contact);
            }

            return $contact->refresh();
        });
    }

    public function update(Contact $contact, array $data): Contact
    {
        return DB::transaction(function () use ($contact, $data) {
            // Chặn override lead_id / branch_id qua input người dùng.
            unset($data['lead_id'], $data['branch_id']);

            $isPrimary = array_key_exists('is_primary', $data)
                ? (bool) $data['is_primary']
                : $contact->is_primary;

            // Bỏ key is_primary khỏi update tổng thể; sẽ xử lý qua setPrimary
            // hoặc gán trực tiếp false.
            unset($data['is_primary']);

            $contact->update($data);

            if ($isPrimary) {
                $this->setPrimary($contact);
            } else {
                $contact->update(['is_primary' => false]);
            }

            return $contact->refresh();
        });
    }

    public function setPrimary(Contact $contact): void
    {
        DB::transaction(function () use ($contact) {
            // Bypass BranchScope để đảm bảo reset toàn bộ contact của lead
            // bất kể context người dùng (super-admin có branch_id=null,
            // hoặc tác vụ chạy ngoài request scope).
            Contact::withoutGlobalScope(BranchScope::class)
                ->where('lead_id', $contact->lead_id)
                ->where('id', '!=', $contact->id)
                ->update(['is_primary' => false]);

            $contact->update(['is_primary' => true]);
        });
    }

    public function delete(Contact $contact): void
    {
        DB::transaction(fn () => $contact->delete());
    }
}
