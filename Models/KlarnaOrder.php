<?php

namespace TopConcepts\Klarna\Models;


use TopConcepts\Klarna\Core\KlarnaClientBase;
use TopConcepts\Klarna\Core\KlarnaOrderManagementClient;
use TopConcepts\Klarna\Core\KlarnaUtils;
use TopConcepts\Klarna\Exception\KlarnaOrderNotFoundException;
use TopConcepts\Klarna\Exception\KlarnaWrongCredentialsException;
use OxidEsales\Eshop\Application\Model\Basket;
use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\Eshop\Core\Exception\StandardException;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\Session;

class KlarnaOrder extends KlarnaOrder_parent
{

    protected $isAnonymous;

    /**
     * Validates order parameters like stock, delivery and payment
     * parameters
     *
     * @param Basket $oBasket basket object
     * @param User $oUser order user
     *
     * @return bool|null|void
     */
    public function validateOrder($oBasket, $oUser)
    {
        if ($oBasket->getPaymentId() == 'klarna_checkout') {
            return $this->_klarnaValidate($oBasket);
        } else {
            return parent::validateOrder($oBasket, $oUser);
        }
    }

    /**
     * Validate Klarna Checkout order information
     * @param $oBasket
     * @return int
     */
    protected function _klarnaValidate($oBasket)
    {
        // validating stock
        $iValidState = $this->validateStock($oBasket);

        if (!$iValidState) {
            // validating delivery
            $iValidState = $this->validateDelivery($oBasket);
        }

        if (!$iValidState) {
            // validating payment
            $iValidState = $this->validatePayment($oBasket);
        }

        if (!$iValidState) {
            // validating minimum price
            $iValidState = $this->validateBasket($oBasket);
        }


        return $iValidState;
    }

    /**
     * @return mixed
     */
    protected function _setNumber()
    {
        if ($blUpdate = parent::_setNumber()) {

            /** @var Session $session */
            if (in_array($this->oxorder__oxpaymenttype->value, KlarnaPayment::getKlarnaPaymentsIds())
                && empty($this->oxorder__klorderid->value)) {

                $session = Registry::getSession();

                if ($this->isKP()) {
                    $klarna_id = $session->getVariable('klarna_last_KP_order_id');
                    $session->deleteVariable('klarna_last_KP_order_id');
                }

                if ($this->isKCO()) {
                    $klarna_id = $session->getVariable('klarna_checkout_order_id');
                }

                $this->oxorder__klorderid = new Field($klarna_id, Field::T_RAW);

                $this->saveMerchantIdAndServerMode();

                $this->save();

                try {
                    $sCountryISO = KlarnaUtils::getCountryISO($this->getFieldData('oxbillcountryid'));
                    $orderClient = $this->getKlarnaClient($sCountryISO);
                    $orderClient->sendOxidOrderNr($this->oxorder__oxordernr->value, $klarna_id);
                } catch (StandardException $e) {
                    $e->debugOut();
                }
            }
        }

        return $blUpdate;
    }

    /**
     *
     * @throws \OxidEsales\EshopCommunity\Core\Exception\SystemComponentException
     */
    protected function saveMerchantIdAndServerMode()
    {
        $sCountryISO = KlarnaUtils::getCountryISO($this->getFieldData('oxbillcountryid'));

        $aKlarnaCredentials = KlarnaUtils::getAPICredentials($sCountryISO);
        $test               = KlarnaUtils::getShopConfVar('blIsKlarnaTestMode');

        preg_match('/(?<mid>^[a-zA-Z0-9]+)/', $aKlarnaCredentials['mid'], $matches);
        $mid        = $matches['mid'];
        $serverMode = $test ? 'playground' : 'live';

        $this->oxorder__klmerchantid = new Field($mid, Field::T_RAW);
        $this->oxorder__klservermode = new Field($serverMode, Field::T_RAW);
    }

    /**
     * @return bool
     */
    public function isKP()
    {
        return in_array($this->oxorder__oxpaymenttype->value, KlarnaPayment::getKlarnaPaymentsIds('KP'));
    }

    /**
     * @return bool
     */
    public function isKCO()
    {
        return $this->oxorder__oxpaymenttype->value === KlarnaPayment::KLARNA_PAYMENT_CHECKOUT_ID;
    }

    /**
     * @return bool
     */
    public function isKlarna()
    {
        return in_array($this->oxorder__oxpaymenttype->value, KlarnaPayment::getKlarnaPaymentsIds());
    }

    /**
     * Check if order is Klarna order
     *
     * @return boolean
     */
    public function isKlarnaOrder()
    {
        if (strstr($this->getFieldData('oxpaymenttype'), 'klarna_')) {
            return true;
        }

        return false;
    }

