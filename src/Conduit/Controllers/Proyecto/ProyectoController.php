<?php

namespace Conduit\Controllers\Proyecto;

use Conduit\Models\Proyecto;
use Conduit\Models\Espacio;
use Conduit\Transformers\ProyectoTransformer;
use Conduit\Models\Columna;
use Conduit\Models\Tarea;
use Conduit\Models\User;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use Slim\Http\Request;
use Slim\Http\Response;
use Respect\Validation\Validator as v;
use Conduit\Models\UsuarioRol;
use Conduit\Transformers\UsuarioRolTransformer;
use Conduit\Models\CfdSnapshot;
use Conduit\Models\HistorialMovimiento;
use Carbon\Carbon; 
class ProyectoController
{
    protected $validator;
    protected $db;
    protected $fractal;

    public function __construct(\Slim\Container $container)
    {
        $this->fractal   = $container->get('fractal');
        $this->validator = $container->get('validator');
        $this->db        = $container->get('db');
    }

    /**
     * Listar proyectos de un espacio, filtrados por usuario si se proporciona
     * GET /api/espacios/{id}/proyectos
     * GET /api/espacios/{id}/proyectos?id_usuario={id}
     */
    public function index(Request $request, Response $response, array $args)
    {
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt['sub'] ?? null;
        $id_espacio = (int) $args['id'];
        $id_usuario = (int) $request->getQueryParam('id_usuario', 0);

        // Verificar si el espacio existe
        $espacio = Espacio::find($id_espacio);
        if (!$espacio) {
            return $response->withJson(['error' => 'Espacio no encontrado.'], 404);
        }

        // Si NO se proporciona id_usuario, devolver TODOS los proyectos del usuario autenticado
        if ($id_usuario === 0) {
            // Obtener todos los proyectos activos del espacio
            $proyectos = Proyecto::where('id_espacio', $id_espacio)->where('status', '0')->get();
            
            // Filtrar solo los proyectos donde el usuario es miembro o creador
            $proyectosPermitidos = $proyectos->filter(function($proyecto) use ($userId) {
                $esMiembro = UsuarioRol::where('id_usuario', $userId)
                    ->where('id_proyecto', $proyecto->id_proyecto)
                    ->where('status', '0')
                    ->exists();
                $esCreador = $proyecto->id_usuario_creador == $userId;
                return $esMiembro || $esCreador;
            });

            if ($proyectosPermitidos->isEmpty()) {
                return $response->withJson([
                    'Proyectos' => [],
                    'ProyectosCount' => 0,
                ]);
            }

            $data = $this->fractal
                ->createData(new Collection($proyectosPermitidos, new ProyectoTransformer()))
                ->toArray();

            return $response->withJson([
                'proyecto' => $data['data'],
                'proyectoCount' => count($proyectosPermitidos)
            ]);
        }

        // Si SÍ se proporciona id_usuario, FILTRAR proyectos según acceso
        
        // 1. Proyectos donde el usuario es CREADOR
        $proyectosCreador = Proyecto::where('id_espacio', $id_espacio)
            ->where('id_usuario_creador', $id_usuario)
            ->where('status', '0')
            ->get();

        // 2. Proyectos donde el usuario es MIEMBRO (en usuarios_roles)
        $proyectoIdsMiembro = UsuarioRol::where('id_usuario', $id_usuario)
            ->where('id_espacio', $id_espacio)
            ->where('status', '0')
            ->whereNotNull('id_proyecto')
            ->pluck('id_proyecto')
            ->toArray();

        $proyectosMiembro = [];
        if (!empty($proyectoIdsMiembro)) {
            $proyectosMiembro = Proyecto::whereIn('id_proyecto', $proyectoIdsMiembro)
                ->where('status', '0')
                ->get();
        }

        // Combinar proyectos eliminando duplicados
        $proyectosMap = [];
        
        foreach ($proyectosCreador as $proyecto) {
            $proyectosMap[$proyecto->id_proyecto] = $proyecto;
        }
        
        foreach ($proyectosMiembro as $proyecto) {
            if (!isset($proyectosMap[$proyecto->id_proyecto])) {
                $proyectosMap[$proyecto->id_proyecto] = $proyecto;
            }
        }

        $proyectos = array_values($proyectosMap);
        $count = count($proyectos);

        // Si no hay proyectos accesibles
        if ($count == 0) {
            return $response->withJson([
                'Proyectos' => [],
                'ProyectosCount' => 0,
            ]);
        }

        // Transformar la colección
        $data = $this->fractal
            ->createData(new Collection($proyectos, new ProyectoTransformer()))
            ->toArray();

        return $response->withJson([
            'proyecto' => $data['data'],
            'proyectoCount' => $count
        ]);
    }

