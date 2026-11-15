<?php

return [
    'action' => 'Presentación',
    'title' => 'Presentación',
    'lead' => 'Cómo se presenta esta guía: en las páginas de sus lectores y a quien abra su enlace sin ser lector. Sube cada imagen tal como la generaste: de ella se cortan, desde el centro, los recortes que necesita cada lugar. Nada de esto requiere una versión nueva.',
    'pitch' => [
        'label' => 'Presentación breve',
        'hint' => 'Unas líneas que venden la guía a quien aún no es lector, bajo su título en la página del enlace. También es la descripción de la vista previa del enlace. Sin precio.',
        'save' => 'Guardar',
    ],
    'preview' => 'Ver la página del enlace',
    'prompt' => [
        'title' => 'Prompt de portada',
        'hint' => 'Lleno con los datos de esta guía. Añade una escena o un estilo si tienes uno en mente, cópialo en ChatGPT o Gemini y sube el resultado en «Portada», abajo. No hace falta imagen de referencia.',
        'version' => 'Versión del prompt',
        'missing' => 'Aún no existe la plantilla del prompt de portada: ejecuta su seeder (CoverPromptTemplateSeeder).',
    ],
    'flyer' => [
        'hint' => 'Una imagen para redes sociales que vende esta guía: su título, sus metas, el precio y cómo pedirla. El prompt viene lleno con los datos de la guía; añade tu WhatsApp o una escena, cópialo en ChatGPT o Gemini y sube el resultado en «Flyer», abajo.',
        'open' => 'Prompt de flyer',
        'missing' => 'Aún no existe la plantilla del prompt de flyer: ejecuta su seeder (GuideFlyerPromptTemplateSeeder).',
    ],
    'slots' => [
        'cover' => [
            'label' => 'Portada',
            'hint' => 'Una sola imagen horizontal para todo: la vista previa del enlace, la parte superior de la guía y la tarjeta de «Mis guías». Deja el tema en el centro y no pongas texto: los bordes se recortan distinto en cada lugar.',
            'rule' => 'JPEG, PNG o WebP, hasta :max MB. Horizontal, de al menos :width × :height px, entre 7:5 y 1,91:1 — 3:2 y 16:9 caben.',
            'ratio' => 'La imagen debe ser horizontal, entre 7:5 y 1,91:1; esta es :ratio:1.',
        ],
        'flyer' => [
            'label' => 'Flyer',
            'hint' => 'Las imágenes para redes sociales que venden esta guía, tal como las generó su prompt de flyer — una por red o por campaña, una junto a otra. Cada una se guarda entera: no se le corta nada. Elige varias a la vez, o suéltalas aquí.',
            'rule' => 'JPEG, PNG o WebP, hasta :max MB. Cuadrada o vertical, de al menos :width × :height px — 1:1, 4:5 y 9:16 caben.',
            'ratio' => 'La imagen debe ser cuadrada o vertical, entre 9:16 y 1:1; esta es :ratio:1.',
        ],
    ],
    'upload' => 'Subir imagen',
    'replace' => 'Reemplazar imagen',
    'uploading' => 'Subiendo…',
    'uploading_many' => 'Subiendo :done de :total…',
    'add' => 'Añadir imágenes',
    'count' => ':count de :max',
    'drop' => 'Suelta las imágenes para subirlas.',
    'empty' => 'Aún no hay imagen.',
    'original' => 'Original · :width × :height px · :size',
    'download' => 'Abrir original',
    'crops' => [
        'share' => 'Vista previa del enlace',
        'hero' => 'Parte superior de la guía',
        'thumb' => 'Tarjeta de «Mis guías»',
    ],
    'remove' => [
        'action' => 'Quitar imagen',
        'title' => '¿Quitar esta imagen?',
        'body' => 'Se borran el original y sus recortes. Puedes subir otra cuando quieras.',
        'confirm' => 'Quitar',
    ],
    'errors' => [
        'required' => 'Elige una imagen para subir.',
        'type' => 'La imagen debe ser JPEG, PNG o WebP.',
        'size' => 'La imagen debe pesar :max MB o menos.',
        'dimensions' => 'La imagen debe medir al menos :width × :height px.',
        'full' => 'Aquí caben :max imágenes como máximo: quita una para añadir otra.',
    ],
    'flash' => [
        'saved' => 'Imagen guardada: :slot.',
        'pitch_saved' => 'Presentación guardada.',
        'removed' => 'Imagen quitada: :slot.',
    ],
];
