<?php

namespace Victoriabank\Victoriabank;

use GuzzleHttp\Command\Guzzle\Description;
use Composer\InstalledVersions;

/**
 * Victoriabank API service description
 * @link https://ecomt.victoriabank.md/cardop/images/instruction-ecom-vb.zip
 */
class VictoriabankDescription extends Description
{
    private const PACKAGE_NAME = 'alexminza/victoriabank-sdk';
    private const DEFAULT_VERSION = 'dev';

    private static function detectVersion(): string
    {
        if (!class_exists(InstalledVersions::class)) {
            return self::DEFAULT_VERSION;
        }

        if (!InstalledVersions::isInstalled(self::PACKAGE_NAME)) {
            return self::DEFAULT_VERSION;
        }

        return InstalledVersions::getPrettyVersion(self::PACKAGE_NAME)
            ?? self::DEFAULT_VERSION;
    }

    public function __construct(array $options = [])
    {
        $version = self::detectVersion();
        $userAgent = "victoriabank-sdk-php/$version";

        $description = [
            // 'baseUrl' => 'https://vb059.vb.md/cgi-bin/cgi_link',
            'name' => 'Victoriabank e-Commerce Gateway API',
            'apiVersion' => '2.1',

            'operations' => [
                'baseOp' => [
                    'parameters' => [
                        'User-Agent' => [
                            'location' => 'header',
                            'default'  => $userAgent,
                        ],
                    ],
                ],

                'authorize' => [
                    'extends' => 'baseOp',
                    'httpMethod' => 'POST',
                    'uri' => '',
                    'summary' => 'Authorize payment',
                    'responseModel' => 'AuthorizationResponse',
                    'parameters' => [
                        'schema' => ['$ref' => 'AuthorizationRequest']
                    ],
                ],
                'complete' => [
                    'extends' => 'baseOp',
                    'httpMethod' => 'POST',
                    'uri' => '',
                    'summary' => 'Complete authorized transaction',
                    'responseModel' => 'AuthorizationResponse',
                    'parameters' => [
                        'schema' => ['$ref' => 'TransactionResponse']
                    ],
                ],
                'reverse' => [
                    'extends' => 'baseOp',
                    'httpMethod' => 'POST',
                    'uri' => '',
                    'summary' => 'Reverse authorized or completed transaction',
                    'responseModel' => 'TransactionResponse',
                    'parameters' => [
                        'schema' => ['$ref' => 'ReversalRequest']
                    ],
                ],
            ],

            'models' => [
                #region Generic Models
                'getResponse' => [
                    'type' => 'object',
                    'additionalProperties' => [
                        'location' => 'json'
                    ]
                ],
                'getRawResponse' => [
                    'type' => 'object',
                    'properties' => [
                        'body' => [
                            'type' => 'string',
                            'location' => 'body',
                            'filters' => ['strval']
                        ]
                    ]
                ],
                #endregion

                #region Schema-based Models
                'AuthorizationRequest' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'parameters' => [
                        'AMOUNT' => [
                            'type' => 'string',
                            'location' => 'formParam',
                            'required' => true,
                            'description' => 'Order total amount in float format with decimal point separator',
                            'minLength' => 1,
                            'maxLength' => 12,
                        ],
                        'CURRENCY' => [
                            'type' => 'string',
                            'location' => 'formParam',
                            'required' => true,
                            'description' => 'Order currency: 3-character currency code',
                            'pattern' => '/^[A-Z]{3}$/',
                            'minLength' => 3,
                            'maxLength' => 3,
                        ],
                        'ORDER' => [
                            'type' => 'string',
                            'location' => 'formParam',
                            'required' => true,
                            'description' => 'Merchant order ID',
                            'minLength' => 6,
                            'maxLength' => 32,
                        ],
                        'DESC' => [
                            'type' => 'string',
                            'location' => 'formParam',
                            'required' => true,
                            'description' => 'Order description',
                            'minLength' => 1,
                            'maxLength' => 50,
                        ],
                        'MERCH_NAME' => [
                            'type' => 'string',
                            'location' => 'formParam',
                            'required' => true,
                            'description' => 'Merchant name (recognizable by cardholder)',
                            'minLength' => 1,
                            'maxLength' => 50,
                        ],
                        'MERCH_URL' => [
                            'type' => 'string',
                            'location' => 'formParam',
                            'required' => true,
                            'description' => 'Merchant primary web site URL',
                            'minLength' => 1,
                            'maxLength' => 250,
                        ],
                        'MERCHANT' => [
                            'type' => 'string',
                            'location' => 'formParam',
                            'required' => true,
                            'description' => 'Merchant ID assigned by bank',
                            'pattern' => '/^\d{15}$/',
                            'minLength' => 15,
                            'maxLength' => 15,
                        ],
                        'TERMINAL' => [
                            'type' => 'string',
                            'location' => 'formParam',
                            'required' => true,
                            'description' => 'Merchant Terminal ID assigned by bank',
                            'pattern' => '/^\d{8}$/',
                            'minLength' => 8,
                            'maxLength' => 8,
                        ],
                        'EMAIL' => [
                            'type' => 'string',
                            'location' => 'formParam',
                            'required' => true,
                            'description' => 'Client e-mail address',
                            'maxLength' => 80,
                        ],
                        'MERCH_ADDRESS' => [
                            'type' => 'string',
                            'location' => 'formParam',
                            'required' => false,
                            'description' => 'Merchant company registered office address',
                            'maxLength' => 250,
                        ],
                        'TRTYPE' => [
                            'type' => 'string',
                            'location' => 'formParam',
                            'required' => true,
                            'description' => 'Transaction type',
                            'enum' => ['0'],
                            'default' => '0',
                            'minLength' => 1,
                            'maxLength' => 1,
                        ],
                        'COUNTRY' => [
                            'type' => 'string',
                            'location' => 'formParam',
                            'required' => false,
                            'description' => 'Merchant shop 2-character country code. Must be provided if merchant system is located in a country other than the gateway server\'s country.',
                            'pattern' => '/^[A-Z]{2}$/',
                            'minLength' => 2,
                            'maxLength' => 2,
                        ],
                        'MERCH_GMT' => [
                            'type' => 'string',
                            'location' => 'formParam',
                            'required' => false,
                            'description' => 'Merchant UTC/GMT time zone offset (e.g. –3). Must be provided if merchant system is located in a time zone other than the gateway server\'s time zone.',
                            'minLength' => 1,
                            'maxLength' => 5,
                        ],
                        'TIMESTAMP' => [
                            'type' => 'string',
                            'location' => 'formParam',
                            'required' => true,
                            'description' => 'Merchant transaction timestamp in GMT: YYYYMMDDHHMMSS. Timestamp difference between merchant server and e-Gateway server must not exceed 1 hour, otherwise e-Gateway will reject this transaction.',
                            'pattern' => '/^\d{14}$/',
                            'minLength' => 14,
                            'maxLength' => 14,
                        ],
                        'NONCE' => [
                            'type' => 'string',
                            'location' => 'formParam',
                            'required' => true,
                            'description' => 'Merchant nonce. Must be filled with 20-32 unpredictable random bytes in hexadecimal format. Must be present if MAC is used.',
                            'minLength' => 1,
                            'maxLength' => 64,
                        ],
                        'BACKREF' => [
                            'type' => 'string',
                            'location' => 'formParam',
                            'required' => true,
                            'description' => 'Merchant URL for redirecting the client after receiving transaction result.',
                            'minLength' => 1,
                            'maxLength' => 250,
                        ],
                        'P_SIGN' => [
                            'type' => 'string',
                            'location' => 'formParam',
                            'required' => true,
                            'description' => 'Merchant MAC in hexadecimal form.',
                            'minLength' => 1,
                            'maxLength' => 256,
                        ],
                        'LANG' => [
                            'type' => 'string',
                            'location' => 'formParam',
                            'required' => false,
                            'description' => 'Transaction forms language. By default are available forms in en, ro, ru. If need forms in another languages please contact gateway administrator.',
                            'enum' => ['en', 'ro', 'ru'],
                            'default' => 'en',
                            'minLength' => 2,
                            'maxLength' => 2,
                        ],
                    ],
                ],
                'AuthorizationResponse' => [
                    'type' => 'object',
                    'additionalProperties' => [
                        'location' => 'body',
                    ],
                    'properties' => [
                        'TERMINAL' => [
                            'type' => 'string',
                            'description' => 'Terminal ID (echo from request)',
                            'maxLength' => 8,
                        ],
                        'TRTYPE' => [
                            'type' => 'string',
                            'description' => 'Transaction type (echo from request)',
                            'maxLength' => 2,
                        ],
                        'ORDER' => [
                            'type' => 'string',
                            'description' => 'Order ID (echo from request)',
                            'minLength' => 6,
                            'maxLength' => 32,
                        ],
                        'AMOUNT' => [
                            'type' => 'string',
                            'description' => 'Amount (echo from request)',
                            'maxLength' => 12,
                        ],
                        'CURRENCY' => [
                            'type' => 'string',
                            'description' => 'Currency (echo from request)',
                            'maxLength' => 3,
                        ],
                        'ACTION' => [
                            'type' => 'string',
                            'description' => 'E-Gateway action code: 0 – Transaction successfully completed; 1 – Duplicate transaction detected; 2 – Transaction declined; 3 – Transaction processing fault.',
                            'enum' => ['0', '1', '2', '3'],
                        ],
                        'RC' => [
                            'type' => 'string',
                            'description' => 'Transaction response code (ISO-8583 Field 39)',
                            'maxLength' => 2,
                        ],
                        'APPROVAL' => [
                            'type' => 'string',
                            'description' => 'Client bank’s approval code (ISO-8583 Field 38). Can be empty if not provided by card management system.',
                            'maxLength' => 6,
                        ],
                        'RRN' => [
                            'type' => 'string',
                            'description' => 'Merchant bank’s retrieval reference number (ISO-8583 Field 37).',
                            'maxLength' => 12,
                        ],
                        'INT_REF' => [
                            'type' => 'string',
                            'description' => 'E-Commerce gateway internal reference number',
                            'minLength' => 1,
                            'maxLength' => 32,
                        ],
                        'TIMESTAMP' => [
                            'type' => 'string',
                            'description' => 'E-Commerce gateway timestamp in GMT: YYYYMMDDHHMMSS',
                            'pattern' => '/^\d{14}$/',
                            'minLength' => 14,
                            'maxLength' => 14,
                        ],
                        'NONCE' => [
                            'type' => 'string',
                            'description' => 'E-Commerce gateway nonce value. Will be filled with 8-32 unpredictable random bytes in hexadecimal format. Will be present if MAC is used.',
                            'minLength' => 1,
                            'maxLength' => 64,
                        ],
                        'P_SIGN' => [
                            'type' => 'string',
                            'description' => 'E-Commerce gateway MAC (Message Authentication Code) in hexadecimal form. Will be present if MAC is used.',
                            'minLength' => 1,
                            'maxLength' => 256,
                        ],
                        'ECI' => [
                            'type' => 'string',
                            'description' => 'Electronic Commerce Indicator: ECI=empty – Technical fault; ECI=05 - Secure electronic commerce transaction (fully 3-D Secure authenticated); ECI=06 - Non-authenticated security transaction at a 3-D Secure-capable merchant, and merchant attempted to authenticate the cardholder using 3-D Secure but was unable to complete the authentication because the issuer or cardholder does not participate in the 3-D Secure program; ECI=07 - Non-authenticated Security Transaction',
                            'maxLength' => 2,
                        ],
                    ],
                ],
                'CompletionRequest' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'parameters' => [
                        'ORDER' => [
                            'type' => 'string',
                            'location' => 'formParam',
                            'required' => true,
                            'description' => 'Merchant order ID from authorization request',
                            'minLength' => 6,
                            'maxLength' => 32,
                        ],
                        'AMOUNT' => [
                            'type' => 'string',
                            'location' => 'formParam',
                            'required' => true,
                            'description' => 'Transaction amount',
                            'maxLength' => 12,
                        ],
                        'CURRENCY' => [
                            'type' => 'string',
                            'location' => 'formParam',
                            'required' => true,
                            'description' => 'Currency name (same as in authorization)',
                            'pattern' => '/^[A-Z]{3}$/',
                        ],
                        'RRN' => [
                            'type' => 'string',
                            'location' => 'formParam',
                            'required' => true,
                            'description' => 'Retrieval reference number from authorization',
                            'maxLength' => 12,
                        ],
                        'INT_REF' => [
                            'type' => 'string',
                            'location' => 'formParam',
                            'required' => true,
                            'description' => 'Internal reference number from authorization',
                            'minLength' => 1,
                            'maxLength' => 32,
                        ],
                        'TRTYPE' => [
                            'type' => 'string',
                            'location' => 'formParam',
                            'required' => true,
                            'description' => 'Transaction type',
                            'enum' => ['21'],
                            'default' => '21',
                        ],
                        'TERMINAL' => [
                            'type' => 'string',
                            'location' => 'formParam',
                            'required' => true,
                            'description' => 'Merchant terminal ID',
                            'pattern' => '/^\d{8}$/',
                        ],
                        'TIMESTAMP' => [
                            'type' => 'string',
                            'location' => 'formParam',
                            'required' => true,
                            'description' => 'Merchant transaction timestamp in GMT',
                            'pattern' => '/^\d{14}$/',
                        ],
                        'NONCE' => [
                            'type' => 'string',
                            'location' => 'formParam',
                            'required' => true,
                            'description' => 'Merchant nonce (8-32 random bytes in hex)',
                            'minLength' => 1,
                            'maxLength' => 64,
                        ],
                        'P_SIGN' => [
                            'type' => 'string',
                            'location' => 'formParam',
                            'required' => true,
                            'description' => 'Merchant MAC in hexadecimal form',
                            'minLength' => 1,
                            'maxLength' => 256,
                        ],
                    ],
                ],
                'ReversalRequest' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'parameters' => [
                        'ORDER' => [
                            'type' => 'string',
                            'location' => 'formParam',
                            'required' => true,
                            'description' => 'Merchant order ID from authorization request',
                            'minLength' => 6,
                            'maxLength' => 32,
                        ],
                        'AMOUNT' => [
                            'type' => 'string',
                            'location' => 'formParam',
                            'required' => true,
                            'description' => 'Transaction amount',
                            'maxLength' => 12,
                        ],
                        'CURRENCY' => [
                            'type' => 'string',
                            'location' => 'formParam',
                            'required' => true,
                            'description' => 'Currency name (same as in authorization)',
                            'pattern' => '/^[A-Z]{3}$/',
                        ],
                        'RRN' => [
                            'type' => 'string',
                            'location' => 'formParam',
                            'required' => true,
                            'description' => 'Retrieval reference number from authorization',
                            'maxLength' => 12,
                        ],
                        'INT_REF' => [
                            'type' => 'string',
                            'location' => 'formParam',
                            'required' => true,
                            'description' => 'Internal reference number from authorization',
                            'minLength' => 1,
                            'maxLength' => 32,
                        ],
                        'TRTYPE' => [
                            'type' => 'string',
                            'location' => 'formParam',
                            'required' => true,
                            'description' => 'Transaction type',
                            'enum' => ['24'],
                            'default' => '24',
                        ],
                        'TERMINAL' => [
                            'type' => 'string',
                            'location' => 'formParam',
                            'required' => true,
                            'description' => 'Merchant terminal ID',
                            'pattern' => '/^\d{8}$/',
                        ],
                        'TIMESTAMP' => [
                            'type' => 'string',
                            'location' => 'formParam',
                            'required' => true,
                            'description' => 'Merchant transaction timestamp in GMT',
                            'pattern' => '/^\d{14}$/',
                        ],
                        'NONCE' => [
                            'type' => 'string',
                            'location' => 'formParam',
                            'required' => true,
                            'description' => 'Merchant nonce (8-32 random bytes in hex)',
                            'minLength' => 1,
                            'maxLength' => 64,
                        ],
                        'P_SIGN' => [
                            'type' => 'string',
                            'location' => 'formParam',
                            'required' => true,
                            'description' => 'Merchant MAC in hexadecimal form',
                            'minLength' => 1,
                            'maxLength' => 256,
                        ],
                    ],
                ],
                'TransactionResponse' => [
                    'type' => 'object',
                    'additionalProperties' => [
                        'location' => 'body',
                    ],
                    'properties' => [
                        'TERMINAL' => [
                            'type' => 'string',
                            'description' => 'Terminal ID',
                            'maxLength' => 8,
                        ],
                        'TRTYPE' => [
                            'type' => 'string',
                            'description' => 'Transaction type',
                            'maxLength' => 2,
                        ],
                        'ORDER' => [
                            'type' => 'string',
                            'description' => 'Order ID',
                            'minLength' => 6,
                            'maxLength' => 32,
                        ],
                        'AMOUNT' => [
                            'type' => 'string',
                            'description' => 'Transaction amount',
                            'maxLength' => 12,
                        ],
                        'CURRENCY' => [
                            'type' => 'string',
                            'description' => 'Currency code',
                            'maxLength' => 3,
                        ],
                        'ACTION' => [
                            'type' => 'string',
                            'description' => 'E-Gateway action code',
                            'enum' => ['0', '1', '2', '3'],
                        ],
                        'RC' => [
                            'type' => 'string',
                            'description' => 'Transaction response code',
                            'maxLength' => 2,
                        ],
                        'APPROVAL' => [
                            'type' => 'string',
                            'description' => 'Approval code',
                            'maxLength' => 6,
                        ],
                        'RRN' => [
                            'type' => 'string',
                            'description' => 'Retrieval reference number',
                            'maxLength' => 12,
                        ],
                        'INT_REF' => [
                            'type' => 'string',
                            'description' => 'Internal reference number',
                            'minLength' => 1,
                            'maxLength' => 32,
                        ],
                        'TIMESTAMP' => [
                            'type' => 'string',
                            'description' => 'Gateway timestamp in GMT',
                            'pattern' => '/^\d{14}$/',
                        ],
                        'NONCE' => [
                            'type' => 'string',
                            'description' => 'Gateway nonce value',
                            'minLength' => 1,
                            'maxLength' => 64,
                        ],
                        'P_SIGN' => [
                            'type' => 'string',
                            'description' => 'Gateway MAC',
                            'minLength' => 1,
                            'maxLength' => 256,
                        ],
                    ],
                ],
            ],
        ];

        parent::__construct($description, $options);
    }
}
