[{$smarty.block.parent}]

[{if !$oDetailsProduct->isNotBuyable() && $oViewConf->isKlarnaCheckoutEnabled() && $oViewConf->addBuyNow()}]
    <div>
        <a class="btn btn-primary largeButton submitButton klarna-express-button [{if !$blCanBuy}]disabled[{/if}]" href="#">
            [{oxmultilang ident="TCKLARNA_BUY_NOW"}]
        </a>
    </div>
    <div class="clear clearfix"></div>
    [{oxscript add='$(".klarna-express-button").KlarnaProceedAction( {sAction: "actionKlarnaExpressCheckoutFromDetailsPage"} );'}]
[{/if}]

[{assign var="oKlarnaButton" value=$oViewConf->getInstantShoppingButton()}]
[{if $oKlarnaButton}]
    <p><klarna-instant-shopping/></p>
[{/if}]

[{assign var="aKlPromotion" value=$oViewConf->getOnSitePromotionInfo('sKlarnaCreditPromotionProduct', $oDetailsProduct)}]
[{if $aKlPromotion}]
    <div>
        [{$aKlPromotion}]
    </div>
    <div class="clear clearfix"></div>
[{/if}]