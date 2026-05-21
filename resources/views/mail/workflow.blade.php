<!doctype html>
<html lang="es">
  <body style="margin:0;background:#f8fafc;color:#17202a;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;padding:30px 15px;">
      <tr>
        <td align="center">
          
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border:1px solid #e2e8f0;border-top:4px solid #17202a;border-radius:4px;text-align:left;">
            
            <tr>
              <td style="padding:30px 30px 20px 30px;border-bottom:1px solid #e2e8f0;">
                <div style="font-size:11px;letter-spacing:0.1em;text-transform:uppercase;font-weight:700;color:#64748b;">
                  KP Invest CRM • {{ $module }}
                </div>
                <div style="font-size:20px;font-weight:900;margin-top:8px;color:#17202a;text-transform:uppercase;">
                  {{ $title }}
                </div>
              </td>
            </tr>
            
            <tr>
              <td style="padding:30px;">
                <p style="margin:0 0 24px;font-size:14px;line-height:1.6;color:#475569;">
                  {{ $intro }}
                </p>

                @if($status !== '')
                  <div style="margin-bottom:24px;font-size:13px;font-weight:800;color:#17202a;text-transform:uppercase;border-left:3px solid #17202a;padding-left:12px;">
                    Estado: {{ $status }}
                  </div>
                @endif

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                  @foreach($fields as $label => $value)
                    <tr>
                      <td style="width:40%;padding:12px 0;border-bottom:1px solid #f1f5f9;color:#64748b;font-size:11px;text-transform:uppercase;font-weight:700;letter-spacing:0.05em;">
                        {{ $label }}
                      </td>
                      <td style="padding:12px 0;border-bottom:1px solid #f1f5f9;color:#17202a;font-size:14px;font-weight:700;">
                        {{ $value !== null && $value !== '' ? $value : '-' }}
                      </td>
                    </tr>
                  @endforeach
                </table>

                @if($actionUrl)
                  <div style="margin-top:32px;">
                    <a href="{{ $actionUrl }}" style="display:inline-block;background:#17202a;color:#ffffff;text-decoration:none;padding:12px 24px;font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:0.05em;border-radius:2px;">
                      {{ $actionText ?? 'Ver en plataforma' }}
                    </a>
                  </div>
                @endif

              </td>
            </tr>
            
            <tr>
              <td style="padding:0 30px 30px 30px;">
                <p style="margin:0;border-top:1px solid #e2e8f0;padding-top:20px;color:#94a3b8;font-size:11px;line-height:1.5;">
                  Este mensaje es generado automáticamente por KP Invest CRM. Por favor, no responda a este correo.
                </p>
              </td>
            </tr>
            
          </table>

        </td>
      </tr>
    </table>
  </body>
</html>