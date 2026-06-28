<?php

namespace App\Services;

use App\Enums\DealStage;
use App\Enums\InvoiceStatus;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Scopes\BranchScope;
use App\Models\Task;
use App\Models\User;

class DashboardService
{
    /**
     * Build dashboard statistics scoped per role.
     *
     * @return array{
     *   total_leads: int,
     *   leads_by_status: array<string, int>,
     *   total_contacts: int,
     *   activities_last_7_days: int,
     *   leads_by_branch?: array<int, int>,
     *   my_overdue_tasks: \Illuminate\Database\Eloquent\Collection,
     *   my_upcoming_tasks: \Illuminate\Database\Eloquent\Collection,
     *   my_upcoming_events: \Illuminate\Database\Eloquent\Collection,
     *   pipeline_value: int,
     *   open_deals_count: int,
     *   won_revenue_this_month: int,
     *   outstanding_amount: int,
     *   overdue_invoices_count: int,
     *   collected_this_month: int,
     *   revenue_by_branch?: array<int, int>,
     * }
     */
    public function getStatsForUser(User $user): array
    {
        $leadQuery = Lead::query();
        $contactQuery = Contact::query();
        $activityQuery = Activity::query();

        $dealQuery = Deal::query();
        $invoiceQuery = Invoice::query();
        $paymentQuery = Payment::query();

        if ($user->hasRole('sales')) {
            $leadQuery->where('assigned_user_id', $user->id);

            $assignedLeadIds = (clone $leadQuery)->select('id');
            $contactQuery->whereIn('lead_id', $assignedLeadIds);
            $activityQuery->whereIn('lead_id', $assignedLeadIds);

            // Sales chỉ thấy doanh thu của deal mình owner.
            $dealQuery->where('owner_user_id', $user->id);
            $myDealIds = (clone $dealQuery)->select('id');
            $invoiceQuery->whereIn('deal_id', $myDealIds);
            $myInvoiceIds = (clone $invoiceQuery)->select('id');
            $paymentQuery->whereIn('invoice_id', $myInvoiceIds);
        }

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $stats = [
            'total_leads' => (clone $leadQuery)->count(),

            'leads_by_status' => (clone $leadQuery)
                ->selectRaw('status, COUNT(*) as c')
                ->groupBy('status')
                ->pluck('c', 'status')
                ->map(fn ($c) => (int) $c)
                ->toArray(),

            'total_contacts' => (clone $contactQuery)->count(),

            'activities_last_7_days' => (clone $activityQuery)
                ->where('happened_at', '>=', now()->subDays(7))
                ->count(),

            'my_overdue_tasks' => Task::query()
                ->with('lead')
                ->where('assigned_user_id', $user->id)
                ->overdue()
                ->orderBy('due_at')
                ->limit(5)
                ->get(),

            'my_upcoming_tasks' => Task::query()
                ->with('lead')
                ->where('assigned_user_id', $user->id)
                ->upcoming(24)
                ->orderBy('due_at')
                ->limit(5)
                ->get(),

            'my_upcoming_events' => Event::query()
                ->with(['organizer', 'lead'])
                ->upcoming(48)
                ->where(function ($q) use ($user) {
                    $q->where('organizer_user_id', $user->id)
                        ->orWhereHas('attendees', fn ($q2) => $q2->where('users.id', $user->id));
                })
                ->orderBy('starts_at')
                ->limit(5)
                ->get(),

            // ───────── Revenue KPIs ─────────

            'pipeline_value' => (int) (clone $dealQuery)
                ->whereNotIn('stage', [
                    DealStage::ClosedWon->value,
                    DealStage::ClosedLost->value,
                ])
                ->sum('total_amount'),

            'open_deals_count' => (clone $dealQuery)
                ->whereNotIn('stage', [
                    DealStage::ClosedWon->value,
                    DealStage::ClosedLost->value,
                ])
                ->count(),

            'won_revenue_this_month' => (int) (clone $dealQuery)
                ->where('stage', DealStage::ClosedWon->value)
                ->whereBetween('actual_close_date', [$monthStart, $monthEnd])
                ->sum('total_amount'),

            'outstanding_amount' => (int) (clone $invoiceQuery)
                ->whereIn('status', [
                    InvoiceStatus::Issued->value,
                    InvoiceStatus::PartiallyPaid->value,
                    InvoiceStatus::Overdue->value,
                ])
                ->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) as bal')
                ->value('bal'),

            'overdue_invoices_count' => (clone $invoiceQuery)
                ->where('status', InvoiceStatus::Overdue->value)
                ->count(),

            'collected_this_month' => (int) (clone $paymentQuery)
                ->whereNotNull('confirmed_at')
                ->whereBetween('paid_at', [$monthStart, $monthEnd])
                ->sum('amount'),
        ];

        if ($user->hasRole('super-admin')) {
            $stats['leads_by_branch'] = Lead::withoutGlobalScope(BranchScope::class)
                ->selectRaw('branch_id, COUNT(*) as c')
                ->groupBy('branch_id')
                ->pluck('c', 'branch_id')
                ->mapWithKeys(fn ($c, $branchId) => [(int) $branchId => (int) $c])
                ->toArray();

            $stats['revenue_by_branch'] = Deal::withoutGlobalScope(BranchScope::class)
                ->where('stage', DealStage::ClosedWon->value)
                ->whereBetween('actual_close_date', [$monthStart, $monthEnd])
                ->selectRaw('branch_id, SUM(total_amount) as v')
                ->groupBy('branch_id')
                ->pluck('v', 'branch_id')
                ->mapWithKeys(fn ($v, $branchId) => [(int) $branchId => (int) $v])
                ->toArray();
        }

        return $stats;
    }
}
