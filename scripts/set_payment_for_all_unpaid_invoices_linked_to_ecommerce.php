#!/usr/bin/env php
<?php
/* Copyright (C) 2019      Open-Dsi <support@open-dsi.fr>
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
 *  \file       scripts/set_payment_for_all_unpaid_invoices_linked_to_ecommerce.php
 *  \ingroup    cron
 *  \brief      Execute set payment for all unpaid invoices linked to ecommerce
 */
if (! defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL','1'); // Disables token renewal
if (! defined('NOREQUIREMENU'))  define('NOREQUIREMENU','1');
if (! defined('NOREQUIREHTML'))  define('NOREQUIREHTML','1');
if (! defined('NOREQUIREAJAX'))  define('NOREQUIREAJAX','1');
if (! defined('NOLOGIN'))        define('NOLOGIN','1');
//if (! defined('NOREQUIRETRAN'))  define('NOREQUIRETRAN','1');


$sapi_type = php_sapi_name();
$script_file = basename(__FILE__);
$path=dirname(__FILE__).'/';

// Test if batch mode
if (substr($sapi_type, 0, 3) == 'cgi') {
	echo "Error: You are using PHP for CGI. To execute ".$script_file." from command line, you must use PHP for CLI mode.\n";
	exit(-1);
}

// Check parameters
if (! isset($argv[1]) || ! $argv[1]) {
    print 'Usage: ' . $script_file . ' user_login_in_dolibarr [chunk_size(Default: 100)]';
    exit(-1);
}
$userlogin=$argv[1];
$chunk_size = isset($argv[2]) && $argv[2] > 0 ? $argv[2] : 100;

// Change this following line to use the correct relative path (../, ../../, etc)
$res=0;
if (! $res && file_exists("../../master.inc.php")) $res=@include '../../master.inc.php';			// to work if your module directory is into a subdir of root htdocs directory
if (! $res && file_exists("../../../master.inc.php")) $res=@include '../../../master.inc.php';		// to work if your module directory is into a subdir of root htdocs directory
if (! $res) die("Include of main fails");
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/compta/paiement/class/paiement.class.php';
dol_include_once('/ecommerceng/class/data/eCommerceSite.class.php');
dol_include_once('/ecommerceng/class/business/eCommerceSynchro.class.php');
dol_include_once('/ecommerceng/lib/eCommerce.lib.php');

// Global variables
$version=DOL_VERSION;

/*
 * Main
 */

// current date
$now=dol_now();

@set_time_limit(0);
print "***** ".$script_file." (".$version.") pid=".dol_getmypid()." ***** userlogin=" . $userlogin . " ***** " . $now . " *****\n";
print "\n";

$user = new User($db);
$res = $user->fetch('', $userlogin);
if ($res == 0) {
    print "Error user (Login: $userlogin) not found\n";
    $db->close();
    exit(0);
} elseif ($res < 0) {
    print "Error fetch user (Login: $userlogin) : " . $user->errorsToString() . "\n";
    $db->close();
    exit(0);
}

//------------------------------------------------
// Get unpaid invoices linked to a site
//------------------------------------------------
print "Get all unpaid invoices linked to a site.\n";
$remote_invoices = array();
$sql = "SELECT DISTINCT f.rowid, f.ref_ext";
$sql .= " FROM " . MAIN_DB_PREFIX . "facture as f";
$sql .= " WHERE f.paye = 0";
$sql .= " AND f.fk_statut > 0";
$sql .= " AND f.ref_ext REGEXP '^eCommerce\\-'";

$resql = $db->query($sql);
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		if (preg_match('/^eCommerce\-(\d+)\-(\d+)/', $obj->ref_ext, $matches)) {
			$site_id = $matches[1];
			$remote_id = $matches[2];
			$remote_invoices[$site_id][$obj->rowid] = $remote_id;
		}
	}
} else {
	print "\nError: SQL : $sql; Error: " . $db->lasterror() . "\n";
}

