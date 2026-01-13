<?php

declare(strict_types=1);

namespace Victoriabank\Victoriabank;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Command\Guzzle\DescriptionInterface;
use GuzzleHttp\Command\Guzzle\GuzzleClient;
use GuzzleHttp\Command\Guzzle\Handler\ValidatedDescriptionHandler;
use GuzzleHttp\Command\Guzzle\SchemaValidator;
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
    public const TEST_BASE_URL    = 'https://ecomt.victoriabank.md/cgi-bin/cgi_link';

    public const TRTYPE_AUTHORIZATION    = '0';
    public const TRTYPE_SALES_COMPLETION = '21';
    public const TRTYPE_REVERSAL         = '24';
    public const TRTYPE_CHECK            = '90';

    public const ACTION_SUCCESS   = '0';
    public const ACTION_DUPLICATE = '1';
    public const ACTION_DECLINED  = '2';
    public const ACTION_FAULT     = '3';

    public const RESULT_SUCCESS = '00';

    public const P_SIGN_HASH_ALGO_MD5    = 'md5';
    public const P_SIGN_HASH_ALGO_SHA256 = 'sha256';

    public const DEFAULT_COUNTRY  = 'md';
    public const DEFAULT_LANGUAGE = 'en';

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
    protected $language = self::DEFAULT_LANGUAGE;

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
    protected $merchant_private_key_passphrase;

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

    /**
     * @link https://en.wikipedia.org/wiki/List_of_ISO_639_language_codes
     */
    public function setLanguage(string $language)
    {
        $this->language = $language;
        return $this;
    }

    /**
     * @link https://en.wikipedia.org/wiki/ISO_3166-1
     */
    public function setCountry(string $country)
    {
        $this->country = $country;
        return $this;
    }

    /**
     * @link https://www.php.net/manual/en/timezones.php
     */
    public function setTimezone(string $timezone)
    {
        $this->timezone = new \DateTimeZone($timezone);
        return $this;
    }

    public function setMerchantPrivateKey(string $merchant_private_key, ?string $merchant_private_key_passphrase = null)
    {
        $this->merchant_private_key = $merchant_private_key;
        $this->merchant_private_key_passphrase = $merchant_private_key_passphrase;
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
    public function generateOrderAuthorizeRequest(string $order_id, float $amount, string $currency, string $description, string $merchant_name, string $merchant_url, string $merchant_address, string $email)
    {
        $authorize_data = [
            'AMOUNT' => (string) $amount,
            'CURRENCY' => $currency,
            'ORDER' => self::normalizeOrderId($order_id),
            'DESC' => $description,
            'MERCH_NAME' => $merchant_name,
            'MERCH_URL' => $merchant_url,
            'EMAIL' => $email,
            'MERCH_ADDRESS' => $merchant_address,
        ];

        return $this->generateAuthorizeRequest($authorize_data);
    }

    public function generateAuthorizeRequest(array $authorize_data)
    {
        $args = $authorize_data;
        $args['TRTYPE'] = self::TRTYPE_AUTHORIZATION;

        $args['MERCH_GMT'] = $this->getTimezoneOffset();
        $args['MERCHANT'] = $this->merchant_id;
        $args['BACKREF'] = $this->backref_url;
        $args['LANG'] = $this->language;
        $args['COUNTRY'] = $this->country;

        $this->setTransactionParams($args);
        $this->validateOperationArgsValidator('authorize', $args);

        return $args;
    }

    /**
     * Sales completion
     * This transaction shall be sent by the merchant system when goods and/or services are delivered to cardholder.
     * The card system will complete the financial transaction and transfer funds to the merchant account.
     * All fields are provided by merchant system and the cardholder does not participate in this transaction.
     */
    public function orderComplete(string $order_id, float $amount, string $currency, string $rrn, string $int_ref)
    {
        $complete_data = [
            'ORDER' => self::normalizeOrderId($order_id),
            'AMOUNT' => (string) $amount,
            'CURRENCY' => $currency,
            'RRN' => $rrn,
            'INT_REF' => $int_ref,
        ];

        return $this->complete($complete_data);
    }

    public function complete(array $complete_data)
    {
        $args = $complete_data;
        $args['TRTYPE'] = self::TRTYPE_SALES_COMPLETION;

        $this->setTransactionParams($args);

        return parent::complete($args);
    }

    /**
     * Reversal
     * The reversal transaction request shall be sent by the merchant system to e-Commerce Gateway in order to
     * cancel previously authorized or completed transactions.
     * All fields are provided by merchant system and the cardholder does not participate in this transaction.
     */
    public function orderReverse(string $order_id, float $amount, string $currency, string $rrn, string $int_ref)
    {
        $reverse_data = [
            'ORDER' => self::normalizeOrderId($order_id),
            'AMOUNT' => (string) $amount,
            'CURRENCY' => $currency,
            'RRN' => $rrn,
            'INT_REF' => $int_ref,
        ];

        return $this->reverse($reverse_data);
    }

    public function reverse(array $reverse_data)
    {
        $args = $reverse_data;
        $args['TRTYPE'] = self::TRTYPE_REVERSAL;

        $this->setTransactionParams($args);

        return parent::reverse($args);
    }

    /**
     * Check transaction status
     */
    public function orderCheck(string $order_id, string $tran_trtype)
    {
        $check_data = [
            'TRAN_TRTYPE' => $tran_trtype,
            'ORDER' => self::normalizeOrderId($order_id),
        ];

        return $this->check($check_data);
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

        $args['P_SIGN'] = $this->generateSignature($args);
    }

    /**
     * Merchant order ID (6-32 characters)
     */
    public static function normalizeOrderId(string $order_id)
    {
        return str_pad($order_id, 6, '0', STR_PAD_LEFT);
    }

    public static function deNormalizeOrderId(string $order_id)
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

    /**
     * Merchant UTC/GMT time zone offset (e.g. –3)
     */
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
        // Generate cryptographically secure pseudo-random bytes
        return bin2hex(random_bytes(32));
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

        $form_id_attr = htmlspecialchars($form_id, ENT_QUOTES);
        $submit_id_attr = htmlspecialchars("$form_id-submit", ENT_QUOTES);
        $action_attr = htmlspecialchars($action, ENT_QUOTES);

        $html = "<form id=\"$form_id_attr\" name=\"$form_id_attr\" method=\"POST\" action=\"$action_attr\">\n";
        foreach ($args as $name => $value) {
            $name_attr = htmlspecialchars($name, ENT_QUOTES);
            $value_attr = htmlspecialchars($value, ENT_QUOTES);
            $html .= "\t<input type=\"hidden\" name=\"$name_attr\" value=\"$value_attr\" />\n";
        }

        if (!$auto_submit) {
            $html .= "\t<input type=\"submit\" id=\"$submit_id_attr\" name=\"$submit_id_attr\" />\n";
        }

        $html .= "</form>\n";

        if ($auto_submit) {
            $js_form_id = json_encode($form_id);
            $html .= "<script type=\"text/javascript\">document.getElementById($js_form_id).submit();</script>\n";
        }

        return $html;
    }
    //endregion

    //region Validation
    protected function validateOperationArgsExecute(string $name, array $args)
    {
        $command = $this->getCommand($name, $args);
        $command->getHandlerStack()->setHandler(function () {
            return new FulfilledPromise(new Result());
        });

        $this->execute($command);
    }

    protected function validateOperationArgsValidator(string $name, array $args)
    {
        $description = $this->getDescription();
        $command = $this->getCommand($name, $args);

        $validation_handler = new ValidatedDescriptionHandler($description);
        $validator = $validation_handler(function () {});
        $validator($command, null);
    }

    public function validateResponseModel(string $name, array $response)
    {
        $description = $this->getDescription();
        $model = $description->getModel($name);

        $validator = new SchemaValidator();
        $is_valid = $validator->validate($model, $response);

        if (!$is_valid) {
            throw new VictoriabankException('Validation errors: ' . implode("\n", $validator->getErrors()));
        }

        return $is_valid;
    }

    /**
     * Validates bank response status and signature
     *
     * @throws VictoriabankException
     */
    public function validateResponse(array $response_data)
    {
        if (!isset($response_data['ACTION'])) {
            throw new VictoriabankException('Invalid bank response status');
        }

        $response_action = $response_data['ACTION'];
        switch ($response_action) {
            case self::ACTION_SUCCESS:
                return $this->verifySignature($response_data);
            case self::ACTION_DUPLICATE:
                throw new VictoriabankException('Bank response: Duplicate transaction detected');
            case self::ACTION_DECLINED:
                throw new VictoriabankException('Bank response: Transaction declined');
            case self::ACTION_FAULT:
                throw new VictoriabankException('Bank response: Transaction processing fault');
            default:
                throw new VictoriabankException('Unknown bank response status');
        }
    }
    //endregion

    //region PSIGN
    protected const MERCHANT_PSIGN_PARAMS = ['ORDER', 'NONCE', 'TIMESTAMP', 'TRTYPE', 'AMOUNT'];
    protected const GATEWAY_PSIGN_PARAMS  = ['ACTION', 'RC', 'RRN', 'ORDER', 'AMOUNT'];

    /**
     * Generates payment gateway request data P_SIGN signature.
     *
     * @throws VictoriabankException
     */
    public function generateSignature(array $params)
    {
        $mac = self::generateMac($params, self::MERCHANT_PSIGN_PARAMS);

        $private_key_resource = openssl_pkey_get_private($this->merchant_private_key, $this->merchant_private_key_passphrase);
        if ($private_key_resource === false) {
            $error = openssl_error_string();
            throw new VictoriabankException("Invalid merchant private key or passphrase: $error");
        }

        try {
            $signature = '';
            $sign_result = openssl_sign($mac, $signature, $private_key_resource, $this->signature_algo);

            if (!$sign_result) {
                $error = openssl_error_string();
                throw new VictoriabankException("Signature generation failed: $error");
            }

            return strtoupper(bin2hex($signature));
        } finally {
            if (PHP_VERSION_ID < 80000) {
                // phpcs:ignore Generic.PHP.DeprecatedFunctions.Deprecated -- PHP_VERSION_ID check performed before invocation.
                openssl_free_key($private_key_resource);
            }
        }
    }

    /**
     * Validates payment gateway response data P_SIGN signature.
     *
     * @throws VictoriabankException
     */
    public function verifySignature(array $params)
    {
        $mac = self::generateMac($params, self::GATEWAY_PSIGN_PARAMS);
        $signature_bin = hex2bin($params['P_SIGN']);

        $public_key_resource = openssl_pkey_get_public($this->bank_public_key);
        if ($public_key_resource === false) {
            $error = openssl_error_string();
            throw new VictoriabankException("Invalid bank public key: $error");
        }

        try {
            $verify_result = openssl_verify($mac, $signature_bin, $public_key_resource, $this->signature_algo);

            if ($verify_result === -1) {
                $error = openssl_error_string();
                throw new VictoriabankException("Signature verification failed: $error");
            }

            return $verify_result === 1;
        } finally {
            if (PHP_VERSION_ID < 80000) {
                // phpcs:ignore Generic.PHP.DeprecatedFunctions.Deprecated -- PHP_VERSION_ID check performed before invocation.
                openssl_free_key($public_key_resource);
            }
        }
    }

    /**
     * Assembles a control string on which a digital signature will be generated.
     *
     * Victoriabank e-Commerce Gateway merchant interface (CGI/WWW forms version)
     * Appendix A: P_SIGN creation/verification in the Merchant System
     *
     * @throws VictoriabankException
     */
    protected static function generateMac(array $params, array $psign_params)
    {
        // Format: {length1}{value1}{length2}{value2}...

        $mac = '';
        foreach ($psign_params as $key) {
            $val = (string) ($params[$key] ?? '');

            // Strict check for null/empty string to allow "0"
            if ($val === '') {
                throw new VictoriabankException("Empty P_SIGN parameter: $key");
            }

            $mac .= strlen($val) . $val;
        }

        return $mac;
    }
    //endregion
}
