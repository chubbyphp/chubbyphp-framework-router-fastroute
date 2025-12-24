<?php

declare(strict_types=1);

namespace Chubbyphp\Tests\Framework\Router\FastRoute\Unit;

use Chubbyphp\Framework\Router\FastRoute\RouteMatcher;
use Chubbyphp\Framework\Router\RouteInterface;
use Chubbyphp\Framework\Router\RoutesByNameInterface;
use Chubbyphp\HttpException\HttpException;
use Chubbyphp\Mock\MockMethod\WithReturn;
use Chubbyphp\Mock\MockMethod\WithReturnSelf;
use Chubbyphp\Mock\MockObjectBuilder;
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
    public const string UUID_PATTERN = '[0-9a-f]{8}\b-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-\b[0-9a-f]{12}';

    public function testMatchFound(): void
    {
        $builder = new MockObjectBuilder();

        /** @var UriInterface $uri */
        $uri = $builder->create(UriInterface::class, [
            new WithReturn('getPath', [], '/api/pets'),
        ]);

        /** @var ServerRequestInterface $request */
        $request = $builder->create(ServerRequestInterface::class, [
            new WithReturn('getMethod', [], 'GET'),
            new WithReturn('getUri', [], $uri),
        ]);

        /** @var RouteInterface $route1 */
        $route1 = $builder->create(RouteInterface::class, [
            new WithReturn('getMethod', [], 'POST'),
            new WithReturn('getPath', [], '/api/pets'),
            new WithReturn('getName', [], 'pet_create'),
        ]);

        /** @var RouteInterface $route2 */
        $route2 = $builder->create(RouteInterface::class, [
            new WithReturn('getMethod', [], 'GET'),
            new WithReturn('getPath', [], '/api/pets'),
            new WithReturn('getName', [], 'pet_list'),
            new WithReturnSelf('withAttributes', [[]]),
        ]);

        $cacheFile = sys_get_temp_dir().'/fast-route-'.uniqid().uniqid().'.php';

        self::assertFileDoesNotExist($cacheFile);

        /** @var RoutesByNameInterface $routesByName */
        $routesByName = $builder->create(RoutesByNameInterface::class, [
            new WithReturn('getRoutesByName', [], ['pet_create' => $route1, 'pet_list' => $route2]),
        ]);

        $routeMatcher = new RouteMatcher($routesByName, $cacheFile);

        self::assertFileExists($cacheFile);

        self::assertSame($route2, $routeMatcher->match($request));

        unlink($cacheFile);
    }

    public function testMatchNotFound(): void
    {
        $builder = new MockObjectBuilder();

        /** @var UriInterface $uri */
        $uri = $builder->create(UriInterface::class, [
            new WithReturn('getPath', [], '/'),
        ]);

        /** @var ServerRequestInterface $request */
        $request = $builder->create(ServerRequestInterface::class, [
            new WithReturn('getMethod', [], 'GET'),
            new WithReturn('getUri', [], $uri),
        ]);

        /** @var RouteInterface $route */
        $route = $builder->create(RouteInterface::class, [
            new WithReturn('getMethod', [], 'GET'),
            new WithReturn('getPath', [], '/api/pets'),
            new WithReturn('getName', [], 'pet_list'),
        ]);

        /** @var RoutesByNameInterface $routesByName */
        $routesByName = $builder->create(RoutesByNameInterface::class, [
            new WithReturn('getRoutesByName', [], ['pet_list' => $route]),
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
                'detail' => 'The path "/" you are looking for could not be found.',
                'instance' => null,
            ], $e->jsonSerialize());
        }
    }

    public function testMatchMethodNotAllowed(): void
    {
        $builder = new MockObjectBuilder();

        /** @var UriInterface $uri */
        $uri = $builder->create(UriInterface::class, [
            new WithReturn('getPath', [], '/api/pets'),
        ]);

        /** @var ServerRequestInterface $request */
        $request = $builder->create(ServerRequestInterface::class, [
            new WithReturn('getMethod', [], 'POST'),
            new WithReturn('getUri', [], $uri),
        ]);

        /** @var RouteInterface $route */
        $route = $builder->create(RouteInterface::class, [
            new WithReturn('getMethod', [], 'GET'),
            new WithReturn('getPath', [], '/api/pets'),
            new WithReturn('getName', [], 'pet_list'),
        ]);

        /** @var RoutesByNameInterface $routesByName */
        $routesByName = $builder->create(RoutesByNameInterface::class, [
            new WithReturn('getRoutesByName', [], ['pet_list' => $route]),
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
                'detail' => 'Method "POST" at path "/api/pets" is not allowed. Must be one of: "GET"',
                'instance' => null,
            ], $e->jsonSerialize());
        }
    }

    public function testMatchWithTokensNotMatch(): void
    {
        $builder = new MockObjectBuilder();

        /** @var UriInterface $uri */
        $uri = $builder->create(UriInterface::class, [
            new WithReturn('getPath', [], '/api/pets/1'),
        ]);

        /** @var ServerRequestInterface $request */
        $request = $builder->create(ServerRequestInterface::class, [
            new WithReturn('getMethod', [], 'GET'),
            new WithReturn('getUri', [], $uri),
        ]);

        /** @var RouteInterface $route */
        $route = $builder->create(RouteInterface::class, [
            new WithReturn('getMethod', [], 'GET'),
            new WithReturn('getPath', [], '/api/pets/{id:'.self::UUID_PATTERN.'}'),
            new WithReturn('getName', [], 'pet_read'),
        ]);

        /** @var RoutesByNameInterface $routesByName */
        $routesByName = $builder->create(RoutesByNameInterface::class, [
            new WithReturn('getRoutesByName', [], ['pet_read' => $route]),
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
                'detail' => 'The path "/api/pets/1" you are looking for could not be found.',
                'instance' => null,
            ], $e->jsonSerialize());
        }
    }

    public function testMatchWithTokensMatch(): void
    {
        $builder = new MockObjectBuilder();

        /** @var UriInterface $uri */
        $uri = $builder->create(UriInterface::class, [
            new WithReturn('getPath', [], '/api/pets/8b72750c-5306-416c-bba7-5b41f1c44791'),
        ]);

        /** @var ServerRequestInterface $request */
        $request = $builder->create(ServerRequestInterface::class, [
            new WithReturn('getMethod', [], 'GET'),
            new WithReturn('getUri', [], $uri),
        ]);

        /** @var RouteInterface $route */
        $route = $builder->create(RouteInterface::class, [
            new WithReturn('getMethod', [], 'GET'),
            new WithReturn('getPath', [], '/api/pets/{id:'.self::UUID_PATTERN.'}'),
            new WithReturn('getName', [], 'pet_read'),
            new WithReturnSelf('withAttributes', [['id' => '8b72750c-5306-416c-bba7-5b41f1c44791']]),
        ]);

        /** @var RoutesByNameInterface $routesByName */
        $routesByName = $builder->create(RoutesByNameInterface::class, [
            new WithReturn('getRoutesByName', [], ['pet_read' => $route]),
        ]);

        $routeMatcher = new RouteMatcher($routesByName);

        self::assertSame($route, $routeMatcher->match($request));
    }
}
