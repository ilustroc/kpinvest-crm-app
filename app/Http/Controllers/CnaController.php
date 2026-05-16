<?php

namespace App\Http\Controllers;

use App\Actions\Cna\ApproveCnaAction;
use App\Actions\Cna\CreateCnaAction;
use App\Actions\Cna\DownloadCnaDocumentAction;
use App\Actions\Cna\PreapproveCnaAction;
use App\Actions\Cna\RejectCnaAction;
use App\Models\CnaSolicitud;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CnaController extends Controller
{
    public function __construct(
        private readonly CreateCnaAction $createCna,
        private readonly PreapproveCnaAction $preapproveCna,
        private readonly ApproveCnaAction $approveCna,
        private readonly RejectCnaAction $rejectCna,
        private readonly DownloadCnaDocumentAction $downloadDocument,
    ) {
    }

    public function store(Request $request, string $dni)
    {
        try {
            $data = $request->validate([
                'titular' => ['nullable', 'string', 'max:150'],
                'nota' => ['nullable', 'string', 'max:1000'],
                'observacion' => ['nullable', 'string', 'max:1000'],
                'fecha_pago_realizado' => ['required', 'date'],
                'monto_pagado' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
                'operaciones' => ['required', 'array', 'min:1'],
                'operaciones.*' => ['string', 'max:50'],
                'cuenta' => ['nullable', 'string', 'max:50'],
            ], [], [
                'fecha_pago_realizado' => 'fecha de pago realizado',
                'monto_pagado' => 'monto pagado',
                'operaciones' => 'operaciones',
            ]);

            [, $message] = $this->createCna->execute($request->user(), $dni, $data);

            return redirect()->route('clientes.show', $dni)->with('ok', $message);
        } catch (DomainException $e) {
            return back()->withErrors($e->getMessage())->withInput();
        } catch (\Throwable $e) {
            if ($e instanceof AuthorizationException) {
                throw $e;
            }

            Log::error('CNA store error', [
                'dni' => $dni,
                'msg' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return redirect()->route('clientes.show', $dni)
                ->withErrors('No se pudo guardar la CNA: '.$e->getMessage())
                ->withInput();
        }
    }

    public function preaprobar(Request $request, CnaSolicitud $cna)
    {
        return $this->runWorkflow(function () use ($request, $cna) {
            $this->preapproveCna->execute($request->user(), $cna, $request->input('nota_estado'));
        }, 'CNA pre-aprobada.');
    }

    public function rechazarSup(Request $request, CnaSolicitud $cna)
    {
        return $this->runWorkflow(function () use ($request, $cna) {
            $this->rejectCna->asSupervisor($request->user(), $cna, $request->input('nota_estado'));
        }, 'CNA rechazada por supervisor.');
    }

    public function aprobar(Request $request, CnaSolicitud $cna)
    {
        return $this->runWorkflow(function () use ($request, $cna) {
            $this->approveCna->execute($request->user(), $cna, $request->input('nota_estado'));
        }, 'CNA aprobada y archivos generados.');
    }

    public function rechazarAdmin(Request $request, CnaSolicitud $cna)
    {
        return $this->runWorkflow(function () use ($request, $cna) {
            $this->rejectCna->asAdministrator($request->user(), $cna, $request->input('nota_estado'));
        }, 'CNA rechazada por administrador.');
    }

    public function pdf(int $id)
    {
        return $this->downloadDocument->execute(auth()->user(), $id, 'pdf');
    }

    public function docx(int $id)
    {
        return $this->downloadDocument->execute(auth()->user(), $id, 'docx');
    }

    private function runWorkflow(callable $callback, string $message)
    {
        try {
            $callback();

            return back()->with('ok', $message);
        } catch (DomainException $e) {
            return back()->withErrors($e->getMessage());
        }
    }
}
