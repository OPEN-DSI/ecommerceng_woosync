<?php
/* Copyright (C) 2010 Franck Charpentier - Auguria <franck.charpentier@auguria.net>
 * Copyright (C) 2013 Laurent Destailleur          <eldy@users.sourceforge.net>
 * Copyright (C) 2017 Open-DSI                     <support@open-dsi.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 * or see http://www.gnu.org/
 */

include_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';

$formproduct = new FormProduct($db);

llxHeader();

print_fiche_titre($langs->trans("ECommerceSetup"),$linkback,'setup');

?>
	<script type="text/javascript" src="<?php print dol_buildpath('/ecommerceng/js/form.js',1); ?>"></script>
	<br>
	<form id="site_form_select" name="site_form_select" action="<?php $_SERVER['PHP_SELF'] ?>" method="post">
		<select class="flat" id="site_form_select_site" name="site_form_select_site" onchange="eCommerceSubmitForm('site_form_select')">
			<option value="-1"><?php print $langs->trans('ECommerceAddNewSite') ?></option>
<?php
if (count($sites))
	foreach ($sites as $option)
	{
		print '
			<option';
		if ($ecommerceId == $option['id'])
			print ' selected="selected"';
		print ' value="'.$option['id'].'">'.$option['name'].'</option>';
	}
?>
		</select>
	</form>
	<br>

	<?php print_titre($langs->trans("MainSyncSetup")); ?>

	<form name="site_form_detail" id="site_form_detail" action="<?php $_SERVER['PHP_SELF'] ?>" method="post">
			<input type="hidden" name="token" value="<?php print $_SESSION['newtoken'] ?>">
			<input id="site_form_detail_action" type="hidden" name="site_form_detail_action" value="save">
			<input type="hidden" name="ecommerce_id" value="<?php print $ecommerceId ?>">
			<input type="hidden" name="ecommerce_last_update" value="<?php print $ecommerceLastUpdate ?>">

			<table class="noborder" width="100%">
				<tr class="liste_titre">
					<td width="20%"><?php print $langs->trans('Parameter') ?></td>
					<td><?php print $langs->trans('Value') ?></td>
					<td><?php print $langs->trans('Description') ?></td>
				</tr>
<?php
$var=!$var;
?>
				<tr <?php print $bc[$var] ?>>
					<td><span class="fieldrequired"><?php print $langs->trans('ECommerceSiteType') ?></span></td>
					<td>
						<select class="flat" name="ecommerce_type">
							<option value="0">&nbsp;</option>
							<?php
								if (count($siteTypes))
									foreach ($siteTypes as $key=>$value)
									{
										print '
											<option';
										if ($ecommerceType == $key)
											print ' selected="selected"';
										print ' value="'.$key.'">'.$langs->trans($value).'</option>';
									}
								?>
						</select>
					</td>
					<td><?php print $langs->trans('ECommerceSiteTypeDescription') ?></td>
				</tr>
<?php
$var=!$var;
?>
				<tr <?php print $bc[$var] ?>>
					<td><span class="fieldrequired"><?php print $langs->trans('ECommerceSiteName') ?></span></td>
					<td>
						<input type="text" class="flat" name="ecommerce_name" value="<?php print $ecommerceName ?>" size="30">
					</td>
					<td><?php print $langs->trans('ECommerceSiteNameDescription') ?></td>
				</tr>
<?php
$var=!$var;
?>
				<tr <?php print $bc[$var] ?>>
					<td><span class="fieldrequired"><?php print $langs->trans('ECommerceCatProduct') ?></span></td>
					<td>
						<select class="flat" name="ecommerce_fk_cat_product">
							<option value="0">&nbsp;</option>
							<?php
								if (count($productCategories))
									foreach ($productCategories as $productCategorie)
									{
										print '
											<option';
										if ($ecommerceFkCatProduct == $productCategorie['id'])
											print ' selected="selected"';
										print ' value="'.$productCategorie['id'].'">'.$productCategorie['label'].'</option>';
									}
								?>
						</select>
					</td>
					<td><?php print $langs->trans('ECommerceCatProductDescription') ?></td>
				</tr>
<?php
$var=!$var;
?>
				<tr <?php print $bc[$var] ?>>
					<td><span class="fieldrequired"><?php print $langs->trans('ECommerceCatSociete') ?></span></td>
					<td>
						<select class="flat" name="ecommerce_fk_cat_societe">
							<option value="0">&nbsp;</option>
							<?php
								if (count($productCategories))
									foreach ($societeCategories as $societeCategorie)
									{
										print '
											<option';
										if ($ecommerceFkCatSociete == $societeCategorie['id'])
											print ' selected="selected"';
										print ' value="'.$societeCategorie['id'].'">'.$societeCategorie['label'].'</option>';
									}
								?>
						</select>
					</td>
					<td><?php print $langs->trans('ECommerceCatSocieteDescription') ?></td>
				</tr>
<?php
$var=!$var;
?>
				<tr <?php print $bc[$var] ?>>
					<td><span><?php print $langs->trans('ThirdPartyForNonLoggedUsers') ?></span></td>
					<td>
						<?php print $form->select_company($ecommerceFkAnonymousThirdparty, 'ecommerce_fk_anonymous_thirdparty', '', 1); ?>
					</td>
					<td><?php print $langs->trans('SynchUnkownCustomersOnThirdParty') ?></td>
				</tr>
<?php
$var=!$var;
?>
				<!-- Filter are not used at this time
				<tr <?php print $bc[$var] ?>>
					<td><?php print $langs->trans('ECommerceFilterLabel') ?></td>
					<td>
						<input type="text" class="flat" name="ecommerce_filter_label" value="<?php print $ecommerceFilterLabel ?>" size="30">
					</td>
					<td><?php print $langs->trans('ECommerceFilterLabelDescription') ?></td>
				</tr>
<?php
$var=!$var;
?>
				<tr <?php print $bc[$var] ?>>
					<td><?php print $langs->trans('ECommerceFilterValue') ?></td>
					<td>
						<input type="text" class="flat" name="ecommerce_filter_value" value="<?php print $ecommerceFilterValue ?>" size="30">
					</td>
					<td><?php print $langs->trans('ECommerceFilterValueDescription') ?></td>
				</tr>
				-->
<?php
$var=!$var;
?>
				<tr <?php print $bc[$var] ?>>
					<td><span class="fieldrequired"><?php print $langs->trans('ECommerceSiteAddress') ?></span></td>
					<td>
						<input type="text" class="flat" name="ecommerce_webservice_address" value="<?php print $ecommerceWebserviceAddress ?>" size="60">
						<?php
						if ($ecommerceWebserviceAddressTest)
						    print '<br><a href="'.$ecommerceWebserviceAddressTest.'" target="_blank">'.$langs->trans("ECommerceClickUrlToTestUrl").'</a>';
						?>
					</td>
					<td><?php print $langs->trans('ECommerceSiteAddressDescription') ?></td>
				</tr>
