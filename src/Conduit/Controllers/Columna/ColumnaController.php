<?php

namespace Conduit\Controllers\Columna;

use Conduit\Models\Columna;
use Conduit\Models\Proyecto;
use Conduit\Transformers\ColumnaTransformer;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use Slim\Http\Request;
use Slim\Http\Response;
use Respect\Validation\Validator as v;
use Conduit\Models\UsuarioRol;

class ColumnaController
{
    protected $validator;
    protected $db;
    protected $fractal;

    // Límites
    const MAX_COLUMNAS_POR_PROYECTO = 10;
    const MAX_COLUMNAS_FIJAS = 2;
    const MAX_TAREAS_FIJA = 200;
    const MAX_TAREAS_NORMAL = 20;
    const MAX_LIMIT_PAGINATION = 100;
    const DEFAULT_LIMIT = 50;

    public function __construct(\Slim\Container $container)
    {
        $this->fractal   = $container->get('fractal');
        $this->validator = $container->get('validator');
        $this->db        = $container->get('db');
    }

    /**
     * Listar todas las columnas de un proyecto (solo las activas)
     */
    public function index(Request $request, Response $response, array $args)
    {
        $proyectoId = $args['id'];

        $proyecto = Proyecto::find($proyectoId);
        if (!$proyecto) {
            return $response->withJson(['error' => 'Proyecto no encontrado.'], 404);
        }

        // Autorización: sólo miembros del proyecto pueden listar columnas
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt['sub'] ?? null;
        if (!$userId) {
            return $response->withJson(['error' => 'Token inválido o no autenticado.'], 401);
        }

        $esMiembro = UsuarioRol::where('id_usuario', $userId)
            ->where('id_proyecto', $proyectoId)
            ->where('status', '0')
            ->exists();

        if (!$esMiembro) {
            return $response->withJson(['error' => 'No autorizado para ver las columnas de este proyecto.'], 403);
        }

        $builder = Columna::where('id_proyecto', $proyectoId)
                          ->where('status', '0')
                          ->orderBy('posicion', 'asc');

        $limit = $request->getParam('limit', self::DEFAULT_LIMIT);
        $offset = $request->getParam('offset', 0);
        
        if ($limit > self::MAX_LIMIT_PAGINATION) {
            $limit = self::MAX_LIMIT_PAGINATION;
        }
        
        $builder->limit($limit)->offset($offset);

        $count    = $builder->count();
        $columnas = $builder->get();

        if ($count == 0) {
            return $response->withJson([
                'columnas' => [],
                'columnasCount' => 0,
            ]);
        }

        $data = $this->fractal
            ->createData(new Collection($columnas, new ColumnaTransformer()))
            ->toArray();

        return $response->withJson([
            'columnas' => $data['data'],
            'columnasCount' => $count,
            'pagination' => [
                'limit' => (int)$limit,
                'offset' => (int)$offset,
                'total' => $count
            ]
        ]);
    }

    /**
     * Mostrar una columna específica por ID
     */
    public function show(Request $request, Response $response, array $args)
    {
        $columna = Columna::where('id_columna', $args['id'])->first();

        if (!$columna) {
            return $response->withJson(['error' => 'Columna no encontrada.'], 404);
        }

        if ($columna->status == '1') {
            return $response->withJson(['message' => 'Esta columna ha sido eliminada.'], 410);
        }

        $data = $this->fractal
            ->createData(new Item($columna, new ColumnaTransformer()))
            ->toArray();

        return $response->withJson(['columna' => $data]);
    }


