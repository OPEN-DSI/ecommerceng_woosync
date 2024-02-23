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
require_once(DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php');
require_once DOL_DOCUMENT_ROOT . '/includes/OAuth/bootstrap.php';
dol_include_once('/ecommerceng/lib/eCommerce.lib.php');

use OAuth\Common\Storage\DoliStorage;

$langs->loadLangs(array("admin", "companies", "bills", "accountancy", "banks", "oauth", "ecommerce@ecommerceng", "woocommerce@ecommerceng"));

if (!$user->admin && !$user->rights->ecommerceng->site) accessforbidden();

$id = GETPOST('id', 'int');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'aZ09');

$object = new eCommerceSite($db);
if (empty($action) && !($id > 0) && strlen($id) == 0) {
	$sites = $object->listSites();
	if (!empty($sites)) {
		$id = array_values($sites)[0]['id'];
	}
}
if ($id > 0) {
	$result = $object->fetch($id);
	if ($result < 0) {
		accessforbidden($object->errorsToString());
	} elseif ($result == 0) {
		$langs->load('errors');
		accessforbidden($langs->trans('ErrorRecordNotFound'));
	}
}

$extrafields = new ExtraFields($db);


/*
 *	Actions
 */
$error = 0;

include dol_buildpath('/ecommerceng/admin/actions_selectsite.inc.php');