    /**
     * Mostrar un proyecto por ID
     */
    public function show(Request $request, Response $response, array $args)
    {
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt['sub'] ?? null;
        $proyecto = Proyecto::find($args['id']);

        if (!$proyecto) {
            return $response->withJson(['error' => 'Proyecto no encontrado.'], 404);
        }

        $esMiembro = UsuarioRol::where('id_usuario', $userId)
            ->where('id_proyecto', $proyecto->id_proyecto)
            ->where('status', '0')
            ->exists();
        //$esCreador = $proyecto->id_usuario_creador == $userId;

        if (!$esMiembro) {
            return $response->withJson(['error' => 'No tienes permiso para ver este proyecto.'], 403);
        }

        // Verificar si el proyecto está inactivo (status = 1)
        if ($proyecto->status == '1') {
            return $response->withJson(['message' => 'Este proyecto ha sido eliminado.'], 410); 
        }

        // Si todo está bien, transformar y devolver
        $data = $this->fractal
            ->createData(new Item($proyecto, new ProyectoTransformer()))
            ->toArray();

        return $response->withJson(['proyecto' => $data]);
    }

    /**
 * Crear un nuevo proyecto
 */
public function store(Request $request, Response $response)
{
    $this->validator->validateArray(
        $data = $request->getParam('proyecto'),
        [
            'nombre'            => v::notEmpty(),
            'id_usuario_creador'=> v::notEmpty()->intVal(),
            'id_espacio'        => v::notEmpty()->intVal(),
        ]
    );

    if ($this->validator->failed()) {
        return $response->withJson(['errors' => $this->validator->getErrors()], 422);
    }

    // Verificar si ya existe un proyecto con el mismo nombre en el mismo espacio
    $existingProyecto = Proyecto::where('id_espacio', $data['id_espacio'])
        ->where('nombre', $data['nombre'])
        ->where('status', '0')
        ->first();

    if ($existingProyecto) {
        return $response->withJson(
            ['error' => 'Ya existe un proyecto con este nombre en este espacio.'],
            400
        );
    }

    // Verificar el número total de proyectos activos en el espacio
    $proyectosCount = Proyecto::where('id_espacio', $data['id_espacio'])
        ->where('status', '0')
        ->count();

    // Limitar a 10 proyectos por espacio
    if ($proyectosCount >= 10) {
        return $response->withJson(['error' => 'El límite de 10 proyectos ha sido alcanzado.'], 400);
    }

    // Verificar que el usuario que crea el proyecto sea el mismo que el del token
    $jwt = $request->getAttribute('jwt');
    $userId = $jwt['sub'] ?? null;
    $idUsuarioCreador = isset($data['id_usuario_creador']) ? (int)$data['id_usuario_creador'] : null;
    $userIdInt = is_null($userId) ? null : (int)$userId;
    
    if (is_null($idUsuarioCreador) || $idUsuarioCreador !== $userIdInt) {
        return $response->withJson([
            'error' => 'No tienes permiso para crear el proyecto con este usuario.'
        ], 403);
    }

    $proyecto = Proyecto::create($data);

    //  CORREGIDO: Asignar rol de líder al creador
    UsuarioRol::create([
        'id_usuario'  => $proyecto->id_usuario_creador,
        'id_rol'      => 1, // líder
        'id_proyecto' => $proyecto->id_proyecto,
        'id_espacio'  => $proyecto->id_espacio,
        'status'      => '0'
    ]);

    // Crear las columnas por defecto
    $nombres = ['Backlog', 'Por Hacer', 'En Progreso', 'Hecho'];
    foreach ($nombres as $index => $nombre) {
        Columna::create([
            'id_proyecto' => $proyecto->id_proyecto,
            'nombre'      => $nombre,
            'posicion'    => $index + 1,
        ]);
    }

    $data = $this->fractal
        ->createData(new Item($proyecto, new ProyectoTransformer()))
        ->toArray();

    return $response->withJson(['proyecto' => $data], 201);
}

