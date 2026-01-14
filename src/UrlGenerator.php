<?php

declare(strict_types=1);

namespace Chubbyphp\Framework\Router\FastRoute;

use Chubbyphp\Framework\Router\Exceptions\MissingRouteByNameException;
use Chubbyphp\Framework\Router\Exceptions\RouteGenerationException;
use Chubbyphp\Framework\Router\RouteInterface;
use Chubbyphp\Framework\Router\RoutesByNameInterface;
use Chubbyphp\Framework\Router\UrlGeneratorInterface;
use FastRoute\RouteParser\Std as RouteParser;
use Psr\Http\Message\ServerRequestInterface;

final class UrlGenerator implements UrlGeneratorInterface
{
    /**
     * @var array<string, RouteInterface>
     */
    private readonly array $routesByName;

    private readonly RouteParser $routeParser;

    public function __construct(RoutesByNameInterface $routes, private readonly string $basePath = '')
    {
        $this->routesByName = $routes->getRoutesByName();
        $this->routeParser = new RouteParser();
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
        $routePath = $route->getPath();

        $routePartSets = array_reverse($this->routeParser->parse($routePath));
        $routeParts = $this->findMatchingRouteParts($routePartSets, $attributes);

        $path = $this->buildPath($name, $routePath, $routeParts, $attributes);

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
     * @param array<int, array<int, array<int, string>|string>> $routePartSets
     * @param array<string>                                     $attributes
     *
     * @return array<int, array<int, string>|string>
     */
    private function findMatchingRouteParts(array $routePartSets, array $attributes): array
    {
        foreach ($routePartSets as $routeParts) {
            if ($this->hasAllRequiredAttributes($routeParts, $attributes)) {
                return $routeParts;
            }
        }

        return end($routePartSets) ?: [];
    }

    /**
     * @param array<int, array<int, string>|string> $routeParts
     * @param array<string>                         $attributes
     */
    private function hasAllRequiredAttributes(array $routeParts, array $attributes): bool
    {
        foreach ($routeParts as $routePart) {
            if (\is_array($routePart) && !isset($attributes[$routePart[0]])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, array<int, string>|string> $routeParts
     * @param array<string>                         $attributes
     */
    private function buildPath(string $name, string $path, array $routeParts, array $attributes): string
    {
        $pathParts = [];

        foreach ($routeParts as $routePart) {
            $pathParts[] = \is_array($routePart)
                ? $this->getAttributeValue($name, $path, $routePart, $attributes)
                : $routePart;
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
                new \RuntimeException(\sprintf('Missing attribute "%s"', $attribute))
            );
        }

        $value = (string) $attributes[$attribute];
        $pattern = '!^'.$routePart[1].'$!';

        if (1 !== preg_match($pattern, $value)) {
            throw RouteGenerationException::create(
                $name,
                $path,
                $attributes,
                new \RuntimeException(\sprintf(
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
