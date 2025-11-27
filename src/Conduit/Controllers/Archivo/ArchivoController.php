<?php

namespace Conduit\Controllers\Archivo;

use Conduit\Models\Archivo;
use Conduit\Transformers\ArchivoTransformer;
use Conduit\Services\PhotosDrive\GoogleDriveService;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use Slim\Http\Request;
use Slim\Http\Response;
use Respect\Validation\Validator as v;

class ArchivoController
{
    protected $validator;
    protected $db;
    protected $fractal;
    protected $googleDrive;


    // === CONSTANTES ARCHIVOS ===
    const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB
    const DANGEROUS_EXTENSIONS = [
        'exe', 'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phar',
        'js', 'html', 'htm', 'jar', 'bat', 'cmd', 'sh', 'bin', 'app',
        'com', 'scr', 'msi', 'dll', 'so', 'py', 'cgi', 'pl', 'asp', 'aspx'
    ];
    const ALLOWED_IMAGE_EXTENSIONS = ['png', 'jpeg', 'jpg'];

    public function __construct(\Slim\Container $container)
    {
        $this->fractal   = $container->get('fractal');
        $this->validator = $container->get('validator');
        $this->db        = $container->get('db');
        $this->googleDrive = $container->get(GoogleDriveService::class);
    }

    /**
     * Listar todos los archivos de una tarea (solo los activos)
     */
    public function index(Request $request, Response $response, array $args)
    {
        $tareaId = $args['id'];

        $builder = Archivo::where('id_tarea', $tareaId)
                        ->where('status', '0')
                        ->get();

        $data = $this->fractal
            ->createData(new Collection($builder, new ArchivoTransformer()))
            ->toArray();

        return $response->withJson($data);
    }

    /**
     * Mostrar un archivo específico por ID
     */
    public function show(Request $request, Response $response, array $args)
    {
        $archivo = Archivo::where('id', $args['id'])->first();

        if (!$archivo) {
            return $response->withJson(['error' => 'Archivo no encontrado.'], 404);
        }

        if ($archivo->status == '1') {
            return $response->withJson(['message' => 'Este archivo ha sido eliminado.'], 410);
        }

        $data = $this->fractal
            ->createData(new Item($archivo, new ArchivoTransformer()))
            ->toArray();

        return $response->withJson(['archivo' => $data]);
    }

    /**
     * Crear un nuevo archivo y subirlo a Google Drive
     */
    public function store(Request $request, Response $response)
    {
        error_log("========================================");
        error_log("=== SUBIDA DE ARCHIVOS ===");
        
        $uploadedFiles = $request->getUploadedFiles();
        error_log("Archivos recibidos: " . count($uploadedFiles));
        
        $uploadedFile = $uploadedFiles['archivo'] ?? null;

        if (!$uploadedFile || $uploadedFile->getError() !== UPLOAD_ERR_OK) {
            $errorCode = $uploadedFile ? $uploadedFile->getError() : 'NO_FILE';
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'El archivo es demasiado grande (supera upload_max_filesize)',
                UPLOAD_ERR_FORM_SIZE => 'El archivo es demasiado grande (supera MAX_FILE_SIZE)',
                UPLOAD_ERR_PARTIAL => 'El archivo se subió solo parcialmente',
                UPLOAD_ERR_NO_FILE => 'No se subió ningún archivo',
                UPLOAD_ERR_NO_TMP_DIR => 'Falta carpeta temporal en el servidor',
                UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo en disco',
                UPLOAD_ERR_EXTENSION => 'Una extensión de PHP bloqueó la subida',
                'NO_FILE' => 'No se encontró el campo "archivo" en la petición'
            ];
            $errorMsg = $errorMessages[$errorCode] ?? 'Error desconocido al subir el archivo';
            error_log("ERROR AL SUBIR: " . $errorMsg);

            return $response->withJson([
                'error' => 'No se pudo subir el archivo',
                'detalle' => $errorMsg,
                'codigo_error' => $errorCode
            ], 400);
        }

         // Validar tamaño del archivo (5 MB máximo)
        $fileSize = $uploadedFile->getSize();
        if ($fileSize > self::MAX_FILE_SIZE) {
            return $response->withJson([
                'error' => 'Archivo demasiado grande',
                'detalle' => 'El archivo excede el límite máximo de 5 MB.'
            ], 422);
        }

