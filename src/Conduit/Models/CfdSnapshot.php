<?php

namespace Conduit\Models;

use Illuminate\Database\Eloquent\Model;

class CfdSnapshot extends Model
{
    protected $table = 'cfd_snapshots';
    protected $primaryKey = 'id';
    public $timestamps = true; 

    protected $fillable = [
        'id_proyecto',
        'fecha',
        'conteo_columnas',
    ];

    protected $casts = [
        'fecha' => 'date',
        'conteo_columnas' => 'array', 
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relación con Proyecto
    public function proyecto()
    {
        return $this->belongsTo(Proyecto::class, 'id_proyecto', 'id_proyecto');
    }

    // Scope para filtrar por proyecto
    public function scopeDelProyecto($query, $idProyecto)
    {
        return $query->where('id_proyecto', $idProyecto);
    }

    // Scope para filtrar por rango de fechas
    public function scopeEntreFechas($query, $fechaInicio, $fechaFin)
    {
        return $query->whereBetween('fecha', [$fechaInicio, $fechaFin]);
    }
}