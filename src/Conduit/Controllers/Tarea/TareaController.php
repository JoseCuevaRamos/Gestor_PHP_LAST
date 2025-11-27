<?php

namespace Conduit\Controllers\Tarea;

use Conduit\Services\Mail\NotificacionTareaService;
use Conduit\Services\TareaService;
use Conduit\Validation\TareaValidator;
use Conduit\Validation\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Conduit\Transformers\TareaTransformer;
use Conduit\Models\Tarea;
use Conduit\Models\Columna;
use Conduit\Models\Proyecto;
use Conduit\Models\User;
use Conduit\Models\Comentario;
use Conduit\Models\HistorialMovimiento;
use Conduit\Models\UsuarioRol;
use Carbon\Carbon;

class TareaController
{
    private TareaService $svc;
    private NotificacionTareaService $notifier;

    public function __construct($svc = null, $notifier = null)
    {
        $this->svc = $svc instanceof TareaService ? $svc : new TareaService();
        $this->notifier = $notifier instanceof NotificacionTareaService
            ? $notifier
            : new NotificacionTareaService();
    }

    /**
     * ⭐ MÉTODO AUXILIAR: Enviar notificaciones según corresponda
     */
    private function enviarNotificacionesSiCorresponde(Tarea $tarea): void
    {
        // Cargar relaciones necesarias para los correos
        $tarea->loadMissing(['asignado', 'proyecto', 'columna']);

        // Solo si hay usuario asignado con correo
        if (!$tarea->asignado || empty($tarea->asignado->correo)) {
            return;
        }

        // 1️⃣ Correo de asignación (siempre que haya asignado)
        $this->notifier->enviarAsignacion($tarea);

        // 2️⃣ Verificar si vence mañana (zona horaria Perú)
        if ($tarea->due_at) {
            $nowPeru = Carbon::now('America/Lima');
            $duePeru = $tarea->due_at instanceof Carbon
                ? $tarea->due_at->copy()->timezone('America/Lima')
                : Carbon::parse($tarea->due_at, 'America/Lima');

            // Si vence mañana, enviar recordatorio AL INSTANTE
            if ($duePeru->isSameDay($nowPeru->copy()->addDay())) {
                $this->notifier->enviarRecordatorioVencimiento($tarea);
            }
        }
    }

    public function assign(Request $req, Response $res, array $args): Response
    {
        $this->runValidation($req, TareaValidator::assign());
        $data = (array)$req->getParsedBody();

        $t = $this->svc->assign((int)$args['id'], $data['id_asignado'] ?? null);

        // ⭐ Usar método auxiliar para notificaciones
        $this->enviarNotificacionesSiCorresponde($t);

        return $this->json($res, TareaTransformer::one($t));
    }

    private function json(Response $res, $data, int $code = 200): Response
    {
        $res->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $res->withHeader('Content-Type', 'application/json')->withStatus($code);
    }

    private function runValidation(Request $req, array $rules): void
    {
        (new Validator())->validate($req, $rules);
    }

    /** ===================== GET ===================== */
    public function index(Request $req, Response $res): Response
    {
        $p = $req->getQueryParams();
        $p['status'] = '0';
        $list = $this->svc->list($p, (int)($p['per_page'] ?? 20));
        return $this->json($res, $list);
    }

    public function show(Request $req, Response $res, array $args): Response
    {
        $t = Tarea::where('status', '0')
            ->with(['columna', 'creador', 'asignado', 'comentarios.usuario'])
            ->find((int)$args['id']);

        if (!$t) {
            return $this->json($res, [
                'error' => 'La tarea no existe o fue eliminada.'
            ], 404);
        }

        return $this->json($res, TareaTransformer::one($t));
    }

