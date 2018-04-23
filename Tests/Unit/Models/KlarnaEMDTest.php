<?php

namespace TopConcepts\Klarna\Tests\Unit\Models;


use OxidEsales\Eshop\Application\Model\User;
use TopConcepts\Klarna\Models\EmdPayload\KlarnaCustomerAccountInfo;
use TopConcepts\Klarna\Models\EmdPayload\KlarnaPaymentHistoryFull;
use TopConcepts\Klarna\Models\KlarnaEMD;
use TopConcepts\Klarna\Tests\Unit\ModuleUnitTestCase;

class KlarnaEMDTest extends ModuleUnitTestCase
{
    /**
     * @param $data
     * @dataProvider getAttachmentsDataProvider
     */
    public function testGetAttachments($data, $expectedResult)
    {
        $this->setModuleConfVar('blKlarnaEmdCustomerAccountInfo', $data['blKlarnaEmdCustomerAccountInfo']);
        $this->setModuleConfVar('blKlarnaEmdPaymentHistoryFull', $data['blKlarnaEmdPaymentHistoryFull']);

        $klarnaEMD = oxNew(KlarnaEMD::class);

        $oUser               = oxNew(User::class);
        $oMockCustomerInfo   = $this->createStub(KlarnaCustomerAccountInfo::class, [
            'getCustomerAccountInfo' =>
                ['customer_account_info' =>
                     [
                         [
                             'unique_account_identifier' => "test_id",
                             'account_registration_date' => "2018-04-20T15:53:40Z",
                             'account_last_modified'     => "2018-04-20T15:53:40Z",
                         ],
                     ],
                ],
        ]);
        $oMockPaymentHistory = $this->createStub(KlarnaPaymentHistoryFull::class, [
            'getPaymentHistoryFull' =>
                ['payment_history_full' =>
                     [
                         ['test' => 'orderhistory'],
                     ],
                ],
        ]);
        \oxTestModules::addModuleObject(KlarnaCustomerAccountInfo::class, $oMockCustomerInfo);
        \oxTestModules::addModuleObject(KlarnaPaymentHistoryFull::class, $oMockPaymentHistory);

        $result = $klarnaEMD->getAttachments($oUser);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     *
     */
    public function getAttachmentsDataProvider()
    {
        return [
            [
                [
                    'blKlarnaEmdCustomerAccountInfo' => 0,
                    'blKlarnaEmdPaymentHistoryFull'  => 0,
                ],
                [],
            ],
            [
                [
                    'blKlarnaEmdCustomerAccountInfo' => 1,
                    'blKlarnaEmdPaymentHistoryFull'  => 0,
                ],
                [
                    'customer_account_info' =>
                        [
                            [
                                'unique_account_identifier' => "test_id",
                                'account_registration_date' => "2018-04-20T15:53:40Z",
                                'account_last_modified'     => "2018-04-20T15:53:40Z",
                            ],
                        ],
                ],
            ], [
                [
                    'blKlarnaEmdCustomerAccountInfo' => 0,
                    'blKlarnaEmdPaymentHistoryFull'  => 1,
                ],
                [
                    'payment_history_full' => [
                        [
                            'test' => 'orderhistory',
                        ],
                    ],
                ],
            ], [
                [
                    'blKlarnaEmdCustomerAccountInfo' => 1,
                    'blKlarnaEmdPaymentHistoryFull'  => 1,
                ],
                [
                    'customer_account_info' => [
                        [
                            'unique_account_identifier' => "test_id",
                            'account_registration_date' => "2018-04-20T15:53:40Z",
                            'account_last_modified'     => "2018-04-20T15:53:40Z",
                        ],
                    ],
                    'payment_history_full'  => [
                        [
                            'test' => 'orderhistory',
                        ],
                    ],
                ],
            ],
        ];
    }
}

