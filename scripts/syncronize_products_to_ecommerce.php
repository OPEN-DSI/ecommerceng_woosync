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
 *  \file       scripts/osv_companies_categories.php
 *  \ingroup    cron
 *  \brief      Execute pendings jobs
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
    print 'Usage: ' . $script_file . ' user_login_in_dolibarr';
    exit(-1);
}
$userlogin=$argv[1];

// Change this following line to use the correct relative path (../, ../../, etc)
$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include '../../main.inc.php';			// to work if your module directory is into a subdir of root htdocs directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include '../../../main.inc.php';		// to work if your module directory is into a subdir of root htdocs directory
if (! $res) die("Include of main fails");
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
dol_include_once('/ecommerceng/class/data/eCommerceSite.class.php');
dol_include_once('/ecommerceng/class/business/eCommerceSynchro.class.php');
dol_include_once('/ecommerceng/class/data/eCommerceProduct.class.php');
dol_include_once('/ecommerceng/lib/eCommerce.lib.php');

// Global variables
$version=DOL_VERSION;
$product_cached = array();

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
$sites = $siteDb->listSites('object');

$max_sites = count($sites);
$num_sites = 0;

if ($max_sites > 0) {
	$startTime = microtime(true);

	foreach ($sites as $site) {
		print "Processing the site '{$site->name}'.\n";
		$num_sites++;
		$max_jobs = 0;
		$num_jobs = 0;

		$result = confSetEntityValues($db,$conf, $site->entity);
		if ($result < 0) {
			print "Error set entity to {$site->entity} : " . $db->lasterror() . "\n";
			continue;
		}
		$user->getrights();

		print "Connect to the site.\n";
		$eCommerceSynchro = new eCommerceSynchro($db, $site);
		$eCommerceSynchro->connect();
		if (count($eCommerceSynchro->errors)) {
			print "Warning: Connect to site fails: {$eCommerceSynchro->errorsToString()}.\n";
			continue;
		}

		print "Get all WooCommerce product categories.\n";
		$eCommerceSynchro->fetch_categories('product', $site->fk_cat_product);
		$woocommerce_product_categories = array_keys($eCommerceSynchro->cache_categories['product']);
		if (empty($woocommerce_product_categories)) {
			print "Warning: WooCommerce product categories not found.\n";
			continue;
		}

		$supported_warehouses = is_array($site->parameters['fk_warehouse_to_ecommerce']) ? $site->parameters['fk_warehouse_to_ecommerce'] : array();
		if (empty($supported_warehouses)) {
			print "Warning: Warehouses not configured.\n";
			continue;
		}

		$sql = "SELECT DISTINCT p.rowid AS product_id, IFNULL(ep.remote_id, '') as remote_id, ep.rowid AS link_id FROM " . MAIN_DB_PREFIX . "product as p" .
			" INNER JOIN " . MAIN_DB_PREFIX . "categorie_product as cp ON p.rowid = cp.fk_product AND cp.fk_categorie IN (" . implode(',', $woocommerce_product_categories) . ")" .
			" LEFT JOIN " . MAIN_DB_PREFIX . "ecommerce_product as ep ON p.rowid = ep.fk_product AND ep.fk_site=" . $site->id .
			" LEFT JOIN " . MAIN_DB_PREFIX . "product_extrafields as pf ON pf.fk_object = p.rowid" .
			" WHERE (ep.rowid IS NULL OR ep.last_update < p.tms OR (pf.tms IS NOT NULL AND ep.last_update < pf.tms))" .
			" AND p.entity IN (" . getEntity('product') . ")";

		$resql = $db->query($sql);
		if ($resql) {
			$eCommerceProduct = new eCommerceProduct($db);

			$ec_price_entities = explode(',', getEntity('productprice'));
			$max_jobs = $db->num_rows($resql);

			while ($obj = $db->fetch_object($resql)) {
				$remote_id = $obj->remote_id;
				$error = 0;

				// Get product
				if (!$error) {
					$dbProduct = getProduct($obj->product_id);
					if (!($dbProduct->id > 0)) {
						print "\nError: Get product (ID: {$obj->product_id}): " . errorsToString($dbProduct) . ".\n";
						$error++;
					} else {
						$dbProduct->context['ec_price_entities'] = $ec_price_entities;
					}
				}

				if (!$error) {
					$db->begin();

					if (empty($remote_id)) {
						// Create remote product
						$result = $eCommerceSynchro->eCommerceRemoteAccess->createRemoteProduct($dbProduct);
						if (!$result) {
							print "\nError: Create product (ID: {$obj->product_id}): " . errorsToString($eCommerceSynchro->eCommerceRemoteAccess) . ".\n";
							$error++;
						} else {
							$remote_id = $result['remote_id'];

							$object->url = $result['remote_url'];
							$object->context['fromsyncofecommerceid'] = $site->id;
							$result = $object->update($object->id, $user);
							if ($result < 0) {
								$error++;
								$error_msg = $langs->trans('ECommerceUpdateProduct');
								$this->errors[] = $error_msg;
								$this->errors = array_merge($this->errors, $object->errors);
								dol_syslog(__METHOD__ . ': Error:' . $error_msg, LOG_WARNING);
							}
						}
					} else {
						// Update remote product
						$result = $eCommerceSynchro->eCommerceRemoteAccess->updateRemoteProduct($obj->remote_id, $dbProduct);
						if (!$result) {
							print "\nError: Update product (ID: {$obj->product_id}, remote ID: {$obj->remote_id}): " . errorsToString($eCommerceSynchro->eCommerceRemoteAccess) . ".\n";
							$error++;
						}
					}

					if (!$error) {
						if (empty($obj->link_id)) {
							// Create product link
							$eCommerceProduct->remote_id = $remote_id;
							$eCommerceProduct->fk_site = $site->id;
							$eCommerceProduct->fk_product = $dbProduct->id;
							$eCommerceProduct->last_update = dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S');
							$result = $eCommerceProduct->create($user);
							if ($result < 0) {
								print "\nError: Create product link (ID: {$obj->product_id}): " . errorsToString($eCommerceProduct) . ".\n";
								$error++;
							}
						} else {
							// Update product link
							$result = $eCommerceProduct->fetch($obj->link_id);
							if ($result > 0) {
								$eCommerceProduct->last_update = dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S');
								$result = $eCommerceProduct->update($user);
							}
							if ($result < 0) {
								print "\nError: Update product link (ID: {$obj->product_id}): " . errorsToString($eCommerceProduct) . ".\n";
								$error++;
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
		} else {
			print "\nError: SQL : $sql; Error: " . $db->lasterror() . "\n";
			continue;
		}
	}
}

print "\nEnd.\n";

$db->close();

exit(0);

function getProduct($product_id)
{
	global $db, $product_cached;

	if (!isset($product_cached[$product_id])) {
		$dbProduct = new Product($db);
		$dbProduct->fetch($product_id);
		$dbProduct->load_stock();

		$product_cached[$product_id] = $dbProduct;
	}

	return $product_cached[$product_id];
}

function errorsToString($object, $separator = ', ')
{
	return $object->error . (is_array($object->errors) ? (!empty($object->error) ? $separator : '') . join($separator, $object->errors) : '');
}

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