    /** ===================== RESUMEN (VISTA TRELLO) ===================== */
    public function resumenPorProyecto(Request $req, Response $res, array $args): Response
    {
        $idProyecto = (int)$args['id_proyecto'];

        $tareas = Tarea::where('id_proyecto', $idProyecto)
            ->where('status', '0')
            ->withCount('comentarios')
            ->with('columna:id_columna,nombre,posicion',
            'asignado:id_usuario,nombre')
            ->orderBy('id_columna')
            ->orderBy('position', 'asc')
            ->get(['id_tarea', 'titulo', 'prioridad', 'id_columna', 'updated_at', 'position', 'id_asignado']);

        $agrupado = $tareas->groupBy(fn($t) => optional($t->columna)->nombre ?? 'Sin columna');

        $data = $agrupado->map(function ($tareas, $columnaNombre) {
            $first = $tareas->first();
            return [
                'id_columna' => $first->id_columna ?? null,
                'columna' => $columnaNombre,
                'tareas' => $tareas->map(function ($t) {
                    return [
                        'id_tarea' => $t->id_tarea,
                        'titulo' => $t->titulo,
                        'prioridad' => $t->prioridad,
                        'comentarios_count' => $t->comentarios_count,
                        'ultima_actualizacion' => $t->updated_at,
                        'position' => $t->position,
                        'id_asignado' => $t->id_asignado,  
                    'asignado' => $t->asignado ? [   
                        'id' => $t->asignado->id_usuario,
                        'nombre' => $t->asignado->nombre
                    ] : null,
                    ];
                })->values(),
            ];
        })->values();

        return $this->json($res, $data);
    }

    /** ===================== POST ===================== */
    public function store(Request $req, Response $res): Response
    {
        $jwt = $req->getAttribute('jwt');
        if (!$jwt || !isset($jwt['sub'])) {
            return $this->json($res, ['error' => 'Token inválido o faltante.'], 401);
        }

        $data = (array)$req->getParsedBody();

        // Validación: ambos campos son obligatorios
        if (empty($data['titulo'])) {
            return $this->json($res, [
                'error' => "El campo 'titulo' es obligatorio para crear una tarea."
            ], 400);
        }
        if (mb_strlen($data['titulo']) > 30) {
            return $this->json($res, [
                'error' => "El título de la tarea no puede tener más de 30 caracteres."
            ], 422);
        }

        if (isset($data['descripcion']) && mb_strlen($data['descripcion']) > 1000) {
            return $this->json($res, [
                'error' => "La descripción de la tarea no puede tener más de 1000 caracteres."
            ], 422);
        }

        if (empty($data['prioridad'])) {
            return $this->json($res, [
                'error' => "El campo 'prioridad' es obligatorio para crear una tarea."
            ], 400);
        }

        $this->runValidation($req, TareaValidator::store());

        $columna = Columna::where('id_columna', $data['id_columna'])
            ->where('status', '0')->first();
        $proyecto = Proyecto::where('id_proyecto', $data['id_proyecto'])
            ->where('status', '0')->first();

        if (!$columna || !$proyecto) {
            return $this->json($res, [
                'error' => 'No se puede crear la tarea porque la columna o el proyecto están eliminados.'
            ], 400);
        }

        $creadorExiste = User::where('id_usuario', $data['id_creador'])->exists();
        if (!$creadorExiste) {
            return $this->json($res, [
                'error' => 'El usuario creador no existe en el sistema.'
            ], 400);
        }

        // VALIDAR: No permitir tareas con el mismo nombre en todo el proyecto
        $duplicado = Tarea::where('id_proyecto', $data['id_proyecto'])
            ->where('titulo', $data['titulo'])
            ->where('status', '0')
            ->exists();
        if ($duplicado) {
            return $this->json($res, [
                'error' => 'Ya existe una tarea con este nombre en el proyecto.'
            ], 422);
        }

        // ==================== LÍMITES ====================
        // Determinar límite según tipo de columna
        $limiteColumna = ($columna->tipo_columna === Columna::TIPO_FIJA) ? 200 : 20;
        
        $totalColumna = Tarea::where('id_columna', $data['id_columna'])
            ->where('status', '0')->count();
        if ($totalColumna >= $limiteColumna) {
            return $this->json($res, [
                'error' => "No se pueden crear más de {$limiteColumna} tareas en esta columna."
            ], 400);
        }

        $totalProyecto = Tarea::where('id_proyecto', $data['id_proyecto'])
            ->where('status', '0')->count();
        if ($totalProyecto >= 200) {
            return $this->json($res, [
                'error' => 'El proyecto ha alcanzado el máximo de 200 tareas activas.'
            ], 400);
        }

        $totalColumnas = Columna::where('id_proyecto', $data['id_proyecto'])
            ->where('status', '0')->count();
        if ($totalColumnas > 10) {
            return $this->json($res, [
                'error' => 'El proyecto no puede tener más de 10 columnas activas.'
            ], 400);
        }
        // =================================================

        $data['status'] = '0';
        $t = $this->svc->create($data);

        // ⭐ REGISTRAR movimiento inicial (creación de la tarea)
        $userId = $jwt['sub'] ?? null;
        HistorialMovimiento::create([
            'id_tarea' => $t->id_tarea,
            'id_columna_anterior' => null,
            'id_columna_nueva' => $t->id_columna,
            'id_usuario' => $userId,
            'timestamp' => Carbon::now(),
        ]);

        // ⭐ ENVIAR NOTIFICACIONES SI CORRESPONDE (asignado + vencimiento)
        $this->enviarNotificacionesSiCorresponde($t);

        return $this->json($res, [
            'message' => 'Tarea creada exitosamente.',
            'tarea' => TareaTransformer::one($t)
        ], 201);
    }

