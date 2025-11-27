<?php
namespace Conduit\Transformers;

use Conduit\Models\Tarea;

class TareaTransformer
{
    /**
     * ===============================
     * 🔹 Transforma una sola tarea
     * ===============================
     */
    public static function one(Tarea $t): array
    {
        return [
            'id'           => $t->id_tarea,
            'proyecto'     => $t->id_proyecto,
            'columna'      => $t->columna->nombre ?? $t->id_columna,
            'titulo'       => $t->titulo,
            'descripcion'  => $t->descripcion,
            'prioridad'    => ucfirst(strtolower($t->prioridad ?? '')),
            'position'     => $t->position,
            'status'       => $t->status,

            /**
             * ===============================
             * 🔹 Fechas importantes (con null-safe)
             * ===============================
             */
            'due_at'        => $t->due_at ? $t->due_at->toIso8601String() : null,
            'started_at'    => $t->started_at ? $t->started_at->toIso8601String() : null,
            'completed_at'  => $t->completed_at ? $t->completed_at->toIso8601String() : null,
            'created_at'    => $t->created_at ? $t->created_at->toIso8601String() : null,
            'updated_at'    => $t->updated_at ? $t->updated_at->toIso8601String() : null,

            /**
             * ===============================
             * 🔹 Información del creador
             * ===============================
             */
            'creador' => $t->creador ? [
                'id'     => $t->creador->id_usuario,
                'nombre' => $t->creador->nombre,
                'correo' => $t->creador->correo,
            ] : null,

            /**
             * 🔹 Información del usuario asignado
             */
            'asignado' => $t->asignado ? [
                'id'     => $t->asignado->id_usuario,
                'nombre' => $t->asignado->nombre,
                'correo' => $t->asignado->correo,
            ] : null,

            /**
             * ===============================
             * 🔹 Comentarios asociados
             * ===============================
             */
            'comentarios' => $t->comentarios && $t->comentarios->count() > 0
                ? $t->comentarios->map(fn($c) => [
                    'id'        => $c->id_comentario,
                    'contenido' => $c->contenido,
                    'autor'     => $c->usuario ? $c->usuario->nombre : null,
                    'fecha'     => $c->created_at ? $c->created_at->toIso8601String() : null,
                    'status'    => $c->status,
                ])->toArray()
                : [],
        ];
    }

    /**
     * ===============================
     * 🔹 Transforma una colección de tareas
     * ===============================
     */
    public static function many($tareas): array
    {
        $items = $tareas instanceof \Illuminate\Support\Collection ? $tareas->all() : $tareas;
        return array_map(fn($t) => self::one($t), $items);
    }
}
