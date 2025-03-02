<?php

declare(strict_types=1);

namespace Chubbyphp\Tests\Framework\Router\FastRoute\Unit;

use Chubbyphp\Framework\Router\Exceptions\MissingRouteByNameException;
use Chubbyphp\Framework\Router\Exceptions\RouteGenerationException;
use Chubbyphp\Framework\Router\FastRoute\UrlGenerator;
use Chubbyphp\Framework\Router\RouteInterface;
use Chubbyphp\Framework\Router\RoutesByNameInterface;
use Chubbyphp\Mock\MockMethod\WithReturn;
use Chubbyphp\Mock\MockObjectBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * @covers \Chubbyphp\Framework\Router\FastRoute\UrlGenerator
 *
 * @internal
 */
final class UrlGeneratorTest extends TestCase
{
    public function testGenerateUri(): void
    {
        $builder = new MockObjectBuilder();

        /** @var UriInterface $uri */
        $uri = $builder->create(UriInterface::class, [
            new WithReturn('getScheme', [], 'https'),
            new WithReturn('getAuthority', [], 'user:password@localhost'),
            new WithReturn('getScheme', [], 'https'),
            new WithReturn('getAuthority', [], 'user:password@localhost'),
            new WithReturn('getScheme', [], 'https'),
            new WithReturn('getAuthority', [], 'user:password@localhost'),
            new WithReturn('getScheme', [], 'https'),
            new WithReturn('getAuthority', [], 'user:password@localhost'),
        ]);

        /** @var ServerRequestInterface $request */
        $request = $builder->create(ServerRequestInterface::class, [
            new WithReturn('getUri', [], $uri),
            new WithReturn('getUri', [], $uri),
            new WithReturn('getUri', [], $uri),
            new WithReturn('getUri', [], $uri),
        ]);

        /** @var RouteInterface $route */
        $route = $builder->create(RouteInterface::class, [
            new WithReturn('getPath', [], '/user/{id:\d+}[/{name}]'),
            new WithReturn('getPath', [], '/user/{id:\d+}[/{name}]'),
            new WithReturn('getPath', [], '/user/{id:\d+}[/{name}]'),
            new WithReturn('getPath', [], '/user/{id:\d+}[/{name}]'),
        ]);

        /** @var RoutesByNameInterface $routesByName */
        $routesByName = $builder->create(RoutesByNameInterface::class, [
            new WithReturn('getRoutesByName', [], ['user' => $route]),
        ]);

        $urlGenerator = new UrlGenerator($routesByName);

        self::assertSame(
            'https://user:password@localhost/user/1',
            $urlGenerator->generateUrl($request, 'user', ['id' => 1])
        );
        self::assertSame(
            'https://user:password@localhost/user/1?key=value',
            $urlGenerator->generateUrl($request, 'user', ['id' => 1], ['key' => 'value'])
        );
        self::assertSame(
            'https://user:password@localhost/user/1/sample',
            $urlGenerator->generateUrl($request, 'user', ['id' => 1, 'name' => 'sample'])
        );
        self::assertSame(
            'https://user:password@localhost/user/1/sample?key1=value1&key2=value2',
            $urlGenerator->generateUrl(
                $request,
                'user',
                ['id' => 1, 'name' => 'sample'],
                ['key1' => 'value1', 'key2' => 'value2']
            )
        );
    }

    public function testGenerateUriWithMissingAttribute(): void
    {
        $this->expectException(RouteGenerationException::class);
        $this->expectExceptionMessage('Route generation for route "user" with path "/user/{id:\d+}[/{name}]" with attributes "{}" failed. Missing attribute "id"');
        $this->expectExceptionCode(3);

        $builder = new MockObjectBuilder();

        /** @var UriInterface $uri */
        $uri = $builder->create(UriInterface::class, []);

        /** @var ServerRequestInterface $request */
        $request = $builder->create(ServerRequestInterface::class, [
            new WithReturn('getUri', [], $uri),
        ]);

        /** @var RouteInterface $route */
        $route = $builder->create(RouteInterface::class, [
            new WithReturn('getPath', [], '/user/{id:\d+}[/{name}]'),
        ]);

        /** @var RoutesByNameInterface $routesByName */
        $routesByName = $builder->create(RoutesByNameInterface::class, [
            new WithReturn('getRoutesByName', [], ['user' => $route]),
        ]);

        $urlGenerator = new UrlGenerator($routesByName);
        $urlGenerator->generateUrl($request, 'user');
    }