    /** ===================== PUT ===================== */
    public function update(Request $req, Response $res, array $args): Response
    {
        $jwt = $req->getAttribute('jwt');
        if (!$jwt || !isset($jwt['sub'])) {
            return $this->json($res, ['error' => 'Token inválido o faltante.'], 401);
        }

        $this->runValidation($req, TareaValidator::update());
        $data = (array)$req->getParsedBody();

        if (isset($data['titulo']) && mb_strlen($data['titulo']) > 30) {
            return $this->json($res, [
                'error' => "El título de la tarea no puede tener más de 30 caracteres."
            ], 422);
        }
        if (isset($data['descripcion']) && mb_strlen($data['descripcion']) > 1000) {
            return $this->json($res, [
                'error' => "La descripción de la tarea no puede tener más de 1000 caracteres."
            ], 422);
        }

        $tareaActual = Tarea::where('status', '0')->find((int)$args['id']);
        if (!$tareaActual) {
            return $this->json($res, ['error' => 'La tarea no existe o fue eliminada.'], 404);
        }

        // VALIDAR: No permitir tareas con el mismo nombre en todo el proyecto
        if (isset($data['titulo'])) {
            $duplicado = Tarea::where('id_proyecto', $tareaActual->id_proyecto)
                ->where('titulo', $data['titulo'])
                ->where('status', '0')
                ->where('id_tarea', '!=', $tareaActual->id_tarea)
                ->exists();
            if ($duplicado) {
                return $this->json($res, [
                    'error' => 'Ya existe otra tarea con este nombre en el proyecto.'
                ], 422);
            }
        }

        // ⭐ DETECTAR CAMBIOS REALES
        $cambioAsignado = isset($data['id_asignado']) && $data['id_asignado'] != $tareaActual->id_asignado;
        
        // Verificar si due_at cambió Y ahora vence mañana
        $cambioDueAtAManana = false;
        if (isset($data['due_at'])) {
            $dueAnterior = $tareaActual->due_at ? Carbon::parse($tareaActual->due_at)->timezone('America/Lima')->toDateString() : null;
            $dueNuevo = $data['due_at'] ? Carbon::parse($data['due_at'])->timezone('America/Lima')->toDateString() : null;
            $manana = Carbon::now('America/Lima')->addDay()->toDateString();
            
            // Solo si cambió Y la nueva fecha es mañana
            $cambioDueAtAManana = ($dueAnterior !== $dueNuevo) && ($dueNuevo === $manana);
        }

        $t = $this->svc->update((int)$args['id'], $data);

        // ⭐ ENVIAR CORREO DE ASIGNACIÓN SOLO SI CAMBIÓ EL USUARIO ASIGNADO
        if ($cambioAsignado) {
            $t->loadMissing(['asignado', 'proyecto', 'columna']);
            if ($t->asignado && !empty($t->asignado->correo)) {
                $this->notifier->enviarAsignacion($t);
            }
        }

        // ⭐ ENVIAR CORREO DE VENCIMIENTO SOLO SI CAMBIÓ A "MAÑANA"
        if ($cambioDueAtAManana) {
            $t->loadMissing(['asignado', 'proyecto', 'columna']);
            if ($t->asignado && !empty($t->asignado->correo)) {
                $this->notifier->enviarRecordatorioVencimiento($t);
            }
        }

        return $this->json($res, [
            'message' => 'Tarea actualizada correctamente.',
            'tarea' => TareaTransformer::one($t)
        ]);
    }

