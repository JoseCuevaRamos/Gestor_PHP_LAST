<?php

namespace Conduit\Transformers;

use Conduit\Models\User;
use League\Fractal\TransformerAbstract;

class UserTransformer extends TransformerAbstract
{
    public function transform(User $user)
    {
        return [
            'id_usuario'  => (int) $user->id_usuario,    
            'email'     => $user->correo,              
            'username'  => $user->nombre,              
            'image'     => $user->image,
            'createdAt' => optional($user->created_at)->toIso8601String(),
            'updatedAt' => optional($user->updated_at)->toIso8601String(), 
            'token'     => $user->token,
        ];
    }
}