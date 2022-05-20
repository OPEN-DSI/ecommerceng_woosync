<?php
/* Copyright (C) 2019      Open-DSI              <support@open-dsi.fr>
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
 *	\file       	htdocs/ecommerceng/webhookslist.php
 *	\ingroup    	ecommerceng
 *	\brief      	Page of webhooks list
 */

// Change this following line to use the correct relative path (../, ../../, etc)
$res=0;
if (! $res && file_exists("../main.inc.php")) $res=@include '../main.inc.php';			// to work if your module directory is into a subdir of root htdocs directory
if (! $res && file_exists("../../main.inc.php")) $res=@include '../../main.inc.php';		// to work if your module directory is into a subdir of root htdocs directory
if (! $res) die("Include of main fails");
include_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
dol_include_once("/ecommerceng/class/business/eCommercePendingWebHook.class.php");
dol_include_once("/ecommerceng/class/business/eCommerceSynchro.class.php");

$langs->load("ecommerce@ecommerceng");

$action			= GETPOST('action', 'alpha');
$massaction		= GETPOST('massaction', 'alpha');
$confirm		= GETPOST('confirm', 'alpha');
$toselect 		= GETPOST('toselect', 'array');

// Security check
if ($user->societe_id > 0 || !$user->rights->ecommerceng->read) {
	accessforbidden();
}

$search_technical_id            = GETPOST('search_technical_id', 'int');
$search_site					= GETPOST('search_site', 'int');
$search_delivery_id				= GETPOST('search_delivery_id', 'alpha');
$search_webhook_id				= GETPOST('search_webhook_id', 'alpha');
$search_webhook_topic			= GETPOST('search_webhook_topic', 'alpha');
$search_webhook_resource		= GETPOST('search_webhook_resource', 'alpha');
$search_webhook_event           = GETPOST('search_webhook_event', 'alpha');
$search_webhook_signature       = GETPOST('search_webhook_signature', 'alpha');
$search_webhook_source          = GETPOST('search_webhook_source', 'alpha');
$search_webhook_data            = GETPOST('search_webhook_data', 'alpha');
$search_error_msg        		= GETPOST('search_error_msg', 'alpha');
$search_datep_start				= dol_mktime(0, 0, 0, GETPOST('search_datep_startmonth', 'int'), GETPOST('search_datep_startday', 'int'), GETPOST('search_datep_startyear', 'int'));
$search_datep_end				= dol_mktime(23, 59, 59, GETPOST('search_datep_endmonth', 'int'), GETPOST('search_datep_endday', 'int'), GETPOST('search_datep_endyear', 'int'));
$search_datec_start				= dol_mktime(0, 0, 0, GETPOST('search_datec_startmonth', 'int'), GETPOST('search_datec_startday', 'int'), GETPOST('search_datec_startyear', 'int'));
$search_datec_end				= dol_mktime(23, 59, 59, GETPOST('search_datec_endmonth', 'int'), GETPOST('search_datec_endday', 'int'), GETPOST('search_datec_endyear', 'int'));

$viewstatut		= GETPOST('viewstatut', 'alpha');
$optioncss		= GETPOST('optioncss', 'alpha');
$object_statut	= GETPOST('search_statut', 'alpha');

$search_btn         = GETPOST('button_search','alpha');
$search_remove_btn  = GETPOST('button_removefilter_x','alpha') || GETPOST('button_removefilter.x','alpha') || GETPOST('button_removefilter','alpha'); // All tests are required to be compatible with all browsers

$mesg = (GETPOST("msg") ? GETPOST("msg") : GETPOST("mesg"));

$limit      = GETPOST('limit')?GETPOST('limit','int'):$conf->liste_limit;
$sortfield  = GETPOST('sortfield','alpha');
$sortorder  = GETPOST('sortorder', 'aZ09comma');
$page       = GETPOST("page",'int');
if (empty($page) || $page == -1 || !empty($search_btn) || !empty($search_remove_btn) || (empty($toselect) && $massaction === '0')) { $page = 0; }     // If $page is not defined, or '' or -1
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
if (! $sortfield) $sortfield='epw.datec';
if (! $sortorder) $sortorder='DESC';

