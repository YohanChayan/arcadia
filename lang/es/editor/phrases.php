<?php

return [
    'description' => 'Frases cortas de ánimo que ve quien sigue sus guías, en español. {name} se cambia por su nombre.',
    'moment' => 'Momento',
    'text' => 'Frase',
    'placeholder' => '¡Vas bien, {name}! Sigue así.',
    'hint' => 'Corta, que se lea de un vistazo: hasta :max caracteres.',
    'add' => 'Añadir',
    'save' => 'Guardar',
    'edit' => 'Editar frase',
    'empty' => 'Aún no hay frases para este momento.',
    'used_by' => '{0} todas las guías|{1} elegida en 1 guía|[2,*] elegida en :count guías',
    'moments' => [
        'welcome' => 'Bienvenida',
        'start' => 'Sin empezar',
        'progress' => 'En camino',
        'stage_done' => 'Etapa completa',
        'almost' => 'Casi lista',
        'done' => 'Terminada',
    ],
    'when' => [
        'welcome' => 'Arriba en «Mis guías», al entrar.',
        'start' => 'En una guía que aún no tiene nada marcado.',
        'progress' => 'En una guía con algo marcado, por debajo de tres cuartos.',
        'stage_done' => 'En la guía, cuando una marca completa una etapa que no es la última.',
        'almost' => 'En una guía con tres cuartos o más marcados.',
        'done' => 'En una guía terminada.',
    ],
    'delete' => [
        'action' => 'Eliminar frase',
        'title' => '¿Eliminar esta frase?',
        'body' => 'Deja de mostrarse, también en las guías que la eligieron.',
        'confirm' => 'Eliminar',
    ],
    'flash' => ['saved' => 'Frase guardada.', 'deleted' => 'Frase eliminada.'],
];
