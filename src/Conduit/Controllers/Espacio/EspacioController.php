<?php

namespace Conduit\Controllers\Espacio;

use Conduit\Models\Espacio;
use Conduit\Models\User;
use Conduit\Models\Proyecto;      
use Conduit\Models\Columna;      
use Conduit\Models\Tarea;         
use Conduit\Models\UsuarioRol;   
use Conduit\Transformers\EspacioTransformer;
use Interop\Container\ContainerInterface;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use Slim\Http\Request;
use Slim\Http\Response;
use Conduit\Transformers\UsuarioRolTransformer;
use Respect\Validation\Validator as v;

class EspacioController
{
    /** @var \Conduit\Validation\Validator */
    protected $validator;
    /** @var \Illuminate\Database\Capsule\Manager */
    protected $db;
    /** @var \League\Fractal\Manager */
    protected $fractal;

    public function __construct(\Slim\Container $container)
    {
        $this->fractal   = $container->get('fractal');
        $this->validator = $container->get('validator');
        $this->db        = $container->get('db');
    }


    /**
     * Listar todas los espacios de un usuario
     */
    public function index(Request $request, Response $response, array $args)
    {
        $id_usuario = (int) $args['id'];

        $usuario = User::find($id_usuario);
        if (!$usuario) {
            return $response->withJson(['error' => 'Usuario no encontrado.'], 404);
        }

        // 1. Espacios donde es CREADOR
        $espaciosCreador = Espacio::where('id_usuario', $id_usuario)
                                ->where('status', '0')
                                ->get();

        // 2. Espacios donde es MIEMBRO de proyectos
        $espacioIdsMiembro = UsuarioRol::where('id_usuario', $id_usuario)
            ->where('status', '0')
            ->whereNotNull('id_proyecto')
            ->pluck('id_espacio')
            ->unique()
            ->toArray();

        $espaciosMiembro = [];
        if (!empty($espacioIdsMiembro)) {
            $espaciosMiembro = Espacio::whereIn('id', $espacioIdsMiembro)
                ->where('status', '0')
                ->get();
        }

        // Combinar eliminando duplicados
        $espaciosMap = [];
        foreach ($espaciosCreador as $espacio) {
            $espaciosMap[$espacio->id] = $espacio;
        }
        foreach ($espaciosMiembro as $espacio) {
            if (!isset($espaciosMap[$espacio->id])) {
                $espaciosMap[$espacio->id] = $espacio;
            }
        }
        
        $espacios = array_values($espaciosMap);
        $count = count($espacios);

        if ($count == 0) {
            return $response->withJson([
                'Espacios' => [],
                'EspaciosCount' => 0,
            ]);
        }

        $data = $this->fractal
            ->createData(new Collection($espacios, new EspacioTransformer()))
            ->toArray();

        return $response->withJson([
            'Espacios' => $data['data'],
            'EspaciosCount' => $count
        ]);
    }

    /**
     * Mostrar un espacio por ID
     */
    public function show(Request $request, Response $response, array $args)
    {
        $espacio = Espacio::find($args['id']);

        if (!$espacio) {
            return $response->withJson(['error' => 'Espacio no encontrado.'], 404);
        }

        if ($espacio->status === '1') {
            return $response->withJson(['message' => 'Este espacio ha sido eliminado.'], 410);
        }

        $data = $this->fractal
            ->createData(new Item($espacio, new EspacioTransformer()))
            ->toArray();

        return $response->withJson(['espacio' => $data]);
    }

