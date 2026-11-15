<?php

return [
    'title' => 'Lectores',
    'description' => 'Todas las personas a las que se les asignó una guía en español, una por email. Se agregan y se quitan en los lectores de cada guía.',
    'access' => [
        'label' => 'Filtrar por acceso',
        'all' => 'Todos',
        'account' => 'Con cuenta',
        'pending' => 'Pendientes',
        'pending_hint' => 'Tienen acceso a una guía, con o sin invitación por correo, pero aún no han entrado: no la han abierto.',
    ],
    'search' => 'Buscar por email o nombre',
    'search_submit' => 'Buscar',
    'columns' => ['reader' => 'Lector', 'account' => 'Cuenta', 'guides' => 'Guías'],
    'account' => ['yes' => 'Con cuenta', 'no' => 'Sin cuenta aún'],
    'invited' => 'invitación enviada el :date',
    'not_invited' => 'sin invitación por correo',
    'show' => 'Ver guías',
    'guides_count' => '{0} Sin guías|{1} 1 guía asignada|[2,*] :count guías asignadas',
    'assigned' => 'Asignada el :date',
    'empty' => [
        'title' => 'Aún no hay lectores.',
        'hint' => 'Asigna una guía a alguien por email desde los lectores de la guía.',
        'search' => 'Nadie coincide con esa búsqueda.',
    ],
];