if ($action == 'set_options') {
	if ($object->id > 0) $object->oldcopy = clone $object;

	$object->type = 2; // WooCommerce
	$object->name = GETPOST('site_name', 'alphanohtml');
	$object->api_version = GETPOST('site_api_version', 'alphanohtml');
	$object->webservice_address = GETPOST('site_webservice_address', 'alphanohtml');
	$object->authentication_type = GETPOST('site_authentication_type', 'az09');
	$object->user_name = GETPOST('site_user_name', 'alphanohtml');
	$object->user_password = GETPOST('site_user_password', 'none');
	$object->timeout = GETPOST('site_timeout', 'int');
	$object->debug = GETPOST('site_debug', 'int') ? 1 : 0;

	if(empty($object->name)) {
		setEventMessage($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("ECommerceSiteName")), 'errors');
		$error++;
	}
	if(empty($object->webservice_address)) {
		setEventMessage($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("ECommerceSiteAddress")), 'errors');
		$error++;
	}
	if(empty($object->authentication_type)) {
		setEventMessage($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("ECommerceAuthenticationType")), 'errors');
		$error++;
	}
	if(empty($object->user_name)) {
		setEventMessage($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("ECommerceUserName")), 'errors');
		$error++;
	}
	if(empty($object->user_password)) {
		setEventMessage($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("ECommerceUserPassword")), 'errors');
		$error++;
	}

	if (!$error) {
		$db->begin();

		if ($object->id > 0) {
			$result = $object->update($user);
		} else {
			// Default values
			//----------------
			// Product
			$object->parameters['realtime_dtoe']['product'] = 1;
			$object->ecommerce_price_type = 'HT';
			$object->parameters['product_synch_price'] = 'regular';
			$object->parameters['product_weight_units'] = 0; // 0 = Kg
			$object->parameters['product_dimension_units'] = 2; // 2 = cm
			$object->parameters['product_variation_mode'] = 'one_to_one';
			$object->parameters['product_status_supported'] = 'publish';
			$object->price_level = 1;
			$object->parameters['product_synch_direction']['ref'] = 'all';
			$object->parameters['product_synch_direction']['description'] = 'etod';
			$object->parameters['product_synch_direction']['short_description'] = 'etod';
			$object->parameters['product_synch_direction']['weight'] = 'etod';
			$object->parameters['product_synch_direction']['dimension'] = 'etod';
			$object->parameters['product_synch_direction']['tax'] = 'etod';
			$object->parameters['product_synch_direction']['status'] = 'etod';
			$object->parameters['product_synch_direction']['image'] = '';
			// Order
			$object->parameters['order_actions']['create_order'] = 1;
			$object->parameters['realtime_dtoe']['order'] = 1;
			$object->parameters['order_status_dtoe_check_lvl_status'] = 1;
			$order_dtoe_default_status = array(
				Commande::STATUS_CANCELED			=> 'cancelled',
				Commande::STATUS_DRAFT				=> 'on-hold',
				Commande::STATUS_VALIDATED			=> 'processing',
				Commande::STATUS_SHIPMENTONPROCESS	=> 'processing',
				Commande::STATUS_CLOSED				=> 'completed',
			);
			$values = array();
			foreach ($order_dtoe_default_status as $d_status => $default) {
				$values[$d_status]['selected'] = $default;
			}
			$object->parameters['order_status_dtoe'] = $values;
			$order_stod_default_status = array(
				"pending"		=> [ 'selected' => 's' . Commande::STATUS_DRAFT,		'billed' => 0, 'synchronize' => 1 ],
				"processing"	=> [ 'selected' => 's' . Commande::STATUS_VALIDATED,	'billed' => 0, 'synchronize' => 1 ],
				"on-hold"		=> [ 'selected' => 's' . Commande::STATUS_DRAFT,		'billed' => 0, 'synchronize' => 1 ],
				"completed"		=> [ 'selected' => 's' . Commande::STATUS_CLOSED,		'billed' => 1, 'synchronize' => 1 ],
				"cancelled"		=> [ 'selected' => 's' . Commande::STATUS_CANCELED,		'billed' => 0, 'synchronize' => 1 ],
				"refunded"		=> [ 'selected' => 's' . Commande::STATUS_CANCELED,		'billed' => 1, 'synchronize' => 1 ],
				"failed"		=> [ 'selected' => 's' . Commande::STATUS_CANCELED,		'billed' => 0, 'synchronize' => 1 ],
				"trash"			=> [ 'selected' => 's' . Commande::STATUS_CANCELED,		'billed' => 0, 'synchronize' => 1 ],
			);
			$values = array();
			foreach ($order_stod_default_status as $s_status => $s_default) {
				$values[$s_status]['selected'] = $s_default['selected'];
				$values[$s_status]['billed'] = $s_default['billed'];
				$values[$s_status]['synchronize'] = $s_default['synchronize'];
			}
			$object->parameters['order_status_etod'] = $values;

			$result = $object->create($user);
		}

		// Create all extrafields
		if ($result > 0) {
			// Product
			$res = $extrafields->addExtraField("ecommerceng_description_{$conf->entity}", 'ECommercengWoocommerceDescription', 'text', 1, '', 'product', 0, 0, '', null, 0, '0', 1, 0, '', '', 'ecommerceng@ecommerceng', '1', 'false');
			if ($res > 0) $res = $extrafields->addExtraField("ecommerceng_short_description_{$conf->entity}", 'ECommercengWoocommerceShortDescription', 'text', 2, '', 'product', 0, 0, '', null, 0, '0', 1, 0, '', '', 'ecommerceng@ecommerceng', '1', 'false');
			if ($res > 0) $res = $extrafields->addExtraField("ecommerceng_wc_status_{$object->id}_{$conf->entity}", $langs->trans('ECommercengWoocommerceStatus', $object->name), 'select', 3, '', 'product', 0, 0, '', array('options' => array(
				"draft" => $langs->trans('ECommercengWoocommerceStatusDraft'),
				"pending" => $langs->trans('ECommercengWoocommerceStatusPending'),
				"private" => $langs->trans('ECommercengWoocommerceStatusPrivate'),
				"publish" => $langs->trans('ECommercengWoocommerceStatusPublish'),
			)), 0, '0', 1, 0, '', '', 'ecommerceng@ecommerceng', '1', 'false');
			if ($res > 0) $res = $extrafields->addExtraField("ecommerceng_tax_class_{$object->id}_{$conf->entity}", $langs->trans('ECommercengWoocommerceTaxClass', $object->name), 'sellist', 4, '', 'product', 0, 0, '', array('options' => array("c_ecommerceng_tax_class:label:code::active=1 AND site_id={$object->id} AND entity={$conf->entity}" => null)), 0, '0', 1, 0, '', '', 'ecommerceng@ecommerceng', '1', 'false');
			if ($res > 0) $res = $extrafields->addExtraField("ecommerceng_wc_regular_price_{$object->id}_{$conf->entity}", $langs->trans('ECommercengWoocommerceRegularPrice', $object->name), 'price', 5, '', 'product', 0, 0, '', null, 0, '0', 1, 0, '', '', 'ecommerceng@ecommerceng', '1', 'false');
			if ($res > 0) $res = $extrafields->addExtraField("ecommerceng_wc_sale_price_{$object->id}_{$conf->entity}", $langs->trans('ECommercengWoocommerceSalePrice', $object->name), 'price', 6, '', 'product', 0, 0, '', null, 0, '0', 1, 0, '', '', 'ecommerceng@ecommerceng', '1', 'false');
			if ($res > 0) $res = $extrafields->addExtraField("ecommerceng_wc_date_on_sale_from_{$object->id}_{$conf->entity}", $langs->trans('ECommercengWoocommerceDateOnSaleFrom', $object->name), 'date', 7, '', 'product', 0, 0, '', null, 0, '0', 1, 0, '', '', 'ecommerceng@ecommerceng', '1', 'false');
			if ($res > 0) $res = $extrafields->addExtraField("ecommerceng_wc_date_on_sale_to_{$object->id}_{$conf->entity}", $langs->trans('ECommercengWoocommerceDateOnSaleTo', $object->name), 'date', 8, '', 'product', 0, 0, '', null, 0, '0', 1, 0, '', '', 'ecommerceng@ecommerceng', '1', 'false');
			if ($res > 0) $res = $extrafields->addExtraField("ecommerceng_wc_manage_stock_{$object->id}_{$conf->entity}", $langs->trans('ECommercengWoocommerceManageStock', $object->name), 'boolean', 9, '', 'product', 0, 0, '1', null, 0, '0', 1, 0, '', '', 'ecommerceng@ecommerceng', '1', 'false');
			if ($res > 0) $res = $extrafields->addExtraField("ecommerceng_wc_dont_update_stock_{$object->id}_{$conf->entity}", $langs->trans('ECommercengWoocommerceDontUpdateStock', $object->name), 'boolean', 10, '', 'product', 0, 0, '0', null, 0, '0', 1, 0, '', '', 'ecommerceng@ecommerceng', '1', 'false');
			// Order
			if ($res > 0) $res = $extrafields->addExtraField("ecommerceng_online_payment_{$conf->entity}", 'ECommercengWoocommerceOnlinePayment', 'boolean', 1, '', 'commande', 0, 0, '', null, 0, '0', 1, 0, '', '', 'ecommerceng@ecommerceng', '1', 'false');
			if ($res > 0) $res = $extrafields->addExtraField("ecommerceng_wc_status_{$object->id}_{$conf->entity}", $langs->trans('ECommercengWoocommerceOrderStatus', $object->name), 'select', 2, '', 'commande', 0, 0, '', array('options' => array(
				"0_pending" => $langs->trans('ECommercengWoocommerceOrderStatusPending'),
				"1_on-hold" => $langs->trans('ECommercengWoocommerceOrderStatusOnHold'),
				"2_processing" => $langs->trans('ECommercengWoocommerceOrderStatusProcessing'),
				"3_completed" => $langs->trans('ECommercengWoocommerceOrderStatusCompleted'),
				"3_cancelled" => $langs->trans('ECommercengWoocommerceOrderStatusCancelled'),
				"3_refunded" => $langs->trans('ECommercengWoocommerceOrderStatusRefunded'),
				"3_failed" => $langs->trans('ECommercengWoocommerceOrderStatusFailed'),
				"3_trash" => $langs->trans('ECommercengWoocommerceOrderStatusTrash'),
			)), 0, '0', 1, 0, '', '', 'ecommerceng@ecommerceng', '1', 'false');
			if ($res > 0) $res = $extrafields->addExtraField("ecommerceng_wc_link_{$object->id}_{$conf->entity}", $langs->trans('ECommercengWoocommerceOrderLink', $object->name), 'url', 3, '', 'commande', 0, 0, '', null, 0, '0', 1, 0, '', '', 'ecommerceng@ecommerceng', '1', 'false');
			// Third party
			if ($res > 0) $res = $extrafields->addExtraField("ecommerceng_wc_role_{$object->id}_{$conf->entity}", $langs->trans('ECommercengWoocommerceCompanyRole', $object->name), 'varchar', 1, '255', 'societe', 0, 0, '', null, 0, '0', 1, 0, '', '', 'ecommerceng@ecommerceng', '1', 'false');
			if ($res < 0) setEventMessages($extrafields->error, $extrafields->errors, 'errors');

			if ($res > 0) {
				// Fix extrafields wc_update_stock to wc_manage_stock on product
				$db->query("UPDATE " . MAIN_DB_PREFIX . "product_extrafields SET ecommerceng_wc_manage_stock_{$object->id}_{$conf->entity} = ecommerceng_wc_update_stock_{$object->id}_{$conf->entity}");
				$extrafields->delete("ecommerceng_wc_update_stock_{$object->id}_{$conf->entity}", 'product');
			}
		}

		if ($result < 0) setEventMessages($object->error, $object->errors, 'errors');

		if ($result < 0) {
			$db->rollback();
		} else {
			$db->commit();
			setEventMessage($langs->trans("SetupSaved"));
			header("Location: " . $_SERVER["PHP_SELF"] . '?id=' . $object->id);
			exit;
		}
	}
} elseif ($action == 'set_web_hooks_options' && $object->id > 0) {
	$object->oldcopy = clone $object;
	$object->parameters['web_hooks_secret'] = GETPOST('site_web_hooks_secret', 'none');
	$object->parameters['web_hooks_volumetry_alert'] = GETPOST('site_web_hooks_volumetry_alert', 'int');

	$result = $object->update($user);
	if ($result < 0) {
		setEventMessages($object->error, $object->errors, 'errors');
	} else {
		setEventMessage($langs->trans("SetupSaved"));
		header("Location: " . $_SERVER["PHP_SELF"] . '?id=' . $object->id);
		exit;
	}
} elseif ($action == 'set_wordpress_api_options' && $object->id > 0) {
	$object->oldcopy = clone $object;

	$object->wordpress_authentication_type = GETPOST('wordpress_authentication_type', 'az09');
	$object->wordpress_authentication_login = !empty($object->wordpress_authentication_type) ? GETPOST('wordpress_authentication_login', 'none') : '';
	$object->wordpress_authentication_password = !empty($object->wordpress_authentication_type) ? GETPOST('wordpress_authentication_password', 'none') : '';
	$object->wordpress_timeout = !empty($object->wordpress_authentication_type) ? GETPOST('wordpress_timeout', 'int') : 0;
	$object->wordpress_debug = GETPOST('wordpress_debug', 'int') ? 1 : 0;

	if(!empty($object->wordpress_authentication_type)) {
		if (empty($object->wordpress_authentication_login)) {
			setEventMessage($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("ECommerceWordpressAuthenticationLogin")), 'errors');
			$error++;
		}
		if (empty($object->wordpress_authentication_password)) {
			setEventMessage($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("ECommerceWordpressAuthenticationPassword")), 'errors');
			$error++;
		}
	}

	if (!$error) {
		$result = $object->update($user);
		if ($result < 0) {
			setEventMessages($object->error, $object->errors, 'errors');
		} else {
			setEventMessage($langs->trans("SetupSaved"));
			header("Location: " . $_SERVER["PHP_SELF"] . '?id=' . $object->id);
			exit;
		}
	}
} elseif ($action == 'confirm_delete' && $confirm == "yes" && $object->id > 0) {
	$result = $object->delete($user);
	if ($result < 0) {
		setEventMessages($object->error, $object->errors, 'errors');
	} else {
		setEventMessage($langs->trans("RecordDeleted"));
		header("Location: " . $_SERVER["PHP_SELF"]);
		exit;
	}
}