    /**
     * Performs standard order cancellation process
     *
     * @return void
     * @throws \OxidEsales\EshopCommunity\Core\Exception\SystemComponentException
     */
    public function cancelOrder()
    {
        // check if it is Klarna order and not already canceled
        if ($this->isKlarnaOrder() && !$this->getFieldData('oxstorno') && $this->getFieldData('klsync') == 1) {
            $orderId     = $this->getFieldData('klorderid');
            $sCountryISO = KlarnaUtils::getCountryISO($this->getFieldData('oxbillcountryid'));
            try {
                $result = $this->cancelKlarnaOrder($orderId, $sCountryISO);
            } catch (KlarnaWrongCredentialsException $e) {
                if (strstr($e->getMessage(), 'is canceled.')) {
                    parent::cancelOrder();
                } else {

                    return Registry::getLang()->translateString("KLARNA_UNAUTHORIZED_REQUEST");
                }

                return;
            } catch (KlarnaOrderNotFoundException $e) {
                return Registry::getLang()->translateString("KLARNA_ORDER_NOT_FOUND");

            } catch (StandardException $e) {
                return $this->showKlarnaErrorMessage($e);
            }

        }

        parent::cancelOrder();
    }

    /**
     * @param $orderId
     * @param null $sCountryISO
     * @return mixed
     * @throws KlarnaOrderNotFoundException
     * @throws KlarnaWrongCredentialsException
     * @throws \TopConcepts\Klarna\Exception\KlarnaCaptureNotAllowedException
     * @throws \TopConcepts\Klarna\Exception\KlarnaClientException
     * @throws \TopConcepts\Klarna\Exception\KlarnaOrderReadOnlyException
     * @throws \OxidEsales\Eshop\Core\Exception\SystemComponentException
     */
    public function cancelKlarnaOrder($orderId = null, $sCountryISO = null)
    {
        $orderId = $orderId ?: $this->getFieldData('klorderid');

        $client = $this->getKlarnaClient($sCountryISO);

        return $client->cancelOrder($orderId);
    }

    /**
     * @param $sCountryISO
     * @return KlarnaOrderManagementClient|KlarnaClientBase
     * @throws \OxidEsales\Eshop\Core\Exception\SystemComponentException
     */
    public function getKlarnaClient($sCountryISO = null)
    {
        return KlarnaOrderManagementClient::getInstance($sCountryISO);
    }

    /**
     * @param $data
     * @param $orderId
     * @param $sCountryISO
     * @return void
     * @throws \OxidEsales\Eshop\Core\Exception\SystemComponentException
     */
    public function updateKlarnaOrder($data, $orderId, $sCountryISO = null)
    {
        $client = $this->getKlarnaClient($sCountryISO);
        try {
            $result                = $client->updateOrderLines($data, $orderId);
            $this->oxorder__klsync = new Field(1);
            $this->save();
        } catch (KlarnaWrongCredentialsException $e) {
            $this->oxorder__klsync = new Field(0, Field::T_RAW);
            $this->save();

            return Registry::getLang()->translateString("KLARNA_UNAUTHORIZED_REQUEST");
        } catch (KlarnaOrderNotFoundException $e) {
            $this->oxorder__klsync = new Field(0, Field::T_RAW);
            $this->save();

            return Registry::getLang()->translateString("KLARNA_ORDER_NOT_FOUND");

        } catch (StandardException $e) {

            $this->oxorder__klsync = new Field(0, Field::T_RAW);
            $this->save();

            return $this->showKlarnaErrorMessage($e);
        }
    }

    /**
     * @param $data
     * @param $orderId
     * @param null $sCountryISO
     * @return array
     * @throws KlarnaOrderNotFoundException
     * @throws KlarnaWrongCredentialsException
     * @throws \TopConcepts\Klarna\Exception\KlarnaCaptureNotAllowedException
     * @throws \TopConcepts\Klarna\Exception\KlarnaClientException
     * @throws \TopConcepts\Klarna\Exception\KlarnaOrderReadOnlyException
     * @throws \OxidEsales\Eshop\Core\Exception\SystemComponentException
     */
    public function captureKlarnaOrder($data, $orderId, $sCountryISO = null)
    {
        if ($trackcode = $this->getFieldData('oxtrackcode')) {
            $data['shipping_info'] = array(array('tracking_number' => $trackcode));
        }
        $client = $this->getKlarnaClient($sCountryISO);

        return $client->captureOrder($data, $orderId);
    }

    /**
     * @param $orderId
     * @param null $sCountryISO
     * @return array
     * @throws KlarnaOrderNotFoundException
     * @throws KlarnaWrongCredentialsException
     * @throws \TopConcepts\Klarna\Exception\KlarnaCaptureNotAllowedException
     * @throws \TopConcepts\Klarna\Exception\KlarnaClientException
     * @throws \TopConcepts\Klarna\Exception\KlarnaOrderReadOnlyException
     * @throws \OxidEsales\Eshop\Core\Exception\SystemComponentException
     */
    public function getAllCaptures($orderId, $sCountryISO = null)
    {
        $client = $this->getKlarnaClient($sCountryISO);

        return $client->getAllCaptures($orderId);
    }

