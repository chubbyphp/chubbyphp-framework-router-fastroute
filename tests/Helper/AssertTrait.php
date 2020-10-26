<?php

declare(strict_types=1);

namespace Chubbyphp\Tests\Framework\Router\FastRoute\Helper;

use PHPUnit\Framework\Assert;

trait AssertTrait
{
    // @todo: remove when phpunit min version >= 9
    public static function assertFileDoesNotExist(string $directory, string $message = ''): void
    {
        if (!is_callable([Assert::class, 'assertFileDoesNotExist'])) {
            Assert::assertFileNotExists($directory, $message);

            return;
        }

        Assert::assertFileDoesNotExist($directory, $message);
    }
}
