{{--
    Internal notification. Inline styles only: mail clients strip the stylesheet, so nothing here
    can rely on `app.css` or on the design tokens. The guides' blue, as the hosts are.
--}}
<div style="background-color:#030D1C;padding:24px;font-family:-apple-system,Segoe UI,Helvetica,Arial,sans-serif;">
    <div style="max-width:600px;margin:0 auto;background-color:#0B2240;border-radius:14px;padding:28px;">

        <p style="margin:0 0 4px;font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#2EE6B8;">
            Guías · {{ strtoupper($inquiry->locale) }}
        </p>
        <h1 style="margin:0 0 20px;font-size:22px;line-height:1.25;color:#EBEEF7;font-weight:700;">
            {{ $inquiry->requesterName() ?: 'Alguien' }} quiere aprender
        </h1>

        <p style="margin:0 0 22px;font-size:17px;line-height:1.5;color:#EBEEF7;">
            {{ $inquiry->topic }}
        </p>

        <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">
            @foreach (array_filter([
                'Para qué' => $inquiry->purpose ? __('arcadia.landing.ask.purposes.'.$inquiry->purpose->value, [], 'es') : null,
                'Cuenta' => $inquiry->user?->email,
                $inquiry->contact_kind === \App\Models\Inquiry::EMAIL ? 'Contacto' : 'WhatsApp' => $inquiry->contact,
            ]) as $label => $value)
                <tr>
                    <td style="padding:8px 0;border-bottom:1px solid rgba(235,238,247,0.10);width:110px;vertical-align:top;
                               font-size:11px;letter-spacing:1px;text-transform:uppercase;color:rgba(235,238,247,0.45);">
                        {{ $label }}
                    </td>
                    <td style="padding:8px 0;border-bottom:1px solid rgba(235,238,247,0.10);font-size:14px;color:#EBEEF7;">
                        {{ $value }}
                    </td>
                </tr>
            @endforeach
        </table>

        <p style="margin:26px 0 0;">
            <a href="{{ $inquiry->replyUrl() }}"
               style="display:inline-block;background-color:#2EE6B8;color:#061A33;text-decoration:none;font-weight:700;
                      font-size:14px;padding:10px 18px;border-radius:9999px;">
                {{ $inquiry->contact_kind === \App\Models\Inquiry::EMAIL ? 'Responder por correo' : 'Responder por WhatsApp' }}
            </a>
        </p>
    </div>
</div>