// Initialize technical object to manage context to save list fields
$contextpage = GETPOST('contextpage','aZ') ? GETPOST('contextpage','aZ') : 'ecommercewebhookslist';

$object = new eCommercePendingWebHook($db);

// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array of hook context
$hookmanager->initHooks(array('ecommercewebhookslist'));

$arrayfields = array(
	'epw.rowid' => array('label' => $langs->trans("TechnicalID"), 'checked' => (!empty($conf->global->MAIN_SHOW_TECHNICAL_ID) ? 1 : 0)),
	'es.name' => array('label' => $langs->trans("ECommerceSite"), 'checked' => 1),
	'epw.delivery_id' => array('label' => $langs->trans("ECommerceDeliveryId"), 'checked' => 0),
	'epw.webhook_id' => array('label' => $langs->trans("ECommerceWebHookId"), 'checked' => 0),
	'epw.webhook_topic' => array('label' => $langs->trans("ECommerceWebHookTopic"), 'checked' => 1),
	'epw.webhook_resource' => array('label' => $langs->trans("ECommerceWebHookResource"), 'checked' => 0),
	'epw.webhook_event' => array('label' => $langs->trans("ECommerceWebHookEvent"), 'checked' => 0),
	'epw.webhook_signature' => array('label' => $langs->trans("ECommerceWebHookSignature"), 'checked' => 0),
	'epw.webhook_source' => array('label' => $langs->trans("ECommerceWebHookSource"), 'checked' => 1),
	'epw.webhook_data' => array('label' => $langs->trans("ECommerceWebHookData"), 'checked' => 1),
	'epw.error_msg' => array('label' => $langs->trans("ECommerceErrorMessage"), 'checked' => 1),
	'epw.datep' => array('label' => $langs->trans("ECommerceProcessedDate"), 'checked' => 1, 'position' => 500),
	'epw.datec' => array('label' => $langs->trans("DateCreation"), 'checked' => 1, 'position' => 500),
	'epw.status' => array('label' => "Status", 'checked' => 1, 'position' => 1000),
);


/*
 * Actions
 */

if (GETPOST('cancel', 'alpha')) { $action='list'; $massaction=''; }
if (!GETPOST('confirmmassaction', 'alpha') &&
	$massaction != 'pre_set_to_process' && $massaction != 'confirm_set_to_process' &&
	$massaction != 'pre_set_processed' && $massaction != 'confirm_set_processed'
) { $massaction=''; }

$parameters=array();
$reshook=$hookmanager->executeHooks('doActions', $parameters, $object, $action);    // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';

// Do we click on purge search criteria ?
if ($search_remove_btn) {
	$search_technical_id = '';
	$search_site = '';
	$search_delivery_id = '';
	$search_webhook_id = '';
	$search_webhook_topic = '';
	$search_webhook_resource = '';
	$search_webhook_event = '';
	$search_webhook_signature = '';
	$search_webhook_source = -1;
	$search_webhook_data = '';
	$search_error_msg = '';
	$search_datep_start = '';
	$search_datep_end = '';
	$search_datec_start = '';
	$search_datec_end = '';
	$viewstatut = '';
	$object_statut = '';
	$toselect = '';
}
if ($object_statut != '') $viewstatut = $object_statut;

