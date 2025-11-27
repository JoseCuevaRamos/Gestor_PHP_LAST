<?php

use Carbon\Carbon;
use Conduit\Models\Tarea;
use Conduit\Services\Mail\NotificacionTareaService;
use Illuminate\Database\Capsule\Manager as Capsule;

// 1) Autoload de Composer
require __DIR__ . '/../vendor/autoload.php';

/**
 * 2) Configurar Eloquent manualmente para este script CLI
 */
$capsule = new Capsule;

$capsule->addConnection([
    'driver'    => 'mysql',
    'host'      => getenv('DB_HOST') ?: 'db',
    'database'  => getenv('DB_DATABASE') ?: 'conduit',
    'username'  => getenv('DB_USERNAME') ?: 'root',
    'password'  => getenv('DB_PASSWORD') ?: '',
    'port'      => getenv('DB_PORT') ?: 3306,
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix'    => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

/**
 * 3) Intentar conectar a la BD con reintentos
 */
$maxAttempts = 5;
$attempt = 0;
while ($attempt < $maxAttempts) {
    try {
        // Fuerza la conexión para ver si la BD está lista
        $capsule->getConnection()->getPdo();
        break; // ✅ Conectó, salimos del bucle
    } catch (\PDOException $e) {
        $attempt++;
        echo "[WARN] No se pudo conectar a la BD (intento {$attempt}/{$maxAttempts}): {$e->getMessage()}\n";
        if ($attempt >= $maxAttempts) {
            echo "[ERROR] No se pudo establecer conexión con la BD después de {$maxAttempts} intentos. Abortando.\n";
            exit(1);
        }
        sleep(5); // Esperar 5 segundos y reintentar
    }
}

/**
 * 4) Lógica de recordatorios con zona horaria de Perú
 */

$nowPeru    = Carbon::now('America/Lima');
$mananaPeru = $nowPeru->copy()->addDay()->toDateString();

echo "[INFO] Buscando tareas que vencen el {$mananaPeru} (hora Perú)...\n";

$notifier = new NotificacionTareaService();

try {
    Tarea::activas()
        ->whereDate('due_at', $mananaPeru)
        ->whereNull('completed_at')
        ->whereNotNull('id_asignado')
        ->with(['asignado', 'proyecto', 'columna'])
        ->chunkById(50, function ($tareas) use ($notifier) {
            foreach ($tareas as $tarea) {
                $usuario = $tarea->asignado;
                if (!$usuario || empty($usuario->correo)) {
                    continue;
                }

                echo "[INFO] Enviando recordatorio para tarea #{$tarea->id_tarea} ({$tarea->titulo}) a {$usuario->correo}\n";
                $notifier->enviarRecordatorioVencimiento($tarea);
            }
        });

    echo "[INFO] Proceso de recordatorios finalizado.\n";
} catch (\Throwable $e) {
    echo "[ERROR] Error ejecutando la consulta de tareas: {$e->getMessage()}\n";
    // No rompemos el loop de cron_loop.sh, solo dejamos constancia
    exit(1);
}