/*
 *	View
 */

$form = new Form($db);

$wikihelp='';
llxHeader('', $langs->trans("ECommerceSetup"), $wikihelp);

$formconfirm = '';

if ($action == 'delete') {
	$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('Delete'), $langs->trans('ECommerceConfirmDelete'), 'confirm_delete', '', 0, 1, 200, 800);
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

dol_fiche_head($head, 'settings', $langs->trans("Module107100Name"), 0, 'eCommerce@ecommerceng');

if ($object->id > 0) {
	print '<div class="tabsAction tabsActionNoBottom">';
	print '<a class="butAction butActionDelete" href="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&amp;action=delete&token='.ecommercengNewToken().'">' . $langs->trans('Delete') . '</a>';
	print '</div>';
}

/**
 * Settings.
 */

print '<div id="options"></div>';
print load_fiche_titre($langs->trans("Parameters"), '', '');

print '<form method="post" action="'.$_SERVER["PHP_SELF"] . ($object->id > 0 ? '?id=' . $object->id : '') . '#options">';
print '<input type="hidden" name="token" value="'.ecommercengNewToken().'">';
print '<input type="hidden" name="action" value="set_options">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td class="width20p">'.$langs->trans("Parameters").'</td>'."\n";
print '<td>'.$langs->trans("Description").'</td>'."\n";
print '<td class="right width40p">'.$langs->trans("Value").'</td>'."\n";
print "</tr>\n";

