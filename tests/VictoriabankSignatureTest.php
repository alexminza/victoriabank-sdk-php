<?php

declare(strict_types=1);

namespace Victoriabank\Victoriabank\Tests;

use PHPUnit\Framework\TestCase;
use Victoriabank\Victoriabank\VictoriabankClient;
use GuzzleHttp\Client;

class VictoriabankSignatureTest extends TestCase
{
    private $privateKey;
    private $publicKey;
    private $client;

    protected function setUp(): void
    {
        // Generate a new key pair for testing
        $res = openssl_pkey_new([
            "digest_alg" => "sha256",
            "private_key_bits" => 2048,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($res, $this->privateKey);
        $this->publicKey = openssl_pkey_get_details($res)['key'];

        $this->client = new VictoriabankClient();
        $this->client->setMerchantPrivateKey($this->privateKey);
        $this->client->setBankPublicKey($this->publicKey); // Using same key pair for simplicity (acting as both merchant and bank)
    }

    public function testGenerateSignatureMd5()
    {
        $this->client->setSignatureAlgo(VictoriabankClient::P_SIGN_HASH_ALGO_MD5);

        $params = [
            'ORDER' => '123456',
            'NONCE' => bin2hex(random_bytes(16)),
            'TIMESTAMP' => '20230101120000',
            'TRTYPE' => '0',
            'AMOUNT' => '100.00'
        ];

        // This method calculates MAC using these specific fields for MERCHANT_PSIGN_PARAMS
        // MERCHANT_PSIGN_PARAMS = ['ORDER', 'NONCE', 'TIMESTAMP', 'TRTYPE', 'AMOUNT'];
        $signatureHex = $this->client->generateSignature($params);

        // Verify manually
        $mac = '';
        foreach (['ORDER', 'NONCE', 'TIMESTAMP', 'TRTYPE', 'AMOUNT'] as $key) {
             $val = $params[$key];
             $mac .= strlen($val) . $val;
        }

        $signatureBin = hex2bin($signatureHex);
        $result = openssl_verify($mac, $signatureBin, $this->publicKey, OPENSSL_ALGO_MD5);
        
        $this->assertEquals(1, $result, "MD5 Signature verification failed");
    }

    public function testGenerateSignatureSha256()
    {
        $this->client->setSignatureAlgo(VictoriabankClient::P_SIGN_HASH_ALGO_SHA256);

        $params = [
            'ORDER' => '123456',
            'NONCE' => bin2hex(random_bytes(16)),
            'TIMESTAMP' => '20230101120000',
            'TRTYPE' => '0',
            'AMOUNT' => '100.00'
        ];

        $signatureHex = $this->client->generateSignature($params);

        // Verify manually
        $mac = '';
        foreach (['ORDER', 'NONCE', 'TIMESTAMP', 'TRTYPE', 'AMOUNT'] as $key) {
             $val = $params[$key];
             $mac .= strlen($val) . $val;
        }

        $signatureBin = hex2bin($signatureHex);
        $result = openssl_verify($mac, $signatureBin, $this->publicKey, OPENSSL_ALGO_SHA256);
        
        $this->assertEquals(1, $result, "SHA256 Signature verification failed");
    }

    public function testValidateSignatureMd5()
    {
        $this->client->setSignatureAlgo(VictoriabankClient::P_SIGN_HASH_ALGO_MD5);

        $params = [
            'ACTION' => '0',
            'RC' => '00',
            'RRN' => '123456789012',
            'ORDER' => '123456',
            'AMOUNT' => '100.00'
        ];
        
        // GATEWAY_PSIGN_PARAMS = ['ACTION', 'RC', 'RRN', 'ORDER', 'AMOUNT'];
        $mac = '';
        foreach (['ACTION', 'RC', 'RRN', 'ORDER', 'AMOUNT'] as $key) {
             $val = $params[$key];
             $mac .= strlen($val) . $val;
        }

        // Sign with private key (acting as bank)
        $signature = '';
        openssl_sign($mac, $signature, $this->privateKey, OPENSSL_ALGO_MD5);
        
        $params['P_SIGN'] = bin2hex($signature);

        $isValid = $this->client->validateSignature($params);
        $this->assertTrue($isValid, "MD5 Signature validation failed");
    }

    public function testValidateSignatureSha256()
    {
        $this->client->setSignatureAlgo(VictoriabankClient::P_SIGN_HASH_ALGO_SHA256);

        $params = [
            'ACTION' => '0',
            'RC' => '00',
            'RRN' => '123456789012',
            'ORDER' => '123456',
            'AMOUNT' => '100.00'
        ];
        
        $mac = '';
        foreach (['ACTION', 'RC', 'RRN', 'ORDER', 'AMOUNT'] as $key) {
             $val = $params[$key];
             $mac .= strlen($val) . $val;
        }

        // Sign with private key (acting as bank)
        $signature = '';
        openssl_sign($mac, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);
        
        $params['P_SIGN'] = bin2hex($signature);

        $isValid = $this->client->validateSignature($params);
        $this->assertTrue($isValid, "SHA256 Signature validation failed");
    }
}
