<?php
/**
 * Created by PhpStorm.
 * User: arekk
 * Date: 04.04.2018
 * Time: 13:15
 */

namespace TopConcepts\Klarna\Models;


use OxidEsales\Eshop\Application\Model\Address;
use OxidEsales\Eshop\Application\Model\Country;
use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\Eshop\Core\Field;
use TopConcepts\Klarna\Tests\Unit\ModuleUnitTestCase;

/**
 * Class KlarnaUserTest
 * @package TopConcepts\Klarna\Models
 * @covers \TopConcepts\Klarna\Models\KlarnaUser
 */
class KlarnaUserTest extends ModuleUnitTestCase
{

    public function setUp()
    {
        parent::setUp();
    }

    public function isCreatableDataProvider()
    {
        return [
            [KlarnaUser::NOT_EXISTING, true],
            [KlarnaUser::REGISTERED, false],
            [KlarnaUser::NOT_REGISTERED, true],
            [KlarnaUser::LOGGED_IN, false]
        ];
    }

    /**
     * @dataProvider isCreatableDataProvider
     * @param $type
     * @param $result
     */
    public function testIsCreatable($type, $result)
    {
        $oUser = oxNew(User::class);
        $oUser->kl_setType($type);
        $this->assertEquals($result,  $oUser->isCreatable());
    }

    public function testSave()
    {

    }

    public function testLogin()
    {

    }

    public function testResolveLocale()
    {

    }

    public function testGetCountryISO()
    {

    }

    public function testLoadByEmail()
    {

    }

    public function loadByEmailDataProvider()
    {

    }

    public function testLogout()
    {
        $this->setSessionParam('klarna_checkout_order_id', 'some_fake_id');

        $oUser = oxNew(User::class);
        $result = $oUser->logout();

        $this->assertNull($this->getSessionParam('klarna_checkout_order_id'));
        $this->assertTrue($result);

    }

    public function resolveCountryDataProvider()
    {
        return [
            ['a7c40f631fc920687.20179984', 'DE'],
            ['a7c40f6320aeb2ec2.72885259', 'AT'],
            ['8f241f11095306451.36998225', 'AF'],

        ];
    }

    /**
     * @dataProvider resolveCountryDataProvider
     * @param $countryId
     * @param $iso
     */
    public function testResolveCountry($countryId, $iso)
    {
        $oUser = oxNew(User::class);
        $oUser->oxuser__oxcountryid = new Field($countryId, Field::T_RAW);

        $this->assertEquals($iso, $oUser->resolveCountry());
    }

    public function clearDeliveryAddressDataProvider()
    {
        return [
            ['41b545c65fe99ca2898614e563a7108b', 1, false],
            ['41b545c65fe99ca2898614e563a7108a', 0, true]
        ];
    }

    /**
     * @dataProvider clearDeliveryAddressDataProvider
     * @param $addressId
     * @param $isTemp
     * @param $loaded
     */
    public function testClearDeliveryAddress($addressId, $isTemp, $loaded)
    {
        $this->setSessionParam('deladrid', $addressId);
        $this->setSessionParam('blshowshipaddress', 1);

        // Prepare temporary address
        $oAddress = oxNew(Address::class);
        $oAddress->load('41b545c65fe99ca2898614e563a7108b');
        $oAddress->oxaddress__kltemporary = new Field($isTemp, Field::T_RAW);
        $oAddress->save();


        $oUser = oxNew(User::class);
        $oUser->clearDeliveryAddress();

        $this->assertNull($this->getSessionParam('deladrid'));
        $this->assertEquals(0, $this->getSessionParam('blshowshipaddress'));

        $oAddress = oxNew(Address::class);
        $oAddress->load($addressId);
        $this->assertEquals($loaded, $oAddress->isLoaded());
    }

    public function countryISOProvider()
    {
        return [
            ['8f241f110953facc6.31621036', 'AW'],
            ['a7c40f632a0804ab5.18804076', 'GB']
        ];
    }

    /**
     * @dataProvider countryISOProvider
     * @param $countryId
     * @param $expectedResult
     */
    public function testGetUserCountryISO2($countryId, $expectedResult)
    {
        $oUser = oxNew(User::class);
        $oUser->oxuser__oxcountryid = new Field($countryId, Field::T_RAW);

        $result = $oUser->getUserCountryISO2();

        $this->assertEquals($expectedResult, $result);
    }

    public function paymentDataProvider()
    {
        return [
            ['0000-00-00', null, true],
            ['1988-01-01', null, false],
            ['1988-01-01', '41b545c65fe99ca2898614e563a7108f', false]
        ];
    }

