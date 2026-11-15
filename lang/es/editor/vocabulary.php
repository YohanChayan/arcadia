<?php

return [
    'title' => 'Vocabulario',
    'description' => 'Lo que eligen las guías en español: los términos con los que se agrupan y las frases que ven sus lectores. Cada idioma tiene los suyos. Las metas de una guía se escriben en la guía misma.',
    'tabs' => ['categories' => 'Categorías', 'topics' => 'Temas', 'phrases' => 'Frases'],
    'hints' => [
        'categories' => 'Dónde encaja una guía: Ofimática, Negocios, Cripto. Una por guía.',
        'topics' => 'Qué cubre una guía: fórmulas, tablas dinámicas. Varios por guía.',
    ],
    'fields' => ['name' => 'Nombre'],
    'add' => 'Añadir',
    'save' => 'Guardar',
    'used_by' => '{0} sin uso|{1} en 1 guía|[2,*] en :count guías',
    'empty' => 'Aún no hay ninguno.',
    'delete' => [
        'title' => '¿Eliminar este término?',
        'body' => 'Las guías que lo usan dejarán de tenerlo. Las guías no se eliminan.',
        'confirm' => 'Eliminar',
    ],
    'errors' => [
        'name' => 'Escribe un nombre.',
        'taken' => 'Ya existe un término con ese nombre.',
    ],
    'flash' => ['saved' => 'Guardado.', 'deleted' => 'Eliminado.'],
    // The categorizer: one category's guides, and a search over all of them to file more.
    'categorizer' => [
        'open' => 'Guías',
        'description' => 'Las guías de esta categoría, y un buscador para añadir más de una vez.',
        'inside' => 'En esta categoría',
        'inside_empty' => 'Todavía no tiene guías.',
        'remove' => 'Quitar de la categoría',
        'search' => 'Buscar guías',
        'search_placeholder' => 'Título de la guía…',
        'results_empty' => 'Ninguna guía coincide.',
        'uncategorised' => 'Sin categoría',
        'statuses' => ['draft' => 'Borrador', 'published' => 'Publicada'],
        'selected' => '{0} Nada seleccionado|{1} 1 seleccionada|[2,*] :count seleccionadas',
        'clear' => 'Limpiar',
        'add' => 'Añadir a la categoría',
        'move' => [
            'title' => '¿Cambiar de categoría?',
            'body' => 'Ya tienen categoría: :guides. Pasarán a «:category».',
            'confirm' => 'Cambiar',
        ],
        'errors' => [
            'none' => 'Selecciona al menos una guía.',
            'filed_elsewhere' => '{1} Una de esas guías ya está en otra categoría.|[2,*] :count de esas guías ya están en otra categoría.',
        ],
        'flash' => ['filed' => 'Guías añadidas a la categoría.', 'unfiled' => 'Guía quitada de la categoría.'],
    ],
];
