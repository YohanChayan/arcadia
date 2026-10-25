{{--
    Internal notification. Inline styles only: mail clients strip the stylesheet, so nothing here
    can rely on `app.css` or on the design tokens. Laid out as `inquiry.blade.php` is.
--}}
@php($requester = $accessRequest->user)
<div style="background-color:#030D1C;padding:24px;font-family:-apple-system,Segoe UI,Helvetica,Arial,sans-serif;">
    <div style="max-width:600px;margin:0 auto;background-color:#0B2240;border-radius:14px;padding:28px;">

        <p style="margin:0 0 4px;font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#2EE6B8;">
            Inscripción · {{ strtoupper($accessRequest->guide->locale) }}
        </p>
        <h1 style="margin:0 0 20px;font-size:22px;line-height:1.25;color:#EBEEF7;font-weight:700;">
            {{ $requester->name ?: 'Alguien' }} quiere inscribirse
        </h1>

        <p style="margin:0 0 22px;font-size:17px;line-height:1.5;color:#EBEEF7;">
            {{ $accessRequest->guide->title }}
        </p>

        <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">
            @foreach (array_filter(['Correo' => $requester->email, 'Mensaje' => $accessRequest->message]) as $label => $value)
                <tr>
                    <td style="padding:8px 0;border-bottom:1px solid rgba(235,238,247,0.10);width:110px;vertical-align:top;
                               font-size:11px;letter-spacing:1px;text-transform:uppercase;color:rgba(235,238,247,0.45);">
                        {{ $label }}
                    </td>
                    <td style="padding:8px 0;border-bottom:1px solid rgba(235,238,247,0.10);font-size:14px;color:#EBEEF7;white-space:pre-line;">{{ $value }}</td>
                </tr>
            @endforeach
        </table>

        <p style="margin:22px 0 0;font-size:13px;line-height:1.5;color:rgba(235,238,247,0.65);">
            Escríbele para acordar el pago; al responder este correo le llega a esa persona. Cuando esté pagado,
            apruébalo en Lectores y recibirá el aviso de acceso.
        </p>

        <p style="margin:22px 0 0;">
            <a href="mailto:{{ $requester->email }}"
               style="display:inline-block;background-color:#2EE6B8;color:#061A33;text-decoration:none;font-weight:700;
                      font-size:14px;padding:10px 18px;border-radius:9999px;">
                Escribir por correo
            </a>
            <a href="{{ $readersUrl }}"
               style="display:inline-block;margin-left:8px;color:#2EE6B8;text-decoration:none;font-weight:600;font-size:14px;padding:10px 6px;">
                Abrir Lectores
            </a>
        </p>
    </div>
</div>
