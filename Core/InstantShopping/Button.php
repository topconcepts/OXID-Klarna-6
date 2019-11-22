<?php


namespace TopConcepts\Klarna\Core\InstantShopping;


use OxidEsales\Eshop\Application\Model\Article;
use OxidEsales\Eshop\Application\Model\Basket;
use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\Eshop\Core\Registry;
use TopConcepts\Klarna\Core\Adapters\ShippingAdapter;
use TopConcepts\Klarna\Core\Adapters\BasketAdapter;
use TopConcepts\Klarna\Core\Exception\KlarnaConfigException;
use TopConcepts\Klarna\Core\KlarnaConsts;
use TopConcepts\Klarna\Core\KlarnaUserManager;
use TopConcepts\Klarna\Core\KlarnaUtils;
use TopConcepts\Klarna\Model\KlarnaInstantBasket;
use TopConcepts\Klarna\Model\KlarnaPayment;
use TopConcepts\Klarna\Model\KlarnaUser;

class Button
{
    const ENV_TEST = 'playground';
    const ENV_LIVE = 'production';

    protected $errors = [];

    /** @var User  */
    protected $oUser;

    /** @var Basket */
    protected $oBasket;
    /**
     * @var object|BasketAdapter
     */
    protected  $basketAdapter;

    public function getConfig(Article $product = null, $update = false) {
        /** @var BasketAdapter $basketAdapter */
        $this->basketAdapter = oxNew(
            BasketAdapter::class,
            $this->getBasket($product),
            $this->getUser(),
            []
        );

        if ($update) {
            return [
                "order_lines" => $this->getOrderLines($product)
            ];
        }
        $config = [
            "setup"=> [
                "key" => $this->getButtonKey(),
                "environment" => $this->getEnvironment(),
                "region" => "eu"
            ],
            "styling" => [
                "theme" => $this->getButtonStyling()
            ],
            "locale" => KlarnaConsts::getLocale(),
            "merchant_urls" => $this->getMerchantUrls()
        ];

        $orderData = [];
        try {
            $orderData["order_lines"] = $this->getOrderLines($product);
            $orderData["shipping_options"] = $this->getShippingOptions($product);
            $orderData["merchant_data"] = $this->basketAdapter->getMerchantData();
        } catch (KlarnaConfigException $e) {
            $this->errors[] = $e->getMessage();
            Registry::getLogger()->log('info', $e->getMessage(), [__METHOD__]);
        }
        if (count($this->errors) === 0) {
            return array_merge(
                $config,
                $this->getPurchaseInfo(),
                $orderData
            );
        }
        return false;
    }

    public function getMerchantUrls() {
        $shopBaseUrl = Registry::getConfig()->getSslShopUrl();
        return [
            "terms"             =>  $shopBaseUrl . "?cl=terms",
//            "push"              =>  $shopBaseUrl . "?cl=KlarnaAcknowledge",
            "confirmation"      =>  $shopBaseUrl . "?cl=thankyou",
            "notification"      =>  $shopBaseUrl . "?cl=notification",
            "update"            =>  $shopBaseUrl . "?cl=KlarnaInstantShoppingController&fnc=updateOrder",
            "country_change"    =>  $shopBaseUrl . "?cl=country_change",
            "place_order"       =>  $shopBaseUrl . "?cl=KlarnaInstantShoppingController&fnc=placeOrder"
        ];
    }

    public function getButtonKey() {
        return KlarnaUtils::getShopConfVar('strKlarnaISButtonKey');
    }

    public function getPurchaseInfo() {
        $result = [
            "purchase_country"  => 'DE',
            "purchase_currency" => 'EUR',
        ];
        /** @var User|KlarnaUser $user */
        $user = Registry::getSession()->getUser();

        if($user) {
            $sCountryISO = $user->resolveCountry();
            $currencyName = Registry::getConfig()->getActShopCurrencyObject()->name;
            $data = $user->getKlarnaPaymentData();
            $data['billing_address']['country'] = strtoupper($data['billing_address']['country']);

            $result = [
                'purchase_country'  => $sCountryISO,
                'purchase_currency' => $currencyName,
                'billing_address' => $data['billing_address']
            ];
        }

        return $result;
    }

