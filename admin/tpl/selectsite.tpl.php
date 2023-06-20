<?php
/* Copyright (C) 2022 Open-DSI <support@open-dsi.fr>
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
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

if (!defined('NOBROWSERNOTIF')) {
	define('NOBROWSERNOTIF', 1);
}

// Protection to avoid direct call of template
if (empty($conf) || !is_object($conf)) {
	print "Error, template page can't be called as URL";
	exit;
}

/**
 * Globals
 *
 * @global int				$id
 * @global eCommerceSite	$object
 * @global string			$self_url
 */
dol_include_once("/ecommerceng/lib/eCommerce.lib.php");

print '<form id="select_site" action="' . $_SERVER['PHP_SELF'] . '" method="post">';
print '<input type="hidden" name="token" value="' . ecommercengNewToken() . '" />';
print '<input type="hidden" name="action" value="select_site" />';
print $langs->trans('ECommerceNgSelectSite') . ' : ';
$sites = $object->listSites();
$sites_list = array(
	0 => $langs->trans('ECommerceAddNewSite')
);
foreach ($sites as $site) {
	$sites_list[$site['id']] = $site['name'];
}
print $form->selectarray('site_id', $sites_list, $id, 0, 0, 0, '', 0, 0, 0, '', 'minwidth300');
print '</form>';
print <<<SCRIPT
    <script type="text/javascript" language="javascript">
        jQuery(document).ready(function(){
            jQuery('#site_id').on('change', function() {
                jQuery('#select_site').submit();
            })
        });
    </script>
SCRIPT;
