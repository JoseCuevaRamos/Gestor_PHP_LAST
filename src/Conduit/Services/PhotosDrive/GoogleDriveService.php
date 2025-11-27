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
        $clientId = getenv('GOOGLE_CLIENT_ID');
        $clientSecret = getenv('GOOGLE_CLIENT_SECRET');
        $accessToken = getenv('GOOGLE_ACCESS_TOKEN');
        $refreshToken = getenv('GOOGLE_REFRESH_TOKEN');
        
        // Verificar que las variables existan
        if (!$clientId || !$clientSecret) {
            throw new \Exception('GOOGLE_CLIENT_ID y GOOGLE_CLIENT_SECRET deben estar configurados en .env');
        }
        
        if (!$accessToken || !$refreshToken) {
            throw new \Exception('GOOGLE_ACCESS_TOKEN y GOOGLE_REFRESH_TOKEN deben estar configurados en .env');
        }
        
        // Inicializar el cliente de Google
        $client = new Google_Client();
        
        // ✅ Configurar credenciales desde variables de entorno (en lugar de archivo JSON)
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setScopes([Google_Service_Drive::DRIVE_FILE]);
        
        // ✅ Configurar token desde variables de entorno (en lugar de archivo JSON)
        $token = [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => 3599,
            'scope' => 'https://www.googleapis.com/auth/drive.file',
            'token_type' => 'Bearer',
            'created' => (int)getenv('GOOGLE_TOKEN_CREATED') ?: time()
        ];
        
        $client->setAccessToken($token);
        
        // Refrescar token si expiró
        if ($client->isAccessTokenExpired()) {
            if ($client->getRefreshToken()) {
                $newToken = $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                
                // Mantener refresh_token si no viene en la respuesta
                if (!isset($newToken['refresh_token'])) {
                    $newToken['refresh_token'] = $refreshToken;
                }
                
                // ⚠️ IMPORTANTE: Aquí deberías guardar el nuevo token
                // Por ahora solo actualiza en memoria (dura solo esta ejecución)
                putenv('GOOGLE_ACCESS_TOKEN=' . $newToken['access_token']);
                putenv('GOOGLE_TOKEN_CREATED=' . time());
                
                $client->setAccessToken($newToken);
                
                error_log('[GoogleDrive] Token refrescado automáticamente');
            } else {
                throw new \Exception('Token expirado sin refresh_token. Verifica GOOGLE_REFRESH_TOKEN en .env');
            }
        }
        
        // Crear servicio de Drive
        $this->driveService = new Google_Service_Drive($client);
    }

    /**
     * Sube un archivo a Google Drive y retorna el ID del archivo
     *
     * @param string $filePath Ruta local del archivo
     * @param string $fileName Nombre del archivo en Drive
     * @param string|null $folderId ID de la carpeta destino (opcional)
     * @return string ID del archivo en Google Drive
     * @throws \Exception
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
     *
     * @param string $folderName Nombre de la carpeta
     * @param string|null $parentFolderId ID de la carpeta padre (opcional)
     * @return string ID de la carpeta creada
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
     *
     * @param string $folderName Nombre de la carpeta
     * @return string|null ID de la carpeta o null si no existe
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
     *
     * @param string $folderName Nombre de la carpeta
     * @return string ID de la carpeta
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
     *
     * @param string $fileId ID del archivo en Drive
     * @return void
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
            // No lanzamos excepción porque el archivo ya se subió exitosamente
        }
    }

    /**
     * Elimina un archivo de Google Drive
     *
     * @param string $fileId ID del archivo en Drive
     * @return bool
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
     *
     * @param string $fileId ID del archivo en Drive
     * @return Google_Service_Drive_DriveFile|null
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