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


/**
 * Class data access to dict
 */
class eCommerceDict
{
	private $db;
	private $table;

    function eCommerceDict($db, $table)
    {
    	$this->db = $db;
    	$this->table = $table;
        return 1;
    }

    /**
     * Get object from database
     *
     * @param   string    $code     Code
     * @return  array               Aray of table fields values
     */
    public function fetchByCode($code)
	{
		$object = array();
		$sql = "SELECT * FROM ".$this->table." WHERE code = '".$code."'";
		$result = $this->db->query($sql);
		if ($result)
		{
			$numRows = $this->db->num_rows($result);
			if ($numRows == 1)
			{
				$obj = $this->db->fetch_object($result);
				if (count($obj))
				{
					foreach ($obj as $field=>$value)
					{
						$object[$field] = $value;
					}
				}
			}
			elseif ($numRows > 1)
				$object = false;
		}
		return $object;
    }
    /**
     * Get all lines from database
     * @return array
     */
    public function getAll()
    {
    	$lines = array();
		$sql = "SELECT * FROM ".$this->table;
		$result = $this->db->query($sql);
		if ($result)
		{
			$numRows = $this->db->num_rows($result);
			if ($numRows > 0)
			{
				while($obj = $this->db->fetch_object($result))
				{
					$line = array();
					if (count($obj))
					{
						foreach ($obj as $field=>$value)
						{
							$line[$field] = $value;
						}
					}
					$lines[] = $line;
				}
			}
		}
		return $lines;
    }

    /**
     * Get all lines from database match with keys (array(field=>array(value, type)))
     * @param   array   $keys    Keys for the search array(field_name=>array('value'=>value, 'type'=>type) (type (optionnel: string, like, date)
     * @return  array
     */
    public function search($keys)
    {
    	$lines = array();

		$sql = "SELECT * FROM `".$this->table."`";
		if (is_array($keys) && count($keys) > 0) {
            $fields = array();
            foreach ($keys as $field => $value) {
                switch ($value['type']) {
                    case 'string':
                        $key = "= '".$this->db->escape($value['value'])."'";
                        break;
                    case 'like':
                        $key = "LIKE '".$this->db->escape($value['value'])."'";
                        break;
                    case 'date':
                        $key = "= '".$this->db->idate($value['value'])."'";
                        break;
                    default:
                        $key = '= '.$this->db->escape($value['value']);
                        break;
                }
                $fields[] = $field.' '.$key;
            }
            $sql .= " WHERE ".implode(' AND ', $fields);
        }

		$result = $this->db->query($sql);
		if ($result) {
            while ($obj = $this->db->fetch_array($result)) {
                $lines[] = $obj;
            }
        }

		return $lines;
    }

    /**
     * Update all lines from database match with keys
     * @param   array   $values     Values for the search array(field_name=>array('value'=>value, 'type'=>type) (type (optionnel: string, date)
     * @param   array   $keys       Keys for the search array(field_name=>array('value'=>value, 'type'=>type) (type (optionnel: string, like, date)
     * @return  boolean
     */
    public function update($values, $keys)
    {
		$sql = "UPDATE `".$this->table."` SET ";
        if (is_array($values) && count($values) > 0) {
            $fields = array();
            foreach ($values as $field => $value) {
                switch ($value['type']) {
                    case 'string':
                        $key = "= '" . $this->db->escape($value['value']) . "'";
                        break;
                    case 'date':
                        $key = "= '" . $this->db->idate($value['value']) . "'";
                        break;
                    default:
                        $key = '= ' . $this->db->escape($value['value']);
                        break;
                }
                $fields[] = $field . ' ' . $key;
            }
            $sql .= implode(' AND ', $fields);
        }
        if (is_array($keys) && count($keys) > 0) {
            $fields = array();
            foreach ($keys as $field => $value) {
                switch ($value['type']) {
                    case 'string':
                        $key = "= '" . $this->db->escape($value['value']) . "'";
                        break;
                    case 'like':
                        $key = "LIKE '" . $this->db->escape($value['value']) . "'";
                        break;
                    case 'date':
                        $key = "= '" . $this->db->idate($value['value']) . "'";
                        break;
                    default:
                        $key = '= ' . $this->db->escape($value['value']);
                        break;
                }
                $fields[] = $field . ' ' . $key;
            }
            $sql .= " WHERE " . implode(' AND ', $fields);
        }

		$result = $this->db->query($sql);
		if ($result) {
            return true;
        }

		return false;
    }

    /**
     * Insert line to database
     * @param   array   $fields     Fields for insert
     * @param   array   $values     Values for the insert array(field_name=>array('value'=>value, 'type'=>type) (type (optionnel: string, date)
     * @return  boolean
     */
    public function insert($fields, $values)
    {
        $values_list = array();
        if (is_array($values) && count($values) > 0) {
            foreach ($fields as $field) {
                if (isset($values[$field])) {
                    switch ($values[$field]['type']) {
                        case 'string':
                            $values_list[] = "'" . $this->db->escape($values[$field]['value']) . "'";
                            break;
                        case 'date':
                            $values_list[] = "'" . $this->db->idate($values[$field]['value']) . "'";
                            break;
                        default:
                            $values_list[] = $this->db->escape($values[$field]['value']);
                            break;
                    }
                }
            }
        }

        $sql = "INSERT INTO `".$this->table."` (".implode(', ', $fields).") VALUES(".implode(', ', $values_list).")";
		$result = $this->db->query($sql);
		if ($result) {
            return true;
        }

		return false;
    }

    /**
     * Get the value of ECOMMERCE_COMPANY_ANONYMOUS from db
     * @return int > 0 if OK, 0 if KO
     */
    /*public function getAnonymousConstValue()
    {
    	$sql = "SELECT value FROM ".$this->table." WHERE name='ECOMMERCE_COMPANY_ANONYMOUS'";
    	$result = -1;
    	$resql = $this->db->query($sql);
    	if ($resql)
		{
			$obj = $this->db->fetch_object($resql);
			$result = $obj->value;
		}
		return $result;
    }*/

}

