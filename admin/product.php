<?php
/* Copyright (C) 2022      Open-DSI             <support@open-dsi.fr>
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
 */

/**
 *	    \file       htdocs/ecommerceng/admin/setup.php
 *		\ingroup    ecommerceng
 *		\brief      Page to setup ecommerceng module
 */

// Change this following line to use the correct relative path (../, ../../, etc)
$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include '../../main.inc.php';			// to work if your module directory is into a subdir of root htdocs directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include '../../../main.inc.php';		// to work if your module directory is into a subdir of root htdocs directory
if (! $res) die("Include of main fails");
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formaccounting.class.php';
require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/html.formproduct.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
dol_include_once('/ecommerceng/lib/eCommerce.lib.php');
dol_include_once('/ecommerceng/admin/class/data/eCommerceDict.class.php');

$langs->loadLangs(array("admin", "companies", "bills", "accountancy", "banks", "oauth", "ecommerce@ecommerceng", "woocommerce@ecommerceng"));

if (!$user->admin && !$user->rights->ecommerceng->site) accessforbidden();

$id = GETPOST('id', 'int');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'aZ09');

include dol_buildpath('/ecommerceng/admin/actions_selectsite.inc.php');

$object = new eCommerceSite($db);
if (!($id > 0)) {
	$sites = $object->listSites();
	if (!empty($sites)) {
		$id = array_values($sites)[0]['id'];
	}
	$action = '';
}
if ($id > 0) {
	$result = $object->fetch($id);
	if ($result < 0) {
		accessforbidden($object->errorsToString());
	} elseif ($result == 0) {
		$langs->load('errors');
		accessforbidden($langs->trans('ErrorRecordNotFound'));
	}
} else {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}

if (empty($conf->product->enabled)) {
	accessforbidden($langs->trans('ModuleDisabled'));
}
if (empty($conf->categorie->enabled)) {
	accessforbidden($langs->trans('ModuleDisabled') . ' : ' . $langs->trans('Categories'));
}

$product_static = new Product($db);
$category_static = new Categorie($db);
$extrafields = new ExtraFields($db);
$extrafields_product_labels = $extrafields->fetch_name_optionals_label($product_static->table_element);
$extrafields_product_labels_clean = array();
foreach ($extrafields_product_labels as $key => $label) {
	if (preg_match('/^ecommerceng_/', $key)) continue;
	$extrafields_product_labels_clean[$key] = $label;
}
$extrafields_category_labels = $extrafields->fetch_name_optionals_label($category_static->table_element);
$extrafields_category_labels_clean = array();
foreach ($extrafields_category_labels as $key => $label) {
	if (preg_match('/^ecommerceng_/', $key)) continue;
	$extrafields_category_labels_clean[$key] = $label;
}

// Get all attributes in dictionary for this entity and site
$eCommerceDict = new eCommerceDict($db, MAIN_DB_PREFIX . 'c_ecommerceng_attribute');
$dict_attributes = $eCommerceDict->search([ 'entity'=> ['value' => $conf->entity], 'site_id' => ['value' => $object->id] ]);
$remote_attributes = array();
foreach ($dict_attributes as $attribute) {
	$remote_attributes[$attribute['attribute_id']] = $attribute['attribute_name'];
}

$extra_fields_list = array(
	$product_static->table_element => array('label' => 'Product', 'extra_fields' => $extrafields_product_labels_clean, 'default'=> true, 'metadata'=> true, 'attributes'=> $remote_attributes),
	$category_static->table_element => array('label' => 'Category', 'extra_fields' => $extrafields_category_labels_clean, 'default'=> true, 'metadata'=> false, 'attributes'=> []),
);

