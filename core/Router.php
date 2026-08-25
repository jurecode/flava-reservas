<?php
/**
 * Ruta: /core/Router.php
 * Router del front controller único (/public/index.php).
 * URLs limpias: /reservar, /reserva/FLV-260824-A7C2, /admin/reservas ...
 */

namespace Core;

use Core\Exceptions\HttpException;

final class Router
{
    /** @var array<int,array{method:string,pattern:string,regex:string,handler:mixed,middleware:array,name:?string}> */
    private array $routes = [];

    /** @var array{prefix:string,middleware:array,namespace:string} */
    private array $group = ['prefix' => '', 'middleware' => [], 'namespace' => ''];

    private array $named = [];

    /** Middleware que se ejecuta en TODAS las rutas, antes que el específico. */
    private array $globalMiddleware = [];

    /**
     * Middleware aplicado a todas las rutas (por ejemplo, el del instalador).
     * @param array<int,string> $middleware
     */
    public function globalMiddleware(array $middleware): self
    {
        $this->globalMiddleware = $middleware;

        return $this;
    }

    public function get(string $uri, mixed $handler): self    { return $this->add('GET', $uri, $handler); }
    public function post(string $uri, mixed $handler): self   { return $this->add('POST', $uri, $handler); }
    public function put(string $uri, mixed $handler): self    { return $this->add('PUT', $uri, $handler); }
    public function patch(string $uri, mixed $handler): self  { return $this->add('PATCH', $uri, $handler); }
    public function delete(string $uri, mixed $handler): self { return $this->add('DELETE', $uri, $handler); }

    public function any(array $methods, string $uri, mixed $handler): self
    {
        foreach ($methods as $method) {
            $this->add(strtoupper($method), $uri, $handler);
        }

        return $this;
    }

    /**
     * Agrupa rutas bajo un prefijo, middleware y/o sub-namespace de controladores.
     * $options: ['prefix' => 'admin', 'middleware' => ['auth','role:ADMIN'], 'namespace' => 'Admin']
     */
    public function group(array $options, callable $callback): void
    {
        $previous = $this->group;

        $this->group = [
            'prefix'     => trim($previous['prefix'] . '/' . trim($options['prefix'] ?? '', '/'), '/'),
            'middleware' => array_merge($previous['middleware'], (array) ($options['middleware'] ?? [])),
            'namespace'  => trim($previous['namespace'] . '\\' . trim($options['namespace'] ?? '', '\\'), '\\'),
        ];

        $callback($this);

        $this->group = $previous;
    }

    private function add(string $method, string $uri, mixed $handler): self
    {
        $uri     = '/' . trim($this->group['prefix'] . '/' . trim($uri, '/'), '/');
        $pattern = $uri === '//' ? '/' : $uri;

        if (is_string($handler) && $this->group['namespace'] !== '') {
            $handler = $this->group['namespace'] . '\\' . $handler;
        }

        $this->routes[] = [
            'method'     => $method,
            'pattern'    => $pattern,
            'regex'      => $this->compile($pattern),
            'handler'    => $handler,
            'middleware' => $this->group['middleware'],
            'name'       => null,
        ];

        return $this;
    }

    /** Asigna un nombre a la última ruta registrada. */
    public function name(string $name): self
    {
        $index = array_key_last($this->routes);

        if ($index !== null) {
            $this->routes[$index]['name'] = $name;
            $this->named[$name]           = $this->routes[$index]['pattern'];
        }

        return $this;
    }

    public function route(string $name, array $params = []): string
    {
        $pattern = $this->named[$name] ?? '/';

        foreach ($params as $key => $value) {
            $pattern = str_replace(['{' . $key . '}', '{' . $key . '?}'], rawurlencode((string) $value), $pattern);
        }

        return url(preg_replace('#\{[^}]+\}#', '', $pattern) ?? '/');
    }

    /** Convierte /reserva/{code} en una expresión regular con grupos nombrados. */
    private function compile(string $pattern): string
    {
        $regex = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)(\?)?\}#',
            static function (array $m): string {
                $name     = $m[1];
                $optional = ($m[2] ?? '') === '?';
                $segment  = '(?P<' . $name . '>[^/]+)';

                return $optional ? '(?:/' . $segment . ')?' : '/' . $segment;
            },
            str_replace('/{', '{', $pattern)
        );

        return '#^' . ($regex === '/' ? '/' : rtrim((string) $regex, '/')) . '/?$#u';
    }

    /**
     * Resuelve la solicitud: encuentra la ruta, ejecuta middleware y controlador.
     */
    public function dispatch(Request $request): Response
    {
        $path        = $request->path();
        $method      = $request->method();
        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            $pathMatched = true;

            if ($route['method'] !== $method) {
                continue;
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (!is_int($key)) {
                    $params[$key] = $value;
                }
            }

            $request->setRouteParams($params);

            return $this->runPipeline($route, $request, $params);
        }

        // La URI existe pero con otro verbo => 405; si no existe => 404.
        throw new HttpException($pathMatched ? 405 : 404);
    }

    /** Ejecuta la cadena de middleware y finalmente el handler. */
    private function runPipeline(array $route, Request $request, array $params): Response
    {
        $handler = fn (Request $req): Response => $this->callHandler($route['handler'], $req, $params);

        $chain = array_merge($this->globalMiddleware, $route['middleware']);

        foreach (array_reverse($chain) as $definition) {
            $next    = $handler;
            $handler = function (Request $req) use ($definition, $next): Response {
                [$name, $argument] = array_pad(explode(':', $definition, 2), 2, null);
                $class = 'App\\Middleware\\' . str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $name))) . 'Middleware';

                if (!class_exists($class)) {
                    throw new \RuntimeException("Middleware no encontrado: {$class}");
                }

                return (new $class())->handle($req, $next, $argument);
            };
        }

        return $handler($request);
    }

    private function callHandler(mixed $handler, Request $request, array $params): Response
    {
        if (is_callable($handler)) {
            $result = $handler($request, ...array_values($params));

            return $this->toResponse($result);
        }

        [$controller, $action] = array_pad(explode('@', (string) $handler, 2), 2, 'index');
        $class = str_starts_with($controller, 'App\\') ? $controller : 'App\\Controllers\\' . $controller;

        if (!class_exists($class)) {
            throw new \RuntimeException("Controlador no encontrado: {$class}");
        }

        $instance = new $class($request);

        if (!method_exists($instance, $action)) {
            throw new \RuntimeException("Acción no encontrada: {$class}@{$action}");
        }

        return $this->toResponse($instance->{$action}(...array_values($params)));
    }

    private function toResponse(mixed $result): Response
    {
        return match (true) {
            $result instanceof Response => $result,
            is_array($result)           => Response::json($result),
            is_string($result)          => Response::make($result),
            $result === null            => Response::noContent(),
            default                     => Response::make((string) $result),
        };
    }

    /** @return array<int,array> Listado para debug del SUPER_ADMIN. */
    public function routes(): array
    {
        return array_map(
            static fn (array $r): array => [
                'method'     => $r['method'],
                'uri'        => $r['pattern'],
                'handler'    => is_string($r['handler']) ? $r['handler'] : 'Closure',
                'middleware' => implode(', ', $r['middleware']),
            ],
            $this->routes
        );
    }
}
