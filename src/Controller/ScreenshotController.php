<?php

namespace WraithPhp\Controller;

use Exception;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Exception\WebDriverCurlException;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverDimension;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverPoint;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use WraithPhp\Helper\FileHelper;

class ScreenshotController extends AbstractJobController
{
    protected static $defaultName = 'screenshot';
    protected string $directory;
    protected RemoteWebDriver $driver;
    protected int $urlCount = 0;

    protected function configure()
    {
        parent::configure();
        $this->addArgument('threads', InputArgument::OPTIONAL, 'The amount of parallel workers for this job', 1);
        $this->setDescription('Take screenshots of your website');
    }

    public function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        // if it's not a join command set directory name
        if (empty($this->config->options['runtime'])) {
            $directory = $this->config->baseDirectory . '/data/screenshots/' .
                $this->config->configName . '/' . date('Y-m-d_H-i-s') . '/';
            $this->config->options['runtime'] = ['directory' => $directory];
        }
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output->writeln([
            '<info>Start screenshot</info>',
            '<info>Job ID: ' . $this->jobId . '</info>',
        ]);
        $this->initJob();
        $this->initPathJobs();

        // start threads
        $threads = (int)$this->input->getArgument('threads');
        $threads = $threads > 0 ? $threads : 1;

        if ($threads > 1) {
            $command = 'php app.php join ' . $this->config->configName . ' ' . $this->jobId . ' 1> /dev/null &';
            for ($i = 1; $i < $threads; $i++) {
                $freeMemory = 0;
                $output = '';
                exec('free -m | awk \'/Mem:/{print $4}\'', $output);
                if(!empty($output[0])) {
                    $freeMemory = (int)$output[0];
                }
                if ($freeMemory < 500) {
                    $this->output->writeln('<info>Stopped creating threads: Only ' . $freeMemory . 'MB free memory left</info>');
                    break;
                }

                $this->output->writeln('Starting thread ' . $i);
                exec($command);
                sleep(2);
            }
            $this->output->writeln('Starting thread ' . $i);
        }

