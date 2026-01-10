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

$VB_BASE_URI = getenv('VB_BASE_URI') ?: VictoriabankClient::TEST_BASE_URL;
$VB_MERCHANT_ID = getenv('VB_MERCHANT_ID');
$VB_TERMINAL_ID = getenv('VB_TERMINAL_ID');
$VB_MERCHANT_PRIVATE_KEY  = getenv('VB_MERCHANT_PRIVATE_KEY') ?: 'file://key.pem';
$VB_MERCHANT_PRIVATE_KEY_PASSPHRASE = getenv('VB_MERCHANT_PRIVATE_KEY_PASSPHRASE');
$VB_MERCHANT_PUBLIC_KEY = getenv('VB_MERCHANT_PUBLIC_KEY') ?: 'file://pubkey.pem';
$VB_BANK_PUBLIC_KEY = getenv('VB_BANK_PUBLIC_KEY') ?: 'file://victoria_pub.pem';
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

$vbClient
    ->setMerchantId($VB_MERCHANT_ID)
    ->setTerminalId($VB_TERMINAL_ID)
    ->setLanguage('en')
    ->setTimezone('Europe/Chisinau')
    ->setMerchantPrivateKey($VB_MERCHANT_PRIVATE_KEY, $VB_MERCHANT_PRIVATE_KEY_PASSPHRASE)
    ->setBankPublicKey($VB_BANK_PUBLIC_KEY)
    ->setSignatureAlgo(VictoriabankClient::P_SIGN_HASH_ALGO_SHA256)
    ->setBackRefUrl('https://www.example.com');
```

## SDK usage examples

### Generate payment authorization request form

```php
$authorizeRequest = $vbClient->generateOrderAuthorizeRequest(
    '123',
    123.45,
    'MDL',
    'Order #123',
    'TEST COMPANY SRL',
    'https://www.example.com',
    'Chisinau, Moldova',
    'example@example.com'
);

$html = $vbClient->generateHtmlForm($VB_BASE_URI, $authorizeRequest);
echo $html;
```

### Validate bank notification callback signature

```php
try {
    $isValid = $vbClient->validateResponse($_POST);
    if ($isValid) {
        // Payment authorized/completed successfully
        // Process the order (e.g. update status in DB)
    }
} catch (\Exception $e) {
    // Handle error (e.g. log error, invalid signature, duplicate transaction, declined)
    echo 'Error: ' . $e->getMessage();
}
```

### Complete authorized payment

```php
$rrn = '';
$int_ref = '';

$completeResponse = $vbClient->orderComplete(
    '123',
    123.45,
    'MDL',
    $rrn,
    $int_ref
);
```

### Refund payment

```php
$reverseResponse = $vbClient->orderReverse(
    '123',
    123.45,
    'MDL',
    $rrn,
    $int_ref
);
```

### Check order payment status

```php
$checkResponse = $vbClient->orderCheck('123', VictoriabankClient::TRTYPE_AUTHORIZATION);
```
