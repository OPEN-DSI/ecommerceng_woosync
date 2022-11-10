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
include_once DOL_DOCUMENT_ROOT.'/core/lib/product.lib.php';
dol_include_once('/ecommerceng/class/html.formecommerceng.class.php');

$formproduct = new FormProduct($db);
$formecommerceng = new FormECommerceNg($db);

llxHeader();

print_fiche_titre($langs->trans("ECommerceSetup"),$linkback,'setup');

?>
	<script type="text/javascript" src="<?php print dol_buildpath('/ecommerceng/js/form.js',1); ?>"></script>
	<br>
	<form id="site_form_select" name="site_form_select" action="<?php $_SERVER['PHP_SELF'] ?>" method="post">
		<input type="hidden" name="token" value="<?php print newToken(); ?>" />
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
			<input type="hidden" name="token" value="<?php print newToken(); ?>">
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
			<input type="checkbox" id="ecommerce_create_invoice" name="ecommerce_create_invoice"
				   value="1" <?php print !empty($ecommerceOrderActions['create_invoice']) ? ' checked' : '' ?>>&nbsp;
			<label for="ecommerce_create_invoice"><?php print $langs->trans('ECommerceCreateInvoice') ?></label>&nbsp;
				<?php
				if (!empty($ecommerceOrderActions['create_invoice'])) {
			print $form->selectarray('ecommerce_create_invoice_type', array(Facture::TYPE_STANDARD => $langs->trans('InvoiceStandard'), Facture::TYPE_DEPOSIT => $langs->trans('InvoiceDeposit')), $ecommerceCreateInvoiceType);
			?>
			&nbsp;<input type="checkbox" id="ecommerce_send_invoice_by_mail"
						 name="ecommerce_send_invoice_by_mail"
						 value="1" <?php print !isset($ecommerceOrderActions['send_invoice_by_mail']) || !empty($ecommerceOrderActions['send_invoice_by_mail']) ? ' checked' : '' ?>>&nbsp;
			<label for="ecommerce_send_invoice_by_mail"><?php print $langs->trans('ECommerceSendInvoiceByMail') ?></label>
			<?php
			print '<div id="ecommerce_create_invoice_deposit_options">';
			$arraylist = array(
				'amount' => $langs->transnoentitiesnoconv('FixAmount', $langs->transnoentitiesnoconv('Deposit')),
				'variable' => $langs->transnoentitiesnoconv('VarAmountOneLine', $langs->transnoentitiesnoconv('Deposit')),
				'variablealllines' => $langs->transnoentitiesnoconv('VarAmountAllLines')
			);
			print '<br>';
			print $form->selectarray('ecommerce_create_invoice_deposit_type', $arraylist, $ecommerceOrderActions['create_invoice_deposit_type'], 0, 0, 0, '', 1);
			print '<span id="ecommerce_create_invoice_deposit_type_variable" > '.$langs->trans('Value');
			print ': <input type="text" id="ecommerce_create_invoice_deposit_value" name="ecommerce_create_invoice_deposit_value" size="3" value="' . $ecommerceOrderActions['create_invoice_deposit_value'] . '"/></span>';
			print '</div>';
			?>
			<script type="text/javascript">
				jQuery(document).ready(function () {
					var ecommerce_create_invoice_type_select = $("#ecommerce_create_invoice_type");
					var ecommerce_create_invoice_deposit_options_div = $("#ecommerce_create_invoice_deposit_options");
					var ecommerce_create_invoice_deposit_type_select = $("#ecommerce_create_invoice_deposit_type");
					var ecommerce_create_invoice_deposit_type_variable_span = $("#ecommerce_create_invoice_deposit_type_variable");

					ecommerce_update_invoice_deposit_options();
					ecommerce_create_invoice_type_select.on('change', function() {
						ecommerce_update_invoice_deposit_options();
					});
					ecommerce_update_invoice_deposit_value_text();
					ecommerce_create_invoice_deposit_type_select.on('change', function() {
						ecommerce_update_invoice_deposit_value_text();
					});

					function ecommerce_update_invoice_deposit_options() {
						var invoice_type = ecommerce_create_invoice_type_select.val();

						if (invoice_type == <?php print Facture::TYPE_DEPOSIT ?>) {
							ecommerce_create_invoice_deposit_options_div.show();
						} else {
							ecommerce_create_invoice_deposit_options_div.hide();
						}
					}

					function ecommerce_update_invoice_deposit_value_text() {
						var invoice_deposit_type = ecommerce_create_invoice_deposit_type_select.val();

						if (invoice_deposit_type == 'amount') {
							ecommerce_create_invoice_deposit_type_variable_span.hide();
						} else {
							ecommerce_create_invoice_deposit_type_variable_span.show();
						}
					}
				});
			</script>
			<br>
			<input type="checkbox" id="ecommerce_create_invoice_if_amount_0" name="ecommerce_create_invoice_if_amount_0" value="1" <?php print !empty($ecommerceOrderActions['create_invoice_if_amount_0']) ? ' checked' : '' ?>>&nbsp;<label for="ecommerce_create_invoice_if_amount_0"><?php print $langs->trans('ECommerceCreateInvoiceIfAmount0') ?></label>&nbsp;
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
			<br>
			<input type="checkbox" id="ecommerce_fee_line_as_item_line" name="ecommerce_fee_line_as_item_line" value="1" <?php print !empty($ecommerceOrderActions['fee_line_as_item_line']) ? ' checked' : '' ?>>&nbsp;<label for="ecommerce_fee_line_as_item_line"><?php print $langs->trans('ECommerceFeeLineAsItemLine') ?></label>&nbsp;
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
	<tr <?php print $bc[$var] ?>>
		<td><?php print $langs->trans('ECommerceDontUpdateDolibarrCompany') ?></td>
		<td>
			<input type="checkbox" id="ecommerce_dont_update_dolibarr_company" name="ecommerce_dont_update_dolibarr_company" value="1" <?php print !empty($ecommerceDontUpdateDolibarrCompany) ? ' checked' : '' ?>>
		</td>
		<td><?php print $langs->trans('ECommerceDontUpdateDolibarrCompanyDescription') ?></td>
	</tr>
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
		<?php
		$var = !$var;
		?>
		<tr <?php print $bc[$var] ?>>
			<td><span><?php print $langs->trans('ECommerceSiteWebHooksVolumetryAlert') ?></span></td>
			<td><input type="number" class="flat" id="ecommerce_web_hooks_volumetry_alert" name="ecommerce_web_hooks_volumetry_alert" value="<?php print $eCommerceSiteWebHooksVolumetryAlert ?>" size="20"></td>
			<td><?php print $langs->trans('ECommerceSiteWebHooksVolumetryAlertDescription') ?></td>
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
			  <td><span><?php print $langs->trans('ECommerceProductWeightUnits') ?></span></td>
			  <td>
				  <?php
				  if (version_compare(DOL_VERSION, "10.0.0") < 0) {
					  print $formproduct->select_measuring_units("ecommerce_product_weight_units", "weight", $ecommerceProductWeightUnits);
				  } else {
					  print $formproduct->selectMeasuringUnits("ecommerce_product_weight_units", "weight", $ecommerceProductWeightUnits, 0, 2);
				  }
				  ?>
			  </td>
			  <td><?php print $langs->trans('ECommerceProductWeightUnitsDescription') ?></td>
		  </tr>
		  <tr <?php print $bc[$var] ?>>
			  <td><span><?php print $langs->trans('ECommerceProductDimensionSyncDirection') ?></span></td>
			  <td>
				  <?php
				  print $form->selectarray('ecommerce_product_dimension_synch_direction', $sync_direction_array, $ecommerceProductDimensionSynchDirection);
				  ?>
			  </td>
			  <td><?php print $langs->trans('ECommerceProductDimensionSyncDirectionDescription') ?></td>
		  </tr>
		  <tr <?php print $bc[$var] ?>>
			  <td><span><?php print $langs->trans('ECommerceProductDimensionUnits') ?></span></td>
			  <td>
				  <?php
				  if (version_compare(DOL_VERSION, "10.0.0") < 0) {
					  print $formproduct->select_measuring_units("ecommerce_product_dimension_units", "size", $ecommerceProductDimensionUnits);
				  } else {
					  print $formproduct->selectMeasuringUnits("ecommerce_product_dimension_units", "size", $ecommerceProductDimensionUnits, 0, 2);
				  }
				  ?>
			  </td>
			  <td><?php print $langs->trans('ECommerceProductDimensionUnitsDescription') ?></td>
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
<?php if ($ecommerceType == 2) { ?>
 		<tr <?php print $bc[$var] ?>>
		 <td><span><?php print $langs->trans('ECommerceWoocommerceProductVariationMode') ?></span></td>
		 <td>
		  <?php
		  $array=array('one_to_one'=>$langs->trans('ECommerceWoocommerceProductVariationOneToOne'), 'all_to_one'=>$langs->trans('ECommerceWoocommerceProductVariationAllToOne'));
		  print $form->selectarray('ecommerce_product_variation_mode', $array, $ecommerceProductVariationMode);
		  ?>
		 </td>
		 <td><?php print $langs->trans('ECommerceWoocommerceProductVariationModeDescription') ?></td>
		</tr>
<?php } ?>
      </table>

<?php
if (!empty($conf->accounting->enabled)) {
	print_titre($langs->trans("MenuDefaultAccounts"));
?>
	<table class="noborder centpercent">
		<?php
		foreach ($list_account as $key) {
			$reg = array();
			if (preg_match('/---(.*)---/', $key, $reg)) {
				print '<tr class="liste_titre"><td>' . $langs->trans($reg[1]) . '</td><td></td></tr>';
			} else {
				print '<tr class="oddeven value">';
				// Param
				$label = $langs->trans('ECOMMERCE_' . strtoupper($key));
				print '<td width="50%">' . $label . '</td>';
				// Value
				print '<td>'; // Do not force class=right, or it align also the content of the select box
				print $formaccounting->select_account($ecommerceDefaultAccount[$key], $key, 1, '', 1, 1);
				$const_name = strtoupper($key);
				print ' ( ' . $langs->trans('DefaultValue') . ' : ' . (empty($conf->global->$const_name) || $conf->global->$const_name == -1 ? $langs->trans('NotDefined') : $conf->global->$const_name) . ' )';
				print '</td>';
				print '</tr>';
			}
		}
		?>
	</table>
<?php } ?>

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
						<span id="warehouse_dolibarr2ecommerce"><?php print $formecommerceng->multiselectWarehouses($ecommerceFkWarehouseToECommerce, 'ecommerce_fk_warehouse_to_ecommerce') ?></span>
						<span id="warehouse_ecommerce2dolibarr"><?php print $formproduct->selectWarehouses($ecommerceFkWarehouse, 'ecommerce_fk_warehouse', 0, 1) ?></span>
					</td>
					<td><?php print $langs->trans('ECommerceStockProductDescription', $langs->transnoentitiesnoconv('ECommerceStockSyncDirection')) ?></td>
				</tr>
      </table>
	<script type="text/javascript">
		function update_warehouse_display() {
			var value = jQuery('#ecommerce_stock_sync_direction').val();
			if (value == 'ecommerce2dolibarr') {
				jQuery('#warehouse_dolibarr2ecommerce').hide();
				jQuery('#warehouse_ecommerce2dolibarr').show();
			} else if (value == 'dolibarr2ecommerce') {
				jQuery('#warehouse_dolibarr2ecommerce').show();
				jQuery('#warehouse_ecommerce2dolibarr').hide();
			} else {
				jQuery('#warehouse_dolibarr2ecommerce').hide();
				jQuery('#warehouse_ecommerce2dolibarr').hide();
			}
		}
		jQuery(document).ready(function (){
			update_warehouse_display();
			jQuery('#ecommerce_stock_sync_direction').on('change', function () {
				update_warehouse_display();
			});
		});
	</script>
<?php
}
?>


