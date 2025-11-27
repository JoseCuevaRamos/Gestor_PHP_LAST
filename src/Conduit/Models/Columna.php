<?php

namespace Conduit\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $id_columna
 * @property int         $id_proyecto
 * @property string      $nombre
 * @property string      $color
 * @property int         $posicion
 * @property string      $tipo_columna
 * @property string      $status_fijas  // NUEVO CAMPO
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property string      $status
 */
class Columna extends Model
{
    protected $table = 'columnas';         // Nombre de la tabla
    protected $primaryKey = 'id_columna';  // Clave primaria

    public $incrementing = true; // Se usará un incremento autoincremental para la clave primaria
    protected $keyType = 'int'; // Tipo de dato de la clave primaria

    // Constantes para tipo_columna
    const TIPO_FIJA = 'fija';
    const TIPO_NORMAL = 'normal';

    // CONSTANTES NUEVAS para status_fijas
    const STATUS_FIJA_PROGRESO = '1';    // Columna fija de progreso  
    const STATUS_FIJA_FINALIZADO = '2';  // Columna fija de finalizado

    // Campos que se pueden asignar en masa
    protected $fillable = [
        'id_proyecto',
        'nombre',
        'color',
        'posicion',
        'status',
        'tipo_columna', 
        'status_fijas',  // NUEVO CAMPO
    ];

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    /********************
     * Relaciones 
     ********************/

    /**
     * Relación con Proyecto
     * Una columna pertenece a un proyecto.
     */
    public function proyecto()
    {
        return $this->belongsTo(Proyecto::class, 'id_proyecto', 'id_proyecto');
    }

    /**
     * Relación con Tarea
     * Una columna puede tener muchas tareas asociadas.
     */
    public function tareas()
    {
        return $this->hasMany(Tarea::class, 'id_columna', 'id_columna');
    }

    /********************
     * Métodos de utilidad NUEVOS
     ********************/

    /**
     * Verificar si la columna es fija de progreso
     */
    public function esFijaProgreso()
    {
        return $this->tipo_columna === self::TIPO_FIJA && $this->status_fijas === self::STATUS_FIJA_PROGRESO;
    }

    /**
     * Verificar si la columna es fija de finalizado
     */
    public function esFijaFinalizado()
    {
        return $this->tipo_columna === self::TIPO_FIJA && $this->status_fijas === self::STATUS_FIJA_FINALIZADO;
    }

    /**
     * Verificar si la columna es normal
     */
    public function esNormal()
    {
        return $this->tipo_columna === self::TIPO_NORMAL;
    }
}