// Site name
print '<tr class="oddeven">' . "\n";
print '<td class="fieldrequired">'.$langs->trans("ECommerceSiteName").'</td>'."\n";
print '<td>'.$langs->transnoentities("ECommerceSiteNameDescription").'</td>'."\n";
print '<td class="right">' . "\n";
print '<input type="text" class="flat centpercent" name="site_name" value="' . dol_escape_htmltag($object->name) . '">' . "\n";
print '</td></tr>' . "\n";

// Site url
if (!empty($object->webservice_address)) $test_url = rtrim($object->webservice_address, '/') . '/wp-json/';
else $test_url = '';
print '<tr class="oddeven">' . "\n";
print '<td class="fieldrequired">'.$langs->trans("ECommerceSiteAddress").'</td>'."\n";
print '<td>'.$langs->transnoentities("ECommerceSiteAddressDescription").(!empty($test_url) ? '<br><a href="'.$test_url.'" target="_blank">'.$langs->trans("ECommerceClickUrlToTestUrl").'</a>' : '') . '</td>'."\n";
print '<td class="right">' . "\n";
print '<input type="text" class="flat centpercent" name="site_webservice_address" value="' . dol_escape_htmltag($object->webservice_address) . '">' . "\n";
print '</td></tr>' . "\n";

