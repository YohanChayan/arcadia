<?php

// An AI's answer to an activity prompt, attached to a draft (`ActivityImportController`).
return [
    'prompt_hint' => 'Lleno con esta guía, su contenido incluido. Cópialo en ChatGPT o Gemini, guarda la respuesta como archivo .json y tráela con «Importar actividades» en el borrador.',
    'lead' => 'La respuesta a un prompt de actividades, en su archivo .json. Se agrega a los ítems, etapas o secciones de este borrador por sus claves; nada más cambia.',
    'drop' => 'Suelta aquí el archivo .json, o elígelo.',
    'file' => 'Elegir archivo',
    'change_file' => 'Cambiar archivo',
    'check' => 'Revisar',
    'problems' => '{1} :count problema: corrígelo en la respuesta o pídesela de nuevo a la IA.|[2,*] :count problemas: corrígelos en la respuesta o pídesela de nuevo a la IA.',
    'ready' => 'Lista para agregar',
    'counts' => ':family: :entries elementos en :nodes lugares.',
    'replaces' => '{0} Este borrador aún no tiene :family.|{1} Reemplaza :family que ya hay en :count lugar.|[2,*] Reemplaza :family que ya hay en :count lugares.',
    'confirm' => 'Agregar',
    'places' => ['activities' => 'Actividad :number', 'questions' => 'pregunta :number', 'options' => 'opción :number', 'pairs' => 'par :number', 'steps' => 'paso :number', 'cards' => 'tarjeta :number'],
    'errors' => [
        'no_json' => 'No se encontró un objeto JSON en el archivo.',
        'no_file' => 'Elige el archivo .json con la respuesta.',
        'not_json_file' => 'El archivo tiene que ser .json.',
        'too_large' => 'El archivo es demasiado grande (máximo 500 KB).',
        'family' => 'la respuesta debe decir su familia: quick_check, stage_game, flip_cards o practice_extras.',
        'unknown_key' => 'este borrador no tiene un lugar con la clave :key para este tipo de actividad.',
        'repeated_key' => 'la clave :key aparece dos veces.',
        'correct' => 'la opción correcta debe ser una de sus opciones, contando desde 0.',
        'blank' => 'un completar necesita ___ donde va la palabra.',
        'not_valid' => 'La respuesta tiene problemas: revísala antes de agregarla.',
        'check_failed' => 'No se pudo revisar el archivo. Inténtalo de nuevo.',
        'import_failed' => 'No se pudieron agregar las actividades. Inténtalo de nuevo.',
    ],
    'flash' => ['imported' => 'Actividades agregadas al borrador.'],
];
