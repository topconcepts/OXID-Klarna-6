<?php

namespace TopConcepts\Klarna\Tests\Unit\Core\Adapters;

use OxidEsales\Eshop\Application\Model\Basket;
use OxidEsales\Eshop\Application\Model\BasketItem;
use TopConcepts\Klarna\Core\Adapters\DiscountAdapter;
use TopConcepts\Klarna\Core\Exception\InvalidItemException;
use TopConcepts\Klarna\Tests\Unit\ModuleUnitTestCase;

class DiscountAdapterTest extends ModuleUnitTestCase
{
    public function testValidateItem()
    {
        $adapter = $this->getMockBuilder(DiscountAdapter::class)->disableOriginalConstructor()->setMethods(
            ['addItemToOrderLines'])->getMock();

        $basketItem = $this->getMockBuilder(BasketItem::class)->disableOriginalConstructor()->getMock();
        $basketItem->dDiscount = 10;
        $this->setProtectedClassProperty($adapter, "oItem", $basketItem);
        $orderLine = ["total_amount" => -1000];

        $adapter->validateItem($orderLine);

        $this->expectException(InvalidItemException::class);
        $orderLine = ["total_amount" => 0];
        $adapter->validateItem($orderLine);
    }

    public function testPrepareItemData()
    {
        $adapterBuilder = $this->getMockBuilder(DiscountAdapter::class);
        $adapterBuilder->setMethods(['getKlarnaType']);
        $adapter = $adapterBuilder->disableOriginalConstructor()->getMockForAbstractClass();
        $adapter->expects($this->any())->method('getKlarnaType')->willReturn('type');

        $basketItem = $this->getMockBuilder(BasketItem::class)->disableOriginalConstructor()->getMock();
        $basketItem->sDiscount = 'name';
        $basketItem->dDiscount = 10;
        $basketItem->sOXID = 'reference';

        $this->setProtectedClassProperty($adapter, "oItem", $basketItem);

        $this->prepareBasket($adapter);

        $prepareItemData = self::getMethod('prepareItemData', DiscountAdapter::class);
        $prepareItemData->invokeArgs($adapter, ['DE']);

        $expected = [
            'type' => 'type',
            'reference' => 'reference',
            'name' => 'name',
            'quantity' => 1,
            'unit_price' => -1000,
            'tax_rate' => 23,
            'total_amount' => -1000,
            'total_discount_amount' => 0,
            'total_tax_amount' => -2,
        ];

        $result = $this->getProtectedClassProperty($adapter, "itemData");
        $this->assertSame($expected, $result);
    }

    public function testGetReference()
    {
        $adapter = $this->getMockBuilder(DiscountAdapter::class)->disableOriginalConstructor()->setMethods(
            ['addItemToOrderLines'])->getMock();

        $basketItem = $this->getMockBuilder(BasketItem::class)->disableOriginalConstructor()->getMock();
        $basketItem->sOXID = 'oxid';

        $this->setProtectedClassProperty($adapter, "oItem", $basketItem);

        $getReference = self::getMethod('getReference', DiscountAdapter::class);
        $result = $getReference->invokeArgs($adapter, []);

        $this->assertSame('oxid', $result);

        $itemData['reference'] = 'reference';
        $this->setProtectedClassProperty($adapter, "itemData", $itemData);

        $getReference = self::getMethod('getReference', DiscountAdapter::class);
        $result = $getReference->invokeArgs($adapter, []);

        $this->assertSame('reference', $result);
    }

    protected function prepareBasket($adapter)
    {
        $basketBuilder = $this->getMockBuilder(Basket::class);
        $basketBuilder->setMethods(['getAdditionalServicesVatPercent']);
        $basket = $basketBuilder->getMock();
        $basket->expects($this->any())->method('getAdditionalServicesVatPercent')->willReturn(0.23);

        $this->setProtectedClassProperty($adapter, "oBasket", $basket);
    }
}