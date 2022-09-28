<?php

declare(strict_types=1);

namespace Chubbyphp\Tests\Framework\Router\FastRoute\Unit;

use Chubbyphp\Framework\Router\FastRoute\RouteMatcher;
use Chubbyphp\Framework\Router\RouteInterface;
use Chubbyphp\Framework\Router\RoutesByNameInterface;
use Chubbyphp\HttpException\HttpException;
use Chubbyphp\Mock\Call;
use Chubbyphp\Mock\MockByCallsTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * @covers \Chubbyphp\Framework\Router\FastRoute\RouteMatcher
 *
 * @internal
 */
final class RouteMatcherTest extends TestCase
{
    use MockByCallsTrait;

    public const UUID_PATTERN = '[0-9a-f]{8}\b-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-\b[0-9a-f]{12}';

    public function testMatchFound(): void
    {
        /** @var MockObject|UriInterface $uri */
        $uri = $this->getMockByCalls(UriInterface::class, [
            Call::create('getPath')->with()->willReturn('/api/pets'),
        ]);

        /** @var MockObject|ServerRequestInterface $request */
        $request = $this->getMockByCalls(ServerRequestInterface::class, [
            Call::create('getMethod')->with()->willReturn('GET'),
            Call::create('getUri')->with()->willReturn($uri),
        ]);

        /** @var MockObject|RouteInterface $route1 */
        $route1 = $this->getMockByCalls(RouteInterface::class, [
            Call::create('getMethod')->with()->willReturn('POST'),
            Call::create('getPath')->with()->willReturn('/api/pets'),
            Call::create('getName')->with()->willReturn('pet_create'),
        ]);

        /** @var MockObject|RouteInterface $route2 */
        $route2 = $this->getMockByCalls(RouteInterface::class, [
            Call::create('getMethod')->with()->willReturn('GET'),
            Call::create('getPath')->with()->willReturn('/api/pets'),
            Call::create('getName')->with()->willReturn('pet_list'),
            Call::create('withAttributes')->with([])->willReturnSelf(),
        ]);

        $cacheFile = sys_get_temp_dir().'/fast-route-'.uniqid().uniqid().'.php';

        self::assertFileDoesNotExist($cacheFile);

        /** @var MockObject|RoutesByNameInterface $routesByName */
        $routesByName = $this->getMockByCalls(RoutesByNameInterface::class, [
            Call::create('getRoutesByName')->with()->willReturn(['pet_create' => $route1, 'pet_list' => $route2]),
        ]);

        $routeMatcher = new RouteMatcher($routesByName, $cacheFile);

        self::assertFileExists($cacheFile);

        self::assertSame($route2, $routeMatcher->match($request));

        unlink($cacheFile);
    }

    public function testMatchNotFound(): void
    {
        /** @var MockObject|UriInterface $uri */
        $uri = $this->getMockByCalls(UriInterface::class, [
            Call::create('getPath')->with()->willReturn('/'),
        ]);

        /** @var MockObject|ServerRequestInterface $request */
        $request = $this->getMockByCalls(ServerRequestInterface::class, [
            Call::create('getMethod')->with()->willReturn('GET'),
            Call::create('getUri')->with()->willReturn($uri),
            Call::create('getRequestTarget')->with()->willReturn('/'),
        ]);

        /** @var MockObject|RouteInterface $route */
        $route = $this->getMockByCalls(RouteInterface::class, [
            Call::create('getMethod')->with()->willReturn('GET'),
            Call::create('getPath')->with()->willReturn('/api/pets'),
            Call::create('getName')->with()->willReturn('pet_list'),
        ]);

        /** @var MockObject|RoutesByNameInterface $routesByName */
        $routesByName = $this->getMockByCalls(RoutesByNameInterface::class, [
            Call::create('getRoutesByName')->with()->willReturn(['pet_list' => $route]),
        ]);

        $routeMatcher = new RouteMatcher($routesByName);

        try {
            $routeMatcher->match($request);
            self::fail('Excepted exception');
        } catch (HttpException $e) {
            self::assertSame('Not Found', $e->getTitle());
            self::assertSame(404, $e->getStatus());
            self::assertSame([
                'type' => 'https://datatracker.ietf.org/doc/html/rfc2616#section-10.4.5',
                'status' => 404,
                'title' => 'Not Found',
                'detail' => 'The page "/" you are looking for could not be found. Check the address bar to ensure your URL is spelled correctly.',
                'instance' => null,
            ], $e->jsonSerialize());
        }
    }