        /**
     * Crear una nueva columna
     */
    public function store(Request $request, Response $response)
    {
        $this->validator->validateArray(
            $data = $request->getParam('columna'),
            [
                'id_proyecto' => v::notEmpty()->intVal(),
                'nombre'      => v::notEmpty()->stringType()->length(1, 25),
                'posicion'    => v::notEmpty()->intVal(),
                'color'       => v::optional(v::stringType()->length(1, 7)),
                'tipo_columna' => v::optional(v::in([Columna::TIPO_FIJA, Columna::TIPO_NORMAL])),
            ]
        );

        if (isset($data['nombre']) && mb_strlen($data['nombre']) > 25) {
            return $response->withJson([
                'error' => 'El nombre de la columna no puede tener más de 25 caracteres.'
            ], 422);
        }

        if ($this->validator->failed()) {
            return $response->withJson(['errors' => $this->validator->getErrors()], 422);
        }

        $proyecto = Proyecto::find($data['id_proyecto']);
        if (!$proyecto) {
            return $response->withJson(['error' => 'El proyecto no existe.'], 404);
        }

        // Autorización: sólo el líder del proyecto puede crear columnas
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt['sub'] ?? null;
        if (!$userId) {
            return $response->withJson(['error' => 'Token inválido o no autenticado.'], 401);
        }

        $esLider = UsuarioRol::where('id_usuario', $userId)
            ->where('id_proyecto', $data['id_proyecto'])
            ->where('id_rol', 1)
            ->where('status', '0')
            ->exists();

        if (!$esLider) {
            return $response->withJson(['error' => 'No autorizado. Sólo el líder del proyecto puede crear columnas.'], 403);
        }

        
        // Validar que la posición esté entre 1 y el máximo permitido (10)
        if ($data['posicion'] < 1 || $data['posicion'] > self::MAX_COLUMNAS_POR_PROYECTO) {
            return $response->withJson([
                'error' => "La posición debe estar entre 1 y " . (self::MAX_COLUMNAS_POR_PROYECTO)
            ], 422);
        }

        $columnasCount = Columna::where('id_proyecto', $data['id_proyecto'])
                                ->where('status', '0')
                                ->count();

        // Validar posición real disponible (considerando columnas existentes + nueva)
        $maxPosicionPermitida = $columnasCount + 1;
        if ($data['posicion'] > $maxPosicionPermitida) {
            return $response->withJson([
                'error' => "La posición {$data['posicion']} no está disponible. La máxima posición permitida es {$maxPosicionPermitida}"
            ], 422);
        }
        // === FIN NUEVA VALIDACIÓN ===

        $existingColumna = Columna::where('id_proyecto', $data['id_proyecto'])
            ->where('nombre', $data['nombre'])
            ->where('status', '0')
            ->first();

        if ($existingColumna) {
            return $response->withJson(['error' => 'Ya existe una columna con este nombre en este proyecto.'], 400);
        }

        $existingPosition = Columna::where('id_proyecto', $data['id_proyecto'])
            ->where('posicion', $data['posicion'])
            ->where('status', '0')
            ->first();

        if ($existingPosition) {
            return $response->withJson(['error' => 'Ya existe una columna con esta posición en este proyecto.'], 400);
        }

        if ($columnasCount >= self::MAX_COLUMNAS_POR_PROYECTO) {
            return $response->withJson(['error' => 'El límite de 10 columnas por proyecto ha sido alcanzado.'], 400);
        }

        // Determinar tipo de columna (por defecto 'normal')
        $tipoColumna = $data['tipo_columna'] ?? Columna::TIPO_NORMAL;
        
        // Si se intenta crear como "fija", validar límite
        if ($tipoColumna === Columna::TIPO_FIJA) {
            $columnasFijasCount = Columna::where('id_proyecto', $data['id_proyecto'])
                                        ->where('status', '0')
                                        ->where('tipo_columna', Columna::TIPO_FIJA)
                                        ->count();

            if ($columnasFijasCount >= self::MAX_COLUMNAS_FIJAS) {
                return $response->withJson([
                    'error' => 'No se pueden tener más de 2 columnas fijas por proyecto.'
                ], 400);
            }
        }

        $columna = Columna::create([
            'id_proyecto' => $data['id_proyecto'],
            'nombre'      => $data['nombre'],
            'color'       => $data['color'] ?? null,
            'posicion'    => $data['posicion'],
            'tipo_columna' => $tipoColumna,
            'status'      => $data['status'] ?? '0',
        ]);

        $data = $this->fractal
            ->createData(new Item($columna, new ColumnaTransformer()))
            ->toArray();

        return $response->withJson([
            'message' => 'Columna creada exitosamente.',
            'columna' => $data
        ], 201);
    }