    /** ===================== DELETE ===================== */
    public function destroy(Request $req, Response $res, array $args): Response
    {
        $jwt = $req->getAttribute('jwt');
        if (!$jwt || !isset($jwt['sub'])) {
            return $this->json($res, ['error' => 'Token inválido o faltante.'], 401);
        }

        $ok = $this->svc->delete((int)$args['id']);
        return $ok
            ? $this->json($res, ['message' => 'Tarea eliminada correctamente.'])
            : $this->json($res, ['error' => 'La tarea no existe o ya fue eliminada.'], 404);
    }

    /** ===================== PATCH ===================== */
    public function move(Request $req, Response $res, array $args): Response
    {
        $jwt = $req->getAttribute('jwt');
        if (!$jwt || !isset($jwt['sub'])) {
            return $this->json($res, ['error' => 'Token inválido o faltante.'], 401);
        }
        $this->runValidation($req, TareaValidator::move());
        $data = (array)$req->getParsedBody();

        $idTarea = (int)$args['id'];
        $idColumnaDestino = (int)$data['id_columna'];

        $tarea = Tarea::where('status', '0')->find($idTarea);
        if (!$tarea) {
            return $this->json($res, ['error' => 'La tarea no existe o fue eliminada.'], 404);
        }

        // ⭐ GUARDAR la columna anterior ANTES de mover
        $idColumnaAnterior = $tarea->id_columna;

        // Autorizar: solo miembros pueden mover tareas
        $userId = $jwt['sub'] ?? null;
        $esMiembro = UsuarioRol::where('id_usuario', $userId)
            ->where('id_proyecto', $tarea->id_proyecto)
            ->where('status', '0')
            ->exists();
        if (!$esMiembro) {
            return $this->json($res, ['error' => 'Solo miembros pueden mover tareas.'], 403);
        }

        // VALIDAR: Restricciones de movimiento según columna origen
        $columnaOrigen = Columna::find($tarea->id_columna);
        
        // Regla 1: NO se puede mover desde columna FINALIZADO
        if ($columnaOrigen && $columnaOrigen->status_fijas === Columna::STATUS_FIJA_FINALIZADO) {
            return $this->json($res, [
                'error' => 'No se puede mover una tarea desde una columna finalizada.'
            ], 400);
        }

        $columnaDestino = Columna::where('id_columna', $idColumnaDestino)
            ->where('status', '0')->first();
        if (!$columnaDestino) {
            return $this->json($res, ['error' => 'La columna de destino no existe o está eliminada.'], 400);
        }

        // Regla 2: Desde columnas NORMALES NO se puede mover directamente a "Finalizado"
        if ($columnaOrigen && $columnaOrigen->status_fijas === null) {
            if ($columnaDestino->status_fijas === Columna::STATUS_FIJA_FINALIZADO) {
                return $this->json($res, [
                    'error' => 'Las tareas deben pasar primero por "En Progreso" antes de ser finalizadas.'
                ], 400);
            }
        }
        
        // Regla 3: Desde "En Progreso" SOLO se puede mover a "Finalizado"
        if ($columnaOrigen && $columnaOrigen->status_fijas === Columna::STATUS_FIJA_PROGRESO) {
            if ($columnaDestino->status_fijas !== Columna::STATUS_FIJA_FINALIZADO) {
                return $this->json($res, [
                    'error' => 'Las tareas en progreso solo pueden moverse a la columna finalizada.'
                ], 400);
            }
        }

        // VALIDAR: Límite según tipo de columna (20 normal, 200 fija)
        $limiteDestino = ($columnaDestino->tipo_columna === Columna::TIPO_FIJA) ? 200 : 20;
        $totalColumnaDestino = Tarea::where('id_columna', $idColumnaDestino)
            ->where('status', '0')
            ->where('id_tarea', '!=', $idTarea)
            ->count();
            
        if ($totalColumnaDestino >= $limiteDestino) {
            return $this->json($res, [
                'error' => "No se puede mover la tarea: la columna destino ya tiene el máximo de {$limiteDestino} tareas activas."
            ], 400);
        }

        $t = $this->svc->move($idTarea, $idColumnaDestino, (int)($data['position'] ?? 0));
        
        // AUTO-ACTUALIZAR timestamps según tipo de columna destino
        if ($columnaDestino->status_fijas === Columna::STATUS_FIJA_PROGRESO) {
            $t->started_at = $t->started_at ?? Carbon::now();
            $t->completed_at = null;
            $t->save();
        } elseif ($columnaDestino->status_fijas === Columna::STATUS_FIJA_FINALIZADO) {
            $t->completed_at = Carbon::now();
            $t->save();
        }
        
        // ⭐ REGISTRAR el movimiento CON columna anterior
        HistorialMovimiento::create([
            'id_tarea' => $idTarea,
            'id_columna_anterior' => $idColumnaAnterior,
            'id_columna_nueva' => $idColumnaDestino,
            'id_usuario' => $userId,
            'timestamp' => Carbon::now(),
        ]);

        return $this->json($res, [
            'message' => 'Tarea movida correctamente.',
            'tarea' => TareaTransformer::one($t)
        ]);
    }

