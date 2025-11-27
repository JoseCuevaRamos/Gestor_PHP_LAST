<?php

namespace Conduit\Transformers;

use Conduit\Models\Espacio;
use League\Fractal\TransformerAbstract;

class EspacioTransformer extends TransformerAbstract
{
    /**
     * Include resources without needing it to be requested.
     *
     * Si en el futuro quisieras incluir datos relacionados (por ejemplo, usuario),
     * podrías declararlo aquí, por ahora lo dejamos vacío.
     *
     * @var array
     */
    protected $defaultIncludes = [];

    /**
     * @var integer|null
     */
    protected $requestUserId;

    /**
     * EspacioTransformer constructor.
     *
     * @param int|null $requestUserId  (opcional, por si luego se usa autenticación)
     */
    public function __construct($requestUserId = null)
    {
        $this->requestUserId = $requestUserId;
    }

    /**
     * Transform a single Espacio model into an array
     *
     * @param \Conduit\Models\Espacio $espacio
     * @return array
     */
    public function transform(Espacio $espacio)
    {
        return [
            'id'          => $espacio->id,
            'nombre'      => $espacio->nombre,
            'descripcion' => $espacio->descripcion,
            'id_usuario' => $espacio->id_usuario,
            'createdAt'   => $espacio->created_at
                                ? $espacio->created_at->toIso8601String()
                                : null,
            'updatedAt'   => $espacio->updated_at
                                ? $espacio->updated_at->toIso8601String()
                                : null,
            
        ];
    }
}
