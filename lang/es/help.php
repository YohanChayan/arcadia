<?php

// La ayuda del lector (`pages/reader/Help.vue`). `:limit` es el límite de dispositivos de la cuenta.
return [
    'meta' => ['title' => 'Ayuda · Arcadia'],
    'title' => 'Ayuda',
    'intro' => 'Cómo funciona Arcadia, en corto. Si algo no queda resuelto aquí, escríbenos.',
    'items' => [
        'open' => ['title' => 'Abrir una guía', 'body' => 'Todas las guías que te compartieron están en Mis guías. Ábrela desde ahí, o desde el enlace que te enviaron, con la misma cuenta con la que te la compartieron.'],
        'progress' => ['title' => 'Tu progreso', 'body' => 'Marca cada video, enlace o práctica cuando lo termines. Lo que marcas se guarda en tu cuenta, así sigues donde lo dejaste en cualquier dispositivo.'],
        'notes' => ['title' => 'Tus notas', 'body' => 'Escribe notas en cualquier paso o etapa de una guía. Solo tú las ves.'],
        'colours' => ['title' => 'Tus colores', 'body' => 'Elige los colores con los que lees una guía, desde la guía o desde Mis guías. Solo tú los ves.'],
        'devices' => ['title' => 'Dispositivos', 'body' => 'Puedes leer en hasta :limit dispositivos. Al llegar al límite, puedes cambiar uno por otro nuevo una vez cada 30 días; para más, escríbenos.'],
        'account' => ['title' => 'Tu cuenta', 'body' => 'Si una guía deja de abrir, o quieres que borremos tu cuenta, escríbenos desde el correo con el que entras.'],
    ],
    'contact' => [
        'title' => 'Contactar a soporte',
        'body' => 'Escríbenos y te responderemos lo antes posible.',
        'copy' => 'Copiar correo',
        'copied' => 'Copiado',
    ],
];
