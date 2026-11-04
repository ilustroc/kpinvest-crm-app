<?php

namespace App\Actions\Cna;

use App\Models\CnaSolicitud;
use App\Models\User;
use App\Services\Cna\CnaDocumentService;
use Illuminate\Support\Facades\Gate;

class DownloadCnaDocumentAction
{
    public function __construct(private readonly CnaDocumentService $documents)
    {
    }

    public function execute(User $user, int $id, string $primary)
    {
        $cna = CnaSolicitud::findOrFail($id);

        Gate::forUser($user)->authorize('download-cna-document', $cna);

        if (strtolower(trim((string) $cna->workflow_estado)) !== 'aprobada') {
            abort(403, 'Solo se pueden descargar documentos para CNA aprobadas.');
        }

        return $this->documents->download($cna, $primary);
    }
}
