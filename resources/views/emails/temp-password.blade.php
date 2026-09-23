<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrasena temporal - UTSLRC</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f5;font-family:Arial,Helvetica,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f4f4f5" style="background-color:#f4f4f5;padding:40px 0;">
        <tr>
            <td align="center">
                <table width="440" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="background-color:#ffffff;border:1px solid #e4e4e7;border-radius:8px;">

                    <!-- Logo (sin texto debajo) -->
                    <tr>
                        <td align="center" bgcolor="#ffffff" style="background-color:#ffffff;padding:24px 0 20px 0;border-bottom:2px solid #e4e4e7;">
                            <img src="{{ $logoUrl }}" alt="Universidad Tecnologica de San Luis Rio Colorado" width="160" style="display:block;margin:0 auto;border:0;outline:none;background-color:#ffffff;" />
                        </td>
                    </tr>

                    <!-- Cuerpo -->
                    <tr>
                        <td style="padding:24px 30px;">
                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding:0 0 16px 0;font-size:20px;font-weight:600;color:#18181b;">
                                        Hola, {{ $userName }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:0 0 24px 0;font-size:14px;color:#52525b;line-height:1.6;">
                                        Un administrador restablecio tu contrasena. Te recomendamos cambiarla desde tu perfil tan pronto inicies sesion.
                                    </td>
                                </tr>

                                <!-- Contrasena temporal -->
                                <tr>
                                    <td style="padding:0 0 24px 0;">
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#fafafa;border:1px solid #e4e4e7;border-radius:8px;">
                                            <tr>
                                                <td align="center" style="padding:20px;">
                                                    <p style="margin:0 0 6px 0;font-size:11px;font-weight:600;letter-spacing:0.5px;text-transform:uppercase;color:#a1a1aa;">
                                                        Contrasena temporal
                                                    </p>
                                                    <p style="margin:0;font-size:24px;font-weight:700;letter-spacing:3px;color:#18181b;font-family:'Courier New',monospace;">
                                                        {{ $tempPassword }}
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:0;font-size:13px;color:#a1a1aa;line-height:1.6;">
                                        Si no esperabas este correo, contacta al administrador del sistema. <strong style="color:#dc2626;">No compartas esta contrasena con nadie.</strong>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Pie -->
                    <tr>
                        <td style="padding:16px 30px;text-align:center;border-top:1px solid #e4e4e7;">
                            <p style="margin:0;font-size:12px;color:#a1a1aa;">Universidad Tecnologica de San Luis Rio Colorado</p>
                            <p style="margin:4px 0 0;font-size:11px;color:#d4d4d8;">Este es un mensaje automatico, no respondas a este correo.</p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