    public function testGenerateUriWithNotMatchingAttribute(): void
    {
        $this->expectException(RouteGenerationException::class);
        $this->expectExceptionMessage(
            'Route generation for route "user" with path "/user/{id:\d+}[/{name}]" with attributes "{"id":"a3bce0ca-2b7c-4fc6-8dad-ecdcc6907791"}" failed. Not matching value "a3bce0ca-2b7c-4fc6-8dad-ecdcc6907791" with pattern "\d+" on attribute "id"'
        );
        $this->expectExceptionCode(3);

        $builder = new MockObjectBuilder();

        /** @var UriInterface $uri */
        $uri = $builder->create(UriInterface::class, []);

        /** @var ServerRequestInterface $request */
        $request = $builder->create(ServerRequestInterface::class, [
            new WithReturn('getUri', [], $uri),
        ]);

        /** @var RouteInterface $route */
        $route = $builder->create(RouteInterface::class, [
            new WithReturn('getPath', [], '/user/{id:\d+}[/{name}]'),
        ]);

        /** @var RoutesByNameInterface $routesByName */
        $routesByName = $builder->create(RoutesByNameInterface::class, [
            new WithReturn('getRoutesByName', [], ['user' => $route]),
        ]);

        $urlGenerator = new UrlGenerator($routesByName);
        $urlGenerator->generateUrl($request, 'user', ['id' => 'a3bce0ca-2b7c-4fc6-8dad-ecdcc6907791']);
    }

    public function testGenerateUriWithBasePath(): void
    {
        $builder = new MockObjectBuilder();

        /** @var UriInterface $uri */
        $uri = $builder->create(UriInterface::class, [
            new WithReturn('getScheme', [], 'https'),
            new WithReturn('getAuthority', [], 'user:password@localhost'),
            new WithReturn('getScheme', [], 'https'),
            new WithReturn('getAuthority', [], 'user:password@localhost'),
            new WithReturn('getScheme', [], 'https'),
            new WithReturn('getAuthority', [], 'user:password@localhost'),
            new WithReturn('getScheme', [], 'https'),
            new WithReturn('getAuthority', [], 'user:password@localhost'),
        ]);

        /** @var ServerRequestInterface $request */
        $request = $builder->create(ServerRequestInterface::class, [
            new WithReturn('getUri', [], $uri),
            new WithReturn('getUri', [], $uri),
            new WithReturn('getUri', [], $uri),
            new WithReturn('getUri', [], $uri),
        ]);

        /** @var RouteInterface $route */
        $route = $builder->create(RouteInterface::class, [
            new WithReturn('getPath', [], '/user/{id:\d+}[/{name}]'),
            new WithReturn('getPath', [], '/user/{id:\d+}[/{name}]'),
            new WithReturn('getPath', [], '/user/{id:\d+}[/{name}]'),
            new WithReturn('getPath', [], '/user/{id:\d+}[/{name}]'),
        ]);

        /** @var RoutesByNameInterface $routesByName */
        $routesByName = $builder->create(RoutesByNameInterface::class, [
            new WithReturn('getRoutesByName', [], ['user' => $route]),
        ]);

        $urlGenerator = new UrlGenerator($routesByName, '/path/to/directory');

        self::assertSame(
            'https://user:password@localhost/path/to/directory/user/1',
            $urlGenerator->generateUrl($request, 'user', ['id' => 1])
        );
        self::assertSame(
            'https://user:password@localhost/path/to/directory/user/1?key=value',
            $urlGenerator->generateUrl($request, 'user', ['id' => 1], ['key' => 'value'])
        );
        self::assertSame(
            'https://user:password@localhost/path/to/directory/user/1/sample',
            $urlGenerator->generateUrl($request, 'user', ['id' => 1, 'name' => 'sample'])
        );
        self::assertSame(
            'https://user:password@localhost/path/to/directory/user/1/sample?key1=value1&key2=value2',
            $urlGenerator->generateUrl(
                $request,
                'user',
                ['id' => 1, 'name' => 'sample'],
                ['key1' => 'value1', 'key2' => 'value2']
            )
        );
    }

