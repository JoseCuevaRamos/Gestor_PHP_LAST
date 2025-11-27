<?php

namespace Conduit\Transformers;

use Conduit\Models\Archivo;
use League\Fractal\TransformerAbstract;

class ArchivoTransformer extends TransformerAbstract
{
    /**
     * Include resources without needing it to be requested.
     *
     * Si en el futuro quisieras incluir datos relacionados (por ejemplo, tarea),
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
     * ArchivoTransformer constructor.
     *
     * @param int|null $requestUserId  (opcional, por si luego se usa autenticación)
     */
    public function __construct($requestUserId = null)
    {
        $this->requestUserId = $requestUserId;
    }

    /**
     * Transform the Archivo model into an array for the API response.
     *
     * @param Archivo $archivo
     * @return array
     */
    public function transform(Archivo $archivo)
    {
        return [
            'id'    => $archivo->id,
            'id_tarea'      => $archivo->id_tarea,
            'archivo_nombre'=> $archivo->archivo_nombre,
            'archivo_ruta'  => $archivo->archivo_ruta,
            'created_at'    => $archivo->created_at ? $archivo->created_at->toIso8601String() : null,
            'updated_at'    => $archivo->updated_at ? $archivo->updated_at->toIso8601String() : null,
        ];
    }

    /**
     * Optionally include the related Tarea (if needed in the future).
     *
     * @param Archivo $archivo
     * @return \League\Fractal\Resource\Item|null
     */
    public function includeTarea(Archivo $archivo)
    {
        // Si tienes una relación con Tarea, puedes incluirla de esta manera.
        if ($archivo->tarea) {
            return $this->item($archivo->tarea, new TareaTransformer());
        }

        return null;
    }
}
