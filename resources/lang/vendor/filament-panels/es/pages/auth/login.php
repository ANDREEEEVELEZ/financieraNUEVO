<?php

return [

    'title' => 'Acceso',

    'heading' => '', // Elimine el encabezado para que no se muestre en la página de inicio de sesión.

    'actions' => [

        'register' => [
            'before' => 'o',
            'label' => 'Abrir una cuenta',
        ],

        'request_password_reset' => [
            'label' => '¿Ha olvidado su contraseña?',
        ],

    ],

    'form' => [

        'email' => [
            'label' => 'Correo electrónico',
        ],

        'password' => [
            'label' => 'Contraseña',
        ],

        'remember' => [
            'label' => 'Recordarme',
        ],

        'actions' => [

            'authenticate' => [
                'label' => 'Iniciar sesión',
            ],

        ],

    ],

    'messages' => [

        'failed' => 'Estas credenciales no coinciden con nuestros registros.',

    ],

    'notifications' => [

        'throttled' => [
            'title' => 'Demasiados intentos. Intente de nuevo en :seconds segundos.',
            'body' => 'Intente de nuevo en :seconds segundos.',
        ],

    ],

];
