<?php

namespace TopConcepts\Klarna\Core;


use OxidEsales\Eshop\Application\Model\Address;
use OxidEsales\Eshop\Application\Model\Country;
use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Registry;
use TopConcepts\Klarna\Model\KlarnaUser;

class KlarnaUserManager
{

    /**
     * @param $orderData
     * @param null $oUser
     * @return object|User
     */
    public function initUser($orderData, $oUser = null)
    {
        /** @var User | KlarnaUser $oUser */
        if ($oUser === null) {
            $oUser = oxNew(User::class);
            $oUser->load($orderData['userId']);
        }
        if ($oUser->isLoaded() === false) {
            $oUser->setFakeUserId();
        }

        //Build user info
        $oCountry = oxNew(Country::class);
        $oUser->oxuser__oxcountryid = new Field(
            $oCountry->getIdByCode(strtoupper($orderData['billing_address']['country'])),
            Field::T_RAW
        );
        //TODO: this part requires update after merging paypal fixes - KlarnaFormatter is updated in that branch
        $aUserData = KlarnaFormatter::klarnaToOxidAddress($orderData, 'billing_address');
        $nonEmptyFields = array_filter(
            $aUserData,
            function($value, $name) {
                return $value !== '';
            },
            ARRAY_FILTER_USE_BOTH
        );
        $oUser->assign($nonEmptyFields);
        $this->setEmptyFields($oUser);

        Registry::getSession()->setUser($oUser);

        if (isset($orderData['shipping_address']) && $orderData['billing_address'] !== $orderData['shipping_address']) {
            $oUser->updateDeliveryAddress(KlarnaFormatter::klarnaToOxidAddress($orderData, 'shipping_address'));
        } else {
            $oUser->clearDeliveryAddress();
        }

        return $oUser;
    }

    protected function setEmptyFields($oUser)
    {
        $required = [
            'oxuser__oxustid',
            'oxuser__oxfon',
            'oxuser__oxfax'
        ];
        foreach ($required as $fieldName) {
            if ($oUser->{$fieldName} === false) {
                $oUser->{$fieldName} = new Field('');
            }
        }
    }
}