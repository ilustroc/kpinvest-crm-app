@php
  $brand   = '#0f172a';
  $primary = '#2563eb';
  $muted   = '#64748b';
  $border  = '#e5e7eb';
  $bg      = '#f8fafc';

  $banner    = $banner ?? '.: CRM :.';
  $subtitle  = $subtitle ?? '';
  $intro     = $intro ?? 'Se requiere su atención para el siguiente caso:';
  $status    = $status ?? null;     // opcional
  $cta       = $cta ?? 'Abrir';
  $link      = $link ?? null;
  $fields    = $fields ?? [];       // array [['label'=>'','value'=>'']]
  $noteTitle = $noteTitle ?? 'Nota';
  $note      = $note ?? null;
@endphp

<!doctype html>
<html lang="es">
<body style="margin:0;padding:0;background:#ffffff;font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#0f172a">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px;">
  <tr><td align="center">

    <table role="presentation" width="640" cellpadding="0" cellspacing="0"
           style="max-width:640px;width:100%;border:1px solid {{ $border }};border-radius:14px;overflow:hidden;">
      {{-- header --}}
      <tr>
        <td style="background:{{ $brand }};color:#fff;padding:16px 18px;">
          <div style="font-size:14px;font-weight:800;">{{ $banner }}</div>
          @if($subtitle)
            <div style="margin-top:4px;font-size:12px;opacity:.85;">{{ $subtitle }}</div>
          @endif
        </td>
      </tr>

      {{-- body --}}
      <tr>
        <td style="padding:18px;background:#fff;">
          @if($status)
            <div style="display:inline-block;background:{{ $bg }};border:1px solid {{ $border }};
                        padding:6px 10px;border-radius:999px;font-size:12px;margin-bottom:12px;">
              <b>Estado:</b> {{ $status }}
            </div>
          @endif

          <div style="font-size:14px;margin-bottom:12px;">{{ $intro }}</div>

          {{-- tabla --}}
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                 style="background:{{ $bg }};border:1px solid {{ $border }};border-radius:12px;overflow:hidden;">
            @foreach($fields as $row)
              @php
                $lbl = $row['label'] ?? '';
                $val = $row['value'] ?? '—';
                if($val === '' || $val === null) $val = '—';
              @endphp
              <tr>
                <td style="width:34%;padding:10px 12px;border-bottom:1px solid {{ $border }};
                           color:{{ $muted }};font-size:12px;font-weight:700;">
                  {{ $lbl }}
                </td>
                <td style="padding:10px 12px;border-bottom:1px solid {{ $border }};font-size:13px;">
                  {{ $val }}
                </td>
              </tr>
            @endforeach
          </table>

          @if(!empty($note))
            <div style="margin-top:14px;padding:12px;border:1px dashed {{ $border }};border-radius:12px;">
              <div style="font-size:12px;color:{{ $muted }};font-weight:800;margin-bottom:6px;">{{ $noteTitle }}</div>
              <div style="font-size:13px;white-space:pre-line;">{{ $note }}</div>
            </div>
          @endif

          @if($link)
            <div style="margin-top:16px;">
              <a href="{{ $link }}"
                 style="display:inline-block;background:{{ $primary }};color:#fff;text-decoration:none;
                        padding:11px 14px;border-radius:10px;font-weight:800;font-size:13px;">
                {{ $cta }}
              </a>
            </div>
          @endif

          <div style="margin-top:18px;color:{{ $muted }};font-size:12px;">
            Inteligencia de Negocios — Plataforma CRM
          </div>
        </td>
      </tr>
    </table>

  </td></tr>
</table>

</body>
</html>