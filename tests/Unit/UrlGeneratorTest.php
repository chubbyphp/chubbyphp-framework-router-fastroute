<?php

declare(strict_types=1);

namespace Chubbyphp\Tests\Framework\Router\FastRoute\Unit;

use Chubbyphp\Framework\Router\Exceptions\MissingRouteByNameException;
use Chubbyphp\Framework\Router\Exceptions\RouteGenerationException;
use Chubbyphp\Framework\Router\FastRoute\UrlGenerator;
use Chubbyphp\Framework\Router\RouteInterface;
use Chubbyphp\Framework\Router\RoutesByNameInterface;
use Chubbyphp\Mock\Call;
use Chubbyphp\Mock\MockByCallsTrait;
use PHPUnit\Framework\MockObject\MockObject;
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
    use MockByCallsTrait;

    public function testGenerateUri(): void
    {
        /** @var MockObject|UriInterface $uri */
        $uri = $this->getMockByCalls(UriInterface::class, [
            Call::create('getScheme')->with()->willReturn('https'),
            Call::create('getAuthority')->with()->willReturn('user:password@localhost'),
            Call::create('getScheme')->with()->willReturn('https'),
            Call::create('getAuthority')->with()->willReturn('user:password@localhost'),
            Call::create('getScheme')->with()->willReturn('https'),
            Call::create('getAuthority')->with()->willReturn('user:password@localhost'),
            Call::create('getScheme')->with()->willReturn('https'),
            Call::create('getAuthority')->with()->willReturn('user:password@localhost'),
        ]);

        /** @var MockObject|ServerRequestInterface $request */
        $request = $this->getMockByCalls(ServerRequestInterface::class, [
            Call::create('getUri')->with()->willReturn($uri),
            Call::create('getUri')->with()->willReturn($uri),
            Call::create('getUri')->with()->willReturn($uri),
            Call::create('getUri')->with()->willReturn($uri),
        ]);

        /** @var MockObject|RouteInterface $route */
        $route = $this->getMockByCalls(RouteInterface::class, [
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
        ]);

        /** @var MockObject|RoutesByNameInterface $routesByName */
        $routesByName = $this->getMockByCalls(RoutesByNameInterface::class, [
            Call::create('getRoutesByName')->with()->willReturn(['user' => $route]),
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

        /** @var MockObject|UriInterface $uri */
        $uri = $this->getMockByCalls(UriInterface::class);

        /** @var MockObject|ServerRequestInterface $request */
        $request = $this->getMockByCalls(ServerRequestInterface::class, [
            Call::create('getUri')->with()->willReturn($uri),
        ]);

        /** @var MockObject|RouteInterface $route */
        $route = $this->getMockByCalls(RouteInterface::class, [
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
        ]);

        /** @var MockObject|RoutesByNameInterface $routesByName */
        $routesByName = $this->getMockByCalls(RoutesByNameInterface::class, [
            Call::create('getRoutesByName')->with()->willReturn(['user' => $route]),
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

        /** @var MockObject|UriInterface $uri */
        $uri = $this->getMockByCalls(UriInterface::class);

        /** @var MockObject|ServerRequestInterface $request */
        $request = $this->getMockByCalls(ServerRequestInterface::class, [
            Call::create('getUri')->with()->willReturn($uri),
        ]);

        /** @var MockObject|RouteInterface $route */
        $route = $this->getMockByCalls(RouteInterface::class, [
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
        ]);

        /** @var MockObject|RoutesByNameInterface $routesByName */
        $routesByName = $this->getMockByCalls(RoutesByNameInterface::class, [
            Call::create('getRoutesByName')->with()->willReturn(['user' => $route]),
        ]);

        $urlGenerator = new UrlGenerator($routesByName);
        $urlGenerator->generateUrl($request, 'user', ['id' => 'a3bce0ca-2b7c-4fc6-8dad-ecdcc6907791']);
    }

    public function testGenerateUriWithBasePath(): void
    {
        /** @var MockObject|UriInterface $uri */
        $uri = $this->getMockByCalls(UriInterface::class, [
            Call::create('getScheme')->with()->willReturn('https'),
            Call::create('getAuthority')->with()->willReturn('user:password@localhost'),
            Call::create('getScheme')->with()->willReturn('https'),
            Call::create('getAuthority')->with()->willReturn('user:password@localhost'),
            Call::create('getScheme')->with()->willReturn('https'),
            Call::create('getAuthority')->with()->willReturn('user:password@localhost'),
            Call::create('getScheme')->with()->willReturn('https'),
            Call::create('getAuthority')->with()->willReturn('user:password@localhost'),
        ]);

        /** @var MockObject|ServerRequestInterface $request */
        $request = $this->getMockByCalls(ServerRequestInterface::class, [
            Call::create('getUri')->with()->willReturn($uri),
            Call::create('getUri')->with()->willReturn($uri),
            Call::create('getUri')->with()->willReturn($uri),
            Call::create('getUri')->with()->willReturn($uri),
        ]);

        /** @var MockObject|RouteInterface $route */
        $route = $this->getMockByCalls(RouteInterface::class, [
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
        ]);

        /** @var MockObject|RoutesByNameInterface $routesByName */
        $routesByName = $this->getMockByCalls(RoutesByNameInterface::class, [
            Call::create('getRoutesByName')->with()->willReturn(['user' => $route]),
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

        /** @var MockObject|RoutesByNameInterface $routesByName */
        $routesByName = $this->getMockByCalls(RoutesByNameInterface::class, [
            Call::create('getRoutesByName')->with()->willReturn([]),
        ]);

        $urlGenerator = new UrlGenerator($routesByName);
        $urlGenerator->generatePath('user', ['id' => 1]);
    }

    public function testGeneratePath(): void
    {
        /** @var MockObject|RouteInterface $route */
        $route = $this->getMockByCalls(RouteInterface::class, [
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
        ]);

        /** @var MockObject|RoutesByNameInterface $routesByName */
        $routesByName = $this->getMockByCalls(RoutesByNameInterface::class, [
            Call::create('getRoutesByName')->with()->willReturn(['user' => $route]),
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

        /** @var MockObject|RouteInterface $route */
        $route = $this->getMockByCalls(RouteInterface::class, [
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
        ]);

        /** @var MockObject|RoutesByNameInterface $routesByName */
        $routesByName = $this->getMockByCalls(RoutesByNameInterface::class, [
            Call::create('getRoutesByName')->with()->willReturn(['user' => $route]),
        ]);

        $urlGenerator = new UrlGenerator($routesByName);
        $urlGenerator->generatePath('user');
    }

    public function testGeneratePathWithBasePath(): void
    {
        /** @var MockObject|RouteInterface $route */
        $route = $this->getMockByCalls(RouteInterface::class, [
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
        ]);

        /** @var MockObject|RoutesByNameInterface $routesByName */
        $routesByName = $this->getMockByCalls(RoutesByNameInterface::class, [
            Call::create('getRoutesByName')->with()->willReturn(['user' => $route]),
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
