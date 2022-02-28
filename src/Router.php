<?php

declare(strict_types=1);

namespace Chubbyphp\Framework\Router\FastRoute;

use Chubbyphp\Framework\Router\RouteInterface;
use Chubbyphp\Framework\Router\RouteMatcherInterface;
use Chubbyphp\Framework\Router\Routes;
use Chubbyphp\Framework\Router\UrlGeneratorInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @deprecated
 */
final class Router implements RouteMatcherInterface, UrlGeneratorInterface
{
    private RouteMatcherInterface $routeMatcher;

    private UrlGeneratorInterface $urlGenerator;

    /**
     * @param array<RouteInterface> $routes
     */
    public function __construct(array $routes, ?string $cacheFile = null, string $basePath = '')
    {
        @trigger_error(
            sprintf('Use %s|%s instead of instead of "%s"', RouteMatcher::class, UrlGenerator::class, self::class),
            E_USER_DEPRECATED
        );

        $routes = new Routes($routes);
        $this->routeMatcher = new RouteMatcher($routes, $cacheFile);
        $this->urlGenerator = new UrlGenerator($routes, $basePath);
    }

    public function match(ServerRequestInterface $request): RouteInterface
    {
        return $this->routeMatcher->match($request);
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
        return $this->urlGenerator->generateUrl($request, $name, $attributes, $queryParams);
    }

    /**
     * @param array<string, string> $attributes
     * @param array<string, mixed>  $queryParams
     */
    public function generatePath(string $name, array $attributes = [], array $queryParams = []): string
    {
        return $this->urlGenerator->generatePath($name, $attributes, $queryParams);
    }
}
