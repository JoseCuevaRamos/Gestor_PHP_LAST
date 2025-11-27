<?php
namespace Conduit\Models;

use Illuminate\Database\Eloquent\Model;
use Conduit\Models\User;
use Conduit\Models\Tarea;

class Comentario extends Model
{
    protected $table = 'comentarios';
    protected $primaryKey = 'id_comentario';
    public $timestamps = true; 

    protected $fillable = [
        'id_tarea',
        'id_usuario',
        'contenido',
        'status'
    ];

    /**
     * ===================== RELACIONES =====================
     */

    // 🔹 Cada comentario pertenece a un usuario (autor)
    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }

    // 🔹 Cada comentario pertenece a una tarea
    public function tarea()
    {
        return $this->belongsTo(Tarea::class, 'id_tarea', 'id_tarea');
    }
}
