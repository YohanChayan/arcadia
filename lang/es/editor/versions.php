<?php

return [
    'history' => 'Versiones',
    'viewing_old' => 'Estás viendo una versión anterior. Solo la última se edita.',
    'latest' => 'Ir a la última',
    'release' => [
        'action' => 'Publicar esta versión',
        'title' => '¿Publicar la versión :number?',
        'body' => 'Desde ese momento queda congelada: quien la reciba la conserva tal cual. Solo «corregir en todas» puede tocarla después.',
        'confirm' => 'Publicar',
    ],
    'released' => [
        'title' => 'Versión :number publicada',
        'body' => 'Este es el enlace de la guía. Sus lectores lo abren con su cuenta de Google y ven esta versión.',
    ],
    'draft_next' => 'Nueva versión',
    'draft_next_hint' => 'Copia esta versión en un borrador nuevo para cambiarla.',
    'changes' => [
        'title' => 'Cambios de esta versión',
        'hint' => 'Qué cambió respecto a la anterior. Decide si a quien tiene una versión anterior se le ofrece esta.',
        'add' => 'Anotar cambio',
        'empty' => 'Sin cambios anotados.',
        'fields' => ['kind' => 'Tipo', 'title' => 'Qué cambió', 'notes' => 'Notas'],
    ],
    'fix' => [
        'action' => 'Corregir en todas las versiones',
        'title' => 'Corregir en todas las versiones',
        'body' => '{1} Esta línea está en 1 versión.|[2,*] Esta línea está en :count versiones, publicadas incluidas. La corrección llega a todas.',
        'save' => 'Corregir',
    ],
    'flash' => [
        'released' => 'Versión :number publicada.',
        'released_and_published' => 'Versión :number publicada, y la guía también: ya la pueden abrir sus lectores.',
        'drafted' => 'Borrador de la versión :number listo.',
        'fixed' => '{1} Corregida en 1 versión.|[2,*] Corregida en :count versiones.',
    ],
];
