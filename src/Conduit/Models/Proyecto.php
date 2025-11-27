<?php

namespace Conduit\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $id_proyecto
 * @property int|null    $id_equipo
 * @property string      $nombre
 * @property string|null $descripcion
 * @property int         $id_usuario_creador
 * @property int         $id_espacio
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Proyecto extends Model
{
    protected $table = 'proyectos';        // nombre de la tabla
    protected $primaryKey = 'id_proyecto'; // clave primaria

    public $incrementing = true;
    protected $keyType   = 'int';

    // Campos que se pueden asignar en masa
    protected $fillable = [
        'nombre',
        'descripcion',
        'id_usuario_creador',
        'id_espacio',
        'status', 
    ];

    protected $casts = [
        'status' => 'string',
    ];
    
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    /********************
     * Relaciones 
     ********************/
    // public function espacio() {
    //     return $this->belongsTo(Espacio::class, 'id_espacio', 'id');
    // }
}