<?php
$var=!$var;
?>
				<tr <?php print $bc[$var] ?>>
					<td class="fieldrequired"><?php print $langs->trans('ECommerceUserName') ?></td>
					<td>
						<input type="text" class="flat" name="ecommerce_user_name" value="<?php print $ecommerceUserName ?>" size="20">
					</td>
					<td><?php print $langs->trans('ECommerceUserNameDescription') ?></td>
				</tr>
<?php
$var=!$var;
?>
				<tr <?php print $bc[$var] ?>>
					<td class="fieldrequired"><?php print $langs->trans('ECommerceUserPassword') ?></td>
					<td><input type="password" class="flat" name="ecommerce_user_password" value="<?php print $ecommerceUserPassword ?>" size="20"></td>
					<td><?php print $langs->trans('ECommerceUserPasswordDescription') ?></td>
				</tr>
<?php
if (!empty($conf->global->PRODUIT_MULTIPRICES)) {
	$var = !$var;
?>
				<tr <?php print $bc[$var] ?>>
					<td class="fieldrequired"><?php print $langs->trans('ECommercePriceLevel') ?></td>
					<td>
						<select class="flat" name="ecommerce_price_level">
						<?php
						foreach ($priceLevels as $idx => $priceLevel) {
							print '<option value="' . $idx . '"' . ($ecommercePriceLevel == $idx ? ' selected="selected"' : '') . '">' . $idx . '</option>';
						}
						?>
						</select>
					</td>
					<td><?php print $langs->trans('ECommercePriceLevelDescription') ?></td>
				</tr>
				<script type="text/javascript">
					jQuery(document).ready(function (){
						eCommerceConfirmUpdatePriceLevel("site_form_detail", "<?php print $langs->transnoentities('ECommerceConfirmUpdatePriceLevel') ?>", <?php print $siteDb->price_level ?>);
					});
				</script>
<?php
}
/*
$var=!$var;
?>
				<tr <?php print $bc[$var] ?>>
					<td><span><?php print $langs->trans('ECommerceTimeout') ?></span></td>
					<td>
						<input type="text" class="flat" name="ecommerce_timeout" value="<?php print $ecommerceTimeout ?>" size="10">
					</td>
					<td><?php print $langs->trans('ECommerceTimeoutDescription') ?></td>
				</tr>
<?php
*/
/* TODO A activer et tester "special prices"
$var=!$var;
?>
				<tr <?php print $bc[$var] ?>>
					<td><?php print $langs->trans('ECommerceMagentoUseSpecialPrice') ?></td>
					<td>
						<input type="checkbox" class="flat" name="ecommerce_magento_use_special_price" <?php print ($ecommerceMagentoUseSpecialPrice ? 'checked' : '') ?> />
					</td>
					<td><?php print $langs->trans('ECommerceMagentoUseSpecialPriceDescription') ?></td>
				</tr>
<?php
*/
$var=!$var;
?>
    <tr <?php print $bc[$var] ?>>
        <td><?php print $langs->trans('ECommercePriceType') ?></td>
        <td>
            <select class="flat" name="ecommerce_price_type">
                <option value="HT" <?php print ($ecommercePriceType == 'HT' ? 'selected="selected"' : '') ?>><?php print $langs->trans('ECommercePriceTypeHT') ?></option>
                <option value="TTC"<?php print ($ecommercePriceType == 'TTC' ? 'selected="selected"' : '') ?>><?php print $langs->trans('ECommercePriceTypeTTC') ?></option>
            </select>
        </td>
        <td><?php print $langs->trans('ECommercePriceTypeDescription') ?></td>
    </tr>
<?php
if ($ecommerceType == 2) {
	$var = !$var;
	?>
	<tr <?php print $bc[$var] ?>>
		<td><span><?php print $langs->trans('ECommerceShippingService') ?></span></td>
		<td>
			<?php $form->select_produits($ecommerceFkShippingService, 'ecommerce_fk_shipping_service', 1, 0); ?>
		</td>
		<td><?php print $langs->trans('ECommerceShippingServiceDescription') ?></td>
	</tr>
	<?php
	$var = !$var;
	?>
	<tr <?php print $bc[$var] ?>>
		<td><span><?php print $langs->trans('ECommerceDiscountCodeService') ?></span></td>
		<td>
			<?php $form->select_produits($ecommerceFkDiscountCodeService, 'ecommerce_fk_discount_code_service', 1, 0); ?>
		</td>
		<td><?php print $langs->trans('ECommerceDiscountCodeServiceDescription') ?></td>
	</tr>
	<?php
	$var = !$var;
	?>
	<tr <?php print $bc[$var] ?>>
		<td><span><?php print $langs->trans('ECommercePwGiftCardsService') ?></span></td>
		<td>
			<?php $form->select_produits($ecommerceFkPwGiftCardsService, 'ecommerce_fk_pw_gift_cards_service', 1, 0); ?>
		</td>
		<td><?php print $langs->trans('ECommercePwGiftCardsServiceDescription') ?></td>
	</tr>
	<?php
}
if (!empty($conf->commande->enabled)) {
    if ($ecommerceType == 2) {
        $var = !$var;
?>
    <tr <?php print $bc[$var] ?>>
        <td><?php print $langs->trans('ECommerceWoocommerceCustomerRolesSupported') ?></td>
        <td><input type="text" class="flat" name="ecommerce_woocommerce_customer_roles" value="<?php print $ecommerceWoocommerceCustomerRoles ?>" size="20"></td>
        <td><?php print $langs->trans('ECommerceWoocommerceCustomerRolesSupportedDescription') ?></td>
    </tr>
<?php
    }
    $var=!$var;
    if ($conf->commande->enabled || $conf->facture->enabled || ($conf->supplier_invoice->enabled && !empty($ecommerceOrderActions['create_invoice']))) {
?>
    <tr <?php print $bc[$var] ?>>
        <td><?php print $langs->trans('ECommerceCreateOrderInvoiceWhenSyncOrder') ?></td>
        <td>
            <?php
            if ($conf->commande->enabled) {
            ?>
            <input type="checkbox" id="ecommerce_create_order" name="ecommerce_create_order" value="1" <?php print !isset($ecommerceOrderActions['create_order']) || !empty($ecommerceOrderActions['create_order']) ? ' checked' : '' ?>>&nbsp;<label for="ecommerce_create_order"><?php print $langs->trans('ECommerceCreateOrder') ?></label>
            <?php
            }
            if ($conf->facture->enabled) {
            ?>
            <br>
            <input type="checkbox" id="ecommerce_create_invoice" name="ecommerce_create_invoice" value="1" <?php print !empty($ecommerceOrderActions['create_invoice']) ? ' checked' : '' ?>>&nbsp;<label for="ecommerce_create_invoice"><?php print $langs->trans('ECommerceCreateInvoice') ?></label>&nbsp;
            <?php
            if (!empty($ecommerceOrderActions['create_invoice'])) {
            ?>
            <input type="checkbox" id="ecommerce_send_invoice_by_mail" name="ecommerce_send_invoice_by_mail" value="1" <?php print !isset($ecommerceOrderActions['send_invoice_by_mail']) || !empty($ecommerceOrderActions['send_invoice_by_mail']) ? ' checked' : '' ?>>&nbsp;<label for="ecommerce_send_invoice_by_mail"><?php print $langs->trans('ECommerceSendInvoiceByMail') ?></label>
            <?php
            }
            }
            if ($conf->supplier_invoice->enabled && !empty($ecommerceOrderActions['create_invoice'])) {
            ?>
            <br>
            <input type="checkbox" id="ecommerce_create_supplier_invoice" name="ecommerce_create_supplier_invoice" value="1" <?php print !empty($ecommerceOrderActions['create_supplier_invoice']) ? ' checked' : '' ?>>&nbsp;<label for="ecommerce_create_supplier_invoice"><?php print $langs->trans('ECommerceCreateSupplierInvoiceFromFee') ?></label>&nbsp;
            <?php
            }
            ?>
        </td>
        <td><?php print $langs->trans('ECommerceCreateOrderDescription') ?></td>
    </tr>
<?php
    }
    $var=!$var;
    if ($ecommerceOrderActions['create_order'] || $ecommerceOrderActions['create_invoice'] || $ecommerceOrderActions['create_supplier_invoice']) {
        ?>
        <tr <?php print $bc[$var] ?>>
            <td><?php print $langs->trans('ECommerceCreateOrderSalesRepresentativeFollowByDefault') ?></td>
            <td>
                <?php
                print $form->select_dolusers($ecommerceDefaultSalesRepresentativeFollow > 0 ? $ecommerceDefaultSalesRepresentativeFollow : -1, 'default_sales_representative_follow', 1, (! empty($userAlreadySelected)?$userAlreadySelected:null), 0, null, null, 0, 56, '', 0, '', 'minwidth200imp');
                ?>
            </td>
            <td><?php print $langs->trans('ECommerceCreateOrderSalesRepresentativeFollowByDefaultDescription') ?></td>
        </tr>
        <?php
    }
}
?>
</table>