// Site API version
print '<tr class="oddeven">' . "\n";
print '<td class="fieldrequired">'.$langs->trans("ECommerceApiVersion").'</td>'."\n";
print '<td>' . $langs->transnoentities("ECommerceApiVersionDescription") . '</td>' . "\n";
print '<td class="right">' . "\n";
$api_versions = array(
	'v1' => 'V1',
	'v2' => 'V2',
	'v3' => 'V3',
);
if (!in_array($object->api_version, array_keys($api_versions))) $object->api_version = 'v3';
print $form->selectarray('site_api_version', $api_versions, $object->api_version, 0, 0, 0, '', 1, 0, 0, '', 'minwidth200 centpercent') . "\n";
print '</td></tr>' . "\n";

// Site API authentication type
print '<tr class="oddeven">' . "\n";
print '<td class="fieldrequired">'.$langs->trans("ECommerceAuthenticationType").'</td>'."\n";
print '<td>' . $langs->transnoentities("ECommerceAuthenticationTypeDescription") . '</td>' . "\n";
print '<td class="right">' . "\n";
$authentication_types = array(
	'oauth1_header' => 'ECommerceAuthenticationTypeOauth1Header',
	'oauth1_query' => 'ECommerceAuthenticationTypeOauth1Query',
	'basic' => 'ECommerceAuthenticationTypeBasic',
	'query' => 'ECommerceAuthenticationTypeQuery',
);
print $form->selectarray('site_authentication_type', $authentication_types, $object->authentication_type, 0, 0, 0, '', 1, 0, 0, '', 'minwidth200 centpercent') . "\n";
print '</td></tr>' . "\n";