    /**
     * @dataProvider paymentDataProvider
     * @param $bday
     * @param $deladrid
     * @param $bday_resultIsNull
     */
    public function testGetKlarnaPaymentData($bday, $deladrid, $bday_resultIsNull)
    {
        $oUser = oxNew(User::class);
        $oUser->load('oxdefaultadmin');
        $oUser->oxuser__oxbirthdate = new Field($bday, Field::T_RAW);
        $this->setSessionParam('deladrid', $deladrid);
        $this->setSessionParam('userDataHash', null);

        $result = $oUser->getKlarnaPaymentData();

        $this->assertTrue($bday_resultIsNull === is_null($result['customer']['date_of_birth']));
        $this->assertEquals($result['billing_address'] !== $result['shipping_address'],  boolval($deladrid));
        $this->assertNotNull($this->getSessionParam('userDataHash'));
    }

    public function isWritableDataProvider()
    {
        return [
            [KlarnaUser::NOT_EXISTING, false],
            [KlarnaUser::REGISTERED, false],
            [KlarnaUser::NOT_REGISTERED, true],
            [KlarnaUser::LOGGED_IN, true],
        ];
    }

    /**
     * @dataProvider isWritableDataProvider
     * @param $type
     * @param $result
     */
    public function testIsWritable($type, $result)
    {
        $oUser = oxNew(User::class);
        $oUser->kl_setType($type);
        $this->assertEquals($result,  $oUser->isWritable());
    }

    public function updateDeliveryAddressDataProvider()
    {
        $aAddress = [
            'name' => 'Zyggy',
            'street' => 'qwdqw'
        ];

        return [
            [$aAddress, true, 'addressId', true],
            [$aAddress, true, false, true],
        ];
    }

    /**
     * @dataProvider updateDeliveryAddressDataProvider
     * @param $aAddressData
     * @param $isValid
     * @param $klExists
     * @param $isFake
     */
    public function testUpdateDeliveryAddress($aAddressData, $isValid, $klExists, $isFake)
    {
        $oAddress = $this->getMock(Address::class, ['isValid', 'klExists']);
        $oAddress->expects($this->once())
            ->method('isValid')->willReturn($isValid);
        $oAddress->expects($this->once())
            ->method('klExists')->willReturn($klExists);


        $oUser = $this->getMock(User::class, ['buildAddress','isFake', 'updateSessionDeliveryAddressId']);
        $oUser->expects($this->once())->method('buildAddress')->willReturn($oAddress);
        $oUser->expects($this->any())->method('isFake')->willReturn($isFake);
        $oUser->expects($this->once())->method('updateSessionDeliveryAddressId');

        $oUser->updateDeliveryAddress($aAddressData);

    }

//    /**
//     * @covers       \TopConcepts\Klarna\Models\KlarnaUser::buildAddress()
//     * @dataProvider updateDeliveryAddressDataProvider
//     * @param $aAddressData
//     */
//    public function testBuildAddress($aAddressData)
//    {
//        $oUser = oxNew(User::class);
//        $oUser->updateDeliveryAddress($aAddressData);
//
//    }

    /**
     * @dataProvider isFakeDataProvider
     * @param $type
     */
    public function testKl_getType($type)
    {
        $oUser = oxNew(User::class);
        $oUser->kl_setType($type);
        $this->assertEquals($type, $oUser->kl_getType());
    }

    public function deliveryCountryDataProvider()
    {
        return [
            ['KCO', 'DE', null, 'a7c40f631fc920687.20179984'],
            ['KCO', null, 'a7c40f631fc920687.20179984', 'a7c40f631fc920687.20179984'],
            ['KCO', null, null, 'a7c40f631fc920687.20179984'],
            ['KP', 'AT', 'a7c40f6320aeb2ec2.72885259', 'a7c40f6320aeb2ec2.72885259'],
            ['KP', null, null, 'a7c40f631fc920687.20179984'],
        ];
    }

    /**
     * @dataProvider deliveryCountryDataProvider
     * @param $mode
     * @param $countryISO
     * @param $userCountryId
     * @param $expectedId
     */
    public function testGetKlarnaDeliveryCountry($mode, $countryISO, $userCountryId, $expectedId)
    {
        $this->setModuleMode($mode);
        $this->setModuleConfVar('sKlarnaDefaultCountry', 'DE');
        $this->setSessionParam('sCountryISO', $countryISO);

        $oUser = oxNew(User::class);
        $oUser->oxuser__oxcountryid = new Field($userCountryId, Field::T_RAW);
        $result =$oUser->getKlarnaDeliveryCountry();

        $oCountry = oxNew(Country::class);
        $oCountry->load($expectedId);
        $this->assertEquals($oCountry, $result);
    }