print "Process unpaid invoices linked to a site.\n";
if (!empty($remote_invoices)) {
	$max_sites = count($remote_invoices);
	$num_sites = 0;
	$startTime = microtime(true);

	foreach ($remote_invoices as $site_id => $invoices) {
		$num_sites++;
		$max_jobs = count($invoices);
		$num_jobs = 0;

		printStatus($num_sites, $max_sites, $num_jobs, $max_jobs);

		$site = new eCommerceSite($db);
		$result = $site->fetch($site_id);
		if ($result < 0) {
			print "\nWarning: Fetch to site (ID: $site_id) fails: " . errorsToString($site) . ".\n";
			continue;
		}

		$result = $site->setEntityValues($site->entity);
		if ($result < 0) {
			print "Error set entity to {$site->entity} : " . $db->lasterror() . "\n";
			continue;
		}
		$user->getrights();

		$eCommerceSynchro = new eCommerceSynchro($db, $site);
		$eCommerceSynchro->connect();
		if (count($eCommerceSynchro->errors)) {
			print "\nWarning: Connect to site '{$site->name}' fails: {$eCommerceSynchro->errorsToString()}.\n";
			continue;
		}

		$result = $eCommerceSynchro->loadPaymentGateways();
		if ($result < 0) {
			print "\nWarning: Load payment gateways for site '{$site->name}' fails: " . errorsToString($site) . ".\n";
			continue;
		}

		// Get remote invoices by bulk
		$invoice_chunked = array_chunk($invoices, $chunk_size, true);
		foreach ($invoice_chunked as $bulk_ids) {
			$remote_ids = array_values($bulk_ids);
			$invoice_ids = array_flip($bulk_ids);

			printStatus($num_sites, $max_sites, $num_jobs, $max_jobs);

			// Get remote invoices data
			$orders_data = $eCommerceSynchro->eCommerceRemoteAccess->convertRemoteObjectIntoDolibarrCommande(null, null, $remote_ids);
			if ($orders_data === false) {
				print "\nError: Get remote invoices data for site '{$site->name}' (remotes ID: " . implode(', ', $remote_ids) . "): " . errorsToString($eCommerceSynchro->eCommerceRemoteAccess) . ".\n";
				$num_jobs += count($bulk_ids);
				continue;
			}

			// Process remote invoice
			foreach ($orders_data as $order_data) {
				printStatus($num_sites, $max_sites, $num_jobs, $max_jobs);
				$num_jobs++;

				$remote_id = $order_data['remote_id'];
				if (!isset($invoice_ids[$remote_id])) {
					print "\nError: invoice ID not found for  site '{$site->name}' and remote id: {$remote_id}.\n";
					continue;
				}

				// Fetch invoice
				$invoice = new Facture($db);
				$result = $invoice->fetch($invoice_ids[$remote_id]);
				if ($result < 0) {
					print "\nError: Fetch to invoice for site '{$site->name}' (ID: {$invoice_ids[$remote_id]}) fails: {$invoice->errorsToString()}.\n";
					continue;
				}

				// Set amount paid warning
				if (price2num($order_data['payment_amount_ttc']) != price2num($invoice->total_ttc)) {
					print "\nWarning: Wrong invoice amount TTC with amount paid (Ref: {$invoice->ref}, Invoice: {$invoice->total_ttc}, Paid: {$order_data['payment_amount_ttc']}).\n";
				}

				// Creation of payment line
				if ($invoice->total_ttc != 0) {
					$selected_payment_gateways = array();

					// Get payment gateways
					if (!empty($order_data['payment_method_id'])) {
						if (isset($eCommerceSynchro->payment_gateways_cached[$order_data['payment_method_id']])) {
							$selected_payment_gateways = $eCommerceSynchro->payment_gateways_cached[$order_data['payment_method_id']];
						} else {
							print "\nWarning: Payment gateway not found for payment '{$order_data['payment_method']}' (ID: {$order_data['payment_method_id']}, Ref: {$invoice->ref}).\n";
							continue;
						}
					} else {
						print "\nWarning: No payment gateway for invoice (Ref: {$invoice->ref}).\n";
					}

					if (!$error && $conf->banque->enabled && !empty($selected_payment_gateways['create_invoice_payment'])) {
						$payment = new Paiement($db);
						$payment->datepaye = $invoice->date;
						$payment->amounts = array($invoice->id => $invoice->total_ttc);   // Array with all payments dispatching with invoice id
						$payment->multicurrency_amounts = array();   // Array with all payments dispatching
						$payment->paiementid = $invoice->mode_reglement_id;
						$payment->num_paiement = '';
						$payment->note = 'Created by WooSync';

						$error = 0;
						$db->begin();

						$payment_id = $payment->create($user, 1);
						if ($payment_id < 0) {
							print "\nError: Create payment for invoice '{$invoice->ref}' fails: {$payment->errorsToString()}.\n";
							$error++;
						} else {
							$result = $payment->addPaymentToBank($user, 'payment', '(CustomerInvoicePayment)', $invoice->fk_account, '', '');
							if ($result < 0) {
								print "\nError: Add payment to bank for invoice '{$invoice->ref}' fails: {$payment->errorsToString()}.\n";
								$error++;
							}
						}

						if ($error) {
							$db->rollback();
						} else {
							$db->commit();
						}
					}
				} else {
					$result = $invoice->set_paid($user);
					if ($result < 0) {
						print "\nError: Set invoice '{$invoice->ref}' paid fails: {$invoice->errorsToString()}.\n";
						continue;
					}
				}

				printStatus($num_sites, $max_sites, $num_jobs, $max_jobs);
			}
		}

		printStatus($num_sites, $max_sites, $num_jobs, $max_jobs);
	}
}

print "\nEnd.\n";

$db->close();

exit(0);

function errorsToString($object, $separator = ', ')
{
	return $object->error . (is_array($object->errors) ? (!empty($object->error) ? $separator : '') . join($separator, $object->errors) : '');
}

function printStatus($num_sites, $max_sites, $num_jobs, $max_jobs)
{
	global $startTime;

	$sub_percent = max(0, $num_sites - 1) * 100 / $max_sites;
	$percent = $sub_percent + ($num_jobs * 100 / $max_jobs) / $max_sites;
	$elapsedTime = microtime(true) - $startTime;
	$remainingTime = $percent > 0 ? $elapsedTime * (100 - $percent) / $percent : 0;
	print sprintf("\rStatus: Site: %2d / %2d - Invoice: %6d / %6d - %3d%% - Elapsed: " . microTimeToTime($elapsedTime) . " - Remaining: " . microTimeToTime($remainingTime), $num_sites, $max_sites, $num_jobs, $max_jobs, $percent);
}

function microTimeToTime($microtime)
{
    $hours = (int)($microtime / 60 / 60);
    $minutes = (int)($microtime / 60) - $hours * 60;
    $seconds = (int)$microtime - $hours * 60 * 60 - $minutes * 60;
    return sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
}