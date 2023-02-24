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
require_once DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php';
require_once(DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php');
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formmail.class.php';
dol_include_once('/ecommerceng/lib/eCommerce.lib.php');
dol_include_once('/ecommerceng/admin/class/data/eCommerceDict.class.php');
dol_include_once('/ecommerceng/class/data/eCommercePaymentGateways.class.php');

$langs->loadLangs(array("admin", "orders", "companies", "bills", "accountancy", "banks", "oauth", "ecommerce@ecommerceng", "woocommerce@ecommerceng"));

if (!$user->admin && !$user->rights->ecommerceng->site) accessforbidden();

$id = GETPOST('id', 'int');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'aZ09');

include dol_buildpath('/ecommerceng/admin/actions_selectsite.inc.php');

$object = new eCommerceSite($db);
if (!($id > 0)) {
	$sites = $object->listSites();
	$id = array_values($sites)[0]['id'];
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

if (empty($conf->facture->enabled) && empty($conf->facture->enabled)) {
	accessforbidden($langs->trans('ModuleDisabled'));
}

$order_static = new Commande($db);
$order_lines_static = new OrderLine($db);
$extrafields = new ExtraFields($db);
$extrafields_order_labels = $extrafields->fetch_name_optionals_label($order_static->table_element);
$extrafields_order_labels_clean = array();
foreach ($extrafields_order_labels as $key => $label) {
	if (preg_match('/^ecommerceng_/', $key)) continue;
	$extrafields_order_labels_clean[$key] = $label;
}
if (isset($extrafields->attributes['commande']['param']["ecommerceng_wc_status_{$object->id}_{$conf->entity}"]['options'])) {
	$status_extra_fields_options = $extrafields->attributes['commande']['param']["ecommerceng_wc_status_{$object->id}_{$conf->entity}"]['options'];
} elseif (isset($extrafields->attribute_param["ecommerceng_wc_status_{$object->id}_{$conf->entity}"]['options'])) {
	$status_extra_fields_options = $extrafields->attribute_param["ecommerceng_wc_status_{$object->id}_{$conf->entity}"]['options'];
} else {
	$status_extra_fields_options = array();
}
$extrafields_order_lines_labels = $extrafields->fetch_name_optionals_label($order_lines_static->table_element);
$extrafields_order_lines_labels_clean = array();
foreach ($extrafields_order_lines_labels as $key => $label) {
	if (preg_match('/^ecommerceng_/', $key)) continue;
	$extrafields_order_lines_labels_clean[$key] = $label;
}

$extrafields_list = array(
	$order_static->table_element => array('label' => 'Order', 'extrafields' => $extrafields_order_labels_clean),
	$order_lines_static->table_element => array('label' => 'OrderLine', 'extrafields' => $extrafields_order_lines_labels_clean),
);

$order_dtoe_status = array(
	Commande::STATUS_CANCELED			=> $langs->trans('StatusOrderCanceled'),
	Commande::STATUS_DRAFT				=> $langs->trans('StatusOrderDraft'),
	Commande::STATUS_VALIDATED			=> $langs->trans('StatusOrderValidated'),
	Commande::STATUS_SHIPMENTONPROCESS	=> $langs->trans('StatusOrderSent'),
	Commande::STATUS_CLOSED				=> $langs->trans('StatusOrderProcessed'),
);

$eCommercePaymentGateways = new eCommercePaymentGateways($db);
$payment_gateways = $eCommercePaymentGateways->get_all($object->id);
if (!is_array($payment_gateways)) {
	setEventMessages($eCommercePaymentGateways->error, $eCommercePaymentGateways->errors, 'errors');
}

/*
 *	Actions
 */

if ($action == 'set_options') {
	$object->oldcopy = clone $object;

	$object->parameters['order_actions']['create_order'] = GETPOST('create_order', 'int') ? 1 : 0;
	$object->parameters['realtime_dtoe']['order'] = GETPOST('realtime_dtoe_order', 'int') ? 1 : 0;
	$object->parameters['order_actions']['create_invoice'] = GETPOST('create_invoice', 'int') ? 1 : 0;
	$object->parameters['order_actions']['create_invoice_type'] = GETPOST('create_invoice_type', 'int');
	$object->parameters['order_actions']['create_invoice_deposit_type'] = GETPOST('create_invoice_deposit_type', 'az09');
	$object->parameters['order_actions']['create_invoice_deposit_value'] = GETPOST('create_invoice_deposit_value', 'int');
	$object->parameters['order_actions']['create_invoice_if_amount_0'] = GETPOST('create_invoice_if_amount_0', 'int') ? 1 : 0;
	$object->parameters['order_actions']['send_invoice_by_mail'] = GETPOST('send_invoice_by_mail', 'int') ? 1 : 0;
	$object->parameters['order_actions']['create_supplier_invoice'] = GETPOST('create_supplier_invoice', 'int') ? 1 : 0;
	$object->parameters['default_sales_representative_follow'] = GETPOST('default_sales_representative_follow', 'int');
	$object->parameters['order_first_date_etod'] = GETPOST('order_first_date_etod', 'alphanohtml') ? dol_mktime(0, 0, 0, GETPOST('order_first_date_etodmonth', 'int'), GETPOST('order_first_date_etodday', 'int'), GETPOST('order_first_date_etodyear', 'int')) : null;
	$object->parameters['order_actions']['fee_line_as_item_line'] = GETPOST('fee_line_as_item_line', 'int') ? 1 : 0;
	$object->parameters['order_metadata_product_lines_to_description_etod'] = GETPOST('order_metadata_product_lines_to_description_etod', 'int') ? 1 : 0;
	$object->parameters['order_filter_mode_metadata_product_lines_to_description_etod'] = GETPOST('order_filter_mode_metadata_product_lines_to_description_etod', 'az09');
	$object->parameters['order_filter_keys_metadata_product_lines_to_description_etod'] = GETPOST('order_filter_keys_metadata_product_lines_to_description_etod', 'alphanohtml');

	$result = $object->update($user);

	if ($result < 0) {
		setEventMessages($object->error, $object->errors, 'errors');
	} else {
		setEventMessage($langs->trans("SetupSaved"));
		header("Location: " . $_SERVER["PHP_SELF"] . '?id=' . $object->id);
		exit;
	}
} elseif ($action == 'set_status_options') {
	$object->oldcopy = clone $object;

	$object->parameters['order_status_dtoe_check_lvl_status'] = GETPOST('order_status_dtoe_check_lvl_status', 'int') ? 1 : 0;

	$values = array();
	foreach ($order_dtoe_status as $d_status => $d_label) {
		$values[$d_status]['selected'] = GETPOST('order_status_dtoe_' . $d_status, 'alphanohtml');
	}
	$object->parameters['order_status_dtoe'] = $values;

	$values = array();
	foreach ($status_extra_fields_options as $key => $value) {
		if (($pos = strpos($key, '_')) > 0) $key = substr($key, $pos + 1);

		$values[$key]['selected'] = GETPOST('order_status_etod_' . $key, 'alphanohtml');
		$values[$key]['billed'] = GETPOST('order_status_etod_billed_' . $key, 'int') ? 1 : 0;
		$values[$key]['synchronize'] = GETPOST('order_status_etod_synchronize_' . $key, 'int') ? 1 : 0;
	}
	$object->parameters['order_status_etod'] = $values;

	$result = $object->update($user);

	if ($result < 0) {
		setEventMessages($object->error, $object->errors, 'errors');
	} else {
		setEventMessage($langs->trans("SetupSaved"));
		header("Location: " . $_SERVER["PHP_SELF"] . '?id=' . $object->id);
		exit;
	}
} elseif ($action == 'set_metadata_matching_extrafields_options') {
	$table_element = GETPOST('table_element', 'alphanohtml');
	if (isset($extrafields_list[$table_element])) {
		$object->oldcopy = clone $object;

		$values = array();
		foreach ($extrafields_list[$table_element]['extrafields'] as $key => $label) {
			$activated = GETPOST("act_ef_crp_{$table_element}_{$key}", 'int') ? 1 : 0;
			$correspondences = $activated ? GETPOST("ef_crp_{$table_element}_{$key}", 'alphanohtml') :
				(!empty($object->parameters['ef_crp'][$table_element][$key]['correspondences']) ? $object->parameters['ef_crp'][$table_element][$key]['correspondences'] : $key);

			$values[$key] = [
				'correspondences' => $correspondences,
				'activated' => $activated,
			];
		}
		$object->parameters['ef_crp'][$table_element] = $values;

		$result = $object->update($user);

		if ($result < 0) {
			setEventMessages($object->error, $object->errors, 'errors');
		} else {
			setEventMessage($langs->trans("SetupSaved"));
			header("Location: " . $_SERVER["PHP_SELF"] . '?id=' . $object->id);
			exit;
		}
	} else {
		setEventMessage("Wrong table element", 'errors');
	}
} elseif ($action == 'set_payment_gateways_options') {
	foreach ($payment_gateways as $key => $infos) {
		$payment_gateways[$key]['payment_mode_id'] = GETPOST('payment_mode_id_' . $key, 'int');
		$payment_gateways[$key]['payment_mode_id'] = $payment_gateways[$key]['payment_mode_id'] > 0 ? $payment_gateways[$key]['payment_mode_id'] : 0;
		$payment_gateways[$key]['bank_account_id'] = GETPOST('bank_account_id_' . $key, 'int');
		$payment_gateways[$key]['bank_account_id'] = $payment_gateways[$key]['bank_account_id'] > 0 ? $payment_gateways[$key]['bank_account_id'] : 0;
		$payment_gateways[$key]['create_invoice_payment'] = GETPOST('create_invoice_payment_' . $key, 'int') ? 1 : 0;
		$payment_gateways[$key]['mail_model_for_send_invoice'] = GETPOST('mail_model_for_send_invoice_' . $key, 'int');
		$payment_gateways[$key]['mail_model_for_send_invoice'] = $payment_gateways[$key]['mail_model_for_send_invoice'] > 0 ? $payment_gateways[$key]['mail_model_for_send_invoice'] : 0;
		$payment_gateways[$key]['supplier_id'] = GETPOST('supplier_id_' . $key, 'int');
		$payment_gateways[$key]['supplier_id'] = $payment_gateways[$key]['supplier_id'] > 0 ? $payment_gateways[$key]['supplier_id'] : 0;
		$payment_gateways[$key]['product_id_for_fee'] = GETPOST('product_id_for_fee_' . $key, 'int');
		$payment_gateways[$key]['product_id_for_fee'] = $payment_gateways[$key]['product_id_for_fee'] > 0 ? $payment_gateways[$key]['product_id_for_fee'] : 0;
		$payment_gateways[$key]['create_supplier_invoice_payment'] = GETPOST('create_supplier_invoice_payment_' . $key, 'int') ? 1 : 0;
	}

	$result = $eCommercePaymentGateways->set($object->id, $payment_gateways);

	if ($result < 0) {
		setEventMessages($eCommercePaymentGateways->error, $eCommercePaymentGateways->errors, 'errors');
	} else {
		setEventMessage($langs->trans("SetupSaved"));
		header("Location: " . $_SERVER["PHP_SELF"] . '?id=' . $object->id);
		exit;
	}
} elseif ($action == 'confirm_synchronize_payment_gateways' && $confirm == "yes") {
	$result = ecommerceng_update_payment_gateways($db, $object);
	if ($result) setEventMessage($langs->trans('ECommercePaymentGatewaysUpdated'), 'mesgs');

	header("Location: " . $_SERVER["PHP_SELF"] . '?id=' . $object->id);
	exit;
}


/*
 *	View
 */

$form = new Form($db);
$formmail = new FormMail($db);

$wikihelp='';
llxHeader('', $langs->trans("ECommerceSetup"), $wikihelp, '', 0, 0, array(
	'/ecommerceng/js/form.js',
));

$formconfirm = '';

if ($action == 'synchronize_payment_gateways') {
	$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('ECommerceUpdatePaymentGateways'), $langs->trans('ECommerceConfirmUpdatePaymentGateways'), 'confirm_synchronize_payment_gateways', '', 0, 1, 200, 800);
}

