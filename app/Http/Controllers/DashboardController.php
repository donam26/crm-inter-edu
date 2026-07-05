<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Services\DashboardService;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $service) {}

    public function index()
    {
        $user = Auth::user();
        $stats = $this->service->getStatsForUser($user);

        // Super-admin xem `customers_by_branch` cần tên branch để hiển thị; các
        // role khác không cần branches dropdown trên dashboard.
        $branches = $user->hasRole('super-admin')
            ? Branch::orderBy('name')->get()->keyBy('id')
            : collect();

        return view('dashboard.index', compact('stats', 'branches'));
    }
}
