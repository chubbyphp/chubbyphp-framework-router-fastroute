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
 * @covers \Chubbyphp\Framework\Router\FastRoute\Router
 *
 * @internal
 */
final class RouterTest extends TestCase
{
    use MockByCallsTrait;

    public const UUID_PATTERN = '[0-9a-f]{8}\b-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-\b[0-9a-f]{12}';

    public function testDeprecationWithinConstruct(): void
    {
        error_clear_last();

        new Router([]);

        $error = error_get_last();

        self::assertNotNull($error);

        self::assertSame(E_USER_DEPRECATED, $error['type']);
        self::assertSame(
            'Use Chubbyphp\Framework\Router\FastRoute\RouteMatcher|Chubbyphp\Framework\Router\FastRoute\UrlGenerator instead of instead of "Chubbyphp\Framework\Router\FastRoute\Router"',
            $error['message']
        );
    }

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
            Call::create('getName')->with()->willReturn('pet_create'),
            Call::create('getMethod')->with()->willReturn('POST'),
            Call::create('getPath')->with()->willReturn('/api/pets'),
            Call::create('getName')->with()->willReturn('pet_create'),
        ]);

        /** @var MockObject|RouteInterface $route2 */
        $route2 = $this->getMockByCalls(RouteInterface::class, [
            Call::create('getName')->with()->willReturn('pet_list'),
            Call::create('getMethod')->with()->willReturn('GET'),
            Call::create('getPath')->with()->willReturn('/api/pets'),
            Call::create('getName')->with()->willReturn('pet_list'),
            Call::create('withAttributes')->with([])->willReturnSelf(),
        ]);

        $cacheFile = sys_get_temp_dir().'/fast-route-'.uniqid().uniqid().'.php';

        self::assertFileDoesNotExist($cacheFile);

        $router = new Router([$route1, $route2], $cacheFile);

        self::assertFileExists($cacheFile);

        self::assertSame($route2, $router->match($request));

        unlink($cacheFile);
    }

    public function testMatchNotFound(): void
    {
        $this->expectException(RouterException::class);
        $this->expectExceptionMessage(
            'The page "/" you are looking for could not be found.'
                .' Check the address bar to ensure your URL is spelled correctly.'
        );
        $this->expectExceptionCode(404);

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
            Call::create('getName')->with()->willReturn('pet_list'),
            Call::create('getMethod')->with()->willReturn('GET'),
            Call::create('getPath')->with()->willReturn('/api/pets'),
            Call::create('getName')->with()->willReturn('pet_list'),
        ]);

        $router = new Router([$route]);
        $router->match($request);
    }

    public function testMatchMethodNotAllowed(): void
    {
        $this->expectException(RouterException::class);
        $this->expectExceptionMessage(
            'Method "POST" at path "/api/pets?offset=1&limit=20" is not allowed. Must be one of: "GET"'
        );
        $this->expectExceptionCode(405);

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
            Call::create('getName')->with()->willReturn('pet_list'),
            Call::create('getMethod')->with()->willReturn('GET'),
            Call::create('getPath')->with()->willReturn('/api/pets'),
            Call::create('getName')->with()->willReturn('pet_list'),
        ]);

        $router = new Router([$route]);
        $router->match($request);
    }

    public function testMatchWithTokensNotMatch(): void
    {
        $this->expectException(RouterException::class);
        $this->expectExceptionMessage(
            'The page "/api/pets/1" you are looking for could not be found.'
                .' Check the address bar to ensure your URL is spelled correctly.'
        );
        $this->expectExceptionCode(404);

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
            Call::create('getName')->with()->willReturn('pet_read'),
            Call::create('getMethod')->with()->willReturn('GET'),
            Call::create('getPath')->with()->willReturn('/api/pets/{id:'.self::UUID_PATTERN.'}'),
            Call::create('getName')->with()->willReturn('pet_read'),
        ]);

        $router = new Router([$route]);
        $router->match($request);
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
            Call::create('getName')->with()->willReturn('pet_read'),
            Call::create('getMethod')->with()->willReturn('GET'),
            Call::create('getPath')->with()->willReturn('/api/pets/{id:'.self::UUID_PATTERN.'}'),
            Call::create('getName')->with()->willReturn('pet_read'),
            Call::create('withAttributes')->with(['id' => '8b72750c-5306-416c-bba7-5b41f1c44791'])->willReturnSelf(),
        ]);

        $router = new Router([$route]);

        self::assertSame($route, $router->match($request));
    }

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
