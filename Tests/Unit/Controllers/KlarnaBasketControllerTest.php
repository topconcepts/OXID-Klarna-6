<?php

namespace TopConcepts\Klarna\Tests\Unit\Controllers;

use OxidEsales\Eshop\Core\Registry;
use TopConcepts\Klarna\Controllers\KlarnaBasketController;
use TopConcepts\Klarna\Models\KlarnaBasket;
use TopConcepts\Klarna\Tests\Unit\ModuleUnitTestCase;

class KlarnaBasketControllerTest extends ModuleUnitTestCase
{

    public function testRender()
    {
        $basket = $this->createStub(KlarnaBasket::class,['getPaymentId' => 'klarna_checkout']);
        $session = Registry::getSession();
        $session->setBasket($basket);
        $this->setRequestParameter('openAmazonLogin', true);
        $this->setRequestParameter('klarnaInvalid', true);
        $basketController = $this->createStub(KlarnaBasketController::class, ['displayKlarnaValidationErrors' => true]);

        $result = $basketController->render();

        $this->assertEquals('page/checkout/basket.tpl', $result);

    }
}