    public function testMatchMethodNotAllowed(): void
    {
        /** @var MockObject|UriInterface $uri */
        $uri = $this->getMockByCalls(UriInterface::class, [
            Call::create('getPath')->with()->willReturn('/api/pets'),
        ]);

        /** @var MockObject|ServerRequestInterface $request */
        $request = $this->getMockByCalls(ServerRequestInterface::class, [
            Call::create('getMethod')->with()->willReturn('POST'),
            Call::create('getUri')->with()->willReturn($uri),
            Call::create('getRequestTarget')->with()->willReturn('/api/pets?offset=1&limit=20'),
        ]);

        /** @var MockObject|RouteInterface $route */
        $route = $this->getMockByCalls(RouteInterface::class, [
            Call::create('getMethod')->with()->willReturn('GET'),
            Call::create('getPath')->with()->willReturn('/api/pets'),
            Call::create('getName')->with()->willReturn('pet_list'),
        ]);

        /** @var MockObject|RoutesByNameInterface $routesByName */
        $routesByName = $this->getMockByCalls(RoutesByNameInterface::class, [
            Call::create('getRoutesByName')->with()->willReturn(['pet_list' => $route]),
        ]);

        $routeMatcher = new RouteMatcher($routesByName);

        try {
            $routeMatcher->match($request);
            self::fail('Excepted exception');
        } catch (HttpException $e) {
            self::assertSame('Method Not Allowed', $e->getTitle());
            self::assertSame(405, $e->getStatus());
            self::assertSame([
                'type' => 'https://datatracker.ietf.org/doc/html/rfc2616#section-10.4.6',
                'status' => 405,
                'title' => 'Method Not Allowed',
                'detail' => 'Method "POST" at path "/api/pets?offset=1&limit=20" is not allowed. Must be one of: "GET"',
                'instance' => null,
            ], $e->jsonSerialize());
        }
    }

    public function testMatchWithTokensNotMatch(): void
    {
        /** @var MockObject|UriInterface $uri */
        $uri = $this->getMockByCalls(UriInterface::class, [
            Call::create('getPath')->with()->willReturn('/api/pets/1'),
        ]);

        /** @var MockObject|ServerRequestInterface $request */
        $request = $this->getMockByCalls(ServerRequestInterface::class, [
            Call::create('getMethod')->with()->willReturn('GET'),
            Call::create('getUri')->with()->willReturn($uri),
            Call::create('getRequestTarget')->with()->willReturn('/api/pets/1'),
        ]);

        /** @var MockObject|RouteInterface $route */
        $route = $this->getMockByCalls(RouteInterface::class, [
            Call::create('getMethod')->with()->willReturn('GET'),
            Call::create('getPath')->with()->willReturn('/api/pets/{id:'.self::UUID_PATTERN.'}'),
            Call::create('getName')->with()->willReturn('pet_read'),
        ]);

        /** @var MockObject|RoutesByNameInterface $routesByName */
        $routesByName = $this->getMockByCalls(RoutesByNameInterface::class, [
            Call::create('getRoutesByName')->with()->willReturn(['pet_read' => $route]),
        ]);

        $routeMatcher = new RouteMatcher($routesByName);

        try {
            $routeMatcher->match($request);
            self::fail('Excepted exception');
        } catch (HttpException $e) {
            self::assertSame('Not Found', $e->getTitle());
            self::assertSame(404, $e->getStatus());
            self::assertSame([
                'type' => 'https://datatracker.ietf.org/doc/html/rfc2616#section-10.4.5',
                'status' => 404,
                'title' => 'Not Found',
                'detail' => 'The page "/api/pets/1" you are looking for could not be found. Check the address bar to ensure your URL is spelled correctly.',
                'instance' => null,
            ], $e->jsonSerialize());
        }
    }

    public function testMatchWithTokensMatch(): void
    {
        /** @var MockObject|UriInterface $uri */
        $uri = $this->getMockByCalls(UriInterface::class, [
            Call::create('getPath')->with()->willReturn('/api/pets/8b72750c-5306-416c-bba7-5b41f1c44791'),
        ]);

        /** @var MockObject|ServerRequestInterface $request */
        $request = $this->getMockByCalls(ServerRequestInterface::class, [
            Call::create('getMethod')->with()->willReturn('GET'),
            Call::create('getUri')->with()->willReturn($uri),
        ]);

        /** @var MockObject|RouteInterface $route */
        $route = $this->getMockByCalls(RouteInterface::class, [
            Call::create('getMethod')->with()->willReturn('GET'),
            Call::create('getPath')->with()->willReturn('/api/pets/{id:'.self::UUID_PATTERN.'}'),
            Call::create('getName')->with()->willReturn('pet_read'),
            Call::create('withAttributes')->with(['id' => '8b72750c-5306-416c-bba7-5b41f1c44791'])->willReturnSelf(),
        ]);

        /** @var MockObject|RoutesByNameInterface $routesByName */
        $routesByName = $this->getMockByCalls(RoutesByNameInterface::class, [
            Call::create('getRoutesByName')->with()->willReturn(['pet_read' => $route]),
        ]);

        $routeMatcher = new RouteMatcher($routesByName);

        self::assertSame($route, $routeMatcher->match($request));
    }
}
