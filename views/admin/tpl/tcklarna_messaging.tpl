[{assign var="lang_tag" value=$languages.$editlanguage->abbr|oxupper}]

<script type="text/javascript">
    var tcklarna_countriesList = JSON.parse('[{ $tcklarna_countryList }]');
</script>

[{if $readonly }]
    [{assign var="readonly" value="readonly disabled"}]
    [{else}]
    [{assign var="readonly" value=""}]
    [{/if}]

<link rel="stylesheet" href="[{$oViewConf->getResourceUrl()}]main.css">
<link rel="stylesheet" href="[{ $oViewConf->getModuleUrl('tcklarna', 'out/admin/src/css/tcklarna_admin2.css') }]">
<link rel="stylesheet" href="[{ $oViewConf->getModuleUrl('tcklarna', 'out/admin/src/css/tooltipster.bundle.min.css') }]">
<link rel="stylesheet" href="[{ $oViewConf->getModuleUrl('tcklarna', 'out/admin/src/css/tooltipster-sideTip-light.min.css') }]">
<script type="text/javascript" src="[{ $oViewConf->getModuleUrl('tcklarna', 'out/src/js/libs/jquery-1.12.4.min.js') }]"></script>
<script type="text/javascript"
        src="[{ $oViewConf->getModuleUrl('tcklarna', 'out/src/js/libs/tooltipster.bundle.min.js') }]"></script>


<div class="[{$box|default:'box'}]" style="[{if !$box && !$bottom_buttons}]height: 100%;[{/if}]">
    <div class="main-container">
        [{include file="tcklarna_header.tpl" title="TCKLARNA_ON_SITE_MESSAGING"|oxmultilangassign desc="TCKLARNA_ON_SITE_MESSAGING_ADMIN_DESC"|oxmultilangassign}]
        <hr>

        <form name="myedit" id="myedit" method="post" action="[{$oViewConf->getSelfLink()}]"
              enctype="multipart/form-data">
            <input type="hidden" name="MAX_FILE_SIZE" value="[{$iMaxUploadFileSize}]">
            [{$oViewConf->getHiddenSid()}]
            <input type="hidden" name="cl" value="KlarnaMessaging">
            <input type="hidden" name="fnc" value="save">

            <table class="klarna-conf-table">
                <tr>
                    <td>[{oxmultilang ident="TCKLARNA_ON_SITE_MESSAGING_SCRIPT"}]:</td>
                    <td class="input">
                        <textarea id="klscript" name="confstrs[sKlarnaMessagingScript]" class="source">[{$confstrs.sKlarnaMessagingScript}]
                        </textarea>
                    </td>
                    <td>
                        <span class="kl-tooltip" title="[{oxmultilang ident="TCKLARNA_ON_SITE_MESSAGING_SCRIPT_TOOLTIP"}]">
                            <i class="fa fa-question fa-lg" aria-hidden="true"></i>
                        </span>
                    </td>
                </tr>

                <tr>
                    <td>[{oxmultilang ident="TCKLARNA_CREDIT_PROMOTION_PRODUCT"}]:</td>
                    <td class="input">
                        <textarea id="klproduct" name="confstrs[sKlarnaCreditPromotionProduct]" class="source">[{$confstrs.sKlarnaCreditPromotionProduct}]
                        </textarea>
                    </td>
                    <td>
                        <span class="kl-tooltip" title="[{oxmultilang ident="TCKLARNA_CREDIT_PROMOTION_PRODUCT_TOOLTIP"}]">
                            <i class="fa fa-question fa-lg" aria-hidden="true"></i>
                        </span>
                    </td>
                </tr>

                <tr>
                    <td>[{oxmultilang ident="TCKLARNA_CREDIT_PROMOTION_BASKET"}]:</td>
                    <td class="input">
                        <textarea id="klbasket" name="confstrs[sKlarnaCreditPromotionBasket]" class="source">[{$confstrs.sKlarnaCreditPromotionBasket}]
                        </textarea>
                    </td>
                    <td>
                        <span class="kl-tooltip" title="[{oxmultilang ident="TCKLARNA_CREDIT_PROMOTION_BASKET_TOOLTIP"}]">
                            <i class="fa fa-question fa-lg" aria-hidden="true"></i>
                        </span>
                    </td>
                </tr>

                <tr>
                    <td>[{oxmultilang ident="TCKLARNA_TOP_STRIP_PROMOTION"}]:</td>
                    <td class="input">
                        <textarea id="klstrip" name="confstrs[sKlarnaStripPromotion]" class="source">[{$confstrs.sKlarnaStripPromotion}]
                        </textarea>
                    </td>
                    <td>
                        <span class="kl-tooltip" title="[{oxmultilang ident="TCKLARNA_TOP_STRIP_PROMOTION_TOOLTIP"}]">
                            <i class="fa fa-question fa-lg" aria-hidden="true"></i>
                        </span>
                    </td>
                </tr>

                <tr>
                    <td>[{oxmultilang ident="TCKLARNA_FOOTER_PROMOTION"}]:</td>
                    <td class="input">
                        <textarea id="klfooter" name="confstrs[sKlarnaFooterPromotion]" class="source">[{$confstrs.sKlarnaFooterPromotion}]
                        </textarea>
                    </td>
                    <td>
                        <span class="kl-tooltip" title="[{oxmultilang ident="TCKLARNA_FOOTER_PROMOTION_TOOLTIP"}]">
                            <i class="fa fa-question fa-lg" aria-hidden="true"></i>
                        </span>
                    </td>
                </tr>
                <tr>
                    <td>[{oxmultilang ident="TCKLARNA_BANNER_PROMOTION"}]:</td>
                    <td class="input">
                        <textarea id="klbanner" name="confstrs[sKlarnaBannerPromotion]" class="source">[{$confstrs.sKlarnaBannerPromotion}]
                        </textarea>
                    </td>
                    <td>
                        <span class="kl-tooltip" title="[{oxmultilang ident="TCKLARNA_BANNER_PROMOTION_TOOLTIP"}]">
                            <i class="fa fa-question fa-lg" aria-hidden="true"></i>
                        </span>
                    </td>
                </tr>
            </table>
            <div class="btn-center">
                <input type="submit" name="save" class="btn-save" value="[{oxmultilang ident="GENERAL_SAVE"}]"
                       id="form-save-button" [{$readonly}]>
            </div>
        </form>
    </div>
</div>
<script src="[{ $oViewConf->getModuleUrl('tcklarna', 'out/admin/src/js/tcklarna_admin_lib.js') }]"></script>
<script src="[{ $oViewConf->getModuleUrl('tcklarna', 'out/admin/src/js/tcklarna_admin_emd.js') }]"></script>