$list_account = array();
$list_account[] = '---Product---';
$list_account[] = 'accounting_product_sold_account';
if ($mysoc->isInEEC()) {
	$list_account[] = 'accounting_product_sold_intra_account';
}
$list_account[] = 'accounting_product_sold_export_account';
$list_account[] = 'accounting_product_buy_account';
if ($mysoc->isInEEC()) {
	$list_account[] = 'accounting_product_buy_intra_account';
}
$list_account[] = 'accounting_product_buy_export_account';
$list_account[] = '---Service---';
$list_account[] = 'accounting_service_sold_account';
if ($mysoc->isInEEC()) {
	$list_account[] = 'accounting_service_sold_intra_account';
}
$list_account[] = 'accounting_service_sold_export_account';
$list_account[] = 'accounting_service_buy_account';
if ($mysoc->isInEEC()) {
	$list_account[] = 'accounting_service_buy_intra_account';
}
$list_account[] = 'accounting_service_buy_export_account';


/*
 *	Actions
 */
$error = 0;

include dol_buildpath('/ecommerceng/admin/actions_extrafields.inc.php');

if ($action == 'set_options') {
	$object->oldcopy = clone $object;

	$object->fk_cat_product = GETPOST('fk_cat_product', 'int');
	$object->fk_cat_product = $object->fk_cat_product > 0 ? $object->fk_cat_product : 0;
	$object->parameters['realtime_dtoe']['product'] = GETPOST('realtime_dtoe_product', 'int') ? 1 : 0;
	$object->ecommerce_price_type = GETPOST('base_price_type', 'az09');
	$object->parameters['shipping_service'] = GETPOST('shipping_service', 'int');
	$object->parameters['shipping_service'] = $object->parameters['shipping_service'] > 0 ? $object->parameters['shipping_service'] : 0;
	$object->parameters['discount_code_service'] = GETPOST('discount_code_service', 'int');
	$object->parameters['discount_code_service'] = $object->parameters['discount_code_service'] > 0 ? $object->parameters['discount_code_service'] : 0;
	$object->parameters['pw_gift_cards_service'] = GETPOST('pw_gift_cards_service', 'int');
	$object->parameters['pw_gift_cards_service'] = $object->parameters['pw_gift_cards_service'] > 0 ? $object->parameters['pw_gift_cards_service'] : 0;
	$object->parameters['product_synch_price'] = GETPOST('product_synch_price', 'az09');
	$object->parameters['product_weight_units'] = empty($conf->global->PRODUCT_DISABLE_WEIGHT) ? GETPOST('product_weight_units', 'int') : 0; // 0 = Kg
	$object->parameters['product_dimension_units'] = empty($conf->global->PRODUCT_DISABLE_SIZE) ? GETPOST('product_dimension_units', 'int') : 2; // 2 = cm
	$object->parameters['product_variation_mode'] = GETPOST('product_variation_mode', 'az09');
    $object->parameters['enable_product_plugin_wpml_support'] = GETPOST('enable_product_plugin_wpml_support', 'int') ? 1 : 0;

	if(empty($object->fk_cat_product)) {
		setEventMessage($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("ECommerceCatProduct")), 'errors');
		$error++;
	}

	if (!$error) {
		$result = $object->update($user);

		if ($result < 0) {
			setEventMessages($object->error, $object->errors, 'errors');
		} else {
			$object->price_level = GETPOST('price_level', 'int');

			if (!empty($conf->global->PRODUIT_MULTIPRICES) && !empty($object->parameters['realtime_dtoe']['product']) &&
				$object->price_level > 0 && $object->price_level <= intval($conf->global->PRODUIT_MULTIPRICES_LIMIT) &&
				$object->oldcopy->price_level != $object->price_level
			) {
				$action = 'update_level_price';
			} else {
				setEventMessage($langs->trans("SetupSaved"));
				header("Location: " . $_SERVER["PHP_SELF"] . '?id=' . $object->id);
				exit;
			}
		}
	}
} elseif ($action == 'set_synchronize_sens_options') {
	$object->oldcopy = clone $object;

	$object->parameters['product_synch_direction']['ref'] = GETPOST('product_synch_direction_ref', 'az09');
	$object->parameters['product_synch_direction']['description'] = GETPOST('product_synch_direction_description', 'az09');
	$object->parameters['product_synch_direction']['short_description'] = GETPOST('product_synch_direction_short_description', 'az09');
	$object->parameters['product_synch_direction']['weight'] = empty($conf->global->PRODUCT_DISABLE_WEIGHT) ? GETPOST('product_synch_direction_weight', 'az09') : 'etod';
	$object->parameters['product_synch_direction']['dimension'] = empty($conf->global->PRODUCT_DISABLE_SIZE) ? GETPOST('product_synch_direction_dimension', 'az09') : 'etod';
	$object->parameters['product_synch_direction']['tax'] = GETPOST('product_synch_direction_tax', 'az09');
	$object->parameters['product_synch_direction']['status'] = GETPOST('product_synch_direction_status', 'az09');
	$object->parameters['product_synch_direction']['image'] = GETPOST('product_synch_direction_image', 'az09');

	$result = $object->update($user);

	if ($result < 0) {
		setEventMessages($object->error, $object->errors, 'errors');
	} else {
		setEventMessage($langs->trans("SetupSaved"));
		header("Location: " . $_SERVER["PHP_SELF"] . '?id=' . $object->id);
		exit;
	}
} elseif ($action == 'set_accounting_codes_options' && !empty($conf->accounting->enabled)) {
	$object->oldcopy = clone $object;

	$values = array();
	foreach ($list_account as $key) {
		if (!preg_match('/---(.*)---/', $key, $reg)) {
			$values[$key] = GETPOST($key, 'alphanohtml');
		}
	}
	$object->parameters['default_account'] = $values;

	$result = $object->update($user);

	if ($result < 0) {
		setEventMessages($object->error, $object->errors, 'errors');
	} else {
		setEventMessage($langs->trans("SetupSaved"));
		header("Location: " . $_SERVER["PHP_SELF"] . '?id=' . $object->id);
		exit;
	}
} elseif ($action == 'confirm_update_price_level' && $confirm == "yes" && !empty($conf->global->PRODUIT_MULTIPRICES) && !empty($object->parameters['realtime_dtoe']['product'])) {
	$object->oldcopy = clone $object;

	$object->price_level = GETPOST('price_level', 'int');

	if ($object->price_level > 0 && $object->price_level <= intval($conf->global->PRODUIT_MULTIPRICES_LIMIT) && $object->oldcopy->price_level != $object->price_level) {
		$result = $object->update($user);

		if ($result < 0) {
			setEventMessages($object->error, $object->errors, 'errors');
		} else {
			updatePriceLevel($object);

			setEventMessages($langs->trans('SetupSaved'), null);
			header("Location: " . $_SERVER["PHP_SELF"] . '?id=' . $object->id);
			exit;
		}
	}
} elseif ($action == 'confirm_synchronize_attributes' && $confirm == "yes") {
	$result = ecommerceng_update_woocommerce_attribute($db, $object);
	if ($result) setEventMessage($langs->trans('ECommercengWoocommerceDictAttributesUpdated'), 'mesgs');

	header("Location: " . $_SERVER["PHP_SELF"] . '?id=' . $object->id);
	exit;
} elseif ($action == 'confirm_synchronize_taxes' && $confirm == "yes") {
	$result = ecommerceng_update_woocommerce_dict_tax($db, $object);
	if ($result) setEventMessage($langs->trans('ECommercengWoocommerceDictTaxClassUpdated'), 'mesgs');

	header("Location: " . $_SERVER["PHP_SELF"] . '?id=' . $object->id);
	exit;
}


