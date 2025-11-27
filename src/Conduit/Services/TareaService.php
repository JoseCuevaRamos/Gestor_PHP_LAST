<?php
namespace Conduit\Services;

use Conduit\Models\Tarea;
use Illuminate\Database\Capsule\Manager as DB;
use Carbon\Carbon;

class TareaService
{
    /** ===================== LISTAR TAREAS ===================== */
    public function list(array $filtros = [], int $perPage = 20)
    {
        $q = Tarea::activas()
            ->when(isset($filtros['proyecto']), fn($x) => $x->where('id_proyecto', $filtros['proyecto']))
            ->when(isset($filtros['columna']), fn($x) => $x->where('id_columna', $filtros['columna']))
            ->when(isset($filtros['asignado']), fn($x) => $x->where('id_asignado', $filtros['asignado']))
            ->when(isset($filtros['q']), function ($x) use ($filtros) {
                $s = '%' . $filtros['q'] . '%';
                $x->where(fn($w) => $w->where('titulo', 'like', $s)
                                      ->orWhere('descripcion', 'like', $s));
            })
            ->when(isset($filtros['estado']), function ($x) use ($filtros) {
                return match ($filtros['estado']) {
                    'completado' => $x->whereNotNull('completed_at'),
                    default      => $x,
                };
            });

        $q->orderBy($filtros['sort'] ?? 'position', $filtros['order'] ?? 'asc');

        $page = max(1, (int)($filtros['page'] ?? 1));
        if (class_exists(\Illuminate\Pagination\Paginator::class)) {
            \Illuminate\Pagination\Paginator::currentPageResolver(fn() => $page);
            return $q->paginate($perPage);
        }

        $total = (clone $q)->count();
        $items = $q->offset(($page - 1) * $perPage)->limit($perPage)->get();
        return [
            'current_page' => $page,
            'data'         => $items->toArray(),
            'from'         => $total ? (($page - 1) * $perPage) + 1 : null,
            'last_page'    => (int)ceil($total / $perPage),
            'per_page'     => $perPage,
            'to'           => $total ? min($page * $perPage, $total) : null,
            'total'        => $total,
        ];
    }

    /** ===================== CREAR TAREA ===================== */
    public function create(array $data): Tarea
    {
        // 🔹 Posición automática
        $data['position'] = $data['position']
            ?? ((Tarea::where('id_columna', $data['id_columna'])->max('position') ?? -1) + 1);

        $data['status'] = $data['status'] ?? '0'; // 0 = activo

        // 🔹 Normalizar prioridad
        $data['prioridad'] = ucfirst(strtolower($data['prioridad']));

        // 🔹 Convertir fechas ISO8601 a formato Y-m-d H:i:s
        foreach (['due_at', 'started_at', 'completed_at'] as $campo) {
            if (!empty($data[$campo])) {
                try {
                    $data[$campo] = Carbon::parse($data[$campo])->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    $data[$campo] = null; // Evita error si viene en formato inválido
                }
            }
        }

        return Tarea::create($data);
    }

    /** ===================== ACTUALIZAR TAREA ===================== */
    public function update(int $id, array $data): Tarea
    {
        $t = Tarea::activas()->findOrFail($id);

        // 🔹 Normalizar prioridad si se envía
        if (isset($data['prioridad'])) {
            $data['prioridad'] = ucfirst(strtolower($data['prioridad']));
        }

        // 🔹 Convertir fechas ISO8601 a formato Y-m-d H:i:s
        foreach (['due_at', 'started_at', 'completed_at'] as $campo) {
            if (!empty($data[$campo])) {
                try {
                    $data[$campo] = Carbon::parse($data[$campo])->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    $data[$campo] = null;
                }
            }
        }

        $t->fill($data)->save();
        return $t;
    }

    /** ===================== ELIMINACIÓN LÓGICA ===================== */
    public function delete(int $id): bool
    {
        return (bool) Tarea::where('id_tarea', $id)->update(['status' => '1']);
    }

    /** ===================== MOVER EN TABLERO (KANBAN) ===================== */
    public function move(int $id, int $newCol, int $newPos): Tarea
    {
        return DB::transaction(function () use ($id, $newCol, $newPos) {
            $t = Tarea::findOrFail($id);

            // compactar origen (solo si position no es null)
            if ($t->position !== null) {
                Tarea::where('id_columna', $t->id_columna)
                    ->where('position', '>', $t->position)
                    ->decrement('position');
            }

            // abrir hueco en destino
            Tarea::where('id_columna', $newCol)
                ->where('position', '>=', $newPos)
                ->increment('position');

            $t->id_columna = $newCol;
            $t->position   = $newPos;
            $t->save();

            return $t;
        });
    }

    /** ===================== ASIGNAR / DESASIGNAR ===================== */
    public function assign(int $id, ?int $userId): Tarea
    {
        $t = Tarea::activas()->findOrFail($id);
        $t->id_asignado = $userId;
        $t->save();
        return $t;
    }

    /** ===================== DEFINIR FECHA DE VENCIMIENTO ===================== */
    public function setDue(int $id, ?string $due): Tarea
    {
        $t = Tarea::activas()->findOrFail($id);
        $t->due_at = $due ? Carbon::parse($due)->format('Y-m-d H:i:s') : null;
        $t->save();
        return $t;
    }

    /** ===================== INICIAR TAREA ===================== */
    public function start(int $id): Tarea
    {
        $t = Tarea::activas()->findOrFail($id);
        $t->started_at   = Carbon::now();
        $t->completed_at = null;
        $t->save();
        return $t;
    }

    /** ===================== COMPLETAR TAREA ===================== */
    public function complete(int $id): Tarea
    {
        $t = Tarea::activas()->findOrFail($id);
        $t->completed_at = Carbon::now();
        $t->save();
        return $t;
    }

    /** ===================== REORDENAR LOTE ===================== */
    public function bulkReorder(int $columna, array $items): array
    {
        DB::transaction(function () use ($columna, $items) {
            foreach ($items as $it) {
                Tarea::where('id_tarea', $it['id'])
                    ->where('id_columna', $columna)
                    ->update(['position' => $it['position']]);
            }
        });

        return Tarea::where('id_columna', $columna)
            ->orderBy('position')
            ->get()
            ->all();
    }

    /** ===================== MOVER LOTE ENTRE COLUMNAS ===================== */
    public function bulkMove(int $dest, array $ids, ?int $start = null): void
    {
        DB::transaction(function () use ($dest, $ids, $start) {
            $start = $start ?? ((Tarea::where('id_columna', $dest)->max('position') ?? -1) + 1);

            Tarea::where('id_columna', $dest)
                ->where('position', '>=', $start)
                ->increment('position', count($ids));

            foreach ($ids as $k => $id) {
                $t = Tarea::lockForUpdate()->findOrFail($id);

                Tarea::where('id_columna', $t->id_columna)
                    ->where('position', '>', $t->position)
                    ->decrement('position');

                $t->id_columna = $dest;
                $t->position   = $start + $k;
                $t->save();
            }
        });
    }

}
