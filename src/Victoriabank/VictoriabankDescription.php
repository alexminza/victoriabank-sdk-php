<?php

declare(strict_types=1);

namespace Victoriabank\Victoriabank;

use GuzzleHttp\Command\Guzzle\Description;
use Composer\InstalledVersions;

/**
 * Victoriabank API service description
 *
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
        $version   = self::detectVersion();
        $userAgent = "victoriabank-sdk-php/$version";

        //region Params
        $orderParam = [
            'ORDER' => [
                'type' => 'string',
                'location' => 'formParam',
                'required' => true,
                'description' => 'Merchant order ID',
                'minLength' => 6,
                'maxLength' => 32,
            ],
        ];

        $amountParams = [
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
        ];

        $terminalTrTypeParams = [
            'TRTYPE' => [
                'type' => 'string',
                'location' => 'formParam',
                'required' => true,
                'description' => 'Transaction type',
                'minLength' => 1,
                'maxLength' => 3,
            ],
            'TERMINAL' => [
                'type' => 'string',
                'location' => 'formParam',
                'required' => true,
                'description' => 'Merchant Terminal ID assigned by bank.',
                'pattern' => '/^\d{8}$/',
                'minLength' => 8,
                'maxLength' => 8,
            ],
        ];

        $tranTrTypeParam = [
            'TRAN_TRTYPE' => [
                'type' => 'string',
                'location' => 'formParam',
                'required' => true,
                'description' => 'Checked transaction type',
                'minLength' => 1,
                'maxLength' => 3,
            ],
        ];

        $transactionSignParams = [
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
            'P_SIGN' => [
                'type' => 'string',
                'location' => 'formParam',
                'required' => true,
                'description' => 'Merchant MAC in hexadecimal form.',
                'minLength' => 1,
                'maxLength' => 512,
            ],
        ];

        $authorizeOrderParams = [
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
                'description' => 'Merchant primary web site URL in format https://www.merchantsitename.domain',
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
            'COUNTRY' => [
                'type' => 'string',
                'location' => 'formParam',
                'required' => false,
                'description' => 'Merchant shop 2-character country code. Must be provided if merchant system is located in a country other than the gateway server\'s country.',
                'default' => VictoriabankClient::DEFAULT_COUNTRY,
                'pattern' => '/^[A-Z]{2}$/i',
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
            'BACKREF' => [
                'type' => 'string',
                'location' => 'formParam',
                'required' => true,
                'description' => 'Merchant URL for redirecting the client after receiving transaction result.',
                'minLength' => 1,
                'maxLength' => 250,
            ],
            'LANG' => [
                'type' => 'string',
                'location' => 'formParam',
                'required' => false,
                'description' => 'Transaction forms language. By default are available forms in en, ro, ru. If need forms in another languages please contact gateway administrator.',
                'enum' => ['en', 'ro', 'ru'],
                'default' => VictoriabankClient::DEFAULT_LANGUAGE,
                'minLength' => 2,
                'maxLength' => 2,
            ],
        ];

        $transactionReferenceParams = [
            'RRN' => [
                'type' => 'string',
                'location' => 'formParam',
                'required' => true,
                'description' => 'Retrieval reference number from authorization response.',
                'minLength' => 12,
                'maxLength' => 12,
            ],
            'INT_REF' => [
                'type' => 'string',
                'location' => 'formParam',
                'required' => true,
                'description' => 'Internal reference number from authorization response.',
                'minLength' => 1,
                'maxLength' => 32,
            ],
        ];
        //endregion

        $orderValueParams  = array_merge($orderParam, $amountParams);
        $transactionParams = array_merge($terminalTrTypeParams, $transactionSignParams);

        $authorizeParameters = array_merge($orderValueParams, $authorizeOrderParams, $transactionParams);
        $completeParameters  = array_merge($orderValueParams, $transactionReferenceParams, $transactionParams);
        $checkParameters     = array_merge($terminalTrTypeParams, $tranTrTypeParam, $orderParam);

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
                    'responseModel' => 'getRawResponse', // TransactionResponse
                    'parameters' => $authorizeParameters,
                ],
                'complete' => [
                    'extends' => 'baseOp',
                    'httpMethod' => 'POST',
                    'uri' => '',
                    'summary' => 'Complete authorized transaction',
                    'responseModel' => 'getRawResponse', // 'TransactionResponse',
                    'parameters' => $completeParameters,
                ],
                'reverse' => [
                    'extends' => 'baseOp',
                    'httpMethod' => 'POST',
                    'uri' => '',
                    'summary' => 'Reverse authorized or completed transaction',
                    'responseModel' => 'getRawResponse', // 'TransactionResponse',
                    'parameters' => $completeParameters,
                ],
                'check' => [
                    'extends' => 'baseOp',
                    'httpMethod' => 'POST',
                    'uri' => '',
                    'summary' => 'Check transaction status',
                    'responseModel' => 'getRawResponse', // 'TransactionResponse',
                    'parameters' => $checkParameters,
                ],
            ],

            'models' => [
                //region Generic Models
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
                //endregion

                //region Schema-based Models
                'TransactionResponse' => [
                    'type' => 'object',
                    'additionalProperties' => [
                        'location' => 'body',
                    ],
                    'properties' => [
                        'TERMINAL' => [
                            'type' => 'string',
                            'description' => 'Terminal ID (echo from request)',
                            'required' => true,
                        ],
                        'TRTYPE' => [
                            'type' => 'string',
                            'description' => 'Transaction type (echo from request)',
                            'required' => true,
                        ],
                        'ORDER' => [
                            'type' => 'string',
                            'description' => 'Order ID (echo from request)',
                            'required' => true,
                        ],
                        'AMOUNT' => [
                            'type' => 'string',
                            'description' => 'Amount (echo from request)',
                            'required' => true,
                        ],
                        'CURRENCY' => [
                            'type' => 'string',
                            'description' => 'Currency (echo from request)',
                            'required' => true,
                        ],
                        'ACTION' => [
                            'type' => 'string',
                            'description' => 'E-Gateway action code: 0 – Transaction successfully completed; 1 – Duplicate transaction detected; 2 – Transaction declined; 3 – Transaction processing fault.',
                            'required' => true,
                        ],
                        // https://en.wikipedia.org/wiki/ISO_8583#Response_code
                        'RC' => [
                            'type' => 'string',
                            'description' => 'Transaction response code (ISO-8583 Field 39)',
                            'required' => true,
                        ],
                        'TEXT' => [
                            'type' => 'string',
                            'description' => 'Bank response status text',
                        ],
                        'APPROVAL' => [
                            'type' => 'string',
                            'description' => 'Client bank’s approval code (ISO-8583 Field 38). Can be empty if not provided by card management system.',
                            'required' => true,
                        ],
                        'RRN' => [
                            'type' => 'string',
                            'description' => 'Merchant bank’s retrieval reference number (ISO-8583 Field 37).',
                            'required' => true,
                        ],
                        'INT_REF' => [
                            'type' => 'string',
                            'description' => 'E-Commerce gateway internal reference number',
                            'required' => true,
                        ],
                        'TIMESTAMP' => [
                            'type' => 'string',
                            'description' => 'E-Commerce gateway timestamp in GMT: YYYYMMDDHHMMSS',
                            'required' => true,
                        ],
                        'NONCE' => [
                            'type' => 'string',
                            'description' => 'E-Commerce gateway nonce value. Will be filled with 8-32 unpredictable random bytes in hexadecimal format. Will be present if MAC is used.',
                            'required' => true,
                        ],
                        'P_SIGN' => [
                            'type' => 'string',
                            'description' => 'E-Commerce gateway MAC (Message Authentication Code) in hexadecimal form. Will be present if MAC is used.',
                            'required' => true,
                        ],
                        // https://en.wikipedia.org/wiki/ISO/IEC_7812
                        // https://en.wikipedia.org/wiki/Payment_card_number#Issuer_identification_number_(IIN)
                        'BIN' => [
                            'type' => 'string',
                            'description' => 'Card Bank Identification Number',
                        ],
                        'CARD' => [
                            'type' => 'string',
                            'description' => 'Masked card number',
                        ],
                        'AUTH' => [
                            'type' => 'string',
                        ],
                        'ECI' => [
                            'type' => 'string',
                            'description' => 'Electronic Commerce Indicator (ECI): ECI=empty – Technical fault; ECI=05 - Secure electronic commerce transaction (fully 3-D Secure authenticated); ECI=06 - Non-authenticated security transaction at a 3-D Secure-capable merchant, and merchant attempted to authenticate the cardholder using 3-D Secure but was unable to complete the authentication because the issuer or cardholder does not participate in the 3-D Secure program; ECI=07 - Non-authenticated Security Transaction',
                        ],
                    ],
                ],
                //endregion
            ],
        ];

        parent::__construct($description, $options);
    }
}
