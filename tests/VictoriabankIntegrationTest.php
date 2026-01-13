<?php

declare(strict_types=1);

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
    protected static $merchant_private_key_passphrase;
    protected static $merchant_public_key;
    protected static $bank_public_key;
    protected static $signature_algo;

    protected static $merchant_name;
    protected static $merchant_url;
    protected static $merchant_address;
    protected static $backref_url;

    protected static $baseUri;

    // Shared state
    protected static $authorize_data;
    protected static $complete_data;
    protected static $validate_data;

    protected static $rrn;
    protected static $int_ref;

    /**
     * @var VictoriabankClient
     */
    protected $client;

    public static function setUpBeforeClass(): void
    {
        self::$merchant_id = getenv('VB_MERCHANT_ID');
        self::$terminal_id = getenv('VB_TERMINAL_ID');

        self::$merchant_private_key = getenv('VB_MERCHANT_PRIVATE_KEY');
        self::$merchant_private_key_passphrase = getenv('VB_MERCHANT_PRIVATE_KEY_PASSPHRASE') ?: null;
        self::$merchant_public_key  = getenv('VB_MERCHANT_PUBLIC_KEY');
        self::$bank_public_key      = getenv('VB_BANK_PUBLIC_KEY');
        self::$signature_algo       = getenv('VB_SIGNATURE_ALGO');

        self::$merchant_name    = getenv('VB_MERCHANT_NAME');
        self::$merchant_url     = getenv('VB_MERCHANT_URL');
        self::$merchant_address = getenv('VB_MERCHANT_ADDRESS');
        self::$backref_url      = getenv('VB_BACKREF_URL');

        self::$baseUri = getenv('VB_BASE_URI') ?: VictoriabankClient::TEST_BASE_URL;

        if (empty(self::$merchant_id) || empty(self::$terminal_id) || empty(self::$merchant_private_key) || empty(self::$bank_public_key) || empty(self::$signature_algo)) {
            self::markTestSkipped('Integration test credentials not provided.');
        }

        // TEST DATA
        $test_validate = './tests/testValidate.json';
        if (file_exists($test_validate)) {
            self::$validate_data = json_decode(file_get_contents($test_validate), true);

            self::$rrn     = self::$validate_data['RRN'];
            self::$int_ref = self::$validate_data['INT_REF'];
        }
    }

    protected function setUp(): void
    {
        $options = [
            'base_uri' => self::$baseUri,
            'timeout' => 30,
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
            ->setLanguage('ro')
            ->setTimezone('Europe/Chisinau')
            ->setMerchantPrivateKey(self::$merchant_private_key, self::$merchant_private_key_passphrase)
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

    protected static function parseResponseForm(string $html)
    {
        return self::parseResponseRegex($html, '/<input.+name="(\w+)".+value="(.*?)"/i');
    }

    protected static function parseResponseRegex(string $response, string $regex)
    {
        $match_result = preg_match_all($regex, $response, $matches, PREG_SET_ORDER);
        if (empty($match_result)) {
            return null;
        }

        $vbdata = [];
        foreach ($matches as $match) {
            if (count($match) === 3) {
                $name = $match[1];
                $value = $match[2];
                $vbdata[$name] = $value;
            }
        }

        return $vbdata;
    }

    public function testAuthorize()
    {
        $order_id = '123';
        $amount = 123.45;
        self::$authorize_data = [
            'AMOUNT' => (string) $amount,
            'CURRENCY' => 'MDL',
            'ORDER' => VictoriabankClient::normalizeOrderId($order_id),
            'DESC' => "Order #$order_id",
            'MERCH_NAME' => self::$merchant_name,
            'MERCH_URL' => self::$merchant_url,
            'EMAIL' => 'example@example.com',
            'MERCH_ADDRESS' => self::$merchant_address,
        ];

        // $authorize_request = $this->client->generateAuthorizeRequest(self::$authorize_data);
        $authorize_request = $this->client->generateOrderAuthorizeRequest(
            $order_id,
            $amount,
            self::$authorize_data['CURRENCY'],
            self::$authorize_data['DESC'],
            self::$merchant_name,
            self::$merchant_url,
            self::$merchant_address,
            self::$authorize_data['EMAIL']
        );
        $this->debugLog('generateAuthorizeRequest', $authorize_request);

        $this->assertIsArray($authorize_request);
        $this->assertNotEmpty($authorize_request);

        $html = $this->client->generateHtmlForm(self::$baseUri, $authorize_request);
        file_put_contents('./tests/testAuthorize.html', $html);
    }

    /**
     * @depends testAuthorize
     */
    public function testComplete()
    {
        if (empty(self::$rrn) || empty(self::$int_ref)) {
            $this->markTestSkipped('RRN and INT_REF are NOT SET');
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

        $this->assertNotEmpty($complete_response);
        $this->assertArrayHasKey('body', $complete_response);
        $this->assertNotEmpty($complete_response['body']);

        $html = $complete_response['body'];
        file_put_contents('./tests/testComplete.html', $html);

        $complete_response_data = $this->parseResponseForm($html);
        $this->assertIsArray($complete_response_data);
        $this->assertNotEmpty($complete_response_data);

        $this->assertTrue($this->client->validateSignature($complete_response_data));

        $this->assertEquals(VictoriabankClient::TRTYPE_SALES_COMPLETION, $complete_response_data['TRTYPE']);
        $this->assertContainsEquals($complete_response_data['ACTION'], [VictoriabankClient::ACTION_SUCCESS, VictoriabankClient::ACTION_DUPLICATE]);
        $this->assertEquals(VictoriabankClient::RESULT_SUCCESS, $complete_response_data['RC']);
    }

    /**
     * @depends testComplete
     */
    public function testReverse()
    {
        if (empty(self::$complete_data)) {
            $this->markTestSkipped('COMPLETE DATA is NOT SET');
            return;
        }

        $reverse_data = self::$complete_data;

        $reverse_response = $this->client->reverse($reverse_data);
        $this->debugLog('reverse', $reverse_response);

        $this->assertNotEmpty($reverse_response);
        $this->assertArrayHasKey('body', $reverse_response);
        $this->assertNotEmpty($reverse_response['body']);

        $html = $reverse_response['body'];
        file_put_contents('./tests/testReverse.html', $html);

        $reverse_response_data = $this->parseResponseForm($html);
        $this->assertIsArray($reverse_response_data);
        $this->assertNotEmpty($reverse_response_data);

        $this->assertTrue($this->client->validateSignature($reverse_response_data));

        $this->assertEquals(VictoriabankClient::TRTYPE_REVERSAL, $reverse_response_data['TRTYPE']);
        $this->assertContainsEquals($reverse_response_data['ACTION'], [VictoriabankClient::ACTION_SUCCESS, VictoriabankClient::ACTION_DUPLICATE]);
        $this->assertEquals(VictoriabankClient::RESULT_SUCCESS, $reverse_response_data['RC']);
    }

    /**
     * @depends testAuthorize
     */
    public function testCheck()
    {
        $check_data = [
            'TRAN_TRTYPE' => VictoriabankClient::TRTYPE_AUTHORIZATION,
            'ORDER' => self::$authorize_data['ORDER'],
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
        if (empty(self::$validate_data)) {
            $this->markTestSkipped('VALIDATE DATA is NOT SET');
            return;
        }

        $is_valid = $this->client->validateSignature(self::$validate_data);
        $this->debugLog('validateSignature', $is_valid);

        $this->assertTrue($is_valid);
    }
}
