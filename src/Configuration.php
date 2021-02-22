<?php

namespace WraithPhp;

use Symfony\Component\Yaml\Yaml;
use WraithPhp\Helper\PortLockHelper;

class Configuration
{
    public string $taskName;
    public string $configName;
    public string $baseDirectory;
    public array $options;
    public array $paths;
    public array $arguments;

    public static function create(string $baseDirectory, string $taskName, string $configName, array $arguments): Configuration
    {
        $config = new Configuration();
        $config->taskName = $taskName;
        $config->configName = $configName;
        $config->baseDirectory = $baseDirectory;
        $config->arguments = $arguments;
        $config->options = Yaml::parseFile($config->baseDirectory . '/configs/' . $configName . '.yml');
        $pathsFilePath = $config->getPathsFilePath();
        $config->paths = is_file($pathsFilePath) ? Yaml::parseFile($pathsFilePath) : [];
        if (!empty($config->paths['paths'])) {
            $config->paths = $config->paths['paths'];
        } else {
            $config->paths = ['/'];
            if (!empty($config->options['include_paths']['starts_with']) &&
                is_array($config->options['include_paths']['starts_with'])) {
                $config->paths = array_merge($config->paths, $config->options['include_paths']['starts_with']);
            }
        }
        return $config;
    }

    public function storePaths(): void
    {
        $stream = PortLockHelper::lockSystemPort();

        sort($this->paths);
        $yaml = Yaml::dump(['paths' => $this->paths]);
        file_put_contents($this->getPathsFilePath(), $yaml);

        PortLockHelper::releaseSystemPort($stream);
    }

    public function getPathsFilePath(): string
    {
        return $pathsFilePath = $this->baseDirectory . '/configs/' . $this->configName . '.paths.yml';
    }
}
