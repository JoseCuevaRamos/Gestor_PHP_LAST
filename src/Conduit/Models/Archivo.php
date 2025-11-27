<?php

namespace Conduit\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $id
 * @property int         $id_tarea
 * @property string      $archivo_nombre
 * @property string      $archivo_ruta
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property string      $status
 */
class Archivo extends Model
{
    protected $table = 'archivos';         // Nombre de la tabla
    protected $primaryKey = 'id';  // Clave primaria

    public $incrementing = true; // Se usará un incremento autoincremental para la clave primaria
    protected $keyType   = 'int'; // Tipo de dato de la clave primaria

    // Campos que se pueden asignar en masa
    protected $fillable = [
        'id_tarea',
        'archivo_nombre',
        'archivo_ruta',
        'status',
    ];

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    /********************
     * Relaciones 
     ********************/
    public function tarea()
    {
        return $this->belongsTo(Tarea::class, 'id_tarea', 'id_tarea');
    }
}
