<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Contraseña temporal - UTSLRC</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:'Segoe UI',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:40px 0;">
    <tr>
      <td align="center">
        <table width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;">

          {{-- Encabezado --}}
          <tr>
            <td style="background:#0f172a;border-radius:16px 16px 0 0;padding:32px 40px;text-align:center;">
              <p style="margin:0 0 4px;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#3bbf82;">
                Universidad Tecnológica de San Luis Río Colorado
              </p>
              <h1 style="margin:0;font-size:20px;font-weight:700;color:#ffffff;">
                Sistema de Gestión Académica
              </h1>
            </td>
          </tr>

          {{-- Cuerpo --}}
          <tr>
            <td style="background:#ffffff;padding:40px;">

              <p style="margin:0 0 8px;font-size:16px;color:#1e293b;font-weight:600;">
                Hola, {{ $userName }}
              </p>
              <p style="margin:0 0 32px;font-size:14px;color:#64748b;line-height:1.6;">
                Un administrador ha restablecido tu contraseña. A continuación encontrarás
                tu nueva contraseña temporal. Por seguridad, te recomendamos cambiarla
                desde tu perfil tan pronto como inicies sesión.
              </p>

              {{-- Contraseña temporal --}}
              <div style="background:#f8fafc;border:2px solid #3bbf82;border-radius:14px;padding:28px 16px;text-align:center;margin-bottom:32px;">
                <p style="margin:0 0 6px;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#64748b;">
                  Contraseña temporal
                </p>
                <p style="margin:0;font-size:32px;font-weight:800;letter-spacing:6px;color:#0f172a;font-family:'Courier New',monospace;">
                  {{ $tempPassword }}
                </p>
              </div>

              <p style="margin:0 0 8px;font-size:13px;color:#94a3b8;text-align:center;line-height:1.6;">
                Si no esperabas este correo, contacta al administrador del sistema.<br/>
                <strong style="color:#ef4444;">No compartas esta contraseña con nadie.</strong>
              </p>

            </td>
          </tr>

          {{-- Pie --}}
          <tr>
            <td style="background:#f1f5f9;border-radius:0 0 16px 16px;padding:20px 40px;text-align:center;">
              <p style="margin:0;font-size:12px;color:#94a3b8;">
                &copy; {{ date('Y') }} UTSLRC &middot; Sistema de Gestión Académica Digital
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
