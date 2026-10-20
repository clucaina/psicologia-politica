<?php
// Copia este archivo a config.php. Nunca publiques ni compartas config.php.
return [
    'site_url' => 'https://psicologiapolitica.es',
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'NOMBRE_COMPLETO_DE_LA_BASE',
        'user' => 'USUARIO_COMPLETO_DE_LA_BASE',
        'password' => 'CONTRASENA_MYSQL',
        'socket' => '',
    ],
    // Escribe aquí una frase aleatoria de al menos 32 caracteres antes de instalar.
    // Se solicita en /install.php y deja de servir al terminar la instalación.
    'setup_key' => 'CAMBIA_ESTA_CLAVE_ANTES_DE_INSTALAR',
    'mail' => [
        'transport' => 'smtp',
        'host' => 'smtp.hostinger.com',
        'port' => 465,
        'encryption' => 'ssl', // ssl en 465; tls en 587
        'username' => 'info@psicologiapolitica.es',
        'password' => 'CONTRASENA_DEL_BUZON',
        'from' => 'info@psicologiapolitica.es',
        'from_name' => 'Psicología Política',
    ],
    'contact_email' => 'info@psicologiapolitica.es',
    'registration_enabled' => true,
    'debug' => false,
];
