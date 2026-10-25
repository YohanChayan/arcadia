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

    // Login and Register page translations
    'login' => [
        'badge' => 'Acceso Seguro',
        'title' => [
            'line_1' => 'Bienvenido a',
            'highlight' => 'Yhn Dev Side',
        ],
        'description' => 'Ingresa tus credenciales para acceder a tu panel de control',
        'fields' => [
            'email' => 'Correo Electrónico',
            'password' => 'Contraseña',
        ],
        'placeholders' => [
            'email' => 'tu@email.com',
            'password' => '••••••••',
        ],
        'submit' => 'Iniciar Sesión',
        'submit_processing' => 'Accediendo...',
        'links' => [
            'forgot_password' => '¿Olvidaste tu contraseña?',
            'register' => 'Registrarme',
        ],
        'footer' => 'Conexión segura y encriptada',
        'oauth' => [
            'title' => 'Continuar con',
            'google' => 'Continuar con Google',
            'linkedin' => 'Continuar con LinkedIn',
            'github' => 'Continuar con GitHub',
            'divider' => 'O',
        ],
        'toast' => [
            'success' => '¡Bienvenido de nuevo, :name!',
            'error' => 'Error en el inicio de sesión. Por favor verifica tus credenciales e intenta de nuevo.',
        ],
    ],

    'register' => [
        'badge' => 'Registro Seguro',
        'title' => [
            'line_1' => 'Crea tu cuenta en',
            'highlight' => 'Yhn Dev Side',
        ],
        'description' => 'Completa tus datos para crear tu cuenta',
        'fields' => [
            'name' => 'Nombre',
            'email' => 'Correo Electrónico',
            'password' => 'Contraseña',
            'password_confirmation' => 'Confirmar Contraseña',
        ],
        'placeholders' => [
            'name' => 'Tu nombre',
            'email' => 'tu@email.com',
            'password' => '••••••••',
            'password_confirmation' => '••••••••',
        ],
        'submit' => 'Crear Cuenta',
        'submit_processing' => 'Creando cuenta...',
        'links' => [
            'has_account' => 'Ya tengo una cuenta',
        ],
        'footer' => 'Conexión segura y encriptada',
        'toast' => [
            'success' => '¡Bienvenido a YHN Dev Side, :name! Por favor revisa tu correo para verificar tu cuenta.',
            'error' => 'Error en el registro. Por favor corrige los errores e intenta de nuevo.',
        ],
    ],

    'forgot_password' => [
        'badge' => 'Recuperación de Cuenta',
        'title' => [
            'line_1' => '¿Olvidaste tu',
            'highlight' => 'Contraseña?',
        ],
        'description' => 'No te preocupes, te enviaremos un enlace de recuperación a tu correo electrónico',
        'fields' => [
            'email' => 'Correo Electrónico',
        ],
        'placeholders' => [
            'email' => 'tu@email.com',
        ],
        'submit' => 'Enviar Enlace',
        'submit_processing' => 'Enviando...',
        'links' => [
            'back_to_login' => 'Volver al inicio de sesión',
        ],
        'footer' => 'Recibirás un correo con instrucciones para restablecer tu contraseña en unos minutos.',
        'toast' => [
            'success' => '¡Enlace de restablecimiento enviado! Por favor revisa tu bandeja de entrada.',
            'error' => 'Error al enviar el enlace. Por favor verifica tu correo e intenta de nuevo.',
        ],
    ],

    'reset_password' => [
        'badge' => 'Restablecer Contraseña',
        'title' => [
            'line_1' => 'Nueva',
            'highlight' => 'Contraseña',
        ],
        'description' => 'Crea una nueva contraseña segura para tu cuenta',
        'fields' => [
            'email' => 'Correo Electrónico',
            'password' => 'Nueva Contraseña',
            'password_confirmation' => 'Confirmar Contraseña',
        ],
        'placeholders' => [
            'email' => 'tu@email.com',
            'password' => 'Mínimo 8 caracteres',
            'password_confirmation' => 'Repite tu contraseña',
        ],
        'submit' => 'Restablecer Contraseña',
        'submit_processing' => 'Actualizando...',
        'footer' => 'Tu contraseña será encriptada de forma segura',
        'validation' => [
            'email_required' => 'El correo es requerido',
            'password_min_length' => 'La contraseña debe tener al menos 8 caracteres',
            'password_mismatch' => 'Las contraseñas no coinciden',
        ],
        'toast' => [
            'success' => '¡Contraseña restablecida exitosamente! Ahora puedes iniciar sesión con tu nueva contraseña.',
            'error' => 'Error al restablecer la contraseña. Por favor revisa el formulario e intenta de nuevo.',
        ],
    ],

    'two_factor' => [
        'badge' => 'Verificación en dos pasos',
        'title' => [
            'line_1' => 'Un paso más',
            'highlight' => 'para confirmar que eres tú',
        ],
        'description' => 'Introduce el código de seis dígitos de tu aplicación de autenticación.',
        'description_recovery' => 'Introduce uno de los códigos de recuperación que guardaste al activar la verificación en dos pasos. Cada código sirve una sola vez.',
        'fields' => [
            'code' => 'Código de autenticación',
            'recovery_code' => 'Código de recuperación',
        ],
        'placeholders' => [
            'code' => '123456',
            'recovery_code' => 'xxxxxxxx-xxxxxxxx',
        ],
        'submit' => 'Verificar',
        'submit_processing' => 'Verificando...',
        'links' => [
            'use_recovery' => 'Usar un código de recuperación',
            'use_code' => 'Usar mi aplicación de autenticación',
        ],
        'footer' => 'Los códigos caducan cada 30 segundos. Si el tuyo falla siempre, revisa la hora de tu dispositivo.',
    ],

    'confirm_password' => [
        'badge' => 'Área segura',
        'title' => [
            'line_1' => 'Confirma tu contraseña',
            'highlight' => 'antes de continuar',
        ],
        'description' => 'Esta acción cambia la configuración de seguridad de tu cuenta, así que te pedimos la contraseña una vez más.',
        'fields' => [
            'password' => 'Contraseña',
        ],
        'placeholders' => [
            'password' => 'Tu contraseña actual',
        ],
        'submit' => 'Confirmar',
        'submit_processing' => 'Confirmando...',
        'footer' => 'No volveremos a pedírtela durante unas horas.',
    ],
    'onboarding' => [
        'step_label' => 'Paso :number',
        'steps' => [
            1 => [
                'title' => 'Verificación de correo',
                'done' => 'Completado — tu correo está confirmado',
                'current' => 'Pendiente — confirma tu correo electrónico',
                'future' => 'Confirma tu correo electrónico',
            ],
            2 => [
                'title' => 'Aprobación del administrador',
                'done' => 'Completado — tu cuenta fue aprobada',
                'current' => 'Pendiente — un administrador está revisando tu solicitud',
                'future' => 'Un administrador revisa tu solicitud',
            ],
            3 => [
                'title' => 'Acceso al panel',
                'done' => 'Completado — tienes acceso completo',
                'current' => 'Listo — inicia sesión para continuar',
                'future' => 'Disponible cuando tu cuenta sea aprobada',
            ],
        ],
    ],

    'verify_email' => [
        'title' => 'Verifica tu correo electrónico',
        'resend' => 'Reenviar correo de verificación',
        'resend_processing' => 'Enviando...',
        'help' => [
            'title' => 'Qué tienes que hacer',
            'items' => [
                'Revisa tu bandeja de entrada',
                'Busca un correo de YHN Dev Side',
                'Abre el enlace de verificación que contiene',
                'Si no aparece, revisa tu carpeta de spam',
            ],
            'expiry' => 'El enlace caduca en 24 horas. Usa el botón de arriba si necesitas uno nuevo.',
        ],
        'toast' => [
            'sent' => 'Correo de verificación reenviado',
        ],
    ],

    'waiting_approval' => [
        'title' => 'Pendiente de aprobación',
        'headline' => 'Tu cuenta está en revisión',
        'body' => 'Tu correo electrónico está confirmado. Ahora un administrador revisa la solicitud antes de abrir la cuenta.',
        'notice' => 'Recibirás un correo cuando la cuenta sea aprobada. Suele tardar unas horas.',
    ],
];
