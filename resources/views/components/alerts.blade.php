{{-- resources/views/components/alerts.blade.php --}}
@if(session('ok'))
  <div class="alert alert-success d-flex align-items-center">
    <i class="bi bi-check-circle me-2"></i>
    <div>{{ session('ok') }}</div>
  </div>
@endif

@if($errors->any())
  <div class="alert alert-danger d-flex align-items-center">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <div>{{ $errors->first() }}</div>
  </div>
@endif