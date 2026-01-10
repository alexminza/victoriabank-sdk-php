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
    protected static $complete_data;

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

        // TEST DATA
        self::$rrn     = getenv('VICTORIABANK_TEST_RRN');
        self::$int_ref = getenv('VICTORIABANK_TEST_INT_REF');
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
        $data_print = var_export($data, true);
        error_log("$message: $data_print");
    }

    public function testAuthorize()
    {
        $order_id = 123;
        self::$authorize_data = [
            'AMOUNT' => '10.00',
            'CURRENCY' => 'MDL',
            'ORDER' => VictoriabankClient::normalizeOrderId($order_id),
            'DESC' => "Order #$order_id",
            'MERCH_NAME' => self::$merchant_name,
            'MERCH_URL' => self::$merchant_url,
            'EMAIL' => 'example@example.com',
            'MERCH_ADDRESS' => self::$merchant_address,
        ];

        $authorize_request = $this->client->generateAuthorizeRequest(self::$authorize_data);
        $this->debugLog('generateAuthorizeRequest', $authorize_request);

        $this->assertIsArray($authorize_request);
        $this->assertNotEmpty($authorize_request);

        $html = $this->client->generateHtmlForm(self::$baseUrl, $authorize_request);
        file_put_contents('./tests/testAuthorize.html', $html);
    }

    /**
     * @depends testAuthorize
     */
    public function testComplete()
    {
        if (empty(self::$rrn) || empty(self::$int_ref)) {
            $this->markTestIncomplete();
            return;
        }

        self::$complete_data = [
            'ORDER' => self::$authorize_data['ORDER'],
            'AMOUNT' => self::$authorize_data['AMOUNT'],
            'CURRENCY' => self::$authorize_data['CURRENCY'],
            'RRN' => self::$rrn,
            'INT_REF' => self::$int_ref,
        ];

        $complete_response = $this->client->complete(self::$complete_data);
        $this->debugLog('complete', $complete_response);

        $this->assertIsArray($complete_response);
        $this->assertNotEmpty($complete_response);
    }

    /**
     * @depends testComplete
     */
    public function testReverse()
    {
        $reverse_data = self::$complete_data;

        $reverse_response = $this->client->reverse($reverse_data);
        $this->debugLog('complete', $reverse_response);

        $this->assertIsArray($reverse_response);
        $this->assertNotEmpty($reverse_response);
    }

    /**
     * @depends testAuthorize
     */
    public function testCheck()
    {
        $order_id = 123;
        $check_data = [
            'TRAN_TRTYPE' => VictoriabankClient::TRTYPE_AUTHORIZATION,
            'ORDER' => VictoriabankClient::normalizeOrderId($order_id),
        ];

        $check_response = $this->client->check($check_data);
        $this->debugLog('check', $check_response);

        $this->assertNotEmpty($check_response);
        $this->assertArrayHasKey('body', $check_response);
        $this->assertNotEmpty($check_response['body']);

        $html = $check_response['body'];
        file_put_contents('./tests/testCheck.html', $html);
    }

    public function testValidate()
    {
        $callback_data = json_decode(file_get_contents('./tests/testValidate.json'), true);
        $is_valid = $this->client->validateSignature($callback_data, self::$bank_public_key);
        $this->debugLog('validateSignature', $is_valid);

        $this->assertTrue($is_valid);
    }
}
