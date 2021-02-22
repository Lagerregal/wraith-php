<?php

namespace WraithPhp;

use Exception;
use Symfony\Component\Console\Application;
use WraithPhp\Controller\AbstractController;
use WraithPhp\Controller\CompareController;
use WraithPhp\Controller\JoinController;
use WraithPhp\Controller\ScreenshotController;

class TaskHandler
{
    protected static array $commands = [
        CompareController::class,
        JoinController::class,
        ScreenshotController::class,
    ];

    public static function createCommand(string $taskName): ?AbstractController
    {
        foreach (self::$commands as $command) {
            if ($command::getDefaultName() === $taskName) {
                return new $command();
            }
        }
        return null;
    }

    /**
     * @param string $baseDirectory
     * @param array $arguments
     * @throws Exception
     */
    public static function bootstrap(string $baseDirectory, array $arguments)
    {
        $application = new Application('PHP Wraith');
        $registeredCommands = [];
        foreach (self::$commands as $command) {
            /** @var AbstractController $commandObject */
            $commandObject = new $command();
            $registeredCommands[$commandObject->getName()] = $commandObject;
            $application->add($commandObject);
        }

        $taskName = empty($arguments[1]) ? '' : strtolower($arguments[1]) ;
        foreach ($registeredCommands as $commandName => $commandObject) {
            if ($commandName === $taskName && $commandObject instanceof AbstractController) {
                $config = Configuration::create($baseDirectory, $taskName, $arguments[2], $arguments);
                $commandObject->setConfig($config);
            }
        }

        $application->run();
    }
}
