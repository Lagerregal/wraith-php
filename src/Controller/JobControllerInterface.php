<?php

namespace WraithPhp\Controller;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

interface JobControllerInterface
{
    public function continueJob(): void;

    public function initJobId(string $jobId): void;

    public function setInputOutput(InputInterface $input, OutputInterface $output): void;
}
