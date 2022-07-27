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
if (! $res && file_exists("../../master.inc.php")) $res=@include '../../master.inc.php';			// to work if your module directory is into a subdir of root htdocs directory
if (! $res && file_exists("../../../master.inc.php")) $res=@include '../../../master.inc.php';		// to work if your module directory is into a subdir of root htdocs directory
if (! $res) die("Include of main fails");
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/stock/class/mouvementstock.class.php';
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

$movement_stock_static = new MouvementStock($db);
$categories = new Categorie($db);
$siteDb = new eCommerceSite($db);

$all_cat_full_arbo = $categories->get_full_arbo('product');
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

		$result = $site->setEntityValues($site->entity);
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
		$woocommerce_product_categories = array();
		if ($site->fk_cat_product > 0) {
			$woocommerce_product_categories[$site->fk_cat_product] = $site->fk_cat_product;
			foreach ($all_cat_full_arbo as $cat_infos) {
				if (preg_match("/^{$site->fk_cat_product}$/", $cat_infos['fullpath']) || preg_match("/^{$site->fk_cat_product}_/", $cat_infos['fullpath']) ||
					preg_match("/_{$site->fk_cat_product}_/", $cat_infos['fullpath']) || preg_match("/_{$site->fk_cat_product}$/", $cat_infos['fullpath'])) {
					$woocommerce_product_categories[$cat_infos['id']] = $cat_infos['id'];
				}
			}
		}
		if (empty($woocommerce_product_categories)) {
			print "Warning: WooCommerce product categories not found.\n";
			continue;
		}

		$supported_warehouses = is_array($site->parameters['fk_warehouse_to_ecommerce']) ? $site->parameters['fk_warehouse_to_ecommerce'] : array();
		if (empty($supported_warehouses)) {
			print "Warning: Warehouses not configured.\n";
			continue;
		}

		$sql = "SELECT DISTINCT cp.fk_product AS product_id";
		$sql .= " FROM " . MAIN_DB_PREFIX . "categorie_product as cp";
		$sql .= " WHERE cp.fk_categorie IN (" . implode(',', $woocommerce_product_categories) . ")";

		$resql = $db->query($sql);
		if ($resql) {
			$max_jobs = $db->num_rows($resql);
			while ($obj = $db->fetch_object($resql)) {
				$error = 0;

				// Get product link
				$eCommerceProduct = new eCommerceProduct($db);
				$result = $eCommerceProduct->fetchByProductId($obj->product_id, $site->id);
				if ($result < 0) {
					print "\nError: Get product link (ID: {$obj->product_id}): " . errorsToString($eCommerceProduct) . ".\n";
					$error++;
				} elseif (empty($eCommerceProduct->remote_id)) {
					print "\nError: Get product remote ID (ID: {$obj->product_id}).\n";
					$error++;
				}

				// Get product
				if (!$error) {
					$dbProduct = getProduct($obj->product_id);
					if (!($dbProduct->id > 0)) {
						print "\nError: Get product (ID: {$obj->product_id}): " . errorsToString($dbProduct) . ".\n";
						$error++;
					} else {
						$movement_stock_static->qty_after = 0;
						foreach ($supported_warehouses as $warehouse_id) {
							$movement_stock_static->qty_after += isset($dbProduct->stock_warehouse[$warehouse_id]->real) ? $dbProduct->stock_warehouse[$warehouse_id]->real : 0;
						}
					}
				}

				if (!$error) {
					$db->begin();

					// Update remote product stock
					$result = $eCommerceSynchro->eCommerceRemoteAccess->updateRemoteStockProduct($eCommerceProduct->remote_id, $movement_stock_static, $dbProduct);
					if (!$result) {
						print "\nError: Update product stock (ID: {$obj->product_id}, remote ID: {$eCommerceProduct->remote_id}): " . errorsToString($eCommerceSynchro->eCommerceRemoteAccess) . ".\n";
						$error++;
					}

					// Update product link
					if (!$error) {
						$eCommerceProduct->last_update = dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S');
						$result = $eCommerceProduct->update($user);
						if ($result < 0) {
							print "\nError: Update product link (ID: {$obj->product_id}): " . errorsToString($eCommerceProduct) . ".\n";
							$error++;
						}
					}

					if ($error) {
						$db->rollback();
						$db->close();
						exit(0);
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