<?php
if ($ecommerceType == 2)
{
	$var=!$var;
	?>
	<br>
	<?php
	print_titre($langs->trans("ECommerceSiteWebHooksSetup"));
	?>
	<table class="noborder" width="100%">

		<tr class="liste_titre">
			<td width="20%"><?php print $langs->trans('Parameter') ?></td>
			<td><?php print $langs->trans('Value') ?></td>
			<td><?php print $langs->trans('Description') ?></td>
		</tr>
		<?php
		$var = !$var;
		?>
		<tr <?php print $bc[$var] ?>>
			<td><?php print $langs->trans("ECommerceSiteWebHooksUrl"); ?></td>
			<td><input type="text" class="flat" value="<?php print $eCommerceSiteWebHooksUrl ?>" size="50" readonly="readonly"></td>
			<td><?php print $langs->trans('ECommerceSiteWebHooksUrlDescription') ?></td>
		</tr>
		<?php
		$var = !$var;
		?>
		<tr <?php print $bc[$var] ?>>
			<td><?php print $langs->trans("ECommerceSiteWebHooksSecret"); ?></td>
			<td><?php
			print '<input size="30" maxsize="32" type="text" id="ecommerce_web_hooks_secret" name="ecommerce_web_hooks_secret" value="'.$eCommerceSiteWebHooksSecret.'" autocomplete="off">';
			if (! empty($conf->use_javascript_ajax))
				print '&nbsp;'.img_picto($langs->trans('Generate'), 'refresh', 'id="generate_api_key" class="linkobject"');
				print "\n".'<script type="text/javascript">';
				print '$(document).ready(function () {
            $("#generate_api_key").click(function() {
                $.get( "'.DOL_URL_ROOT.'/core/ajax/security.php", {
                    action: \'getrandompassword\',
                    generic: true
                },
                function(token) {
                    $("#ecommerce_web_hooks_secret").val(token);
                });
            });
    });';
				print '</script>';
			?></td>
			<td><?php print $langs->trans('ECommerceSiteWebHooksSecretDescription') ?></td>
		</tr>
	</table>
	<?php
}
?>

