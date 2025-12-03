<?php

namespace Conduit\Services\PhotosDrive;

use Google_Client;
use Google_Service_Drive;
use Google_Service_Drive_DriveFile;

class GoogleDriveService
{
    private $driveService;

    public function __construct()
    {
        // ✅ Leer credenciales desde variables de entorno
        // Intentar getenv() primero, luego $_ENV, luego $_SERVER
        $clientId = $this->getEnvVar('GOOGLE_CLIENT_ID');
        $clientSecret = $this->getEnvVar('GOOGLE_CLIENT_SECRET');
        $accessToken = $this->getEnvVar('GOOGLE_ACCESS_TOKEN');
        $refreshToken = $this->getEnvVar('GOOGLE_REFRESH_TOKEN');
        
        error_log('[GoogleDrive] CLIENT_ID: ' . substr($clientId, 0, 20) . '...');
        error_log('[GoogleDrive] ACCESS_TOKEN: ' . (strlen($accessToken) > 20 ? substr($accessToken, 0, 20) . '...' : 'VACÍO'));
        error_log('[GoogleDrive] REFRESH_TOKEN: ' . (strlen($refreshToken) > 20 ? substr($refreshToken, 0, 20) . '...' : 'VACÍO'));
        
        // Verificar que las variables existan y no estén vacías
        if (empty($clientId) || empty($clientSecret)) {
            throw new \Exception(
                'GOOGLE_CLIENT_ID y GOOGLE_CLIENT_SECRET no están configurados. ' .
                'Verifica las variables de entorno en Docker.'
            );
        }
        
        // IMPORTANTE: Validar tokens antes de usarlos
        if (empty($accessToken)) {
            throw new \Exception(
                'GOOGLE_ACCESS_TOKEN no está configurado o está vacío. ' .
                'Debes obtener un token válido de Google OAuth 2.0'
            );
        }

        if (empty($refreshToken)) {
            throw new \Exception(
                'GOOGLE_REFRESH_TOKEN no está configurado o está vacío. ' .
                'Necesitas ambos tokens para funcionar correctamente'
            );
        }
        
        // Inicializar el cliente de Google
        $client = new Google_Client();
        
        // ✅ Configurar credenciales desde variables de entorno
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setScopes([Google_Service_Drive::DRIVE_FILE]);
        
        // ✅ Configurar token desde variables de entorno (validado)
        $token = [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => 3599,
            'scope' => 'https://www.googleapis.com/auth/drive.file',
            'token_type' => 'Bearer',
            'created' => (int)$this->getEnvVar('GOOGLE_TOKEN_CREATED') ?: time()
        ];
        
        error_log('[GoogleDrive] Token array creado: ' . json_encode([
            'access_token' => substr($token['access_token'], 0, 20) . '...',
            'refresh_token' => substr($token['refresh_token'], 0, 20) . '...',
            'created' => $token['created']
        ]));
        
        try {
            $client->setAccessToken($token);
            error_log('[GoogleDrive] Token configurado exitosamente en cliente');
        } catch (\Exception $e) {
            throw new \Exception('Error al configurar el token de Google: ' . $e->getMessage());
        }
        
        // Refrescar token si expiró
        if ($client->isAccessTokenExpired()) {
            error_log('[GoogleDrive] Token expirado, intentando refrescar...');
            
            if (!$client->getRefreshToken()) {
                throw new \Exception(
                    'Token expirado sin refresh_token válido. ' .
                    'Verifica GOOGLE_REFRESH_TOKEN en .env'
                );
            }
            
            try {
                $newToken = $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                
                // Validar que se obtuvo un nuevo token
                if (!isset($newToken['access_token'])) {
                    throw new \Exception('No se recibió un nuevo access_token del servidor de Google');
                }
                
                // Mantener refresh_token si no viene en la respuesta
                if (!isset($newToken['refresh_token'])) {
                    $newToken['refresh_token'] = $refreshToken;
                }
                
                // ⚠️ IMPORTANTE: Guardar el nuevo token en variables de entorno
                putenv('GOOGLE_ACCESS_TOKEN=' . $newToken['access_token']);
                putenv('GOOGLE_TOKEN_CREATED=' . time());
                
                $client->setAccessToken($newToken);
                
                error_log('[GoogleDrive] Token refrescado automáticamente');
            } catch (\Exception $e) {
                throw new \Exception('Error al refrescar token: ' . $e->getMessage());
            }
        }
        
        // Crear servicio de Drive
        $this->driveService = new Google_Service_Drive($client);
        error_log('[GoogleDrive] Servicio de Google Drive inicializado correctamente');
    }

