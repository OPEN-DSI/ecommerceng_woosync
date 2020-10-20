<?php
/* Copyright (C) 2010 Franck Charpentier - Auguria <franck.charpentier@auguria.net>
 * Copyright (C) 2013 Laurent Destailleur          <eldy@users.sourceforge.net>
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


// Change this following line to use the correct relative path (../, ../../, etc)
$res=0;
if (! $res && file_exists("../main.inc.php")) $res=@include '../main.inc.php';			// to work if your module directory is into a subdir of root htdocs directory
if (! $res && file_exists("../../main.inc.php")) $res=@include '../../main.inc.php';		// to work if your module directory is into a subdir of root htdocs directory
if (! $res) die("Include of main fails");
include_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
dol_include_once("/ecommerceng/class/business/eCommerceSynchro.class.php");

$langs->load("ecommerce@ecommerceng");
$errors = array();
$success = array();
$warnings = array();
$site = null;

$nbCategoriesToUpdate=0;
$nbProductInDolibarr=0;
$nbSocieteInDolibarr = 0;
$nbCommandeInDolibarr = 0;
$nbFactureInDolibarr = 0;

$langs->load("admin");
$langs->load("ecommerce");

// Protection if external user
if ($user->societe_id > 0 || !$user->rights->ecommerceng->read) {
	accessforbidden();
}

$id = GETPOST('id','int');
$to_date = GETPOST('to_date','aZ09');
$from_date = GETPOST('from_date','aZ09');
$product_remote_ids = GETPOST('product_remote_ids','alpha');
$order_remote_ids = GETPOST('order_remote_ids','alpha');
$to_nb = GETPOST('to_nb','int');
$dtoe_to_date = GETPOST('dtoe_to_date','aZ09');
$dtoe_to_nb = GETPOST('dtoe_to_nb','int');

$error=0;


// Increase limit of time. Works only if we are not in safe mode
$ExecTimeLimit=3600;    // 60 mn
if (!empty($ExecTimeLimit)) {
	$err = error_reporting();
	error_reporting(0);     // Disable all errors
	//error_reporting(E_ALL);
	@set_time_limit($ExecTimeLimit);   // Need more than 240 on Windows 7/64
	error_reporting($err);
}

$MemoryLimit=0;
if (!empty($MemoryLimit)) {
	@ini_set('memory_limit', $MemoryLimit);
}

$conf->global->ECOMMERCENG_NO_COUNT_UPDATE = 1;

/*******************************************************************
* ACTIONS
********************************************************************/