// Site API login
print '<tr class="oddeven">' . "\n";
print '<td class="fieldrequired">'.$langs->trans("ECommerceUserName").'</td>'."\n";
print '<td>'.$langs->transnoentities("ECommerceUserNameDescription").'</td>'."\n";
print '<td class="right">' . "\n";
print '<input type="text" class="flat centpercent" name="site_user_name" value="' . dol_escape_htmltag($object->user_name) . '">' . "\n";
print '</td></tr>' . "\n";

// Site API password
print '<tr class="oddeven">' . "\n";
print '<td class="fieldrequired">'.$langs->trans("ECommerceUserPassword").'</td>'."\n";
print '<td>'.$langs->transnoentities("ECommerceUserPasswordDescription").'</td>'."\n";
print '<td class="right">' . "\n";
print '<input type="password" class="flat centpercent" name="site_user_password" value="' . dol_escape_htmltag($object->user_password) . '">' . "\n";
print '</td></tr>' . "\n";

// Site API timeout
print '<tr class="oddeven">' . "\n";
print '<td>'.$langs->trans("ECommerceTimeout").'</td>'."\n";
print '<td>'.$langs->transnoentities("ECommerceTimeoutDescription").'</td>'."\n";
print '<td class="right">' . "\n";
print '<input type="number" class="flat centpercent" name="site_timeout" value="' . dol_escape_htmltag($object->timeout) . '">' . "\n";
print '</td></tr>' . "\n";

// Site API debug
print '<tr class="oddeven">' . "\n";
print '<td>'.$langs->trans("ECommerceDebug").'</td>'."\n";
print '<td>'.$langs->trans("ECommerceDebugDescription").'</td>'."\n";
print '<td class="right">' . "\n";
print '<input type="checkbox" name="site_debug" value="1"' . (!empty($object->debug) ? ' checked' : '') . ' />' . "\n";
print '</td></tr>' . "\n";

print '</table>'."\n";

print '<br>';
print '<div align="center">';
print '<input type="submit" class="button" value="' . $langs->trans($object->id > 0 ? 'Modify' : 'Create') . '" />';
print '</div>';

print '</form>';

