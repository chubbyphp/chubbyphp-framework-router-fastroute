<?php

declare(strict_types=1);

namespace Chubbyphp\Tests\Framework\Router\FastRoute\Unit;

use Chubbyphp\Framework\Router\FastRoute\Router;
use Chubbyphp\Framework\Router\RouteInterface;
use Chubbyphp\Framework\Router\RouterException;
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
            Call::create('getName')->with()->willReturn('user'),
            Call::create('getMethod')->with()->willReturn('GET'),
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
            Call::create('getName')->with()->willReturn('user'),
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
        ]);

        $router = new Router([$route]);

        self::assertSame(
            'https://user:password@localhost/user/1',
            $router->generateUrl($request, 'user', ['id' => 1])
        );
        self::assertSame(
            'https://user:password@localhost/user/1?key=value',
            $router->generateUrl($request, 'user', ['id' => 1], ['key' => 'value'])
        );
        self::assertSame(
            'https://user:password@localhost/user/1/sample',
            $router->generateUrl($request, 'user', ['id' => 1, 'name' => 'sample'])
        );
        self::assertSame(
            'https://user:password@localhost/user/1/sample?key1=value1&key2=value2',
            $router->generateUrl(
                $request,
                'user',
                ['id' => 1, 'name' => 'sample'],
                ['key1' => 'value1', 'key2' => 'value2']
            )
        );
    }

    public function testGenerateUriWithMissingAttribute(): void
    {
        $this->expectException(RouterException::class);
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
            Call::create('getName')->with()->willReturn('user'),
            Call::create('getMethod')->with()->willReturn('GET'),
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
            Call::create('getName')->with()->willReturn('user'),
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
        ]);

        $router = new Router([$route]);
        $router->generateUrl($request, 'user');
    }

    public function testGenerateUriWithNotMatchingAttribute(): void
    {
        $this->expectException(RouterException::class);
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
            Call::create('getName')->with()->willReturn('user'),
            Call::create('getMethod')->with()->willReturn('GET'),
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
            Call::create('getName')->with()->willReturn('user'),
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
        ]);

        $router = new Router([$route]);
        $router->generateUrl($request, 'user', ['id' => 'a3bce0ca-2b7c-4fc6-8dad-ecdcc6907791']);
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
            Call::create('getName')->with()->willReturn('user'),
            Call::create('getMethod')->with()->willReturn('GET'),
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
            Call::create('getName')->with()->willReturn('user'),
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
        ]);

        $router = new Router([$route], null, '/path/to/directory');

        self::assertSame(
            'https://user:password@localhost/path/to/directory/user/1',
            $router->generateUrl($request, 'user', ['id' => 1])
        );
        self::assertSame(
            'https://user:password@localhost/path/to/directory/user/1?key=value',
            $router->generateUrl($request, 'user', ['id' => 1], ['key' => 'value'])
        );
        self::assertSame(
            'https://user:password@localhost/path/to/directory/user/1/sample',
            $router->generateUrl($request, 'user', ['id' => 1, 'name' => 'sample'])
        );
        self::assertSame(
            'https://user:password@localhost/path/to/directory/user/1/sample?key1=value1&key2=value2',
            $router->generateUrl(
                $request,
                'user',
                ['id' => 1, 'name' => 'sample'],
                ['key1' => 'value1', 'key2' => 'value2']
            )
        );
    }

    public function testGeneratePathWithMissingRoute(): void
    {
        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Missing route: "user"');
        $this->expectExceptionCode(1);

        $router = new Router([]);
        $router->generatePath('user', ['id' => 1]);
    }

    public function testGeneratePath(): void
    {
        /** @var MockObject|RouteInterface $route */
        $route = $this->getMockByCalls(RouteInterface::class, [
            Call::create('getName')->with()->willReturn('user'),
            Call::create('getMethod')->with()->willReturn('GET'),
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
            Call::create('getName')->with()->willReturn('user'),
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
        ]);

        $router = new Router([$route]);

        self::assertSame('/user/1', $router->generatePath('user', ['id' => 1]));
        self::assertSame('/user/1?key=value', $router->generatePath('user', ['id' => 1], ['key' => 'value']));
        self::assertSame('/user/1/sample', $router->generatePath('user', ['id' => 1, 'name' => 'sample']));
        self::assertSame(
            '/user/1/sample?key1=value1&key2=value2',
            $router->generatePath(
                'user',
                ['id' => 1, 'name' => 'sample'],
                ['key1' => 'value1', 'key2' => 'value2']
            )
        );
    }

    public function testGeneratePathWithMissingAttribute(): void
    {
        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Route generation for route "user" with path "/user/{id:\d+}[/{name}]" with attributes "{}" failed. Missing attribute "id"');

        /** @var MockObject|RouteInterface $route */
        $route = $this->getMockByCalls(RouteInterface::class, [
            Call::create('getName')->with()->willReturn('user'),
            Call::create('getMethod')->with()->willReturn('GET'),
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
            Call::create('getName')->with()->willReturn('user'),
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
        ]);

        $router = new Router([$route]);
        $router->generatePath('user');
    }

    public function testGeneratePathWithBasePath(): void
    {
        /** @var MockObject|RouteInterface $route */
        $route = $this->getMockByCalls(RouteInterface::class, [
            Call::create('getName')->with()->willReturn('user'),
            Call::create('getMethod')->with()->willReturn('GET'),
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
            Call::create('getName')->with()->willReturn('user'),
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
            Call::create('getPath')->with()->willReturn('/user/{id:\d+}[/{name}]'),
        ]);

        $router = new Router([$route], null, '/path/to/directory');

        self::assertSame('/path/to/directory/user/1', $router->generatePath('user', ['id' => 1]));
        self::assertSame(
            '/path/to/directory/user/1?key=value',
            $router->generatePath('user', ['id' => 1], ['key' => 'value'])
        );
        self::assertSame(
            '/path/to/directory/user/1/sample',
            $router->generatePath('user', ['id' => 1, 'name' => 'sample'])
        );
        self::assertSame(
            '/path/to/directory/user/1/sample?key1=value1&key2=value2',
            $router->generatePath(
                'user',
                ['id' => 1, 'name' => 'sample'],
                ['key1' => 'value1', 'key2' => 'value2']
            )
        );
    }
}
