<?php

namespace WraithPhp\Controller;

use Exception;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverDimension;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverPoint;
use WraithPhp\Configuration;
use WraithPhp\Helper\FileHelper;

class ScreenshotController implements ControllerInterface
{
    protected string $directory;
    protected RemoteWebDriver $driver;
    protected Configuration $config;
    protected int $urlCount = 0;
    protected array $currentResolutionPaths;

    public function exec(Configuration $config): void
    {
        $this->config = $config;
        $this->initDriver();
        $this->directory = $this->config->baseDirectory . '/data/screenshots/' .
            $this->config->name . '/' . date('Y-m-d_H-i-s') . '/';

        foreach ($this->config->options['resolutions'] as $resolutionString) {
            $this->currentResolutionPaths = $this->config->paths;
            $resolution = explode('x', $resolutionString);
            $this->driver->manage()->window()->setSize(
                new WebDriverDimension($resolution[0], $resolution[1])
            );

            while($nextUrl = array_shift($this->currentResolutionPaths)) {
                $this->handleUrl($nextUrl, $resolutionString);
            }
        }

        $this->driver->close();
    }

    protected function initDriver(): void
    {
        $serverUrl = 'http://localhost:4444';
        $options = new ChromeOptions();
        $options->addArguments(['headless']);
        $desiredCapabilities = DesiredCapabilities::chrome();
        $desiredCapabilities->setCapability(ChromeOptions::CAPABILITY, $options);
        $this->driver = RemoteWebDriver::create($serverUrl, $desiredCapabilities);
        $this->driver->manage()->window()->setPosition(new WebDriverPoint(0, 0));
        $this->urlCount = 0;
    }

    protected function handleUrl(string $url, string $resolutionString): void
    {
        if ($this->isUrlExcluded($url)) {
            echo 'Ignoring url: ' . $url . PHP_EOL;
            return;
        }

        $fullUrl = $this->config->options['domain'] . $url;
        $filePath = $this->directory . FileHelper::sanitizeFileName($url) . '_' . $resolutionString . '.png';

        echo 'Screenshotting (' . $resolutionString . ') ' . $fullUrl . PHP_EOL;
        try {
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
            echo PHP_EOL . 'ERROR: Page could not be loaded: ' . $fullUrl . PHP_EOL .
                'Message: ' . $e->getMessage() . PHP_EOL . PHP_EOL;
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
                if ( // check host
                    !empty($parsedFoundUrl['host']) &&
                    $parsedSourceUrl['host'] === $parsedFoundUrl['host']
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
                            echo 'Found new URL: ' . $newFoundUrl . PHP_EOL;

                            // store new path
                            $this->currentResolutionPaths[] = $newFoundUrl;
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
            echo 'WARNING: Element cookie_banner_button not found (' .
                $this->config->options['cookie_banner_button'] . ') ' . PHP_EOL;
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
            echo 'WARNING: Element img not found' . PHP_EOL;
        }
    }

    protected function isUrlExcluded(string $relativeUrl): bool
    {
        // check excluded paths first
        if (is_array($this->config->options['exclude_paths']['ends_with'])) {
            foreach ($this->config->options['exclude_paths']['ends_with'] as $fileEnding) {
                if (substr($relativeUrl, 0 - strlen($fileEnding)) === $fileEnding) {
                    return true;
                }
            }
        }

        $excluded = false;
        if (is_array($this->config->options['include_paths']['starts_with'])
            && count($this->config->options['include_paths']['starts_with']) > 0) {
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
