<?php

namespace WraithPhp\Controller;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use WraithPhp\Configuration;

abstract class AbstractController extends Command
{
    protected Configuration $config;
    protected InputInterface $input;
    protected OutputInterface $output;

    protected function configure()
    {
        parent::configure();
        $this->addArgument('configName', InputArgument::REQUIRED, 'The name of your configuration in "configs/" directory (filename without ".yml")');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);
        $this->input = $input;
        $this->output = $output;
    }

    public function setConfig(Configuration $config): void
    {
        $this->config = $config;
    }
}
