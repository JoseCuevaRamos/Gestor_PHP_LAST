<?php
namespace Conduit\Models;

use Illuminate\Database\Eloquent\Model;

class UsuarioRol extends Model
{
    protected $table = 'usuarios_roles';
    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'id_rol',
        'id_espacio',
        'id_proyecto',
        'status',
        'created_at',
        'updated_at'
    ];

    // Relación con usuario
    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }

    // Relación con rol
    public function rol()
    {
        return $this->belongsTo(Rol::class, 'id_rol', 'id_rol');
    }

    // Relación con espacio
    public function espacio()
    {
        return $this->belongsTo(Espacio::class, 'id_espacio', 'id');
    }

    // Relación con proyecto
    public function proyecto()
    {
        return $this->belongsTo(Proyecto::class, 'id_proyecto', 'id_proyecto');
    }
}