        $this->continueJob();
        return Command::SUCCESS;
    }

    public function continueJob(): void
    {
        $this->directory = $this->config->options['runtime']['directory'];
        $this->initDriver();
        while($nextUrlJob = $this->getNextJobItem('paths')) {
            $this->handleUrl($nextUrlJob['url'], $nextUrlJob['resolution']);
        }
        $this->driver->close();
    }

    protected function initPathJobs(): void
    {
        $pathsToScreenshot = [];
        foreach ($this->config->options['resolutions'] as $resolutionString) {
            $knownPaths = $this->config->paths;
            while($nextUrl = array_shift($knownPaths)) {
                $pathsToScreenshot[] = [
                    'resolution' => $resolutionString,
                    'url' => $nextUrl,
                ];
            }
        }
        $this->setJobItems('paths', $pathsToScreenshot);
    }

    protected function initDriver(): void
    {
        $serverUrl = $this->config->options['chromedriver']['server_url'] ?? 'http://localhost:4444';
        $options = new ChromeOptions();
        $options->addArguments(['headless']);
        $options->setExperimentalOption('prefs', [
            'download.prompt_for_download' => true,
            'download.default_directory' => '/dev/null',
            'download_restrictions' => 3,
        ]);
        $desiredCapabilities = DesiredCapabilities::chrome();
        $desiredCapabilities->setCapability(ChromeOptions::CAPABILITY, $options);
        try {
            $this->driver = RemoteWebDriver::create($serverUrl, $desiredCapabilities);
        } catch (WebDriverCurlException $e) {
            $autostart = $this->config->options['chromedriver']['autostart'] ?? false;
            if ($autostart) {
                $startCommand = $this->config->options['chromedriver']['commands']['start'] ?? 'bin/chromedriver --port=4444';
                if (substr($startCommand, 0, 1) !== '/') {
                    $startCommand = $this->config->baseDirectory . '/' . $startCommand;
                }
                $this->output->writeln([
                    'Starting chromedriver...',
                    $startCommand,
                    '',
                ]);
                exec($startCommand . ' > /dev/null &');
                sleep(3);
                $this->driver = RemoteWebDriver::create($serverUrl, $desiredCapabilities);
            } else {
                throw new RuntimeException('Could not connect to chromedriver (autostart disabled): ' . $serverUrl);
            }
        }
        $this->driver->manage()->window()->setPosition(new WebDriverPoint(0, 0));
        $this->urlCount = 0;
    }

    protected function handleUrl(string $url, string $resolutionString): void
    {
        if ($this->isUrlExcluded($url)) {
            $this->output->writeln('Ignoring url: ' . $url);
            return;
        }

        $fullUrl = $this->config->options['domain'] . $url;
        $filePath = $this->directory . FileHelper::sanitizeFileName($url, 180) . '_' . $resolutionString . '.png';

        $this->output->writeln('Screenshotting (' . $resolutionString . ') ' . $fullUrl);
        try {
            $resolution = explode('x', $resolutionString);
            $this->driver->manage()->window()->setSize(
                new WebDriverDimension($resolution[0], $resolution[1])
            );
            $this->driver->get($fullUrl);

            if (!empty($this->config->options['cookie_banner_button']) && $this->urlCount === 0) {
                $this->clickCookieBanner();
            }
            $this->waitForVisibleImages();
            if (!empty($this->config->options['crawl_domain_for_new_paths']) &&
                $this->config->options['crawl_domain_for_new_paths'] === true) {
                $this->addLinksToPaths($fullUrl);
            }
            $this->driver->takeScreenshot($filePath);
            $this->urlCount++;
        } catch (Exception $e) {
            $this->output->writeln('<error>' . PHP_EOL . 'ERROR: Page could not be loaded: ' . $fullUrl . PHP_EOL .
                'Message: ' . $e->getMessage() . '</error>' . PHP_EOL);
        }
    }

    protected function addLinksToPaths(string $sourceUrl): void
    {
        $parsedSourceUrl = parse_url($sourceUrl);
        if (count($this->driver->findElements(WebDriverBy::xpath("//a[@href]"))) != 0){
            $links = $this->driver->findElements(WebDriverBy::xpath('//a[@href]'));
            foreach ($links as $link) {
                $foundUrl = $link->getAttribute('href');
                $parsedFoundUrl = parse_url($foundUrl);
                if ( // check host - this should maybe be configurable?
                    (   // check if it's a relative link starting with a slash to avoid crawling javascript links
                        !isset($parsedFoundUrl['host']) &&
                        !empty($parsedFoundUrl['path']) &&
                        substr($parsedFoundUrl['path'], 0, 1) === '/'
                    )
                    ||
                    (   // check if it's on the same domain
                        !empty($parsedFoundUrl['host']) &&
                        $parsedSourceUrl['host'] === $parsedFoundUrl['host']
                    )
                ) {
                    if ( // check port
                        (empty($parsedSourceUrl['port']) && empty($parsedFoundUrl['port'])) ||
                        $parsedSourceUrl['port'] === $parsedFoundUrl['port']
                    ) {
                        $path     = isset($parsedFoundUrl['path']) ? $parsedFoundUrl['path'] : '';
                        $query    = isset($parsedFoundUrl['query']) ? '?' . $parsedFoundUrl['query'] : '';
                        $fragment = isset($parsedFoundUrl['fragment']) ? '#' . $parsedFoundUrl['fragment'] : '';
                        $newFoundUrl = $path.$query.$fragment;
                        if (!in_array($newFoundUrl, $this->config->paths) && !$this->isUrlExcluded($newFoundUrl)) {
                            $this->output->writeln('Found new URL: ' . $newFoundUrl);

                            // store new paths
                            $pathsToScreenshot = [];
                            foreach ($this->config->options['resolutions'] as $resolutionString) {
                                $pathsToScreenshot[] = [
                                    'resolution' => $resolutionString,
                                    'url' => $newFoundUrl,
                                ];
                            }
                            $this->addJobItems('paths', $pathsToScreenshot);

                            $this->config->paths[] = $newFoundUrl;
                            $this->config->storePaths();
                        }
                    }
                }
            }
        }
    }

    protected function clickCookieBanner(): void
    {
        // let's close the cookie hint if available
        $button = WebDriverBy::cssSelector($this->config->options['cookie_banner_button']);
        try {
            $this->driver->wait(15)
                ->until(WebDriverExpectedCondition::elementToBeClickable($button));
            $this->driver->findElement($button)->click();
        } catch (Exception $e) {
            $this->output->writeln('<info>WARNING: Element cookie_banner_button not found (' .
                $this->config->options['cookie_banner_button'] . ')</info>');
        }
    }

    protected function waitForVisibleImages(): void
    {
        // let's wait for images
        try {
            $images = WebDriverBy::cssSelector('img');
            $this->driver->wait(15, 2000)
                ->until(WebDriverExpectedCondition::visibilityOfAnyElementLocated($images));
        } catch (Exception $e) {
            $this->output->writeln('<info>WARNING: Element img not found</info>');
        }
    }

    protected function isUrlExcluded(string $relativeUrl): bool
    {
        // check excluded paths first
        if (!empty($this->config->options['exclude_paths']['ends_with']) &&
            is_array($this->config->options['exclude_paths']['ends_with'])) {
            foreach ($this->config->options['exclude_paths']['ends_with'] as $fileEnding) {
                if (substr($relativeUrl, 0 - strlen($fileEnding)) === $fileEnding) {
                    return true;
                }
            }
        }
        if (!empty($this->config->options['exclude_paths']['contains']) &&
            is_array($this->config->options['exclude_paths']['contains'])) {
            foreach ($this->config->options['exclude_paths']['contains'] as $pathPart) {
                if (strpos($relativeUrl, $pathPart) !== false) {
                    return true;
                }
            }
        }

        $excluded = false;
        if (!empty($this->config->options['include_paths']['starts_with']) &&
            is_array($this->config->options['include_paths']['starts_with']) &&
            count($this->config->options['include_paths']['starts_with']) > 0) {
            $excluded = true;
            foreach ($this->config->options['include_paths']['starts_with'] as $pathStart) {
                if (substr($relativeUrl, 0, strlen($pathStart)) === $pathStart) {
                    $excluded = false;
                    break;
                }
            }
        }
        return $excluded;
    }
}
