# chubbyphp-framework-router-fastroute

[![Build Status](https://api.travis-ci.org/chubbyphp/chubbyphp-framework-router-fastroute.png?branch=master)](https://travis-ci.org/chubbyphp/chubbyphp-framework-router-fastroute)
[![Coverage Status](https://coveralls.io/repos/github/chubbyphp/chubbyphp-framework-router-fastroute/badge.svg?branch=master)](https://coveralls.io/github/chubbyphp/chubbyphp-framework-router-fastroute?branch=master)
[![Infection MSI](https://badge.stryker-mutator.io/github.com/chubbyphp/chubbyphp-framework-router-fastroute/master)](https://travis-ci.org/chubbyphp/chubbyphp-framework-router-fastroute)
[![Latest Stable Version](https://poser.pugx.org/chubbyphp/chubbyphp-framework-router-fastroute/v/stable.png)](https://packagist.org/packages/chubbyphp/chubbyphp-framework-router-fastroute)
[![Total Downloads](https://poser.pugx.org/chubbyphp/chubbyphp-framework-router-fastroute/downloads.png)](https://packagist.org/packages/chubbyphp/chubbyphp-framework-router-fastroute)
[![Monthly Downloads](https://poser.pugx.org/chubbyphp/chubbyphp-framework-router-fastroute/d/monthly)](https://packagist.org/packages/chubbyphp/chubbyphp-framework-router-fastroute)

[![bugs](https://sonarcloud.io/api/project_badges/measure?project=chubbyphp_chubbyphp-framework-router-fastroute&metric=bugs)](https://sonarcloud.io/dashboard?id=chubbyphp_chubbyphp-framework-router-fastroute)
[![code_smells](https://sonarcloud.io/api/project_badges/measure?project=chubbyphp_chubbyphp-framework-router-fastroute&metric=code_smells)](https://sonarcloud.io/dashboard?id=chubbyphp_chubbyphp-framework-router-fastroute)
[![coverage](https://sonarcloud.io/api/project_badges/measure?project=chubbyphp_chubbyphp-framework-router-fastroute&metric=coverage)](https://sonarcloud.io/dashboard?id=chubbyphp_chubbyphp-framework-router-fastroute)
[![duplicated_lines_density](https://sonarcloud.io/api/project_badges/measure?project=chubbyphp_chubbyphp-framework-router-fastroute&metric=duplicated_lines_density)](https://sonarcloud.io/dashboard?id=chubbyphp_chubbyphp-framework-router-fastroute)
[![ncloc](https://sonarcloud.io/api/project_badges/measure?project=chubbyphp_chubbyphp-framework-router-fastroute&metric=ncloc)](https://sonarcloud.io/dashboard?id=chubbyphp_chubbyphp-framework-router-fastroute)
[![sqale_rating](https://sonarcloud.io/api/project_badges/measure?project=chubbyphp_chubbyphp-framework-router-fastroute&metric=sqale_rating)](https://sonarcloud.io/dashboard?id=chubbyphp_chubbyphp-framework-router-fastroute)
[![alert_status](https://sonarcloud.io/api/project_badges/measure?project=chubbyphp_chubbyphp-framework-router-fastroute&metric=alert_status)](https://sonarcloud.io/dashboard?id=chubbyphp_chubbyphp-framework-router-fastroute)
[![reliability_rating](https://sonarcloud.io/api/project_badges/measure?project=chubbyphp_chubbyphp-framework-router-fastroute&metric=reliability_rating)](https://sonarcloud.io/dashboard?id=chubbyphp_chubbyphp-framework-router-fastroute)
[![security_rating](https://sonarcloud.io/api/project_badges/measure?project=chubbyphp_chubbyphp-framework-router-fastroute&metric=security_rating)](https://sonarcloud.io/dashboard?id=chubbyphp_chubbyphp-framework-router-fastroute)
[![sqale_index](https://sonarcloud.io/api/project_badges/measure?project=chubbyphp_chubbyphp-framework-router-fastroute&metric=sqale_index)](https://sonarcloud.io/dashboard?id=chubbyphp_chubbyphp-framework-router-fastroute)
[![vulnerabilities](https://sonarcloud.io/api/project_badges/measure?project=chubbyphp_chubbyphp-framework-router-fastroute&metric=vulnerabilities)](https://sonarcloud.io/dashboard?id=chubbyphp_chubbyphp-framework-router-fastroute)

## Description

Fastroute Router implementation for [chubbyphp-framework][1].

## Requirements

 * php: ^7.4|^8.0
 * [chubbyphp/chubbyphp-framework][1]: ^3.2
 * [nikic/fast-route][2]: ^1.0|^0.6

## Installation

Through [Composer](http://getcomposer.org) as [chubbyphp/chubbyphp-framework-router-fastroute][10].

```bash
composer require chubbyphp/chubbyphp-framework-router-fastroute "^1.1"
```

## Usage

```php
<?php

declare(strict_types=1);

namespace App;

use Chubbyphp\Framework\Application;
use Chubbyphp\Framework\ErrorHandler;
use Chubbyphp\Framework\Middleware\ExceptionMiddleware;
use Chubbyphp\Framework\Middleware\RouterMiddleware;
use Chubbyphp\Framework\RequestHandler\CallbackRequestHandler;
use Chubbyphp\Framework\Router\FastRoute\Router;
use Chubbyphp\Framework\Router\Route;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

$loader = require __DIR__.'/vendor/autoload.php';

set_error_handler([new ErrorHandler(), 'errorToException']);

$responseFactory = new ResponseFactory();

$app = new Application([
    new ExceptionMiddleware($responseFactory, true),
    new RouterMiddleware(new Router([
        Route::get('/hello/{name:[a-z]+}', 'hello', new CallbackRequestHandler(
            function (ServerRequestInterface $request) use ($responseFactory) {
                $name = $request->getAttribute('name');
                $response = $responseFactory->createResponse();
                $response->getBody()->write(sprintf('Hello, %s', $name));

                return $response;
            }
        ))
    ]), $responseFactory),
]);

$app->emit($app->handle((new ServerRequestFactory())->createFromGlobals()));
```

## Copyright

Dominik Zogg 2020

[1]: https://packagist.org/packages/chubbyphp/chubbyphp-framework
[2]: https://packagist.org/packages/nikic/fast-route
[10]: https://packagist.org/packages/chubbyphp/chubbyphp-framework-router-fastroute
