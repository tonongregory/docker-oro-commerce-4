<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Debug\Debug;

/** @var \Composer\Autoload\ClassLoader $loader */
$loader = require __DIR__.'/../vendor/autoload.php';
$env = getenv('SYMFONY_ENV');

if ("dev" === $env) {
    // Need to trace all kind of errors
    error_reporting(-1);
    ini_set('display_errors', 'On');
    Debug::enable();
}

require_once __DIR__.'/../src/AppKernel.php';
//require_once __DIR__.'/../src/AppCache.php';

$kernel = new AppKernel($env, getenv('SYMFONY_DEBUG'));

//$kernel = new AppCache($kernel);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