    /**
     * Obtiene una variable de entorno desde múltiples fuentes
     */
    private function getEnvVar($varName)
    {
        // Intentar getenv() primero
        $value = getenv($varName);
        if ($value !== false) {
            return $value;
        }
        
        // Intentar $_ENV
        if (isset($_ENV[$varName])) {
            return $_ENV[$varName];
        }
        
        // Intentar $_SERVER
        if (isset($_SERVER[$varName])) {
            return $_SERVER[$varName];
        }
        
        return '';
    }

    /**
     * Sube un archivo a Google Drive y retorna el ID del archivo
     */
    public function uploadFile($filePath, $fileName, $folderId = null)
    {
        try {
            // Verificar que el archivo existe
            if (!file_exists($filePath)) {
                throw new \Exception("El archivo no existe: $filePath");
            }

            // Preparar metadata del archivo
            $metadata = ['name' => $fileName];
            
            // Si se especifica una carpeta, agregar como parent
            if ($folderId) {
                $metadata['parents'] = [$folderId];
            }
            
            $fileMetadata = new Google_Service_Drive_DriveFile($metadata);

            // Leer contenido del archivo
            $content = file_get_contents($filePath);
            $mimeType = mime_content_type($filePath);

            // Subir archivo a Google Drive
            $file = $this->driveService->files->create($fileMetadata, [
                'data' => $content,
                'mimeType' => $mimeType,
                'uploadType' => 'multipart',
                'fields' => 'id'
            ]);

            // Hacer el archivo público (compartido con cualquiera)
            $this->makeFilePublic($file->id);

            return $file->id;

        } catch (\Exception $e) {
            error_log("Error en GoogleDriveService::uploadFile - " . $e->getMessage());
            throw new \Exception("Error al subir archivo a Google Drive: " . $e->getMessage());
        }
    }

    /**
     * Crea una carpeta en Google Drive y retorna su ID
     */
    public function createFolder($folderName, $parentFolderId = null)
    {
        try {
            $metadata = [
                'name' => $folderName,
                'mimeType' => 'application/vnd.google-apps.folder'
            ];
            
            if ($parentFolderId) {
                $metadata['parents'] = [$parentFolderId];
            }
            
            $fileMetadata = new Google_Service_Drive_DriveFile($metadata);
            
            $folder = $this->driveService->files->create($fileMetadata, [
                'fields' => 'id'
            ]);
            
            return $folder->id;
            
        } catch (\Exception $e) {
            error_log("Error al crear carpeta: " . $e->getMessage());
            throw new \Exception("Error al crear carpeta en Google Drive: " . $e->getMessage());
        }
    }

    /**
     * Busca una carpeta por nombre y retorna su ID
     */
    public function findFolderByName($folderName)
    {
        try {
            $response = $this->driveService->files->listFiles([
                'q' => "mimeType='application/vnd.google-apps.folder' and name='" . addslashes($folderName) . "' and trashed=false",
                'fields' => 'files(id, name)',
                'pageSize' => 1
            ]);
            
            $files = $response->getFiles();
            return !empty($files) ? $files[0]->getId() : null;
            
        } catch (\Exception $e) {
            error_log("Error al buscar carpeta: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtiene o crea una carpeta (si no existe)
     */
    public function getOrCreateFolder($folderName)
    {
        $folderId = $this->findFolderByName($folderName);
        
        if (!$folderId) {
            $folderId = $this->createFolder($folderName);
        }
        
        return $folderId;
    }

    /**
     * Hace un archivo público (compartido con cualquiera que tenga el enlace)
     */
    private function makeFilePublic($fileId)
    {
        try {
            $permission = new \Google_Service_Drive_Permission([
                'type' => 'anyone',
                'role' => 'reader'
            ]);

            $this->driveService->permissions->create($fileId, $permission);
        } catch (\Exception $e) {
            error_log("Advertencia: No se pudo hacer el archivo público - " . $e->getMessage());
        }
    }

    /**
     * Elimina un archivo de Google Drive
     */
    public function deleteFile($fileId)
    {
        try {
            $this->driveService->files->delete($fileId);
            return true;
        } catch (\Exception $e) {
            error_log("Error al eliminar archivo de Drive: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene información de un archivo
     */
    public function getFileInfo($fileId)
    {
        try {
            return $this->driveService->files->get($fileId, [
                'fields' => 'id, name, webViewLink, webContentLink, mimeType, size'
            ]);
        } catch (\Exception $e) {
            error_log("Error al obtener info del archivo: " . $e->getMessage());
            return null;
        }
    }
}