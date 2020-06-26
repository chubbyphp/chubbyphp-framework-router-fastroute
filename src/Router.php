<?php

declare(strict_types=1);

namespace Chubbyphp\Framework\Router\FastRoute;

use Chubbyphp\Framework\Router\Exceptions\MethodNotAllowedException;
use Chubbyphp\Framework\Router\Exceptions\MissingAttributeForPathGenerationException;
use Chubbyphp\Framework\Router\Exceptions\MissingRouteByNameException;
use Chubbyphp\Framework\Router\Exceptions\NotFoundException;
use Chubbyphp\Framework\Router\Exceptions\NotMatchingValueForPathGenerationException;
use Chubbyphp\Framework\Router\RouteInterface;
use Chubbyphp\Framework\Router\RouterInterface;
use FastRoute\DataGenerator\GroupCountBased as DataGenerator;
use FastRoute\Dispatcher\GroupCountBased as Dispatcher;
use FastRoute\RouteCollector;
use FastRoute\RouteParser\Std as RouteParser;
use Psr\Http\Message\ServerRequestInterface;

final class Router implements RouterInterface
{
    /**
     * @var array<string, RouteInterface>
     */
    private $routes;

    /**
     * @var Dispatcher
     */
    private $dispatcher;

    /**
     * @var RouteParser
     */
    private $routeParser;

    /**
     * @var string
     */
    private $basePath;

    /**
     * @param array<RouteInterface> $routes
     */
    public function __construct(array $routes, ?string $cacheFile = null, string $basePath = '')
    {
        $this->routes = $this->getRoutesByName($routes);
        $this->dispatcher = $this->getDispatcher($routes, $cacheFile);
        $this->routeParser = new RouteParser();
        $this->basePath = $basePath;
    }

    public function match(ServerRequestInterface $request): RouteInterface
    {
        $method = $request->getMethod();
        $path = \rawurldecode($request->getUri()->getPath());

        $routeInfo = $this->dispatcher->dispatch($method, $path);

        if (Dispatcher::NOT_FOUND === $routeInfo[0]) {
            throw NotFoundException::create($request->getRequestTarget());
        }

        if (Dispatcher::METHOD_NOT_ALLOWED === $routeInfo[0]) {
            throw MethodNotAllowedException::create(
                $request->getRequestTarget(),
                $method,
                $routeInfo[1]
            );
        }

        /** @var RouteInterface $route */
        $route = $this->routes[$routeInfo[1]];

        return $route->withAttributes($routeInfo[2]);
    }

    /**
     * @param array<string, string> $attributes
     * @param array<string, mixed>  $queryParams
     */
    public function generateUrl(
        ServerRequestInterface $request,
        string $name,
        array $attributes = [],
        array $queryParams = []
    ): string {
        $uri = $request->getUri();
        $requestTarget = $this->generatePath($name, $attributes, $queryParams);

        return $uri->getScheme().'://'.$uri->getAuthority().$requestTarget;
    }

    /**
     * @param array<string, string> $attributes
     * @param array<string, mixed>  $queryParams
     */
    public function generatePath(string $name, array $attributes = [], array $queryParams = []): string
    {
        $route = $this->getRoute($name);

        $routePartSets = \array_reverse($this->routeParser->parse($route->getPath()));

        $routeIndex = $this->getRouteIndex($routePartSets, $attributes);

        $path = $this->generatePathFromAttributes($name, $routePartSets, $attributes, $routeIndex);

        if ([] === $queryParams) {
            return $this->basePath.$path;
        }

        return $this->basePath.$path.'?'.\http_build_query($queryParams);
    }

    /**
     * @param array<int, RouteInterface> $routes
     *
     * @return array<string, RouteInterface>
     */
    private function getRoutesByName(array $routes): array
    {
        $routesByName = [];

        foreach ($routes as $route) {
            $routesByName[$route->getName()] = $route;
        }

        return $routesByName;
    }

    /**
     * @param array<int, RouteInterface> $routes
     */
    private function getDispatcher(array $routes, ?string $cacheFile = null): Dispatcher
    {
        if (null === $cacheFile) {
            return new Dispatcher($this->getRouteCollector($routes)->getData());
        }

        if (!\file_exists($cacheFile)) {
            \file_put_contents(
                $cacheFile,
                '<?php return '.\var_export($this->getRouteCollector($routes)->getData(), true).';'
            );
        }

        return new Dispatcher(require $cacheFile);
    }

    /**
     * @param array<int, RouteInterface> $routes
     */
    private function getRouteCollector(array $routes): RouteCollector
    {
        $routeCollector = new RouteCollector(new RouteParser(), new DataGenerator());

        foreach ($routes as $route) {
            $routeCollector->addRoute($route->getMethod(), $route->getPath(), $route->getName());
        }

        return $routeCollector;
    }

    private function getRoute(string $name): RouteInterface
    {
        if (!isset($this->routes[$name])) {
            throw MissingRouteByNameException::create($name);
        }

        return $this->routes[$name];
    }

    /**
     * @param array<int, array<int, array|string>> $routePartSets
     * @param array<string>                        $attributes
     */
    private function getRouteIndex(array $routePartSets, array $attributes): int
    {
        foreach ($routePartSets as $routeIndex => $routeParts) {
            foreach ($routeParts as $routePart) {
                if (\is_array($routePart)) {
                    $parameter = $routePart[0];

                    if (!isset($attributes[$parameter])) {
                        continue 2;
                    }
                }
            }

            return $routeIndex;
        }

        return $routeIndex;
    }

    /**
     * @param array<int, array<int, array|string>> $routePartSets
     * @param array<string>                        $attributes
     *
     * @return string
     */
    private function generatePathFromAttributes(string $name, array $routePartSets, array $attributes, int $routeIndex)
    {
        $pathParts = [];

        foreach ($routePartSets[$routeIndex] as $routePart) {
            if (\is_array($routePart)) {
                $pathParts[] = $this->getAttributeValue($name, $routePart, $attributes);
            } else {
                $pathParts[] = $routePart;
            }
        }

        return \implode('', $pathParts);
    }

    /**
     * @param array<int, string> $routePart
     * @param array<string>      $attributes
     */
    private function getAttributeValue(string $name, array $routePart, array $attributes): string
    {
        $attribute = $routePart[0];

        if (!isset($attributes[$attribute])) {
            throw MissingAttributeForPathGenerationException::create($name, $attribute);
        }

        $value = (string) $attributes[$attribute];
        $pattern = '!^'.$routePart[1].'$!';

        if (1 !== \preg_match($pattern, $value)) {
            throw NotMatchingValueForPathGenerationException::create(
                $name,
                $attribute,
                $value,
                $routePart[1]
            );
        }

        return $value;
    }
}