if ($id) {
	try {
		$site = new eCommerceSite($db);
		$site->fetch($id);

		$site->cleanOrphelins();

		require_once(DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php');
//		$params = getSoapParams();
//		if (!empty($params['response_timeout'])) set_time_limit($params['response_timeout']);

		// Define date max (synch is done for element modified before)
		$fromDate = null;
		if (!empty($from_date)) $fromDate = dol_stringtotime($from_date);

		// Define date max (synch is done for element modified before)
		$toDate = null;
		if (!empty($to_date)) $toDate = dol_stringtotime($to_date);

		$toNb = 0;
		if ($to_nb == '') $to_nb = (empty($conf->global->ECOMMERCENG_MAXRECORD_PERSYNC) ? '' : $conf->global->ECOMMERCENG_MAXRECORD_PERSYNC);     // If '0', we keep 0
		if (!empty($to_nb)) $toNb = $to_nb;

		$toNbDToE = 0;
		if ($dtoe_to_nb == '') $dtoe_to_nb = (empty($conf->global->ECOMMERCENG_MAXRECORD_PERSYNC) ? '' : $conf->global->ECOMMERCENG_MAXRECORD_PERSYNC);     // If '0', we keep 0
		if (!empty($dtoe_to_nb)) $toNbDToE = $dtoe_to_nb;

		$synchro = new eCommerceSynchro($db, $site, $fromDate, $toDate, $toNb);          // $synchro->toDate will be set to dol_now if toDate no defined.

		dol_syslog("site.php Try to connect to eCommerce site " . $site->name);
		$synchro->connect();
		if (count($synchro->errors)) {
			$error++;
			setEventMessages($synchro->error, $synchro->errors, 'errors');
		}

		/*$result=0;

		if (! $error)
		{
		  $result=$synchro->checkAnonymous();
		}

		if ($result <= 0)
		{
		  $errors = $synchro->errors;
		  $errors[] = $synchro->error;
		  $error++;
		}*/

		//synch only with write rights
		if (!$error && $user->rights->ecommerceng->write && empty($const->global->ECOMMERCE_PROCESSING_WEBHOOK_SYNCHRONIZATION)) {
			if (in_array(GETPOST('reset_data'), array('categories_links', 'products_links', 'thirdparties_links', 'orders_links', 'invoices_links'))) {
				$synchro->dropImportedAndSyncData(0, GETPOST('reset_data'));
			}
			if (in_array(GETPOST('reset_data'), array('categories_all', 'products_all', 'thirdparties_all', 'orders_all', 'invoices_all'))) {
				$synchro->dropImportedAndSyncData(1, GETPOST('reset_data'));
			}
			if (GETPOST('reset_data') == 'links') {
				$synchro->dropImportedAndSyncData(0);
			}
			if (GETPOST('reset_data') == 'all') {
				$synchro->dropImportedAndSyncData(1);
			}

			if (GETPOST('submit_synchro_category') || GETPOST('submit_synchro_category_ajax') || GETPOST('submit_synchro_all')) {
				$result = $synchro->synchCategory($toNb);
				if ($result < 0) $error++;
			}
			if (GETPOST('submit_synchro_product') || GETPOST('submit_synchro_product_ajax') || GETPOST('submit_synchro_all')) {
				$result = $synchro->synchProduct(array_filter(array_map('trim', explode(',', $product_remote_ids)), 'strlen'), $toNb);
				if ($result < 0) $error++;
			}
			if (GETPOST('submit_synchro_societe') || GETPOST('submit_synchro_societe_ajax') || GETPOST('submit_synchro_all')) {
				$result = $synchro->synchSociete(array(), $toNb);
				if ($result < 0) $error++;
			}
			if (GETPOST('submit_synchro_commande') || GETPOST('submit_synchro_commande_ajax') || GETPOST('submit_synchro_all')) {
				$result = $synchro->synchCommande(array_filter(array_map('trim', explode(',', $order_remote_ids)), 'strlen'), $toNb);
				if ($result < 0) $error++;
			}
//			if (GETPOST('submit_synchro_facture') || GETPOST('submit_synchro_facture_ajax') || GETPOST('submit_synchro_all')) {
//				$result = $synchro->synchFacture($toNb);
//				if ($result < 0) $error++;
//			}

			if (GETPOST('submit_dtoe_synchro_category') || GETPOST('submit_dtoe_synchro_category_ajax') || GETPOST('submit_dtoe_synchro_all')) {
				$result = $synchro->synchDtoECategory($toNbDToE);
				if ($result < 0) $error++;
			}
			if (GETPOST('submit_dtoe_synchro_product') || GETPOST('submit_dtoe_synchro_product_ajax') || GETPOST('submit_dtoe_synchro_all')) {
				$result = $synchro->synchDtoEProduct($toNbDToE);
				if ($result < 0) $error++;
			}
		}


		dol_syslog("site.php Now we read only database to get counting information");


		/***************************************************
		 * Vars to build output tpl page
		 ****************************************************/

		// Count into Dolibarr
		$nbCategoriesInDolibarr = $synchro->getNbCategoriesInDolibarr(true);
		if ($nbCategoriesInDolibarr < 0) $error++;
		$nbCategoriesInDolibarrLinkedToE = $synchro->getNbCategoriesInDolibarrLinkedToE($site->fk_cat_product);

		$nbProductInDolibarr = $synchro->getNbProductInDolibarr();
		if ($nbProductInDolibarr < 0) $error++;
		$nbProductInDolibarrLinkedToE = $synchro->getNbProductInDolibarrLinkedToE();

		$nbSocieteInDolibarr = $synchro->getNbSocieteInDolibarr(true);
		if ($nbSocieteInDolibarr < 0) $error++;
		$nbSocieteInDolibarrLinkedToE = $synchro->getNbSocieteInDolibarrLinkedToE(true);

		if (!empty($conf->commande->enabled)) {
			$nbCommandeInDolibarr = $synchro->getNbCommandeInDolibarr();
			if ($nbCommandeInDolibarr < 0) $error++;
			$nbCommandeInDolibarrLinkedToE = $synchro->getNbCommandeInDolibarrLinkedToE();
		}
//		if (!empty($conf->facture->enabled)) {
//			$nbFactureInDolibarr = $synchro->getNbFactureInDolibarr(true);
//			if ($nbFactureInDolibarr < 0) $error++;
//			$nbFactureInDolibarrLinkedToE = $synchro->getNbFactureInDolibarrLinkedToE(true);
//		}

		// Count into Magento
		if (!GETPOST('test_with_no_categ_count')) {
			if (!$error && (empty($conf->global->ECOMMERCENG_NO_COUNT_UPDATE) || GETPOST('refresh'))) $nbCategoriesToUpdate = $synchro->getNbCategoriesToUpdate(true);
			else $nbCategoriesToUpdate = '?';
			if ($nbCategoriesToUpdate < 0) $error++;
		}
		if (!GETPOST('test_with_no_product_count')) {
			if (!$error && (empty($conf->global->ECOMMERCENG_NO_COUNT_UPDATE) || GETPOST('refresh'))) $nbProductToUpdate = $synchro->getNbProductToUpdate(true);
			else $nbProductToUpdate = '?';
			if ($nbProductToUpdate < 0) $error++;
		}
		if (!GETPOST('test_with_no_thirdparty_count')) {
			if (!$error && (empty($conf->global->ECOMMERCENG_NO_COUNT_UPDATE) || GETPOST('refresh'))) $nbSocieteToUpdate = $synchro->getNbSocieteToUpdate(true);
			else $nbSocieteToUpdate = '?';
			if ($nbSocieteToUpdate < 0) $error++;
		}
		if (!GETPOST('test_with_no_order_count')) {
			if (!empty($conf->commande->enabled)) {
				if (!$error && (empty($conf->global->ECOMMERCENG_NO_COUNT_UPDATE) || GETPOST('refresh'))) $nbCommandeToUpdate = $synchro->getNbCommandeToUpdate(true);
				else $nbCommandeToUpdate = '?';
				if ($nbCommandeToUpdate < 0) $error++;
			}
		}
//		if (!GETPOST('test_with_no_invoice_count')) {
//			if (!empty($conf->facture->enabled)) {
//				if (!$error && (empty($conf->global->ECOMMERCENG_NO_COUNT_UPDATE) || $action == 'refresh')) $nbFactureToUpdate = $synchro->getNbFactureToUpdate(true);
//				else $nbFactureToUpdate = '?';
//				if ($nbFactureToUpdate < 0) $error++;
//			}
//		}

		if ($nbCategoriesToUpdate == 0 && $nbProductToUpdate == 0 && $nbSocieteToUpdate == 0 && $nbCommandeToUpdate == 0 && $nbFactureToUpdate == 0
			&& !GETPOST('to_date', 'alpha')
			&& !GETPOST('test_with_no_categ_count') && !GETPOST('test_with_no_product_count') && !GETPOST('test_with_no_thirdparty_count')
			&& !GETPOST('test_with_no_order_count') && !GETPOST('test_with_no_invoice_count')
		) {
			$lastupdatedate = $synchro->toDate;
			// If there is at least one error, we take date less one second, so we are sure to not forget record next time (because we stop at first error
			// and we process in order of update_at)
			if ($error) $lastupdatedate = $lastupdatedate - 1;    // Remove 1 second
			$site->last_update = $synchro->toDate;
			$site->update($user);
		}

		// Count into Dolibarr not linked to ecommerce
		if (!$error) $nbCategoriesToUpdateDToE = $synchro->getNbCategoriesInDolibarrNotLinkedToE($site->fk_cat_product);
		else $nbCategoriesToUpdateDToE = '?';
		if ($nbCategoriesToUpdateDToE < 0) $error++;
		if (!$error) $nbProductToUpdateDToE = $synchro->getNbProductInDolibarrNotLinkedToE();
		else $nbProductToUpdateDToE = '?';
		if ($nbProductToUpdateDToE < 0) $error++;

		if ($user->rights->ecommerceng->write)
			$synchRights = true;                // Set permission ok for .tpl

		if (@count($synchro->success))
			$success = $synchro->success;

		if (@count($synchro->errors))
			$errors = $synchro->errors;

		if (@count($synchro->warnings))
			$warnings = $synchro->warnings;
	} catch (Exception $e) {
		$errors[] = $langs->trans('ECommerceSiteErrorConnect');
	}
}

/*
 * View
 */

if (GETPOST('submit_synchro_category_ajax') || GETPOST('submit_synchro_product_ajax') || GETPOST('submit_synchro_societe_ajax')
    || GETPOST('submit_synchro_commande_ajax') || GETPOST('submit_synchro_facture_ajax')
) {
	// Return ajax content
	// Not used yet
} else {
	// Return HTML page content
	$urltpl = dol_buildpath('/ecommerceng/tpl/site.tpl.php', 0);
	include($urltpl);
}

$db->close();