    public function setDue(Request $req, Response $res, array $args): Response
    {
        $this->runValidation($req, TareaValidator::setDue());
        $data = (array)$req->getParsedBody();
        
        // ⭐ OBTENER TAREA ANTES DE ACTUALIZAR
        $tareaActual = Tarea::where('status', '0')->find((int)$args['id']);
        if (!$tareaActual) {
            return $this->json($res, ['error' => 'La tarea no existe o fue eliminada.'], 404);
        }
        
        // Verificar si due_at cambió Y ahora vence mañana
        $cambioDueAtAManana = false;
        if (isset($data['due_at'])) {
            $dueAnterior = $tareaActual->due_at ? Carbon::parse($tareaActual->due_at)->timezone('America/Lima')->toDateString() : null;
            $dueNuevo = $data['due_at'] ? Carbon::parse($data['due_at'])->timezone('America/Lima')->toDateString() : null;
            $manana = Carbon::now('America/Lima')->addDay()->toDateString();
            
            $cambioDueAtAManana = ($dueAnterior !== $dueNuevo) && ($dueNuevo === $manana);
        }
        
        $t = $this->svc->setDue((int)$args['id'], $data['due_at'] ?? null);
        
        // ⭐ ENVIAR CORREO SOLO SI CAMBIÓ A "MAÑANA"
        if ($cambioDueAtAManana && $t->id_asignado) {
            $t->loadMissing(['asignado', 'proyecto', 'columna']);
            if ($t->asignado && !empty($t->asignado->correo)) {
                $this->notifier->enviarRecordatorioVencimiento($t);
            }
        }
        
        return $this->json($res, TareaTransformer::one($t));
    }

    public function start(Request $req, Response $res, array $args): Response
    {
        $t = $this->svc->start((int)$args['id']);
        return $this->json($res, TareaTransformer::one($t));
    }

    public function complete(Request $req, Response $res, array $args): Response
    {
        $t = $this->svc->complete((int)$args['id']);
        return $this->json($res, TareaTransformer::one($t));
    }

    /** ===================== BULK ===================== */
    public function bulkReorder(Request $req, Response $res): Response
    {
        $this->runValidation($req, TareaValidator::bulkReorder());
        $data = (array)$req->getParsedBody();
        $items = $this->svc->bulkReorder((int)$data['id_columna'], $data['items']);
        return $this->json($res, TareaTransformer::many(collect($items)));
    }

