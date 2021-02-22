<?php

namespace WraithPhp\Controller;

use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use WraithPhp\Helper\FileHelper;
use WraithPhp\TaskHandler;

class JoinController extends AbstractController
{
    protected static $defaultName = 'join';

    protected function configure()
    {
        parent::configure();
        $this->addArgument(
            'jobId',
            InputArgument::REQUIRED,
            'ID of the job to join (see "' . ltrim(AbstractJobController::JOB_PATHS, '/') . '" directory)'
        );
        $this->setDescription('Join a started command with a new worker');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $jobId = $this->input->getArgument('jobId');
        $jobDirectory = $this->config->baseDirectory . AbstractJobController::JOB_PATHS . $jobId . '/';
        if (!is_dir($jobDirectory)) {
            throw new RuntimeException('Job not found');
        }

        // load job config
        $filename = $jobDirectory . FileHelper::sanitizeFileName('configuration') . '.data';
        $jobConfig = unserialize(file_get_contents($filename));

        // create controller and continue
        $controller = TaskHandler::createCommand($jobConfig->taskName);
        if ($controller instanceof JobControllerInterface) {
            $controller->setConfig($jobConfig);
            $controller->setInputOutput($input, $output);
            $controller->initJobId($jobId);
            $controller->continueJob();
        } else {
            throw new RuntimeException('Controller "' . $jobConfig->taskName . '" is not a JobController');
        }
        return Command::SUCCESS;
    }
}