<?php
if ($ecommerceType == 2)
{
    $var=!$var;
    $sync_direction_array=array(''=>$langs->trans('None'), 'etod'=>$langs->trans('ECommerceToDolibarr'), 'dtoe'=>$langs->trans('DolibarrToECommerce'), 'all'=>$langs->trans('AllDirection'));
?>
      <br>
<?php
    print_titre($langs->trans("ProductsSyncSetup"));
?>
      <table class="noborder" width="100%">

        <tr class="liste_titre">
          <td width="20%"><?php print $langs->trans('Parameter') ?></td>
          <td><?php print $langs->trans('Value') ?></td>
          <td><?php print $langs->trans('Description') ?></td>
        </tr>

        <tr <?php print $bc[$var] ?>>
          <td><span><?php print $langs->trans('ECommerceWoocommerceProductSyncPrice') ?></span></td>
          <td>
            <?php
              print $form->selectarray('ecommerce_product_synch_price', array(/*'selling'=>$langs->trans('ECommerceWoocommerceSellingPrice'),*/ 'regular'=>$langs->trans('ECommerceWoocommerceRegularPrice')), $ecommerceProductSyncPrice);
            ?>
          </td>
          <td><?php print $langs->trans('ECommerceWoocommerceProductSyncPriceDescription') ?></td>
        </tr>
        <tr <?php print $bc[$var] ?>>
          <td><span><?php print $langs->trans('ECommerceProductImageSyncDirection') ?></span></td>
          <td>
            <?php
              print $form->selectarray('ecommerce_product_image_synch_direction', $sync_direction_array, $ecommerceProductImageSynchDirection);
            ?>
          </td>
          <td><?php print $langs->trans('ECommerceProductImageSyncDirectionDescription') ?></td>
        </tr>
        <tr <?php print $bc[$var] ?>>
          <td><span><?php print $langs->trans('ECommerceProductRefSyncDirection') ?></span></td>
          <td>
            <?php
              print $form->selectarray('ecommerce_product_ref_synch_direction', $sync_direction_array, $ecommerceProductRefSynchDirection);
            ?>
          </td>
          <td><?php print $langs->trans('ECommerceProductRefSyncDirectionDescription') ?></td>
        </tr>
        <tr <?php print $bc[$var] ?>>
          <td><span><?php print $langs->trans('ECommerceProductDescriptionSyncDirection') ?></span></td>
          <td>
            <?php
              print $form->selectarray('ecommerce_product_description_synch_direction', $sync_direction_array, $ecommerceProductDescriptionSynchDirection);
            ?>
          </td>
          <td><?php print $langs->trans('ECommerceProductDescriptionSyncDirectionDescription') ?></td>
        </tr>
        <tr <?php print $bc[$var] ?>>
          <td><span><?php print $langs->trans('ECommerceProductShortDescriptionSyncDirection') ?></span></td>
          <td>
            <?php
              print $form->selectarray('ecommerce_product_short_description_synch_direction', $sync_direction_array, $ecommerceProductShortDescriptionSynchDirection);
            ?>
          </td>
          <td><?php print $langs->trans('ECommerceProductShortDescriptionSyncDirectionDescription') ?></td>
        </tr>
        <tr <?php print $bc[$var] ?>>
          <td><span><?php print $langs->trans('ECommerceProductWeightSyncDirection') ?></span></td>
          <td>
            <?php
              print $form->selectarray('ecommerce_product_weight_synch_direction', $sync_direction_array, $ecommerceProductWeightSynchDirection);
            ?>
          </td>
          <td><?php print $langs->trans('ECommerceProductWeightSyncDirectionDescription') ?></td>
        </tr>
        <tr <?php print $bc[$var] ?>>
          <td><span><?php print $langs->trans('ECommerceProductTaxSyncDirection') ?></span></td>
          <td>
            <?php
              print $form->selectarray('ecommerce_product_tax_synch_direction', $sync_direction_array, $ecommerceProductTaxSynchDirection);
            ?>
          </td>
          <td><?php print $langs->trans('ECommerceProductTaxSyncDirectionDescription') ?></td>
        </tr>
        <tr <?php print $bc[$var] ?>>
          <td><span><?php print $langs->trans('ECommerceProductStatusSyncDirection') ?></span></td>
          <td>
            <?php
              print $form->selectarray('ecommerce_product_status_synch_direction', $sync_direction_array, $ecommerceProductStatusSynchDirection);
            ?>
          </td>
          <td><?php print $langs->trans('ECommerceProductStatusSyncDirectionDescription') ?></td>
        </tr>
      </table>

    <!--
      <script type="text/javascript" language="javascript">
          jQuery(document).ready(function () {
            updateECommerceOAuthWordpress();
            $('#ecommerce_product_image_synch_direction').on("change", function () {
              updateECommerceOAuthWordpress();
            });

            function updateECommerceOAuthWordpress() {
              var image_sync = $('#ecommerce_product_image_synch_direction').val();
              if (image_sync == 'all' || image_sync == 'dtoe') {
                $('#ecommerce_oauth_wordpress').show();
              } else {
                $('#ecommerce_oauth_wordpress').hide();
              }
            }
          });
      </script>
    -->
<?php
}
?>

<?php
if ($ecommerceOAuth) {
?>
  <br>
  <div id="ecommerce_oauth_wordpress">
<?php
    print_titre($langs->trans("ECommerceOAuthWordpressSetup", $ecommerceOAuthWordpressOAuthSetupUri));
?>
		<table class="noborder" width="100%">
			<tr class="liste_titre">
				<td width="20%"><?php print $langs->trans('Parameter') ?></td>
				<td><?php print $langs->trans('Value') ?></td>
				<td><?php print $langs->trans('Description') ?></td>
			</tr>
<?php
	$var = !$var;
?>
			<tr <?php print $bc[$var] ?>>
				<td><?php print $langs->trans("ECommerceSiteOAuthRedirectUri"); ?></td>
				<td><input type="text" class="flat" name="ecommerce_oauth_redirect_uri" value="<?php print $ecommerceOAuthRedirectUri ?>" size="50" readonly="readonly"></td>
				<td><?php print $langs->trans('ECommerceSiteOAuthRedirectUriDescription') ?></td>
			</tr>
<?php
	$var = !$var;
?>
			<tr <?php print $bc[$var] ?>>
				<td><span class="fieldrequired"><?php print $langs->trans("ECommerceSiteOAuthId"); ?></span></td>
				<td><input type="text" class="flat" name="ecommerce_oauth_id" value="<?php print $ecommerceOAuthId ?>" size="20"></td>
				<td><?php print $langs->trans('ECommerceSiteOAuthIdDescription') ?></td>
			</tr>
<?php
	$var = !$var;
?>
			<tr <?php print $bc[$var] ?>>
				<td><span class="fieldrequired"><?php print $langs->trans("ECommerceSiteOAuthSecret"); ?></span></td>
				<td><input type="password" class="flat" name="ecommerce_oauth_secret" value="<?php print $ecommerceOAuthSecret ?>" size="20"></td>
				<td><?php print $langs->trans('ECommerceSiteOAuthSecretDescription') ?></td>
			</tr>
<?php
	if ($ecommerceOAuthGenerateToken) {
		$var = !$var;
?>
			<tr <?php print $bc[$var] ?>>
				<td><?php print $langs->trans("IsTokenGenerated"); ?></td>
				<td><?php print (is_object($ecommerceOAuthTokenObj) ? $langs->trans("HasAccessToken") : $langs->trans("NoAccessToken")); ?></td>
				<td>
<?php
		// Links to delete/checks token
		if (is_object($ecommerceOAuthTokenObj)) {
			print '<a class="button" href="' . $ecommerceOAuthRedirectUri . '&action=delete&backtourl=' . $ecommerceOAuthBackToUri . '">' . $langs->trans('DeleteAccess') . '</a><br><br>';
		}
		// Request remote token
		print '<a class="button" href="' . $ecommerceOAuthRedirectUri . '&backtourl=' . $ecommerceOAuthBackToUri . '">' . $langs->trans('RequestAccess') . '</a><br><br>';
		// Check remote access
		if ($ecommerceOAuthCheckTokenUri) {
			print $langs->trans("ECommerceOAuthCheckToken", $ecommerceOAuthCheckTokenUri);
		}
?>
				</td>
			</tr>
<?php
		$var = !$var;
?>
			<tr <?php print $bc[$var] ?>>
				<td><?php print $langs->trans("Token"); ?></td>
				<td><?php print (is_object($ecommerceOAuthTokenObj) ? $ecommerceOAuthTokenObj->getAccessToken() : ''); ?></td>
				<td></td>
			</tr>
<?php
		if (is_object($ecommerceOAuthTokenObj)) {
			$var = !$var;
?>
			<tr <?php print $bc[$var] ?>>
				<td><?php print $langs->trans("TOKEN_REFRESH"); ?></td>
				<td><?php print yn(!empty($ecommerceOAuthHasRefreshToken)); ?></td>
				<td></td>
			</tr>
<?php
			$var = !$var;
?>
			<tr <?php print $bc[$var] ?>>
				<td><?php print $langs->trans("TOKEN_EXPIRED"); ?></td>
				<td><?php print yn(!empty($ecommerceOAuthTokenExpired)); ?></td>
				<td></td>
			</tr>
<?php
			$var = !$var;
?>
			<tr <?php print $bc[$var] ?>>
				<td><?php print $langs->trans("TOKEN_EXPIRE_AT"); ?></td>
				<td><?php print $ecommerceOAuthTokenExpireDate; ?></td>
				<td></td>
			</tr>
<?php
		}
	}
?>
    </table>
  </div>
<?php
}
?>


