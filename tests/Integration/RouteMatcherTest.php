<?php

declare(strict_types=1);

namespace Chubbyphp\Tests\Framework\Router\FastRoute\Integration;

use Bitty\Http\ResponseFactory as BittyResponseFactory;
use Bitty\Http\ServerRequestFactory as BittyServerRequestFactory;
use Chubbyphp\Framework\Application;
use Chubbyphp\Framework\Middleware\ExceptionMiddleware;
use Chubbyphp\Framework\Middleware\RouteMatcherMiddleware;
use Chubbyphp\Framework\RequestHandler\CallbackRequestHandler;
use Chubbyphp\Framework\Router\FastRoute\RouteMatcher;
use Chubbyphp\Framework\Router\Route;
use Chubbyphp\Framework\Router\RoutesByName;
use Fig\Http\Message\RequestMethodInterface as RequestMethod;
use Http\Factory\Guzzle\ResponseFactory as GuzzleResponseFactory;
use Http\Factory\Guzzle\ServerRequestFactory as GuzzleServerRequestFactory;
use Laminas\Diactoros\ResponseFactory as LaminasResponseFactory;
use Laminas\Diactoros\ServerRequestFactory as LaminasServerRequestFactory;
use Nyholm\Psr7\Factory\Psr17Factory as NyholmResponseFactory;
use Nyholm\Psr7\Factory\Psr17Factory as NyholmServerRequestFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ResponseFactory as SlimResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory as SlimServerRequestFactory;
use Sunrise\Http\Message\ResponseFactory as SunriseResponseFactory;
use Sunrise\Http\ServerRequest\ServerRequestFactory as SunriseServerRequestFactory;

/**
 * @coversNothing
 *
 * @internal
 */
final class RouteMatcherTest extends TestCase
{
    public function providePsr7Implementations(): array
    {
        return [
            'bitty' => [
                'responseFactory' => new BittyResponseFactory(),
                'serverRequestFactory' => new BittyServerRequestFactory(),
            ],
            'guzzle' => [
                'responseFactory' => new GuzzleResponseFactory(),
                'serverRequestFactory' => new GuzzleServerRequestFactory(),
            ],
            'nyholm' => [
                'responseFactory' => new NyholmResponseFactory(),
                'serverRequestFactory' => new NyholmServerRequestFactory(),
            ],
            'slim' => [
                'responseFactory' => new SlimResponseFactory(),
                'serverRequestFactory' => new SlimServerRequestFactory(),
            ],
            'sunrise' => [
                'responseFactory' => new SunriseResponseFactory(),
                'serverRequestFactory' => new SunriseServerRequestFactory(),
            ],
            'zend' => [
                'responseFactory' => new LaminasResponseFactory(),
                'serverRequestFactory' => new LaminasServerRequestFactory(),
            ],
        ];
    }

    /**
     * @dataProvider providePsr7Implementations
     */
    public function testOk(
        ResponseFactoryInterface $responseFactory,
        ServerRequestFactoryInterface $serverRequestFactory
    ): void {
        $route = Route::get('/hello/{name:[a-z]+}', 'hello', new CallbackRequestHandler(
            static function (ServerRequestInterface $request) use ($responseFactory) {
                $name = $request->getAttribute('name');
                $response = $responseFactory->createResponse();
                $response->getBody()->write(sprintf('Hello, %s', $name));

                return $response;
            }
        ));

        $app = new Application([
            new ExceptionMiddleware($responseFactory, true),
            new RouteMatcherMiddleware(new RouteMatcher(new RoutesByName([$route])), $responseFactory),
        ]);

        $request = $serverRequestFactory->createServerRequest(
            RequestMethod::METHOD_GET,
            '/hello/test'
        );

        $response = $app->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Hello, test', (string) $response->getBody());
    }

    /**
     * @dataProvider providePsr7Implementations
     */
    public function testTestNotFound(
        ResponseFactoryInterface $responseFactory,
        ServerRequestFactoryInterface $serverRequestFactory
    ): void {
        $route = Route::get('/hello/{name:[a-z]+}', 'hello', new CallbackRequestHandler(
            static function (ServerRequestInterface $request) use ($responseFactory) {
                $name = $request->getAttribute('name');
                $response = $responseFactory->createResponse();
                $response->getBody()->write(sprintf('Hello, %s', $name));

                return $response;
            }
        ));

        $app = new Application([
            new ExceptionMiddleware($responseFactory, true),
            new RouteMatcherMiddleware(new RouteMatcher(new RoutesByName([$route])), $responseFactory),
        ]);

        $request = $serverRequestFactory->createServerRequest(
            RequestMethod::METHOD_GET,
            '/hello'
        );

        $response = $app->handle($request);

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString(
            'The page "/hello" you are looking for could not be found.',
            (string) $response->getBody()
        );
    }

    /**
     * @dataProvider providePsr7Implementations
     */
    public function testMethodNotAllowed(
        ResponseFactoryInterface $responseFactory,
        ServerRequestFactoryInterface $serverRequestFactory
    ): void {
        $route = Route::get('/hello/{name:[a-z]+}', 'hello', new CallbackRequestHandler(
            static function (ServerRequestInterface $request) use ($responseFactory) {
                $name = $request->getAttribute('name');
                $response = $responseFactory->createResponse();
                $response->getBody()->write(sprintf('Hello, %s', $name));

                return $response;
            }
        ));

        $app = new Application([
            new ExceptionMiddleware($responseFactory, true),
            new RouteMatcherMiddleware(new RouteMatcher(new RoutesByName([$route])), $responseFactory),
        ]);

        $request = $serverRequestFactory->createServerRequest(
            RequestMethod::METHOD_POST,
            '/hello/test'
        );

        $response = $app->handle($request);

        self::assertSame(405, $response->getStatusCode());
        self::assertStringContainsString(
            'Method "POST" at path "/hello/test" is not allowed.',
            (string) $response->getBody()
        );
    }

    /**
     * @dataProvider providePsr7Implementations
     */
    public function testException(
        ResponseFactoryInterface $responseFactory,
        ServerRequestFactoryInterface $serverRequestFactory
    ): void {
        $route = Route::get('/hello/{name:[a-z]+}', 'hello', new CallbackRequestHandler(
            static function (): void {
                throw new \RuntimeException('Something went wrong');
            }
        ));

        $app = new Application([
            new ExceptionMiddleware($responseFactory, true),
            new RouteMatcherMiddleware(new RouteMatcher(new RoutesByName([$route])), $responseFactory),
        ]);

        $request = $serverRequestFactory->createServerRequest(
            RequestMethod::METHOD_GET,
            '/hello/test'
        );

        $response = $app->handle($request);

        self::assertSame(500, $response->getStatusCode());

        $body = (string) $response->getBody();

        self::assertStringContainsString('RuntimeException', $body);
        self::assertStringContainsString('Something went wrong', $body);
    }

    /**
     * @dataProvider providePsr7Implementations
     */
    public function testExceptionWithoutExceptionMiddleware(
        ResponseFactoryInterface $responseFactory,
        ServerRequestFactoryInterface $serverRequestFactory
    ): void {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Something went wrong');

        $route = Route::get('/hello/{name:[a-z]+}', 'hello', new CallbackRequestHandler(
            static function (): void {
                throw new \RuntimeException('Something went wrong');
            }
        ));

        $app = new Application([new RouteMatcherMiddleware(new RouteMatcher(new RoutesByName([$route])), $responseFactory),
        ]);

        $request = $serverRequestFactory->createServerRequest(
            RequestMethod::METHOD_GET,
            '/hello/test'
        );

        $app->handle($request);
    }
}
