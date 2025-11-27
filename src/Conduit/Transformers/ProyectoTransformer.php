<?php

namespace Conduit\Transformers;

use Conduit\Models\Proyecto;
use League\Fractal\TransformerAbstract;

class ProyectoTransformer extends TransformerAbstract
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


    public function transform(Proyecto $proyecto)
    {
        return [
            'id_proyecto'       => $proyecto->id_proyecto,
            'nombre'            => $proyecto->nombre,
            'descripcion'       => $proyecto->descripcion,
            'id_usuario_creador'=> $proyecto->id_usuario_creador,
            'id_espacio'        => $proyecto->id_espacio,
            'created_at'        => $proyecto->created_at?->toIso8601String(),
            'updated_at'        => $proyecto->updated_at?->toIso8601String(),
        ];
    }
}
