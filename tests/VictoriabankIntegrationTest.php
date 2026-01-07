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

    protected static $merchant_name;
    protected static $merchant_url;
    protected static $merchant_address;

    protected static $merchant_private_key;
    protected static $bank_public_key;
    protected static $psign_algo;

    protected static $baseUrl;

    // Shared state
    protected static $trans_id;
    protected static $authorize_data;

    /**
     * @var VictoriabankClient
     */
    protected $client;

    public static function setUpBeforeClass(): void
    {
        self::$merchant_id = getenv('VICTORIABANK_MERCHANT_ID');
        self::$terminal_id = getenv('VICTORIABANK_TERMINAL_ID');

        self::$merchant_name    = getenv('VICTORIABANK_MERCHANT_NAME');
        self::$merchant_url     = getenv('VICTORIABANK_MERCHANT_URL');
        self::$merchant_address = getenv('VICTORIABANK_MERCHANT_ADDRESS');

        self::$merchant_private_key = getenv('VICTORIABANK_MERCHANT_PRIVATE_KEY');
        self::$bank_public_key      = getenv('VICTORIABANK_BANK_PUBLIC_KEY');
        self::$psign_algo           = getenv('VICTORIABANK_PSIGN_ALGO');

        self::$baseUrl = VictoriabankClient::TEST_BASE_URL;

        if (empty(self::$username) || empty(self::$password) || empty(self::$iban) || empty(self::$publicKey)) {
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
            ->setMerchantPrivateKey(self::$merchant_private_key)
            ->setBankPublicKey(self::$bank_public_key)
            ->setPSignAlgo(self::$psign_algo);
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
            'MERCHANT' => self::$merchant_id,
            'TERMINAL' => self::$terminal_id,
            'EMAIL' => 'example@example.com',
            'MERCH_ADDRESS' => self::$merchant_address,

            // 'COUNTRY' => 'MD',
            'MERCH_GMT' => date('Z') / 3600, // https://stackoverflow.com/questions/193499/utc-offset-in-php
            'TIMESTAMP' => VictoriabankClient::getTimestamp(),
            'NONCE' => VictoriabankClient::generateNonce(),
            'BACKREF' => 'https://example.com/backref',
            'P_SIGN' => '',
            'LANG' => 'ro',
        ];

        $response = $this->client->authorize($authorize_data);
        // $this->debugLog('authorize', $response);

        $this->assertNotEmpty($response);
        $this->assertArrayHasKey('TERMINAL', $response);
        $this->assertArrayHasKey('TRTYPE', $response);
        $this->assertArrayHasKey('ORDER', $response);
        $this->assertArrayHasKey('AMOUNT', $response);
        $this->assertArrayHasKey('CURRENCY', $response);
        $this->assertEquals($authorize_data['TERMINAL'], $response['TERMINAL']);
        $this->assertEquals($authorize_data['TRTYPE'], $response['TRTYPE']);
        $this->assertEquals($authorize_data['ORDER'], $response['ORDER']);
        $this->assertEquals($authorize_data['AMOUNT'], $response['AMOUNT']);
        $this->assertEquals($authorize_data['CURRENCY'], $response['CURRENCY']);

        self::$qrHeaderUUID = $response['qrHeaderUUID'];
        self::$qrExtensionUUID = $response['qrExtensionUUID'];
        self::$authorize_data = $authorize_data;
    }

    /**
     * @depends testAuthorize
     */
    public function testComplete()
    {
        $hybridData = [
            'header' => [
                'qrType' => 'HYBR',
                'amountType' => 'Fixed',
                'pmtContext' => 'e'
            ],
            'extension' => [
                'creditorAccount' => [
                    'iban' => self::$iban
                ],
                'amount' => [
                    'sum' => 15.00,
                    'currency' => 'MDL'
                ],
                'dba' => 'Test Hybrid Merchant',
                'remittanceInfo4Payer' => 'Hybrid Order #H1',
                'creditorRef' => 'H1',
                'ttl' => [
                    'length' => 60,
                    'units' => 'mm'
                ]
            ]
        ];

        $response = $this->client->createPayeeQr($hybridData, self::$accessToken);
        // $this->debugLog('createPayeeQr', $response);

        $this->assertNotEmpty($response);
        $this->assertArrayHasKey('qrHeaderUUID', $response);
        $this->assertArrayHasKey('qrExtensionUUID', $response);
        $this->assertArrayHasKey('qrAsText', $response);
        $this->assertNotEmpty($response['qrHeaderUUID']);
        $this->assertNotEmpty($response['qrExtensionUUID']);
        $this->assertNotEmpty($response['qrAsText']);

        self::$hybridQrHeaderUUID = $response['qrHeaderUUID'];
        self::$hybridQrExtensionUUID = $response['qrExtensionUUID'];
        self::$hybridQrData = $hybridData;
    }

    /**
     * @depends testAuthorize
     */
    public function testReverse()
    {
        $qrToCancelResponse = $this->client->createPayeeQr(self::$qrData, self::$accessToken);
        $this->assertNotEmpty($qrToCancelResponse);
        $this->assertArrayHasKey('qrHeaderUUID', $qrToCancelResponse);
        $this->assertNotEmpty($qrToCancelResponse['qrHeaderUUID']);

        $response = $this->client->cancelPayeeQr($qrToCancelResponse['qrHeaderUUID'], self::$accessToken);
        // $this->debugLog('cancelPayeeQr', $response);

        $this->assertNotNull($response);
        $this->assertEmpty($response);
    }
}