        /**
     * Actualizar una columna
     */
    public function update(Request $request, Response $response, array $args)
    {
        $columna = Columna::find($args['id']);
        
        if (!$columna) {
            return $response->withJson(['error' => 'Columna no encontrada.'], 404);
        }

        $params = $request->getParam('columna', []);

        if ($columna->status == '1') {
            return $response->withJson(['error' => 'No se puede actualizar una columna eliminada.'], 400);
        }

        // Autorización: sólo líder del proyecto puede actualizar columnas
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt['sub'] ?? null;
        if (!$userId) {
            return $response->withJson(['error' => 'Token inválido o no autenticado.'], 401);
        }

        $esLider = UsuarioRol::where('id_usuario', $userId)
            ->where('id_proyecto', $columna->id_proyecto)
            ->where('id_rol', 1)
            ->where('status', '0')
            ->exists();

        if (!$esLider) {
            return $response->withJson(['error' => 'No autorizado. Sólo el líder del proyecto puede modificar columnas.'], 403);
        }

        $this->validator->validateArray($params, [
            'nombre'   => v::optional(v::notEmpty()->stringType()->length(1, 25)),
            'posicion' => v::optional(v::intVal()),
            'color'    => v::optional(v::stringType()->length(1, 7)),
            'status'   => v::optional(v::in(['0', '1'])),
        ]);

        if (isset($params['nombre']) && mb_strlen($params['nombre']) > 25) {
            return $response->withJson([
                'error' => 'El nombre de la columna no puede tener más de 25 caracteres.'
            ], 422);
        }

        if ($this->validator->failed()) {
            return $response->withJson(['errors' => $this->validator->getErrors()], 422);
        }

        // NO permitir cambiar tipo_columna mediante update normal
        if (isset($params['tipo_columna'])) {
            return $response->withJson([
                'error' => 'Use el endpoint /proyectos/{id}/columnas/gestionar-tipos para cambiar tipos de columna.'
            ], 400);
        }

        if (isset($params['nombre'])) {
            $existingColumna = Columna::where('id_proyecto', $columna->id_proyecto)
                ->where('nombre', $params['nombre'])
                ->where('status', '0')
                ->where('id_columna', '!=', $args['id'])
                ->first();

            if ($existingColumna) {
                return $response->withJson(['error' => 'Ya existe una columna activa con este nombre en este proyecto.'], 400);
            }
        }

        if (isset($params['posicion']) && $params['posicion'] != $columna->posicion) {
            $nuevoOrden = (int)$params['posicion'];
            $posicionActual = (int)$columna->posicion;
            $idProyecto = $columna->id_proyecto;

            // === VALIDACIÓN: Rango de posición (1-10) ===
            // Validar que la posición esté entre 1 y el máximo permitido (10)
            if ($nuevoOrden < 1 || $nuevoOrden > self::MAX_COLUMNAS_POR_PROYECTO) {
                return $response->withJson([
                    'error' => "La posición debe estar entre 1 y " . self::MAX_COLUMNAS_POR_PROYECTO
                ], 422);
            }

            // Validar posición real disponible
            $totalColumnas = Columna::where('id_proyecto', $idProyecto)
                ->where('status', '0')
                ->count();
                
            if ($nuevoOrden > $totalColumnas) {
                return $response->withJson([
                    'error' => "La posición {$nuevoOrden} no está disponible. El proyecto tiene {$totalColumnas} columnas activas."
                ], 422);
            }
            // === FIN VALIDACIÓN ===

            $columnas = Columna::where('id_proyecto', $idProyecto)
                ->where('status', '0')
                ->orderBy('posicion', 'asc')
                ->get();

            if ($nuevoOrden < $posicionActual) {
                foreach ($columnas as $c) {
                    if ($c->id_columna == $columna->id_columna) continue;
                    if ($c->posicion >= $nuevoOrden && $c->posicion < $posicionActual) {
                        $c->update(['posicion' => $c->posicion + 1]);
                    }
                }
            } elseif ($nuevoOrden > $posicionActual) {
                foreach ($columnas as $c) {
                    if ($c->id_columna == $columna->id_columna) continue;
                    if ($c->posicion <= $nuevoOrden && $c->posicion > $posicionActual) {
                        $c->update(['posicion' => $c->posicion - 1]);
                    }
                }
            }
            $columna->update([
                'nombre'   => $params['nombre'] ?? $columna->nombre,
                'color'    => $params['color'] ?? $columna->color,
                'posicion' => $nuevoOrden,
                'status'   => $params['status'] ?? $columna->status,
            ]);
        } else {
            $columna->update([
                'nombre'   => $params['nombre'] ?? $columna->nombre,
                'color'    => $params['color'] ?? $columna->color,
                'posicion' => $params['posicion'] ?? $columna->posicion,
                'status'   => $params['status'] ?? $columna->status,
            ]);
        }

        $data = $this->fractal
            ->createData(new Item($columna, new ColumnaTransformer()))
            ->toArray();

        return $response->withJson([
            'message' => 'Columna actualizada exitosamente.',
            'columna' => $data
        ]);
    }