<?php
  $var = true;
?>
         <br>
<?php
   print_titre($langs->trans("ECommerceRealTimeSynchroDolibarrToECommerceSetup"));
?>
          <table class="noborder" width="100%">

           <tr class="liste_titre">
             <td width="20%"><?php print $langs->trans('Parameter') ?></td>
             <td width="5%" align="center"><?php print $langs->trans('Enabled') ?></td>
             <td><?php print $langs->trans('Description') ?></td>
           </tr>
<?php
if ($conf->societe->enabled) {
  $var = !$var;
?>
         <tr <?php print $bc[$var] ?>>
           <td><?php print $langs->trans('ECommerceThirdParty') ?></td>
           <td align="center"><input type="checkbox" name="ecommerce_realtime_dtoe_thridparty" value="1" <?php print !isset($ecommerceRealtimeDtoe['thridparty']) || !empty($ecommerceRealtimeDtoe['thridparty']) ? ' checked' : '' ?>></td>
           <td><?php print $langs->trans('ECommerceRealTimeSynchroDolibarrToECommerceSetupDescription') ?></td>
         </tr>
<?php
  $var = !$var;
?>
         <tr <?php print $bc[$var] ?>>
           <td><?php print $langs->trans('ECommerceContact') ?></td>
           <td align="center"><input type="checkbox" name="ecommerce_realtime_dtoe_contact" value="1" <?php print !isset($ecommerceRealtimeDtoe['contact']) || !empty($ecommerceRealtimeDtoe['contact']) ? ' checked' : '' ?>></td>
           <td><?php print $langs->trans('ECommerceRealTimeSynchroDolibarrToECommerceSetupDescription') ?></td>
         </tr>
<?php
}
?>
<?php
if ($conf->product->enabled) {
  $var = !$var;
?>
         <tr <?php print $bc[$var] ?>>
           <td><?php print $langs->trans('ECommerceProduct') ?></td>
           <td align="center"><input type="checkbox" name="ecommerce_realtime_dtoe_product" value="1" <?php print !isset($ecommerceRealtimeDtoe['product']) || !empty($ecommerceRealtimeDtoe['product']) ? ' checked' : '' ?>></td>
           <td><?php print $langs->trans('ECommerceRealTimeSynchroDolibarrToECommerceSetupDescription') ?></td>
         </tr>
<?php
}
?>
<?php
if ($conf->commande->enabled && (!isset($ecommerceOrderActions['create_order']) || !empty($ecommerceOrderActions['create_order']))) {
  $var = !$var;
?>
         <tr <?php print $bc[$var] ?>>
           <td><?php print $langs->trans('ECommerceOrder') ?></td>
           <td align="center"><input type="checkbox" name="ecommerce_realtime_dtoe_order" value="1" <?php print !isset($ecommerceRealtimeDtoe['order']) || !empty($ecommerceRealtimeDtoe['order']) ? ' checked' : '' ?>></td>
           <td><?php print $langs->trans('ECommerceRealTimeSynchroDolibarrToECommerceSetupDescription') ?></td>
         </tr>
<?php
}
?>
         </table>

<?php
if ($conf->stock->enabled)
{
    $var=!$var;
?>
      <br>
<?php
    print_titre($langs->trans("StockSyncSetup"));
?>
			<table class="noborder" width="100%">

				<tr class="liste_titre">
					<td width="20%"><?php print $langs->trans('Parameter') ?></td>
					<td><?php print $langs->trans('Value') ?></td>
					<td><?php print $langs->trans('Description') ?></td>
				</tr>

				<tr <?php print $bc[$var] ?>>
					<td><span><?php print $langs->trans('ECommerceStockSyncDirection') ?></span></td>
					<td>
						<?php
                            $array=array('none'=>$langs->trans('None'), 'ecommerce2dolibarr'=>$langs->trans('ECommerceToDolibarr'), 'dolibarr2ecommerce'=>$langs->trans('DolibarrToECommerce'));
							print $form->selectarray('ecommerce_stock_sync_direction', $array, $ecommerceStockSyncDirection);
						?>
					</td>
					<td><?php print $langs->trans('ECommerceStockSyncDirectionDescription') ?></td>
				</tr>
<?php
    $var=!$var;
?>

				<tr <?php print $bc[$var] ?>>
					<td><span><?php print $langs->trans('ECommerceStockProduct') ?></span></td>
					<td>
							<?php
								print $formproduct->selectWarehouses($ecommerceFkWarehouse, 'ecommerce_fk_warehouse', 0, 1);
							?>
					</td>
					<td><?php print $langs->trans('ECommerceStockProductDescription', $langs->transnoentitiesnoconv('ECommerceStockSyncDirection')) ?></td>
				</tr>
      </table>
<?php
}
?>


