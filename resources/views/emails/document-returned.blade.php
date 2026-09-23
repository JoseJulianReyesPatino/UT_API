<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documento devuelto - UTSLRC</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f5;font-family:Arial,Helvetica,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f4f4f5" style="background-color:#f4f4f5;padding:40px 0;">
        <tr>
            <td align="center">
                <table width="480" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="background-color:#ffffff;border:1px solid #e4e4e7;border-radius:8px;">

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
                                    <td style="padding:0 0 4px 0;font-size:12px;font-weight:600;color:#71717a;text-transform:uppercase;letter-spacing:0.3px;">
                                        Documento revisado
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:0 0 16px 0;font-size:20px;font-weight:600;color:#18181b;">
                                        Hola, {{ $docenteName }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:0 0 24px 0;font-size:14px;color:#52525b;line-height:1.6;">
                                        {{ $adminName }} reviso tu documento y fue <strong style="color:#dc2626;">devuelto</strong> para realizar correcciones.
                                    </td>
                                </tr>

                                <!-- Datos -->
                                <tr>
                                    <td style="padding:0 0 16px 0;">
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td style="padding:10px 0;border-top:1px solid #e4e4e7;font-size:13px;color:#71717a;width:40%;">Documento</td>
                                                <td style="padding:10px 0;border-top:1px solid #e4e4e7;font-size:13px;color:#18181b;font-weight:500;text-align:right;width:60%;">{{ $documentTitle }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:10px 0;border-top:1px solid #e4e4e7;font-size:13px;color:#71717a;">Tipo</td>
                                                <td style="padding:10px 0;border-top:1px solid #e4e4e7;font-size:13px;color:#18181b;font-weight:500;text-align:right;">{{ $documentType }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:10px 0;border-top:1px solid #e4e4e7;font-size:13px;color:#71717a;">Devuelto</td>
                                                <td style="padding:10px 0;border-top:1px solid #e4e4e7;font-size:13px;color:#18181b;font-weight:500;text-align:right;">{{ $returnedAt }}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <!-- Motivo -->
                                <tr>
                                    <td style="padding:0 0 24px 0;">
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td style="padding:0 0 4px 0;font-size:12px;font-weight:600;color:#71717a;text-transform:uppercase;letter-spacing:0.3px;">
                                                    Motivo
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding:0;font-size:14px;color:#18181b;line-height:1.6;">
                                                    {{ $comment ?: 'No se proporciono un motivo especifico.' }}
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <!-- Boton -->
                                <tr>
                                    <td align="center" style="padding:0 0 12px 0;">
                                        <table cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td style="border-radius:6px;background:#18181b;" align="center">
                                                    <a href="{{ $appUrl }}" style="display:inline-block;padding:12px 28px;font-size:14px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:6px;background:#18181b;font-family:Arial,Helvetica,sans-serif;">
                                                        Ir al sistema
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 0 0 0;font-size:13px;color:#a1a1aa;line-height:1.6;">
                                        Puedes reenviar el documento una vez que hayas hecho los cambios.
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
