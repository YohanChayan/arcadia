<?php

// A signed-in account at a guide it may not read (`pages/GuidePresentation.vue`).
return [
    'preview' => 'Vista previa: esto es lo que el enlace muestra a quien no puede leer la guía. Desde aquí no se envía nada.',
    'back' => 'Arcadia',
    'goals' => 'Lo que podrás hacer',
    'sign_in' => [
        'title' => 'Entra para inscribirte',
        'body' => 'Entra con tu cuenta de Google para pedir esta guía. Si ya es tuya, al entrar se abre.',
        'action' => 'Entrar',
    ],
    'open' => [
        'title' => 'Pide inscribirte',
        'body' => 'Envía tu pedido y te escribiremos por correo para acordarlo. Podrás leer la guía en cuanto se apruebe.',
        'message' => 'Mensaje',
        'message_placeholder' => 'Lo que quieras contarnos — opcional',
        'action' => 'Pedir inscribirme',
        'hint' => 'Te escribiremos al correo de esta cuenta.',
    ],
    'pending' => ['title' => 'Pedido enviado', 'body' => 'Te escribiremos por correo y te avisaremos cuando puedas leer la guía.'],
    'closed' => ['title' => 'Esta guía no está disponible para inscribirse', 'body' => 'Esta guía no recibe pedidos de inscripción. Si te la compartieron, entra con la cuenta a la que te la enviaron.'],
    'removed' => ['title' => 'Esta guía ya no está disponible para ti', 'body' => 'Ya no tienes acceso a esta guía. Tu cuenta sigue activa.'],
    'declined' => ['title' => 'No tienes acceso a esta guía', 'body' => 'Tu pedido de inscripción no fue aprobado. Tu cuenta sigue activa.'],
    'signed_in_as' => 'Entraste como :email',
    'other_account' => 'Usar otra cuenta de Google',
    'my_guides' => 'Mis guías',
    'flash' => ['requested' => 'Pedido enviado. Te escribiremos por correo.'],
];
