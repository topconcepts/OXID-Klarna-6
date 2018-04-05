<?php

namespace TopConcepts\Klarna\Models;


use TopConcepts\Klarna\Core\KlarnaConsts;
use TopConcepts\Klarna\Core\KlarnaFormatter;
use TopConcepts\Klarna\Core\KlarnaUtils;
use OxidEsales\Eshop\Application\Model\Address;
use OxidEsales\Eshop\Application\Model\Country;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\Request;

/**
 * Class Klarna_oxUser extends default OXID oxUser class to add
 * Klarna payment related additional parameters and logic
 */
class KlarnaUser extends KlarnaUser_parent
{
    const NOT_EXISTING   = 0;    // email not exists in user table
    const REGISTERED     = 1;    // registered but not logged in
    const NOT_REGISTERED = 2;    // not registered returning customer
    const LOGGED_IN      = 3;    // logged in user

    /**
     *
     * @var int  user type
     */
    protected $_type;

    /**
     * @var string user data checksum
     */
    protected $_userDataHash = '';

    /**
     * @var string user country ISO
     */
    protected $_countryISO;

    /**
     * @return array
     */
    public function getKlarnaData()
    {
        $shippingAddress = null;
        $result          = array();

        if ((bool)KlarnaUtils::getShopConfVar('blKlarnaEnablePreFilling')) {
            $customer = array(
                'type' => 'person',
            );

            if ($this->oxuser__oxbirthdate->value && $this->oxuser__oxbirthdate->value != '0000-00-00') {
                $customer['date_of_birth'] = $this->oxuser__oxbirthdate->value;
            }

            $result = array(
                'customer' => $customer,
            );

            $blShowShippingAddress = (bool)Registry::getSession()->getVariable('blshowshipaddress');

            if ($this->_type == self::LOGGED_IN || $this->_type == self::NOT_REGISTERED) {
                $billingAddress            = KlarnaFormatter::oxidToKlarnaAddress($this);
                $result['billing_address'] = isset($billingAddress) ? $billingAddress : null;

                if (Registry::getSession()->hasVariable('deladrid') && $blShowShippingAddress) {
                    $delAddressOxid = Registry::getSession()->getVariable('deladrid');
                    $oAddress       = oxNew(Address::class);
                    $oAddress->load($delAddressOxid);
                    $shippingAddress            = KlarnaFormatter::oxidToKlarnaAddress($oAddress);
                    $result['shipping_address'] = isset($shippingAddress) ? $shippingAddress : null;
                }

            } elseif ($this->_type == self::NOT_EXISTING && Registry::getSession()->hasVariable('invadr')) {

                $this->assign(Registry::getSession()->getVariable('invadr'));
                $billingAddress            = KlarnaFormatter::oxidToKlarnaAddress($this);
                $result['billing_address'] = isset($billingAddress) ? $billingAddress : null;
            }
        }

        if ($sCountryISO = Registry::get(Request::class)->getRequestEscapedParameter('selected-country')) {
            if (Registry::getSession()->hasVariable('invadr')) {
                Registry::getSession()->deleteVariable('invadr');
            }
            $result['billing_address']['country'] = $sCountryISO;
            Registry::getSession()->setVariable('sCountryISO', $sCountryISO);
        }

        return $result;
    }

    /**
     * @return array
     */
    public function getKlarnaPaymentData()
    {
        $customer = array(
            'date_of_birth' => null,
        );
        if (isset($this->oxuser__oxbirthdate->value) && $this->oxuser__oxbirthdate->value !== '0000-00-00') {
            $customer['date_of_birth'] = $this->oxuser__oxbirthdate->value;
        }

        $billingAddress = KlarnaFormatter::oxidToKlarnaAddress($this);

        if (Registry::getSession()->hasVariable('deladrid')) {
            $oAddress = oxNew(Address::class);
            $oAddress->load(Registry::getSession()->getVariable('deladrid'));
            $shippingAddress = KlarnaFormatter::oxidToKlarnaAddress($oAddress);
        }

        $aUserData = array(
            'billing_address'  => $billingAddress,
            'shipping_address' => isset($shippingAddress) ? $shippingAddress : $billingAddress,
            'customer'         => $customer,
            'attachment'       => $this->addAttachmentsData(),
        );

        if (!Registry::getSession()->getVariable('userDataHash'))
            $this->saveHash(md5(json_encode($aUserData)));

        return $aUserData;
    }

