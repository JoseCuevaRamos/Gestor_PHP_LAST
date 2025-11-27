<?php
namespace Conduit\Validation;

use Respect\Validation\Validator as v;

class TareaValidator
{
    /** ===================== CREAR TAREA ===================== */
    public static function store(): array
    {
        return [
            'id_proyecto'  => v::intVal()->positive(),
            'id_columna'   => v::intVal()->positive(),
            'titulo'       => v::stringType()->length(1, 255),
            // 🔹 Limitar descripción a máximo 1000 caracteres
            'descripcion'  => v::optional(v::stringType()->length(0, 1000)),
            'id_asignado'  => v::optional(v::intVal()->positive()),
            'id_creador'   => v::intVal()->positive(),

            // 🔹 Prioridad OBLIGATORIA
            'prioridad'    => v::in(['No definido', 'Baja', 'Media', 'Alta']),

            // 🔹 Fechas opcionales en formato ISO 8601
            'due_at'       => v::optional(v::date('Y-m-d\TH:i:sP')),
            'started_at'   => v::optional(v::date('Y-m-d\TH:i:sP')),
            'completed_at' => v::optional(v::date('Y-m-d\TH:i:sP')),
            'status'       => v::in(['0', '1']),
        ];
    }

    /** ===================== ACTUALIZAR TAREA ===================== */
    public static function update(): array
    {
        return [
            'titulo'       => v::optional(v::stringType()->length(1, 255)),
            'descripcion'  => v::optional(v::stringType()->length(0, 1000)),
            'id_columna'   => v::optional(v::intVal()->positive()),
            'id_asignado'  => v::optional(v::intVal()->positive()),

            // 🔹 Prioridad opcional en update
            'prioridad'    => v::optional(v::in(['No definido', 'Baja', 'Media', 'Alta'])),

            'due_at'       => v::optional(v::date('Y-m-d\TH:i:sP')),
            'started_at'   => v::optional(v::date('Y-m-d\TH:i:sP')),
            'completed_at' => v::optional(v::date('Y-m-d\TH:i:sP')),
            'status'       => v::optional(v::in(['0', '1'])),
        ];
    }

    /** ===================== MOVER EN TABLERO (KANBAN) ===================== */
    public static function move(): array
    {
        return [
            'id_columna' => v::intVal()->positive(),
            'position'   => v::intVal()->min(0),
        ];
    }

    /** ===================== ASIGNAR / DESASIGNAR ===================== */
    public static function assign(): array
    {
        return [
            'id_asignado' => v::optional(v::intVal()->positive()), // null = desasignar
        ];
    }

    /** ===================== ESTABLECER FECHA DE VENCIMIENTO ===================== */
    public static function setDue(): array
    {
        return [
            'due_at' => v::optional(v::date('Y-m-d\TH:i:sP')),
        ];
    }

    /** ===================== REORDENAR LOTE ===================== */
    public static function bulkReorder(): array
    {
        return [
            'id_columna' => v::intVal()->positive(),
            'items'      => v::arrayType()->notEmpty(), // [{id, position}]
        ];
    }

    /** ===================== MOVER LOTE ===================== */
    public static function bulkMove(): array
    {
        return [
            'id_columna_destino' => v::intVal()->positive(),
            'ids'                => v::arrayType()->notEmpty(), // [1,2,3]
            'start_position'     => v::optional(v::intVal()->min(0)),
        ];
    }
}