/*
 *	View
 */

$form = new Form($db);
$formproduct = new FormProduct($db);
$formaccounting = new FormAccounting($db);

$v10p = version_compare(DOL_VERSION, "10.0.0") >= 0;
$wikihelp='';
llxHeader('', $langs->trans("ECommerceSetup"), $wikihelp, '', 0, 0, array(
	'/ecommerceng/js/form.js',
));

$formconfirm = '';

if ($action == 'synchronize_attributes') {
	$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('ECommerceWoocommerceUpdateDictAttributes'), $langs->trans('ECommerceWoocommerceConfirmUpdateDictAttributes'), 'confirm_synchronize_attributes', '', 0, 1, 200, 800);
} elseif ($action == 'synchronize_taxes') {
	$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('ECommerceWoocommerceUpdateDictTaxClasses'), $langs->trans('ECommerceWoocommerceConfirmUpdateDictTaxClasses'), 'confirm_synchronize_taxes', '', 0, 1, 200, 800);
} elseif ($action == 'update_level_price' && !empty($conf->global->PRODUIT_MULTIPRICES)) {
	$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id.'&price_level='.$object->price_level, $langs->trans('ECommerceUpdateLevelPrice'), $langs->trans('ECommerceConfirmUpdatePriceLevel'), 'confirm_update_level_price', '', 0, 1, 200, 800);
}