if (empty($reshook)) {
	$objectclass = 'eCommercePendingWebHook';
	$objectlabel = 'ECommerceWebHooks';
	$permtoread = $user->rights->ecommerceng->read;
	$permtodelete = $user->rights->ecommerceng->write;
	$permtoclose = $user->rights->ecommerceng->write;
	$uploaddir = $conf->ecommerceng->multidir_output[$conf->entity];
	include DOL_DOCUMENT_ROOT . '/core/actions_massactions.inc.php';

	if (!$error && ($massaction == 'confirm_set_to_process' || ($action == 'confirm_set_to_process' && $confirm == 'yes')) && $user->rights->ecommerceng->write) {
		$nbok = 0;
		foreach ($toselect as $toselectid) {
			$result = $object->setStatusToProcess($toselectid);
			if ($result < 0) {
				setEventMessages($object->error, $object->errors, 'errors');
				$error++;
				break;
			}
			$nbok++;
		}

		if (!$error) {
			if ($nbok > 1) setEventMessages($langs->trans("RecordsModified", $nbok), null, 'mesgs');
			else setEventMessages($langs->trans("RecordModified", $nbok), null, 'mesgs');
		}
	} elseif (!$error && ($massaction == 'confirm_set_processed' || ($action == 'confirm_set_processed' && $confirm == 'yes')) && $user->rights->ecommerceng->write) {
		$nbok = 0;
		foreach ($toselect as $toselectid) {
			$result = $object->setStatusProcessed($toselectid);
			if ($result < 0) {
				setEventMessages($object->error, $object->errors, 'errors');
				$error++;
				break;
			}
			$nbok++;
		}

		if (!$error) {
			if ($nbok > 1) setEventMessages($langs->trans("RecordsModified", $nbok), null, 'mesgs');
			else setEventMessages($langs->trans("RecordModified", $nbok), null, 'mesgs');
		}
	}
}


/*
 * View
 */

$now=dol_now();

$form = new Form($db);

$help_url='';

$sqlselect = 'SELECT epw.rowid, epw.status';
$sqlselect.= ", epw.site_id, es.name AS site_name";
$sqlselect.= ", epw.delivery_id, epw.webhook_id, epw.webhook_topic, epw.webhook_resource, epw.webhook_event, epw.webhook_signature";
$sqlselect.= ", epw.webhook_source, epw.webhook_data, epw.error_msg, epw.datep, epw.datec";
$parameters=array();
$reshook=$hookmanager->executeHooks('printFieldListSelect', $parameters);    // Note that $action and $object may have been modified by hook
$sqlselect.=$hookmanager->resPrint;
$sql= ' FROM '.MAIN_DB_PREFIX.'ecommerce_pending_webhooks AS epw';
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."ecommerce_site AS es on es.rowid = epw.site_id";
$sql.= ' WHERE 1 = 1';
if ($search_technical_id)           $sql .= " AND epw.rowid IN (".$db->escape($search_technical_id).')';
if ($search_site)       			$sql .= natural_search('es.name', $search_site);
if ($search_delivery_id)            $sql .= natural_search('epw.delivery_id', $search_delivery_id);
if ($search_webhook_id)             $sql .= natural_search("epw.webhook_id", $search_webhook_id);
if ($search_webhook_topic)          $sql .= natural_search("epw.webhook_topic", $search_webhook_topic);
if ($search_webhook_resource)       $sql .= natural_search('epw.webhook_resource', $search_webhook_resource);
if ($search_webhook_event)          $sql .= natural_search('epw.webhook_event', $search_webhook_event);
if ($search_webhook_signature)      $sql .= natural_search('epw.webhook_signature', $search_webhook_signature);
if ($search_webhook_source)         $sql .= natural_search('epw.webhook_source', $search_webhook_source);
if ($search_webhook_data)           $sql .= natural_search('epw.webhook_data', $search_webhook_data);
if ($search_error_msg)           	$sql .= natural_search('epw.error_msg', $search_error_msg);
if ($search_datep_start)            $sql .= " AND epw.datep >= '" . $db->idate($search_datep_start) . "'";
if ($search_datep_end)              $sql .= " AND epw.datep <= '" . $db->idate($search_datep_end) . "'";
if ($search_datec_start)     		$sql .= " AND epw.datec >= '" . $db->idate($search_datec_start) . "'";
if ($search_datec_end)       		$sql .= " AND epw.datec <= '" . $db->idate($search_datec_end) . "'";
if ($viewstatut != '' && $viewstatut != '-1') {
	$sql .= ' AND epw.status IN (' . $db->escape($viewstatut) . ')';
}
// Add where from hooks
$parameters=array();
$reshook=$hookmanager->executeHooks('printFieldListWhere', $parameters);    // Note that $action and $object may have been modified by hook
$sql.=$hookmanager->resPrint;

$sql.= $db->order($sortfield, $sortorder);

