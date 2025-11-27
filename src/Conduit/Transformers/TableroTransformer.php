<?php

namespace Conduit\Transformers;

use Conduit\Models\Tablero;
use League\Fractal\TransformerAbstract;

class TableroTransformer extends TransformerAbstract
{
    /**
     * Include resources without needing it to be requested.
     *
     * Si en el futuro quisieras incluir datos relacionados (por ejemplo, proyecto o columnas),
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
     * TableroTransformer constructor.
     *
     * @param int|null $requestUserId  (opcional, por si luego se usa autenticación)
     */
    public function __construct($requestUserId = null)
    {
        $this->requestUserId = $requestUserId;
    }

    /**
     * Transform the Tablero model into an array for the API response.
     *
     * @param Tablero $tablero
     * @return array
     */
    public function transform(Tablero $tablero)
    {
        return [
            'id_tablero'    => $tablero->id_tablero,
            'id_proyecto'   => $tablero->id_proyecto,
            'nombre'        => $tablero->nombre,
            'created_at'    => $tablero->created_at?->toIso8601String(),
            'updated_at'    => $tablero->updated_at?->toIso8601String(),
        ];
    }
}