// Call Hook formConfirm
$parameters = array('formConfirm' => $formconfirm);
$reshook = $hookmanager->executeHooks('formConfirm', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
if (empty($reshook)) {
	$formconfirm .= $hookmanager->resPrint;
} elseif ($reshook > 0) {
	$formconfirm = $hookmanager->resPrint;
}

// Print form confirm
print $formconfirm;

$linkback='<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans("ECommerceSetup"),$linkback,'title_setup');

include dol_buildpath('/ecommerceng/admin/tpl/selectsite.tpl.php');

$head=ecommercengConfigSitePrepareHead($object);

dol_fiche_head($head, 'product', $langs->trans("Module107100Name"), 0, 'eCommerce@ecommerceng');

print '<div class="tabsAction tabsActionNoBottom">';
print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=synchronize_attributes&token='.ecommercengNewToken().'">'.$langs->trans('ECommerceWoocommerceUpdateDictAttributes').'</a>';
print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=synchronize_taxes&token='.ecommercengNewToken().'">'.$langs->trans('ECommerceWoocommerceUpdateDictTaxClasses').'</a>';
print '</div>';

/**
 * Settings.
 */

print '<div id="options"></div>';
print load_fiche_titre($langs->trans("Parameters"), '', '');

print '<form method="post" action="'.$_SERVER["PHP_SELF"] . '?id=' . $object->id . '#options">';
print '<input type="hidden" name="token" value="'.ecommercengNewToken().'">';
print '<input type="hidden" name="action" value="set_options">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td class="20p">'.$langs->trans("Parameters").'</td>'."\n";
print '<td>'.$langs->trans("Description").'</td>'."\n";
print '<td class="right">'.$langs->trans("Value").'</td>'."\n";
print "</tr>\n";

// Root category
print '<tr class="oddeven">' . "\n";
print '<td class="fieldrequired">'.$langs->trans("ECommerceCatProduct").'</td>'."\n";
print '<td>'.$langs->transnoentities("ECommerceCatProductDescription").'</td>'."\n";
print '<td class="right">' . "\n";
$categories = $category_static->get_full_arbo(Categorie::TYPE_PRODUCT);
$categories_list = array();
foreach ($categories as $category) {
	$categories_list[$category['id']] = $category['label'];
}
print $form->selectarray('fk_cat_product', $categories_list, $object->fk_cat_product, 1, 0, 0, '', 1, 0, 0, '', 'minwidth200 centpercent') . "\n";
print '</td></tr>' . "\n";

// Synchronize product real time from dolibarr to site
print '<tr class="oddeven">' . "\n";
print '<td>'.$langs->trans("ECommerceRealTimeSynchroDolibarrToECommerceProduct").'</td>'."\n";
print '<td>'.$langs->transnoentities("ECommerceRealTimeSynchroDolibarrToECommerceProductDescription").'</td>'."\n";
print '<td class="right">' . "\n";
print '<input type="checkbox" name="realtime_dtoe_product" value="1"' . (!empty($object->parameters['realtime_dtoe']['product']) ? ' checked' : '') . ' />' . "\n";
print '</td></tr>' . "\n";

// Base price type
print '<tr class="oddeven">' . "\n";
print '<td>'.$langs->trans("ECommercePriceType").'</td>'."\n";
print '<td>' . $langs->transnoentities("ECommercePriceTypeDescription") . '</td>' . "\n";
print '<td class="right">' . "\n";
$base_price_types = array(
	'HT' => 'ECommercePriceTypeHT',
	'TTC' => 'ECommercePriceTypeTTC',
);
print $form->selectarray('base_price_type', $base_price_types, $object->ecommerce_price_type, 1, 0, 0, '', 1, 0, 0, '', 'minwidth200 centpercent') . "\n";
print '</td></tr>' . "\n";

// Price level
if (!empty($conf->global->PRODUIT_MULTIPRICES)) {
	$price_levels = array();
	for ($i = 1; $i <= $conf->global->PRODUIT_MULTIPRICES_LIMIT; $i++) {
		$key_for_label = 'PRODUIT_MULTIPRICES_LABEL'.$i;
		$price_levels[$i] = !empty($conf->global->$key_for_label) ? $langs->trans($conf->global->$key_for_label) : $i;
	}

	print '<tr class="oddeven">' . "\n";
	print '<td>' . $langs->trans("ECommercePriceLevel") . '</td>' . "\n";
	print '<td>' . $langs->transnoentities("ECommercePriceLevelDescription") . '</td>' . "\n";
	print '<td class="right">' . "\n";
	print $form->selectarray('price_level', $price_levels, $object->price_level, 1, 0, 0, '', 1, 0, 0, '', 'minwidth200 centpercent') . "\n";
	print '</td></tr>' . "\n";
}

// Shipping service
print '<tr class="oddeven">' . "\n";
print '<td>'.$langs->trans("ECommerceShippingService").'</td>'."\n";
print '<td>' . $langs->transnoentities("ECommerceShippingServiceDescription") . '</td>' . "\n";
print '<td class="right">' . "\n";
print $form->select_produits($object->parameters['shipping_service'], 'shipping_service', 1, 0) . "\n";
print '</td></tr>' . "\n";

// Discount code service
print '<tr class="oddeven">' . "\n";
print '<td>'.$langs->trans("ECommerceDiscountCodeService").'</td>'."\n";
print '<td>' . $langs->transnoentities("ECommerceDiscountCodeServiceDescription") . '</td>' . "\n";
print '<td class="right">' . "\n";
print $form->select_produits($object->parameters['discount_code_service'], 'discount_code_service', 1, 0) . "\n";
print '</td></tr>' . "\n";

// PW gift cards service
print '<tr class="oddeven">' . "\n";
print '<td>'.$langs->trans("ECommercePwGiftCardsService").'</td>'."\n";
print '<td>' . $langs->transnoentities("ECommercePwGiftCardsServiceDescription") . '</td>' . "\n";
print '<td class="right">' . "\n";
print $form->select_produits($object->parameters['pw_gift_cards_service'], 'pw_gift_cards_service', 1, 0) . "\n";
print '</td></tr>' . "\n";

// Synchronize prices
print '<tr class="oddeven">' . "\n";
print '<td>'.$langs->trans("ECommerceWoocommerceProductSyncPrice").'</td>'."\n";
print '<td>' . $langs->transnoentities("ECommerceWoocommerceProductSyncPriceDescription") . '</td>' . "\n";
print '<td class="right">' . "\n";
$sync_prices_array = array(
//	'selling'=>$langs->trans('ECommerceWoocommerceSellingPrice'),
	'regular'=>$langs->trans('ECommerceWoocommerceRegularPrice'),
);
print $form->selectarray('product_synch_price', $sync_prices_array, $object->parameters['product_synch_price'], 0, 0, 0, '', 0, 0, 0, '', 'minwidth200 centpercent') . "\n";
print '</td></tr>' . "\n";

if (empty($conf->global->PRODUCT_DISABLE_WEIGHT)) {
	// Weight units
	print '<tr class="oddeven">' . "\n";
	print '<td>' . $langs->trans("ECommerceProductWeightUnits") . '</td>' . "\n";
	print '<td>' . $langs->transnoentities("ECommerceProductWeightUnitsDescription") . '</td>' . "\n";
	print '<td class="right">' . "\n";
	$value = isset($object->parameters['product_weight_units']) ? $object->parameters['product_weight_units'] : ''; // 0 = kg
	if ($v10p) {
		print $formproduct->selectMeasuringUnits("product_weight_units", "weight", $value, 0, 2);
	} else {
		print $formproduct->select_measuring_units("product_weight_units", "weight", $value);
	}
	print '</td></tr>' . "\n";
}

if (empty($conf->global->PRODUCT_DISABLE_SIZE)) {
	// Dimension units
	print '<tr class="oddeven">' . "\n";
	print '<td>' . $langs->trans("ECommerceProductDimensionUnits") . '</td>' . "\n";
	print '<td>' . $langs->transnoentities("ECommerceProductDimensionUnitsDescription") . '</td>' . "\n";
	print '<td class="right">' . "\n";
	$value = isset($object->parameters['product_dimension_units']) ? $object->parameters['product_dimension_units'] : '';
	if ($v10p) {
		print $formproduct->selectMeasuringUnits("product_dimension_units", "size", $value, 0, 2);
	} else {
		print $formproduct->select_measuring_units("product_dimension_units", "size", $value);
	}
	print '</td></tr>' . "\n";
}

// Product variation mode
print '<tr class="oddeven">' . "\n";
print '<td>'.$langs->trans("ECommerceWoocommerceProductVariationMode").'</td>'."\n";
print '<td>' . $langs->transnoentities("ECommerceWoocommerceProductVariationModeDescription") . '</td>' . "\n";
print '<td class="right">' . "\n";
$variation_modes = array(
	'one_to_one'=>$langs->trans('ECommerceWoocommerceProductVariationOneToOne'),
	'all_to_one'=>$langs->trans('ECommerceWoocommerceProductVariationAllToOne'),
);
$value = isset($object->parameters['product_variation_mode']) ? $object->parameters['product_variation_mode'] : 'one_to_one';
print $form->selectarray('product_variation_mode', $variation_modes, $value, 0, 0, 0, '', 0, 0, 0, '', 'minwidth200 centpercent') . "\n";
print '</td></tr>' . "\n";

// Support of WooCommerce plugin : WPML for WooCommerce
print '<tr class="oddeven">' . "\n";
print '<td>' . $langs->trans("ECommerceWoocommerceEnableProductWpmlPluginSupport") . '</td>' . "\n";
print '<td>' . $langs->transnoentities("ECommerceWoocommerceEnableProductWpmlPluginSupportDescription") . '</td>' . "\n";
print '<td class="right">' . "\n";
print '<input type="checkbox" name="enable_product_plugin_wpml_support" value="1"' . (!empty($object->parameters['enable_product_plugin_wpml_support']) ? ' checked' : '') . ' />' . "\n";
print '</td></tr>' . "\n";

print '</table>'."\n";

print '<br>';
print '<div align="center">';
print '<input type="submit" class="button" value="' . $langs->trans('Modify') . '" />';
print '</div>';

print '</form>';

/**
 * Synchronize sens settings.
 */

print '<div id="synchronize_sens_options"></div>';
print load_fiche_titre($langs->trans("ProductsSyncSetup"), '', '');

print '<form method="post" action="'.$_SERVER["PHP_SELF"] . '?id=' . $object->id . '#synchronize_sens_options">';
print '<input type="hidden" name="token" value="'.ecommercengNewToken().'">';
print '<input type="hidden" name="action" value="set_synchronize_sens_options">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td class="20p">'.$langs->trans("Parameters").'</td>'."\n";
print '<td>'.$langs->trans("Description").'</td>'."\n";
print '<td class="right">'.$langs->trans("Value").'</td>'."\n";
print "</tr>\n";

$sync_direction_array = array(
	'' => $langs->trans('None'),
	'etod' => $langs->trans('ECommerceToDolibarr'),
	'dtoe' => $langs->trans('DolibarrToECommerce'),
	'all' => $langs->trans('AllDirection'),
);

// Synchronize ref sens
print '<tr class="oddeven">' . "\n";
print '<td>'.$langs->trans("ECommerceProductRefSyncDirection").'</td>'."\n";
print '<td>' . $langs->transnoentities("ECommerceProductRefSyncDirectionDescription") . '</td>' . "\n";
print '<td class="right">' . "\n";
$value = !empty($object->parameters['product_synch_direction']['ref']) ? $object->parameters['product_synch_direction']['ref'] : '';
print $form->selectarray('product_synch_direction_ref', $sync_direction_array, $value, 0, 0, 0, '', 0, 0, 0, '', 'minwidth300') . "\n";
print '</td></tr>' . "\n";

// Synchronize description sens
print '<tr class="oddeven">' . "\n";
print '<td>'.$langs->trans("ECommerceProductDescriptionSyncDirection").'</td>'."\n";
print '<td>' . $langs->transnoentities("ECommerceProductDescriptionSyncDirectionDescription") . '</td>' . "\n";
print '<td class="right">' . "\n";
$value = !empty($object->parameters['product_synch_direction']['description']) ? $object->parameters['product_synch_direction']['description'] : '';
print $form->selectarray('product_synch_direction_description', $sync_direction_array, $value, 0, 0, 0, '', 0, 0, 0, '', 'minwidth300') . "\n";
print '</td></tr>' . "\n";

// Synchronize short description sens
print '<tr class="oddeven">' . "\n";
print '<td>'.$langs->trans("ECommerceProductShortDescriptionSyncDirection").'</td>'."\n";
print '<td>' . $langs->transnoentities("ECommerceProductShortDescriptionSyncDirectionDescription") . '</td>' . "\n";
print '<td class="right">' . "\n";
$value = !empty($object->parameters['product_synch_direction']['short_description']) ? $object->parameters['product_synch_direction']['short_description'] : '';
print $form->selectarray('product_synch_direction_short_description', $sync_direction_array, $value, 0, 0, 0, '', 0, 0, 0, '', 'minwidth300') . "\n";
print '</td></tr>' . "\n";

if (empty($conf->global->PRODUCT_DISABLE_WEIGHT)) {
	// Synchronize weight sens
	print '<tr class="oddeven">' . "\n";
	print '<td>' . $langs->trans("ECommerceProductWeightSyncDirection") . '</td>' . "\n";
	print '<td>' . $langs->transnoentities("ECommerceProductWeightSyncDirectionDescription") . '</td>' . "\n";
	print '<td class="right">' . "\n";
	$value = !empty($object->parameters['product_synch_direction']['weight']) ? $object->parameters['product_synch_direction']['weight'] : '';
	print $form->selectarray('product_synch_direction_weight', $sync_direction_array, $value, 0, 0, 0, '', 0, 0, 0, '', 'minwidth300') . "\n";
	print '</td></tr>' . "\n";
}

if (empty($conf->global->PRODUCT_DISABLE_SIZE)) {
	// Synchronize dimension sens
	print '<tr class="oddeven">' . "\n";
	print '<td>' . $langs->trans("ECommerceProductDimensionSyncDirection") . '</td>' . "\n";
	print '<td>' . $langs->transnoentities("ECommerceProductDimensionSyncDirectionDescription") . '</td>' . "\n";
	print '<td class="right">' . "\n";
	$value = !empty($object->parameters['product_synch_direction']['dimension']) ? $object->parameters['product_synch_direction']['dimension'] : '';
	print $form->selectarray('product_synch_direction_dimension', $sync_direction_array, $value, 0, 0, 0, '', 0, 0, 0, '', 'minwidth300') . "\n";
	print '</td></tr>' . "\n";
}

// Synchronize tax sens
print '<tr class="oddeven">' . "\n";
print '<td>'.$langs->trans("ECommerceProductTaxSyncDirection").'</td>'."\n";
print '<td>' . $langs->transnoentities("ECommerceProductTaxSyncDirectionDescription") . '</td>' . "\n";
print '<td class="right">' . "\n";
$value = !empty($object->parameters['product_synch_direction']['tax']) ? $object->parameters['product_synch_direction']['tax'] : '';
print $form->selectarray('product_synch_direction_tax', $sync_direction_array, $value, 0, 0, 0, '', 0, 0, 0, '', 'minwidth300') . "\n";
print '</td></tr>' . "\n";

// Synchronize status sens
print '<tr class="oddeven">' . "\n";
print '<td>'.$langs->trans("ECommerceProductStatusSyncDirection").'</td>'."\n";
print '<td>' . $langs->transnoentities("ECommerceProductStatusSyncDirectionDescription") . '</td>' . "\n";
print '<td class="right">' . "\n";
$value = !empty($object->parameters['product_synch_direction']['status']) ? $object->parameters['product_synch_direction']['status'] : '';
print $form->selectarray('product_synch_direction_status', $sync_direction_array, $value, 0, 0, 0, '', 0, 0, 0, '', 'minwidth300') . "\n";
print '</td></tr>' . "\n";

// Synchronize image sens
print '<tr class="oddeven">' . "\n";
print '<td>'.$langs->trans("ECommerceProductImageSyncDirection").'</td>'."\n";
print '<td>' . $langs->transnoentities("ECommerceProductImageSyncDirectionDescription") . '</td>' . "\n";
print '<td class="right">' . "\n";
$value = !empty($object->parameters['product_synch_direction']['image']) ? $object->parameters['product_synch_direction']['image'] : '';
print $form->selectarray('product_synch_direction_image', $sync_direction_array, $value, 0, 0, 0, '', 0, 0, 0, '', 'minwidth300') . "\n";
print '</td></tr>' . "\n";

print '</table>'."\n";

print '<br>';
print '<div align="center">';
print '<input type="submit" class="button" value="' . $langs->trans('Modify') . '" />';
print '</div>';

print '</form>';

/**
 * Extra fields.
 */
include dol_buildpath('/ecommerceng/admin/tpl/extrafields.tpl.php');

if (!empty($conf->accounting->enabled)) {
	/**
	 * Accounting code settings.
	 */

	print '<div id="accounting_codes_options"></div>';
	print load_fiche_titre($langs->trans("MenuDefaultAccounts"), '', '');

	print '<form method="post" action="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '#accounting_codes_options">';
	print '<input type="hidden" name="token" value="' . ecommercengNewToken() . '">';
	print '<input type="hidden" name="action" value="set_accounting_codes_options">';

	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td class="20p">' . $langs->trans("Parameters") . '</td>' . "\n";
	print '<td>' . $langs->trans("Description") . '</td>' . "\n";
	print '<td class="right">' . $langs->trans("Value") . '</td>' . "\n";
	print "</tr>\n";

	foreach ($list_account as $key) {
		if (preg_match('/---(.*)---/', $key, $matches)) {
			print '<tr class="liste_titre"><td colspan="3">' . $langs->trans($matches[1]) . '</td></tr>';
		} else {
			$const_name = strtoupper($key);
			print '<tr class="oddeven">' . "\n";
			print '<td>'.$langs->trans('ECOMMERCE_' . $const_name).'</td>'."\n";
			print '<td>' . $langs->transnoentities('DefaultValue') . ' : ' . (empty($conf->global->$const_name) || $conf->global->$const_name == -1 ? $langs->trans('NotDefined') : $conf->global->$const_name) . '</td>' . "\n";
			print '<td class="right">' . "\n";
			print $formaccounting->select_account($object->parameters['default_account'][$key], $key, 1, '', 1, 1);
			print '</td></tr>' . "\n";
		}
	}

	print '</table>' . "\n";

	print '<br>';
	print '<div align="center">';
	print '<input type="submit" class="button" value="' . $langs->trans('Modify') . '" />';
	print '</div>';

	print '</form>';
}

print dol_get_fiche_end();

llxFooter();

$db->close();
