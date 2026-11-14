@php
    $primary = $branch->color_primary ?: '#6b3e1e';
    $accent = $branch->color_accent ?: '#c8a24a';
    $logo = $branch->logo_path ? url($branch->logo_path) : null;
@endphp
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Código {{ $code }}</title></head>
<body style="margin:0;padding:0;background:#f4f1ec;font-family:Arial,Helvetica,sans-serif;color:#2b1d17;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f1ec;padding:24px 12px;">
    <tr><td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e9e2d8;">
            <tr><td style="background:{{ $primary }};height:6px;font-size:0;line-height:0;">&nbsp;</td></tr>
            <tr><td align="center" style="padding:28px 28px 8px;">
                @if($logo)
                    <img src="{{ $logo }}" alt="{{ $branch->brandName() }}" height="56" style="display:block;height:56px;width:auto;border:0;">
                @else
                    <div style="font-family:Georgia,serif;font-size:28px;font-weight:bold;color:{{ $primary }};">{{ $branch->brandName() }}</div>
                @endif
            </td></tr>
            <tr><td style="padding:12px 32px 0;text-align:center;">
                <p style="margin:0;font-size:18px;font-weight:bold;">Hola {{ $customer->firstName() }} 👋</p>
                <p style="margin:12px 0 0;font-size:15px;line-height:22px;color:#5a4a40;">
                    @if($isReset)
                        Recibimos un pedido para cambiar la contraseña de tu cuenta en <strong>{{ $branch->brandName() }}</strong>. Usa este código:
                    @else
                        Para terminar de crear tu cuenta en <strong>{{ $branch->brandName() }}</strong>, escribe este código de verificación:
                    @endif
                </p>
            </td></tr>
            <tr><td align="center" style="padding:24px 32px;">
                <div style="display:inline-block;background:#faf6f1;border:2px dashed {{ $accent }};border-radius:12px;padding:16px 28px;font-size:34px;font-weight:bold;letter-spacing:10px;color:{{ $primary }};font-family:'Courier New',monospace;">{{ $code }}</div>
                <p style="margin:14px 0 0;font-size:13px;color:#8a7a70;">Vence en {{ $minutes }} minutos.</p>
            </td></tr>
            <tr><td style="padding:0 32px 28px;text-align:center;font-size:13px;line-height:20px;color:#8a7a70;">
                Si tú no pediste este código, ignora este correo: tu cuenta sigue segura.<br>
                Nunca te pediremos este código por WhatsApp ni por teléfono.
            </td></tr>
            <tr><td style="background:#faf6f1;padding:16px 24px;text-align:center;font-size:12px;color:#8a7a70;border-top:1px solid #efe8de;">
                {{ $branch->name }} · {{ $branch->address }}, {{ $branch->city }}
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
