"use strict";
/**
 * Copyright 2018 Klarna AB
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

function KlButtonManager (buttonConfig) {
    var instanceIndex = 0;
    var buttons = document.querySelectorAll('klarna-instant-shopping')
    var amountInput;

    this.quantityUpdate = false;

    this.buttonClickHandler = function() {
        if (this.quantityUpdate) {
            Klarna.InstantShopping.update({
                setup: buttonConfig.setup,
                order_lines: buttonConfig.order_lines
            });
            this.quantityUpdate = false; // reset quantity update flag
        }

        $.ajax({
            method: 'POST',
            contentType: "application/json",
            url: '?cl=KlarnaInstantShoppingController&fnc=startSessionAjax',
                data: JSON.stringify(buttonConfig)
        });
    };

    this.amountInputChangedHandler = function (evt) {
        // update buttonConfig
        var currentQuantity = + evt.target.value;
        var item = buttonConfig.order_lines[0];
        item.quantity = currentQuantity;
        // update other fields in order pass through klarna validation - real update is made server-side later
        item.total_amount = item.unit_price * currentQuantity;
        item.total_tax_amount = item.total_amount - item.total_amount * 10000 / (item.tax_rate + 10000);
        this.quantityUpdate = true;
    };

    for(instanceIndex; instanceIndex < buttons.length; instanceIndex++) {
        buttons[instanceIndex].setAttribute('data-instance-id', instanceIndex);
        buttonConfig.setup.instance_id = instanceIndex.toString();
        Klarna.InstantShopping.load(buttonConfig);
        console.log(buttonConfig);

        var itemForm = buttons[instanceIndex].closest('form');
        amountInput =  itemForm ? itemForm.querySelector('[name=am]') : false;
        if (amountInput) {
            amountInput.addEventListener('change', this.amountInputChangedHandler.bind(this));
        }
        Klarna.InstantShopping.on(
            'confirmation_displayed',
            function (event) {
                $.ajax('?cl=KlarnaInstantShoppingController&fnc=successAjax');
            },
            {setup: {instance_id: buttonConfig.setup.instance_id}}
        );

        Klarna.InstantShopping.on(
            'buy_button_clicked',
            this.buttonClickHandler.bind(this),
            {setup: {instance_id: buttonConfig.setup.instance_id}}
        );
    }

    this.updateInstances = function(buttonConfig) {
        buttons = document.querySelectorAll('klarna-instant-shopping');
        instanceIndex++;
        for(var i=0; i < buttons.length; i++) {
            instanceIndex += i;
            buttonConfig.setup.instance_id = instanceIndex;
            Klarna.InstantShopping.load(buttonConfig);
            console.log(buttonConfig);
        }
    };
}
var klButtonManager = null;
if (!window.klarnaAsyncCallback) {
    window.klarnaAsyncCallback = function () {
        klButtonManager = new KlButtonManager(klButtonManagerConfig);
    };
}
