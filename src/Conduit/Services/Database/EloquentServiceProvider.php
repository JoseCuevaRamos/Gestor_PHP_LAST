<?php

namespace Conduit\Services\Database;

use Illuminate\Database\Capsule\Manager;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class EloquentServiceProvider implements ServiceProviderInterface
{

    /**
     * Registers services on the given container.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     *
     * @param Container $pimple A container instance
     */
    public function register(Container $pimple)
    {
        $capsule = new Manager();
        $config = $pimple['settings']['database'];
        $options = isset($config['options']) ? $config['options'] : [];

        if (empty($options)) {
            $sslCaPath = $this->resolveSslCaPath();
            if ($sslCaPath) {
                $options[\PDO::MYSQL_ATTR_SSL_CA] = $sslCaPath;
                error_log('[db] Using SSL CA for Eloquent at ' . $sslCaPath);
            }
                error_log('[db] WARNING: SSL CA path not resolved for Eloquent connection.');
        }

        $capsule->addConnection([
            'driver'    => $config['driver'],
            'host'      => $config['host'],
            'database'  => $config['database'],
            'username'  => $config['username'],
            'password'  => $config['password'],
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
            'port'      => isset($config['port']) ? $config['port'] : null,
            'options'   => $options,
        ]);

// Make this Capsule instance available globally via static methods... (optional)
        $capsule->setAsGlobal();

// Setup the Eloquent ORM... (optional; unless you've used setEventDispatcher())
        $capsule->bootEloquent();


        $pimple['db'] = function ($c) use ($capsule) {

            return $capsule;
        };
    }

    private function resolveSslCaPath(): ?string
    {
        $candidates = [
            getenv('DB_SSL_CA_PATH'),
            getenv('DB_SSL_CA'),
        ];

        foreach ($candidates as $path) {
            if ($path && file_exists($path)) {
                return $path;
            }
        }

        $sslCaB64 = getenv('DB_SSL_CA_B64');
        if ($sslCaB64) {
            $target = sys_get_temp_dir() . '/ca-cert-eloquent.pem';
            file_put_contents($target, base64_decode($sslCaB64));
            return $target;
        }

        if ((getenv('APP_ENV') ?: '') === 'production') {
            $fallback = '/etc/ssl/certs/ca-certificates.crt';
            if (file_exists($fallback)) {
                return $fallback;
            }
        }

        return null;
    }
}