    protected function getOrderLines(Article $product = null) {
        $type = KlarnaInstantBasket::TYPE_SINGLE_PRODUCT;
        if ($product === null) {
            $type = KlarnaInstantBasket::TYPE_BASKET;
        }
        $this->basketAdapter->storeBasket($type);
        $this->basketAdapter->buildOrderLinesFromBasket();

        return $this->basketAdapter->getOrderData()['order_lines'];

    }

    /**
     * @return array|null
     * @throws KlarnaConfigException
     * @throws \OxidEsales\Eshop\Core\Exception\SystemComponentException
     */
    protected function getShippingOptions(Article $product = null) {
        $oShippingAdapter = oxNew(
            ShippingAdapter::class,
            [],
            null,
            $this->getBasket($product),
            $this->getuser()
        );

        return $oShippingAdapter->getShippingOptions(KlarnaPayment::KLARNA_INSTANT_SHOPPING);
    }

    protected function getEnvironment() {
        $test = KlarnaUtils::getShopConfVar('blIsKlarnaTestMode');
        return $test ? self::ENV_TEST : self::ENV_LIVE;
    }

    protected function getButtonStyling() {
        $style = [
            "tagline" => "dark",
            "variation" => "klarna",
            "type" => "express"
        ];
        $oConfig = Registry::getConfig();
        $savedStyle = $oConfig->getConfigParam('aarrKlarnaISButtonStyle');
        if($savedStyle) {
            return $savedStyle;
        }

        return $style;
    }

    public function getGenericConfig()
    {

        return [
            "setup"=> [
                "key" => "45a2837c-aa16-46df-9a93-69fcddbc4810",
                "environment" => $this->getEnvironment(),
                "region" => "eu"
            ],
            "styling" => [
                "theme" => $this->getButtonStyling()
            ],
            "purchase_country" => "DE",
            "purchase_currency" => "EUR",
            "locale" => KlarnaConsts::getLocale(true),
            "merchant_urls" => $this->getMerchantUrls(),
            "order_lines" => [[
                "type" => "physical",
                "reference" => "12345",
                "name" => "Testprodukt",
                "quantity" => 1,
                "unit_price" => 125000,
                "tax_rate" => 2500,
                "total_amount" => 125000,
                "total_discount_amount" => 0,
                "total_tax_amount" => 25000,
                "image_url" => ""
            ]],
            "billing_address" => [
                "given_name" => "John",
                "family_name" => "Doe",
                "email" => "jane@doeklarna.com",
                "title" => "Mr",
                "street_address" => "Theresienhöhe 12.",
                "postal_code" => "80339 ",
                "city" => "Munich",
                "phone" => "333444555",
                "country" => "DE",
            ],
        ];
    }

    protected function getUser()
    {

        if ($this->oUser) {
            return $this->oUser;
        }
        $oUser = Registry::getSession()->getUser();
        if (!$oUser) {
            $userManager = oxNew(KlarnaUserManager::class);
            $oUser = $userManager->initUser(['billing_address' =>
                ['country' => Registry::getConfig()->getConfigParam('sKlarnaDefaultCountry')]]);

        }

        return  $this->oUser = $oUser;
    }

    protected function getBasket(Article $product = null)
    {
        if ($this->oBasket) {
            return $this->oBasket;
        }
        if($product !== null) {
            $oBasket = oxNew(Basket::class);
            $oBasket->setBasketUser($this->oUser);
            $oBasket->addToBasket($product->getId(), 1);
            Registry::getSession()->deleteVariable("blAddedNewItem"); // prevent showing notification to user
        } else {
            $oBasket = Registry::getSession()->getBasket();
        }
        $oBasket->setPayment(KlarnaPayment::KLARNA_INSTANT_SHOPPING);
        $oBasket->calculateBasket(true);

        return $this->oBasket = $oBasket;
    }
}