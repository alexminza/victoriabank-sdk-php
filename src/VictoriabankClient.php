<?php

namespace Victoriabank\Victoriabank;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Command\Guzzle\DescriptionInterface;
use GuzzleHttp\Command\Guzzle\GuzzleClient;
use GuzzleHttp\Command\Result;

/**
 * Victoriabank client
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
    protected $merchant_private_key;

    /**
     * @var string
     */
    protected $bank_public_key;

    /**
     * @var string
     */
    protected $psign_algo = self::P_SIGN_HASH_ALGO_SHA256;

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

    public function setPSignAlgo(string $psign_algo)
    {
        $this->psign_algo = $psign_algo;
        return $this;
    }
    //endregion

    /**
     * Authorize payment
     */
    public function authorize(array $authorize_data): Result
    {
        $args = $authorize_data;
        $args['TRTYPE'] = self::TRTYPE_AUTHORIZATION;
        
        [
            'grant_type' => $grant_type,
            'username' => $username,
            'password' => $password,
            'refresh_token' => $refresh_token
        ];

        return parent::authorize($args);
    }

    /**
     * Merchant transaction timestamp in GMT: YYYYMMDDHHMMSS.
     */
    protected function getTimestamp()
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
            : (string)$hours;

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
     * Merchant MAC in hexadecimal form.
     */
    public static function generatePSign(string $order, string $nonce, string $timestamp, string $tr_type, float $amount)
    {

    }
}
