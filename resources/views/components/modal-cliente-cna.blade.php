{{-- resources/views/components/modal-cliente-cna.blade.php --}}
@props(['dni'])

<div class="modal fade" id="modalCna" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="POST" action="{{ route('clientes.cna.store', $dni) }}" data-once>
      @csrf

      <div class="modal-header">
        <h6 class="modal-title d-flex align-items-center gap-2">
          <i class="bi bi-file-earmark-text"></i>
          Solicitar Carta de No Adeudo (CNA)
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">
        <div class="alert alert-info small">
          <div><b>Cuenta:</b> <span id="cnaCuenta">—</span></div>
          <div><b>Cosecha:</b> <span id="cnaCosecha">—</span></div>
          <div><b>Origen / Plantilla:</b> <span id="cnaPlantilla">—</span></div>
          <div><b>Operaciones incluidas:</b>
            <span id="cnaOpsList" class="d-inline-flex flex-wrap gap-1 align-middle"></span>
          </div>
          <div class="mt-1">El correlativo se asignará por <b>serie</b> (KPI, F, F2) al guardar.</div>
        </div>

        <div class="row g-2">
          <div class="col-md-6">
            <label class="form-label">Fecha de pago realizado <span class="text-danger">*</span></label>
            <input type="date" name="fecha_pago_realizado" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Monto pagado (S/.) <span class="text-danger">*</span></label>
            <input type="number" name="monto_pagado" step="0.01" min="0.01" class="form-control" required>
          </div>
        </div>

        <div class="mb-3 mt-2">
          <label class="form-label">Observación (opcional)</label>
          <textarea name="observacion" class="form-control" rows="3"
                    placeholder="Algún comentario contextual"></textarea>
        </div>

        <input type="hidden" name="cuenta" id="cnaCuentaInput">
        <div id="cnaOpsHidden"></div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-success" type="submit">
          <i class="bi bi-send me-1"></i> Enviar solicitud
        </button>
      </div>
    </form>
  </div>
</div>