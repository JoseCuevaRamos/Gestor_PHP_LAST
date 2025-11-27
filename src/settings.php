<?php

// Define root path
defined('DS') ?: define('DS', DIRECTORY_SEPARATOR);
defined('ROOT') ?: define('ROOT', dirname(__DIR__) . DS);

// Load .env file
if (file_exists(ROOT . '.env')) {
    $dotenv = new Dotenv\Dotenv(ROOT);
    $dotenv->load();
}

// Resolver certificado SSL CA desde variables de entorno (ruta o base64)
$ssl_ca_path = null;
$ssl_ca_env_path = getenv('DB_SSL_CA_PATH') ?: getenv('DB_SSL_CA');
$ssl_ca_b64 = getenv('DB_SSL_CA_B64');

if ($ssl_ca_env_path && file_exists($ssl_ca_env_path)) {
    $ssl_ca_path = $ssl_ca_env_path;
} elseif ($ssl_ca_b64) {
    $target = sys_get_temp_dir() . '/ca-cert.pem';
    file_put_contents($target, base64_decode($ssl_ca_b64));
    $ssl_ca_path = $target;
} elseif ((getenv('APP_ENV') ?: '') === 'production' && file_exists('/etc/ssl/certs/ca-certificates.crt')) {
    $ssl_ca_path = '/etc/ssl/certs/ca-certificates.crt';
}

return [
    'settings' => [
        'displayErrorDetails'    => getenv('APP_DEBUG') === 'true', // set to false in production
        'addContentLengthHeader' => false, // Allow the web server to send the content-length header

        // App Settings
        'app'                    => [
            'name' => getenv('APP_NAME'),
            'url'  => getenv('APP_URL'),
            'env'  => getenv('APP_ENV'),
        ],

        // Renderer settings
        'renderer'               => [
            'template_path' => __DIR__ . '/../templates/',
        ],

        // Monolog settings
        'logger'                 => [
            'name'  => getenv('APP_NAME'),
            'path'  => isset($_ENV['docker']) ? 'php://stdout' : __DIR__ . '/../logs/app.log',
            'level' => \Monolog\Logger::DEBUG,
        ],

        // Database settings
        'database'               => [
            'driver'    => getenv('DB_CONNECTION'),
            'host'      => getenv('DB_HOST'),
            'database'  => getenv('DB_DATABASE'),
            'username'  => getenv('DB_USERNAME'),
            'password'  => getenv('DB_PASSWORD'),
            'port'      => getenv('DB_PORT'),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
            'options'   => $ssl_ca_path ? [
                PDO::MYSQL_ATTR_SSL_CA => $ssl_ca_path,
            ] : [],
        ],

        'cors' => null !== getenv('CORS_ALLOWED_ORIGINS') ? getenv('CORS_ALLOWED_ORIGINS') : '*',

        // jwt settings
        'jwt'  => [
            'secret' => getenv('JWT_SECRET'),
            'secure' => false,
            "header" => "Authorization",
            "regexp" => "/Token\s+(.*)$/i",
            'passthrough' => ['OPTIONS']
        ],
    ],
];