    /**
     * Actualizar un proyecto
     */
    public function update(Request $request, Response $response, array $args)
    {
        $proyecto = Proyecto::findOrFail($args['id']);
        $params   = $request->getParam('proyecto', []);

        // Verificar si el proyecto tiene status = 1 (inactivo)
        if ($proyecto->status == '1') {
            return $response->withJson(['error' => 'No se puede actualizar un proyecto eliminado.'], 400);
        }

        // Verificar si el nombre ya está tomado por otro proyecto en el mismo espacio
        $existingProyecto = Proyecto::where('id_espacio', $params['id_espacio'] ?? $proyecto->id_espacio)
            ->where('nombre', $params['nombre'] ?? $proyecto->nombre)
            ->where('status', '0')
            ->where('id_proyecto', '!=', $proyecto->id_proyecto)
            ->first();

        if ($existingProyecto) {
            return $response->withJson(['error' => 'Ya existe un proyecto con este nombre en este espacio.'], 400);
        }

        // Verificar que el usuario sea líder en el proyecto
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt['sub'] ?? null;
        $usuarioRol = UsuarioRol::where('id_usuario', $userId)
            ->where('id_proyecto', $proyecto->id_proyecto)
            ->where('id_rol', 1)
            ->where('status', '0')
            ->first();

        if (!$usuarioRol) {
            return $response->withJson([
                'error' => 'No tienes permiso para editar este proyecto. Solo el líder puede editar.'
            ], 403);
        }

        $proyecto->update([
            'nombre'            => $params['nombre']            ?? $proyecto->nombre,
            'descripcion'       => $params['descripcion']       ?? $proyecto->descripcion,
            'id_usuario_creador'=> $params['id_usuario_creador'] ?? $proyecto->id_usuario_creador,
            'id_espacio'        => $params['id_espacio']        ?? $proyecto->id_espacio,
        ]);

        $data = $this->fractal
            ->createData(new Item($proyecto, new ProyectoTransformer()))
            ->toArray();

        return $response->withJson(['proyecto' => $data]);
    }

    /**
     * Eliminar un proyecto (soft delete)
     */
    public function destroy(Request $request, Response $response, array $args)
    {
        $proyecto = Proyecto::findOrFail($args['id']);
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt['sub'] ?? null;

        if ($proyecto->id_usuario_creador != $userId) {
            return $response->withJson([
                'error' => 'No tienes permiso para eliminar este proyecto. Solo el creador puede eliminar.'
            ], 403);
        }

        $proyecto->update(['status' => '1']);

        // Actualizar el estado de las columnas del proyecto
        $columnas = Columna::where('id_proyecto', $proyecto->id_proyecto)->get();
        foreach ($columnas as $columna) {
            $columna->update(['status' => '1']);

            // Actualizar el estado de tareas de cada columna
            Tarea::where('id_columna', $columna->id_columna)
                ->update(['status' => '1']);
        }

        return $response->withJson(['message' => 'Proyecto eliminado con éxito.'], 200);
    }

