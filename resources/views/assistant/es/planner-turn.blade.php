<contexto>
Canal: {{ $channel }}
Interés anterior: {{ $previousEngagement ?? 'ninguno, es el primer mensaje' }}
Guías ya ofrecidas: @forelse ($offeredGuides as $id => $title){{ $loop->first ? '' : '; ' }}{!! $title !!} (id {{ $id }})@empty ninguna @endforelse

Pasajes:
@forelse ($passages as $passage)
- Guía {{ $passage->guideId }}, «{!! $passage->guideTitle !!}», relevancia {{ number_format($passage->score, 2) }}: {!! $passage->content !!}
@empty
- Ninguno: ninguna guía encaja con lo que dijo.
@endforelse
</contexto>

{!! $message !!}
