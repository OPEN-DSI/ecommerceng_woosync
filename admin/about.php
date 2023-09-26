<?php
/* Copyright (C) 2020      Open-DSI             <support@open-dsi.fr>
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
 *	    \file       htdocs/ecommerceng/admin/about.php
 *		\ingroup    ecommerceng
 *		\brief      Page about of ecommerceng module
 */

// Change this following line to use the correct relative path (../, ../../, etc)
$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include '../../main.inc.php';			// to work if your module directory is into a subdir of root htdocs directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include '../../../main.inc.php';		// to work if your module directory is into a subdir of root htdocs directory
if (! $res) die("Include of main fails");
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/ecommerceng/lib/eCommerce.lib.php');
dol_include_once('/ecommerceng/core/modules/modECommerceNg.class.php');

$langs->load("admin");
$langs->load("ecommerce@ecommerceng");
$langs->load("opendsi@ecommerceng");

if (!$user->admin) accessforbidden();


/**
 * View
 */

$wikihelp='EN:ECommerceNg_En|FR:ECommerceNg_Fr|ES:ECommerceNg_Es';
llxHeader('', $langs->trans("ECommerceSetup"), $wikihelp);

$linkback='<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans("ECommerceSetup"),$linkback,'title_setup');
print "<br>\n";

$head=ecommercengConfigSitePrepareHead($object);

print dol_get_fiche_head($head, 'about', $langs->trans("Module107100Name"), 0, 'opendsi@ecommerceng');


$modClass = new modECommerceNg($db);
$ECommerceNgVersion = !empty($modClass->getVersion()) ? $modClass->getVersion() : 'NC';

$supportvalue = "/*****"."<br>";
$supportvalue.= " * Module version : ".$ECommerceNgVersion."<br>";
$supportvalue.= " * Dolibarr version : ".DOL_VERSION."<br>";
$supportvalue.= " * Dolibarr version installation initiale : ".$conf->global->MAIN_VERSION_LAST_INSTALL."<br>";
$supportvalue.= " *****/"."<br><br>";
$supportvalue.= "Description de votre problème :"."<br>";


print '<form id="ticket" method="POST" target="_blank" action="https://support.easya.solutions/create_ticket.php">';
print '<input name=message type="hidden" value="'.$supportvalue.'" />';
print '<input name=email type="hidden" value="'.$user->email.'" />';
print '<table class="centpercent"><tr>'."\n";
print '<td width="310px"><img src="../img/opendsi_dolibarr_preferred_partner.png" /></td>'."\n";
print '<td valign="top"><p>'.$langs->trans("OpenDsiAboutDesc1").' <button type="submit" >'.$langs->trans("OpenDsiAboutDesc2").'</button> '.$langs->trans("OpenDsiAboutDesc3").'</p></td>'."\n";
print '</tr></table>'."\n";
print '</form>'."\n";



print dol_get_fiche_end();


llxFooter();

$db->close();