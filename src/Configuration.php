<?php

namespace WraithPhp;

use Symfony\Component\Yaml\Yaml;

class Configuration
{
    public string $name;
    public string $baseDirectory;
    public array $options;
    public array $arguments;

    public static function create(string $baseDirectory, string $name, array $arguments): Configuration
    {
        $config = new Configuration();
        $config->name = $name;
        $config->baseDirectory = $baseDirectory;
        $config->arguments = $arguments;
        $config->options = Yaml::parseFile($config->baseDirectory . '/configs/' . $name . '.yml');
        return $config;
    }
}
