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
    private array $routesByName;

    private Dispatcher $dispatcher;

    public function __construct(RoutesByNameInterface $routes, ?string $cacheFile = null)
    {
        $this->routesByName = $routes->getRoutesByName();
        $this->dispatcher = $this->getDispatcher($cacheFile);
    }

    public function match(ServerRequestInterface $request): RouteInterface
    {
        $method = $request->getMethod();
        $path = rawurldecode($request->getUri()->getPath());

        $routeInfo = $this->dispatcher->dispatch($method, $path);

        if (Dispatcher::NOT_FOUND === $routeInfo[0]) {
            throw HttpException::createNotFound([
                'detail' => \sprintf(
                    'The page "%s" you are looking for could not be found.'
                    .' Check the address bar to ensure your URL is spelled correctly.',
                    $request->getRequestTarget()
                ),
            ]);
        }

        if (Dispatcher::METHOD_NOT_ALLOWED === $routeInfo[0]) {
            throw HttpException::createMethodNotAllowed([
                'detail' => \sprintf(
                    'Method "%s" at path "%s" is not allowed. Must be one of: "%s"',
                    $method,
                    $request->getRequestTarget(),
                    implode('", "', $routeInfo[1]),
                ),
            ]);
        }

        /** @var RouteInterface $route */
        $route = $this->routesByName[$routeInfo[1]];

        return $route->withAttributes($routeInfo[2]);
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
