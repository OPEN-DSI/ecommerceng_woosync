#!/usr/bin/env php
<?php
/* Copyright (C) 2012      Nicolas Villa aka Boyquotes http://informetic.fr
 * Copyright (C) 2013      Florian Henry <forian.henry@open-concept.pro
 * Copyright (C) 2013-2015 Laurent Destailleur <eldy@users.sourceforge.net>
 * Copyright (C) 2019      Open-Dsi <support@open-dsi.fr>
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
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
dol_include_once('/ecommerceng/class/data/eCommerceSite.class.php');

// Global variables
$version=DOL_VERSION;
$error=0;

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

$categories = new Categorie($db);
$siteDb = new eCommerceSite($db);
$sites = $siteDb->listSites('object');

if (count($sites)) {
    $woocommerce_product_categories = array();
    $warehouses = array();
    $max_jobs = 0;
    $num_jobs = 0;
    $startTime = microtime(true);

    print "Get all WooCommerce product categories.\n";
    $all_cat_full_arbo = $categories->get_full_arbo('product');
    foreach ($sites as $site) {
        if ($site->fk_warehouse > 0) {
            if (!isset($warehouses[$site->fk_warehouse])) $warehouses[$site->fk_warehouse] = array('fk_warehouse' => $site->fk_warehouse, 'names' => array());
            $warehouses[$site->fk_warehouse]['names'][$site->name] = $site->name;
        }
        if ($site->fk_cat_product > 0) {
            $woocommerce_product_categories[$site->fk_cat_product] = $site->fk_cat_product;

            foreach($all_cat_full_arbo as $cat_infos) {
                if (preg_match("/^{$site->fk_cat_product}$/", $cat_infos['fullpath']) || preg_match("/^{$site->fk_cat_product}_/", $cat_infos['fullpath']) ||
                    preg_match("/_{$site->fk_cat_product}_/", $cat_infos['fullpath']) || preg_match("/_{$site->fk_cat_product}$/", $cat_infos['fullpath'])) {
                    $woocommerce_product_categories[$cat_infos['id']] = $cat_infos['id'];
                }
            }
        }
    }

    if (count($woocommerce_product_categories) == 0) {
        print "WooCommerce product categories not found.\n";
        $db->close();
        exit(0);
    }

    print "Begin.\n";
    $nb_warehouses = count($warehouses);
    if ($nb_warehouses) {
        $sql = "SELECT DISTINCT cp.fk_product";
        $sql .= " FROM " . MAIN_DB_PREFIX . "categorie_product as cp";
        $sql .= " WHERE cp.fk_categorie IN (" . implode(',', $woocommerce_product_categories) . ")";

        $resql = $db->query($sql);
        if ($resql) {
            $max_jobs = $db->num_rows($resql) * $nb_warehouses;

            while ($obj = $db->fetch_object($resql)) {
                $product_id = $obj->fk_product;

                $product = new Product($db);
                $res = $product->fetch($product_id);
                if ($res > 0) {
                    foreach ($warehouses as $warehouse) {
                        $warehouse_id = $warehouse['fk_warehouse'];
                        $site_name = implode(', ', $warehouses[$warehouse_id]['names']);

                        $res = $product->correct_stock($user, $warehouse_id, 0, 0, "Synchronisation forcée du stock du produit sur le site $site_name.");
                        if ($res < 0) {
                            print "\nError correct stock - product (ID: $product_id) - warehouse (ID: $warehouse_id) - site (Nom: $site_name) : " . $product->errorsToString() . "\n";
                            $db->close();
                            exit(0);
                        }
                        $num_jobs++;
                        $percent = $num_jobs * 100 / $max_jobs;
                        $elapsedTime = microtime(true) - $startTime;
                        $remainingTime = $elapsedTime * (100 - $percent) / $percent;
                        print sprintf("\rStatus: %6d / %6d - %3d%% - Elapsed: " . microTimeToTime($elapsedTime) . " - Remaining: " . microTimeToTime($remainingTime), $num_jobs, $max_jobs, $percent);
                    }
                } elseif ($res == 0) {
                    print "\nError product (ID: $product_id) not found\n";
                    $db->close();
                    exit(0);
                } else {
                    print "\nError fetch product (ID: $product_id) : " . $product->errorsToString() . "\n";
                    $db->close();
                    exit(0);
                }
            }

            $db->free($result);
        } else {
            print "\nError: SQL : $sql; Error: " . $db->lasterror() . "\n";
            $db->close();
            exit(0);
        }
    }
}

print "\nEnd.\n";

$db->close();

exit(0);

function microTimeToTime($microtime)
{
    $hours = (int)($microtime / 60 / 60);
    $minutes = (int)($microtime / 60) - $hours * 60;
    $seconds = (int)$microtime - $hours * 60 * 60 - $minutes * 60;
    return sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
}