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

    public function exec(Configuration $config): void
    {
        $this->config = $config;
        $this->initDriver();
        $this->directory = $this->config->baseDirectory . '/data/screenshots/' .
            $this->config->name . '/' . date('Y-m-d_H-i-s') . '/';

        foreach ($this->config->options['resolutions'] as $resolutionString) {
            $resolution = explode('x', $resolutionString);
            $this->driver->manage()->window()->setSize(
                new WebDriverDimension($resolution[0], $resolution[1])
            );

            foreach ($this->config->options['paths'] as $url) {
                $this->handleUrl($url, $resolutionString);
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
        $fullUrl = $this->config->options['domain'] . $url;
        $filePath = $this->directory . FileHelper::sanitizeFileName($url) . '_' . $resolutionString . '.png';

        echo 'Screenshotting (' . $resolutionString . ') ' . $fullUrl . PHP_EOL;
        try {
            $this->driver->get($fullUrl);

            if (!empty($this->config->options['cookie_banner_button']) && $this->urlCount === 0) {
                $this->clickCookieBanner();
            }
            $this->waitForVisibleImages();
            $this->driver->takeScreenshot($filePath);
            $this->urlCount++;
        } catch (Exception $e) {
            echo PHP_EOL . 'ERROR: Page could not be loaded: ' . $fullUrl . PHP_EOL .
                'Message: ' . $e->getMessage() . PHP_EOL . PHP_EOL;
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
}
