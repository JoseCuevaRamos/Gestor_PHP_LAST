<?php
namespace Conduit\Transformers;

use Conduit\Models\Comentario;

class ComentarioTransformer
{
    public static function transform(Comentario $comentario)
    {
        return [
            'id_comentario' => $comentario->id_comentario,
            'id_tarea' => $comentario->id_tarea,
            'id_usuario' => $comentario->id_usuario,
            'contenido' => $comentario->contenido,
            'status' => $comentario->status,
            'created_at' => $comentario->created_at,
            'updated_at' => $comentario->updated_at,
            'minutos_desde_creacion' => $comentario->minutos_desde_creacion ?? null,

        ];
    }
    
}