// Call Hook formConfirm
$parameters = array('formConfirm' => $formconfirm, 'lineid' => $lineid);
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

dol_fiche_head($head, 'order_invoice', $langs->trans("Module107100Name"), 0, 'eCommerce@ecommerceng');

print '<div class="tabsAction tabsActionNoBottom">';
print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=synchronize_payment_gateways">'.$langs->trans('ECommerceUpdatePaymentGateways').'</a>';
print '</div>';

/**
 * Settings.
 */

print '<div id="options"></div>';
print load_fiche_titre($langs->trans("Parameters"), '', '');

print '<form method="post" action="'.$_SERVER["PHP_SELF"] . '?id=' . $object->id . '#options">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_options">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td class="20p">'.$langs->trans("Parameters").'</td>'."\n";
print '<td>'.$langs->trans("Description").'</td>'."\n";
print '<td class="right">'.$langs->trans("Value").'</td>'."\n";
print "</tr>\n";

if (!empty($conf->commande->enabled)) {
	// Create order
	print '<tr class="oddeven">' . "\n";
	print '<td>' . $langs->trans("ECommerceCreateOrder") . '</td>' . "\n";
	print '<td>' . $langs->trans("ECommerceCreateOrderDescription") . '</td>' . "\n";
	print '<td class="right">' . "\n";
	print '<input type="checkbox" name="create_order" value="1"' . (!empty($object->parameters['order_actions']['create_order']) ? ' checked' : '') . ' />' . "\n";
	print '</td></tr>' . "\n";

	// Synchronize order real time from dolibarr to site
	print '<tr class="oddeven">' . "\n";
	print '<td>'.$langs->trans("ECommerceRealTimeSynchroDolibarrToECommerceOrder").'</td>'."\n";
	print '<td>'.$langs->trans("ECommerceRealTimeSynchroDolibarrToECommerceOrderDescription").'</td>'."\n";
	print '<td class="right">' . "\n";
	print '<input type="checkbox" name="realtime_dtoe_order" value="1"' . (!empty($object->parameters['realtime_dtoe']['order']) ? ' checked' : '') . ' />' . "\n";
	print '</td></tr>' . "\n";
}