<?php
if ($ecommerceOrderStatus)
{
   $var = true;
?>
          <br>
<?php
    print_titre($langs->trans("ECommerceOrderStatusSetup"));
?>
     			<table class="noborder" width="100%">

            <tr class="liste_titre">
              <td colspan="3"><?php print $langs->trans('ECommerceToDolibarr') ?></td>
            </tr>

            <tr class="liste_titre">
              <td width="20%"><?php print $langs->trans('Parameter') ?></td>
              <td><?php print $langs->trans('Value') ?></td>
              <td><?php print $langs->trans('Description') ?></td>
            </tr>
<?php
  foreach ($ecommerceOrderStatusForECommerceToDolibarr as $key => $value) {
    $var = !$var;
?>
            <tr <?php print $bc[$var] ?>>
              <td><span><?php print $value['label'] ?></span></td>
              <td>
                <?php
                  $array_list = array();
                  foreach ($ecommerceOrderStatusForDolibarrToECommerce as $key2 => $value2) {
                    $array_list[$key2] = $value2['label'];
                  }
                  print $form->selectarray('order_status_etod_'.$key, $array_list, $value['selected']);
                  print '&nbsp;'.$langs->trans('Billed').'&nbsp;?&nbsp;:&nbsp;';
                ?>
                <input type="checkbox" name="order_status_etod_billed_<?php print $key ?>" value="1" <?php print $value['billed'] ? ' checked' : '' ?>>
              </td>
              <td><?php print $langs->trans('ECommerceOrderStatusSetupDescription') ?></td>
            </tr>
<?php
  }
?>

<?php
    if ((!isset($ecommerceOrderActions['create_order']) || !empty($ecommerceOrderActions['create_order']))) {
  $var = true;
?>
            <tr class="liste_titre">
              <td colspan="3"><?php print $langs->trans('DolibarrToECommerce') ?></td>
            </tr>

            <tr class="liste_titre">
              <td width="20%"><?php print $langs->trans('Parameter') ?></td>
              <td><?php print $langs->trans('Value') ?></td>
              <td><?php print $langs->trans('Description') ?></td>
            </tr>

<?php
  $var = !$var;
?>
            <tr <?php print $bc[$var] ?>>
              <td><?php print $langs->trans('ECommerceOrderStatusDtoECheckLvlStatus') ?></td>
              <td><?php print $form->selectyesno('order_status_dtoe_check_lvl_status', $conf->global->ECOMMERCENG_WOOCOMMERCE_ORDER_STATUS_LVL_CHECK) ?></td>
              <td><?php print $langs->trans('ECommerceOrderStatusDtoECheckLvlStatusDescription') ?></td>
            </tr>
<?php
      foreach ($ecommerceOrderStatusForDolibarrToECommerce as $key => $value) {
        $var = !$var;
?>
            <tr <?php print $bc[$var] ?>>
              <td><span><?php print $value['label'] ?></span></td>
              <td>
                <?php
                  $array_list = array();
                  foreach ($ecommerceOrderStatusForECommerceToDolibarr as $key2 => $value2) {
                    $array_list[$key2] = $value2['label'];
                  }
                  print $form->selectarray('order_status_dtoe_'.substr($key, 1), $array_list, $value['selected'])
                ?>
              </td>
              <td><?php print $langs->trans('ECommerceOrderStatusSetupDescription') ?></td>
            </tr>
<?php
      }
  }
?>
          </table>
<?php
}
?>



