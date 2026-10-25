<?php

/*
| The account emails — verify the address, reset the password — (`ArcadiaAccountMail`,
| view `mail/account`). `:site` is the brand's name: Arcadia.
*/

return [
    'account' => [
        'verify' => [
            'subject' => 'Confirma tu correo · :site',
            'heading' => 'Confirma tu correo',
            'body' => 'Para terminar de crear tu cuenta en :site, confirma que este correo es tuyo.',
            'expires' => 'El enlace vence en :minutes minutos.',
            'action' => 'Confirmar mi correo',
            'ignore' => 'Si no creaste una cuenta en :site, ignora este correo: no pasará nada.',
        ],
        'reset' => [
            'subject' => 'Elige una contraseña nueva · :site',
            'heading' => 'Elige una contraseña nueva',
            'body' => 'Alguien pidió cambiar la contraseña de tu cuenta en :site. Si fuiste tú, elige una nueva desde aquí.',
            'expires' => 'El enlace vence en :minutes minutos.',
            'action' => 'Elegir contraseña',
            'ignore' => 'Si no lo pediste, ignora este correo: tu contraseña sigue igual.',
        ],
        'fallback' => 'Si el botón no funciona, copia este enlace en tu navegador:',
        'signature' => 'Con cariño, el equipo de :site',
    ],
    'guide_shared' => [
        'subject' => 'Tu guía «:title» está lista · :site',
        'heading' => 'Tienes una guía nueva: :title',
        'heading_access' => 'Ya tienes acceso a :title',
        'body' => 'Te compartieron la guía «:title» en :site. Está lista para que empieces cuando quieras.',
        'body_access' => 'Aceptaron tu pedido: la guía «:title» ya es tuya en :site.',
        'sign_in' => 'Para abrirla, entra con este correo: :email. Puedes entrar con Google o crear tu cuenta de :site con él.',
        'hello' => '¡Hola, :name!',
        'action' => 'Abrir mi guía',
        'help' => '¿Dudas, o no era para ti? Escríbenos a :contact.',
    ],
];
