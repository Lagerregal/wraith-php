<?php
require __DIR__ . '/vendor/autoload.php';

$taskName = strtolower($argv[1]);
$configName = $argv[2];

$config = \WraithPhp\Configuration::create(__DIR__, $configName, $argv);

$controllers = [
    'screenshot' => \WraithPhp\Controller\ScreenshotController::class,
    'compare' => \WraithPhp\Controller\CompareController::class,
];

$controller = new $controllers[$taskName]();
$controller->exec($config);
