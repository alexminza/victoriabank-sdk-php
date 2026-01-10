# PHP SDK for Victoriabank API

![Victoriabank](https://repository-images.githubusercontent.com/1129513478/029d830d-e668-470d-9c9f-825ad74995bd)

* Victoriabank e-Commerce Gateway Instructions https://ecomt.victoriabank.md/cardop/images/instruction-ecom-vb.zip
* GitHub project https://github.com/alexminza/victoriabank-sdk-php
* Composer package https://packagist.org/packages/alexminza/victoriabank-sdk

## Installation
To easily install or upgrade to the latest release, use `composer`:
```shell
composer require alexminza/victoriabank-sdk
```

To enable logging add the `monolog` package:
```shell
composer require monolog/monolog
```

## Getting started
Import SDK:

```php
require_once __DIR__ . '/vendor/autoload.php';

use Victoriabank\Victoriabank\VictoriabankClient;
```

Add project configuration:

```php
$DEBUG = getenv('DEBUG');

$VB_BASE_URI = getenv('VB_BASE_URI');
$VB_MERCHANT_ID = getenv('VB_MERCHANT_ID');
$VB_TERMINAL_ID = getenv('VB_TERMINAL_ID');
$VB_MERCHANT_PRIVATE_KEY  = getenv('VB_MERCHANT_PRIVATE_KEY');
$VB_MERCHANT_PRIVATE_KEY_PASSPHRASE = getenv('VB_MERCHANT_PRIVATE_KEY_PASSPHRASE');
$VB_MERCHANT_PUBLIC_KEY = getenv('VB_MERCHANT_PUBLIC_KEY');
$VB_BANK_PUBLIC_KEY = getenv('VB_BANK_PUBLIC_KEY');
$VB_MERCHANT_PUBLIC_KEY = getenv('VB_MERCHANT_PUBLIC_KEY');
$VB_SIGNATURE_ALGO = getenv('VB_SIGNATURE_ALGO');
$VB_MERCHANT_NAME = getenv('VB_MERCHANT_NAME');
$VB_MERCHANT_URL = getenv('VB_MERCHANT_URL');
$VB_MERCHANT_ADDRESS = getenv('VB_MERCHANT_ADDRESS');
$VVB_BACKREF_URL = getenv('VVB_BACKREF_URL');
```

Initialize client:

```php
$options = [
    'base_uri' => $VB_BASE_URI,
    'timeout' => 30
];

if ($DEBUG) {
    $logName = 'victoriabank_guzzle';
    $logFileName = "$logName.log";

    $log = new \Monolog\Logger($logName);
    $log->pushHandler(new \Monolog\Handler\StreamHandler($logFileName, \Monolog\Logger::DEBUG));

    $stack = \GuzzleHttp\HandlerStack::create();
    $stack->push(\GuzzleHttp\Middleware::log($log, new \GuzzleHttp\MessageFormatter(\GuzzleHttp\MessageFormatter::DEBUG)));

    $options['handler'] = $stack;
}

$guzzleClient = new \GuzzleHttp\Client($options);
$vbClient = new VictoriabankClient($guzzleClient);
```