    /**
     * Get user country ISO2
     *
     * @return string|null
     */
    public function getUserCountryISO2()
    {
        // always reset cache for tests
        if (defined('OXID_PHP_UNIT')) {
            $this->_sUserCountryISO2 = null;
        }

        if ($this->_sUserCountryISO2 === null) {
            $oCountry = oxNew(Country::class);
            $oCountry->load($this->getFieldData('oxcountryid'));
            if ($oCountry->exists()) {
                $this->_sUserCountryISO2 = $oCountry->getFieldData('oxisoalpha2');
            }
        }

        return $this->_sUserCountryISO2;
    }

    /**
     * Set user countryId
     * @return string country ISO alfa2
     */
    public function resolveCountry()
    {
        $oCountry    = $this->getKlarnaDeliveryCountry();
        $sCountryISO = $oCountry->getFieldData('oxisoalpha2');
        Registry::getSession()->setVariable('sCountryISO', $sCountryISO);

        return strtoupper($sCountryISO);
    }

    /**
     * @return Country
     */
    public function getKlarnaDeliveryCountry()
    {
        $oCountry = oxNew(Country::class);
        // KCO
        if (KlarnaUtils::isKlarnaCheckoutEnabled()) {
            if (!($sCountryISO = Registry::getSession()->getVariable('sCountryISO'))) {

                if (!($sCountryId = $this->getFieldData('oxcountryid'))) {
                    $sCountryISO = KlarnaUtils::getShopConfVar('sKlarnaDefaultCountry');
                    $sCountryId  = $oCountry->getIdByCode($sCountryISO);
                }

            } else {
                $sCountryId = $oCountry->getIdByCode($sCountryISO);
            }
            $this->oxuser__oxcountryid = new Field($sCountryId, Field::T_RAW);
        }


        // KP
        if (KlarnaUtils::isKlarnaPaymentsEnabled()) {
            if (!($sCountryId = $this->getFieldData('oxcountryid'))) {
                $sCountryISO = KlarnaUtils::getShopConfVar('sKlarnaDefaultCountry');
                $sCountryId  = $oCountry->getIdByCode($sCountryISO);
            }
        }
        $oCountry->load($sCountryId);

        return $oCountry;
    }

    /**
     * @return string
     */
    public function getCountryISO()
    {
        if ($this->_countryISO)
            return $this->_countryISO;
        else
            return $this->_countryISO = $this->resolveCountry();
    }

    /**
     * @param $sCountryISO
     * @return string user locale
     */
    public function resolveLocale($sCountryISO)
    {
        return KlarnaUtils::resolveLocale($sCountryISO);
    }

    /**
     * Check if user exists by its email, allow users with passwords as well, skip admin
     * @param string $sEmail
     * @return klarnaUser -1 cant use this email, 0 no user found, 1 user loaded
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     */
    public function loadByEmail($sEmail)
    {
        if ($this->_type == self::LOGGED_IN)
            return $this;

        $exists = false;
        if ($sEmail) {
            $oDb = DatabaseProvider::getDb();
            $sQ  = "SELECT `oxid` FROM `oxuser` WHERE `oxusername` = " . $oDb->quote($sEmail);
            if (!Registry::getConfig()->getConfigParam('blMallUsers')) {
                $sQ .= " AND `oxshopid` = " . $oDb->quote(Registry::getConfig()->getShopId());
            }
            $sId    = $oDb->getOne($sQ);
            $exists = $this->load($sId);
        }

        if ($exists) {
            if (empty($this->oxuser__oxpassword->value)) {
                $this->_type = self::NOT_REGISTERED;
            } else {
                $this->_type = self::REGISTERED;
            }
        } else {
            $this->_type = self::NOT_EXISTING;
            $this->setFakeUserId();
        }

        $this->addToGroup('oxidnotyetordered');

        return $this;
    }

