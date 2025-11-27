<?php

namespace Conduit\Transformers;

use Conduit\Models\User;
use League\Fractal\TransformerAbstract;

class UserSearchTransformer extends TransformerAbstract
{
    
    public function transform(User $user)
    {
        return [
            'id'       => (int)$user->id_usuario,
            'nombre'   => $user->nombre,
            'correo'   => $user->correo,
        ];
    }
}