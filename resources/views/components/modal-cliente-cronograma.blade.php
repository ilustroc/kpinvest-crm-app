{{-- resources/views/components/modal-cliente-cronograma.blade.php --}}
<div class="modal fade" id="cronModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Cronograma</h6>
        <button class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead>
              <tr>
                <th class="text-end" style="width:60px">#</th>
                <th>Fecha de pago</th>
                <th class="text-end">Monto</th>
                <th class="text-center d-none" id="thBalon">¿Balón?</th>
              </tr>
            </thead>
            <tbody id="cronTbody"></tbody>
          </table>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>