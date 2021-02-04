[![Packagist Release](https://img.shields.io/packagist/v/different-technology/wraith-php.svg?style=flat-square)](https://packagist.org/packages/different-technology/wraith-php)
[![Packagist Downloads](https://img.shields.io/packagist/dt/different-technology/wraith-php.svg?style=flat-square)](https://packagist.org/packages/different-technology/wraith-php)
[![GitHub License](https://img.shields.io/github/license/different-technology/wraith-php.svg?style=flat-square)](https://github.com/different-technology/wraith-php/blob/main/LICENSE)

# PHP Wraith

PHP Wraith is a website crawler & screenshot comparison tool using Selenium - written in PHP.

This tool is based on the ideas of [bbc/wraith](https://github.com/bbc/wraith).


## Installation

### Prerequisites
System requirements:
* PHP version (> 7.4) with PHP extensions `imagick` and `json` (see [composer.json](../main/composer.json))
* [Composer](https://getcomposer.org/)

### Install sources

**Either** install this package via composer:
```bash
composer require different-technology/wraith-php
```

**Or** clone the code from GitHub:
```bash
git clone git@github.com:different-technology/wraith-php.git
cd wraith-php
composer install
```

### Chromedriver
Download the chromedriver for your Chrome version: https://chromedriver.chromium.org/downloads

Place the chromedriver here (optional): `bin/chromedriver`


## Setup

Configure your website in your own YAML config file in `/configs`.

See example in [/configs/example.yml](../main/configs/example.yml)



## Start

### Run chromedriver
The `chromedriver` has to run on port `4444` while executing the commands.

Start the chromedriver
```bash
bin/chromedriver --port=4444
```

### Screenshots

Call the `screenshot` command with your config name (file name without `.yml` extension).
```bash
php app.php screenshot {{your-config-name}}
```
The results are store in the directory `/data/screenshots/{{your-config-name}}/{{current-date-time}}`.

Feel free to rename the last directory name from `{{current-date-time}}` to something meaningful.

#### Example:
```bash
# Take screenshots
php app.php screenshot example
# Rename screenshots to "before-update"
mv data/screenshots/example/2021-02-04_10-10-55 data/screenshots/example/before-update
```


### Compare

Call the `compare` command with your config name (file name without `.yml` extension).

Provide the two directory names to compare.

```bash
php app.php compare {{your-config-name}} {{directory1}} {{directory2}}
```

#### Example:
```bash
php app.php compare example 2021-02-04_00-05-35 2021-02-04_00-38-37
```

### Show results

Open this file in your browser to see the results: `public/index.html`
