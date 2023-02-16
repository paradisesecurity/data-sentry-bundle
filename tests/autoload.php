<?php

declare(strict_types=1);

include_once __DIR__.'/../vendor/autoload.php';

$classLoader = new \Composer\Autoload\ClassLoader();
$classLoader->addPsr4("ParadiseSecurity\\Component\\DataSentry\\Test\\", __DIR__.'/../vendor/paradisesecurity/data-sentry/tests/Test/', true);
$classLoader->register();
