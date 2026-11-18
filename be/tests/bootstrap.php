<?php

declare(strict_types=1);

// Tests are found by PHPUnit as files, but the integration tests also share a base class, which has
// to be loadable by name. Registering the namespace here keeps vendor/ (which is committed) unchanged.
/** @var \Composer\Autoload\ClassLoader $loader */
$loader = require __DIR__ . '/../vendor/autoload.php';
$loader->addPsr4('Tests\\', __DIR__);