if (!empty($conf->facture->enabled)) {
	// Create invoice
	print '<tr class="oddeven">' . "\n";
	print '<td>' . $langs->trans("ECommerceCreateInvoice") . '</td>' . "\n";
	print '<td>' . $langs->trans("ECommerceCreateInvoiceDescription") . '</td>' . "\n";
	print '<td class="right">' . "\n";
	print '<input type="checkbox" name="create_invoice" value="1"' . (!empty($object->parameters['order_actions']['create_invoice']) ? ' checked' : '') . ' />' . "\n";
	print '</td></tr>' . "\n";
}

if (!empty($object->parameters['order_actions']['create_invoice'])) {
	// Invoice type
	print '<tr class="oddeven">' . "\n";
	print '<td>' . $langs->trans("ECommerceCreateInvoiceType") . '</td>' . "\n";
	print '<td>' . $langs->trans("ECommerceCreateInvoiceTypeDescription") . '</td>' . "\n";
	print '<td class="right">' . "\n";
	$invoice_types = array(
		Facture::TYPE_STANDARD => $langs->trans('InvoiceStandard'),
		Facture::TYPE_DEPOSIT => $langs->trans('InvoiceDeposit')
	);
	print $form->selectarray('create_invoice_type', $invoice_types, $object->parameters['order_actions']['create_invoice_type'], 0, 0, 0, '', 0, 0, 0, '', 'minwidth300');
	print '</td></tr>' . "\n";

	if ($object->parameters['order_actions']['create_invoice_type'] == Facture::TYPE_DEPOSIT) {
		// Invoice deposit type
		print '<tr class="oddeven">' . "\n";
		print '<td>' . $langs->trans("ECommerceSendInvoiceByMail") . '</td>' . "\n";
		print '<td>' . $langs->trans("ECommerceSendInvoiceByMailDescription") . '</td>' . "\n";
		print '<td class="right">' . "\n";
		$invoice_deposit_types = array(
			'amount' => $langs->trans('FixAmount', $langs->transnoentitiesnoconv('Deposit')),
			'variable' => $langs->trans('VarAmountOneLine', $langs->transnoentitiesnoconv('Deposit')),
			'variablealllines' => $langs->trans('VarAmountAllLines')
		);
		print $form->selectarray('create_invoice_deposit_type', $invoice_deposit_types, $object->parameters['order_actions']['create_invoice_deposit_type'], 0, 0, 0, '', 0, 0, 0, '', 'minwidth300');
		print '</td></tr>' . "\n";

		if (in_array($object->parameters['order_actions']['create_invoice_deposit_type'], [ 'variable', 'variablealllines' ])) {
			// Invoice deposit value
			print '<tr class="oddeven">' . "\n";
			print '<td>' . $langs->trans("ECommerceSendInvoiceByMail") . '</td>' . "\n";
			print '<td>' . $langs->trans("ECommerceSendInvoiceByMailDescription") . '</td>' . "\n";
			print '<td class="right">' . "\n";
			print '<input type="number" class="flat centpercent" name="create_invoice_deposit_value" value="' . dol_escape_htmltag($object->parameters['order_actions']['create_invoice_deposit_value']) . '">' . "\n";
			print '</td></tr>' . "\n";
		}
	}

	// Create invoice if amount 0
	print '<tr class="oddeven">' . "\n";
	print '<td>' . $langs->trans("ECommerceCreateInvoiceIfAmount0") . '</td>' . "\n";
	print '<td>' . $langs->trans("ECommerceCreateInvoiceIfAmount0Description") . '</td>' . "\n";
	print '<td class="right">' . "\n";
	print '<input type="checkbox" name="create_invoice_if_amount_0" value="1"' . (!empty($object->parameters['order_actions']['create_invoice_if_amount_0']) ? ' checked' : '') . ' />' . "\n";
	print '</td></tr>' . "\n";

	// Send invoice by mail
	print '<tr class="oddeven">' . "\n";
	print '<td>' . $langs->trans("ECommerceSendInvoiceByMail") . '</td>' . "\n";
	print '<td>' . $langs->trans("ECommerceSendInvoiceByMailDescription") . '</td>' . "\n";
	print '<td class="right">' . "\n";
	print '<input type="checkbox" name="send_invoice_by_mail" value="1"' . (!empty($object->parameters['order_actions']['send_invoice_by_mail']) ? ' checked' : '') . ' />' . "\n";
	print '</td></tr>' . "\n";

	if (!empty($conf->supplier_invoice->enabled)) {
		// Create supplier invoice
		print '<tr class="oddeven">' . "\n";
		print '<td>' . $langs->trans("ECommerceCreateSupplierInvoiceFromFee") . '</td>' . "\n";
		print '<td>' . $langs->trans("ECommerceCreateSupplierInvoiceFromFeeDescription") . '</td>' . "\n";
		print '<td class="right">' . "\n";
		print '<input type="checkbox" name="create_supplier_invoice" value="1"' . (!empty($object->parameters['order_actions']['create_supplier_invoice']) ? ' checked' : '') . ' />' . "\n";
		print '</td></tr>' . "\n";
	}
}

