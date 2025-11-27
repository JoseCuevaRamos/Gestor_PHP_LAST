<?php
namespace Conduit\Models;

use Illuminate\Database\Eloquent\Model;

class Rol extends Model
{
    protected $table = 'roles';
    protected $primaryKey = 'id_rol';
    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'descripcion',
        'status'
    ];

    // Relación inversa con usuarios_roles
    public function usuariosRoles()
    {
        return $this->hasMany(UsuarioRol::class, 'id_rol', 'id_rol');
    }
}