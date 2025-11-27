<?php
namespace Conduit\Controllers\User;

use Conduit\Transformers\UserTransformer;
use Conduit\Transformers\UserSearchTransformer;
use Interop\Container\ContainerInterface;
use League\Fractal\Resource\Item;
use League\Fractal\Resource\Collection;
use Conduit\Models\User;
use Slim\Http\Request;
use Slim\Http\Response;
use Respect\Validation\Validator as v;
//use Psr\Http\Message\ServerRequestInterface as Request;
//use Psr\Http\Message\ResponseInterface as Response;

class UserController
{
    /** @var \Conduit\Services\Auth\Auth */
      protected $auth;
    
    /** @var \Conduit\Validation\Validator */
  
    protected $validator;
    /** @var \Illuminate\Database\Capsule\Manager */
    protected $db;
    /** @var \League\Fractal\Manager */
    protected $fractal;


    public function __construct(\Slim\Container $container)
    {
        $this->auth      = $container->get('auth');
        $this->fractal   = $container->get('fractal');
        $this->validator = $container->get('validator');
        $this->db        = $container->get('db');
        
    }

    private function generarPasswordSegura()
    {
        $mayus = chr(rand(65, 90));  // A-Z
        $minus = chr(rand(97, 122)); // a-z
        $num = chr(rand(48, 57));    // 0-9
        $simbolos = '!@#$%^&*';
        $simbolo = $simbolos[rand(0, strlen($simbolos) - 1)];

        // caracteres restantes hasta llegar a 6
        $restantes = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*'), 0, 2);

        $password = str_shuffle($mayus . $minus . $num . $simbolo . $restantes);

        return substr($password, 0, 6); // exactamente 6 caracteres
    }

    public function createTempUser(Request $request, Response $response, $args)
    {
        $correo = $request->getParam('correo');
        if (!$correo || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return $response->withStatus(400)->withJson(['error' => 'Correo inválido o faltante.']);
        }

        if (User::where('correo', $correo)->exists()) {
            return $response->withStatus(409)->withJson(['error' => 'El correo ya está registrado.']);
        }

        // Contraseña aleatoria segura
        $password = $this->generarPasswordSegura();

        $user = new User();
        $user->nombre = 'Temporal';
        $user->correo = $correo;
        $user->password_hash = password_hash($password, PASSWORD_DEFAULT);
        $user->save();

        $data = [
            'id_usuario' => $user->id_usuario,
            'correo' => $user->correo,
            'password' => $password,
            'created_at' => $user->created_at,
        ];

        return $response->withJson(['user' => $data], 201);
    }


    public function show(Request $request, Response $response, array $args)
    {
        $user = User::find($args['id']); // Buscar por ID

        if (!$user) {
            return $response->withJson(['error' => 'Usuario no encontrado'], 404);
        }

        $user->token =$this->auth->generateToken($user);

        $data = $this->fractal->createData(new Item($user, new UserTransformer()))->toArray();

        return $response->withJson(['user' => $data]);
    
    }

    public function search(Request $request, Response $response, $args)
    {
        $query = $request->getParam('query', '');

        if (strlen($query) < 3) {
            return $response->withStatus(400)->withJson(['error' => 'La consulta debe tener al menos 3 caracteres.']);
        }

        //$users = User::where('nombre', 'LIKE', "%$query%")
        //    ->orWhere('correo', 'LIKE', "%$query%")
        //    ->get();
        $users = User::where(function ($q) use ($query) {
            $q->where('nombre', 'LIKE', "%$query%")
              ->orWhere('correo', 'LIKE', "%$query%");
        })
        ->where('status', '0') // solo usuarios activos
        ->get();

        if ($users->isEmpty()) {
            return $response->withStatus(404)->withJson(['error' => 'No se encontraron usuarios que coincidan con la consulta.']);
        }
        $transformer = new UserSearchTransformer();
        $data = array_map([$transformer, 'transform'], $users->all());

        return $response->withJson(['users' => $data]);
    }

    public function index(Request $request, Response $response)
    {
        $users = User::where('status', '0')                
                 ->get();

        $data = $this->fractal
                     ->createData(new Collection($users, new UserTransformer()))
                     ->toArray();

        return $response->withJson(['users' => $data]);
    }

    public function searchByEmail(Request $request, Response $response)
    {
        $correo = $request->getParam('correo');

        if (!$correo || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return $response->withJson(['error' => 'Debe ingresar un correo válido.'], 400);
        }

        $user = User::where('correo', $correo)
            ->where('status', '0')
            ->first();

        if (!$user) {
            return $response->withJson(['error' => 'No se encontró ningún usuario con ese correo.'], 404);
        }

        $esTemporal = ($user->nombre === 'Temporal' && is_null($user->dni));

        return $response->withJson([
            'message' => 'Usuario encontrado.',
            'user' => [
                'correo' => $user->correo,
                'nombre' => $user->nombre,
                'dni' => $user->dni,
                'esTemporal' => $esTemporal
            ]
        ], 200);
    }

