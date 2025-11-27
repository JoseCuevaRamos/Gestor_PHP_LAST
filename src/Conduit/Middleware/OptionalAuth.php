<?php

namespace Conduit\Middleware;

use Slim\Container;
use Slim\DeferredCallable;

class OptionalAuth
{
    /**
     * @var \Slim\Container
     */
    private $container;

    /**
     * OptionalAuth constructor.
     *
     * @param \Slim\Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * OptionalAuth middleware invokable class to verify JWT token when present in Request
     *
     * @param  \Psr\Http\Message\ServerRequestInterface $request  PSR7 request
     * @param  \Psr\Http\Message\ResponseInterface      $response PSR7 response
     * @param  callable                                 $next     Next middleware
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function __invoke($request, $response, $next)
    {
        // ⭐ CORREGIDO: Verificar header correcto
        if ($request->hasHeader('Authorization')) {
            $callable = new DeferredCallable($this->container->get('jwt'), $this->container);
            return call_user_func($callable, $request, $response, $next);
        }

        return $next($request, $response);
    }
}