    public function bulkMove(Request $req, Response $res): Response
    {
        $this->runValidation($req, TareaValidator::bulkMove());
        $data = (array)$req->getParsedBody();

        $idColumnaDestino = (int)$data['id_columna_destino'];
        
        // VALIDAR: Restricciones según columna origen
        
        // Regla 1: Que ninguna tarea venga de columna FINALIZADO
        $tareasDesdeFinalizado = Tarea::whereIn('id_tarea', $data['ids'])
            ->whereHas('columna', function($q) {
                $q->where('status_fijas', Columna::STATUS_FIJA_FINALIZADO);
            })
            ->count();
            
        if ($tareasDesdeFinalizado > 0) {
            return $this->json($res, [
                'error' => 'No se pueden mover tareas desde una columna finalizada.'
            ], 400);
        }
        
        $columnaDestino = Columna::find($idColumnaDestino);
        if (!$columnaDestino) {
            return $this->json($res, ['error' => 'La columna de destino no existe.'], 404);
        }
        
        // Regla 1.5: Desde columnas NORMALES NO se puede mover directamente a "Finalizado"
        $tareasDesdeNormal = Tarea::whereIn('id_tarea', $data['ids'])
            ->whereHas('columna', function($q) {
                $q->whereNull('status_fijas');
            })
            ->count();
            
        if ($tareasDesdeNormal > 0 && $columnaDestino->status_fijas === Columna::STATUS_FIJA_FINALIZADO) {
            return $this->json($res, [
                'error' => 'Las tareas deben pasar primero por "En Progreso" antes de ser finalizadas.'
            ], 400);
        }
        
        // Regla 3: Desde "En Progreso" SOLO se puede mover a "Finalizado"
        $tareasDesdeProgreso = Tarea::whereIn('id_tarea', $data['ids'])
            ->whereHas('columna', function($q) {
                $q->where('status_fijas', Columna::STATUS_FIJA_PROGRESO);
            })
            ->count();
            
        if ($tareasDesdeProgreso > 0 && $columnaDestino->status_fijas !== Columna::STATUS_FIJA_FINALIZADO) {
            return $this->json($res, [
                'error' => 'Las tareas en progreso solo pueden moverse a la columna finalizada.'
            ], 400);
        }
        
        // VALIDAR: Límite según tipo de columna (20 normal, 200 fija)
        $limiteDestino = ($columnaDestino->tipo_columna === Columna::TIPO_FIJA) ? 200 : 20;
        $totalColumnaDestino = Tarea::where('id_columna', $idColumnaDestino)
            ->where('status', '0')->count();

        if ($totalColumnaDestino + count($data['ids']) > $limiteDestino) {
            return $this->json($res, [
                'error' => "No se pueden mover las tareas: la columna destino superaría el máximo de {$limiteDestino} tareas."
            ], 400);
        }

        // ⭐ OBTENER id_usuario del JWT
        $jwt = $req->getAttribute('jwt');
        $userId = $jwt['sub'] ?? null;

        // ⭐ OBTENER columnas anteriores ANTES de mover
        $tareasConColumnaAnterior = Tarea::whereIn('id_tarea', $data['ids'])
            ->pluck('id_columna', 'id_tarea')
            ->toArray();

        $this->svc->bulkMove($idColumnaDestino, $data['ids'], $data['start_position'] ?? null);

        // ⭐ REGISTRAR movimientos CON columna anterior
        foreach ($data['ids'] as $idTarea) {
            HistorialMovimiento::create([
                'id_tarea' => $idTarea,
                'id_columna_anterior' => $tareasConColumnaAnterior[$idTarea] ?? null,
                'id_columna_nueva' => $idColumnaDestino,
                'id_usuario' => $userId,
                'timestamp' => Carbon::now(),
            ]);
        }

        return $this->json($res, ['ok' => true]);
    }

    /** ===================== HEAD ===================== */
    public function head(Request $req, Response $res, array $args): Response
    {
        $t = Tarea::where('status', '0')
            ->select('id_tarea', 'updated_at')
            ->find((int)$args['id']);
        if (!$t) {
            return $this->json($res, ['error' => 'La tarea no existe o fue eliminada.'], 404);
        }

        $etag = '"' . md5($t->id_tarea . '|' . ($t->updated_at ?? '')) . '"';
        $last = gmdate('D, d M Y H:i:s', strtotime((string)$t->updated_at ?: 'now')) . ' GMT';

        return $res
            ->withHeader('ETag', $etag)
            ->withHeader('Last-Modified', $last)
            ->withHeader('Content-Length', '0')
            ->withStatus(200);
    }

    public function headIndex(Request $req, Response $res): Response
    {
        $p = $req->getQueryParams();
        $q = Tarea::query()
            ->where('status', '0')
            ->when(isset($p['proyecto']), fn($x) => $x->where('id_proyecto', $p['proyecto']))
            ->when(isset($p['columna']), fn($x) => $x->where('id_columna', $p['columna']));
        $total = (string)$q->count();

        return $res
            ->withHeader('X-Total-Count', $total)
            ->withHeader('Allow', 'GET, POST, HEAD')
            ->withHeader('Content-Length', '0')
            ->withStatus(200);
    }
}