    /**
     * Eliminar una columna (marcarla como inactiva)
     */
    public function destroy(Request $request, Response $response, array $args)
    {
        $columna = Columna::find($args['id']);
        
        if (!$columna) {
            return $response->withJson(['error' => 'Columna no encontrada.'], 404);
        }

        if ($columna->status == '1') {
            return $response->withJson(['error' => 'La columna ya ha sido eliminada.'], 400);
        }

        // Verificar si la columna es fija (no se puede eliminar)
        if ($columna->tipo_columna === Columna::TIPO_FIJA) {
            return $response->withJson([
                'error' => 'No se puede eliminar una columna fija.'
            ], 400);
        }

        // Autorización: sólo líder del proyecto puede eliminar columnas
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt['sub'] ?? null;
        if (!$userId) {
            return $response->withJson(['error' => 'Token inválido o no autenticado.'], 401);
        }

        $esLider = UsuarioRol::where('id_usuario', $userId)
            ->where('id_proyecto', $columna->id_proyecto)
            ->where('id_rol', 1)
            ->where('status', '0')
            ->exists();

        if (!$esLider) {
            return $response->withJson(['error' => 'No autorizado. Sólo el líder del proyecto puede eliminar columnas.'], 403);
        }

        $tareasAsociadas = $columna->tareas()->where('status', '0')->count();

        if ($tareasAsociadas > 0) {
            return $response->withJson([
                'error' => 'No se puede eliminar esta columna porque tiene ' . $tareasAsociadas . ' tareas asociadas.'
            ], 400);
        }

        $columna->update(['status' => '1']);

        return $response->withJson([
            'message' => 'Columna eliminada con éxito.'
        ], 200);
    }
    /**
     * Gestión de tipos de columnas por el líder
     */
    public function gestionarTipos(Request $request, Response $response, array $args)
    {
        $proyectoId = $args['id'];
        $data = $request->getParsedBody();

        // Validar que venga el array de columnas
        if (!isset($data['columnas']) || !is_array($data['columnas'])) {
            return $response->withJson([
                'error' => 'Se requiere un array de columnas.'
            ], 422);
        }

        $proyecto = Proyecto::find($proyectoId);
        if (!$proyecto) {
            return $response->withJson(['error' => 'Proyecto no encontrado.'], 404);
        }

        // Autorización: sólo el líder del proyecto puede gestionar tipos de columnas
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt['sub'] ?? null;
        if (!$userId) {
            return $response->withJson(['error' => 'Token inválido o no autenticado.'], 401);
        }

        $esLider = UsuarioRol::where('id_usuario', $userId)
            ->where('id_proyecto', $proyectoId)
            ->where('id_rol', 1)
            ->where('status', '0')
            ->exists();

        if (!$esLider) {
            return $response->withJson(['error' => 'No autorizado. Sólo el líder del proyecto puede gestionar tipos de columnas.'], 403);
        }

        // Obtener todas las columnas activas del proyecto
        $columnasProyecto = Columna::where('id_proyecto', $proyectoId)
                                ->where('status', '0')
                                ->get()
                                ->keyBy('id_columna');

        // Validar que todas las columnas a modificar existan y sean del proyecto
        foreach ($data['columnas'] as $columnaData) {
            if (!isset($columnaData['id_columna'])) {
                return $response->withJson([
                    'error' => 'Cada columna debe tener id_columna.'
                ], 422);
            }

            $columnaId = $columnaData['id_columna'];
            
            // Verificar que la columna existe y pertenece al proyecto
            if (!isset($columnasProyecto[$columnaId])) {
                return $response->withJson([
                    'error' => "La columna {$columnaId} no existe o no pertenece a este proyecto."
                ], 404);
            }

            // Verificar que no se intente modificar una columna eliminada
            if ($columnasProyecto[$columnaId]->status == '1') {
                return $response->withJson([
                    'error' => "No se puede modificar la columna {$columnaId} porque está eliminada."
                ], 400);
            }

            // Validar valores permitidos para status_fijas (si se proporciona)
            if (array_key_exists('status_fijas', $columnaData)) {
                $statusFijas = $columnaData['status_fijas'];
                if ($statusFijas !== null && $statusFijas !== '' && !in_array($statusFijas, ['1', '2'], true)) {
                    return $response->withJson([
                        'error' => "status_fijas debe ser null, '1' (progreso) o '2' (finalizado)."
                    ], 400);
                }
            }
        }

        // Contar cuántas columnas fijas habrá después de los cambios
        // (status_fijas con valor '1' o '2' = fija)
        $fijasDespues = [];
        foreach ($data['columnas'] as $columnaData) {
            if (array_key_exists('status_fijas', $columnaData) && 
                $columnaData['status_fijas'] !== null && 
                $columnaData['status_fijas'] !== '') {
                $fijasDespues[] = $columnaData['id_columna'];
            }
        }

        // Agregar las columnas fijas que no se están modificando en esta petición
        $columnasNoModificadas = Columna::where('id_proyecto', $proyectoId)
                                    ->where('status', '0')
                                    ->where('tipo_columna', Columna::TIPO_FIJA)
                                    ->whereNotIn('id_columna', array_column($data['columnas'], 'id_columna'))
                                    ->pluck('id_columna')
                                    ->toArray();
        
        $totalFijasDespues = count($fijasDespues) + count($columnasNoModificadas);
        
        if ($totalFijasDespues > self::MAX_COLUMNAS_FIJAS) {
            return $response->withJson([
                'error' => "No se pueden tener más de " . self::MAX_COLUMNAS_FIJAS . " columnas fijas por proyecto."
            ], 400);
        }

        // NUEVA VALIDACIÓN: Verificar que no se repitan status_fijas en el proyecto
        $statusFijasUsados = [];
        foreach ($data['columnas'] as $columnaData) {
            if (array_key_exists('status_fijas', $columnaData) && $columnaData['status_fijas'] !== null) {
                $status = $columnaData['status_fijas'];
                
                // Verificar si ya está usado por otra columna en la misma solicitud
                if (in_array($status, $statusFijasUsados)) {
                    $tipo = $status === '1' ? 'progreso' : 'finalizado';
                    return $response->withJson([
                        'error' => "No puede haber dos columnas con status_fijas '{$tipo}' en el mismo proyecto."
                    ], 400);
                }
                
                // Verificar si ya está usado por otra columna en la base de datos
                $existingStatus = Columna::where('id_proyecto', $proyectoId)
                                    ->where('status', '0')
                                    ->where('status_fijas', $status)
                                    ->where('id_columna', '!=', $columnaData['id_columna'])
                                    ->first();
                
                if ($existingStatus) {
                    $tipo = $status === '1' ? 'progreso' : 'finalizado';
                    return $response->withJson([
                        'error' => "Ya existe una columna fija con status '{$tipo}' en este proyecto."
                    ], 400);
                }
                
                $statusFijasUsados[] = $status;
            }
        }

        // Validar cambios de status_fijas según el estado actual de la columna
        foreach ($data['columnas'] as $columnaData) {
            $columna = $columnasProyecto[$columnaData['id_columna']];
            $tareasCount = $columna->tareas()->where('status', '0')->count();
            
            // Verificar si la columna NUNCA tuvo status_fijas (siempre fue normal)
            $nuncaTuvoStatusFijas = ($columna->tipo_columna === Columna::TIPO_NORMAL && 
                                     $columna->status_fijas === null);
            
            // Verificar si YA TIENE status_fijas (es una columna fija)
            $yaTieneStatusFijas = ($columna->tipo_columna === Columna::TIPO_FIJA && 
                                   $columna->status_fijas !== null);
            
            if ($tareasCount > 0) {
                // Caso 1: Si la columna NUNCA tuvo status_fijas → Se puede establecer aunque tenga tareas
                if ($nuncaTuvoStatusFijas && 
                    array_key_exists('status_fijas', $columnaData) && 
                    $columnaData['status_fijas'] !== null) {
                    // ✅ PERMITIDO: Establecer status_fijas por primera vez aunque tenga tareas
                    continue;
                }
                
                // Caso 2: Si YA TIENE status_fijas y quiere cambiarlo → NO se permite con tareas
                if ($yaTieneStatusFijas && 
                    isset($columnaData['status_fijas']) && 
                    $columnaData['status_fijas'] !== $columna->status_fijas) {
                    
                    $statusActual = $columna->status_fijas === '1' ? 'En Progreso' : 'Finalizado';
                    $statusNuevo = $columnaData['status_fijas'] === '1' ? 'En Progreso' : ($columnaData['status_fijas'] === '2' ? 'Finalizado' : 'Normal');
                    
                    return $response->withJson([
                        'error' => "No se puede cambiar la columna '{$columna->nombre}' de '{$statusActual}' a '{$statusNuevo}' porque tiene {$tareasCount} tareas asociadas. Mueve todas las tareas primero."
                    ], 400);
                }
                
                // Caso 3: Si es columna FIJA y quiere convertirse a NORMAL → NO se permite con tareas
                if ($yaTieneStatusFijas && 
                    isset($columnaData['tipo_columna']) && 
                    $columnaData['tipo_columna'] === Columna::TIPO_NORMAL) {
                    
                    $statusActual = $columna->status_fijas === '1' ? 'En Progreso' : 'Finalizado';
                    
                    return $response->withJson([
                        'error' => "No se puede convertir la columna '{$columna->nombre}' de tipo fija ('{$statusActual}') a normal porque tiene {$tareasCount} tareas activas. Elimina todas las tareas primero."
                    ], 400);
                }
            } else {
                // Caso 3: Si NO tiene tareas pero quiere cambiar el status_fijas, validar que no exista ya ese status_fijas
                if ($columna->tipo_columna === Columna::TIPO_FIJA && 
                    array_key_exists('status_fijas', $columnaData) && 
                    $columnaData['status_fijas'] !== $columna->status_fijas &&
                    $columnaData['status_fijas'] !== null) {
                    
                    // Verificar si ya existe otra columna fija con ese status_fijas
                    $existeOtraColumna = Columna::where('id_proyecto', $proyectoId)
                                        ->where('status', '0')
                                        ->where('tipo_columna', Columna::TIPO_FIJA)
                                        ->where('status_fijas', $columnaData['status_fijas'])
                                        ->where('id_columna', '!=', $columna->id_columna)
                                        ->exists();
                    
                    if ($existeOtraColumna) {
                        $statusNuevo = $columnaData['status_fijas'] === '1' ? 'En Progreso' : 'Finalizado';
                        return $response->withJson([
                            'error' => "Ya existe una columna fija con status '{$statusNuevo}' en este proyecto. No se puede cambiar la columna '{$columna->nombre}' a ese estado."
                        ], 400);
                    }
                }
            }
        }

        // NUEVA ACTUALIZACIÓN: Incluir status_fijas en la transacción
        $this->db->getConnection()->transaction(function() use ($data, $proyectoId) {
            foreach ($data['columnas'] as $columnaData) {
                // Validar que venga el campo status_fijas
                if (!array_key_exists('status_fijas', $columnaData)) {
                    continue; // Si no viene el campo, ignorar esta columna
                }
                
                $updateData = [];
                
                // El tipo_columna se determina automáticamente según status_fijas
                if ($columnaData['status_fijas'] === null || $columnaData['status_fijas'] === '') {
                    // Si status_fijas es null → normal
                    $updateData['tipo_columna'] = Columna::TIPO_NORMAL;
                    $updateData['status_fijas'] = null;
                } else {
                    // Si status_fijas es '1' o '2' → fija
                    $updateData['tipo_columna'] = Columna::TIPO_FIJA;
                    $updateData['status_fijas'] = $columnaData['status_fijas'];
                }
                
                // Ejecutar update
                Columna::where('id_proyecto', $proyectoId)
                    ->where('id_columna', $columnaData['id_columna'])
                    ->where('status', '0')
                    ->update($updateData);
            }
        });

        return $response->withJson([
            'message' => 'Tipos de columnas actualizados exitosamente.'
        ]);
    }

