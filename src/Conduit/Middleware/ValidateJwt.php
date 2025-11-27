<?php

namespace Conduit\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class ValidateJwt
{
    private $secret;

    public function __construct($secret)
    {
        $this->secret = $secret;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $next)
    {
        $authHeader = $request->getHeaderLine('Authorization');
        
        if (!$authHeader || !preg_match('/Token\s+(.*)$/i', $authHeader, $matches)) {
            $response->getBody()->write(json_encode(['error' => 'Token JWT no proporcionado.']));
            return $response
                ->withStatus(401)
                ->withHeader('Content-Type', 'application/json');
        }

        $token = $matches[1];

        try {
            $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
            $jwtArray = (array) $decoded;
            $request = $request->withAttribute('jwt', $jwtArray);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Token JWT inválido: ' . $e->getMessage()]));
            return $response
                ->withStatus(401)
                ->withHeader('Content-Type', 'application/json');
        }

        return $next($request, $response);
    }
}