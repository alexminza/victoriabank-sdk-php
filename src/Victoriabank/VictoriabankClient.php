<?php

namespace Victoriabank\Victoriabank;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Command\Guzzle\DescriptionInterface;
use GuzzleHttp\Command\Guzzle\GuzzleClient;
use GuzzleHttp\Command\Guzzle\Handler\ValidatedDescriptionHandler;
use GuzzleHttp\Command\Result;

/**
 * Victoriabank client
 *
 * @link https://ecomt.victoriabank.md/cardop/images/instruction-ecom-vb.zip
 */
class VictoriabankClient extends GuzzleClient
{
    public const DEFAULT_BASE_URL = 'https://vb059.vb.md/cgi-bin/cgi_link';
    public const TEST_BASE_URL = 'https://ecomt.victoriabank.md/cgi-bin/cgi_link';

    public const TRTYPE_AUTHORIZATION = '0';
    public const TRTYPE_SALES_COMPLETION = '21';
    public const TRTYPE_REVERSAL = '24';

    public const P_SIGN_HASH_ALGO_MD5    = 'md5';
    public const P_SIGN_HASH_ALGO_SHA256 = 'sha256';

    //region Config
    /**
     * @var string
     */
    protected $merchant_id;

    /**
     * @var string
     */
    protected $terminal_id;

    /**
     * @var string
     */
    protected $backref_url;

    /**
     * @var string
     */
    protected $lang;

    /**
     * @var string
     */
    protected $country;

    /**
     * @var string
     */
    protected $merchant_private_key;

    /**
     * @var string
     */
    protected $bank_public_key;

    /**
     * @var string
     */
    protected $signature_algo = self::P_SIGN_HASH_ALGO_SHA256;

    /**
     * @var \DateTimeZone
     */
    protected $timezone;
    //endregion

    public function __construct(?ClientInterface $client = null, ?DescriptionInterface $description = null, array $config = [])
    {
        $client = $client ?? new Client();
        $description = $description ?? new VictoriabankDescription();
        parent::__construct($client, $description, null, null, null, $config);

        $this->init();
    }

    protected function init()
    {
        $this->timezone = new \DateTimeZone(date_default_timezone_get());
    }

    //region Set config
    public function setMerchantId(string $merchant_id)
    {
        $this->merchant_id = $merchant_id;
        return $this;
    }

    public function setTerminalId(string $terminal_id)
    {
        $this->terminal_id = $terminal_id;
        return $this;
    }

    public function setLang(string $lang)
    {
        $this->lang = $lang;
        return $this;
    }

    public function setCountry(string $country)
    {
        $this->country = $country;
        return $this;
    }

    public function setTimezone(string $timezone)
    {
        $this->timezone = new \DateTimeZone($timezone);
        return $this;
    }

    public function setMerchantPrivateKey(string $merchant_private_key)
    {
        $this->merchant_private_key = $merchant_private_key;
        return $this;
    }

    public function setBankPublicKey(string $bank_public_key)
    {
        $this->bank_public_key = $bank_public_key;
        return $this;
    }

    public function setSignatureAlgo(string $signature_algo)
    {
        $this->signature_algo = $signature_algo;
        return $this;
    }

    public function setBackRefUrl(string $backref_url)
    {
        $this->backref_url = $backref_url;
        return $this;
    }
    //endregion

    //region Operations
    /**
     * Authorize payment
     */
    public function generateAuthorizeRequest(array $authorize_data)
    {
        $args = $authorize_data;
        $args['TRTYPE'] = self::TRTYPE_AUTHORIZATION;
        $args['MERCH_GMT'] = $this->getTimezoneOffset();
        $args['TIMESTAMP'] = self::getTimestamp();
        $args['NONCE'] = self::generateNonce();

        $args['MERCHANT'] = $this->merchant_id;
        $args['TERMINAL'] = $this->terminal_id;
        $args['BACKREF'] = $this->backref_url;
        $args['LANG'] = $this->lang;
        $args['COUNTRY'] = $this->country;

        $args['P_SIGN'] = $this->generateSignature($args, self::MERCHANT_PSIGN_PARAMS, $this->merchant_private_key);

        $operation_name = 'authorize';
        $description = $this->getDescription();
        // $operation = $description->getOperation($operation_name);
        $command = $this->getCommand($operation_name, $args);

        $validationHandler = new ValidatedDescriptionHandler($description);
        $validator = $validationHandler(function () {
        });
        $validator($command, null);

        return $args;
    }
    //endregion

