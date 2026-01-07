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

    public function __construct(?ClientInterface $client = null, ?DescriptionInterface $description = null, array $config = [])
    {
        $client = $client ?? new Client();
        $description = $description ?? new VictoriabankDescription();
        parent::__construct($client, $description, null, null, null, $config);
    }
}