<?php
if ($ecommerceType == 2)
{
?>
         <br>
<?php
   print_titre($langs->trans("ECommercengWoocommerceExtrafieldsCorrespondence"));
?>
          <table class="noborder" width="100%">

<?php
  if ($conf->product->enabled)
  {
    $var = true;
?>
            <script type="text/javascript">
               $(document).ready(function() {
                  var act_ef_crp_product_name = "act_ef_crp_<?php print $product_table_element ?>";
                  var act_ef_crp_product_all = $('input[name="act_all_ef_crp_<?php print $product_table_element ?>"]');
                  var act_ef_crp_product = $('input[name^="act_ef_crp_<?php print $product_table_element ?>"]');

                  act_ef_crp_product.click(function () {
                    var _this = $(this);
                    var name = _this.attr('name').substr(act_ef_crp_product_name.length);
                    var checked = _this.is(':checked');
                    $('input[name="ef_crp_<?php print $product_table_element ?>'+name+'"]').prop('disabled', !checked);
                    update_act_ef_crp_product_all();
                  });

                  update_act_ef_crp_product_all();
                  act_ef_crp_product_all.click(function () {
                    var _this = $(this);
                    var checked = _this.is(':checked');
                    $('input[name^="ef_crp_<?php print $product_table_element ?>"]').prop('disabled', !checked);
                    $('input[name^="act_ef_crp_<?php print $product_table_element ?>"]').prop('checked', checked);
                  });

                  function update_act_ef_crp_product_all() {
                    var nbElem = 0;
                    var nbElemChecked = 0;
                    $('input[name^="act_ef_crp_<?php print $product_table_element ?>"]').map(function (index, elem) {
                      nbElem++;
                      if ($(elem).is(':checked')) nbElemChecked++;
                    });

                    act_ef_crp_product_all.prop('checked', nbElem == nbElemChecked && nbElem > 0);
                  }
               });
            </script>
            <tr class="liste_titre">
              <td colspan="3"><?php print $langs->trans('ExtraFields') . ' : ' . $langs->trans('Products') ?></td>
              <td width="5%" align="center"><?php print $langs->trans('Enabled') ?></td>
            </tr>

            <tr class="liste_titre">
              <td width="20%"><?php print $langs->trans('Label') ?></td>
              <td><?php print $langs->trans('Value') ?></td>
              <td><?php print $langs->trans('Description') ?></td>
              <td width="5%" align="center"><input type="checkbox" name="act_all_ef_crp_<?php print $product_table_element ?>" value="1" /></td>
            </tr>
<?php
   foreach ($productExtrafields as $key => $label) {
     $var = !$var;
     $options_saved = $ecommerceExtrafieldsCorrespondence[$product_table_element][$key];
?>
           <tr <?php print $bc[$var] ?>>
             <td><span><?php print $label ?></span></td>
             <td>
               <input type="text" name="ef_crp_<?php print $product_table_element ?>_<?php print $key ?>" value="<?php print dol_escape_htmltag(isset($options_saved['correspondences']) ? $options_saved['correspondences'] : '') ?>"<?php print empty($options_saved['activated']) ? ' disabled' : '' ?> />
             </td>
             <td><?php print $langs->trans('ECommercengWoocommerceExtrafieldsCorrespondenceSetupDescription', $key) ?></td>
             <td width="5%" align="center"><input type="checkbox" name="act_ef_crp_<?php print $product_table_element ?>_<?php print $key ?>" value="1"<?php print !empty($options_saved['activated']) ? ' checked' : '' ?> /></td>
           </tr>
<?php
    }
  }
?>

<?php
  if ($conf->commande->enabled)
  {
    $var = true;
?>
          <script type="text/javascript">
             $(document).ready(function() {
                var act_ef_crp_order_name = "act_ef_crp_<?php print $order_table_element ?>";
                var act_ef_crp_order_all = $('input[name="act_all_ef_crp_<?php print $order_table_element ?>"]');
                var act_ef_crp_order = $('input[name^="act_ef_crp_<?php print $order_table_element ?>"]');

                act_ef_crp_order.click(function () {
                  var _this = $(this);
                  var name = _this.attr('name').substr(act_ef_crp_order_name.length);
                  var checked = _this.is(':checked');
                  $('input[name="ef_crp_<?php print $order_table_element ?>'+name+'"]').prop('disabled', !checked);
                  update_act_ef_crp_order_all();
                });

                update_act_ef_crp_order_all();
                act_ef_crp_order_all.click(function () {
                  var _this = $(this);
                  var checked = _this.is(':checked');
                  $('input[name^="ef_crp_<?php print $order_table_element ?>"]').prop('disabled', !checked);
                  $('input[name^="act_ef_crp_<?php print $order_table_element ?>"]').prop('checked', checked);
                });

                function update_act_ef_crp_order_all() {
                  var nbElem = 0;
                  var nbElemChecked = 0;
                  $('input[name^="act_ef_crp_<?php print $order_table_element ?>"]').map(function (index, elem) {
                    nbElem++;
                    if ($(elem).is(':checked')) nbElemChecked++;
                  });

                  act_ef_crp_order_all.prop('checked', nbElem == nbElemChecked && nbElem > 0);
                }
             });
          </script>
          <tr class="liste_titre">
            <td colspan="3"><?php print $langs->trans('ExtraFields') . ' : ' . $langs->trans('Orders') ?></td>
            <td width="5%" align="center"><?php print $langs->trans('Enabled') ?></td>
          </tr>

          <tr class="liste_titre">
            <td width="20%"><?php print $langs->trans('Label') ?></td>
            <td><?php print $langs->trans('Value') ?></td>
            <td><?php print $langs->trans('Description') ?></td>
            <td width="5%" align="center"><input type="checkbox" name="act_all_ef_crp_<?php print $order_table_element ?>" value="1" /></td>
          </tr>
<?php
     foreach ($orderExtrafields as $key => $label) {
       $var = !$var;
       $options_saved = $ecommerceExtrafieldsCorrespondence[$order_table_element][$key];
?>
           <tr <?php print $bc[$var] ?>>
             <td><span><?php print $label ?></span></td>
             <td>
               <input type="text" name="ef_crp_<?php print $order_table_element ?>_<?php print $key ?>" value="<?php print dol_escape_htmltag(isset($options_saved['correspondences']) ? $options_saved['correspondences'] : '') ?>"<?php print empty($options_saved['activated']) ? ' disabled' : '' ?> />
             </td>
             <td><?php print $langs->trans('ECommercengWoocommerceExtrafieldsCorrespondenceSetupDescription', $key) ?></td>
             <td width="5%" align="center"><input type="checkbox" name="act_ef_crp_<?php print $order_table_element ?>_<?php print $key ?>" value="1"<?php print !empty($options_saved['activated']) ? ' checked' : '' ?> /></td>
           </tr>
<?php
    }
    $var = true;
?>
      <script type="text/javascript">
         $(document).ready(function() {
            var act_ef_crp_order_name = "act_ef_crp_<?php print $order_line_table_element ?>";
            var act_ef_crp_order_all = $('input[name="act_all_ef_crp_<?php print $order_line_table_element ?>"]');
            var act_ef_crp_order = $('input[name^="act_ef_crp_<?php print $order_line_table_element ?>"]');

            act_ef_crp_order.click(function () {
              var _this = $(this);
              var name = _this.attr('name').substr(act_ef_crp_order_name.length);
              var checked = _this.is(':checked');
              $('input[name="ef_crp_<?php print $order_line_table_element ?>'+name+'"]').prop('disabled', !checked);
              update_act_ef_crp_order_all();
            });

            update_act_ef_crp_order_all();
            act_ef_crp_order_all.click(function () {
              var _this = $(this);
              var checked = _this.is(':checked');
              $('input[name^="ef_crp_<?php print $order_line_table_element ?>"]').prop('disabled', !checked);
              $('input[name^="act_ef_crp_<?php print $order_line_table_element ?>"]').prop('checked', checked);
            });

            function update_act_ef_crp_order_all() {
              var nbElem = 0;
              var nbElemChecked = 0;
              $('input[name^="act_ef_crp_<?php print $order_line_table_element ?>"]').map(function (index, elem) {
                nbElem++;
                if ($(elem).is(':checked')) nbElemChecked++;
              });

              act_ef_crp_order_all.prop('checked', nbElem == nbElemChecked && nbElem > 0);
            }
         });
      </script>
      <tr class="liste_titre">
        <td colspan="3"><?php print $langs->trans('ExtraFieldsLines') . ' : ' . $langs->trans('Orders') ?></td>
        <td width="5%" align="center"><?php print $langs->trans('Enabled') ?></td>
      </tr>

      <tr class="liste_titre">
        <td width="20%"><?php print $langs->trans('Label') ?></td>
        <td><?php print $langs->trans('Value') ?></td>
        <td><?php print $langs->trans('Description') ?></td>
        <td width="5%" align="center"><input type="checkbox" name="act_all_ef_crp_<?php print $order_line_table_element ?>" value="1" /></td>
      </tr>
<?php
    foreach ($orderLinesExtrafields as $key => $label) {
     $var = !$var;
     $options_saved = $ecommerceExtrafieldsCorrespondence[$order_line_table_element][$key];
?>
       <tr <?php print $bc[$var] ?>>
         <td><span><?php print $label ?></span></td>
         <td>
           <input type="text" name="ef_crp_<?php print $order_line_table_element ?>_<?php print $key ?>" value="<?php print dol_escape_htmltag(isset($options_saved['correspondences']) ? $options_saved['correspondences'] : '') ?>"<?php print empty($options_saved['activated']) ? ' disabled' : '' ?> />
         </td>
         <td><?php print $langs->trans('ECommercengWoocommerceExtrafieldsCorrespondenceSetupDescription', $key) ?></td>
         <td width="5%" align="center"><input type="checkbox" name="act_ef_crp_<?php print $order_line_table_element ?>_<?php print $key ?>" value="1"<?php print !empty($options_saved['activated']) ? ' checked' : '' ?> /></td>
       </tr>
<?php
    }
  }
?>
    </table>
<?php
    if (!empty($ecommerceOrderActions['create_order']) || !empty($ecommerceOrderActions['create_invoice']) || !empty($ecommerceOrderActions['create_supplier_invoice'])) {
?>
    <br>
<?php
    print_titre($langs->trans("ECommercePaymentGatewaysCorrespondence"));
?>
    <table class="noborder" width="100%">
        <tr class="liste_titre">
            <td width="20%"><?php print $langs->trans('ECommercePaymentGatewayLabel') ?></td>
        <?php
        if (!empty($ecommerceOrderActions['create_order']) || !empty($ecommerceOrderActions['create_invoice']) || !empty($ecommerceOrderActions['create_supplier_invoice'])) {
        ?>
            <td><?php print $langs->trans('PaymentMode') ?></td>
        <?php
        }
        if (!empty($ecommerceOrderActions['create_invoice']) || !empty($ecommerceOrderActions['create_supplier_invoice'])) {
            ?>
            <td><?php print $langs->trans('BankAccount') ?></td>
        <?php
        }
        if (!empty($ecommerceOrderActions['create_invoice'])) {
            ?>
            <td><?php print $langs->trans('ECommerceCreateAssociatePaymentForInvoice') ?></td>
            <?php
            if (!empty($ecommerceOrderActions['send_invoice_by_mail'])) {
                ?>
                <td><?php print $langs->trans('ECommerceSelectMailModelForSendInvoice') ?></td>
                <?php
            }
        }
        if (!empty($ecommerceOrderActions['create_supplier_invoice'])) {
        ?>
            <td><?php print $langs->trans('Supplier') ?></td>
            <td><?php print $langs->trans('ECommerceProductForFee') ?></td>
            <td><?php print $langs->trans('ECommerceCreateAssociatePaymentForSupplierInvoice') ?></td>
        <?php
        }
        ?>
        </tr>
<?php
    require_once DOL_DOCUMENT_ROOT . '/core/class/html.formmail.class.php';
    $formmail = new FormMail($db);
    $type_template = 'facture_send';
    $modelmail_array = array();
    $result = $formmail->fetchAllEMailTemplate($type_template, $user, $langs);
    if ($result < 0) {
        setEventMessages($formmail->error, $formmail->errors, 'errors');
    }
    foreach ($formmail->lines_model as $line) {
        if (preg_match('/\((.*)\)/', $line->label, $reg)) {
            $modelmail_array[$line->id] = $langs->trans($reg[1]);        // langs->trans when label is __(xxx)__
        } else {
            $modelmail_array[$line->id] = $line->label;
        }
        if ($line->lang) $modelmail_array[$line->id] .= ' (' . $line->lang . ')';
        if ($line->private) $modelmail_array[$line->id] .= ' - ' . $langs->trans("Private");
    }

    foreach ($ecommercePaymentGateways as $key => $infos) {
        $var = !$var;
?>
        <tr <?php print $bc[$var] ?>>
            <td><?php print $infos['payment_gateway_label'] ?></td>
        <?php
        if (!empty($ecommerceOrderActions['create_order']) || !empty($ecommerceOrderActions['create_invoice']) || !empty($ecommerceOrderActions['create_supplier_invoice'])) {
        ?>
            <td><?php print $form->select_types_paiements($infos['payment_mode_id'], 'payment_mode_id_'.$key) ?></td>
        <?php
        }
        if (!empty($ecommerceOrderActions['create_invoice']) || !empty($ecommerceOrderActions['create_supplier_invoice']) && $conf->banque->enabled) {
        ?>
            <td><?php $form->select_comptes($infos['bank_account_id'], 'bank_account_id_'.$key, 0, '', 1) ?></td>
            <?php
        }
        if (!empty($ecommerceOrderActions['create_invoice'])) {
            ?>
            <td><input type="checkbox" id="<?php print 'create_invoice_payment_'.$key ?>" name="<?php print 'create_invoice_payment_'.$key ?>" value="1" <?php print !empty($infos['create_invoice_payment']) ? ' checked' : '' ?>></td>
            <?php
            if (!empty($ecommerceOrderActions['send_invoice_by_mail'])) {
            ?>
            <td>
            <?php
                // Zone to select email template
                if (count($modelmail_array) > 0) {
                    print $form->selectarray('mail_model_for_send_invoice_'.$key, $modelmail_array, $infos['mail_model_for_send_invoice'], 1, 0, 0, '', 0, 0, 0, '', 'minwidth100');
                } else {
                    print '<select name="mail_model_for_send_invoice_' . $key . '" disabled="disabled"><option value="none">' . $langs->trans("NoTemplateDefined") . '</option></select>';    // Do not put 'disabled' on 'option' tag, it is already on 'select' and it makes chrome crazy.
                }
                if ($user->admin) print info_admin($langs->trans("YouCanChangeValuesForThisListFrom", $langs->transnoentitiesnoconv('Setup') . ' - ' . $langs->transnoentitiesnoconv('EMails')), 1);
            }
            ?>
            </td>
        <?php
        }
        if (!empty($ecommerceOrderActions['create_supplier_invoice'])) {
        ?>
            <td><?php print $form->select_company($infos['supplier_id'], 'supplier_id_'.$key, 's.fournisseur=1 AND status=1', 'SelectThirdParty', 0, 0, null, 0, 'minwidth300') ?></td>
            <td><?php $form->select_produits($infos['product_id_for_fee'], 'product_id_for_fee_'.$key, '', $conf->product->limit_size, 0, -1, 2, '', 0, array(), 0, '1', 0, 'maxwidth300') ?></td>
            <td><input type="checkbox" id="<?php print 'create_supplier_invoice_payment_'.$key ?>" name="<?php print 'create_supplier_invoice_payment_'.$key ?>" value="1" <?php print !empty($infos['create_supplier_invoice_payment']) ? ' checked' : '' ?>></td>
            <?php
        }
        ?>
        </tr>
<?php
        }
?>
    </table>
<?php
    }
}
?>

    <br>
    <center>
