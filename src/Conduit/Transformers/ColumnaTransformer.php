<?php

namespace Conduit\Transformers;

use Conduit\Models\Columna;
use League\Fractal\TransformerAbstract;

class ColumnaTransformer extends TransformerAbstract
{
    /**
     * Include resources without needing it to be requested.
     *
     * Si en el futuro quisieras incluir datos relacionados (por ejemplo, tablero),
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
     * ColumnaTransformer constructor.
     *
     * @param int|null $requestUserId  (opcional, por si luego se usa autenticación)
     */
    public function __construct($requestUserId = null)
    {
        $this->requestUserId = $requestUserId;
    }

    /**
     * Transform the Columna model into an array for the API response.
     *
     * @param Columna $columna
     * @return array
     */
    public function transform(Columna $columna)
    {
        return [
            'id_columna'   => $columna->id_columna,
            'id_proyecto'  => $columna->id_proyecto,
            'nombre'       => $columna->nombre,
            'color'        => $columna->color,
            'posicion'     => $columna->posicion,
            'tipo_columna' => $columna->tipo_columna, 
            'status_fijas' => $columna->status_fijas, 
            'status'       => $columna->status,
            'created_at'   => $columna->created_at ? $columna->created_at->toIso8601String() : null,
            'updated_at'   => $columna->updated_at ? $columna->updated_at->toIso8601String() : null,
        ];
    }

    /**
     * Optionally include the related Proyecto (if needed in the future).
     *
     * @param Columna $columna
     * @return \League\Fractal\Resource\Item|null
     */
    public function includeProyecto(Columna $columna)
    {
        // Si tienes una relación con Proyecto, puedes incluirla de esta manera.
        if ($columna->proyecto) {
            return $this->item($columna->proyecto, new ProyectoTransformer());
        }

        return null;
    }
}