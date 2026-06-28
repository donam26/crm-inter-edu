<?php

namespace App\Services;

use App\Enums\DealStage;
use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\Deal;
use App\Models\DealItem;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Scopes\BranchScope;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class RevenueReportService
{
    /**
     * Tổng hợp báo cáo doanh thu trong khoảng [from, to].
     *
     * Mọi truy vấn đều đi qua BranchScope (super-admin tự bypass), nên
     * không cần check role thủ công ở đây.
     *
     * @return array{
     *   from: string, to: string,
     *   pipeline_value: int,
     *   open_deals_count: int,
     *   won_value: int,
     *   won_deals_count: int,
     *   invoiced_amount: int,
     *   collected_amount: int,
     *   outstanding_amount: int,
     *   overdue_amount: int,
     *   monthly: Collection<int, array{label:string,won:int,collected:int}>,
     *   top_products: Collection<int, array{name:string,quantity:int,revenue:int}>,
     *   by_branch?: Collection<int, array{branch:?Branch,won:int,collected:int}>,
     *   by_owner: Collection<int, array{user:?User,won:int,collected:int}>,
     * }
     */
    public function summary(?string $from = null, ?string $to = null, User $viewer = null): array
    {
        $start = CarbonImmutable::parse($from ?: now()->startOfYear()->toDateString());
        $end = CarbonImmutable::parse($to ?: now()->endOfYear()->toDateString());

        $pipelineValue = (int) Deal::query()
            ->whereNotIn('stage', [
                DealStage::ClosedWon->value,
                DealStage::ClosedLost->value,
            ])
            ->sum('total_amount');

        $openDealsCount = (int) Deal::query()
            ->whereNotIn('stage', [
                DealStage::ClosedWon->value,
                DealStage::ClosedLost->value,
            ])
            ->count();

        $wonQuery = Deal::query()
            ->where('stage', DealStage::ClosedWon->value)
            ->whereBetween('actual_close_date', [$start->toDateString(), $end->toDateString()]);

        $wonValue = (int) (clone $wonQuery)->sum('total_amount');
        $wonCount = (int) (clone $wonQuery)->count();

        $invoicedAmount = (int) Invoice::query()
            ->whereIn('status', [
                InvoiceStatus::Issued->value,
                InvoiceStatus::PartiallyPaid->value,
                InvoiceStatus::Paid->value,
                InvoiceStatus::Overdue->value,
            ])
            ->whereBetween('issued_at', [$start->toDateString(), $end->toDateString()])
            ->sum('total_amount');

        $collectedAmount = (int) Payment::query()
            ->whereNotNull('confirmed_at')
            ->whereBetween('paid_at', [$start->toDateString(), $end->toDateString()])
            ->sum('amount');

        $outstandingAmount = (int) Invoice::query()
            ->outstanding()
            ->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) as bal')
            ->value('bal');

        $overdueAmount = (int) Invoice::query()
            ->where('status', InvoiceStatus::Overdue->value)
            ->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) as bal')
            ->value('bal');

        return [
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'pipeline_value' => $pipelineValue,
            'open_deals_count' => $openDealsCount,
            'won_value' => $wonValue,
            'won_deals_count' => $wonCount,
            'invoiced_amount' => $invoicedAmount,
            'collected_amount' => $collectedAmount,
            'outstanding_amount' => $outstandingAmount,
            'overdue_amount' => $overdueAmount,
            'monthly' => $this->monthlyBreakdown($start, $end),
            'top_products' => $this->topProducts($start, $end),
            'by_branch' => $viewer?->hasRole('super-admin')
                ? $this->byBranch($start, $end)
                : null,
            'by_owner' => $this->byOwner($start, $end),
        ];
    }

    /** @return Collection<int, array{label:string,won:int,collected:int}> */
    private function monthlyBreakdown(CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        $rows = collect();
        $cursor = $start->startOfMonth();
        $stop = $end->startOfMonth();

        while ($cursor <= $stop) {
            $monthStart = $cursor->toDateString();
            $monthEnd = $cursor->endOfMonth()->toDateString();

            $won = (int) Deal::query()
                ->where('stage', DealStage::ClosedWon->value)
                ->whereBetween('actual_close_date', [$monthStart, $monthEnd])
                ->sum('total_amount');

            $collected = (int) Payment::query()
                ->whereNotNull('confirmed_at')
                ->whereBetween('paid_at', [$monthStart, $monthEnd])
                ->sum('amount');

            $rows->push([
                'label' => $cursor->format('m/Y'),
                'won' => $won,
                'collected' => $collected,
            ]);

            $cursor = $cursor->addMonth();
        }

        return $rows;
    }

    /** @return Collection<int, array{name:string,quantity:int,revenue:int}> */
    private function topProducts(CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return DealItem::query()
            ->join('deals', 'deals.id', '=', 'deal_items.deal_id')
            ->where('deals.stage', DealStage::ClosedWon->value)
            ->whereBetween('deals.actual_close_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('deal_items.name as name, SUM(deal_items.quantity) as qty, SUM(deal_items.line_total) as revenue')
            ->groupBy('deal_items.name')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'name' => $r->name,
                'quantity' => (int) $r->qty,
                'revenue' => (int) $r->revenue,
            ]);
    }

    /** @return Collection<int, array{branch:?Branch,won:int,collected:int}> */
    private function byBranch(CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        $won = Deal::withoutGlobalScope(BranchScope::class)
            ->where('stage', DealStage::ClosedWon->value)
            ->whereBetween('actual_close_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('branch_id, SUM(total_amount) as v')
            ->groupBy('branch_id')
            ->pluck('v', 'branch_id');

        $collected = Payment::withoutGlobalScope(BranchScope::class)
            ->whereNotNull('confirmed_at')
            ->whereBetween('paid_at', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('branch_id, SUM(amount) as v')
            ->groupBy('branch_id')
            ->pluck('v', 'branch_id');

        $branchIds = $won->keys()->merge($collected->keys())->unique();
        $branches = Branch::whereIn('id', $branchIds)->get()->keyBy('id');

        return $branchIds->map(fn ($id) => [
            'branch' => $branches[$id] ?? null,
            'won' => (int) ($won[$id] ?? 0),
            'collected' => (int) ($collected[$id] ?? 0),
        ])->values();
    }

    /** @return Collection<int, array{user:?User,won:int,collected:int}> */
    private function byOwner(CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        $won = Deal::query()
            ->where('stage', DealStage::ClosedWon->value)
            ->whereBetween('actual_close_date', [$start->toDateString(), $end->toDateString()])
            ->whereNotNull('owner_user_id')
            ->selectRaw('owner_user_id, SUM(total_amount) as v')
            ->groupBy('owner_user_id')
            ->pluck('v', 'owner_user_id');

        $userIds = $won->keys();
        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        return $userIds->map(fn ($id) => [
            'user' => $users[$id] ?? null,
            'won' => (int) ($won[$id] ?? 0),
            'collected' => 0,
        ])->values();
    }
}
