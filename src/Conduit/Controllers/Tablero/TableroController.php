<?php

namespace Conduit\Controllers\Tablero;

use Conduit\Models\Tablero;
use Conduit\Transformers\TableroTransformer;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use Slim\Http\Request;
use Slim\Http\Response;
use Respect\Validation\Validator as v;

class TableroController
{
    protected $validator;
    protected $db;
    protected $fractal;

    public function __construct(\Slim\Container $container)
    {
        $this->fractal   = $container->get('fractal');
        $this->validator = $container->get('validator');
        $this->db        = $container->get('db');
    }

    /**
     * Listar todos los tableros
     */
    public function index(Request $request, Response $response, array $args)
    {
        $builder = Tablero::query()->latest('created_at');

        // Aplicar limitación y desplazamiento (paginación) si se proporciona
        if ($limit = $request->getParam('limit')) {
            $builder->limit($limit);
        }
        if ($offset = $request->getParam('offset')) {
            $builder->offset($offset);
        }

        $count    = $builder->count();  // Contar el número total de tableros
        $tableros = $builder->get();    // Obtener los tableros

        // Transformar la colección de tableros
        $data = $this->fractal
            ->createData(new Collection($tableros, new TableroTransformer()))
            ->toArray();

        return $response->withJson([
            'tableros'      => $data['data'],
            'tablerosCount' => $count,
        ]);
    }

    /**
     * Mostrar un tablero por ID
     */
    public function show(Request $request, Response $response, array $args)
    {
        // Buscar el tablero por su ID
        $tablero = Tablero::findOrFail($args['id']); 

        // Transformar el tablero individual
        $data = $this->fractal
            ->createData(new Item($tablero, new TableroTransformer()))
            ->toArray();

        return $response->withJson(['tablero' => $data]);
    }

    /**
     * Crear un nuevo tablero
     */
    public function store(Request $request, Response $response)
    {
        // Validar los datos de entrada para crear el tablero
        $this->validator->validateArray(
            $data = $request->getParam('tablero'),
            [
                'id_proyecto' => v::notEmpty()->intVal(),
                'nombre'      => v::notEmpty(),
            ]
        );

        if ($this->validator->failed()) {
            return $response->withJson(['errors' => $this->validator->getErrors()], 422);
        }

        // Crear el tablero
        $tablero = Tablero::create($data);

        // Transformar el tablero recién creado
        $data = $this->fractal
            ->createData(new Item($tablero, new TableroTransformer()))
            ->toArray();

        return $response->withJson(['tablero' => $data], 201);
    }

    /**
     * Actualizar un tablero
     */
    public function update(Request $request, Response $response, array $args)
    {
        // Buscar el tablero por ID
        $tablero = Tablero::findOrFail($args['id']);
        $params   = $request->getParam('tablero', []);

        // Actualizar los datos del tablero
        $tablero->update([
            'id_proyecto' => $params['id_proyecto'] ?? $tablero->id_proyecto,
            'nombre'      => $params['nombre'] ?? $tablero->nombre,
        ]);

        // Transformar el tablero actualizado
        $data = $this->fractal
            ->createData(new Item($tablero, new TableroTransformer()))
            ->toArray();

        return $response->withJson(['tablero' => $data]);
    }

    /**
     * Eliminar un tablero
     */
    public function destroy(Request $request, Response $response, array $args)
    {
        // Buscar el tablero por ID
        $tablero = Tablero::findOrFail($args['id']);
        
        // Eliminar el tablero
        $tablero->delete();

        return $response->withJson([], 200);  // Respuesta exitosa sin contenido
    }
}