if (!empty($object->parameters['order_actions']['create_order']) ||
	!empty($object->parameters['order_actions']['create_invoice'])
) {
	// Default sale representative
	print '<tr class="oddeven">' . "\n";
	print '<td>' . $langs->trans("ECommerceCreateOrderSalesRepresentativeFollowByDefault") . '</td>' . "\n";
	print '<td>' . $langs->trans("ECommerceCreateOrderSalesRepresentativeFollowByDefaultDescription") . '</td>' . "\n";
	print '<td class="right">' . "\n";
	print $form->select_dolusers($object->parameters['default_sales_representative_follow'], 'default_sales_representative_follow', 1, null, 0, null, null, 0, 56, '', 0, '', 'minwidth200imp');
	print '</td></tr>' . "\n";

	// First date of the order to be synchronize
	print '<tr class="oddeven">' . "\n";
	print '<td>' . $langs->trans("ECommerceWoocommerceOrderFirstDateForECommerceToDolibarr") . '</td>' . "\n";
	print '<td>' . $langs->trans("ECommerceWoocommerceOrderFirstDateForECommerceToDolibarrDescription") . '</td>' . "\n";
	print '<td class="right">' . "\n";
	$value = isset($object->parameters['order_first_date_etod']) ? $object->parameters['order_first_date_etod'] : -1;
	print $form->select_date($value, 'order_first_date_etod', 0, 0, 0, '', 1, 1);
	print '</td></tr>' . "\n";

	// Add fees as product line
	print '<tr class="oddeven">' . "\n";
	print '<td>' . $langs->trans("ECommerceFeeLineAsItemLine") . '</td>' . "\n";
	print '<td>' . $langs->trans("ECommerceFeeLineAsItemLineDescription") . '</td>' . "\n";
	print '<td class="right">' . "\n";
	print '<input type="checkbox" name="fee_line_as_item_line" value="1"' . (!empty($object->parameters['order_actions']['fee_line_as_item_line']) ? ' checked' : '') . ' />' . "\n";
	print '</td></tr>' . "\n";

	// Add meta data in product line description
	print '<tr class="oddeven">' . "\n";
	print '<td>' . $langs->trans("ECommerceWoocommerceOrderMetaDataInProductLineToDescriptionForECommerceToDolibarr") . '</td>' . "\n";
	print '<td>' . $langs->trans("ECommerceWoocommerceOrderMetaDataInProductLineToDescriptionForECommerceToDolibarrDescription") . '</td>' . "\n";
	print '<td class="right">' . "\n";
	print '<input type="checkbox" name="order_metadata_product_lines_to_description_etod" value="1"' . (!empty($object->parameters['order_metadata_product_lines_to_description_etod']) ? ' checked' : '') . ' />' . "\n";
	print '</td></tr>' . "\n";

	if (!empty($object->parameters['order_metadata_product_lines_to_description_etod'])) {
		// Filter added meta data in product line description
		print '<tr class="oddeven">' . "\n";
		print '<td>' . $langs->trans("ECommerceWoocommerceOrderFilterMetaDataInProductLineToDescriptionForECommerceToDolibarr") . '</td>' . "\n";
		print '<td>' . $langs->trans("ECommerceWoocommerceOrderFilterMetaDataInProductLineToDescriptionForECommerceToDolibarrDescription") . '</td>' . "\n";
		print '<td class="right">' . "\n";
		$filter_mode = array(
			'exclude' => $langs->trans('ECommerceExclude'),
			'include' => $langs->trans('ECommerceInclude'),
		);
		$value_mode = isset($object->parameters['order_filter_mode_metadata_product_lines_to_description_etod']) ? $object->parameters['order_filter_mode_metadata_product_lines_to_description_etod'] : 'exclude';
		print $form->selectarray('order_filter_mode_metadata_product_lines_to_description_etod', $filter_mode, $value_mode, 0, 0, 0, '', 0, 0, 0, '', 'minwidth300');
		print '<input type="text" class="flat centpercent" name="order_filter_keys_metadata_product_lines_to_description_etod" value="' . dol_escape_htmltag($object->parameters['order_filter_keys_metadata_product_lines_to_description_etod']) . '">' . "\n";
		print '</td></tr>' . "\n";
	}
}

