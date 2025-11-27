<?php
namespace Conduit\Models;
use Illuminate\Database\Eloquent\Model;

class Tarea extends Model
{
    protected $table = 'tareas';
    protected $primaryKey = 'id_tarea';
    public $timestamps = true;

    protected $fillable = [
        'id_proyecto',      // ID del proyecto al que pertenece la tarea
        'id_columna',       // ID de la columna actual donde está la tarea (estado actual)
        'titulo',           // Título o nombre de la tarea
        'descripcion',      // Descripción detallada de la tarea
        'id_creador',       // ID del usuario que creó la tarea
        'id_asignado',      // ID del usuario asignado a la tarea
        'position',         // Posición de la tarea dentro de la columna (orden)
        'due_at',           // Fecha límite para completar la tarea
        'started_at',       // Fecha en que se empezó la tarea
        'completed_at',     // Fecha en que se completó la tarea
        'status',           // Estado de la tarea (ej. activa, archivada, eliminada)
        'prioridad',        // Prioridad de la tarea (ej. baja, media, alta)
        'created_at',       // Fecha de creación de la tarea
        'updated_at',       // Fecha de última actualización de la tarea
    ];

    protected $casts = [
        'due_at'       => 'datetime',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    /**
     * Scope para filtrar solo tareas activas (status = '0')
     */
    public function scopeActivas($query)
    {
        return $query->where('status', '0');
    }

    /**
     * ===================== RELACIONES =====================
     */

    // 🔹 Relación con la columna
    public function columna()
    {
        return $this->belongsTo(Columna::class, 'id_columna', 'id_columna');
    }

    // 🔹 Relación con los comentarios
    public function comentarios()
    {
        return $this->hasMany(\Conduit\Models\Comentario::class, 'id_tarea', 'id_tarea');
    }

    // 🔹 Relación con el proyecto
    public function proyecto()
    {
        return $this->belongsTo(Proyecto::class, 'id_proyecto', 'id_proyecto');
    }

    // 🔹 Relación con el creador
    public function creador()
    {
        return $this->belongsTo(User::class, 'id_creador', 'id_usuario');
    }

    // 🔹 Relación con el usuario asignado
    public function asignado()
    {
        return $this->belongsTo(User::class, 'id_asignado', 'id_usuario');
    }
}
