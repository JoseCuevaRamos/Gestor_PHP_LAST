<?php

namespace Conduit\Controllers\Perfil;

use Conduit\Models\User;
use Conduit\Transformers\UserTransformer;
use League\Fractal\Resource\Item;
use Slim\Http\Request;
use Slim\Http\Response;

class PerfilController
{
    protected $auth;
    protected $validator;
    protected $db;
    protected $fractal;

    public function __construct(\Slim\Container $container)
    {
        $this->auth = $container->get('auth');
        $this->validator = $container->get('validator');
        $this->db = $container->get('db');
        $this->fractal = $container->get('fractal');
    }

    /**
     * Actualizar perfil (nombre y correo)
     */
    public function updatePerfil(Request $request, Response $response, $args)
    {
        // === Obtener usuario autenticado desde JWT ===
        $jwt = $request->getAttribute('jwt');
        $id_usuario_actual = isset($jwt['sub']) ? (int)$jwt['sub'] : null;

        if ($id_usuario_actual === null) {
            return $response->withJson(['error' => 'No autorizado.'], 401);
        }

        $id_usuario_param = (int)($args['id_usuario'] ?? 0);

        // === Validar que solo el dueño del token pueda editar su perfil ===
        if ($id_usuario_actual !== $id_usuario_param) {
            return $response->withJson(['error' => 'No tiene permiso para editar este perfil.'], 403);
        }

        // === Buscar usuario activo ===
        $user = User::where('id_usuario', $id_usuario_actual)
            ->where('status', '0')
            ->first();

        if (!$user) {
            return $response->withJson(['error' => 'Usuario no encontrado o inactivo.'], 404);
        }

        // === Verificar si es usuario temporal ===
        if ($user->nombre === 'Temporal' && is_null($user->dni)) {
            return $response->withJson(['error' => 'Los usuarios temporales no pueden editar su perfil.'], 403);
        }

        // === Obtener datos del request ===
        $params = $request->getParam('user');
        $nombre = $params['nombre'] ?? null;
        $correo = $params['correo'] ?? null;

        // === Validar nombre ===
        if (!$nombre || preg_match('/\s/', $nombre) || strlen($nombre) < 1 || strlen($nombre) > 25) {
            return $response->withJson([
                'errors' => [
                    'nombre' => ['El nombre no debe contener espacios y debe tener entre 1 y 25 caracteres.']
                ]
            ], 422);
        }

        // === Validar correo ===
        if (!$correo || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return $response->withJson([
                'errors' => ['correo' => ['Debe ingresar un correo válido.']]
            ], 422);
        }

        // === Validar que el correo no esté siendo usado por otro usuario ===
        $correoExiste = User::where('correo', $correo)
            ->where('id_usuario', '!=', $id_usuario_actual)
            ->where('status', '0')
            ->exists();

        if ($correoExiste) {
            return $response->withJson([
                'errors' => ['correo' => ['El correo ingresado ya está registrado.']]
            ], 422);
        }

        // === Actualizar perfil ===
        $user->update([
            'nombre' => trim($nombre),
            'correo' => trim($correo),
        ]);

        // === Preparar respuesta ===
        $data = $this->fractal->createData(new Item($user, new UserTransformer()))->toArray();

        return $response->withJson([
            'message' => 'Perfil actualizado correctamente.',
            'user' => $data,
        ], 200);
    }
}
