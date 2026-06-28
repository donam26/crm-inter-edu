<?php

namespace Database\Seeders;

use App\Enums\DealStage;
use App\Enums\InvoiceStatus;
use App\Models\Deal;
use App\Models\DealItem;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class DealSeeder extends Seeder
{
    /**
     * Sinh dữ liệu mẫu cho module Revenue.
     *
     * Mỗi lead lấy ngẫu nhiên ~70% sẽ có deal (1 lead = 1 deal). Mỗi deal có
     * 1-3 items lấy từ catalog Product cùng branch. Một phần deal chuyển
     * Won + sinh hoá đơn + thanh toán đã xác nhận để dashboard có dữ liệu.
     */
    public function run(): void
    {
        $leads = Lead::withoutGlobalScopes()->inRandomOrder()->take(40)->get();

        foreach ($leads as $lead) {
            if (random_int(1, 10) <= 3) {
                continue; // 30% lead không có deal
            }

            $branchId = $lead->branch_id;

            $products = Product::withoutGlobalScopes()
                ->where('branch_id', $branchId)
                ->where('is_active', true)
                ->get();

            if ($products->isEmpty()) {
                continue;
            }

            $owner = User::query()
                ->where('branch_id', $branchId)
                ->inRandomOrder()
                ->first();

            $stage = $this->randomStage();

            /** @var Deal $deal */
            $deal = Deal::factory()->forLead($lead)->create([
                'owner_user_id' => $owner?->id ?? $lead->assigned_user_id,
                'created_by' => $owner?->id ?? User::query()->whereNull('branch_id')->value('id'),
                'stage' => $stage->value,
                'actual_close_date' => $stage->isClosed()
                    ? now()->subDays(random_int(0, 30))->toDateString()
                    : null,
            ]);

            // Items
            $itemCount = random_int(1, min(3, $products->count()));
            $picked = $products->random($itemCount);
            $position = 1;
            foreach ($picked as $product) {
                DealItem::factory()
                    ->forDeal($deal)
                    ->forProduct($product)
                    ->create(['position' => $position++]);
            }
            $this->recompute($deal);

            // Won deals → sinh hoá đơn + thanh toán
            if ($stage === DealStage::ClosedWon) {
                $invoice = Invoice::factory()->forDeal($deal->fresh())->create([
                    'created_by' => $deal->created_by,
                    'issued_by' => $owner?->id,
                    'status' => InvoiceStatus::Issued->value,
                    'issued_at' => $deal->actual_close_date,
                    'due_at' => now()->addDays(15)->toDateString(),
                ]);

                // 60% trả đủ, 30% trả 1 phần, 10% còn outstanding
                $roll = random_int(1, 10);
                if ($roll <= 6) {
                    Payment::factory()->forInvoice($invoice)->confirmed()->create([
                        'created_by' => $deal->created_by,
                        'amount' => $invoice->total_amount,
                    ]);
                    $invoice->update([
                        'paid_amount' => $invoice->total_amount,
                        'status' => InvoiceStatus::Paid->value,
                    ]);
                } elseif ($roll <= 9) {
                    $half = (int) round($invoice->total_amount * 0.5);
                    Payment::factory()->forInvoice($invoice)->confirmed()->create([
                        'created_by' => $deal->created_by,
                        'amount' => $half,
                    ]);
                    $invoice->update([
                        'paid_amount' => $half,
                        'status' => InvoiceStatus::PartiallyPaid->value,
                    ]);
                }
            }
        }
    }

    private function randomStage(): DealStage
    {
        $weights = [
            DealStage::Lead->value => 2,
            DealStage::Proposal->value => 3,
            DealStage::Negotiation->value => 3,
            DealStage::ClosedWon->value => 4,
            DealStage::ClosedLost->value => 2,
        ];

        $bag = [];
        foreach ($weights as $value => $weight) {
            for ($i = 0; $i < $weight; $i++) {
                $bag[] = $value;
            }
        }

        return DealStage::from($bag[array_rand($bag)]);
    }

    private function recompute(Deal $deal): void
    {
        $items = $deal->items()->withoutGlobalScopes()->get();
        $deal->update([
            'subtotal_amount' => (int) $items->sum('line_subtotal'),
            'tax_amount' => (int) $items->sum('line_tax_amount'),
            'total_amount' => (int) $items->sum('line_total'),
        ]);
    }
}