    /**
     * @return int
     */
    public function kl_getType()
    {
        return $this->_type;
    }

    /**
     * @param int $iType
     */
    public function kl_setType($iType)
    {
        $this->_type = $iType;
    }

    /**
     * @return bool
     */
    public function isFake()
    {

        return ($this->kl_getType() && $this->kl_getType() !== self::LOGGED_IN) || !$this->oxuser__oxpassword->value;
    }

    /**
     * Checks if we can save user data to the database
     * @return bool
     */
    public function isWritable()
    {
        return $this->_type == self::LOGGED_IN || $this->_type == self::NOT_REGISTERED;
    }

    /**
     * @return bool
     */
    public function isCreatable()
    {
        return $this->_type == self::NOT_EXISTING || $this->_type == self::NOT_REGISTERED;
    }

    /**
     * Saves delivery address in the database
     *
     * @param $aDelAddress array Klarna delivery_address
     * @return void
     */
    public function updateDeliveryAddress($aDelAddress)
    {
        $oAddress = oxNew(Address::class);
        $oAddress->setId();
        $oAddress->assign($aDelAddress);

        $oAddress->oxaddress__oxuserid  = new Field($this->getId(), Field::T_RAW);
        $oAddress->oxaddress__oxcountry = $this->getUserCountry($oAddress->oxaddress__oxcountryid->value);

        if ($oAddress->isValid()) {
            // save only unique address for 
            if (!$sAddressOxid = $oAddress->klExists()){
                $sAddressOxid = $oAddress->save();
                if($this->isFake()){
                    $oAddress->oxaddress__kltemporary = new Field(1, Field::T_RAW);
                }
            }
            $this->updateSessionDeliveryAddressId($sAddressOxid);
        }
    }

    /**
     * For Fake user. Replace session oxAddress ID, remove old address from DB
     * @param $sAddressOxid
     */
    public function updateSessionDeliveryAddressId($sAddressOxid = null)
    {
        $oSession = Registry::getSession();
        // keep only one address record for fake user, remove old
        if ($this->isFake() && $oSession->hasVariable('deladrid')) {
            $this->clearDeliveryAddress(); // remove old address from db
        }
        if ($sAddressOxid) {
            $oSession->setVariable('deladrid', $sAddressOxid);
            Registry::getSession()->setVariable('blshowshipaddress', 1);
        }
    }

    /**
     * Remove delivery address from session and database
     *
     * @return void
     */
    public function clearDeliveryAddress()
    {
        $oAddress = oxNew(Address::class);
        $oAddress->load(Registry::getSession()->getVariable('deladrid'));
        Registry::getSession()->setVariable('deladrid', null);
        Registry::getSession()->setVariable('blshowshipaddress', 0);
        if ($oAddress->isTemporary()){
            $oAddress->delete();
        }
    }

    /**
     *
     */
    protected function setFakeUserId()
    {
        if (Registry::getSession()->hasVariable('sFakeUserId')) {
            $this->setId(Registry::getSession()->getVariable('sFakeUserId'));
        } else {
            $this->setId();
            Registry::getSession()->setVariable('sFakeUserId', $this->getId());
        }
    }

    /**
     * @return bool
     */
    public function userDataChanged()
    {
        $oldHash = Registry::getSession()->getVariable('userDataHash');
        if ($this->recalculateHash() != $oldHash)
            return true;

        return false;
    }

