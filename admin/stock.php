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
require_once DOL_DOCUMENT_ROOT . '/product/class/html.formproduct.class.php';
dol_include_once('/ecommerceng/lib/eCommerce.lib.php');
dol_include_once('/ecommerceng/class/html.formecommerceng.class.php');
dol_include_once('/ecommerceng/class/data/eCommerceRemoteWarehouses.class.php');

$langs->loadLangs(array("admin", "stocks", "ecommerce@ecommerceng", "woocommerce@ecommerceng"));

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

if (empty($conf->product->enabled) && empty($conf->stock->enabled)) {
	accessforbidden($langs->trans('ModuleDisabled'));
}

if (!empty($object->parameters['enable_warehouse_plugin_sl_support'])) {
	$eCommerceRemoteWarehouses = new eCommerceRemoteWarehouses($db);
	$remote_warehouses = $eCommerceRemoteWarehouses->get_all($object->id);
	if (!is_array($remote_warehouses)) {
		setEventMessages($eCommerceRemoteWarehouses->error, $eCommerceRemoteWarehouses->errors, 'errors');
	}
}


/*
 *	Actions
 */

if ($action == 'set_options') {
	$object->oldcopy = clone $object;

	$object->parameters['order_actions']['valid_order_fk_warehouse'] = !empty($object->parameters['order_actions']['create_order']) ? GETPOST('valid_order_fk_warehouse', 'int') : -1;
	$object->parameters['order_actions']['valid_invoice_fk_warehouse'] = !empty($object->parameters['order_actions']['create_invoice']) ? GETPOST('valid_invoice_fk_warehouse', 'int') : -1;
	$object->parameters['order_actions']['valid_supplier_invoice_fk_warehouse'] = !empty($object->parameters['order_actions']['create_supplier_invoice']) ? GETPOST('valid_supplier_invoice_fk_warehouse', 'int') : -1;
	$object->stock_sync_direction = GETPOST('stock_sync_direction', 'az09');
	$object->parameters['enable_warehouse_plugin_sl_support'] = $object->stock_sync_direction != 'none' && GETPOST('enable_warehouse_plugin_sl_support', 'int') ? 1 : 0;
	$object->fk_warehouse = $object->stock_sync_direction == 'ecommerce2dolibarr' && empty($object->parameters['enable_warehouse_plugin_sl_support']) ? GETPOST('fk_warehouse', 'int') : 0;
	$object->fk_warehouse = $object->fk_warehouse > 0 ? $object->fk_warehouse : 0;
	$object->parameters['fk_warehouse_to_ecommerce'] = $object->stock_sync_direction == 'dolibarr2ecommerce' && empty($object->parameters['enable_warehouse_plugin_sl_support']) ? GETPOST('fk_warehouse_to_ecommerce', 'array') : array();

	$result = $object->update($user);

	if ($result < 0) {
		setEventMessages($object->error, $object->errors, 'errors');
	} else {
		setEventMessage($langs->trans("SetupSaved"));
		header("Location: " . $_SERVER["PHP_SELF"] . '?id=' . $object->id);
		exit;
	}
} elseif ($action == 'set_remote_warehouse_options' && !empty($object->parameters['enable_warehouse_plugin_sl_support'])) {
	foreach ($remote_warehouses as $remote_code => $infos) {
		$remote_warehouses[$remote_code]['warehouse_id'] = GETPOST('warehouse_id_' . $remote_code, 'int');
		$remote_warehouses[$remote_code]['warehouse_id'] = $remote_warehouses[$remote_code]['warehouse_id'] > 0 ? $remote_warehouses[$remote_code]['warehouse_id'] : 0;
		$remote_warehouses[$remote_code]['set_even_if_empty_stock'] = GETPOST('set_even_if_empty_stock_' . $remote_code, 'int') ? 1 : 0;
	}

	$result = $eCommerceRemoteWarehouses->set($object->id, $remote_warehouses);

	if ($result < 0) {
		setEventMessages($eCommerceRemoteWarehouses->error, $eCommerceRemoteWarehouses->errors, 'errors');
	} else {
		setEventMessage($langs->trans("SetupSaved"));
		header("Location: " . $_SERVER["PHP_SELF"] . '?id=' . $object->id);
		exit;
	}
} elseif ($action == 'confirm_update_remote_warehouses' && $confirm == "yes" && !empty($object->parameters['enable_warehouse_plugin_sl_support'])) {
	$result = ecommerceng_update_remote_warehouses($db, $object);
	if ($result) setEventMessage($langs->trans('ECommerceRemoteWarehousesUpdated'), 'mesgs');

	header("Location: " . $_SERVER["PHP_SELF"] . '?id=' . $object->id);
	exit;
}


