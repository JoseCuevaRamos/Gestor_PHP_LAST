<?php
namespace Conduit\Transformers;

use Conduit\Models\User;
use League\Fractal\TransformerAbstract;

class UserTempTransformer extends TransformerAbstract
{
    public function transform(User $user, $plainPassword = null)
    {
        return [
            'id_usuario' => $user->id_usuario,
            'correo'     => $user->correo,
            'password'   => $plainPassword, // Solo para usuario temporal
            'created_at' => $user->created_at
        ];
    }
}
