<?php

// Ensure BaseMigration.php is loaded
require_once __DIR__ . '/database/generator/BaseMigration.php';

return (function () {
    $createEnvironmentConfig = function () {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $dbname = getenv('DB_DATABASE') ?: 'test';
        $username = getenv('DB_USERNAME') ?: 'root';
        $password = getenv('DB_PASSWORD') ?: '';
        $port = getenv('DB_PORT') ?: 3306;

        $sslCaPath = null;
        $sslCaFromEnv = getenv('DB_SSL_CA_PATH');
        $sslCaBase64 = getenv('DB_SSL_CA_B64');
        $defaultProdCa = '/etc/ssl/certs/ca-certificates.crt';

        if ($sslCaFromEnv && file_exists($sslCaFromEnv)) {
            $sslCaPath = $sslCaFromEnv;
        } elseif ($sslCaBase64) {
            $tmpPath = sys_get_temp_dir() . '/tidb-ca-cert.pem';
            file_put_contents($tmpPath, base64_decode($sslCaBase64));
            $sslCaPath = $tmpPath;
        } elseif ((getenv('APP_ENV') ?: '') === 'production' && file_exists($defaultProdCa)) {
            $sslCaPath = $defaultProdCa;
        }

        if (!$sslCaPath || !file_exists($sslCaPath)) {
            $stderr = defined('STDERR') ? STDERR : fopen('php://stderr', 'w');
            fwrite($stderr, "[phinx] Advertencia: no se pudo resolver el certificado SSL CA. La conexión fallará en TiDB Cloud." . PHP_EOL);
            if (!defined('STDERR')) {
                fclose($stderr);
            }
        }

        $config = [
            'adapter' => 'mysql',
            'host' => $host,
            'name' => $dbname,
            'user' => $username,
            'pass' => $password,
            'port' => (int)$port,
            'charset' => 'utf8mb4',
        ];

        if ($sslCaPath && file_exists($sslCaPath)) {
            $config['mysql_attr_ssl_ca'] = $sslCaPath;
        }

        return $config;
    };

    return [
        'paths' => [
            'migrations' => '%%PHINX_CONFIG_DIR%%/database/migrations',
            'seeds' => '%%PHINX_CONFIG_DIR%%/database/seeds',
        ],
        'environments' => [
            'default_migration_table' => 'phinxlog',
            'default_environment' => getenv('APP_ENV') ?: 'development',
            'development' => $createEnvironmentConfig(),
            'production' => $createEnvironmentConfig(),
        ],
        'version_order' => 'creation',
    ];
})();