/*
 *	View
 */

$form = new Form($db);
$formproduct = new FormProduct($db);
$formecommerceng = new FormECommerceNg($db);

$wikihelp='';
llxHeader('', $langs->trans("ECommerceSetup"), $wikihelp);

$formconfirm = '';
$lineid = '';

if ($action == 'update_remote_warehouses' && !empty($object->parameters['enable_warehouse_plugin_sl_support'])) {
	$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id . '&plugin=' . $plugin, $langs->trans('ECommerceUpdateRemoteWarehouses'), $langs->trans('ECommerceConfirmUpdateRemoteWarehouses'), 'confirm_update_remote_warehouses', '', 0, 1, 200, 800);
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

dol_fiche_head($head, 'stock', $langs->trans("Module107100Name"), 0, 'eCommerce@ecommerceng');

if (!empty($object->parameters['enable_warehouse_plugin_sl_support'])) {
	print '<div class="tabsAction tabsActionNoBottom">';
	print '<a class="butAction" href="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&amp;action=update_remote_warehouses">' . $langs->trans('ECommerceUpdateRemoteWarehouses') . '</a>';
	print '</div>';
}

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

if (!empty($object->parameters['order_actions']['create_order'])) {
	// Warehouse used when valid a order
	print '<tr class="oddeven">' . "\n";
	print '<td>' . $langs->trans("ECommerceValidOrderWarehouse") . '</td>' . "\n";
	print '<td>' . $langs->transnoentities("ECommerceValidOrderWarehouseDescription") . '</td>' . "\n";
	print '<td class="right">' . "\n";
	print $formproduct->selectWarehouses($object->parameters['order_actions']['valid_order_fk_warehouse'], 'valid_order_fk_warehouse', 0, 1);
	print '</td></tr>' . "\n";
}

if (!empty($object->parameters['order_actions']['create_invoice'])) {
	// Warehouse used when valid a invoice
	print '<tr class="oddeven">' . "\n";
	print '<td>' . $langs->trans("ECommerceValidInvoiceWarehouse") . '</td>' . "\n";
	print '<td>' . $langs->transnoentities("ECommerceValidInvoiceWarehouseDescription") . '</td>' . "\n";
	print '<td class="right">' . "\n";
	print $formproduct->selectWarehouses($object->parameters['order_actions']['valid_invoice_fk_warehouse'], 'valid_invoice_fk_warehouse', 0, 1);
	print '</td></tr>' . "\n";
}

if (!empty($object->parameters['order_actions']['create_supplier_invoice'])) {
	// Warehouse used when valid a supplier invoice
	print '<tr class="oddeven">' . "\n";
	print '<td>' . $langs->trans("ECommerceValidSupplierInvoiceWarehouse") . '</td>' . "\n";
	print '<td>' . $langs->transnoentities("ECommerceValidSupplierInvoiceWarehouseDescription") . '</td>' . "\n";
	print '<td class="right">' . "\n";
	print $formproduct->selectWarehouses($object->parameters['order_actions']['valid_supplier_invoice_fk_warehouse'], 'valid_supplier_invoice_fk_warehouse', 0, 1);
	print '</td></tr>' . "\n";
}

// Synchronize sens
print '<tr class="oddeven">' . "\n";
print '<td>'.$langs->trans("ECommerceStockSyncDirection").'</td>'."\n";
print '<td>'.$langs->transnoentities("ECommerceStockSyncDirectionDescription").'</td>'."\n";
print '<td class="right">' . "\n";
$synchronize_sens = array(
	'none' => $langs->trans('None'),
	'ecommerce2dolibarr' => $langs->trans('ECommerceToDolibarr'),
	'dolibarr2ecommerce' => $langs->trans('DolibarrToECommerce'),
);
print $form->selectarray('stock_sync_direction', $synchronize_sens, $object->stock_sync_direction, 0, 0, 0, '', 0, 0, 0, '', 'minwidth200 centpercent') . "\n";
print '</td></tr>' . "\n";

