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
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
dol_include_once("/ecommerceng/class/data/eCommerceSite.class.php");

$langs->load("admin");
$langs->load("ecommerce@ecommerceng");
//$langs->load("companies");
//$langs->load("users");
//$langs->load("orders");
//$langs->load("bills");
//$langs->load("contracts");

/***************************************************
* Check access
****************************************************/
//CHECK ACCESS
// Protection if external user
if ($user->societe_id > 0 || !$user->rights->ecommerceng->read)
{
	accessforbidden();
}

/***************************************************
* Define page variables
****************************************************/

$eCommerceSite = new eCommerceSite($db);
$sites = $eCommerceSite->listSites('object');

/***************************************************
* Show page
****************************************************/
$var=true;

$urltpl=dol_buildpath('/ecommerceng/tpl/index.tpl.php',0);
include($urltpl);

$db->close();
