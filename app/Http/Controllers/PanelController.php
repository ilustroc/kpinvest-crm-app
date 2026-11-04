<?php

namespace App\Http\Controllers;

use App\Services\Panel\PanelDashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PanelController extends Controller
{
    public function __construct(
        private readonly PanelDashboardService $panelService
    ) {}

    public function index(Request $request)
    {
        $user = Auth::user();
        $mes = $request->input('mes');

        $data = $this->panelService->build($user, $mes);

        return view('panel.resumen', $data);
    }
}
