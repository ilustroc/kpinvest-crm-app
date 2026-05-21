@php($btn = $cta ?? 'Abrir')
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;margin:auto;font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif">
  <tr>
    <td style="background:#0f172a;color:#fff;padding:14px 18px;border-radius:10px 10px 0 0;">
      <strong>{{ $banner ?? '.: CRM :. Solicitud de CNA' }}</strong>
      <span style="opacity:.8"> — DNI {{ $dni }}@if(!empty($cliente)) - Cliente: {{ $cliente }}@endif</span>
    </td>
  </tr>
  <tr><td style="border:1px solid #e5e7eb;border-top:none;padding:18px;border-radius:0 0 10px 10px">
    <p>Estimado(a) usuario,</p>
    <p>Se requiere su atención para la siguiente solicitud:</p>
    <table width="100%" cellpadding="6" cellspacing="0" style="background:#f8fafc;border:1px solid #eef2f7;border-radius:8px">
      <tr><td width="34%"><b>N° Carta</b></td><td>{{ $nro }}</td></tr>
      <tr><td><b>Cliente</b></td><td>{{ $cliente }}</td></tr>
      <tr><td><b>Documento</b></td><td>{{ $dni }}</td></tr>
      <tr><td><b>Procede de</b></td><td>{{ $procede }}</td></tr>
      @if(!empty($operacion))
        <tr><td><b>Operación</b></td><td>{{ $operacion }}</td></tr>
      @endif
      @if(!empty($observa))
        <tr><td><b>Observación</b></td><td>{{ $observa }}</td></tr>
      @endif
      @isset($nota)
        @if($nota !== '')
          <tr><td><b>Nota</b></td><td>{{ $nota }}</td></tr>
        @endif
      @endisset
    </table>

    <div style="margin-top:14px">
      <a href="{{ $link }}" style="display:inline-block;padding:10px 14px;background:#2563eb;color:#fff;text-decoration:none;border-radius:8px">{{ $btn }}</a>
    </div>

    <p style="color:#64748b;font-size:12px;margin-top:18px">
      Inteligencia de Negocios — Plataforma CRM
    </p>
  </td></tr>
</table>