    /**
     * Agregar un miembro a un proyecto
     */
    public function agregarMiembro(Request $request, Response $response, $args)
    {
        $id_proyecto = $args['id_proyecto'];
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt['sub'] ?? null;

        // Verificar si el usuario autenticado es líder
        $usuarioRol = UsuarioRol::where('id_usuario', $userId)
            ->where('id_proyecto', $id_proyecto)
            ->where('id_rol', 1)
            ->where('status', '0')
            ->first();

        if (!$usuarioRol) {
            return $response->withJson([
                'error' => 'No tienes permiso para agregar miembros. Solo el líder puede hacerlo.'
            ], 403);
        }

        $data = $request->getParsedBody();
        $id_usuario = $data['id_usuario'] ?? null;
        $id_rol = $data['id_rol'] ?? null;

        // Validaciones
        if (!$id_usuario || !is_numeric($id_usuario)) {
            return $response->withStatus(400)->withJson(['error' => 'El id_usuario es requerido y debe ser un número.']);
        }
        if (!$id_rol || !is_numeric($id_rol)) {
            return $response->withStatus(400)->withJson(['error' => 'El id_rol es requerido y debe ser un número.']);
        }

        // Verificar si el proyecto existe
        $proyecto = Proyecto::find($id_proyecto);
        if (!$proyecto) {
            return $response->withStatus(404)->withJson(['error' => 'Proyecto no encontrado.']);
        }

        $id_espacio = $proyecto->id_espacio;

        // Verificar si el usuario a agregar existe
        if (!User::find($id_usuario)) {
            return $response->withStatus(404)->withJson(['error' => 'El usuario que intentas agregar no fue encontrado.']);
        }

        // Verificar si el usuario estuvo en el proyecto y fue eliminado
        $user = User::where('id_usuario', $id_usuario)
            ->where('status', '0') // 0 = activo, 1 = eliminado
            ->first();

        if (!$user) {
            return $response->withJson([
                'error' => 'El usuario no existe o está eliminado. No puede ser agregado.'
            ], 404);
        }


        // Verificar límite de miembros
        $miembrosCount = UsuarioRol::where('id_proyecto', $id_proyecto)
            ->where('status', '0')
            ->count();

        if ($miembrosCount >= 30) {
            return $response->withStatus(400)->withJson(['error' => 'El límite de 30 miembros ha sido alcanzado.']);
        }

        // Evitar duplicados
        $yaEsMiembro = UsuarioRol::where('id_usuario', $id_usuario)
            ->where('id_proyecto', $id_proyecto)
            ->where('status', '0')
            ->exists();

        if ($yaEsMiembro) {
            return $response->withStatus(409)->withJson(['error' => 'Este usuario ya es miembro del proyecto.']);
        }

        //  Validar máximo 2 líderes
        if ((int)$id_rol === 1) {
            $lideresCount = UsuarioRol::where('id_proyecto', $id_proyecto)
                ->where('id_rol', 1)
                ->where('status', '0')
                ->count();

            if ($lideresCount >= 2) {
                return $response->withJson([
                    'error' => 'El proyecto ya tiene el máximo de 2 líderes.'
                ], 400);
            }
        }


        // Crear la asignación
        $asignacion = UsuarioRol::create([
            'id_usuario'  => $id_usuario,
            'id_rol'      => $id_rol,
            'id_proyecto' => $id_proyecto,
            'id_espacio'  => $id_espacio,
            'status'      => '0'
        ]);

        return $response->withStatus(201)->withJson([
            'message' => 'Usuario agregado al proyecto exitosamente.',
            'asignacion' => UsuarioRolTransformer::transform($asignacion)
        ]);
    }

