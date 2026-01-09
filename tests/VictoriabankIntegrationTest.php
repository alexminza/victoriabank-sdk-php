<?php

namespace Victoriabank\Victoriabank\Tests;

use Victoriabank\Victoriabank\VictoriabankClient;
use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;

/**
 * @group integration
 */
class VictoriabankIntegrationTest extends TestCase
{
    protected static $terminal_id;
    protected static $merchant_id;

    protected static $merchant_private_key;
    protected static $bank_public_key;
    protected static $signature_algo;

    protected static $merchant_name;
    protected static $merchant_url;
    protected static $merchant_address;
    protected static $backref_url;

    protected static $baseUrl;

    // Shared state
    protected static $authorize_data;

    protected static $trans_id;
    protected static $rrn;
    protected static $int_ref;

    /**
     * @var VictoriabankClient
     */
    protected $client;

    public static function setUpBeforeClass(): void
    {
        self::$merchant_id = getenv('VICTORIABANK_MERCHANT_ID');
        self::$terminal_id = getenv('VICTORIABANK_TERMINAL_ID');

        self::$merchant_private_key = getenv('VICTORIABANK_MERCHANT_PRIVATE_KEY');
        self::$bank_public_key      = getenv('VICTORIABANK_BANK_PUBLIC_KEY');
        self::$signature_algo       = getenv('VICTORIABANK_SIGNATURE_ALGO');

        self::$merchant_name    = getenv('VICTORIABANK_MERCHANT_NAME');
        self::$merchant_url     = getenv('VICTORIABANK_MERCHANT_URL');
        self::$merchant_address = getenv('VICTORIABANK_MERCHANT_ADDRESS');
        self::$backref_url      = getenv('VICTORIABANK_BACKREF_URL');

        self::$baseUrl = VictoriabankClient::TEST_BASE_URL;

        if (empty(self::$merchant_id) || empty(self::$terminal_id) || empty(self::$merchant_private_key) || empty(self::$bank_public_key) || empty(self::$signature_algo)) {
            self::markTestSkipped('Integration test credentials not provided.');
        }
    }

    protected function setUp(): void
    {
        $options = [
            'base_uri' => self::$baseUrl,
            'timeout' => 15,
        ];

        #region Logging
        $classParts = explode('\\', self::class);
        $logName = end($classParts) . '_guzzle';
        $logFileName = "$logName.log";

        $log = new \Monolog\Logger($logName);
        $log->pushHandler(new \Monolog\Handler\StreamHandler($logFileName, \Monolog\Logger::DEBUG));

        $stack = \GuzzleHttp\HandlerStack::create();
        $stack->push(\GuzzleHttp\Middleware::log($log, new \GuzzleHttp\MessageFormatter(\GuzzleHttp\MessageFormatter::DEBUG)));

        $options['handler'] = $stack;
        #endregion

        $this->client = new VictoriabankClient(new Client($options));

        $this->client
            ->setMerchantId(self::$merchant_id)
            ->setTerminalId(self::$terminal_id)
            ->setLang('ro')
            ->setTimezone('Europe/Chisinau')
            ->setMerchantPrivateKey(self::$merchant_private_key)
            ->setBankPublicKey(self::$bank_public_key)
            ->setSignatureAlgo(self::$signature_algo)
            ->setBackRefUrl(self::$backref_url);
    }

    protected function onNotSuccessfulTest(\Throwable $t): void
    {
        if ($this->isDebugMode()) {
            // https://github.com/guzzle/guzzle/issues/2185
            if ($t instanceof \GuzzleHttp\Command\Exception\CommandException) {
                $response = $t->getResponse();
                $responseBody = !empty($response) ? (string) $response->getBody() : '';
                $exceptionMessage = $t->getMessage();

                $this->debugLog($responseBody, $exceptionMessage);
            }
        }

        parent::onNotSuccessfulTest($t);
    }

    protected function isDebugMode()
    {
        // https://stackoverflow.com/questions/12610605/is-there-a-way-to-tell-if-debug-or-verbose-was-passed-to-phpunit-in-a-test
        return in_array('--debug', $_SERVER['argv'] ?? []);
    }

    protected function debugLog($message, $data)
    {
        $data_print = print_r($data, true);
        error_log("$message: $data_print");
    }

    public function testAuthorize()
    {
        $authorize_data = [
            'AMOUNT' => '10.00',
            'CURRENCY' => 'MDL',
            'ORDER' => '000123',
            'DESC' => 'Order #123',
            'MERCH_NAME' => self::$merchant_name,
            'MERCH_URL' => self::$merchant_url,
            'EMAIL' => 'example@example.com',
            'MERCH_ADDRESS' => self::$merchant_address,
        ];

        $authorize_request = $this->client->generateAuthorizeRequest($authorize_data);
        $this->debugLog('generateAuthorizeRequest', $authorize_request);

        $this->assertIsArray($authorize_request);
        $this->assertNotEmpty($authorize_request);
        // file_put_contents('./tests/test.html', $html);
    }
}