    //region Utility
    /**
     * Merchant order ID (6-32 characters)
     */
    public static function normalizeOrderId($order_id)
    {
        return sprintf('%06s', $order_id);
    }

    public static function deNormalizeOrderId($order_id)
    {
        return ltrim($order_id, '0');
    }

    /**
     * Merchant transaction timestamp in GMT: YYYYMMDDHHMMSS.
     */
    protected static function getTimestamp()
    {
        // Format the date as YYYYMMDDHHMMSS
        return gmdate('YmdHis');
    }

    protected function getTimezoneOffset()
    {
        $now = new \DateTime('now', $this->timezone);

        // Get offset in seconds and convert to whole hours
        $hours = $now->getOffset() / 3600;
        $offset = ($hours >= 0)
            ? "+$hours"
            : (string) $hours;

        return $offset;
    }

    /**
     * Merchant nonce. Must be filled with 20-32 unpredictable random bytes in hexadecimal format.
     */
    protected static function generateNonce()
    {
        // Generate the cryptographically secure bytes
        $bytes = random_bytes(32);

        // Convert to hexadecimal format
        return bin2hex($bytes);
    }
    //endregion

    //region PSIGN
    protected const MERCHANT_PSIGN_PARAMS = ['ORDER', 'NONCE', 'TIMESTAMP', 'TRTYPE', 'AMOUNT'];
    protected const GATEWAY_PSIGN_PARAMS  = ['ACTION', 'RC', 'RRN', 'ORDER', 'AMOUNT'];

    public function generateSignature(array $params, array $psign_params, string $private_key)
    {
        $mac = self::generateMac($params, $psign_params);
        $private_key_resource = openssl_pkey_get_private($private_key);

        switch ($this->signature_algo) {
            case self::P_SIGN_HASH_ALGO_MD5:
                $signature = self::createSignatureMd5($mac, $private_key_resource);
                break;
            case self::P_SIGN_HASH_ALGO_SHA256:
                $signature = self::createSignatureSha256($mac, $private_key_resource);
                break;
            default:
                throw new \Exception('Failed to generate transaction signature: Unknown P_SIGN hashing algorithm.');
        }

        if (PHP_VERSION_ID < 80000) {
            // phpcs:ignore Generic.PHP.DeprecatedFunctions.Deprecated -- PHP_VERSION_ID check performed before invocation.
            openssl_free_key($private_key_resource);
        }

        return strtoupper(bin2hex($signature));
    }

    public function validateSignature(array $params, array $psign_params, string $public_key)
    {
    }

    protected static function generateMac(array $params, array $psign_params)
    {
        // Format: {length1}{value1}{length2}{value2}...

        $mac = '';
        foreach ($psign_params as $key) {
            // Strict check for null/empty string to allow "0"
            $val = (isset($params[$key]) && $params[$key] !== '')
                ? (string) $params[$key]
                : '';

            if ($val !== '') {
                $mac .= strlen($val) . $val;
            } else {
                $mac .= '-';
            }
        }

        return $mac;
    }

    /**
     * Victoriabank e-Commerce Gateway merchant interface (CGI/WWW forms version)
     * Appendix A: P_SIGN creation/verification in the Merchant System
     *
     * This prefix is required for the e-Gateway to recognize the MD5 hash
     */
    protected const VB_SIGNATURE_PREFIX = '003020300C06082A864886F70D020505000410';

    protected static function createSignatureMd5(string $mac, $private_key_resource)
    {
        $mac_hash = md5($mac);
        $signed_data = hex2bin(self::VB_SIGNATURE_PREFIX . $mac_hash);

        // RSA Private Encryption with PKCS#1 padding
        // The specification describes manual padding, but openssl_private_encrypt
        // handles this automatically with the OPENSSL_PKCS1_PADDING flag.
        $signature = '';
        openssl_private_encrypt($signed_data, $signature, $private_key_resource, OPENSSL_PKCS1_PADDING);

        return $signature;
    }

    protected static function createSignatureSha256(string $mac, $private_key_resource)
    {
        $signature = '';
        openssl_sign($mac, $signature, $private_key_resource, OPENSSL_ALGO_SHA256);

        return $signature;
    }
    //endregion
}
