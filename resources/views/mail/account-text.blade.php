{{-- The plain-text part of `mail/account`, for clients that show no HTML. --}}
{{ $heading }}

@foreach ($lines as $line)
{{ $line }}

@endforeach
{{ $action }}: {{ $url }}

{{ $ignore }}

— {{ $signature }}
