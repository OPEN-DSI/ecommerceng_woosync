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
 *  \file       ecommerceng/scripts/activate_sharing_product_images.php
 *  \ingroup    cron
 *  \brief      Active the sharing of the images of all the products
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
if (! isset($argv[3]) || $argv[3] === '') {
	print "Usage: " . $script_file . " user_login_in_dolibarr entity only_parent_category [images_extension]\n";
	print " only_parent_category: 0 (all) or id of the parent category (all product in this category and sub categories)\n";
	print " images_extension: images extension separate by comma (by default: png,jpg,jpeg,gif)\n";
    exit(-1);
}
$userlogin=$argv[1];
$entity=$argv[2];
$only_parent_category=$argv[3];
$images_extension=isset($argv[4]) ? $argv[4] : null;

// Change this following line to use the correct relative path (../, ../../, etc)
$res=0;
if (! $res && file_exists("../../master.inc.php")) $res=@include '../../master.inc.php';			// to work if your module directory is into a subdir of root htdocs directory
if (! $res && file_exists("../../../master.inc.php")) $res=@include '../../../master.inc.php';		// to work if your module directory is into a subdir of root htdocs directory
if (! $res) die("Include of main fails");
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
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
$siteDb->setEntityValues($entity);

$only_categories = array();
if ($only_parent_category > 0) {
	$cat = new Categorie($db);
	$all_cat = $cat->get_full_arbo($type);
	if (is_array($all_cat)) {
		foreach ($all_cat as $category) {
			if (preg_match('/_' . $only_parent_category . '(_|$)/', $category['fullpath'])) {
				$only_categories[$category['id']] = $category['id'];
			}
		}
	}
}

$sql = "SELECT p.rowid";
$sql.= " FROM " . MAIN_DB_PREFIX . "product AS p";
if (!empty($only_categories)) {
	$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "categorie_product AS cp ON cp.fk_product = p.rowid";
}
$sql.= " WHERE p.entity IN (" . getEntity('product') . ")";
if (!empty($only_categories)) {
	$sql .= " AND cp.fk_categorie IN (" . implode(',', $only_categories) . ")";
}

$resql = $db->query($sql);
if ($resql) {
	$idx_lines = 1;
	$max_lines = $db->num_rows($resql);

	if (empty($images_extension)) $images_extension = ['png', 'jpg', 'jpeg', 'gif'];
	$startTime = microtime(true);
	while ($obj = $db->fetch_object($resql)) {
		printStatus($idx_lines++, $max_lines);
		activateShareProductImages($db, $user, $obj->rowid, $images_extension);
	}

	$db->free($resql);
} else {
	print "\nError: SQL : $sql; Error: " . $db->lasterror() . "\n";
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

		$product_cached[$product_id] = $dbProduct;
	}

	return $product_cached[$product_id];
}

function errorsToString($object, $separator = ', ')
{
	return $object->error . (is_array($object->errors) ? (!empty($object->error) ? $separator : '') . join($separator, $object->errors) : '');
}

function printStatus($idx_lines, $max_lines)
{
	global $startTime;

	$percent = $idx_lines * 100 / $max_lines;
	$elapsedTime = microtime(true) - $startTime;
	$remainingTime = $percent > 0 ? $elapsedTime * (100 - $percent) / $percent : 0;
	print sprintf("\rStatus: Product: %6d / %6d - %3d%% - Elapsed: " . microTimeToTime($elapsedTime) . " - Remaining: " . microTimeToTime($remainingTime), $idx_lines, $max_lines, $percent);
}

function microTimeToTime($microtime)
{
    $hours = (int)($microtime / 60 / 60);
    $minutes = (int)($microtime / 60) - $hours * 60;
    $seconds = (int)$microtime - $hours * 60 * 60 - $minutes * 60;
    return sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
}