    /**
     * Crear un nuevo espacio
     */
    public function store(Request $request, Response $response)
    {
        $jwt = $request->getAttribute('jwt');
        if (!$jwt || !isset($jwt['sub'])) {
            return $response->withJson(['error' => 'Token inválido o ausente.'], 401);
        }

        $data = $request->getParam('espacio');
        $data['id_usuario'] = $jwt['sub']; // Asociar el espacio al usuario autenticado

        $this->validator->validateArray(
            $data,
            [
                'nombre'      => v::notEmpty()->stringType()->length(1, 30),
                'descripcion' => v::notEmpty(),
            ]
        );

        if (isset($data['nombre']) && mb_strlen($data['nombre']) > 30) {
            return $response->withJson([
                'error' => 'El nombre del espacio no puede tener más de 30 caracteres.'
            ], 422);
        }

        if ($this->validator->failed()) {
            return $response->withJson(['errors' => $this->validator->getErrors()], 422);
        }

        // Validar nombre único (solo espacios activos)
        $existing = Espacio::where('nombre', $data['nombre'])
            ->where('status', '0')
            ->first();
        if ($existing) {
            return $response->withJson(
                ['error' => 'Ya existe un espacio con este nombre.'],
                400
            );
        }

        // Verificar el número total de espacios activos del usuario
        $proyectosCount = Espacio::where('id_usuario', $data['id_usuario'])
                                ->where('status', '0') // Solo los espacios activos
                                ->count();

        // Limitar a 3 espacios por usuario
        if ($proyectosCount >= 3) {
            return $response->withJson(['error' => 'El límite de 3 espacio ha sido alcanzado.'], 400);
        }

        $espacio = Espacio::create($data);

        $data = $this->fractal
            ->createData(new Item($espacio, new EspacioTransformer()))
            ->toArray();

        return $response->withJson(['espacio' => $data], 201);
    }

    /**
     * Actualizar un espacio
     */
    public function update(Request $request, Response $response, array $args)
    {
        $jwt = $request->getAttribute('jwt');
        if (!$jwt || !isset($jwt['sub'])) {
            return $response->withJson(['error' => 'Token inválido o ausente.'], 401);
        }

        $espacio = Espacio::findOrFail($args['id']);
        $params  = $request->getParam('espacio', []);

        if ($espacio->status === '1') {
            return $response->withJson(['error' => 'No se puede actualizar un espacio eliminado.'], 400);
        }

        // Verificar que el usuario autenticado sea el creador del espacio
        if ($espacio->id_usuario != $jwt['sub']) {
            return $response->withJson(['error' => 'No tienes permiso para actualizar este espacio.'], 403);
        }

        // Verificar longitud del nombre
        if (isset($params['nombre']) && mb_strlen($params['nombre']) > 30) {
            return $response->withJson([
                'error' => 'El nombre del espacio no puede tener más de 30 caracteres.'
            ], 422);
        }

        // Verificar nombre duplicado (excluyendo este espacio)
        $existing = Espacio::where('nombre', $params['nombre'] ?? $espacio->nombre)
            ->where('id', '!=', $espacio->id_espacio)
            ->where('status', '0')
            ->first();

        if ($existing) {
            return $response->withJson(
                ['error' => 'Ya existe un espacio con este nombre.'],
                400
            );
        }

        $espacio->update([
            'nombre'      => $params['nombre']      ?? $espacio->nombre,
            'descripcion' => $params['descripcion'] ?? $espacio->descripcion,
        ]);

        $data = $this->fractal
            ->createData(new Item($espacio, new EspacioTransformer()))
            ->toArray();

        return $response->withJson(['espacio' => $data]);
    }

    /**
     * Eliminar un espacio
     */
    public function destroy(Request $request, Response $response, array $args)
    {
        $jwt = $request->getAttribute('jwt');
        if (!$jwt || !isset($jwt['sub'])) {
            return $response->withJson(['error' => 'Token inválido o ausente.'], 401);
        }

        $espacio = Espacio::findOrFail($args['id']);

        // Verificar que el usuario autenticado sea el creador del espacio
        if ($espacio->id_usuario != $jwt['sub']) {
            return $response->withJson(['error' => 'No tienes permiso para eliminar este espacio.'], 403);
        }

        $espacio->update([
            'status' => '1',
        ]);

        // Desactivar proyectos asociados a este espacio
        $proyectos = Proyecto::where('id_espacio', $espacio->id_espacio)->get();
        foreach ($proyectos as $proyecto) {
            $proyecto->update(['status' => '1']);

            // Desactivar columnas de cada proyecto
            $columnas = Columna::where('id_proyecto', $proyecto->id_proyecto)->get();
            foreach ($columnas as $columna) {
                $columna->update(['status' => '1']);

                // Desactivar tareas de cada columna
                Tarea::where('id_columna', $columna->id_columna)
                    ->update(['status' => '1']);
            }
        }

        return $response->withJson(['message' => 'Espacio y sus proyectos, columnas y tareas eliminados con éxito.'], 200);
    }
}