<?php
if ($ecommerceOrderStatus)
{
	if ((!isset($ecommerceOrderActions['create_order']) || !empty($ecommerceOrderActions['create_order']))) {
		print_titre($langs->trans("ECommerceOrdersSyncSetup"));
		?>
		<table class="noborder" width="100%">
			<tr class="liste_titre">
				<td width="20%"><?php print $langs->trans('Parameter') ?></td>
				<td><?php print $langs->trans('Value') ?></td>
				<td><?php print $langs->trans('Description') ?></td>
			</tr>
			<tr <?php print $bc[$var] ?>>
				<td><span><?php print $langs->trans('ECommerceWoocommerceOrderFirstDateForECommerceToDolibarr') ?></span></td>
				<td>
					<?php
					$form->select_date($ecommerceOrderFirstDateForECommerceToDolibarr !== '' ? $ecommerceOrderFirstDateForECommerceToDolibarr : -1, 'ecommerce_order_first_date_etod');
					?>
				</td>
				<td><?php print $langs->trans('ECommerceWoocommerceOrderFirstDateForECommerceToDolibarrDescription') ?></td>
			</tr>
			<tr <?php print $bc[$var] ?>>
				<td><span><?php print $langs->trans('ECommerceWoocommerceOrderMetaDataInProductLineToDescriptionForECommerceToDolibarr') ?></span></td>
				<td><input type="checkbox" name="ecommerce_order_metadata_product_lines_to_description_etod" value="1" <?php print !empty($ecommerceOrderMetadataProductLinesToDescriptionEtod) ? ' checked' : '' ?>></td>
				<td><?php print $langs->trans('ECommerceWoocommerceOrderMetaDataInProductLineToDescriptionForECommerceToDolibarrDescription') ?></td>
			</tr>
			<tr <?php print $bc[$var] ?>>
				<td><span><?php print $langs->trans('ECommerceWoocommerceOrderFilterMetaDataInProductLineToDescriptionForECommerceToDolibarr') ?></span></td>
				<td>
					<?php
					$array=array('exclude'=>$langs->trans('ECommerceExclude'), 'include'=>$langs->trans('ECommerceInclude'));
					print $form->selectarray('ecommerce_order_filter_mode_metadata_product_lines_to_description_etod', $array, $ecommerceOrderFilterModeMetadataProductLinesToDescriptionEtod);
					?>
					<input type="text" name="ecommerce_order_filter_keys_metadata_product_lines_to_description_etod" value="<?php print dol_escape_js(dol_escape_htmltag($ecommerceOrderFilterKeysMetadataProductLinesToDescriptionEtod), 2) ?>"></td>
				<td><?php print $langs->trans('ECommerceWoocommerceOrderFilterMetaDataInProductLineToDescriptionForECommerceToDolibarrDescription') ?></td>
			</tr>
		</table>
<?php
	}
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
				  <?php
				  print '&nbsp;'.$langs->trans('ECommerceSynchronize').'&nbsp;?&nbsp;:&nbsp;';
				  ?>
				  <input type="checkbox" name="order_status_etod_synchronize_<?php print $key ?>" value="1" <?php print $value['synchronize'] ? ' checked' : '' ?>>
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
if ($ecommerceType == 2) {
	if (!empty($productExtrafields) || !empty($thirdPartyExtrafields) || !empty($orderExtrafields) || !empty($orderLinesExtrafields)) {
		?>
		<script type="text/javascript">
			$(document).ready(function () {
				var ef_crp_all = $('.ef_crp_all');

				$.map(ef_crp_all, function (item) {
					var table = $(item).closest('table');

					act_ef_crp_update_all_checkbox(table);
				});

				$('.ef_crp_state').click(function () {
					var _this = $(this);
					var state = _this.is(':checked');
					var table = _this.closest('table');
					var tr_line = _this.closest('tr');

					tr_line.find('.ef_crp_value').prop('disabled', !state);
					act_ef_crp_update_all_checkbox(table);
				});

				ef_crp_all.click(function () {
					var _this = $(this);
					var table = _this.closest('table');
					var state = _this.is(':checked');

					table.find('.ef_crp_state').prop('checked', state);
					table.find('.ef_crp_value').prop('disabled', !state);
				});

				function act_ef_crp_update_all_checkbox(table) {
					var all_checkbox_checked = table.find('.ef_crp_state').length == table.find('.ef_crp_state:checked').length;

					table.find('.ef_crp_all').prop('checked', all_checkbox_checked);
				}
			});
		</script>
	<?php
	if (!empty($productExtrafields)) {
	?>
		<br>
		<?php
		print_titre($langs->trans("ECommercengWoocommerceProductExtrafieldsCorrespondenceAttribute"));
		?>
		<table class="noborder centpercent">
			<tr class="liste_titre">
				<td colspan="3"><?php print $langs->trans('ExtraFields') . ' : ' . $langs->trans('Products') ?></td>
				<td width="5%" align="center"><?php print $langs->trans('Enabled') ?></td>
			</tr>
			<tr class="liste_titre">
				<td width="20%"><?php print $langs->trans('ExtraFields') ?></td>
				<td><?php print $langs->trans('ECommercengWoocommerceProductAttribute') ?></td>
				<td><?php print $langs->trans('Description') ?></td>
				<td width="5%" align="center"><input type="checkbox" class="ef_crp_all" name="attribute_ef_crp_all"
													 value="1"/></td>
			</tr>
			<?php
			foreach ($productExtrafields as $key => $label) {
				$var = !$var;
				$options_saved = $ecommerceProductExtrafieldsCorrespondenceAttributes[$key];
				?>
				<tr class="oddeven">
					<td><span><?php print $label ?></span></td>
					<td>
						<?php
						$value = isset($attributes_array[$options_saved['correspondences']]) ? $options_saved['correspondences'] : (isset($attributes_name_array[$label]) ? $attributes_name_array[$label] : '');
						print $form->selectarray('attribute_ef_crp_value_'.$key, $attributes_array, $value, 1, 0, 0, '', 0, 0, empty($options_saved['activated']) ? 1 : 0, '', 'ef_crp_value minwidth300');
						?>
					</td>
					<td><?php print $langs->trans('ECommercengWoocommerceProductExtrafieldsCorrespondenceAttributeSetupDescription', $key) ?></td>
					<td width="5%" align="center"><input type="checkbox" class="ef_crp_state"
														 name="attribute_ef_crp_state_<?php print $key ?>"
														 value="1"<?php print !empty($options_saved['activated']) ? ' checked' : '' ?> />
					</td>
				</tr>
				<?php
			}
			?>
		</table>

		<br>
		<?php
		print_titre($langs->trans("ECommercengWoocommerceExtrafieldsCorrespondence"));
		?>
		<table class="noborder centpercent">
			<tr class="liste_titre">
				<td colspan="3"><?php print $langs->trans('ExtraFields') . ' : ' . $langs->trans('Products') ?></td>
				<td width="5%" align="center"><?php print $langs->trans('Enabled') ?></td>
			</tr>
			<tr class="liste_titre">
				<td width="20%"><?php print $langs->trans('Label') ?></td>
				<td><?php print $langs->trans('Value') ?></td>
				<td><?php print $langs->trans('Description') ?></td>
				<td width="5%" align="center"><input type="checkbox" class="ef_crp_all"
													 name="act_all_ef_crp_<?php print $product_table_element ?>"
													 value="1"/></td>
			</tr>
			<?php
			foreach ($productExtrafields as $key => $label) {
				$var = !$var;
				$options_saved = $ecommerceExtrafieldsCorrespondence[$product_table_element][$key];
				?>
				<tr <?php print $bc[$var] ?>>
					<td><span><?php print $label ?></span></td>
					<td>
						<input type="text" class="ef_crp_value"
							   name="ef_crp_<?php print $product_table_element ?>_<?php print $key ?>"
							   value="<?php print dol_escape_htmltag(isset($options_saved['correspondences']) ? $options_saved['correspondences'] : '') ?>"<?php print empty($options_saved['activated']) ? ' disabled' : '' ?> />
					</td>
					<td><?php print $langs->trans('ECommercengWoocommerceExtrafieldsCorrespondenceSetupDescription', $key) ?></td>
					<td width="5%" align="center"><input type="checkbox" class="ef_crp_state"
														 name="act_ef_crp_<?php print $product_table_element ?>_<?php print $key ?>"
														 value="1"<?php print !empty($options_saved['activated']) ? ' checked' : '' ?> />
					</td>
				</tr>
				<?php
			}
			?>
		</table>
	<?php
	}

	if (!empty($thirdPartyExtrafields)) {
	?>
	<table class="noborder centpercent">
		<tr class="liste_titre">
			<td colspan="3"><?php print $langs->trans('ExtraFields') . ' : ' . $langs->trans('ThirdParty') ?></td>
			<td width="5%" align="center"><?php print $langs->trans('Enabled') ?></td>
		</tr>
		<tr class="liste_titre">
			<td width="20%"><?php print $langs->trans('Label') ?></td>
			<td><?php print $langs->trans('Value') ?></td>
			<td><?php print $langs->trans('Description') ?></td>
			<td width="5%" align="center"><input type="checkbox" class="ef_crp_all"
												 name="act_all_ef_crp_<?php print $thirdparty_table_element ?>"
												 value="1"/></td>
		</tr>
		<?php
		foreach ($thirdPartyExtrafields as $key => $label) {
			$var = !$var;
			$options_saved = $ecommerceExtrafieldsCorrespondence[$thirdparty_table_element][$key];
			?>
			<tr <?php print $bc[$var] ?>>
				<td><span><?php print $label ?></span></td>
				<td>
					<input type="text" class="ef_crp_value"
						   name="ef_crp_<?php print $thirdparty_table_element ?>_<?php print $key ?>"
						   value="<?php print dol_escape_htmltag(isset($options_saved['correspondences']) ? $options_saved['correspondences'] : '') ?>"<?php print empty($options_saved['activated']) ? ' disabled' : '' ?> />
				</td>
				<td><?php print $langs->trans('ECommercengWoocommerceExtrafieldsCorrespondenceSetupDescription', $key) ?></td>
				<td width="5%" align="center"><input type="checkbox" class="ef_crp_state"
													 name="act_ef_crp_<?php print $thirdparty_table_element ?>_<?php print $key ?>"
													 value="1"<?php print !empty($options_saved['activated']) ? ' checked' : '' ?> />
				</td>
			</tr>
			<?php
		}
		?>
	</table>
	<?php
	}

	if (!empty($orderExtrafields)) {
	?>
		<table class="noborder centpercent">
			<tr class="liste_titre">
				<td colspan="3"><?php print $langs->trans('ExtraFields') . ' : ' . $langs->trans('Orders') ?></td>
				<td width="5%" align="center"><?php print $langs->trans('Enabled') ?></td>
			</tr>
			<tr class="liste_titre">
				<td width="20%"><?php print $langs->trans('Label') ?></td>
				<td><?php print $langs->trans('Value') ?></td>
				<td><?php print $langs->trans('Description') ?></td>
				<td width="5%" align="center"><input type="checkbox" class="ef_crp_all"
													 name="act_all_ef_crp_<?php print $order_table_element ?>"
													 value="1"/></td>
			</tr>
			<?php
			foreach ($orderExtrafields as $key => $label) {
				$var = !$var;
				$options_saved = $ecommerceExtrafieldsCorrespondence[$order_table_element][$key];
				?>
				<tr <?php print $bc[$var] ?>>
					<td><span><?php print $label ?></span></td>
					<td>
						<input type="text" class="ef_crp_value"
							   name="ef_crp_<?php print $order_table_element ?>_<?php print $key ?>"
							   value="<?php print dol_escape_htmltag(isset($options_saved['correspondences']) ? $options_saved['correspondences'] : '') ?>"<?php print empty($options_saved['activated']) ? ' disabled' : '' ?> />
					</td>
					<td><?php print $langs->trans('ECommercengWoocommerceExtrafieldsCorrespondenceSetupDescription', $key) ?></td>
					<td width="5%" align="center"><input type="checkbox" class="ef_crp_state"
														 name="act_ef_crp_<?php print $order_table_element ?>_<?php print $key ?>"
														 value="1"<?php print !empty($options_saved['activated']) ? ' checked' : '' ?> />
					</td>
				</tr>
				<?php
			}
			?>
		</table>
	<?php
	}

	if (!empty($orderLinesExtrafields)) {
	?>
		<table class="noborder centpercent">
			<tr class="liste_titre">
				<td colspan="3"><?php print $langs->trans('ExtraFieldsLines') . ' : ' . $langs->trans('Orders') ?></td>
				<td width="5%" align="center"><?php print $langs->trans('Enabled') ?></td>
			</tr>
			<tr class="liste_titre">
				<td width="20%"><?php print $langs->trans('Label') ?></td>
				<td><?php print $langs->trans('Value') ?></td>
				<td><?php print $langs->trans('Description') ?></td>
				<td width="5%" align="center"><input type="checkbox" class="ef_crp_all"
													 name="act_all_ef_crp_<?php print $order_line_table_element ?>"
													 value="1"/></td>
			</tr>
			<?php
			foreach ($orderLinesExtrafields as $key => $label) {
				$var = !$var;
				$options_saved = $ecommerceExtrafieldsCorrespondence[$order_line_table_element][$key];
				?>
				<tr <?php print $bc[$var] ?>>
					<td><span><?php print $label ?></span></td>
					<td>
						<input type="text" class="ef_crp_value"
							   name="ef_crp_<?php print $order_line_table_element ?>_<?php print $key ?>"
							   value="<?php print dol_escape_htmltag(isset($options_saved['correspondences']) ? $options_saved['correspondences'] : '') ?>"<?php print empty($options_saved['activated']) ? ' disabled' : '' ?> />
					</td>
					<td><?php print $langs->trans('ECommercengWoocommerceExtrafieldsCorrespondenceSetupDescription', $key) ?></td>
					<td width="5%" align="center"><input type="checkbox" class="ef_crp_state"
														 name="act_ef_crp_<?php print $order_line_table_element ?>_<?php print $key ?>"
														 value="1"<?php print !empty($options_saved['activated']) ? ' checked' : '' ?> />
					</td>
				</tr>
				<?php
			}
			?>
		</table>
	<?php
	}
}


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
					<td><?php print $form->select_types_paiements($infos['payment_mode_id'], 'payment_mode_id_' . $key) ?></td>
					<?php
				}
				if (!empty($ecommerceOrderActions['create_invoice']) || !empty($ecommerceOrderActions['create_supplier_invoice']) && $conf->banque->enabled) {
					?>
					<td><?php $form->select_comptes($infos['bank_account_id'], 'bank_account_id_' . $key, 0, '', 1) ?></td>
					<?php
				}
				if (!empty($ecommerceOrderActions['create_invoice'])) {
					?>
					<td><input type="checkbox" id="<?php print 'create_invoice_payment_' . $key ?>"
							   name="<?php print 'create_invoice_payment_' . $key ?>"
							   value="1" <?php print !empty($infos['create_invoice_payment']) ? ' checked' : '' ?>></td>
					<?php
					if (!empty($ecommerceOrderActions['send_invoice_by_mail'])) {
						?>
						<td>
						<?php
						// Zone to select email template
						if (count($modelmail_array) > 0) {
							print $form->selectarray('mail_model_for_send_invoice_' . $key, $modelmail_array, $infos['mail_model_for_send_invoice'], 1, 0, 0, '', 0, 0, 0, '', 'minwidth100');
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
					<td><?php print $form->select_company($infos['supplier_id'], 'supplier_id_' . $key, 's.fournisseur=1 AND status=1', 'SelectThirdParty', 0, 0, null, 0, 'minwidth300') ?></td>
					<td><?php $form->select_produits($infos['product_id_for_fee'], 'product_id_for_fee_' . $key, '', $conf->product->limit_size, 0, -1, 2, '', 0, array(), 0, '1', 0, 'maxwidth300') ?></td>
					<td><input type="checkbox" id="<?php print 'create_supplier_invoice_payment_' . $key ?>"
							   name="<?php print 'create_supplier_invoice_payment_' . $key ?>"
							   value="1" <?php print !empty($infos['create_supplier_invoice_payment']) ? ' checked' : '' ?>>
					</td>
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
	<a class="butAction" href='javascript:eCommerceConfirmWoocommerceUpdateAttributes("site_form_detail", "<?php print $langs->trans('ECommerceWoocommerceConfirmUpdateDictAttributes') ?>")'><?php print $langs->trans('ECommerceWoocommerceUpdateDictAttributes') ?></a>
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
