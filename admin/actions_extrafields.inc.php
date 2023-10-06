<?php
/* Copyright (C) 2022      Open-DSI             <support@open-dsi.fr>
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
 *	\file       htdocs/ecommerceng/admin/actions_selectsite.inc.php
 *	\ingroup    ecommerceng
 *	\brief      Include select site actions
 */

/**
 * Globals
 *
 * @global string			$action
 * @global array			$extra_fields_list
 */

if ($action == 'set_extra_fields_options') {
	$table_element = GETPOST('table_element', 'alphanohtml');
	if (isset($extra_fields_list[$table_element])) {
		$object->oldcopy = clone $object;

		$activated_list = array();
		$value_list = array();
		$show_list = array();
		foreach ($extra_fields_list[$table_element]['extra_fields'] as $key => $label) {
			// default value
			$activated = GETPOST("ef_dft_state_{$table_element}_{$key}", 'int') ? 1 : 0;
			if ($activated) $activated_list['dft'][$key] = true;
			$value = $activated ? GETPOST("ef_dft_value_{$table_element}_options_{$key}", 'alphanohtml') :
				(!empty($object->parameters['extra_fields'][$table_element]['values']['dft'][$key]) ? $object->parameters['extra_fields'][$table_element]['values']['dft'][$key] : null);
			if (isset($value)) $value_list['dft'][$key] = $value;

			// meta-data
			$activated = GETPOST("ef_mdt_state_{$table_element}_{$key}", 'int') ? 1 : 0;
			if ($activated) $activated_list['mdt'][$key] = true;
			$value = $activated ? GETPOST("ef_mdt_value_{$table_element}_{$key}", 'alphanohtml') :
				(!empty($object->parameters['extra_fields'][$table_element]['values']['mdt'][$key]) ? $object->parameters['extra_fields'][$table_element]['values']['mdt'][$key] : null);
			if (isset($value)) $value_list['mdt'][$key] = $value;

			// attribute
			$activated = GETPOST("ef_att_state_{$table_element}_{$key}", 'int') ? 1 : 0;
			if ($activated) $activated_list['att'][$key] = true;
			$value = $activated ? GETPOST("ef_att_value_{$table_element}_{$key}", 'alphanohtml') :
				(!empty($object->parameters['extra_fields'][$table_element]['values']['att'][$key]) ? $object->parameters['extra_fields'][$table_element]['values']['att'][$key] : null);
			if (isset($value)) $value_list['att'][$key] = $value;
			$show = $activated ? GETPOST("ef_att_show_{$table_element}_{$key}", 'alphanohtml') :
				(!empty($object->parameters['extra_fields'][$table_element]['show']['att'][$key]) ? $object->parameters['extra_fields'][$table_element]['show']['att'][$key] : null);
			if (isset($show)) $show_list['att'][$key] = $show;
		}
		$object->parameters['extra_fields'][$table_element]['activated'] = $activated_list;
		$object->parameters['extra_fields'][$table_element]['values'] = $value_list;
		$object->parameters['extra_fields'][$table_element]['show'] = $show_list;

		$result = $object->update($user);

		if ($result < 0) {
			setEventMessages($object->error, $object->errors, 'errors');
		} else {
			setEventMessage($langs->trans("SetupSaved"));
			header("Location: " . $_SERVER["PHP_SELF"] . '?id=' . $object->id);
			exit;
		}
	} else {
		setEventMessage("Wrong table element", 'errors');
	}
}