print '</table>'."\n";

print '<br>';
print '<div align="center">';
print '<input type="submit" class="button" value="' . $langs->trans('Modify') . '" />';
print '</div>';

print '</form>';

if (!empty($object->parameters['order_actions']['create_order'])) {
	/**
	 * Status settings.
	 */

	print '<div id="status_options"></div>';
	print load_fiche_titre($langs->trans("ECommerceOrderStatusSetup"), '', '');

	print '<form method="post" action="'.$_SERVER["PHP_SELF"] . '?id=' . $object->id . '#status_options">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="action" value="set_status_options">';

	print '<table class="noborder centpercent">';

	if (!empty($object->parameters['realtime_dtoe']['order'])) {
		// Synchronize status from dolibarr to site
		print '<tr class="liste_titre">';
		print '<td colspan="5">' . $langs->trans("DolibarrToECommerce") . '</td>' . "\n";
		print "</tr>\n";
		print '<tr class="liste_titre">';
		print '<td class="20p">' . $langs->trans("Parameters") . '</td>' . "\n";
		print '<td>' . $langs->trans("Description") . '</td>' . "\n";
		print '<td class="right" colspan="3">' . $langs->trans("Value") . '</td>' . "\n";
		print "</tr>\n";

		// Do not synchronize status if this rank is below the current remote status
		print '<tr class="oddeven">' . "\n";
		print '<td>' . $langs->trans("ECommerceOrderStatusDtoECheckLvlStatus") . '</td>' . "\n";
		print '<td>' . $langs->trans("ECommerceOrderStatusDtoECheckLvlStatusDescription") . '</td>' . "\n";
		print '<td class="right" colspan="3">' . "\n";
		print '<input type="checkbox" name="order_status_dtoe_check_lvl_status" value="1"' . (!empty($object->parameters['order_status_dtoe_check_lvl_status']) ? ' checked' : '') . ' />' . "\n";
		print '</td></tr>' . "\n";

		foreach ($order_dtoe_status as $d_status => $d_label) {
			$selected = isset($object->parameters['order_status_dtoe'][$d_status]) ? $object->parameters['order_status_dtoe'][$d_status] : '';

			print '<tr class="oddeven">' . "\n";
			print '<td>' . $d_label . '</td>' . "\n";
			print '<td>' . $langs->trans("ECommerceOrderStatusSetupDescription") . '</td>' . "\n";
			print '<td class="right" colspan="3">' . "\n";
			$array_list = array();
			foreach ($status_extra_fields_options as $key => $value) {
				if (($pos = strpos($key, '_')) > 0) $key = substr($key, $pos + 1);
				$array_list[$key] = $value;
			}
			print $form->selectarray('order_status_dtoe_' . $d_status, $array_list, $selected, 1, 0, 0, '', 0, 0, 0, '', 'minwidth300');
			print '</td></tr>' . "\n";
		}
	}

	print '<tr class="liste_titre">';
	print '<td colspan="5">'.$langs->trans("ECommerceToDolibarr").'</td>'."\n";
	print "</tr>\n";
	print '<tr class="liste_titre">';
	print '<td class="20p">'.$langs->trans("Parameters").'</td>'."\n";
	print '<td>'.$langs->trans("Description").'</td>'."\n";
	print '<td class="right">'.$langs->trans("Value").'</td>'."\n";
	print '<td class="center">'.$langs->trans("Billed").'</td>'."\n";
	print '<td class="center">'.$langs->trans("ECommerceSynchronize").'</td>'."\n";
	print "</tr>\n";

	foreach ($status_extra_fields_options as $key => $value) {
		if (($pos = strpos($key, '_')) > 0) $key = substr($key, $pos + 1);
		$selected = isset($object->parameters['order_status_etod'][$key]['selected']) ? $object->parameters['order_status_etod'][$key]['selected'] : '';
		$billed = isset($object->parameters['order_status_etod'][$key]['billed']) ? $object->parameters['order_status_etod'][$key]['billed'] : '';
		$synchronize = isset($object->parameters['order_status_etod'][$key]['synchronize']) ? $object->parameters['order_status_etod'][$key]['synchronize'] : '';

		print '<tr class="oddeven">' . "\n";
		print '<td>' . $value . '</td>' . "\n";
		print '<td>' . $langs->trans("ECommerceOrderStatusSetupDescription") . '</td>' . "\n";
		print '<td class="right">' . "\n";
		$array_list = array();
		foreach ($order_dtoe_status as $d_status => $d_label) {
			$array_list['s' . $d_status] = $d_label;
		}
		print $form->selectarray('order_status_etod_' . $key, $array_list, $selected, 1, 0, 0, '', 0, 0, 0, '', 'minwidth300');
		print '</td>';
		print '<td class="center"><input type="checkbox" name="order_status_etod_billed_' . $key . '" value="1"' . ($billed ? ' checked' : '') . '></td>' . "\n";
		print '<td class="center"><input type="checkbox" name="order_status_etod_synchronize_' . $key . '" value="1"' . ($synchronize ? ' checked' : '') . '></td>' . "\n";
		print '</tr>' . "\n";
	}

	print '</table>'."\n";

	print '<br>';
	print '<div align="center">';
	print '<input type="submit" class="button" value="' . $langs->trans('Modify') . '" />';
	print '</div>';

	print '</form>';
}

