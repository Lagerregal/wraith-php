<?php

namespace WraithPhp\Controller;

use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use WraithPhp\Helper\FileHelper;
use WraithPhp\Helper\PortLockHelper;

abstract class AbstractJobController extends AbstractController implements JobControllerInterface
{
    const JOB_PATHS = '/data/jobs/';

    protected string $jobId;
    protected string $jobDirectory;

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);
        $this->initJobId($this->generateJobId());
    }

    public function initJobId($jobId): void
    {
        $this->jobId = $jobId;
        $this->jobDirectory = $this->config->baseDirectory . static::JOB_PATHS . $this->jobId . '/';
    }

    public function setInputOutput(InputInterface $input, OutputInterface $output): void
    {
        $this->input = $input;
        $this->output = $output;
    }

    /**
     * This should be called by each JobController in function exec()
     */
    public function initJob(): void
    {
        mkdir($this->jobDirectory, 0777, true);
        $this->storeJobStatus('configuration', $this->config);
    }

    protected function setJobItems(string $key, array $jobItems)
    {
        $this->storeJobStatus($key, $jobItems, 'json');
    }

    protected function addJobItems(string $key, array $newJobItems)
    {
        $stream = PortLockHelper::lockSystemPort();

        $filename = $this->jobDirectory . FileHelper::sanitizeFileName($key) . '.json';
        $existingJobItems = json_decode(file_get_contents($filename), true);
        if ($existingJobItems === null) {
            throw new RuntimeException('Can not json_decode file: ' . $filename);
        }
        $allJobItems = array_merge($existingJobItems, $newJobItems);
        $this->storeJobStatus($key, $allJobItems, 'json');

        PortLockHelper::releaseSystemPort($stream);
    }

    protected function getNextJobItem(string $key): ?array
    {
        $stream = PortLockHelper::lockSystemPort();

        $nextJobItem = null;
        $filename = $this->jobDirectory . FileHelper::sanitizeFileName($key) . '.json';
        if (is_file($filename)) {
            $jobItems = json_decode(file_get_contents($filename), true);
            if ($jobItems === null) {
                throw new RuntimeException('Can not json_decode file: ' . $filename);
            }
            $nextJobItem = array_shift($jobItems);
            if ($nextJobItem === null) {
                unlink($filename);
            } else {
                // set job items again without the processed item
                $this->setJobItems($key, $jobItems);
            }
        }

        PortLockHelper::releaseSystemPort($stream);
        return $nextJobItem;
    }

    /**
     * This method is not thread-safe (no file locking)
     *
     * @param string $key
     * @param $status
     * @param string $format
     */
    protected function storeJobStatus(string $key, $status, string $format = 'data')
    {
        switch ($format) {
            case 'json':
                $status = json_encode($status, JSON_PRETTY_PRINT);
                break;
            case 'data':
            default:
                $status = serialize($status);
        }
        $filename = $this->jobDirectory . FileHelper::sanitizeFileName($key) . '.' . $format;
        file_put_contents($filename, $status);
    }

    protected function generateJobId(): string
    {
        return md5(microtime() . bin2hex(random_bytes(5)));
    }
}
