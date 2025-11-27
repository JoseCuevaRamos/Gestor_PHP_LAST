<?php

namespace Conduit\Controllers\Auth;

use Conduit\Models\User;
use Conduit\Transformers\UserTransformer;
use Interop\Container\ContainerInterface;
use League\Fractal\Resource\Item;
use Slim\Http\Request;
use Slim\Http\Response;
use Respect\Validation\Validator as v;

class RegisterController
{

    /** @var \Conduit\Validation\Validator */
    protected $validator;
    /** @var \Illuminate\Database\Capsule\Manager */
    protected $db;
    /** @var \League\Fractal\Manager */
    protected $fractal;
    /** @var \Conduit\Services\Auth\Auth */
    private $auth;

    /**
     * RegisterController constructor.
     *
     * @param \Interop\Container\ContainerInterface $container
     */
    public function __construct(\Slim\Container $container)
    {
        $this->auth = $container->get('auth');
        $this->validator = $container->get('validator');
        $this->db = $container->get('db');
        $this->fractal = $container->get('fractal');
    }

    /**
     * Register New Users from POST Requests to /api/users
     *
     * @param \Slim\Http\Request  $request
     * @param \Slim\Http\Response $response
     *
     * @return \Slim\Http\Response
     */
    public function register(Request $request, Response $response)
    {
        $userParams = $request->getParam('user');

        $correo = isset($userParams['correo']) ? trim($userParams['correo']) : '';
        $nombre = isset($userParams['nombre']) ? trim($userParams['nombre']) : '';
        $password = isset($userParams['password']) ? $userParams['password'] : '';
        $dni = isset($userParams['dni']) ? trim($userParams['dni']) : '';


        $validation = $this->validator->validateArray($userParams, [
            'correo'  => v::noWhitespace()->notEmpty()->email()
                        ->existsInTable($this->db->table('usuarios')->where('status', '0'), 'correo'),
            'nombre'  => v::noWhitespace()->notEmpty()->length(1, 25)
                        ->existsInTable($this->db->table('usuarios')->where('status', '0'), 'nombre'),
            'password'=> v::noWhitespace()->notEmpty()->length(6),
            'dni'=> v::noWhitespace()->notEmpty()
                        ->existsInTable($this->db->table('usuarios')->where('status', '0'), 'dni'),
        ]);

        // Verificar espacios al inicio o final en 'nombre' y retornar error directo
        $nombre = isset($userParams['nombre']) ? $userParams['nombre'] : '';
        if (preg_match('/^\s|\s$/', $nombre)) {
            return $response->withJson([
                'errors' => [
                    'nombre' => ['El nombre no debe tener espacios al inicio ni al final.']
                ]
            ], 422);
        }
        // Verificar longitud máxima de 'nombre'
        if (mb_strlen($nombre) > 25) {
            return $response->withJson(['errors' => ['nombre' => ['El nombre no debe tener más de 25 caracteres.']]], 422);
        }
        // Verificar campo DNI
        if ($dni === '') {
            return $response->withJson(['errors' => ['dni' => ['El campo DNI es obligatorio.']]], 422);
        }
        // Verificar formato de DNI
        if (!preg_match('/^\d{8}$/', $dni)) {
            return $response->withJson([
                'errors' => [
                    'dni' => ['El DNI debe contener exactamente 8 dígitos numéricos.']
                ]
            ], 422);
        }
        // Verificar unicidad de correo y DNI
        if (User::where('correo', $correo)->where('status', '0')->exists()) {
            return $response->withJson(['errors' => ['correo' => ['El correo ya está registrado.']]], 409);
        }

        if (User::where('dni', $dni)->where('status', '0')->exists()) {
            return $response->withJson(['errors' => ['dni' => ['El DNI ya está registrado.']]], 409);
        }

        //validar contraseña 
        if (!$password) {
            return $response->withJson(['errors' => ['password' => ['Debe ingresar la contraseña.']]], 400);
        }
        if (strlen($password) != 6) {
            return $response->withJson(['errors' => ['password' => ['La contraseña debe tener exactamente 6 caracteres.']]], 422);
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return $response->withJson(['errors' => ['password' => ['Debe incluir al menos una letra mayúscula.']]], 422);
        }
        if (!preg_match('/[a-z]/', $password)) {
            return $response->withJson(['errors' => ['password' => ['Debe incluir al menos una letra minúscula.']]], 422);
        }
        if (!preg_match('/[0-9]/', $password)) {
            return $response->withJson(['errors' => ['password' => ['Debe incluir al menos un número.']]], 422);
        }
        if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            return $response->withJson(['errors' => ['password' => ['Debe incluir al menos un símbolo especial.']]], 422);
        }

        if ($validation->failed()) {
            return $response->withJson(['errors' => $validation->getErrors()], 422);
        }

        $user = new User();
        $user->nombre        = $userParams['nombre'];
        $user->correo        = $userParams['correo'];
        $user->password_hash = password_hash($userParams['password'], PASSWORD_DEFAULT);
        $user->dni       = $userParams['dni'];
        $user->save();

        $user->token = $this->auth->generateToken($user);

        return $response->withJson([
            'user' => [
                'id_usuario' => $user->id_usuario,
                'nombre'     => $user->nombre,
                'correo'     => $user->correo,
                'created_at' => $user->created_at,
                'token'      => $user->token,
            ]
        ]);
    }

    /**
     * @param array
     *
     * @return \Conduit\Validation\Validator
     */
    protected function validateRegisterRequest($values)
    {
        return $this->validator->validateArray($values,
            [
                'email'    => v::noWhitespace()->notEmpty()->email()->existsInTable($this->db->table('users'), 'email'),
                'username' => v::noWhitespace()->notEmpty()->existsInTable($this->db->table('users'),
                    'username'),
                'dni' => v::noWhitespace()->notEmpty()->existsInTable($this->db->table('users'),
                    'dni'),
                'password' => v::noWhitespace()->notEmpty(),
            ]);
    }
}