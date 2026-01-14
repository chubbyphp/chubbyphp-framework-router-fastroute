<?php

declare(strict_types=1);

namespace Chubbyphp\Framework\Router\FastRoute;

use Chubbyphp\Framework\Router\RouteInterface;
use Chubbyphp\Framework\Router\RouteMatcherInterface;
use Chubbyphp\Framework\Router\RoutesByNameInterface;
use Chubbyphp\HttpException\HttpException;
use FastRoute\DataGenerator\GroupCountBased as DataGenerator;
use FastRoute\Dispatcher\GroupCountBased as Dispatcher;
use FastRoute\RouteCollector;
use FastRoute\RouteParser\Std as RouteParser;
use Psr\Http\Message\ServerRequestInterface;

final class RouteMatcher implements RouteMatcherInterface
{
    /**
     * @var array<string, RouteInterface>
     */
    private readonly array $routesByName;

    private readonly Dispatcher $dispatcher;

    public function __construct(RoutesByNameInterface $routes, ?string $cacheFile = null)
    {
        $this->routesByName = $routes->getRoutesByName();
        $this->dispatcher = $this->getDispatcher($cacheFile);
    }

    public function match(ServerRequestInterface $request): RouteInterface
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();

        $routeInfo = $this->dispatcher->dispatch($method, rawurldecode($path));

        if (Dispatcher::NOT_FOUND === $routeInfo[0]) {
            throw HttpException::createNotFound([
                'detail' => \sprintf(
                    'The path "%s" you are looking for could not be found.',
                    $path
                ),
            ]);
        }

        if (Dispatcher::METHOD_NOT_ALLOWED === $routeInfo[0]) {
            /** @var array<string> */
            $allowedMethods = $routeInfo[1];

            throw HttpException::createMethodNotAllowed([
                'detail' => \sprintf(
                    'Method "%s" at path "%s" is not allowed. Must be one of: "%s"',
                    $method,
                    $path,
                    implode('", "', $allowedMethods),
                ),
            ]);
        }

        /** @var string $routeName */
        $routeName = $routeInfo[1];

        /** @var RouteInterface $route */
        $route = $this->routesByName[$routeName];

        /** @var array<string, string> */
        $attributes = $routeInfo[2];

        return $route->withAttributes($attributes);
    }

    private function getDispatcher(?string $cacheFile = null): Dispatcher
    {
        if (null === $cacheFile) {
            return new Dispatcher($this->getRouteCollector()->getData());
        }

        if (!file_exists($cacheFile)) {
            file_put_contents(
                $cacheFile,
                '<?php return '.var_export($this->getRouteCollector()->getData(), true).';'
            );
        }

        return new Dispatcher(require $cacheFile);
    }

    private function getRouteCollector(): RouteCollector
    {
        $routeCollector = new RouteCollector(new RouteParser(), new DataGenerator());

        foreach ($this->routesByName as $route) {
            $routeCollector->addRoute($route->getMethod(), $route->getPath(), $route->getName());
        }

        return $routeCollector;
    }
}