        // Validar extensión peligrosa
        $nombreArchivo = $uploadedFile->getClientFilename();
        $extension = strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION));
        
        if (in_array($extension, self::DANGEROUS_EXTENSIONS)) {
            return $response->withJson([
                'error' => 'Tipo de archivo no permitido',
                'detalle' => 'No se permiten archivos ejecutables o scripts por seguridad.'
            ], 422);
        }

        // === Solo imágenes permitidas ===
        if (!in_array($extension, self::ALLOWED_IMAGE_EXTENSIONS)) {
            return $response->withJson([
                'error' => 'Tipo de archivo no permitido',
                'detalle' => 'Solo se permiten archivos de imagen (PNG, JPEG, JPG).',
                'formatos_permitidos' => self::ALLOWED_IMAGE_EXTENSIONS
            ], 422);
        }

        // Validar datos
        $this->validator->validateArray(
            $data = $request->getParam('archivo', []),
            ['id_tarea' => v::notEmpty()->intVal()]
        );

        if ($this->validator->failed()) {
            return $response->withJson(['errors' => $this->validator->getErrors()], 422);
        }

        // Verificar límite de 3 archivos
        $archivosCount = Archivo::where('id_tarea', $data['id_tarea'])
                                ->where('status', '0')
                                ->count();

        if ($archivosCount >= 3) {
            return $response->withJson(['error' => 'El límite de 3 archivos por tarea ha sido alcanzado.'], 400);
        }

        // Obtener nombre del archivo
        $nombreArchivo = $uploadedFile->getClientFilename();
        $fileNameWithoutExtension = pathinfo($nombreArchivo, PATHINFO_FILENAME);
        $extension = pathinfo($nombreArchivo, PATHINFO_EXTENSION);
        
        // Verificar duplicados y generar nombre único si es necesario
        $existingArchivo = Archivo::where('id_tarea', $data['id_tarea'])
            ->where('archivo_nombre', $nombreArchivo)
            ->where('status', '0')
            ->first();

        if ($existingArchivo) {
            for ($i = 1; $i <= 3; $i++) {
                $newFileName = $fileNameWithoutExtension . "_$i." . $extension;
                $existingArchivo = Archivo::where('id_tarea', $data['id_tarea'])
                    ->where('archivo_nombre', $newFileName)
                    ->where('status', '0')
                    ->first();
                
                if (!$existingArchivo) {
                    $nombreArchivo = $newFileName;
                    break;
                }
            }
        }

        // Guardar archivo temporalmente
        $tempDir = sys_get_temp_dir();
        $tempPath = $tempDir . '/' . uniqid() . '_' . $nombreArchivo;
        
        try {
            error_log("Moviendo archivo a: " . $tempPath);
            $uploadedFile->moveTo($tempPath);
            error_log("Archivo movido exitosamente");

            // Subir a Google Drive
            error_log("Subiendo a Google Drive...");
            
            // Crear o usar carpeta "Archivos Tareas" en Drive
            $folderId = $this->googleDrive->getOrCreateFolder('Archivos Tareas');
            
            // Subir archivo dentro de la carpeta
            $googleDriveFileId = $this->googleDrive->uploadFile($tempPath, $nombreArchivo, $folderId);
            error_log("Archivo subido a Google Drive. ID: " . $googleDriveFileId);

            // Crear enlace público
            $googleDriveUrl = "https://drive.google.com/file/d/$googleDriveFileId/view?usp=sharing";

            // Guardar en base de datos
            $archivo = Archivo::create([
                'id_tarea'      => $data['id_tarea'],
                'archivo_nombre'=> $nombreArchivo,
                'archivo_ruta'  => $googleDriveUrl,
                'status'        => '0',
            ]);

            error_log("Archivo guardado en BD. ID: " . $archivo->id);

            $transformedData = $this->fractal
                ->createData(new Item($archivo, new ArchivoTransformer()))
                ->toArray();

            return $response->withJson([
                'message' => 'Archivo creado y subido a Google Drive exitosamente.',
                'archivo' => $transformedData
            ], 201);

        } catch (\Exception $e) {
            error_log("EXCEPCIÓN: " . $e->getMessage());
            error_log("TRACE: " . $e->getTraceAsString());
            
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
            
            return $response->withJson([
                'error' => 'Error al subir el archivo: ' . $e->getMessage()
            ], 500);
        } finally {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
    }

    /**
     * Actualizar un archivo
     */
    public function update(Request $request, Response $response, array $args)
    {
        $archivo = Archivo::findOrFail($args['id']);
        
        if ($archivo->status == '1') {
            return $response->withJson(['error' => 'No se puede actualizar un archivo eliminado.'], 400);
        }

        $params = $request->getParam('archivo', []);

        $archivo->update([
            'id_tarea'      => $params['id_tarea'] ?? $archivo->id_tarea,
            'archivo_nombre'=> $params['archivo_nombre'] ?? $archivo->archivo_nombre,
            'archivo_ruta'  => $params['archivo_ruta'] ?? $archivo->archivo_ruta,
            'status'        => $params['status'] ?? $archivo->status,
        ]);

        $data = $this->fractal
            ->createData(new Item($archivo, new ArchivoTransformer()))
            ->toArray();

        return $response->withJson(['archivo' => $data]);
    }

    /**
     * Eliminar un archivo (marcarlo como inactivo)
     */
    public function eliminar(Request $request, Response $response, array $args)
    {
        $archivo = Archivo::findOrFail($args['id']);
        
        if ($archivo->status == '1') {
            return $response->withJson(['error' => 'Este archivo ya está eliminado.'], 400);
        }

        $archivo->update(['status' => '1']);

        return $response->withJson(['message' => 'Archivo eliminado con éxito.'], 200);
    }
}