// Count total nb of records
$nbtotalofrecords = '';
if (empty($conf->global->MAIN_DISABLE_FULL_SCANLIST)) {
	$result = $db->query("SELECT COUNT(*) AS nb " . $sql);
	$nbtotalofrecords = 0;
	if ($result && $obj = $db->fetch_object($result)) $nbtotalofrecords = $obj->nb;

	if (($page * $limit) > $nbtotalofrecords) {    // if total resultset is smaller then paging size (filtering), goto and load page 0
		$page = 0;
		$offset = 0;
	}
}

$sql.= $db->plimit($limit+1, $offset);

$resql=$db->query($sqlselect . $sql);

if ($resql) {
	$objectstatic = new eCommercePendingWebHook($db);

	$title = $langs->trans('ECommerceListOfWebHooks');

	$num = $db->num_rows($resql);

	$arrayofselected = is_array($toselect) ? $toselect : array();

	llxHeader('', $langs->trans('ECommerceWebHooks'), $help_url);

	$param = '&viewstatut=' . urlencode($viewstatut);
	if (!empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) $param .= '&contextpage=' . urlencode($contextpage);
	if ($limit > 0 && $limit != $conf->liste_limit) $param .= '&limit=' . urlencode($limit);
	if ($search_technical_id) $param .= '&search_technical_id=' . urlencode($search_technical_id);
	if ($search_site > 0) $param .= '&search_site=' . urlencode($search_site);
	if ($search_delivery_id) $param .= '&search_delivery_id=' . urlencode($search_delivery_id);
	if ($search_webhook_id) $param .= '&search_webhook_id=' . urlencode($search_webhook_id);
	if ($search_webhook_topic) $param .= '&search_webhook_topic=' . urlencode($search_webhook_topic);
	if ($search_webhook_resource) $param .= '&search_webhook_resource=' . urlencode($search_webhook_resource);
	if ($search_webhook_event) $param .= '&search_webhook_event=' . urlencode($search_webhook_event);
	if ($search_webhook_signature) $param .= '&search_webhook_signature=' . urlencode($search_webhook_signature);
	if ($search_webhook_source) $param .= '&search_webhook_source=' . urlencode($search_webhook_source);
	if ($search_webhook_data) $param .= '&search_webhook_data=' . urlencode($search_webhook_data);
	if ($search_error_msg) $param .= '&search_error_msg=' . urlencode($search_error_msg);
	if ($search_datep_start) $param .= '&search_datep_start=' . urlencode($search_datep_start);
	if ($search_datep_end) $param .= '&search_datep_end=' . urlencode($search_datep_end);
	if ($search_datec_start) $param .= '&search_datec_start=' . urlencode($search_datec_start);
	if ($search_datec_end) $param .= '&search_datec_end=' . urlencode($search_datec_end);
	if ($optioncss != '') $param .= '&optioncss=' . urlencode($optioncss);

	// List of mass actions available
	$arrayofmassactions = array();
	if ($user->rights->ecommerceng->write) {
		$arrayofmassactions['pre_set_to_process'] = $langs->trans("ECommerceWebHooksSetToProcess");
		$arrayofmassactions['pre_set_processed'] = $langs->trans("ECommerceWebHooksSetProcessed");
	}
	if (in_array($massaction, array('pre_set_to_process', 'pre_set_processed'))) $arrayofmassactions = array();
	$massactionbutton = $form->selectMassAction('', $arrayofmassactions);

	$newcardbutton = '';

	// Fields title search
	print '<form method="POST" id="searchFormList" action="' . $_SERVER["PHP_SELF"] . '">';
	if ($optioncss != '') print '<input type="hidden" name="optioncss" value="' . $optioncss . '">';
	print '<input type="hidden" name="token" value="' . newToken() . '">';
	print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
	print '<input type="hidden" name="action" value="list">';
	print '<input type="hidden" name="sortfield" value="' . $sortfield . '">';
	print '<input type="hidden" name="sortorder" value="' . $sortorder . '">';
	print '<input type="hidden" name="page" value="' . $page . '">';
	print '<input type="hidden" name="contextpage" value="' . $contextpage . '">';

	print_barre_liste($title, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, 'commercial', 0, $newcardbutton, '', $limit);

	if ($massaction == 'pre_set_to_process') {
		print $form->formconfirm($_SERVER["PHP_SELF"], $langs->trans("ECommerceConfirmMassSetToProcess"), $langs->trans("ECommerceConfirmMassSetToProcessQuestion", count($toselect)), "confirm_set_to_process", null, '', 0, 200, 500, 1);
	} elseif ($massaction == 'pre_set_processed') {
		print $form->formconfirm($_SERVER["PHP_SELF"], $langs->trans("ECommerceConfirmMassSetProcessed"), $langs->trans("ECommerceConfirmMassSetProcessedQuestion", count($toselect)), "confirm_set_processed", null, '', 0, 200, 500, 1);
	}

	$i = 0;

	$moreforfilter = '';

	$parameters = array();
	$reshook = $hookmanager->executeHooks('printFieldPreListTitle', $parameters);    // Note that $action and $object may have been modified by hook
	if (empty($reshook)) $moreforfilter .= $hookmanager->resPrint;
	else $moreforfilter = $hookmanager->resPrint;

	if (!empty($moreforfilter)) {
		print '<div class="liste_titre liste_titre_bydiv centpercent">';
		print $moreforfilter;
		print '</div>';
	}

	$varpage = empty($contextpage) ? $_SERVER["PHP_SELF"] : $contextpage;
	$selectedfields = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage);    // This also change content of $arrayfields
	$selectedfields .= (count($arrayofmassactions) ? $form->showCheckAddButtons('checkforselect', 1) : '');

	print '<div class="div-table-responsive">';
	print '<table class="tagtable liste' . ($moreforfilter ? " listwithfilterbefore" : "") . '">' . "\n";

	print '<tr class="liste_titre_filter">';
	if (!empty($arrayfields['epw.rowid']['checked'])) {
		print '<td class="liste_titre">';
		print '<input class="flat" size="6" type="text" name="search_technical_id" value="' . dol_escape_htmltag($search_technical_id) . '">';
		print '</td>';
	}
	if (!empty($arrayfields['es.name']['checked'])) {
		print '<td class="liste_titre">';
		print '<input class="flat" size="6" type="text" name="search_site" value="' . dol_escape_htmltag($search_site) . '">';
		print '</td>';
	}
	if (!empty($arrayfields['epw.delivery_id']['checked'])) {
		print '<td class="liste_titre">';
		print '<input class="flat" size="6" type="text" name="search_delivery_id" value="' . dol_escape_htmltag($search_delivery_id) . '">';
		print '</td>';
	}
	if (!empty($arrayfields['epw.webhook_id']['checked'])) {
		print '<td class="liste_titre">';
		print '<input class="flat" size="6" type="text" name="search_webhook_id" value="' . dol_escape_htmltag($search_webhook_id) . '">';
		print '</td>';
	}
	if (!empty($arrayfields['epw.webhook_topic']['checked'])) {
		print '<td class="liste_titre">';
		print '<input class="flat" type="text" size="10" name="search_webhook_topic" value="' . dol_escape_htmltag($search_webhook_topic) . '">';
		print '</td>';
	}
	if (!empty($arrayfields['epw.webhook_resource']['checked'])) {
		print '<td class="liste_titre">';
		print '<input class="flat" type="text" size="10" name="search_webhook_resource" value="' . dol_escape_htmltag($search_webhook_resource) . '">';
		print '</td>';
	}
	if (!empty($arrayfields['epw.webhook_event']['checked'])) {
		print '<td class="liste_titre">';
		print '<input class="flat" type="text" size="10" name="search_webhook_event" value="' . dol_escape_htmltag($search_webhook_event) . '">';
		print '</td>';
	}
	if (!empty($arrayfields['epw.webhook_signature']['checked'])) {
		print '<td class="liste_titre">';
		print '<input class="flat" type="text" size="10" name="search_webhook_signature" value="' . dol_escape_htmltag($search_webhook_signature) . '">';
		print '</td>';
	}
	if (!empty($arrayfields['epw.webhook_source']['checked'])) {
		print '<td class="liste_titre">';
		print '<input class="flat" type="text" size="10" name="search_webhook_source" value="' . dol_escape_htmltag($search_webhook_source) . '">';
		print '</td>';
	}
	if (!empty($arrayfields['epw.webhook_data']['checked'])) {
		print '<td class="liste_titre">';
		print '<input class="flat" type="text" size="10" name="search_webhook_data" value="' . dol_escape_htmltag($search_webhook_data) . '">';
		print '</td>';
	}
	if (!empty($arrayfields['epw.error_msg']['checked'])) {
		print '<td class="liste_titre">';
		print '<input class="flat" type="text" size="10" name="search_error_msg" value="' . dol_escape_htmltag($search_error_msg) . '">';
		print '</td>';
	}

	// Fields from hook
	$parameters = array('arrayfields' => $arrayfields);
	$reshook = $hookmanager->executeHooks('printFieldListOption', $parameters);    // Note that $action and $object may have been modified by hook
	print $hookmanager->resPrint;
	// Date processed
	if (!empty($arrayfields['epw.datep']['checked'])) {
		print '<td class="liste_titre center">';
		print '<div class="nowrap">';
		print $langs->trans('From') . ' ';
		print $form->selectDate($search_datep_start ? $search_datep_start : -1, 'search_datep_start', 0, 0, 1);
		print '</div>';
		print '<div class="nowrap">';
		print $langs->trans('to') . ' ';
		print $form->selectDate($search_datep_end ? $search_datep_end : -1, 'search_datep_end', 0, 0, 1);
		print '</div>';
		print '</td>';
	}
	// Date creation
	if (!empty($arrayfields['epw.datec']['checked'])) {
		print '<td class="liste_titre center">';
		print '<div class="nowrap">';
		print $langs->trans('From') . ' ';
		print $form->selectDate($search_datec_start ? $search_datec_start : -1, 'search_datec_start', 0, 0, 1);
		print '</div>';
		print '<div class="nowrap">';
		print $langs->trans('to') . ' ';
		print $form->selectDate($search_datec_end ? $search_datec_end : -1, 'search_datec_end', 0, 0, 1);
		print '</div>';
		print '</td>';
	}
	// Status
	if (!empty($arrayfields['epw.status']['checked'])) {
		print '<td class="liste_titre maxwidthonsmartphone right">';
		print $form->selectarray('search_statut', $object->labelStatusShort, $viewstatut, 1, 0, 0, '', 1);
		print '</td>';
	}
	// Action column
	print '<td class="liste_titre center">';
	$searchpicto = $form->showFilterButtons();
	print $searchpicto;
	print '</td>';

	print "</tr>\n";

	// Fields title
	print '<tr class="liste_titre">';
	if (!empty($arrayfields['epw.rowid']['checked'])) print_liste_field_titre($arrayfields['epw.rowid']['label'], $_SERVER["PHP_SELF"], 'epw.rowid', '', $param, '', $sortfield, $sortorder);
	if (!empty($arrayfields['es.name']['checked'])) print_liste_field_titre($arrayfields['es.name']['label'], $_SERVER["PHP_SELF"], 'es.name', '', $param, '', $sortfield, $sortorder);
	if (!empty($arrayfields['epw.delivery_id']['checked'])) print_liste_field_titre($arrayfields['epw.delivery_id']['label'], $_SERVER["PHP_SELF"], 'epw.delivery_id', '', $param, '', $sortfield, $sortorder);
	if (!empty($arrayfields['epw.webhook_id']['checked'])) print_liste_field_titre($arrayfields['epw.webhook_id']['label'], $_SERVER["PHP_SELF"], 'epw.webhook_id', '', $param, '', $sortfield, $sortorder);
	if (!empty($arrayfields['epw.webhook_topic']['checked'])) print_liste_field_titre($arrayfields['epw.webhook_topic']['label'], $_SERVER["PHP_SELF"], 'epw.webhook_topic', '', $param, '', $sortfield, $sortorder);
	if (!empty($arrayfields['epw.webhook_resource']['checked'])) print_liste_field_titre($arrayfields['epw.webhook_resource']['label'], $_SERVER["PHP_SELF"], 'epw.webhook_resource', '', $param, '', $sortfield, $sortorder);
	if (!empty($arrayfields['epw.webhook_event']['checked'])) print_liste_field_titre($arrayfields['epw.webhook_event']['label'], $_SERVER["PHP_SELF"], 'epw.webhook_event', '', $param, '', $sortfield, $sortorder);
	if (!empty($arrayfields['epw.webhook_signature']['checked'])) print_liste_field_titre($arrayfields['epw.webhook_signature']['label'], $_SERVER["PHP_SELF"], "epw.webhook_signature", "", $param, '', $sortfield, $sortorder);
	if (!empty($arrayfields['epw.webhook_source']['checked'])) print_liste_field_titre($arrayfields['epw.webhook_source']['label'], $_SERVER["PHP_SELF"], "epw.webhook_source", "", $param, '', $sortfield, $sortorder);
	if (!empty($arrayfields['epw.webhook_data']['checked'])) print_liste_field_titre($arrayfields['epw.webhook_data']['label'], $_SERVER["PHP_SELF"], "epw.webhook_data", "", $param, '', $sortfield, $sortorder);
	if (!empty($arrayfields['epw.error_msg']['checked'])) print_liste_field_titre($arrayfields['epw.error_msg']['label'], $_SERVER["PHP_SELF"], 'epw.error_msg', '', $param, '', $sortfield, $sortorder);
	// Hook fields
	$parameters = array('arrayfields' => $arrayfields, 'param' => $param, 'sortfield' => $sortfield, 'sortorder' => $sortorder);
	$reshook = $hookmanager->executeHooks('printFieldListTitle', $parameters);    // Note that $action and $object may have been modified by hook
	print $hookmanager->resPrint;
	if (!empty($arrayfields['epw.datep']['checked'])) print_liste_field_titre($arrayfields['epw.datep']['label'], $_SERVER["PHP_SELF"], 'epw.datep', '', $param, '', $sortfield, $sortorder, "center nowrap ");
	if (!empty($arrayfields['epw.datec']['checked'])) print_liste_field_titre($arrayfields['epw.datec']['label'], $_SERVER["PHP_SELF"], "epw.datec", "", $param, '', $sortfield, $sortorder, "center nowrap ");
	if (!empty($arrayfields['epw.status']['checked'])) print_liste_field_titre($arrayfields['epw.status']['label'], $_SERVER["PHP_SELF"], "epw.status", "", $param, '', $sortfield, $sortorder, "right ");
	print_liste_field_titre($selectedfields, $_SERVER["PHP_SELF"], "", '', '', '', $sortfield, $sortorder, 'maxwidthsearch center ');
	print '</tr>' . "\n";

	$now = dol_now();
	$i = 0;
	$totalarray = array();
	while ($i < min($num, $limit)) {
		$obj = $db->fetch_object($resql);

		print '<tr class="oddeven">';

		// Technical ID
		if (!empty($arrayfields['epw.rowid']['checked'])) {
			print '<td class="nowrap">';
			print $obj->rowid;
			print '</td>';
			if (!$i) $totalarray['nbfield']++;
		}

		// Site name
		if (!empty($arrayfields['es.name']['checked'])) {
			print '<td class="nowrap tdoverflowmax200">';
			print $obj->site_name;
			print "</td>\n";
			if (!$i) $totalarray['nbfield']++;
		}

		// Delivery id
		if (!empty($arrayfields['epw.delivery_id']['checked'])) {
			print '<td class="nowrap">';
			print $obj->delivery_id;
			print '</td>';
			if (!$i) $totalarray['nbfield']++;
		}

		// WebHook id
		if (!empty($arrayfields['epw.webhook_id']['checked'])) {
			print '<td class="nowrap">';
			print $obj->webhook_id;
			print '</td>';
			if (!$i) $totalarray['nbfield']++;
		}

		// WebHook topic
		if (!empty($arrayfields['epw.webhook_topic']['checked'])) {
			print '<td class="nowrap">';
			print $obj->webhook_topic;
			print '</td>';
			if (!$i) $totalarray['nbfield']++;
		}

		// WebHook resource
		if (!empty($arrayfields['epw.webhook_resource']['checked'])) {
			print '<td class="nowrap">';
			print $obj->webhook_resource;
			print '</td>';
			if (!$i) $totalarray['nbfield']++;
		}

		// WebHook event
		if (!empty($arrayfields['epw.webhook_event']['checked'])) {
			print '<td class="nowrap">';
			print $obj->webhook_event;
			print '</td>';
			if (!$i) $totalarray['nbfield']++;
		}

		// WebHook signature
		if (!empty($arrayfields['epw.webhook_signature']['checked'])) {
			print '<td class="nowrap">';
			print $obj->webhook_signature;
			print '</td>';
			if (!$i) $totalarray['nbfield']++;
		}

		// WebHook source
		if (!empty($arrayfields['epw.webhook_source']['checked'])) {
			print '<td class="nowrap">';
			print $obj->webhook_source;
			print '</td>';
			if (!$i) $totalarray['nbfield']++;
		}

		// WebHook data
		if (!empty($arrayfields['epw.webhook_data']['checked'])) {
			print '<td class="nowrap tdoverflowmax200">';
			print $form->textwithtooltip(dol_escape_htmltag(dol_trunc($obj->webhook_data, 25)), dol_escape_htmltag($obj->webhook_data), 3, 1, '', '', 2, '', '', 'ttdata' . $obj->rowid);
			print '</td>';
			if (!$i) $totalarray['nbfield']++;
		}

		// Error message
		if (!empty($arrayfields['epw.error_msg']['checked'])) {
			print '<td>';
//			print '<td class="nowrap tdoverflowmax200">';
			print $obj->error_msg;
//			print $form->textwithtooltip(dol_trunc($obj->error_msg, 25), $obj->error_msg, 3, 1, '', '', 2, '', '', 'tterror' . $obj->rowid);
			print '</td>';
			if (!$i) $totalarray['nbfield']++;
		}

		// Fields from hook
		$parameters = array('arrayfields' => $arrayfields, 'obj' => $obj, 'i' => $i);
		$reshook = $hookmanager->executeHooks('printFieldListValue', $parameters);    // Note that $action and $object may have been modified by hook
		print $hookmanager->resPrint;

		// Date processed
		if (!empty($arrayfields['epw.datep']['checked'])) {
			print '<td class="nowrap center">';
			print dol_print_date($db->jdate($obj->datep), 'dayhour', 'tzuser');
			print "</td>\n";
			if (!$i) $totalarray['nbfield']++;
		}
		// Date creation
		if (!empty($arrayfields['epw.datec']['checked'])) {
			print '<td class="nowrap center">';
			print dol_print_date($db->jdate($obj->datec), 'dayhour', 'tzuser');
			print '</td>';
			if (!$i) $totalarray['nbfield']++;
		}
		// Status
		if (!empty($arrayfields['epw.status']['checked'])) {
			print '<td class="nowrap right">' . $objectstatic->LibStatut($obj->status, 5) . '</td>';
			if (!$i) $totalarray['nbfield']++;
		}
		// Action column
		print '<td class="nowrap center">';
		if ($massactionbutton || $massaction)   // If we are in select mode (massactionbutton defined) or if we have already selected and sent an action ($massaction) defined
		{
			$selected = 0;
			if (in_array($obj->rowid, $arrayofselected)) $selected = 1;
			print '<input id="cb' . $obj->rowid . '" class="flat checkforselect" type="checkbox" name="toselect[]" value="' . $obj->rowid . '"' . ($selected ? ' checked="checked"' : '') . '>';
		}
		print '</td>';
		if (!$i) $totalarray['nbfield']++;

		print "</tr>\n";

		$i++;
	}

	$db->free($resql);

	$parameters = array('arrayfields' => $arrayfields, 'sql' => $sql);
	$reshook = $hookmanager->executeHooks('printFieldListFooter', $parameters);    // Note that $action and $object may have been modified by hook
	print $hookmanager->resPrint;

	print '</table>' . "\n";
	print '</div>' . "\n";

	print '</form>' . "\n";
} else {
	dol_print_error($db);
}

// End of page
llxFooter();
$db->close();
