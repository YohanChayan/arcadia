<contexto>
Canal: {{ $channel }}
Interés de la persona: {{ $engagement }} de 100
Guías ya ofrecidas: @forelse ($offeredGuides as $title){{ $loop->first ? '' : '; ' }}{!! $title !!}@empty ninguna @endforelse

Pasajes:
@forelse ($passages as $passage)
- «{!! $passage->guideTitle !!}»: {!! $passage->content !!}
@empty
- Ninguno: ninguna guía encaja con lo que dijo.
@endforelse

Plan del turno: @if ($acceptedGuide)la persona acaba de aceptar «{!! $acceptedGuide !!}». Celebra su decisión y explícale cómo empezar.@elseif ($offerGuide)ofrece «{!! $offerGuide !!}».@else no ofrezcas ninguna guía todavía; entiende mejor qué quiere lograr la persona.@endif

</contexto>

{!! $message !!}
