<?php

namespace Conduit\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property integer                 id
 * @property string                  nombre
 * @property string                  descripcion
 * @property integer                 id_usuario
 * @property \Carbon\Carbon          created_at
 * @property \Carbon\Carbon          update_at
 */

class Espacio extends Model
{
    protected $table = 'espacios';
    // Clave primaria
    protected $primaryKey = 'id';

    // Si es autoincremental y entero 
    public $incrementing = true;
    protected $keyType = 'int';

    /**
     * Columnas que se pueden asignar en masa.
     *
     * @var array
     */
    protected $fillable = [
        'nombre',
        'descripcion',
        'id_usuario',
        'status',
    ];

    protected $casts = [
        'status' => 'string',
    ];
    // Columnas de timestamps personalizadas
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    /********************
     *  Relaciones
     ********************/

    /**
     * Un espacio pertenece a un usuario
     */
    //public function usuario()
    //{
      //  return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    //}
}