if ($object->id > 0) {
	/**
	 * WebHooks.
	 */

	print '<div id="web_hooks_options"></div>';
	print load_fiche_titre($langs->trans("ECommerceSiteWebHooksSetup"), '', '');

	print '<form method="post" action="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '#web_hooks_options">';
	print '<input type="hidden" name="token" value="' . ecommercengNewToken() . '">';
	print '<input type="hidden" name="action" value="set_web_hooks_options">';

	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td class="width20p">' . $langs->trans("Parameters") . '</td>' . "\n";
	print '<td>' . $langs->trans("Description") . '</td>' . "\n";
	print '<td class="right width40p">' . $langs->trans("Value") . '</td>' . "\n";
	print "</tr>\n";

	// Site web hooks url
	print '<tr class="oddeven">' . "\n";
	print '<td>' . $langs->trans("ECommerceSiteWebHooksUrl") . '</td>' . "\n";
	print '<td>' . $langs->transnoentities("ECommerceSiteWebHooksUrlDescription") . '</td>' . "\n";
	print '<td class="right">' . "\n";
	$uriFactory = new \OAuth\Common\Http\Uri\UriFactory();
	$currentUri = $uriFactory->createFromAbsolute(dol_buildpath('/ecommerceng/webhooks.php', 2) . '?ecommerce_id=' . $object->id);
	$web_hooks_url = $currentUri->getAbsoluteUri();
	print '<input type="text" class="flat centpercent" readonly="readonly" value="' . dol_escape_htmltag($web_hooks_url) . '">' . "\n";
	print '</td></tr>' . "\n";

	// Site web hooks secret
	print '<tr class="oddeven">' . "\n";
	print '<td>' . $langs->trans("ECommerceSiteWebHooksSecret") . '</td>' . "\n";
	print '<td>' . $langs->transnoentities("ECommerceSiteWebHooksSecretDescription") . '</td>' . "\n";
	print '<td class="right">' . "\n";
	print '<table class="nobordernopadding centpercent"><tr><td>' . "\n";
	$p_webhooksecret = !empty($object->parameters['web_hooks_secret']) ? $object->parameters['web_hooks_secret'] : '';
	print '<input type="text" class="flat centpercent" id="site_web_hooks_secret" name="site_web_hooks_secret" value="' . dol_escape_htmltag($p_webhooksecret) . '">' . "\n";
	if (!empty($conf->use_javascript_ajax)) {
		print '</td><td class="width25">' . img_picto($langs->trans('Generate'), 'refresh', 'id="generate_web_hooks_secret" class="linkobject"');
		print "\n" . '<script type="text/javascript">';
		print '$(document).ready(function () {
            $("#generate_web_hooks_secret").click(function() {
                $.get( "' . DOL_URL_ROOT . '/core/ajax/security.php", {
                    action: \'getrandompassword\',
                    generic: true
                },
                function(token) {
                    $("#site_web_hooks_secret").val(token);
                });
            });
    });';
		print '</script>';
	}
	print '</td></tr></table>' . "\n";
	print '</td></tr>' . "\n";

	// Site web hooks volumetry alert
	print '<tr class="oddeven">' . "\n";
	print '<td>' . $langs->trans("ECommerceSiteWebHooksVolumetryAlert") . '</td>' . "\n";
	print '<td>' . $langs->transnoentities("ECommerceSiteWebHooksVolumetryAlertDescription") . '</td>' . "\n";
	print '<td class="right">' . "\n";
	$p_webhookvolalert = !empty($object->parameters['web_hooks_volumetry_alert']) ? $object->parameters['web_hooks_volumetry_alert'] : '';
	print '<input type="number" class="flat centpercent" name="site_web_hooks_volumetry_alert" value="' . dol_escape_htmltag($p_webhookvolalert) . '">' . "\n";
	print '</td></tr>' . "\n";

	print '</table>' . "\n";

	print '<br>';
	print '<div align="center">';
	print '<input type="submit" class="button" value="' . $langs->trans('Modify') . '" />';
	print '</div>';

	print '</form>';

	if (!empty($object->webservice_address)) {
		/**
		 * Wordpress API (wordpress token connexion or plugin "WordPress REST API Authentication by miniOrange").
		 */

		print '<div id="wordpress_api_options"></div>';
		$setup_help = '';
		if ($object->wordpress_authentication_type == 'wordpress_application') {
			$setup_help = ' ' . $langs->trans("ECommerceWordpressAuthenticationSetup", rtrim($object->webservice_address, "/") . '/wp-admin/users.php');
		} elseif ($object->wordpress_authentication_type == 'jwt_authentication') {
			$setup_help = ' ' . $langs->trans("ECommerceWordpressAuthenticationSetup", rtrim($object->webservice_address, "/") . '/wp-admin/admin.php?page=mo_api_authentication_settings');
		}
		print load_fiche_titre($langs->trans("ECommerceWordpressAuthenticationOptions") . $setup_help, '', '');

		print '<form method="post" action="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '#wordpress_api_options">';
		print '<input type="hidden" name="token" value="' . ecommercengNewToken() . '">';
		print '<input type="hidden" name="action" value="set_wordpress_api_options">';

		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<td class="width20p">' . $langs->trans("Parameters") . '</td>' . "\n";
		print '<td>' . $langs->trans("Description") . '</td>' . "\n";
		print '<td class="right width40p">' . $langs->trans("Value") . '</td>' . "\n";
		print "</tr>\n";

		// Wordpress authentication type
		print '<tr class="oddeven">' . "\n";
		print '<td>'.$langs->trans("ECommerceWordpressAuthenticationType").'</td>'."\n";
		print '<td>' . $langs->transnoentities("ECommerceWordpressAuthenticationTypeDescription") . '</td>' . "\n";
		print '<td class="right">' . "\n";
		$wordpress_authentication_types = array(
			'' => 'None',
			'wordpress_application' => 'ECommerceWordpressAuthenticationTypeWordpressApplication',
			'jwt_authentication' => 'ECommerceWordpressAuthenticationTypeJWTAuthentication',
		);
		print $form->selectarray('wordpress_authentication_type', $wordpress_authentication_types, $object->wordpress_authentication_type, 0, 0, 0, '', 1, 0, 0, '', 'minwidth200 centpercent') . "\n";
		print '</td></tr>' . "\n";

		if (!empty($object->wordpress_authentication_type)) {
			// Wordpress authentication login
			print '<tr class="oddeven">' . "\n";
			print '<td class="fieldrequired">' . $langs->trans("ECommerceWordpressAuthenticationLogin") . '</td>' . "\n";
			print '<td>' . $langs->transnoentities("ECommerceWordpressAuthenticationLoginDescription") . '</td>' . "\n";
			print '<td class="right">' . "\n";
			print '<input type="text" class="flat centpercent" name="wordpress_authentication_login" value="' . dol_escape_htmltag($object->wordpress_authentication_login) . '">' . "\n";
			print '</td></tr>' . "\n";

			// Wordpress authentication password
			print '<tr class="oddeven">' . "\n";
			print '<td class="fieldrequired">' . $langs->trans("ECommerceWordpressAuthenticationPassword") . '</td>' . "\n";
			print '<td>' . $langs->transnoentities("ECommerceWordpressAuthenticationPasswordDescription") . '</td>' . "\n";
			print '<td class="right">' . "\n";
			print '<input type="password" class="flat centpercent" name="wordpress_authentication_password" value="' . dol_escape_htmltag($object->wordpress_authentication_password) . '">' . "\n";
			print '</td></tr>' . "\n";

			// Wordpress timeout
			print '<tr class="oddeven">' . "\n";
			print '<td>'.$langs->trans("ECommerceWordpressTimeout").'</td>'."\n";
			print '<td>'.$langs->transnoentities("ECommerceWordpressTimeoutDescription").'</td>'."\n";
			print '<td class="right">' . "\n";
			print '<input type="number" class="flat centpercent" name="wordpress_timeout" value="' . dol_escape_htmltag($object->wordpress_timeout) . '">' . "\n";
			print '</td></tr>' . "\n";

			// Wordpress debug
			print '<tr class="oddeven">' . "\n";
			print '<td>'.$langs->trans("ECommerceWordpressDebug").'</td>'."\n";
			print '<td>'.$langs->trans("ECommerceWordpressDebugDescription").'</td>'."\n";
			print '<td class="right">' . "\n";
			print '<input type="checkbox" name="wordpress_debug" value="1"' . (!empty($object->wordpress_debug) ? ' checked' : '') . ' />' . "\n";
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

print dol_get_fiche_end();

llxFooter();

$db->close();