function activateShareProductImages($db, $user, $product_id, $extensions)
{
	global $conf;

	$extensions = is_array($extensions) ? $extensions : array_filter(array_map('trim', explode(',', $extensions)), 'strlen');
	$object = getProduct($product_id);

	if ($object->id == $product_id) {
		// Product - Images properties
		$entity = isset($object->entity) ? $object->entity : $conf->entity;
		if (!empty($conf->global->PRODUCT_USE_OLD_PATH_FOR_PHOTO)) {    // For backward compatiblity, we scan also old dirs
			if ($object->type == Product::TYPE_PRODUCT) {
				$dir = $conf->product->multidir_output[$entity] . '/' . substr(substr("000" . $object->id, -2), 1, 1) . '/' . substr(substr("000" . $object->id, -2), 0, 1) . '/' . $object->id . "/photos/";
			} else {
				$dir = $conf->service->multidir_output[$entity] . '/' . substr(substr("000" . $object->id, -2), 1, 1) . '/' . substr(substr("000" . $object->id, -2), 0, 1) . '/' . $object->id . "/photos/";
			}
		} else {
			if (version_compare(DOL_VERSION, "13.0.0") >= 0) {
				if ($object->type == Product::TYPE_PRODUCT) {
					$dir = $conf->product->multidir_output[$entity] . '/' . get_exdir(0, 0, 0, 1, $object, 'product') . '/';
				} else {
					$dir = $conf->service->multidir_output[$entity] . '/' . get_exdir(0, 0, 0, 1, $object, 'product') . '/';
				}
			} else {
				if ($object->type == Product::TYPE_PRODUCT) {
					$dir = $conf->product->multidir_output[$entity] . '/' . get_exdir(0, 0, 0, 0, $object, 'product') . dol_sanitizeFileName($object->ref) . '/';
				} else {
					$dir = $conf->service->multidir_output[$entity] . '/' . get_exdir(0, 0, 0, 0, $object, 'product') . dol_sanitizeFileName($object->ref) . '/';
				}
			}
		}
		// Defined relative dir to DOL_DATA_ROOT
		$relativedir = '';
		if ($dir) {
			$relativedir = preg_replace('/^' . preg_quote(DOL_DATA_ROOT, '/') . '/', '', $dir);
			$relativedir = preg_replace('/^[\\/]/', '', $relativedir);
		}

		// Build file list
		$filearray = dol_dir_list($dir, "files", 0, '', '(\.meta|_preview.*\.png)$', 'position_name', SORT_ASC, 1);
		if (!empty($conf->global->PRODUCT_USE_OLD_PATH_FOR_PHOTO)) {    // For backward compatiblity, we scan also old dirs
			$filearrayold = dol_dir_list($dir, "files", 0, '', '(\.meta|_preview.*\.png)$', 'position_name', SORT_ASC, 1);
			$filearray = array_merge($filearray, $filearrayold);
		}

		// Get list of files stored into database for same relative directory
		$relativedir = trim($relativedir, '/');
		if ($relativedir) {
			completeFileArrayWithDatabaseInfo($filearray, $relativedir);
			$filearray = dol_sort_array($filearray, 'position_name', 'ASC');
		}

		print " - Processing " . count($filearray) . " files of the product '{$object->ref}' (ID: {$object->id}).\n";

		foreach ($filearray as $key => $file) {
			if (!empty($file['share'])) continue;

			$file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);

			if (!in_array($file_extension, $extensions)) continue;

			$ecm_file_id = $file['rowid'];
			include_once DOL_DOCUMENT_ROOT . '/ecm/class/ecmfiles.class.php';
			$ecmfile = new EcmFiles($db);
			$result = $ecmfile->fetch($ecm_file_id);
			if ($result > 0) {
				require_once DOL_DOCUMENT_ROOT . '/core/lib/security2.lib.php';
				$ecmfile->share = getRandomPassword(true);
				$ecmfile->src_object_type = $object->element;
				$ecmfile->src_object_id = $object->id;
				$result = $ecmfile->update($user);
				if ($result < 0) {
					print "\nError: Set share of file '" . $file['fullname'] . "': " . $ecmfile->errorsToString() . "\n";
				}
			} else {
				print "\nError: Fetch ECM file '" . $file['fullname'] . "' (ID: " . $ecm_file_id . ") - " . $ecmfile->errorsToString() . "\n";
			}
		}
	} else {
		print "\nError: Product ID: " . $product_id . " - " . $object->errorsToString() . "\n";
	}
}

