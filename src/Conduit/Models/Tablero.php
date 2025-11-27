<?php

namespace Conduit\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $id_proyecto
 * @property string      $nombre
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Tablero extends Model
{
    protected $table = 'tableros';         // Nombre de la tabla
    protected $primaryKey = 'id_tablero';  // Clave primaria

    public $incrementing = true; // Se usará un incremento autoincremental para la clave primaria
    protected $keyType   = 'int'; // Tipo de dato de la clave primaria

    // Campos que se pueden asignar en masa
    protected $fillable = [
        'id_proyecto',
        'nombre',
    ];

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    /********************
     * Relaciones 
     ********************/

    // Relación con el modelo Proyecto
    public function proyecto()
    {
        return $this->belongsTo(Proyecto::class, 'id_proyecto', 'id_proyecto');
    }
}
