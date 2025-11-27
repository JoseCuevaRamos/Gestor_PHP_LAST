<?php
namespace Conduit\Controllers\Comentario;

use Conduit\Models\Comentario;
use Conduit\Models\User;
use Conduit\Transformers\ComentarioTransformer;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class ComentarioController
{
    public function create(Request $request, Response $response, $args)
    {
        $data       = $request->getParsedBody();
        $id_tarea   = $data['id_tarea']   ?? null;
        $id_usuario = $data['id_usuario'] ?? null;
        $contenido  = $data['contenido']  ?? null;

        // Validaciones de ID
        if (!is_numeric($id_tarea) || $id_tarea <= 0) {
            $response->getBody()->write(json_encode(['error' => 'El id_tarea es inválido']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        if (!is_numeric($id_usuario) || $id_usuario <= 0) {
            $response->getBody()->write(json_encode(['error' => 'El id_usuario es inválido']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Validación del contenido
        if (!$contenido || strlen(trim($contenido)) < 1 || strlen($contenido) > 151) {
            $response->getBody()->write(json_encode([
                'error' => 'El contenido es requerido y debe tener entre 1 y 151 caracteres'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $totalComentarios = Comentario::where('id_tarea', $id_tarea)
            ->where('status', '0') 
            ->count();

        if ($totalComentarios >= 10) {
            $response->getBody()->write(json_encode([
                'error' => 'No se pueden agregar más de 10 comentarios a esta tarea'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $comentario = Comentario::create([
            'id_tarea'   => $id_tarea,
            'id_usuario' => $id_usuario,
            'contenido'  => trim($contenido),
            'status'     => '0' 
        ]);

        // ✅ AGREGADO: Obtener usuario para incluir nombre_usuario
        $usuario = User::find($comentario->id_usuario);
        $comentarioData = ComentarioTransformer::transform($comentario);
        $comentarioData['nombre_usuario'] = $usuario ? ($usuario->nombre ?? 'Usuario') : 'Usuario Desconocido';

        $response->getBody()->write(json_encode($comentarioData));
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    }

    public function view(Request $request, Response $response, $args)
    {
        $comentario = Comentario::find($args['id']);
        if (!$comentario) {
            return $response->withStatus(404);
        }

        // ✅ AGREGADO: Incluir nombre_usuario
        $usuario = User::find($comentario->id_usuario);
        $comentarioData = ComentarioTransformer::transform($comentario);
        $comentarioData['nombre_usuario'] = $usuario ? ($usuario->nombre ?? 'Usuario') : 'Usuario Desconocido';

        $response->getBody()->write(json_encode($comentarioData));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function update(Request $request, Response $response, $args)
    {
        $comentario = Comentario::find($args['id']);
        if (!$comentario) {
            $response->getBody()->write(json_encode(['error' => 'Comentario no encontrado']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $data      = $request->getParsedBody();
        $contenido = $data['contenido'] ?? null;

        if (!$contenido || strlen(trim($contenido)) < 1 || strlen($contenido) > 151) {
            $response->getBody()->write(json_encode([
                'error' => 'El contenido es requerido y debe tener entre 1 y 151 caracteres'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $comentario->contenido = $contenido;
        $comentario->save();

        // ✅ AGREGADO: Incluir nombre_usuario
        $usuario = User::find($comentario->id_usuario);
        $comentarioData = ComentarioTransformer::transform($comentario);
        $comentarioData['nombre_usuario'] = $usuario ? ($usuario->nombre ?? 'Usuario') : 'Usuario Desconocido';

        $response->getBody()->write(json_encode($comentarioData));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function delete(Request $request, Response $response, $args)
    {
        $comentario = Comentario::find($args['id']);
        if (!$comentario) {
            return $response->withStatus(404);
        }

        $comentario->status = '1';
        $comentario->save();

        $response->getBody()->write(json_encode(['message' => 'Comentario desactivado']));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    }

    public function list(Request $request, Response $response, $args)
    {
        $id_tarea = $args['id_tarea'] ?? null;

        if (!$id_tarea || !is_numeric($id_tarea)) {
            $response->getBody()->write(json_encode(['error' => 'id_tarea inválido']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $comentarios = Comentario::where('id_tarea', $id_tarea)
            ->where('status', '0')
            ->orderBy('created_at', 'desc')
            ->get();

        $now = new \DateTime();
        $data = array_map(function($comentario) use ($now) {
            $created = new \DateTime($comentario->created_at);
            $minutos = $created->diff($now)->days * 24 * 60 + $created->diff($now)->h * 60 + $created->diff($now)->i;
            $comentario->minutos_desde_creacion = $minutos;
            
            // ✅ AGREGADO: Obtener usuario y agregar nombre_usuario
            $usuario = User::find($comentario->id_usuario);
            $comentarioData = ComentarioTransformer::transform($comentario);
            $comentarioData['nombre_usuario'] = $usuario ? ($usuario->nombre ?? 'Usuario') : 'Usuario Desconocido';
            
            return $comentarioData;
        }, $comentarios->all());

        $response->getBody()->write(json_encode([
            'comentarios' => $data,
            'total' => count($data),
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}