    /**
     * Obtener miembros de un proyecto
     */
    public function obtenerMiembros(Request $request, Response $response, $args)
    {
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt['sub'] ?? null;
        $id_proyecto = (int) $args['id_proyecto'];

        $proyecto = Proyecto::find($id_proyecto);
        if (!$proyecto) {
            return $response->withStatus(404)->withJson(['error' => 'Proyecto no encontrado.']);
        }

        $esMiembro = UsuarioRol::where('id_usuario', $userId)
            ->where('id_proyecto', $id_proyecto)
            ->where('status', '0')
            ->exists();
        $esCreador = $proyecto->id_usuario_creador == $userId;

        if (!$esMiembro && !$esCreador) {
            return $response->withJson(['error' => 'No tienes permiso para ver los miembros de este proyecto.'], 403);
        }

        try {
            $miembrosFormateados = [];
            
            // Obtener todos los miembros desde usuarios_roles
            $miembrosRoles = UsuarioRol::where('id_proyecto', $id_proyecto)
                ->where('status', '0')
                ->with(['usuario', 'rol'])
                ->get();
            
            $usuariosProcesados = [];
            
            foreach ($miembrosRoles as $miembroRol) {
                $usuario = $miembroRol->usuario;
                $rol = $miembroRol->rol;
                
                if ($usuario) {
                    $esCreador = ($miembroRol->id_usuario == $proyecto->id_usuario_creador);
                    
                    $miembrosFormateados[] = [
                        'id_usuario' => $usuario->id_usuario,
                        'nombre' => $usuario->nombre ?? 'Usuario',
                        'email' => $usuario->correo ?? 'sin-email@example.com',
                        'rol' => $rol ? $rol->nombre : 'Miembro',
                        'id_rol' => $miembroRol->id_rol,
                        'es_creador' => $esCreador
                    ];
                    
                    $usuariosProcesados[] = $usuario->id_usuario;
                }
            }
            
            // Si el creador no está en usuarios_roles, agregarlo manualmente
            if ($proyecto->id_usuario_creador && !in_array($proyecto->id_usuario_creador, $usuariosProcesados)) {
                $creador = User::find($proyecto->id_usuario_creador);
                if ($creador) {
                    $miembrosFormateados[] = [
                        'id_usuario' => $creador->id_usuario,
                        'nombre' => $creador->nombre ?? 'Usuario Creador',
                        'email' => $creador->correo ?? 'sin-email@example.com',
                        'rol' => 'Creador',
                        'id_rol' => null,
                        'es_creador' => true
                    ];
                }
            }
            
            return $response->withJson([
                'miembros' => $miembrosFormateados,
                'total' => count($miembrosFormateados)
            ]);

        } catch (\Exception $e) {
            return $response->withStatus(500)->withJson([
                'error' => 'Error interno: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Quitar un miembro de un proyecto
     */
    public function quitarMiembro(Request $request, Response $response, $args)
    {
        $id_proyecto = $args['id_proyecto'];
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt['sub'] ?? null;

        $id_usuario = $args['id_usuario'];

        // Validar proyecto
        $proyecto = Proyecto::find($id_proyecto);
        if (!$proyecto) {
            return $response->withJson(['error' => 'Proyecto no encontrado.'], 404);
        }

        // Validar que quien ejecuta es líder
        $usuarioLider = UsuarioRol::where('id_usuario', $userId)
            ->where('id_proyecto', $id_proyecto)
            ->where('id_rol', 1)
            ->where('status', '0')
            ->first();

        if (!$usuarioLider) {
            return $response->withJson([
                'error' => 'No tienes permiso para quitar miembros. Solo un líder puede hacerlo.'
            ], 403);
        }

        // Buscar asignación del usuario a eliminar
        $asignacion = UsuarioRol::where('id_proyecto', $id_proyecto)
            ->where('id_usuario', $id_usuario)
            ->where('status', '0')
            ->first();

        if (!$asignacion) {
            return $response->withJson(['error' => 'El usuario no pertenece al proyecto.'], 404);
        }

        //  1. NADIE puede eliminar al líder creador
        if ($id_usuario == $proyecto->id_usuario_creador) {
            return $response->withJson([
                'error' => 'El líder creador no puede ser eliminado del proyecto.'
            ], 403);
        }

        //  2. Un líder NO puede eliminarse a sí mismo
        if ($userId == $id_usuario && (int)$asignacion->id_rol === 1) {
            return $response->withJson([
                'error' => 'No puedes eliminarte del proyecto si eres líder.'
            ], 403);
        }

        //  3. Si el usuario a eliminar es líder, solo el creador puede hacerlo
        if ((int)$asignacion->id_rol === 1 && $userId != $proyecto->id_usuario_creador) {
            return $response->withJson([
                'error' => 'Solo el líder creador puede eliminar a otro líder.'
            ], 403);
        }

        // 4. Si el líder a eliminar es el segundo líder, validar que quede 1 líder (el creador)
        if ((int)$asignacion->id_rol === 1) {
            $lideresActivos = UsuarioRol::where('id_proyecto', $id_proyecto)
                ->where('id_rol', 1)
                ->where('status', '0')
                ->count();

            if ($lideresActivos <= 1) {
                return $response->withJson([
                    'error' => 'Debe quedar al menos un líder activo en el proyecto.'
                ], 400);
            }
        }

        // Ejecutar eliminación
        $asignacion->update(['status' => '1']);

        return $response->withJson([
            'message' => 'Miembro removido exitosamente del proyecto.',
            'asignacion' => UsuarioRolTransformer::transform($asignacion)
        ]);
    }


    /**
     * Permite que un usuario abandone un proyecto
     * DELETE /proyectos/{id_proyecto}/abandonar
     */
    public function abandonarProyecto(Request $request, Response $response, $args)
    {
        $id_proyecto = $args['id_proyecto'];
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt['sub'] ?? null;

        // Validar proyecto
        $proyecto = Proyecto::find($id_proyecto);
        if (!$proyecto) {
            return $response->withJson(['error' => 'Proyecto no encontrado.'], 404);
        }

        // Verificar si el usuario es el creador
        if ($proyecto->id_usuario_creador == $userId) {
            return $response->withJson([
                    'error' => 'No puedes abandonar el proyecto porque eres el líder creador.'
                ], 403);
        }

        // Buscar la asignación del usuario
        $asignacion = UsuarioRol::where('id_proyecto', $id_proyecto)
            ->where('id_usuario', $userId)
            ->where('status', '0')
            ->first();

        if (!$asignacion) {
            return $response->withJson([
                'error' => 'No perteneces a este proyecto o ya lo abandonaste.'
            ], 404);
        }

        // Si es líder, verificar que NO sea el único
        if ($asignacion->id_rol == 1) {
            $cantidadLideres = UsuarioRol::where('id_proyecto', $id_proyecto)
                ->where('id_rol', 1)
                ->where('status', '0')
                ->count();

            if ($cantidadLideres <= 1) {
                return $response->withJson([
                    'error' => 'No puedes abandonar el proyecto porque eres el único líder. Debe haber al menos otro líder.'
                ], 400);
            }
        }

        // Soft delete → abandonar proyecto
        $asignacion->update(['status' => '1']);

        return $response->withJson([
            'message' => 'Has abandonado el proyecto exitosamente.',
            'asignacion' => UsuarioRolTransformer::transform($asignacion)
        ], 200);
    }


    /**
     * Cambiar el rol de un miembro
     */
    public function cambiarRol(Request $request, Response $response, $args)
    {
        $id_proyecto = $args['id_proyecto'];
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt['sub'] ?? null;

        $usuarioRol = UsuarioRol::where('id_usuario', $userId)
            ->where('id_proyecto', $id_proyecto)
            ->where('id_rol', 1)
            ->where('status', '0')
            ->first();

        if (!$usuarioRol) {
            return $response->withJson([
                'error' => 'No tienes permiso para cambiar roles. Solo el líder puede hacerlo.'
            ], 403);
        }

        $id_usuario = $args['id_usuario'];
        $data = $request->getParsedBody();
        $nuevo_rol = $data['id_rol'] ?? null;

        if (!$nuevo_rol || !is_numeric($nuevo_rol)) {
            return $response->withStatus(400)->withJson(['error' => 'El id_rol es requerido y debe ser un número.']);
        }

        $proyecto = Proyecto::find($id_proyecto);
        if (!$proyecto) {
            return $response->withStatus(404)->withJson(['error' => 'Proyecto no encontrado.']);
        }

        // Verificar si se intenta cambiar el rol del creador
        if ($proyecto->id_usuario_creador == $id_usuario) {
            return $response->withJson([
                'error' => 'No puedes cambiar el rol del líder creador del proyecto.'
            ], 403);
        }

        $asignacion = UsuarioRol::where('id_proyecto', $id_proyecto)
            ->where('id_usuario', $id_usuario)
            ->where('status', '0')
            ->first();

        if (!$asignacion) {
            return $response->withStatus(404)->withJson(['error' => 'El usuario no pertenece a este proyecto o fue eliminado.']);
        }


        // El segundo líder NO puede cambiar su propio rol
        if ($userId == $id_usuario && $asignacion->id_rol == 1) {
            return $response->withJson([
                'error' => 'No puedes cambiar tu propio rol si eres líder.'
            ], 403);
        }

        // Solo el líder creador puede cambiar el rol de OTRO líder
        if ($asignacion->id_rol == 1 && $userId != $proyecto->id_usuario_creador) {
            return $response->withJson([
                'error' => 'Solo el líder creador puede cambiar el rol de otro líder.'
            ], 403);
        }

        //  Validar máximo 2 líderes al cambiar rol
        if ((int)$nuevo_rol === 1) {

            // Contar líderes actuales con status activo
            $lideresCount = UsuarioRol::where('id_proyecto', $id_proyecto)
                ->where('id_rol', 1)
                ->where('status', '0')
                ->count();

            // Si ya hay 2 y el usuario NO es uno de los líderes actuales
            if ($lideresCount >= 2 && $asignacion->id_rol != 1) {
                return $response->withJson([
                    'error' => 'El proyecto ya tiene 2 líderes. No puedes asignar más.'
                ], 400);
            }
        }

        // Asegurar al menos 1 líder al cambiar rol
        if ($asignacion->id_rol == 1 && (int)$nuevo_rol !== 1) {

            // Contar cuántos líderes hay actualmente (activos)
            $lideresCount = UsuarioRol::where('id_proyecto', $id_proyecto)
                ->where('id_rol', 1)
                ->where('status', '0')
                ->count();

            // Si solo hay 1 líder → NO permitir que este deje de serlo
            if ($lideresCount <= 1) {
                return $response->withJson([
                    'error' => 'El proyecto debe tener al menos un líder.'
                ], 400);
            }
        }

        $asignacion->update(['id_rol' => $nuevo_rol]);

        return $response->withStatus(200)->withJson([
            'message' => 'Rol del usuario actualizado correctamente.',
            'asignacion' => UsuarioRolTransformer::transform($asignacion)
        ]);
    }

    /**
     * ⭐ CFD - Generar y retornar el Cumulative Flow Diagram
     * GET /api/proyectos/{id}/cfd?dias=30
     */
    public function cfd($request, $response, $args)
    {
        $idProyecto = (int)$args['id'];
        
        // Validar que el proyecto existe
        $proyecto = Proyecto::find($idProyecto);
        if (!$proyecto || $proyecto->status == '1') {
            return $response->withJson(['error' => 'Proyecto no encontrado o eliminado.'], 404);
        }

        // Validar permisos del usuario
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt['sub'] ?? null;
        
        $esMiembro = UsuarioRol::where('id_usuario', $userId)
            ->where('id_proyecto', $idProyecto)
            ->where('status', '0')
            ->exists();
        $esCreador = $proyecto->id_usuario_creador == $userId;
        
        if (!$esMiembro && !$esCreador) {
            return $response->withJson(['error' => 'No tienes permiso para ver el CFD de este proyecto.'], 403);
        }

        // Obtener días del query param (default 30)
        $dias = (int)($request->getQueryParam('dias', 30));
        $dias = max(7, min($dias, 365)); // Limitar entre 7 y 365 días
        
        $fechaFin = Carbon::now();
        $fechaInicio = Carbon::now()->subDays($dias - 1);

        // Obtener solo columnas ACTIVAS ordenadas por posición
        $columnas = Columna::where('id_proyecto', $idProyecto)
            ->where('status', '0')
            ->orderBy('posicion')
            ->pluck('nombre', 'id_columna')
            ->toArray();

        if (empty($columnas)) {
            return $response->withJson([
                'error' => 'No hay columnas activas en este proyecto.',
                'snapshots' => [],
                'columnas' => []
            ], 200);
        }

        $result = [];

        // Generar snapshots día por día
        for ($fecha = $fechaInicio->copy(); $fecha <= $fechaFin; $fecha->addDay()) {
            $conteo = array_fill_keys(array_values($columnas), 0);

            // Solo tareas ACTIVAS creadas antes o en esta fecha
            $tareas = Tarea::where('id_proyecto', $idProyecto)
                ->where('status', '0')
                ->whereDate('created_at', '<=', $fecha->toDateString())
                ->get();

            foreach ($tareas as $tarea) {
                // Buscar el último movimiento de la tarea hasta esta fecha
                $mov = HistorialMovimiento::where('id_tarea', $tarea->id_tarea)
                    ->where('timestamp', '<=', $fecha->endOfDay())
                    ->orderBy('timestamp', 'desc')
                    ->first();

                if ($mov) {
                    // Usar columna del último movimiento
                    $nombreColumna = $columnas[$mov->id_columna_nueva] ?? null;
                    if ($nombreColumna) {
                        $conteo[$nombreColumna]++;
                    }
                } else {
                    // Si no hay movimiento, usar columna actual de la tarea
                    $nombreColumna = $columnas[$tarea->id_columna] ?? null;
                    if ($nombreColumna) {
                        $conteo[$nombreColumna]++;
                    }
                }
            }

            // Almacenar el snapshot (cast automático a JSON)
            CfdSnapshot::updateOrCreate(
                ['id_proyecto' => $idProyecto, 'fecha' => $fecha->toDateString()],
                ['conteo_columnas' => $conteo]
            );

            $result[] = [
                'fecha' => $fecha->toDateString(),
                'conteo' => $conteo
            ];
        }

        // Metadata adicional
        return $response->withJson([
            'snapshots' => $result,
            'columnas' => array_values($columnas),
            'fecha_inicio' => $fechaInicio->toDateString(),
            'fecha_fin' => $fechaFin->toDateString(),
            'total_dias' => $dias,
            'total_tareas_activas' => Tarea::where('id_proyecto', $idProyecto)
                ->where('status', '0')
                ->count()
        ]);
    }

    /**
     * Generar snapshot individual para un día específico
     * Método auxiliar para regeneración
     */
    public function generarCfdSnapshot($idProyecto, $fecha)
    {
        // Obtener solo columnas ACTIVAS
        $columnas = Columna::where('id_proyecto', $idProyecto)
            ->where('status', '0')
            ->orderBy('posicion')
            ->pluck('nombre', 'id_columna')
            ->toArray();
        
        if (empty($columnas)) {
            return false;
        }

        $conteo = array_fill_keys(array_values($columnas), 0);

        // Solo tareas ACTIVAS creadas antes o en esta fecha
        $tareas = Tarea::where('id_proyecto', $idProyecto)
            ->where('status', '0')
            ->whereDate('created_at', '<=', $fecha)
            ->get();

        foreach ($tareas as $tarea) {
            $mov = HistorialMovimiento::where('id_tarea', $tarea->id_tarea)
                ->where('timestamp', '<=', $fecha . ' 23:59:59')
                ->orderBy('timestamp', 'desc')
                ->first();

            if ($mov) {
                $nombreColumna = $columnas[$mov->id_columna_nueva] ?? null;
                if ($nombreColumna) {
                    $conteo[$nombreColumna]++;
                }
            } else {
                // Si no hay movimiento, usar columna actual de la tarea
                $nombreColumna = $columnas[$tarea->id_columna] ?? null;
                if ($nombreColumna) {
                    $conteo[$nombreColumna]++;
                }
            }
        }

        CfdSnapshot::updateOrCreate(
            ['id_proyecto' => $idProyecto, 'fecha' => $fecha],
            ['conteo_columnas' => $conteo]
        );

        return true;
    }

    /**
     * ⭐ NUEVO: Regenerar snapshots históricos
     * POST /api/proyectos/{id}/cfd/regenerar?dias=90
     */
    public function regenerarCfdHistorico($request, $response, $args)
    {
        $idProyecto = (int)$args['id'];
        
        // Validar proyecto
        $proyecto = Proyecto::find($idProyecto);
        if (!$proyecto || $proyecto->status == '1') {
            return $response->withJson(['error' => 'Proyecto no encontrado.'], 404);
        }

        // Validar permisos (solo líder puede regenerar)
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt['sub'] ?? null;
        
        $esLider = UsuarioRol::where('id_usuario', $userId)
            ->where('id_proyecto', $idProyecto)
            ->where('id_rol', 1)
            ->where('status', '0')
            ->exists();
        
        if (!$esLider) {
            return $response->withJson(['error' => 'Solo el líder puede regenerar snapshots.'], 403);
        }

        // Obtener rango de fechas
        $dias = (int)($request->getQueryParam('dias', 90));
        $dias = max(7, min($dias, 365));
        
        $fechaFin = Carbon::now();
        $fechaInicio = Carbon::now()->subDays($dias - 1);

        $snapshotsGenerados = 0;
        
        for ($fecha = $fechaInicio->copy(); $fecha <= $fechaFin; $fecha->addDay()) {
            if ($this->generarCfdSnapshot($idProyecto, $fecha->toDateString())) {
                $snapshotsGenerados++;
            }
        }

        return $response->withJson([
            'message' => 'Snapshots regenerados exitosamente.',
            'snapshots_generados' => $snapshotsGenerados,
            'fecha_inicio' => $fechaInicio->toDateString(),
            'fecha_fin' => $fechaFin->toDateString()
        ]);
    }
}
