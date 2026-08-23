<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use RuntimeException;

final class Router
{
    /**
     * @var array<string, array<int, array{
     *     uri: string,
     *     pattern: string,
     *     parameters: array<int, string>,
     *     handler: callable|array,
     *     middleware: array
     * }>>
     */
    private array $routes = [];

    private string $basePath;

    public function __construct(
        string $basePath = ''
    ) {
        $this->basePath = '/'
            . trim(
                $basePath,
                '/'
            );

        if ($this->basePath === '/') {
            $this->basePath = '';
        }
    }

    public function get(
        string $uri,
        callable|array $handler,
        array $middleware = []
    ): void {
        $this->addRoute(
            'GET',
            $uri,
            $handler,
            $middleware
        );
    }

    public function post(
        string $uri,
        callable|array $handler,
        array $middleware = []
    ): void {
        $this->addRoute(
            'POST',
            $uri,
            $handler,
            $middleware
        );
    }

    private function addRoute(
        string $method,
        string $uri,
        callable|array $handler,
        array $middleware
    ): void {
        $uri = $this->normalizeUri(
            $uri
        );

        [$pattern, $parameters] =
            $this->compileRoute(
                $uri
            );

        $this->routes[$method][] = [
            'uri' => $uri,
            'pattern' => $pattern,
            'parameters' => $parameters,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(
        string $requestMethod,
        string $requestUri
    ): void {
        $method = strtoupper(
            $requestMethod
        );

        $path = parse_url(
            $requestUri,
            PHP_URL_PATH
        );

        if (!is_string($path)) {
            $path = '/';
        }

        if (
            $this->basePath !== ''
            && str_starts_with(
                $path,
                $this->basePath
            )
        ) {
            $path = substr(
                $path,
                strlen($this->basePath)
            );
        }

        $path = $this->normalizeUri(
            $path
        );

        $route = null;
        $routeParameters = [];

        foreach (
            $this->routes[$method] ?? []
            as $candidate
        ) {
            $matches = [];

            if (
                preg_match(
                    $candidate['pattern'],
                    $path,
                    $matches
                ) !== 1
            ) {
                continue;
            }

            $route = $candidate;

            foreach (
                $candidate['parameters']
                as $parameter
            ) {
                $routeParameters[$parameter] =
                    isset($matches[$parameter])
                        ? urldecode(
                            (string) $matches[$parameter]
                        )
                        : null;
            }

            break;
        }

        if ($route === null) {
            throw new HttpException(
                404,
                'A página solicitada não foi encontrada.'
            );
        }

        $handler = $route['handler'];
        $middleware = $route['middleware'];

        $pipeline = function () use (
            $handler,
            $routeParameters
        ): void {
            $this->executeHandler(
                $handler,
                $routeParameters
            );
        };

        foreach (
            array_reverse($middleware)
            as $middlewareItem
        ) {
            $next = $pipeline;

            $pipeline = function () use (
                $middlewareItem,
                $next
            ): void {
                $this->executeMiddleware(
                    $middlewareItem,
                    $next
                );
            };
        }

        $pipeline();
    }

    private function executeMiddleware(
        mixed $middleware,
        callable $next
    ): void {
        if (is_object($middleware)) {
            if (
                !method_exists(
                    $middleware,
                    'handle'
                )
            ) {
                throw new RuntimeException(
                    'Middleware inválido.'
                );
            }

            $middleware->handle(
                $next
            );

            return;
        }

        if (
            is_string($middleware)
            && class_exists($middleware)
        ) {
            $instance = new $middleware();

            if (
                !method_exists(
                    $instance,
                    'handle'
                )
            ) {
                throw new RuntimeException(
                    'Middleware inválido.'
                );
            }

            $instance->handle(
                $next
            );

            return;
        }

        throw new RuntimeException(
            'Middleware da rota não pôde ser carregado.'
        );
    }

    private function executeHandler(
        callable|array $handler,
        array $parameters = []
    ): void {
        if ($handler instanceof Closure) {
            $handler(...array_values($parameters));
            return;
        }

        if (
            !is_array($handler)
            || count($handler) !== 2
        ) {
            throw new RuntimeException(
                'Handler de rota inválido.'
            );
        }

        [$controllerClass, $method] = $handler;

        if (
            !is_string($controllerClass)
            || !class_exists($controllerClass)
        ) {
            throw new RuntimeException(
                'Controller da rota não encontrado.'
            );
        }

        $controller = new $controllerClass();

        if (
            !is_string($method)
            || !method_exists(
                $controller,
                $method
            )
        ) {
            throw new RuntimeException(
                'Método do controller não encontrado.'
            );
        }

        $controller->{$method}(
            ...array_values(
                $parameters
            )
        );
    }

    /**
     * Transforma:
     *
     * /produtos/{id}/editar
     *
     * em uma expressão regular segura.
     */
    private function compileRoute(
        string $uri
    ): array {
        $parameters = [];

        $segments = explode(
            '/',
            trim($uri, '/')
        );

        if ($uri === '/') {
            return [
                '#^/$#',
                [],
            ];
        }

        $patternParts = [];

        foreach ($segments as $segment) {
            if (
                preg_match(
                    '/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/',
                    $segment,
                    $matches
                ) === 1
            ) {
                $parameter = $matches[1];

                $parameters[] = $parameter;

                $patternParts[] =
                    '(?P<'
                    . $parameter
                    . '>[^/]+)';

                continue;
            }

            $patternParts[] =
                preg_quote(
                    $segment,
                    '#'
                );
        }

        $pattern = '#^/'
            . implode(
                '/',
                $patternParts
            )
            . '$#';

        return [
            $pattern,
            $parameters,
        ];
    }

    private function normalizeUri(
        string $uri
    ): string {
        $uri = '/'
            . trim(
                $uri,
                '/'
            );

        return $uri === '//'
            ? '/'
            : $uri;
    }
}