<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Verifica tu registro - UTSLRC</title></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 0;">
        <tr>
            <td align="center">
                <table width="520" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;">
                    <tr>
                        <td align="center" style="padding:32px 24px 8px;">
                            <img src="{{ $logoUrl }}" alt="UTSLRC" style="height:56px;">
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:8px 32px 0;">
                            <h1 style="margin:0;color:#0f172a;font-size:22px;">Verifica tu correo</h1>
                            <p style="color:#64748b;font-size:14px;margin-top:8px;">
                                Hola {{ $userName }}, usa este código para completar tu registro:
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:24px 32px;">
                            <div style="display:inline-block;background:#ecfdf5;border:2px dashed #3BBF82;border-radius:12px;padding:18px 36px;">
                                <span style="font-size:36px;font-weight:800;letter-spacing:12px;color:#3BBF82;font-family:monospace;">{{ $code }}</span>
                            </div>
                            <p style="color:#94a3b8;font-size:12px;margin-top:16px;">
                                Este código expira en <strong>15 minutos</strong>.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
