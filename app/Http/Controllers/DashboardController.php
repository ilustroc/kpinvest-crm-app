<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\DashboardStatsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardStatsService $statsService
    ) {}

    public function index(Request $request)
    {
        $data = $this->statsService->build($request, Auth::user());

        return view('dashboard.index', $data);
    }
}