    /**
     * Verificar límite de tareas para una columna
     */
    public function verificarLimiteTareas(Request $request, Response $response, array $args)
    {
        $columna = Columna::find($args['id']);
        
        if (!$columna) {
            return $response->withJson(['error' => 'Columna no encontrada.'], 404);
        }

        // Autorización: sólo miembros del proyecto pueden consultar el límite de tareas
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt['sub'] ?? null;
        if (!$userId) {
            return $response->withJson(['error' => 'Token inválido o no autenticado.'], 401);
        }

        $esMiembro = UsuarioRol::where('id_usuario', $userId)
            ->where('id_proyecto', $columna->id_proyecto)
            ->where('status', '0')
            ->exists();

        if (!$esMiembro) {
            return $response->withJson(['error' => 'No autorizado para consultar el límite de esta columna.'], 403);
        }

        $tareasCount = $columna->tareas()->where('status', '0')->count();
        $limite = $columna->tipo_columna === Columna::TIPO_FIJA ? self::MAX_TAREAS_FIJA : self::MAX_TAREAS_NORMAL;
        $disponible = $limite - $tareasCount;

        return $response->withJson([
            'columna_id' => $columna->id_columna,
            'columna_nombre' => $columna->nombre,
            'tipo_columna' => $columna->tipo_columna,
            'tareas_actuales' => $tareasCount,
            'limite_tareas' => $limite,
            'espacio_disponible' => $disponible,
            'puede_agregar_mas' => $disponible > 0
        ]);
    }
}