<?php

// A signed-in reader's own guides (`pages/reader/Dashboard.vue`), and the colours they
// change there and on the guide page.
return [
    'meta' => ['title' => 'Mis guías · Arcadia'],
    'title' => 'Mis guías',
    'sign_out' => 'Salir',
    'open' => 'Abrir guía',
    'preparing' => 'La estamos preparando. Te avisaremos cuando esté lista.',
    'progress' => [
        'done' => ':done de :total pasos hechos',
        'videos' => ':done/:total videos',
        'links' => ':done/:total enlaces',
        'practices' => ':done/:total prácticas',
        'stages' => ':done/:total etapas',
        'last' => 'Última marca el :date. Marca cada video o enlace al terminarlo para llevar tu avance.',
        'none' => 'Aún no marcas nada. Marca cada video o enlace como hecho al terminarlo: así llevas tu avance.',
    ],
    // The line to do next (`GuideProgressSummary` `next`): after a first tick, and before it.
    'continue' => ['next' => 'Sigue con', 'first' => 'Empieza con'],
    // Days in a row with a line ticked (`ReadingStreak`): the run, then how to keep or start it.
    'streak' => [
        'days' => '{1} :count día seguido|[2,*] :count días seguidos',
        'keep' => 'Marca un paso hoy para no perder la racha.',
        'start' => 'Marca un paso hoy y empieza tu racha.',
    ],
    'empty' => ['title' => 'Aún no tienes guías', 'hint' => 'Cuando te compartan una guía, aparecerá aquí.'],
    'colours' => [
        'action' => 'Colores',
        'title' => 'Tus colores',
        'hint' => 'Solo tú ves la guía con estos colores.',
        'save' => 'Guardar',
        'cancel' => 'Cancelar',
    ],
    'flash' => ['saved' => 'Colores guardados.'],
];
