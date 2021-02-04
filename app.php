<?php
require __DIR__ . '/vendor/autoload.php';

/**
 * 1.
 * Run browser on port 4444:
 * bin/chromedriver --port=4444
 *
 *
 * 2.
 * Call task with config:
 * php app.php {{task}} {{config}}
 *
 * Example:
 * php app.php screenshot example
 * php app.php compare example 2021-02-04_00-05-35 2021-02-04_00-38-37
 */

$taskName = strtolower($argv[1]);
$configName = $argv[2];

$config = \WraithPhp\Configuration::create(__DIR__, $configName, $argv);

$controllers = [
    'screenshot' => \WraithPhp\Controller\ScreenshotController::class,
    'compare' => \WraithPhp\Controller\CompareController::class,
];

$controller = new $controllers[$taskName]();
$controller->exec($config);
