<?php
namespace Conduit\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $table = 'usuarios';
    protected $primaryKey = 'id_usuario';
    public $timestamps = true;

    protected $fillable = [
        'nombre',
        'correo',
        'password_hash',
        'dni',
    ];

    // Ocultar en respuestas JSON
    protected $hidden = [
        'password_hash',
    ];
    protected $casts = [
        'status' => 'string',
    ];

    // Usar las columnas created_at y updated_at personalizadas
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    /**
     * Return Default Image Profile When User Does Not Have An Image
     *
     * @param $value
     *
     * @return string
     */
    public function getImageAttribute($value){
        return $value ?: 'https://static.productionready.io/images/smiley-cyrus.jpg';
    }
       
        
    // Un usuario tiene muchos comentarios
    public function comentarios()
    {
        return $this->hasMany(Comentario::class, 'id_usuario', 'id_usuario');
    }

    // Un usuario tiene muchos espacios
    public function espacios()
    {
        return $this->hasMany(Espacio::class, 'id_usuario', 'id_usuario');
    }

    // Un usuario puede ser creador de muchos proyectos
    public function proyectosCreados()
    {
        return $this->hasMany(Proyecto::class, 'id_usuario_creador', 'id_usuario');
    }

    // Un usuario puede ser creador de muchas tareas
    public function tareasCreadas()
    {
        return $this->hasMany(Tarea::class, 'id_creador', 'id_usuario');
    }

    // Un usuario puede ser asignado en muchas tareas
    public function tareasAsignadas()
    {
        return $this->hasMany(Tarea::class, 'id_asignado', 'id_usuario');
    }

    // Un usuario tiene muchas notificaciones
    public function notificaciones()
    {
        return $this->hasMany(Notificacion::class, 'id_usuario', 'id_usuario');
    }

    // Un usuario tiene muchos roles en espacios y proyectos
    public function usuariosRoles()
    {
        return $this->hasMany(UsuarioRol::class, 'id_usuario', 'id_usuario');
    }
}