    public function testKl_setType()
    {
        $oUser = oxNew(User::class);
        $oUser->kl_setType('myType');
        $this->assertEquals('myType', $oUser->kl_getType());
    }

    public function isFakeDataProvider()
    {
        return [
            [KlarnaUser::NOT_EXISTING, '', true],
            [KlarnaUser::REGISTERED, 'aaa', true],
            [KlarnaUser::NOT_REGISTERED, '', true],
            [KlarnaUser::LOGGED_IN, '', true],
            [KlarnaUser::LOGGED_IN, 'aaa', false],
        ];
    }

    /**
     * @dataProvider isFakeDataProvider
     * @param $type
     * @param $pass
     * @param $result
     */
    public function testIsFake($type, $pass, $result)
    {
        $oUser = oxNew(User::class);
        $oUser->kl_setType($type);
        $oUser->oxuser__oxpassword = new Field($pass);
        $this->assertEquals($result,  $oUser->isFake());
    }

    public function getAttachmentsDataProvider()
    {
        return [
            [false, ['content_type' => 'application/vnd.klarna.internal.emd-v2+json',
                    'body'         => json_encode(['one', 'two'])]],
            [true, null],
        ];
    }

    /**
     * @dataProvider getAttachmentsDataProvider
     * @param $isFake
     * @param $expectedResult
     */
    public function testGetAttachmentsData($isFake, $expectedResult)
    {
        $oUser = $this->getMock(User::class, ['isFake', 'getEMD']);
        $oUser->expects($this->any())->method('isFake')->willReturn($isFake);
        $oUser->expects($this->any())->method('getEMD')->willReturn(['one', 'two']);

        $this->assertEquals($expectedResult, $oUser->getAttachmentsData());

    }

    public function testSaveHash()
    {
        $toSave = 'hash';
        $oUser  = oxNew(User::class);
        $oUser->saveHash($toSave);
        $this->assertEquals('hash', $this->getSessionParam('userDataHash'));
    }

    /**
     * @dataProvider modeDataProvider
     * @param $mode
     */
    public function testChangeUserData($mode)
    {
        $this->setModuleMode($mode);

        $oUser = oxNew(User::class);
        $oUser->load('oxdefaultadmin');
        $this->setLanguage(1);


        $this->markTestIncomplete();
        $oUser->changeUserData('name', 'pass', 'pass', [], []);

        $this->assertEquals($oUser->getUserCountryISO2(), $this->getSessionParam('sCountryISO'));
    }

    public function getKlarnaDataProvider()
    {
        return [
            [null, null, []],
            ['DE', 1, ['billing_address']],
        ];
    }

    /**
     * @dataProvider getKlarnaDataProvider
     * @param $selectedCountry
     * @param $invadr
     */
    public function testGetKlarnaData($selectedCountry, $invadr, $resultKeys)
    {
        $this->setModuleConfVar('blKlarnaEnablePreFilling', false);
        $this->setRequestParameter('selected-country', $selectedCountry);
        $this->setSessionParam('invadr', $invadr);

        $oUser  = oxNew(User::class);
        $result = $oUser->getKlarnaData();

        $this->assertEquals(array_keys($result), $resultKeys);
        $this->assertNull($this->getSessionParam('invadr'));
    }

    public function getKlarnaDataProvider_PFE()
    {

        return [
            [0, 0, null, null, ['customer']],
            [0, 0, null, 1, ['customer', 'billing_address']],
            [1, 0, null, null, ['customer']],
            [3, 0, null, null, ['customer', 'billing_address']],
            [2, 1, '41b545c65fe99ca2898614e563a7108a', null, ['customer', 'billing_address', 'shipping_address']],
        ];
    }

    /**
     * @dataProvider getKlarnaDataProvider_PFE
     * @param $userType
     * @param $showSippingAddress
     * @param $addressId
     * @param $resultKeys
     */
    public function testGetKlarnaData_PreFillingEnabled($userType, $showSippingAddress, $addressId, $invadr, $resultKeys)
    {
        $this->setModuleConfVar('blKlarnaEnablePreFilling', true);
        $this->setSessionParam('blshowshipaddress', $showSippingAddress);
        $this->setSessionParam('deladrid', $addressId);
        $this->setSessionParam('invadr', $invadr);

        $oUser = oxNew(User::class);
        $oUser->load('92ebae5067055431aeaaa6f75bd9a131');
        $oUser->kl_setType($userType);
        $result = $oUser->getKlarnaData();

        $this->assertEquals(array_keys($result), $resultKeys);
    }

