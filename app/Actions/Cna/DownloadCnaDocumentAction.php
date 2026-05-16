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

        return $this->documents->download($cna, $primary);
    }
}
