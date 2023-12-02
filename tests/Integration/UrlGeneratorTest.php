<?php

declare(strict_types=1);

namespace Chubbyphp\Tests\Framework\Router\FastRoute\Integration;

use Chubbyphp\Framework\RequestHandler\CallbackRequestHandler;
use Chubbyphp\Framework\Router\FastRoute\UrlGenerator;
use Chubbyphp\Framework\Router\Route;
use Chubbyphp\Framework\Router\RoutesByName;
use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 *
 * @internal
 */
final class UrlGeneratorTest extends TestCase
{
    public function testGeneratePath(): void
    {
        $route = Route::get('/hello/{name:[a-z]+}', 'hello', new CallbackRequestHandler(
            static function (): void {}
        ));

        $router = new UrlGenerator(new RoutesByName([$route]));

        self::assertSame(
            '/hello/world?key=value',
            $router->generatePath('hello', ['name' => 'world'], ['key' => 'value'])
        );
    }
}