    /**
     * @dataProvider deliveryAddressDataProvider
     * @param $showShippingAddress
     * @param $addressId
     * @param $isLoaded
     */
    public function testGetDelAddressInfo($showShippingAddress, $addressId, $isLoaded)
    {
        $this->setSessionParam('blshowshipaddress', $showShippingAddress);
        $this->setSessionParam('deladrid', $addressId);

        $oUser  = oxNew(User::class);
        $result = $oUser->getDelAddressInfo();

        $this->assertEquals($isLoaded, $result->isLoaded());
        $this->assertNotEmpty($result->oxaddress__oxcountry->value);

    }

    public function deliveryAddressDataProvider()
    {
        return [
            [1, '41b545c65fe99ca2898614e563a7108a', true],
            [1, '41b545c65fe99ca2898614e563a7108f', true],
        ];
    }

    public function testGetDelAddressInfo_null()
    {
        $this->setSessionParam('blshowshipaddress', 0);
        $this->setSessionParam('deladrid', 'dawdawdawd');

        $oUser  = oxNew(User::class);
        $result = $oUser->getDelAddressInfo();

        $this->assertNull($result);

    }

    /**
     * @dataProvider userTypeDataProvider
     * @param $userId
     * @param $session_usr
     * @param $expectedResult
     */
    public function testKl_checkUserType($userId, $session_usr, $expectedResult)
    {
        $this->setSessionParam('usr', $session_usr);
        $oUser = $this->getMock(User::class, ['getId']);
        $oUser->expects($this->once())
            ->method('getId')->willReturn($userId);

        $this->assertEquals($expectedResult, $oUser->kl_checkUserType());
    }

    public function userTypeDataProvider()
    {
        return [
            ['id', null, KlarnaUser::NOT_REGISTERED],
            ['id', 'id', KlarnaUser::LOGGED_IN],
        ];
    }

    /**
     * @dataProvider userDeliveryDataProvider
     * @param $resAddressId
     * @param $newAddressId
     * @param $isFake
     * @param $showShippingAddress
     */
    public function testUpdateSessionDeliveryAddressId($resAddressId, $newAddressId, $isFake, $showShippingAddress)
    {
        $oUser = $this->getMock(User::class, ['isFake']);
        $oUser->expects($this->once())
            ->method('isFake')->willReturn($isFake);

        $this->setSessionParam('deladrid', 'old-fake-id');

        $oUser->updateSessionDeliveryAddressId($newAddressId);

        $this->assertEquals($resAddressId, $this->getSessionParam('deladrid'));
        $this->assertEquals($showShippingAddress, $this->getSessionParam('blshowshipaddress'));


    }

    public function userDeliveryDataProvider()
    {
        return [
            ['new-id', 'new-id', true, 1],
            ['new-id', 'new-id', false, 1],
            ['new-id', 'new-id', true, 1],
            ['old-fake-id', null, false, null],
        ];
    }

    /**
     * @dataProvider userCurrencyDataProvider
     * @param $countryId
     * @param $expectedCurrency
     */
    public function testGetKlarnaPaymentCurrency($countryId, $expectedCurrency)
    {

        $oUser                      = oxNew(User::class);
        $oUser->oxuser__oxcountryid = new Field($countryId, Field::T_RAW);
        $result                     = $oUser->getKlarnaPaymentCurrency();

        $this->assertEquals($result, $expectedCurrency);
    }

    public function userCurrencyDataProvider()
    {
        // oxcountryid, oxcurrencyiso
        return [
            ['a7c40f632848c5217.53322339', 'SEK'],
            ['8f241f11096176795.61257067', 'NOK'],
            ['8f241f110957e6ef8.56458418', 'DKK'],
            ['a7c40f631fc920687.20179984', 'EUR'],
            ['a7c40f63293c19d65.37472814', 'EUR'],
            ['a7c40f632cdd63c52.64272623', 'EUR'],
            ['a7c40f6320aeb2ec2.72885259', 'EUR'],
            ['a7c40f632a0804ab5.18804076', 'GBP'],
            ['a7c40f632a0804ab5.18804076', 'GBP'],
            ['8f241f1109624d3f8.50953605', null],
        ];
    }

    public function modeDataProvider()
    {
        return [
            ['KP'],
            ['KCO'],
        ];
    }
}
