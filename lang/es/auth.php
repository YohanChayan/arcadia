<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user. You are free to modify
    | these language lines according to your application's requirements.
    |
    */

    'failed' => 'Estas credenciales no coinciden con nuestros registros.',
    'password' => 'La contraseña proporcionada es incorrecta',
    'throttle' => 'Demasiado intentos, intente de nuevo en :seconds segundos.',

    // The sign-in and register screens (`AuthFrame.vue`).
    'meta' => ['title' => 'Entrar · Arcadia'],
    'back' => 'Volver al inicio',
    'heading' => 'Entrar',
    'lead' => 'Entra con la cuenta de Google con la que te compartieron tu guía.',
    'fields' => ['name' => 'Tu nombre', 'email' => 'Correo', 'password' => 'Contraseña', 'password_confirmation' => 'Repite la contraseña', 'remember' => 'Recordarme'],
    'submit' => 'Entrar',
    'submitting' => 'Entrando…',
    'owner' => 'Entrar con correo y contraseña',
    'forgot' => '¿Olvidaste tu contraseña?',
    'no_account' => '¿No tienes cuenta?',
    'create_account' => 'Crear una cuenta',
    // The other account screens on Arcadia (`Site::authPage()`), since 2026-10-15.
    'screens' => [
        'register' => [
            'title' => 'Crear una cuenta',
            'lead' => 'Con tu correo y una contraseña, si prefieres no usar Google.',
            'email_hint' => 'Usa el correo con el que te compartieron tu guía. Te enviaremos un enlace para confirmarlo.',
            'submit' => 'Crear cuenta',
            'have_account' => '¿Ya tienes cuenta?',
        ],
        'verify-email' => [
            'title' => 'Revisa tu correo',
            'body' => 'Te enviamos un enlace a :email. Ábrelo para confirmar tu correo y ver tus guías.',
            'sent' => 'Te enviamos un enlace nuevo.',
            'resend' => 'Enviar el enlace otra vez',
            'other' => 'Usar otra cuenta',
        ],
        'forgot-password' => [
            'title' => 'Recuperar tu contraseña',
            'lead' => 'Escribe tu correo y te enviaremos un enlace para elegir una nueva.',
            'submit' => 'Enviar enlace',
        ],
        'reset-password' => [
            'title' => 'Elige una contraseña nueva',
            'submit' => 'Guardar contraseña',
        ],
    ],
    'google' => [
        'continue' => 'Continuar con Google',
        'hint' => 'Sin formularios ni contraseñas. Solo eliges tu cuenta.',
        'unavailable' => 'El acceso con Google no está disponible ahora. Inténtalo en un momento.',
        'failed' => 'No se pudo completar el acceso con Google. Inténtalo de nuevo.',
        'not_shared' => 'La cuenta :email no tiene guías en Arcadia. Si te compartieron una guía con otro correo, entra con esa cuenta.',
    ],
    // A passkey: only where the browser supports one and `PASSKEYS_ENABLED` is on.
    'passkey' => [
        'sign_in' => 'Entrar con una llave de acceso',
        'signing_in' => 'Comprobando…',
        'or' => 'o',
    ],
    // Shown inside an app's own browser (WhatsApp, Instagram…), where Google may refuse to sign in.
    'in_app' => [
        'title' => 'Ábrelo en tu navegador',
        'body' => 'Estás dentro de otra aplicación, y Google podría no dejarte entrar desde aquí. Abre esta página en Chrome o Safari.',
        'open' => 'Abrir en Chrome',
        'copy' => 'Copiar enlace',
        'copied' => 'Enlace copiado: pégalo en tu navegador.',
    ],
];
