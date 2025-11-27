<?php
namespace Conduit\Transformers;

use Conduit\Models\UsuarioRol;

class UsuarioRolTransformer
{
    
    public static function transform(UsuarioRol $asignacion): array
    {
        return [
            'id'          => (int) $asignacion->id,
            'id_usuario'  => (int) $asignacion->id_usuario,
            'id_rol'      => (int) $asignacion->id_rol,
            'id_proyecto' => (int) $asignacion->id_proyecto,
            'id_espacio'  => (int) $asignacion->id_espacio,
            'status'      => $asignacion->status,
        ];
    }
}