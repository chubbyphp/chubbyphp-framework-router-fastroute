<?php

declare(strict_types=1);

namespace Chubbyphp\Framework\Router\FastRoute;

use Chubbyphp\Framework\Router\Exceptions\MissingRouteByNameException;
use Chubbyphp\Framework\Router\Exceptions\RouteGenerationException;
use Chubbyphp\Framework\Router\RouteInterface;
use Chubbyphp\Framework\Router\RoutesInterface;
use Chubbyphp\Framework\Router\UrlGeneratorInterface;
use FastRoute\RouteParser\Std as RouteParser;
use Psr\Http\Message\ServerRequestInterface;

final class UrlGenerator implements UrlGeneratorInterface
{
    /**
     * @var array<string, RouteInterface>
     */
    private array $routesByName;

    private RouteParser $routeParser;

    private string $basePath;

    public function __construct(RoutesInterface $routes, string $basePath = '')
    {
        $this->routesByName = $routes->getRoutesByName();
        $this->routeParser = new RouteParser();
        $this->basePath = $basePath;
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
        $path = $route->getPath();

        $routePartSets = array_reverse($this->routeParser->parse($path));

        $routeIndex = $this->getRouteIndex($routePartSets, $attributes);

        $path = $this->generatePathFromAttributes($name, $path, $routePartSets, $attributes, $routeIndex);

        if ([] === $queryParams) {
            return $this->basePath.$path;
        }

        return $this->basePath.$path.'?'.http_build_query($queryParams);
    }

    private function getRoute(string $name): RouteInterface
    {
        if (!isset($this->routesByName[$name])) {
            throw MissingRouteByNameException::create($name);
        }

        return $this->routesByName[$name];
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
     */
    private function generatePathFromAttributes(
        string $name,
        string $path,
        array $routePartSets,
        array $attributes,
        int $routeIndex
    ): string {
        $pathParts = [];

        foreach ($routePartSets[$routeIndex] as $routePart) {
            if (\is_array($routePart)) {
                $pathParts[] = $this->getAttributeValue($name, $path, $routePart, $attributes);
            } else {
                $pathParts[] = $routePart;
            }
        }

        return implode('', $pathParts);
    }

    /**
     * @param array<int, string> $routePart
     * @param array<string>      $attributes
     */
    private function getAttributeValue(string $name, string $path, array $routePart, array $attributes): string
    {
        $attribute = $routePart[0];

        if (!isset($attributes[$attribute])) {
            throw RouteGenerationException::create(
                $name,
                $path,
                $attributes,
                new \RuntimeException(sprintf('Missing attribute "%s"', $attribute))
            );
        }

        $value = (string) $attributes[$attribute];
        $pattern = '!^'.$routePart[1].'$!';

        if (1 !== preg_match($pattern, $value)) {
            throw RouteGenerationException::create(
                $name,
                $path,
                $attributes,
                new \RuntimeException(sprintf(
                    'Not matching value "%s" with pattern "%s" on attribute "%s"',
                    $value,
                    $routePart[1],
                    $attribute
                ))
            );
        }

        return $value;
    }
}
