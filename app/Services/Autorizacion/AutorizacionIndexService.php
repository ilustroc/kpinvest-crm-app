<?php

namespace App\Services\Autorizacion;

use App\Services\Cna\CnaQueryService;
use App\Services\Promesa\PromesaQueryService;
use App\Support\Authorization\Roles;
use App\ViewModels\Autorizacion\AutorizacionIndexViewModel;
use Illuminate\Http\Request;

class AutorizacionIndexService
{
    public function __construct(
        private readonly PromesaQueryService $promesas,
        private readonly CnaQueryService $cnas,
    ) {
    }

    public function fromRequest(Request $request): AutorizacionIndexViewModel
    {
        $user = $request->user();
        $query = trim((string) ($request->input('q') ?? ''));
        $status = $request->input('status');
        $status = is_string($status) && $status !== '' ? $status : null;

        $promesaRows = $this->promesas->authorizationRows($user, $query, $status);
        $cnaData = $this->cnas->authorizationData($user, $query, $status);

        return new AutorizacionIndexViewModel(
            rows: $promesaRows,
            cnaRows: $cnaData['cnaRows'],
            prodByOp: $cnaData['prodByOp'],
            q: $query,
            status: $status,
            isSupervisor: Roles::normalize($user->role) === Roles::SUPERVISOR,
        );
    }
}
