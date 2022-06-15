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
if (!isset($argv[3]) || !in_array($argv[3], array('categories', 'products', 'thirdparties', 'orders')) || !isset($argv[5]) || !$argv[5]) {
    print 'Usage: ' . $script_file . ' user_login_in_dolibarr site_id (categories|products|thirdparties|orders) id_from id_to';
    exit(-1);
}
$userlogin=$argv[1];
$site_id=$argv[2];
$type=$argv[3];
$id_from=$argv[4];
$id_to=$argv[5];

// Change this following line to use the correct relative path (../, ../../, etc)
$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include '../../main.inc.php';			// to work if your module directory is into a subdir of root htdocs directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include '../../../main.inc.php';		// to work if your module directory is into a subdir of root htdocs directory
if (! $res) die("Include of main fails");
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
$error = 0;
$nb_by_request = 100;

@set_time_limit(0);
print "***** ".$script_file." (".$version.") pid=".dol_getmypid()." ***** userlogin=" . $userlogin . " ***** " . $now . " *****\n";
print "\n";

$site = new eCommerceSite($db);
$result = $site->fetch($site_id);
if ($result < 0) {
	print "Error fetch site (ID: $site_id) : " . dol_htmlentitiesbr_decode(errorsToString($site)) . "\n";
	$error++;
} elseif ($result == 0) {
	print "Error fetch site (ID: $site_id) not found\n";
	$error++;
}


if (!$error) {
	$result = confSetEntityValues($db,$conf, $site->entity);
	if ($result < 0) {
		print "Error set entity to {$site->entity} : " . $db->lasterror() . "\n";
		$error++;
	}
}

if (!$error) {
	$user = new User($db);
	$res = $user->fetch('', $userlogin);
	if ($res == 0) {
		print "Error user (Login: $userlogin) not found\n";
		$error++;
	} elseif ($res < 0) {
		print "Error fetch user (Login: $userlogin) : " . dol_htmlentitiesbr_decode($user->errorsToString()) . "\n";
		$error++;
	} else {
		$user->getrights();
	}
}

if (!$error) {
	$site->cleanOrphelins();
	$site->cleanDuplicatesRemoteID();

	$synchro = new eCommerceSynchro($db, $site);

	$result = $synchro->connect();
	if ($result < 0) {
		print "Error connect site (ID: {$site->name}) : " . dol_htmlentitiesbr_decode($synchro->errorsToString()) . "\n";
		$error++;
	}

	if (!$user->rights->ecommerceng->write) {
		print "Error unauthorized access write\n";
		$error++;
	}

	if (!empty($conf->global->ECOMMERCE_PROCESSING_WEBHOOK_SYNCHRONIZATION)) {
		print "Error - " . $langs->transnoentitiesnoconv("ECommerceProcessingWebHooksSynchronisationSince") . ': ' . $conf->global->ECOMMERCE_PROCESSING_WEBHOOK_SYNCHRONIZATION . "\n";
		$error++;
	}

	if (!$error) {
		$group_request_ids = array();
		$current_group = array();
		if ($type != 'categories') {
			for ($idx = $id_from; $idx <= $id_to; $idx++) {
				if ($idx % $nb_by_request == 0 && !empty($current_group)) {
					$group_request_ids[] = $current_group;
					$current_group = array();
				}
				$current_group[] = $idx;
			}
			if (!empty($current_group)) {
				$group_request_ids[] = $current_group;
				$current_group = array();
			}
		}

		$max_jobs = $id_to - $id_from;
		$num_jobs = 0;
		$nb_synchronized = 0;
		$startTime = microtime(true);

		if ($type == 'categories') {
			print "Synchronize the categories\n";
			printStatus(0, 1);
			$result = $synchro->synchCategory();
			if ($result < 0) {
				$error++;
			} else {
				$nb_synchronized += $result;
			}
			printStatus(1, 1, $nb_synchronized);
		} elseif ($type == 'products') {
			print "Synchronize the products\n";
			printStatus(0, $max_jobs);
			foreach ($group_request_ids as $request_ids) {
				$result = $synchro->synchProduct($request_ids, $nb_by_request);
				if ($result < 0) {
					$nb_synchronized += $result - 1;
					$error++;
					break;
				} else {
					$nb_synchronized += $result;
				}
				printStatus(++$num_jobs * $nb_by_request, $max_jobs, $nb_synchronized);
			}
		} elseif ($type == 'thirdparties') {
			print "Synchronize the thirdparties\n";
			printStatus(0, $max_jobs);
			foreach ($group_request_ids as $request_ids) {
				$result = $synchro->synchSociete($request_ids, $nb_by_request);
				if ($result < 0) {
					$nb_synchronized += $result - 1;
					$error++;
					break;
				} else {
					$nb_synchronized += $result;
				}
				printStatus(++$num_jobs * $nb_by_request, $max_jobs, $nb_synchronized);
			}
		} elseif ($type == 'orders') {
			print "Synchronize the orders\n";
			printStatus(0, $max_jobs);
			foreach ($group_request_ids as $request_ids) {
				$result = $synchro->synchCommande($request_ids, $nb_by_request);
				if ($result < 0) {
					$nb_synchronized += $result - 1;
					$error++;
					break;
				} else {
					$nb_synchronized += $result;
				}
				printStatus(++$num_jobs * $nb_by_request, $max_jobs, $nb_synchronized);
			}
		}
		if ($error) {
			print "\nError synchronisation : " . dol_htmlentitiesbr_decode($synchro->errorsToString()) . "\n";
		}
	}
}

print "\nEnd.\n";

$db->close();
exit(0);


function errorsToString($object, $separator = ', ')
{
	return $object->error . (is_array($object->errors) ? (!empty($object->error) ? $separator : '') . join($separator, $object->errors) : '');
}

function printStatus($num_jobs, $max_jobs, $nb_synchronized = 0)
{
	global $startTime;

	$num_jobs = max(0, min($num_jobs, $max_jobs));
	$padding = strlen($max_jobs);
	$percent = $num_jobs * 100 / $max_jobs;
	$elapsedTime = microtime(true) - $startTime;
	$remainingTime = $percent > 0 ? $elapsedTime * (100 - $percent) / $percent : 0;
	print sprintf("\rStatus: blocks d'ids: %{$padding}d / %{$padding}d - %3d%% - Nb synchronized: %{$padding}d - Elapsed: " . microTimeToTime($elapsedTime) . " - Remaining: " . microTimeToTime($remainingTime), $num_jobs, $max_jobs, $percent, $nb_synchronized);
}

function microTimeToTime($microtime)
{
    $hours = (int)($microtime / 60 / 60);
    $minutes = (int)($microtime / 60) - $hours * 60;
    $seconds = (int)$microtime - $hours * 60 * 60 - $minutes * 60;
    return sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
}