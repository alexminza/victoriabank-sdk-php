<?php

namespace Victoriabank\Victoriabank;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Command\Guzzle\DescriptionInterface;
use GuzzleHttp\Command\Guzzle\GuzzleClient;
use GuzzleHttp\Command\Guzzle\Handler\ValidatedDescriptionHandler;
use GuzzleHttp\Command\Result;
use GuzzleHttp\Promise\FulfilledPromise;

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
    public const TRTYPE_CHECK = '90';

    public const P_SIGN_HASH_ALGO_MD5    = 'md5';
    public const P_SIGN_HASH_ALGO_SHA256 = 'sha256';

    public const DEFAULT_COUNTRY = 'md';
    public const DEFAULT_LANG = 'en';

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
    protected $lang = self::DEFAULT_LANG;

    /**
     * @var string
     */
    protected $country = self::DEFAULT_COUNTRY;

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
        $args['MERCHANT'] = $this->merchant_id;
        $args['BACKREF'] = $this->backref_url;
        $args['LANG'] = $this->lang;
        $args['COUNTRY'] = $this->country;

        $this->setTransactionParams($args);
        $this->validateOperationArgs('authorize', $args);

        return $args;
    }

    public function complete(array $complete_data)
    {
        $args = $complete_data;
        $args['TRTYPE'] = self::TRTYPE_SALES_COMPLETION;

        $this->setTransactionParams($args);

        return parent::complete($args);
    }

    public function reverse(array $reverse_data)
    {
        $args = $reverse_data;
        $args['TRTYPE'] = self::TRTYPE_REVERSAL;

        $this->setTransactionParams($args);

        return parent::reverse($args);
    }

    public function check(array $check_data)
    {
        $args = $check_data;
        $args['TRTYPE'] = self::TRTYPE_CHECK;
        $args['TERMINAL'] = $this->terminal_id;

        return parent::check($args);
    }
    //endregion

    //region Utility
    protected function setTransactionParams(array &$args)
    {
        $args['TERMINAL'] = $this->terminal_id;
        $args['TIMESTAMP'] = self::getTimestamp();
        $args['NONCE'] = self::generateNonce();

        $args['P_SIGN'] = $this->generateSignature($args, self::MERCHANT_PSIGN_PARAMS, $this->merchant_private_key);
    }

    protected function validateOperationArgs(string $name, array $args)
    {
        $command = $this->getCommand($name, $args);
        $command->getHandlerStack()->setHandler(function () {
            return new FulfilledPromise(new Result());
        });

        $this->execute($command);
    }

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

    /**
     * Generates an HTML form that automatically submits to the bank's payment gateway.
     *
     * @param string $action The URL to which the form will be submitted.
     * @param array $args The parameters to be sent in the form as hidden inputs.
     * @param string|null $form_id The ID of the form. If null, a unique ID will be generated.
     * @param bool $auto_submit Whether the form should be automatically submitted via JavaScript.
     *
     * @return string The generated HTML form.
     */
    public static function generateHtmlForm(string $action, array $args, ?string $form_id = null, bool $auto_submit = true)
    {
        if (empty($form_id)) {
            $form_id = uniqid('form-');
        }

        $form_id = htmlspecialchars($form_id, ENT_QUOTES);
        $submit_id = "$form_id-submit";
        $attr_action = htmlspecialchars($action, ENT_QUOTES);

        $html = "<form id='$form_id' name='$form_id' method='POST' action='$attr_action'>\n";
        foreach ($args as $name => $value) {
            $attr_name = htmlspecialchars($name, ENT_QUOTES);
            $attr_value = htmlspecialchars($value, ENT_QUOTES);
            $html .= "\t<input type='hidden' name='$attr_name' value='$attr_value' />\n";
        }

        if (!$auto_submit) {
            $html .= "\t<input type='submit' id='$submit_id' name='$submit_id' />\n";
        }

        $html .= "</form>\n";

        if ($auto_submit) {
            $html .= "<script type='text/javascript'>document.getElementById('$form_id').submit();</script>\n";
        }

        return $html;
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
                throw new \Exception('Unknown P_SIGN hashing algorithm.');
        }

        if (PHP_VERSION_ID < 80000) {
            // phpcs:ignore Generic.PHP.DeprecatedFunctions.Deprecated -- PHP_VERSION_ID check performed before invocation.
            openssl_free_key($private_key_resource);
        }

        return strtoupper(bin2hex($signature));
    }

    public function validateSignature(array $params, string $public_key)
    {
        $mac = self::generateMac($params, self::GATEWAY_PSIGN_PARAMS);
        $signature_bin = hex2bin($params['P_SIGN']);

        $public_key_resource = openssl_pkey_get_public($public_key);

        switch ($this->signature_algo) {
            case self::P_SIGN_HASH_ALGO_MD5:
                $is_valid = $this->verifySignature($mac, $signature_bin, $public_key_resource, self::P_SIGN_HASH_ALGO_MD5, self::VB_SIGNATURE_MD5_PREFIX);
                break;
            case self::P_SIGN_HASH_ALGO_SHA256:
                $is_valid = $this->verifySignature($mac, $signature_bin, $public_key_resource, self::P_SIGN_HASH_ALGO_SHA256, self::VB_SIGNATURE_SHA256_PREFIX);
                break;
            default:
                throw new \Exception('Unknown P_SIGN hashing algorithm.');
        }

        return $is_valid;
    }

    /**
     * Decrypts the signature and checks for the specific ASN.1 prefix and hash match.
     */
    protected function verifySignature(string $mac, string $signature_bin, $pub_key, string $algo, string $prefix): bool
    {
        $decrypted_bin = '';
        // Note: Victoriabank usually requires standard PKCS1 padding for public decryption
        if (!openssl_public_decrypt($signature_bin, $decrypted_bin, $pub_key, OPENSSL_PKCS1_PADDING)) {
            return false;
        }

        $decrypted_hex = strtoupper(bin2hex($decrypted_bin));
        $calculated_hash = strtoupper(hash($algo, $mac));

        // Remove the prefix from the decrypted data to isolate the hash
        $decrypted_hash = str_replace($prefix, '', $decrypted_hex);

        return hash_equals($decrypted_hash, $calculated_hash);
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
    protected const VB_SIGNATURE_MD5_PREFIX = '3020300C06082A864886F70D020505000410';
    protected const VB_SIGNATURE_SHA256_PREFIX = '3031300D060960864801650304020105000420';

    protected static function createSignatureMd5(string $mac, $private_key_resource)
    {
        $mac_hash = md5($mac);
        $signed_data = hex2bin(self::VB_SIGNATURE_MD5_PREFIX . $mac_hash);

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