if (!empty($object->parameters['order_actions']['create_order']) ||
	!empty($object->parameters['order_actions']['create_invoice'])
) {
	/**
	 * Remote meta data with extra fields.
	 */

	foreach ($extrafields_list as $table_element => $info) {
		if (!empty($info['extrafields'])) {
			print '<div id="metadata_matching_extrafields_' . $table_element . '_options"></div>';
			print load_fiche_titre($langs->trans('ECommercengWoocommerceExtrafieldsCorrespondenceOf', $langs->transnoentitiesnoconv($info['label'])), '', '');

			print '<form method="post" action="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '#metadata_matching_extrafields_' . $table_element . '_options">';
			print '<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '">';
			print '<input type="hidden" name="action" value="set_metadata_matching_extrafields_options">';
			print '<input type="hidden" name="table_element" value="' . $table_element . '">';

			print '<table class="noborder centpercent">';
			print '<tr class="liste_titre">';
			print '<td class="20p">' . $langs->trans("ExtraFields") . '</td>' . "\n";
			print '<td>' . $langs->trans("Description") . '</td>' . "\n";
			print '<td class="right">' . $langs->trans("Value") . '</td>' . "\n";
			print '<td width="5%" class="center"><input type="checkbox" class="ef_crp_all" name="act_all_ef_crp_' . $table_element . '" value="1" title="' . $langs->trans('Enabled') . '"/></td>' . "\n";
			print "</tr>\n";

			foreach ($info['extrafields'] as $key => $label) {
				if (!empty($extrafields->attributes[$table_element]['langfile'][$key])) $langs->load($extrafields->attributes[$table_element]['langfile'][$key]);
				elseif (!empty($extrafields->attribute_langfile[$key])) $langs->load($extrafields->attribute_langfile[$key]);

				$options_saved = $object->parameters['ef_crp'][$table_element][$key];
				print '<tr class="oddeven">' . "\n";
				print '<td>' . $langs->trans($label) . '</td>' . "\n";
				print '<td>' . $langs->transnoentities('ECommercengWoocommerceExtrafieldsCorrespondenceSetupDescription', $key) . '</td>' . "\n";
				print '<td class="right">' . "\n";
				$value = !empty($options_saved['correspondences']) ? $options_saved['correspondences'] : $key;
				print '<input type="text" class="ef_crp_value centpercent" name="ef_crp_' . $table_element . '_' . $key . '" value="' . dol_escape_htmltag($value) . '"' . (empty($options_saved['activated']) ? ' disabled' : '') . ' />';
				print '</td>' . "\n";
				print '<td width="5%" class="center">' . "\n";
				print '<input type="checkbox" class="ef_crp_state" name="act_ef_crp_' . $table_element . '_' . $key . '" value="1"' . (!empty($options_saved['activated']) ? ' checked' : '') . ' title="' . $langs->trans('Enabled') . '" />' . "\n";
				print '</td></tr>' . "\n";
			}

			print '</table>' . "\n";

			print '<br>';
			print '<div align="center">';
			print '<input type="submit" class="button" value="' . $langs->trans('Modify') . '" />';
			print '</div>';

			print '</form>';
		}
	}

	/**
	 * Payment gateways.
	 */
	print '<div id="payment_gateways_options"></div>';
	print load_fiche_titre($langs->trans('ECommercePaymentGatewaysCorrespondence'), '', '');

	print '<form method="post" action="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '#payment_gateways_options">';
	print '<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '">';
	print '<input type="hidden" name="action" value="set_payment_gateways_options">';

	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td class="20p">' . $langs->trans("ECommercePaymentGatewayLabel") . '</td>' . "\n";
	print '<td>' . $langs->trans("PaymentMode") . '</td>' . "\n";
	print '<td>' . $langs->trans("BankAccount") . '</td>' . "\n";
	if (!empty($object->parameters['order_actions']['create_invoice'])) {
		print '<td>' . $langs->trans("ECommerceCreateAssociatePaymentForInvoice") . '</td>' . "\n";
		if (!empty($object->parameters['order_actions']['send_invoice_by_mail'])) {
			print '<td>' . $langs->trans("ECommerceSelectMailModelForSendInvoice") . '</td>' . "\n";
		}
		if (!empty($object->parameters['order_actions']['create_supplier_invoice'])) {
			print '<td>' . $langs->trans("Supplier") . '</td>' . "\n";
			print '<td>' . $langs->trans("ECommerceProductForFee") . '</td>' . "\n";
			print '<td>' . $langs->trans("ECommerceCreateAssociatePaymentForSupplierInvoice") . '</td>' . "\n";
		}
	}
	print "</tr>\n";

	// Get email templates
	$type_template = 'facture_send';
	$email_templates = array();
	$result = $formmail->fetchAllEMailTemplate($type_template, $user, $langs);
	if ($result < 0) {
		setEventMessages($formmail->error, $formmail->errors, 'errors');
	}
	foreach ($formmail->lines_model as $line) {
		if (preg_match('/\((.*)\)/', $line->label, $reg)) {
			$email_templates[$line->id] = $langs->trans($reg[1]);        // langs->trans when label is __(xxx)__
		} else {
			$email_templates[$line->id] = $line->label;
		}
		if ($line->lang) $email_templates[$line->id] .= ' (' . $line->lang . ')';
		if ($line->private) $email_templates[$line->id] .= ' - ' . $langs->trans("Private");
	}

	foreach ($payment_gateways as $key => $infos) {
		print '<tr class="oddeven">' . "\n";
		print '<td>' . $infos['payment_gateway_label'] . '</td>' . "\n";
		print '<td>';
		$form->select_types_paiements($infos['payment_mode_id'], 'payment_mode_id_' . $key);
		print '</td>' . "\n";
		print '<td>';
		$form->select_comptes($infos['bank_account_id'], 'bank_account_id_' . $key, 0, '', 1);
		print '</td>' . "\n";
		if (!empty($object->parameters['order_actions']['create_invoice'])) {
			print '<td><input type="checkbox" id="create_invoice_payment_' . $key . '" name="create_invoice_payment_' . $key . '" value="1"' . (!empty($infos['create_invoice_payment']) ? ' checked' : '') . '></td>' . "\n";
			if (!empty($object->parameters['order_actions']['send_invoice_by_mail'])) {
				print '<td>' . "\n";
				// Zone to select email template
				if (count($email_templates) > 0) {
					print $form->selectarray('mail_model_for_send_invoice_' . $key, $email_templates, $infos['mail_model_for_send_invoice'], 1, 0, 0, '', 0, 0, 0, '', 'minwidth100');
				} else {
					print '<select name="mail_model_for_send_invoice_' . $key . '" disabled="disabled"><option value="none">' . $langs->trans("NoTemplateDefined") . '</option></select>';    // Do not put 'disabled' on 'option' tag, it is already on 'select' and it makes chrome crazy.
				}
				if ($user->admin) print info_admin($langs->trans("YouCanChangeValuesForThisListFrom", $langs->transnoentitiesnoconv('Setup') . ' - ' . $langs->transnoentitiesnoconv('EMails')), 1);
				print '</td>' . "\n";
			}
			if (!empty($object->parameters['order_actions']['create_supplier_invoice'])) {
				print '<td>' . $form->select_company($infos['supplier_id'], 'supplier_id_' . $key, 's.fournisseur=1 AND status=1', 'SelectThirdParty', 0, 0, null, 0, 'minwidth300') . '</td>' . "\n";
				print '<td>';
				$form->select_produits($infos['product_id_for_fee'], 'product_id_for_fee_' . $key, '', $conf->product->limit_size, 0, -1, 2, '', 0, array(), 0, '1', 0, 'maxwidth300');
				print '</td>' . "\n";
				print '<td><input type="checkbox" id="create_supplier_invoice_payment_' . $key .'" name="create_supplier_invoice_payment_' . $key .'" value="1"' . (!empty($infos['create_supplier_invoice_payment']) ? ' checked' : '') . '></td>' . "\n";
			}
		}
		print '</tr>' . "\n";
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
