<?php

namespace Conduit\Controllers\Auth;

use Conduit\Models\User;
use Conduit\Transformers\UserTransformer;
use Interop\Container\ContainerInterface;
use League\Fractal\Resource\Item;
use Slim\Http\Request;
use Slim\Http\Response;
use Respect\Validation\Validator as v;

class LoginController
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
     * Return token after successful login
     *
     * @param \Slim\Http\Request  $request
     * @param \Slim\Http\Response $response
     *
     * @return \Slim\Http\Response
     */
    public function login(Request $request, Response $response)
    {
        $userParams = $request->getParam('user');

        $validation = $this->validateLoginRequest($userParams);
        if ($validation->failed()) {
            return $response->withJson([
                'error' => 'correo o password: datos incompletos o inválidos'
            ], 422);
        }

        $user = User::where('correo', $userParams['correo'])->first();
            if (!$user) {
                return $response->withJson([
                    'error' => 'correo o password: es incorrecto'
                ], 422);
            }
            if ($user->status !== '0') {
                return $response->withJson([
                    'error' => 'Acceso denegado: usuario eliminado.'
                ], 422);
        }

        // === Detectar si es temporal ===
        $esTemporal = ($user->nombre === 'Temporal' && is_null($user->dni));

        if ($esTemporal) {
            $data = $this->fractal->createData(new Item($user, new UserTransformer()))->toArray();
            $data['user']['esTemporal'] = true;

            return $response->withJson([
                'message' => 'Usuario temporal detectado. Debe completar su registro.',
                'user' => [
                    'id_usuario' => $user->id_usuario,
                    'correo' => $user->correo,
                    'esTemporal' => true
                ]
            ], 200);
        }

        //usuario normal

        // IMPORTANTE: en Auth::attempt asegúrate de que se busque por 'correo'
        if ($user = $this->auth->attempt($userParams['correo'], $userParams['password'])) {
            // Generar token y preparar datos de salida
            $user->token = $this->auth->generateToken($user);
            $data        = $this->fractal
                                ->createData(new Item($user, new UserTransformer()))
                                ->toArray();

            return $response->withJson(['user' => $data]);
        };

         return $response->withJson([
                'error' => 'correo o password: es incorrecto'
            ], 422);
    }

    public function actualizarTemporal(Request $request, Response $response)
    {
        $userParams = $request->getParam('user');

        $correo = $userParams['correo'] ?? null;
        $nombre = $userParams['nombre'] ?? null;
        $dni = $userParams['dni'] ?? null;
        $password = $userParams['password'] ?? null;

        if (!$correo || !$nombre || !$dni || !$password) {
            return $response->withJson(['error' => 'Debe ingresar correo, nombre, DNI y nueva contraseña.'], 400);
        }

        // Buscar usuario por correo
        $user = User::where('correo', $correo)->first();
        if (!$user) {
            return $response->withJson(['error' => 'Usuario no encontrado.'], 404);
        }

        // Verificar que sea temporal
        if (!($user->nombre === 'Temporal' && is_null($user->dni))) {
            return $response->withJson(['error' => 'El usuario no es temporal.'], 400);
        }

        // === Validar nombre ===
        if (preg_match('/\s/', $nombre) || strlen($nombre) < 1 || strlen($nombre) > 25) {
            return $response->withJson([
                'errors' => ['nombre' => ['El nombre no debe contener espacios y debe tener entre 1 y 25 caracteres.']]
            ], 422);
        }

        // === Validar DNI ===
        if (!preg_match('/^\d{8}$/', $dni)) {
            return $response->withJson([
                'errors' => ['dni' => ['El DNI debe tener exactamente 8 dígitos.']]
            ], 422);
        }

        $existeDni = User::where('dni', $dni)
            ->where('id_usuario', '!=', $user->id_usuario)
            ->where('status', '0')
            ->exists();

        if ($existeDni) {
            return $response->withJson([
                'errors' => ['dni' => ['El DNI ingresado ya está registrado.']]
            ], 422);
        }

        // === Validar contraseña ===
        if (strlen($password) != 6 ||
            !preg_match('/[A-Z]/', $password) ||
            !preg_match('/[a-z]/', $password) ||
            !preg_match('/[0-9]/', $password) ||
            !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)
        ) {
            return $response->withJson([
                'errors' => ['password' => ['La contraseña debe tener exactamente 6 caracteres e incluir mayúscula, minúscula, número y símbolo.']]
            ], 422);
        }

        // === Actualizar usuario ===
        $user->update([
            'nombre' => trim($nombre),
            'dni' => $dni,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);

        // === Generar token y devolver ===
        $user->token = $this->auth->generateToken($user);
        $data = $this->fractal->createData(new Item($user, new UserTransformer()))->toArray();

        return $response->withJson([
            'message' => 'Usuario temporal actualizado y autenticado correctamente.',
            'user' => $data,
        ], 200);
    }

    /**
     * @param array
     *
     * @return \Conduit\Validation\Validator
     */
    protected function validateLoginRequest($values)
    {
        return $this->validator->validateArray($values,
            [
                'correo'    => v::noWhitespace()->notEmpty(),
                'password' => v::noWhitespace()->notEmpty(),
            ]);
    }
}