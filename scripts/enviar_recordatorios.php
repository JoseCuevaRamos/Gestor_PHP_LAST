<?php

use Carbon\Carbon;
use Conduit\Models\Tarea;
use Conduit\Services\Mail\NotificacionTareaService;
use Illuminate\Database\Capsule\Manager as Capsule;

// 1) Autoload de Composer
require __DIR__ . '/../vendor/autoload.php';

// ✅ CARGAR VARIABLES DE ENTORNO (compatible con versiones viejas)
if (file_exists(__DIR__ . '/../.env')) {
    try {
        $dotenv = new \Dotenv\Dotenv(__DIR__ . '/..');
        $dotenv->load();
    } catch (\Throwable $e) {
        // Si falla, Render ya tiene las variables cargadas
        echo "[INFO] .env no cargado, usando variables de Render\n";
    }
}

echo "[INFO] ============================================\n";
echo "[INFO] Iniciando script de recordatorios\n";
echo "[INFO] APP_ENV: " . (getenv('APP_ENV') ?: 'local') . "\n";
echo "[INFO] APP_URL: " . (getenv('APP_URL') ?: 'http://localhost') . "\n";
echo "[INFO] DB_HOST: " . (getenv('DB_HOST') ?: 'db') . "\n";
echo "[INFO] ============================================\n\n";

/**
 * 2) Configurar Eloquent manualmente para este script CLI
 */
$capsule = new Capsule;

// ✅ CONFIGURACIÓN COMPLETA CON SSL PARA TIDB
$dbConfig = [
    'driver'    => 'mysql',
    'host'      => getenv('DB_HOST') ?: 'db',
    'database'  => getenv('DB_DATABASE') ?: 'conduit',
    'username'  => getenv('DB_USERNAME') ?: 'root',
    'password'  => getenv('DB_PASSWORD') ?: '',
    'port'      => (int)(getenv('DB_PORT') ?: 3306),
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix'    => '',
];

// ✅ CONFIGURAR SSL SI ESTÁ EN PRODUCCIÓN O SI HAY CERTIFICADO
if (getenv('APP_ENV') === 'production' || getenv('DB_SSL_CA_B64')) {
    echo "[INFO] Detectado entorno de producción o certificado SSL\n";
    
    if (getenv('DB_SSL_CA_B64')) {
        echo "[INFO] Configurando SSL con certificado TiDB...\n";
        
        // Decodificar certificado base64
        $certContent = base64_decode(getenv('DB_SSL_CA_B64'));
        if ($certContent === false) {
            echo "[ERROR] No se pudo decodificar el certificado SSL\n";
            exit(1);
        }
        
        // Guardar en archivo temporal
        $certPath = sys_get_temp_dir() . '/tidb-ca-' . uniqid() . '.crt';
        if (file_put_contents($certPath, $certContent) === false) {
            echo "[ERROR] No se pudo escribir el certificado SSL en: {$certPath}\n";
            exit(1);
        }
        
        echo "[INFO] Certificado guardado en: {$certPath}\n";
        
        // ✅ AÑADIR OPCIONES SSL (compatible con PHP 8.1)
        $dbConfig['options'] = [
            \PDO::MYSQL_ATTR_SSL_CA => $certPath,
        ];
        
        echo "[INFO] SSL habilitado: SSL_CA={$certPath}\n";
    } else {
        echo "[INFO] APP_ENV es producción pero no hay certificado SSL\n";
        echo "[WARN] Intentando conectar sin SSL (puede fallar en TiDB)\n";
    }
} else {
    echo "[INFO] Modo desarrollo - SSL opcional\n";
}

$capsule->addConnection($dbConfig);
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
        $pdo = $capsule->getConnection()->getPdo();
        
        echo "[✅] Conexión a BD exitosa en intento " . ($attempt + 1) . "\n";
        echo "[INFO] Base de datos: " . getenv('DB_DATABASE') . "\n";
        echo "[INFO] Usuario: " . getenv('DB_USERNAME') . "\n\n";
        break;
        
    } catch (\PDOException $e) {
        $attempt++;
        
        $errorMsg = $e->getMessage();
        echo "[⚠️  INTENTO {$attempt}/{$maxAttempts}] Error de conexión:\n";
        echo "    Código: " . $e->getCode() . "\n";
        echo "    Mensaje: {$errorMsg}\n\n";
        
        if ($attempt >= $maxAttempts) {
            echo "[❌ FATAL] No se pudo establecer conexión con la BD después de {$maxAttempts} intentos\n";
            echo "[❌] Verificar:\n";
            echo "    1. DB_HOST, DB_PORT, DB_DATABASE son correctos\n";
            echo "    2. DB_USERNAME y DB_PASSWORD son válidos\n";
            echo "    3. DB_SSL_CA_B64 está en base64 correcto (si es producción)\n";
            echo "    4. APP_ENV=production está seteado\n";
            exit(1);
        }
        
        echo "[INFO] Reintentando en 3 segundos...\n\n";
        sleep(3);
    }
}

/**
 * 4) Lógica de recordatorios con zona horaria de Perú
 */

$nowPeru    = Carbon::now('America/Lima');
$mananaPeru = $nowPeru->copy()->addDay()->toDateString();

echo "[INFO] ============================================\n";
echo "[INFO] Buscando tareas que vencen el {$mananaPeru}\n";
echo "[INFO] Zona horaria: America/Lima\n";
echo "[INFO] ============================================\n\n";

$notifier = new NotificacionTareaService();

try {
    $tareas = Tarea::activas()
        ->whereDate('due_at', $mananaPeru)
        ->whereNull('completed_at')
        ->whereNotNull('id_asignado')
        ->with(['asignado', 'proyecto', 'columna'])
        ->get();
    
    echo "[INFO] Se encontraron " . $tareas->count() . " tareas para recordar\n\n";
    
    $countEnviados = 0;
    $countError = 0;
    
    foreach ($tareas as $tarea) {
        $usuario = $tarea->asignado;
        
        if (!$usuario || empty($usuario->correo)) {
            echo "[SKIP] Tarea #{$tarea->id_tarea} ({$tarea->titulo}) - Usuario sin correo\n";
            continue;
        }
        
        try {
            echo "[ENVIANDO] Tarea #{$tarea->id_tarea}\n";
            echo "           Título: {$tarea->titulo}\n";
            echo "           Para: {$usuario->correo}\n";
            
            $notifier->enviarRecordatorioVencimiento($tarea);
            
            echo "           ✅ ENVIADO\n\n";
            $countEnviados++;
            
        } catch (\Throwable $e) {
            echo "           ❌ ERROR: {$e->getMessage()}\n\n";
            $countError++;
        }
    }
    
    echo "[✅ PROCESO COMPLETADO]\n";
    echo "    Recordatorios enviados: {$countEnviados}\n";
    echo "    Errores: {$countError}\n";
    echo "    Total procesadas: " . ($countEnviados + $countError) . "\n\n";
    
    exit(0);
    
} catch (\Throwable $e) {
    echo "[❌ ERROR CRÍTICO]\n";
    echo "    Mensaje: {$e->getMessage()}\n";
    echo "    Archivo: {$e->getFile()}\n";
    echo "    Línea: {$e->getLine()}\n";
    echo "    Stack trace:\n";
    
    foreach (explode("\n", $e->getTraceAsString()) as $line) {
        echo "    {$line}\n";
    }
    
    exit(1);
}