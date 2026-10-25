{{--
    An account email — verify the address, reset the password — in Arcadia's brand
    (`MailBrand`, via `ArcadiaAccountMail`): teal on navy.
    Inline styles only: mail clients strip stylesheets, so nothing here can rely on `app.css`.
    The words arrive translated; this only lays them out.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="dark">
    <meta name="supported-color-schemes" content="dark">
    <title>{{ $heading }}</title>
</head>
<body style="margin:0;padding:0;background-color:{{ $brand->ground }};">
<div style="background-color:{{ $brand->ground }};padding:32px 16px;font-family:-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;">
    <div style="max-width:560px;margin:0 auto;">

        <p style="margin:0 0 18px;text-align:center;">
            <a href="{{ $brand->homeUrl }}" style="font-size:20px;font-weight:800;letter-spacing:-0.3px;color:{{ $brand->ink }};text-decoration:none;">
                {{ $brand->name }}
            </a>
        </p>

        <div style="background-color:{{ $brand->panel }};border-radius:14px;padding:32px 28px;">
            <p style="margin:0 0 6px;font-size:11px;letter-spacing:2px;text-transform:uppercase;color:{{ $brand->eyebrow }};">
                {{ $brand->name }}
            </p>
            <h1 style="margin:0 0 18px;font-size:22px;line-height:1.3;color:{{ $brand->ink }};font-weight:700;">
                {{ $heading }}
            </h1>

            @foreach ($lines as $line)
                <p style="margin:0 0 14px;font-size:15px;line-height:1.65;color:{{ $brand->inkSoft }};">{{ $line }}</p>
            @endforeach

            <p style="margin:26px 0 28px;text-align:center;">
                <a href="{{ $url }}"
                   style="display:inline-block;background-color:{{ $brand->accent }};color:{{ $brand->onAccent }};text-decoration:none;
                          font-weight:700;font-size:15px;padding:13px 26px;border-radius:9999px;">
                    {{ $action }}
                </a>
            </p>

            <p style="margin:0 0 22px;font-size:13px;line-height:1.6;color:{{ $brand->inkSoft }};">{{ $ignore }}</p>

            <p style="margin:0;padding-top:18px;border-top:1px solid {{ $brand->rule }};font-size:12px;line-height:1.6;color:{{ $brand->inkSoft }};">
                {{ $fallback }}<br>
                <a href="{{ $url }}" style="color:{{ $brand->accent }};word-break:break-all;">{{ $url }}</a>
            </p>
        </div>

        <p style="margin:18px 0 0;text-align:center;font-size:12px;color:{{ $brand->inkSoft }};">
            {{ $signature }}
        </p>
    </div>
</div>
</body>
</html>