    // Paso 2: Validar DNI y correo (para usuarios registrados)
    public function validateDniCorreo(Request $request, Response $response)
    {
        $correo = $request->getParam('correo');
        $dni = $request->getParam('dni');

        // === Validar correo ===
        if (!$correo || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return $response->withJson(['error' => 'Debe ingresar un correo válido.'], 400);
        }

        // === Validar DNI ===
        if (!$dni || !preg_match('/^\d{8}$/', $dni)) {
            return $response->withJson([
                'errors' => ['dni' => ['Debe ingresar un DNI válido de 8 dígitos.']]
            ], 422);
        }

        // === Buscar usuario activo ===
        $user = User::where('correo', $correo)
            ->where('status', '0')
            ->first();

        if (!$user) {
            return $response->withJson(['error' => 'Usuario no encontrado o inactivo.'], 404);
        }

        // === Validar que no sea temporal ===
        if ($user->nombre === 'Temporal' && is_null($user->dni)) {
            return $response->withJson(['error' => 'El usuario es temporal y no puede validar DNI en este flujo.'], 403);
        }

        // === Validar que el DNI coincida ===
        if ($user->dni !== $dni) {
            return $response->withJson(['error' => 'El DNI ingresado no coincide con el registrado.'], 403);
        }

        return $response->withJson([
            'message' => 'DNI y correo validados correctamente. Puede continuar con el cambio de contraseña.',
            'user' => [
                'correo' => $user->correo,
                'nombre' => $user->nombre
            ]
        ], 200);
    }


    public function update(Request $request, Response $response)

    {
        $params = $request->getParam('user');
        $correo = $params['correo'] ?? null;
        $password = $params['password'] ?? null;

        // === Validar correo ===
        if (!$correo || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return $response->withJson(['error' => 'Debe ingresar un correo válido.'], 400);
        }

        // === Buscar usuario activo ===
        $user = User::where('correo', $correo)
            ->where('status', '0')
            ->first();

        if (!$user) {
            return $response->withJson(['error' => 'El correo ingresado no existe o está inactivo.'], 404);
        }

        // === Verificar que no sea temporal ===
        if ($user->nombre === 'Temporal' && is_null($user->dni)) {
            return $response->withJson(['error' => 'El usuario es temporal. No puede actualizar contraseña aquí.'], 403);
        }

        // === Validar nueva contraseña ===
        if (!$password) {
            return $response->withJson(['error' => 'Debe ingresar la nueva contraseña.'], 400);
        }

        if (strlen($password) != 6 ||
            !preg_match('/[A-Z]/', $password) ||
            !preg_match('/[a-z]/', $password) ||
            !preg_match('/[0-9]/', $password) ||
            !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)
        ) {
            return $response->withJson([
                'errors' => [
                    'password' => [
                        'La contraseña debe tener exactamente 6 caracteres e incluir mayúscula, minúscula, número y símbolo.'
                    ]
                ]
            ], 422);
        }

        // === Actualizar contraseña ===
        $user->update([
            'password_hash' => password_hash($password, PASSWORD_DEFAULT)
        ]);

        return $response->withJson([
            'message' => 'Contraseña actualizada correctamente.',
            'user' => [
                'correo' => $user->correo,
                'nombre' => $user->nombre,
            ]
        ], 200);
    }


    public function destroy(Request $request, Response $response, array $args)
    {
        $user = $this->db->table('usuarios')->where('id_usuario', $args['id'])->first();

        if (!$user) {
            return $response->withJson(['error' => 'Usuario no encontrado.'], 404);
        }

        // Desactivar usuario
        $this->db->table('usuarios')
                ->where('id_usuario', $args['id'])
                ->update(['status' => '1']);

        // Desactivar espacios asociados
        $espacios = $this->db->table('espacios')->where('id_usuario', $args['id'])->get();
        foreach ($espacios as $espacio) {
            $this->db->table('espacios')->where('id', $espacio->id)
                ->update(['status' => '1']);

            // Desactivar proyectos
            $proyectos = $this->db->table('proyectos')->where('id_espacio', $espacio->id)->get();
            foreach ($proyectos as $proyecto) {
                $this->db->table('proyectos')->where('id_proyecto', $proyecto->id_proyecto)
                    ->update(['status' => '1']);

                // Desactivar columnas
                $columnas = $this->db->table('columnas')->where('id_proyecto', $proyecto->id_proyecto)->get();
                foreach ($columnas as $columna) {
                    $this->db->table('columnas')->where('id_columna', $columna->id_columna)
                        ->update(['status' => '1']);

                    // Desactivar tareas
                    $this->db->table('tareas')->where('id_columna', $columna->id_columna)
                        ->update(['status' => '1']);
                }
            }
        }

        return $response->withJson(['message' => 'Usuario y sus espacios, proyectos, columnas y tareas eliminados.'], 200);
    }

    /**
     * @param array
     *
     * @return \Conduit\Validation\Validator
     */
    protected function validateUpdateRequest($values, $userId)
    {
        return $this->validator->validateArray($values, [
            'correo' => v::optional(
                v::noWhitespace()
                    ->notEmpty()
                    ->email()
                    ->existsWhenUpdate($this->db->table('usuarios'), 'correo', $userId)
            ),
            'nombre' => v::optional(
                v::noWhitespace()
                    ->notEmpty()
                    ->existsWhenUpdate($this->db->table('usuarios'), 'nombre', $userId)
            ),
            'password' => v::optional(v::noWhitespace()->notEmpty()),
        ]);
    }
}