    /**
     * @param $orderId
     * @param null $sCountryISO
     * @return mixed
     * @throws StandardException
     * @throws \TopConcepts\Klarna\Exception\KlarnaClientException
     * @throws \OxidEsales\Eshop\Core\Exception\SystemComponentException
     */
    public function retrieveKlarnaOrder($orderId, $sCountryISO = null)
    {
        $client = $this->getKlarnaClient($sCountryISO);

        return $client->getOrder($orderId);
    }

    /**
     * @param $data
     * @param $orderId
     * @param null $sCountryISO
     * @return array
     * @throws KlarnaOrderNotFoundException
     * @throws KlarnaWrongCredentialsException
     * @throws \TopConcepts\Klarna\Exception\KlarnaCaptureNotAllowedException
     * @throws \TopConcepts\Klarna\Exception\KlarnaClientException
     * @throws \TopConcepts\Klarna\Exception\KlarnaOrderReadOnlyException
     * @throws \OxidEsales\Eshop\Core\Exception\SystemComponentException
     */
    public function createOrderRefund($data, $orderId, $sCountryISO = null)
    {
        $client = $this->getKlarnaClient($sCountryISO);

        return $client->createOrderRefund($data, $orderId);
    }

    /**
     * @param $data
     * @param $orderId
     * @param $captureId
     * @param null $sCountryISO
     * @return array
     * @throws KlarnaOrderNotFoundException
     * @throws KlarnaWrongCredentialsException
     * @throws \TopConcepts\Klarna\Exception\KlarnaCaptureNotAllowedException
     * @throws \TopConcepts\Klarna\Exception\KlarnaClientException
     * @throws \TopConcepts\Klarna\Exception\KlarnaOrderReadOnlyException
     * @throws \OxidEsales\Eshop\Core\Exception\SystemComponentException
     */
    public function addShippingToCapture($data, $orderId, $captureId, $sCountryISO = null)
    {
        $client = $this->getKlarnaClient($sCountryISO);

        return $client->addShippingToCapture($data, $orderId, $captureId);
    }

//    /**
//     * Get average of order VAT
//     *
//     * @return float
//     */
//    public function getOrderVatAverage()
//    {
//        $vatAvg = ($this->getTotalOrderSum() / $this->getOrderNetSum() - 1) * 100;
//
//        return number_format($vatAvg, 2);
//    }

    /**
     * @param $orderLang
     * @param bool $isCapture
     * @return mixed
     */
    public function getNewOrderLinesAndTotals($orderLang, $isCapture = false)
    {
        $cur = $this->getOrderCurrency();
        Registry::getConfig()->setActShopCurrency($cur->id);
        if ($isCapture) {
            $this->reloadDiscount(false);
        }
//        $this->recalculateOrder();
        $oBasket = $this->_getOrderBasket();
        $oBasket->setKlarnaOrderLang($orderLang);
        $this->_addOrderArticlesToBasket($oBasket, $this->getOrderArticles(true));

        $oBasket->calculateBasket(true);
        $orderLines = $oBasket->getKlarnaOrderLines($this->getId());

        return $orderLines;
    }

    /**
     * @param StandardException $e
     * @return string
     */
    public function showKlarnaErrorMessage(StandardException $e)
    {
        if (in_array($e->getCode(), array(403, 422, 401, 404))) {
            $oLang = Registry::getLang();

            return sprintf($oLang->translateString('KL_ORDER_UPDATE_REJECTED_BY_KLARNA'), $e->getMessage());
        }
    }

    /**
     * Set anonymous data if anonymization is enabled.
     *
     * @param $aArticleList
     */
    protected function _setOrderArticles($aArticleList)
    {

        parent::_setOrderArticles($aArticleList);

        if ($this->isKlarnaAnonymous()) {
            $oOrderArticles = $this->getOrderArticles();
            if ($oOrderArticles && count($oOrderArticles) > 0) {
                $this->_setOrderArticleKlarnaInfo($oOrderArticles);
            }
        }

    }

    /**
     * @param $oOrderArticles
     */
    protected function _setOrderArticleKlarnaInfo($oOrderArticles)
    {
        $iIndex = 0;
        foreach ($oOrderArticles as $oOrderArticle) {
            $iIndex++;
            $oOrderArticle->kl_setTitle($iIndex);
            $oOrderArticle->kl_setArtNum($iIndex);
        }
    }

    /**
     * @return mixed
     */
    protected function isKlarnaAnonymous()
    {
        if ($this->isAnonymous !== null)
            return $this->isAnonymous;

        return $this->isAnonymous = KlarnaUtils::getShopConfVar('blKlarnaEnableAnonymization');
    }
}