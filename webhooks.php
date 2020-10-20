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

if (! defined('NOCSRFCHECK'))    define('NOCSRFCHECK', '1');			// Do not check anti CSRF attack test
if (! defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');		// Do not check anti POST attack test
if (! defined('NOREQUIREMENU'))  define('NOREQUIREMENU', '1');		// If there is no need to load and show top and left menu
if (! defined('NOREQUIREHTML'))  define('NOREQUIREHTML', '1');		// If we don't need to load the html.form.class.php
if (! defined('NOREQUIREAJAX'))  define('NOREQUIREAJAX', '1');       // Do not load ajax.lib.php library
if (! defined("NOLOGIN"))        define("NOLOGIN", '1');				// If this page is public (can be called outside logged session)

// Change this following line to use the correct relative path (../, ../../, etc)
$res=0;
if (! $res && file_exists("../main.inc.php")) $res=@include '../main.inc.php';			// to work if your module directory is into a subdir of root htdocs directory
if (! $res && file_exists("../../main.inc.php")) $res=@include '../../main.inc.php';		// to work if your module directory is into a subdir of root htdocs directory
if (! $res) die("Include of main fails");
dol_include_once("/ecommerceng/class/business/eCommercePendingWebHook.class.php");

/*dol_syslog('_SERVER : ' . json_encode($_SERVER), LOG_ERR);
dol_syslog('_REQUEST : ' . json_encode($_REQUEST), LOG_ERR);
dol_syslog('_POST : ' . json_encode($_POST), LOG_ERR);
dol_syslog('_GET : ' . json_encode($_GET), LOG_ERR);
dol_syslog('HTTP_RAW_POST_DATA : ' . json_encode($HTTP_RAW_POST_DATA), LOG_ERR);
$postdata = file_get_contents("php://input");
dol_syslog('postdata : ' . json_encode($postdata), LOG_ERR);
dol_syslog('_FILES : ' . json_encode($_FILES), LOG_ERR);*/

$langs->load("ecommerce@ecommerceng");

$site_id = GETPOST('ecommerce_id', 'int');
if (!($site_id > 0)) {
	// Bad values
	http_response_code(400);
	die();
}
if (GETPOST('webhook_id', 'int') > 0) {
	// Test webhook links
	http_response_code(200);
	die();
}

$webhook = new eCommercePendingWebHook($db);

// Set values
$webhook->site_id = $site_id;
$webhook->delivery_id = $_SERVER['HTTP_X_WC_WEBHOOK_DELIVERY_ID'];
$webhook->webhook_id = $_SERVER['HTTP_X_WC_WEBHOOK_ID'];
$webhook->webhook_topic = $_SERVER['HTTP_X_WC_WEBHOOK_TOPIC'];
$webhook->webhook_event = $_SERVER['HTTP_X_WC_WEBHOOK_EVENT'];
$webhook->webhook_resource = $_SERVER['HTTP_X_WC_WEBHOOK_RESOURCE'];
$webhook->webhook_signature = $_SERVER['HTTP_X_WC_WEBHOOK_SIGNATURE'];
$webhook->webhook_source = $_SERVER['HTTP_X_WC_WEBHOOK_SOURCE'];
$webhook->webhook_data = file_get_contents("php://input");

// Check values
$result = $webhook->check();
if ($result == -1) {
	// Bad values
	http_response_code(400);
	die();
} elseif ($result == -2) {
	// Unauthorized
	http_response_code(401);
	die();
}

$result = $webhook->create();
if ($result < 0) {
	// Error
	http_response_code(500);
	die();
}

http_response_code(200);
$db->close();