    /**
     * Gets and saves to the session user data hash
     * @return string
     */
    protected function recalculateHash()
    {
        $currentHash = md5(json_encode($this->getKlarnaPaymentData()));
        $this->saveHash($currentHash);

        return $currentHash;
    }

    /**
     * Save new user data hash to the session
     * @param $currentHash
     */
    public function saveHash($currentHash)
    {
        Registry::getSession()->setVariable('userDataHash', $currentHash);
    }

    /**
     * @return string currency ISO for user country
     */
    public function getKlarnaPaymentCurrency()
    {
        $country2currency = KlarnaConsts::getCountry2CurrencyArray();
        $cur = $this->resolveCountry();
        if(isset($country2currency[$cur])){

            return $country2currency[$cur];
        }
    }

    /**
     * @return null|Address
     */
    public static function getDelAddressInfo()
    {
        $oDelAdress            = null;
        $blShowShippingAddress = (bool)Registry::getSession()->getVariable('blshowshipaddress');
        if (!($soxAddressId = Registry::get(Request::class)->getRequestEscapedParameter('deladrid'))) {
            $soxAddressId = Registry::getSession()->getVariable('deladrid');
        }
        if ($soxAddressId && $blShowShippingAddress) {
            $oDelAdress = oxNew(Address::class);
            $oDelAdress->load($soxAddressId);

            //get delivery country name from delivery country id
            if ($oDelAdress->oxaddress__oxcountryid->value && $oDelAdress->oxaddress__oxcountryid->value != -1) {
                $oCountry = oxNew(Country::class);
                $oCountry->load($oDelAdress->oxaddress__oxcountryid->value);
                $oDelAdress->oxaddress__oxcountry = clone $oCountry->oxcountry__oxtitle;
            }
        }

        return $oDelAdress;
    }

    /**
     *
     */
    public function changeUserData($sUser, $sPassword, $sPassword2, $aInvAddress, $aDelAddress)
    {
        parent::changeUserData($sUser, $sPassword, $sPassword2, $aInvAddress, $aDelAddress);
        if (KlarnaUtils::isKlarnaCheckoutEnabled()) {
            Registry::getSession()->setVariable('sCountryISO', $this->getUserCountryISO2());
        }
    }

    /**
     * @return array
     */
    public function addAttachmentsData()
    {
        if (!$this->isFake()) {
            /** @var KlarnaEMD $klarnaEmd */
            $klarnaEmd = new KlarnaEMD;
            $emd       = $klarnaEmd->getAttachments($this);
            if (!empty($emd)) {
                return array(
                    'content_type' => 'application/vnd.klarna.internal.emd-v2+json',
                    'body'         => json_encode($emd),
                );
            }
        }
    }

    /**
     * @return mixed
     */
    public function save()
    {
        $result = parent::save();
        if ($result && KlarnaUtils::isKlarnaCheckoutEnabled()) {
            Registry::getSession()->setVariable('sCountryISO', $this->getUserCountryISO2());
        }

        return $result;
    }

    /**
     * @return mixed
     */
    public function logout()
    {
        $result = parent::logout();
        if ($result) {
            KlarnaUtils::fullyResetKlarnaSession();
        }

        return $result;
    }

    /**
     *
     * @param $sUser
     * @param $sPassword
     * @param bool $blCookie
     */
    public function login($sUser, $sPassword, $blCookie = false)
    {
        parent::login($sUser, $sPassword, $blCookie);

        if (KlarnaUtils::getKlarnaModuleMode() == KlarnaConsts::MODULE_MODE_KCO) {
            Registry::getSession()->setVariable(
                'sCountryISO',
                $this->getUserCountryISO2()
            );
            Registry::getSession()->deleteVariable('klarna_checkout_user_email');
            $this->_type = self::LOGGED_IN;
        }
    }

    public function kl_checkUserType()
    {
        if($this->getId() === Registry::getSession()->getVariable('usr')){

            return $this->_type = self::LOGGED_IN;
        }

        return $this->_type = self::NOT_REGISTERED;

    }
}
