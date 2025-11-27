<?php

namespace Conduit\Models;

use Illuminate\Database\Eloquent\Model;

class HistorialMovimiento extends Model
{
    protected $table = 'historial_movimientos';
    protected $primaryKey = 'id';
    public $timestamps = false; 
    
    protected $fillable = [
        'id_tarea',
        'id_columna_anterior',  
        'id_columna_nueva',     
        'id_usuario',           
        'timestamp',
    ];

    protected $casts = [
        'timestamp' => 'datetime'
    ];

   
    public function tarea()
    {
        return $this->belongsTo(Tarea::class, 'id_tarea', 'id_tarea');
    }

    public function columnaAnterior()
    {
        return $this->belongsTo(Columna::class, 'id_columna_anterior', 'id_columna');
    }

    public function columnaNueva()
    {
        return $this->belongsTo(Columna::class, 'id_columna_nueva', 'id_columna');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }
}