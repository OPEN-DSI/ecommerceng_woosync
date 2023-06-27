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
 *  \file       scripts/check_movement_stock_dolibarr.php
 *  \ingroup    cron
 *  \brief      Execute check movement stock dolibarr for orders linked to the sites
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
if (! isset($argv[1]) || ! $argv[1] || ! isset($argv[2]) || ! $argv[2]) {
    print 'Usage: ' . $script_file . ' user_login_in_dolibarr from_order_date';
    exit(-1);
}
$userlogin=$argv[1];
$from_order_date=strtotime($argv[2]);
if (! isset($from_order_date)) {
	print 'Error: Wrong date format';
	exit(-1);
}

// Change this following line to use the correct relative path (../, ../../, etc)
$res=0;
if (! $res && file_exists("../../master.inc.php")) $res=@include '../../master.inc.php';			// to work if your module directory is into a subdir of root htdocs directory
if (! $res && file_exists("../../../master.inc.php")) $res=@include '../../../master.inc.php';		// to work if your module directory is into a subdir of root htdocs directory
if (! $res) die("Include of main fails");
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/stock/class/mouvementstock.class.php';
dol_include_once('/ecommerceng/class/data/eCommerceSite.class.php');
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

$siteDb = new eCommerceSite($db);
$sites = $siteDb->listSites('object', true);

$max_sites = count($sites);
$num_sites = 0;

if ($max_sites > 0) {
	$startTime = microtime(true);

	foreach ($sites as $site) {
		print "Processing the site '{$site->name}'.\n";
		$num_sites++;
		$max_jobs = 0;
		$num_jobs = 0;

		$result = $site->setEntityValues($site->entity);
		if ($result < 0) {
			print "Error set entity to {$site->entity} : " . $db->lasterror() . "\n";
			continue;
		}
		$langs->loadLangs(array('main', "agenda"));
		$user->getrights();

		if (empty($conf->stock->enabled)) {
			print "Warning: Stock disabled.\n";
			continue;
		}

		if (!empty($conf->global->STOCK_CALCULATE_ON_VALIDATE_ORDER) && $conf->global->STOCK_CALCULATE_ON_VALIDATE_ORDER == 1) {
			$warehouse_id = isset($site->parameters['order_actions']['valid_order_fk_warehouse']) && $site->parameters['order_actions']['valid_order_fk_warehouse'] > 0 ? $site->parameters['order_actions']['valid_order_fk_warehouse'] : 0;
			if (empty($warehouse_id)) {
				print "Warning: Warehouse on valid order not configured.\n";
				continue;
			}

			$sql = "SELECT DISTINCT c.rowid";
			$sql .= " FROM " . MAIN_DB_PREFIX . "commande AS c";
			$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "ecommerce_commande AS ec ON ec.fk_commande = c.rowid";
			$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "stock_mouvement AS sm ON sm.origintype = 'commande' AND sm.fk_origin = c.rowid";
			$sql .= " WHERE c.date_commande >= '" . $db->idate($from_order_date) . "'";
			$sql .= " AND c.fk_statut IN (" . Commande::STATUS_VALIDATED . ',' . Commande::STATUS_SHIPMENTONPROCESS . ',' . Commande::STATUS_CLOSED . ")";
			$sql .= " AND sm.rowid IS NULL";
			$sql .= " AND ec.rowid IS NOT NULL";
			$sql .= " AND ec.fk_site = " . $site->id;

			$resql = $db->query($sql);
			if (!$resql) {
				print "Error: SQL : $sql; Error: " . $db->lasterror() . "\n";
				continue;
			} else {
				$max_jobs = $db->num_rows($resql);
				print "Processing the {$max_jobs} orders found.\n";

				while ($obj = $db->fetch_object($resql)) {
					// Get order
					$order = new Commande($db);
					$result = $order->fetch($obj->rowid);
					if ($result <= 0) {
						print "\nError: Fetch order ID {$obj->rowid} : " . $order->errorsToString() . "\n";
						continue;
					} else {
						$error = 0;
						$db->begin();

						foreach ($order->lines as $i => $line) {
							if ($line->fk_product > 0) {
								$mouvP = new MouvementStock($db);
								$mouvP->origin = &$order;
								// We decrement stock of product (and sub-products)
								$result = $mouvP->livraison($user, $line->fk_product, $warehouse_id, $line->qty, $line->subprice, $langs->trans("OrderValidatedInDolibarr", $order->ref));
								if ($result < 0) {
									$error++;
									print "\nError: Insert stock movement for order line ID {$line->id} : " . $mouvP->errorsToString() . "\n";
									break;
								}
							}
						}

						if ($error) {
							$db->rollback();
						} else {
							$db->commit();
						}
					}

					$num_jobs++;
					printStatus($num_sites, $max_sites, $num_jobs, $max_jobs);
				}

				$db->free($resql);
			}
		} else {
			print "Warning: No decrement stock on valid supported for the current configuration.\n";
			continue;
		}
	}
}

print "\nEnd.\n";

$db->close();

exit(0);

function printStatus($num_sites, $max_sites, $num_jobs, $max_jobs)
{
	global $startTime;

	$sub_percent = $num_sites * 100 / $max_sites;
	$percent = $num_jobs * $sub_percent / $max_jobs;
	$elapsedTime = microtime(true) - $startTime;
	$remainingTime = $percent > 0 ? $elapsedTime * (100 - $percent) / $percent : 0;
	print sprintf("\rStatus: Site: %2d / %2d - Product: %6d / %6d - %3d%% - Elapsed: " . microTimeToTime($elapsedTime) . " - Remaining: " . microTimeToTime($remainingTime), $num_sites, $max_sites, $num_jobs, $max_jobs, $percent);
}

function microTimeToTime($microtime)
{
    $hours = (int)($microtime / 60 / 60);
    $minutes = (int)($microtime / 60) - $hours * 60;
    $seconds = (int)$microtime - $hours * 60 * 60 - $minutes * 60;
    return sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
}