if (in_array($object->stock_sync_direction, [ 'dolibarr2ecommerce', 'ecommerce2dolibarr' ])) {
	// Support of WooCommerce plugin : Stock Locations for WooCommerce
	print '<tr class="oddeven">' . "\n";
	print '<td>' . $langs->trans("ECommerceWoocommerceEnableWarehouseSlPluginSupport") . '</td>' . "\n";
	print '<td>' . $langs->transnoentities("ECommerceWoocommerceEnableWarehousePluginSlSupportDescription") . '</td>' . "\n";
	print '<td class="right">' . "\n";
	print '<input type="checkbox" name="enable_warehouse_plugin_sl_support" value="1"' . (!empty($object->parameters['enable_warehouse_plugin_sl_support']) ? ' checked' : '') . ' />' . "\n";
	print '</td></tr>' . "\n";

	if (empty($object->parameters['enable_warehouse_plugin_sl_support'])) {
		// Warehouses
		print '<tr class="oddeven">' . "\n";
		print '<td>' . $langs->trans("ECommerceStockProduct") . '</td>' . "\n";
		print '<td>' . $langs->transnoentities("ECommerceStockProductDescription") . '</td>' . "\n";
		print '<td class="right">' . "\n";
		if ($object->stock_sync_direction == 'dolibarr2ecommerce') {
			$value = isset($object->parameters['fk_warehouse_to_ecommerce']) ? $object->parameters['fk_warehouse_to_ecommerce'] : array();
			print $formecommerceng->multiselectWarehouses($value, 'fk_warehouse_to_ecommerce');
		} else {
			print $formproduct->selectWarehouses($object->fk_warehouse, 'fk_warehouse', 0, 1);
		}
		print '</td></tr>' . "\n";
	}
}

print '</table>'."\n";

print '<br>';
print '<div align="center">';
print '<input type="submit" class="button" value="' . $langs->trans('Modify') . '" />';
print '</div>';

print '</form>';

if (!empty($object->parameters['enable_warehouse_plugin_sl_support'])) {
	/**
	 * Remote warehouses
	 */
	print '<div id="remote_warehouse_options"></div>';
	print load_fiche_titre($langs->trans('ECommerceRemoteWarehousesCorrespondence'), '', '');

	print '<form method="post" action="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '#remote_warehouse_options">';
	print '<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '">';
	print '<input type="hidden" name="action" value="set_remote_warehouse_options">';

	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td class="20p">' . $langs->trans("Code") . '</td>' . "\n";
	print '<td>' . $langs->trans("ECommerceRemoteID") . '</td>' . "\n";
    print '<td>' . $langs->trans("Label") . '</td>' . "\n";
	print '<td>' . $langs->trans("Warehouse") . '</td>' . "\n";
	print '<td>' . $langs->trans("ECommerceWarehouseSetToZeroEvenIfEmptyStock") . '</td>' . "\n";
	print '<td>' . $langs->trans("ECommerceWarehouseOldEntry") . '</td>' . "\n";
	print "</tr>\n";

	foreach ($remote_warehouses as $remote_code => $infos) {
		print '<tr class="oddeven">' . "\n";
		print '<td>' . $remote_code . '</td>' . "\n";
		print '<td>' . $infos['remote_id'] . '</td>' . "\n";
        print '<td>' . $infos['remote_name'] . '</td>' . "\n";
		print '<td>' . $formproduct->selectWarehouses($infos['warehouse_id'], 'warehouse_id_' . $remote_code, 0, 1) . '</td>' . "\n";
		print '<td><input type="checkbox" id="set_even_if_empty_stock_' . $remote_code .'" name="set_even_if_empty_stock_' . $remote_code .'" value="1"' . (!empty($infos['set_even_if_empty_stock']) ? ' checked' : '') . '></td>' . "\n";
		print '<td>' . yn($infos['old_entry']) . '</td>' . "\n";
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