<?php
if ($siteDb->id)
{
?>
    <input type="submit" name="save_site" class="butAction" value="<?php print $langs->trans('Save') ?>">
<?php
if ($siteDb->type == 2)
{
?>
    <a class="butAction" href='javascript:eCommerceConfirmWoocommerceUpdateDictTaxClass("site_form_detail", "<?php print $langs->trans('ECommerceWoocommerceConfirmUpdateDictTaxClasses') ?>")'><?php print $langs->trans('ECommerceWoocommerceUpdateDictTaxClasses') ?></a>
    <a class="butAction" href='javascript:eCommerceConfirmUpdatePaymentGateways("site_form_detail", "<?php print $langs->trans('ECommerceConfirmUpdatePaymentGateways') ?>")'><?php print $langs->trans('ECommerceUpdatePaymentGateways') ?></a>
<?php
}
?>
    <a class="butActionDelete" href='javascript:eCommerceConfirmDelete("site_form_detail", "<?php print $langs->trans('ECommerceConfirmDelete') ?>")'><?php print $langs->trans('Delete') ?></a>
<?php
}
else
{
?>
    <input type="submit" name="save_site" class="butAction" value="<?php print $langs->trans('Add') ?>">
<?php
}
?>
    </center>
</form>

<?php
if ($success != array())
	foreach ($success as $succes)
		print '<p class="ok">'.$succes.'</p>';
?>
<br>
