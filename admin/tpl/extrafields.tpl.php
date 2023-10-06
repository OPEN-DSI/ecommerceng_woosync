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
 * @global array			$extra_fields_list
 * @global array			$remote_attributes
 */


/**
 * Extra fields.
 */

if (is_array($extra_fields_list)) {
	dol_include_once("/ecommerceng/lib/eCommerce.lib.php");
	foreach ($extra_fields_list as $table_element => $info) {
		if (!empty($info['extra_fields'])) {
			print '<div id="extra_fields_options"></div>';
			print load_fiche_titre($langs->trans("ECommercengWoocommerceExtrafieldsOptionsOf", $langs->transnoentitiesnoconv($info['label'])), '', '');

			print '<form method="post" action="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '#extra_fields_options">';
			print '<input type="hidden" name="token" value="' . ecommercengNewToken() . '">';
			print '<input type="hidden" name="action" value="set_extra_fields_options">';
			print '<input type="hidden" name="table_element" value="' . $table_element . '">';

			print '<table class="noborder centpercent">';
			print '<tr class="liste_titre">';
			print '<td class="width20p">' . $langs->trans("ExtraFields") . '</td>' . "\n";
			if (!empty($info['default'])) {
				print '<td>' . $langs->trans("DefaultValue");
				print ' ' . $form->textwithpicto('', $langs->transnoentities('ECommercengWoocommerceExtrafieldsOptionsDefaultValueDescription'));
				print '</td>' . "\n";
				print '<td class="width25 center"><input type="checkbox" class="ef_state_all" data-target="ef_dft_state_' . $table_element . '" title="' . $langs->trans('Enabled') . '"/></td>' . "\n";
			}
			if (!empty($info['metadata'])) {
				print '<td>' . $langs->trans("ECommercengWoocommerceExtrafieldsMetaData");
				print ' ' . $form->textwithpicto('', $langs->transnoentities('ECommercengWoocommerceExtrafieldsOptionsMetaDataDescription'));
				print '</td>' . "\n";
				print '<td class="width25 center"><input type="checkbox" class="ef_state_all" data-target="ef_mdt_state_' . $table_element . '" title="' . $langs->trans('Enabled') . '"/></td>' . "\n";
			}
			if (!empty($info['attributes'])) {
				$remote_attributes_label = array_flip($info['attributes']);
				print '<td>' . $langs->trans("ECommercengWoocommerceExtrafieldsAttribute");
				print ' ' . $form->textwithpicto('', $langs->transnoentities('ECommercengWoocommerceExtrafieldsOptionsAttributeDescription'));
				print '</td>' . "\n";
				print '<td class="width25 center"><input type="checkbox" class="ef_state_all" data-target="ef_att_state_' . $table_element . '" title="' . $langs->trans('Enabled') . '"/></td>' . "\n";
			}
			print "</tr>\n";

			$activated_info = $object->parameters['extra_fields'][$table_element]['activated'];
			$values_info = $object->parameters['extra_fields'][$table_element]['values'];
			$show_info = $object->parameters['extra_fields'][$table_element]['show'];
			foreach ($info['extra_fields'] as $key => $label) {
				if (!empty($extrafields->attributes[$table_element]['langfile'][$key])) $langs->load($extrafields->attributes[$table_element]['langfile'][$key]);

				print '<tr class="oddeven">' . "\n";
				print '<td>' . $langs->trans($label) . ' ( ' . $key . ' )</td>' . "\n";
				if (!empty($info['default'])) {
					$not_supported = in_array($extrafields->attributes[$table_element]['type'][$key], [ 'date', 'datetime' ]);

					$target_class = 'ef_dft_' . $table_element . '_' . $key;
					$default_value = '';
					if (isset($extrafields->attributes[$table_element]['default'][$key])) $default_value = $extrafields->attributes[$table_element]['default'][$key];
					$value = isset($values_info['dft'][$key]) ? $values_info['dft'][$key] : $default_value;
					$activated = !empty($activated_info['dft'][$key]);
					print '<td>' . "\n";
					if ($not_supported) {
						print $langs->trans('NotSupported');
					} else {
//						print $extrafields->showInputField($key, $value, ($activated ? '' : ' disabled'), '', 'ef_dft_value_' . $table_element . '_', '', 0, $table_element);
						print '<input type="text" class="centpercent ' . $target_class . '" id="ef_dft_value_' . $table_element . '_options_' . $key . '" name="ef_dft_value_' . $table_element . '_options_' . $key . '" value="' . dol_escape_htmltag($value) . '"' . ($activated ? '' : ' disabled') . ' />';
					}
					print '</td>' . "\n";
					print '<td class="center">' . "\n";
					if (!$not_supported) {
						print '<input type="checkbox" class="ef_state ef_dft_state_' . $table_element . '" name="ef_dft_state_' . $table_element . '_' . $key . '" value="1" data-target="' . $target_class . '"' . ($activated ? ' checked' : '') . ' title="' . $langs->trans('Enabled') . '" />' . "\n";
					}
					print '</td>';
				}
				if (!empty($info['metadata'])) {
					$target_class = 'ef_mdt_' . $table_element . '_' . $key;
					$value = isset($values_info['mdt'][$key]) ? $values_info['mdt'][$key] : $key;
					$activated = !empty($activated_info['mdt'][$key]);
					print '<td>' . "\n";
					print '<input type="text" class="centpercent ' . $target_class . '" id="ef_mdt_value_' . $table_element . '_' . $key . '" name="ef_mdt_value_' . $table_element . '_' . $key . '" value="' . dol_escape_htmltag($value) . '"' . ($activated ? '' : ' disabled') . ' />';
					print '</td>' . "\n";
					print '<td class="center">' . "\n";
					print '<input type="checkbox" class="ef_state ef_mdt_state_' . $table_element . '" name="ef_mdt_state_' . $table_element . '_' . $key . '" value="1" data-target="' . $target_class . '"' . ($activated ? ' checked' : '') . ' title="' . $langs->trans('Enabled') . '" />' . "\n";
					print '</td>';
				}
				if (!empty($info['attributes'])) {
					$target_class = 'ef_att_' . $table_element . '_' . $key;
					$value = isset($values_info['att'][$key]) ? $values_info['att'][$key] : (isset($remote_attributes_label[$label]) ? $remote_attributes_label[$label] : $key);
					$show = isset($show_info['att'][$key]) ? $show_info['att'][$key] : 0;
					$activated = !empty($activated_info['att'][$key]);
					print '<td>' . "\n";
					print $form->selectarray('ef_att_value_' . $table_element . '_' . $key, $info['attributes'], $value, 1, 0, 0, '', 0, 0, $activated ? 0 : 1, '', 'minwidth300 ' . $target_class);
					print $form->selectarray('ef_att_show_' . $table_element . '_' . $key, [ 1 => $langs->trans('Show'), 2 => $langs->trans('Hide') ], $show, 0, 0, 0, '', 0, 0, $activated ? 0 : 1, '', 'minwidth100 ' . $target_class);
					print '</td>' . "\n";
					print '<td class="center">' . "\n";
					print '<input type="checkbox" class="ef_state ef_att_state_' . $table_element . '" name="ef_att_state_' . $table_element . '_' . $key . '" value="1" data-target="' . $target_class . '"' . ($activated ? ' checked' : '') . ' title="' . $langs->trans('Enabled') . '" />' . "\n";
					print '</td>';
				}
				print '</tr>' . "\n";
			}

			print '</table>' . "\n";

			print '<br>';
			print '<div align="center">';
			print '<input type="submit" class="button" value="' . $langs->trans('Modify') . '" />';
			print '</div>';

			print '</form>';
		}
	}
}