    public function testGeneratePathWithMissingRoute(): void
    {
        $this->expectException(MissingRouteByNameException::class);
        $this->expectExceptionMessage('Missing route: "user"');

        $builder = new MockObjectBuilder();

        /** @var RoutesByNameInterface $routesByName */
        $routesByName = $builder->create(RoutesByNameInterface::class, [
            new WithReturn('getRoutesByName', [], []),
        ]);

        $urlGenerator = new UrlGenerator($routesByName);
        $urlGenerator->generatePath('user', ['id' => 1]);
    }

    public function testGeneratePath(): void
    {
        $builder = new MockObjectBuilder();

        /** @var RouteInterface $route */
        $route = $builder->create(RouteInterface::class, [
            new WithReturn('getPath', [], '/user/{id:\d+}[/{name}]'),
            new WithReturn('getPath', [], '/user/{id:\d+}[/{name}]'),
            new WithReturn('getPath', [], '/user/{id:\d+}[/{name}]'),
            new WithReturn('getPath', [], '/user/{id:\d+}[/{name}]'),
        ]);

        /** @var RoutesByNameInterface $routesByName */
        $routesByName = $builder->create(RoutesByNameInterface::class, [
            new WithReturn('getRoutesByName', [], ['user' => $route]),
        ]);

        $urlGenerator = new UrlGenerator($routesByName);

        self::assertSame('/user/1', $urlGenerator->generatePath('user', ['id' => 1]));
        self::assertSame('/user/1?key=value', $urlGenerator->generatePath('user', ['id' => 1], ['key' => 'value']));
        self::assertSame('/user/1/sample', $urlGenerator->generatePath('user', ['id' => 1, 'name' => 'sample']));
        self::assertSame(
            '/user/1/sample?key1=value1&key2=value2',
            $urlGenerator->generatePath(
                'user',
                ['id' => 1, 'name' => 'sample'],
                ['key1' => 'value1', 'key2' => 'value2']
            )
        );
    }

    public function testGeneratePathWithMissingAttribute(): void
    {
        $this->expectException(RouteGenerationException::class);
        $this->expectExceptionMessage('Route generation for route "user" with path "/user/{id:\d+}[/{name}]" with attributes "{}" failed. Missing attribute "id"');

        $builder = new MockObjectBuilder();

        /** @var RouteInterface $route */
        $route = $builder->create(RouteInterface::class, [
            new WithReturn('getPath', [], '/user/{id:\d+}[/{name}]'),
        ]);

        /** @var RoutesByNameInterface $routesByName */
        $routesByName = $builder->create(RoutesByNameInterface::class, [
            new WithReturn('getRoutesByName', [], ['user' => $route]),
        ]);

        $urlGenerator = new UrlGenerator($routesByName);
        $urlGenerator->generatePath('user');
    }

    public function testGeneratePathWithBasePath(): void
    {
        $builder = new MockObjectBuilder();

        /** @var RouteInterface $route */
        $route = $builder->create(RouteInterface::class, [
            new WithReturn('getPath', [], '/user/{id:\d+}[/{name}]'),
            new WithReturn('getPath', [], '/user/{id:\d+}[/{name}]'),
            new WithReturn('getPath', [], '/user/{id:\d+}[/{name}]'),
            new WithReturn('getPath', [], '/user/{id:\d+}[/{name}]'),
        ]);

        /** @var RoutesByNameInterface $routesByName */
        $routesByName = $builder->create(RoutesByNameInterface::class, [
            new WithReturn('getRoutesByName', [], ['user' => $route]),
        ]);

        $urlGenerator = new UrlGenerator($routesByName, '/path/to/directory');

        self::assertSame('/path/to/directory/user/1', $urlGenerator->generatePath('user', ['id' => 1]));
        self::assertSame(
            '/path/to/directory/user/1?key=value',
            $urlGenerator->generatePath('user', ['id' => 1], ['key' => 'value'])
        );
        self::assertSame(
            '/path/to/directory/user/1/sample',
            $urlGenerator->generatePath('user', ['id' => 1, 'name' => 'sample'])
        );
        self::assertSame(
            '/path/to/directory/user/1/sample?key1=value1&key2=value2',
            $urlGenerator->generatePath(
                'user',
                ['id' => 1, 'name' => 'sample'],
                ['key1' => 'value1', 'key2' => 'value2']
            )
        );
    }
}
