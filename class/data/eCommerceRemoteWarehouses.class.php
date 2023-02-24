<?php
/* Copyright (C) 2019      Open-DSI             <support@open-dsi.fr>
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
 * \file    htdocs/ecommerceng/class/data/eCommerceRemoteWarehouses.class.php
 * \ingroup ecommerceng
 * \brief
 */


/**
 * Class eCommerceRemoteWarehouses
 *
 * Put here description of your class
 */
class eCommerceRemoteWarehouses
{
    /**
     * @var DoliDB Database handler.
     */
    public $db;
    /**
     * @var string Error
     */
    public $error = '';
    /**
     * @var array Errors
     */
    public $errors = array();

    public $table_element = 'ecommerceng_remote_warehouses';

    /**
     * Constructor
     *
     * @param        DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     *  Set all remote warehouse of a site
     *
     * @param   int         $site_id                Site ID
     * @param   array       $remote_warehouses      List of infos of each remote warehouse
     * @return  int                                 >0 if OK, <0 if KO
     * @throws  Exception
     */
    public function set($site_id, $remote_warehouses)
	{
		global $conf, $langs;
		dol_syslog(__METHOD__ . " site_id=$site_id, remote_warehouses=" . json_encode($remote_warehouses));

		$errors = 0;
		$this->errors = array();

		// Clean values
		$site_id = $site_id > 0 ? $site_id : 0;
		$remote_warehouses = is_array($remote_warehouses) ? $remote_warehouses : array();

		// Check values
		if ($site_id == 0) {
			$this->errors[] = $langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("ECommerceSite"));
			return -1;
		}

		$this->db->begin();

		foreach ($remote_warehouses as $remote_code => $infos) {
			// Search warehouse
			$sql = 'SELECT rowid';
			$sql .= ' FROM ' . MAIN_DB_PREFIX . $this->table_element;
			$sql .= ' WHERE site_id = ' . $site_id;
			$sql .= ' AND entity = ' . $conf->entity;
			$sql .= " AND remote_code = '" . $this->db->escape($remote_code) . "'";

			$resql = $this->db->query($sql);
			if (!$resql) {
				dol_syslog(__METHOD__ . ' SQL: ' . $sql . '; Errors: ' . $this->db->lasterror(), LOG_ERR);
				$this->errors[] = $this->db->lasterror();
				$errors++;
				break;
			}

			$line_id = 0;
			if ($obj = $this->db->fetch_object($resql)) {
				$line_id = $obj->rowid;
			}

			$this->db->free($resql);

			if ($line_id > 0) {
				// Update values
				$sql = 'UPDATE ' . MAIN_DB_PREFIX . $this->table_element . ' SET';
				$sql .= "  remote_id = '" . $this->db->escape($infos['remote_id']) . "'";
                $sql .= ", remote_name = '" . $this->db->escape($infos['remote_name']) . "'";
				$sql .= ", warehouse_id = " . ($infos['warehouse_id'] > 0 ? $infos['warehouse_id'] : 'NULL');
				$sql .= ', set_even_if_empty_stock = ' . (!empty($infos['set_even_if_empty_stock']) ? 1 : 'NULL');
				$sql .= ', old_entry = ' . (!empty($infos['old_entry']) ? 1 : 'NULL');
				$sql .= ' WHERE rowid = ' . $line_id;

				$resql = $this->db->query($sql);
			} else {
				// Insert values
				$sql = 'INSERT INTO ' . MAIN_DB_PREFIX . $this->table_element . '(site_id, remote_code, remote_id, remote_name, warehouse_id, set_even_if_empty_stock, old_entry, entity) VALUES (';
				$sql .= $site_id;
				$sql .= ", '" . $this->db->escape($remote_code) . "'";
				$sql .= ", '" . $this->db->escape($infos['remote_id']) . "'";
                $sql .= ", '" . $this->db->escape($infos['remote_name']) . "'";
				$sql .= ', ' . ($infos['warehouse_id'] > 0 ? $infos['warehouse_id'] : 'NULL');
				$sql .= ', ' . (!empty($infos['set_even_if_empty_stock']) ? 1 : 'NULL');
				$sql .= ', ' . (!empty($infos['old_entry']) ? 1 : 'NULL');
				$sql .= ', ' . $conf->entity;
				$sql .= ')';
				$resql = $this->db->query($sql);
			}
			if (!$resql) {
				dol_syslog(__METHOD__ . ' SQL: ' . $sql . '; Errors: ' . $this->db->lasterror(), LOG_ERR);
				$this->errors[] = $this->db->lasterror();
				$errors++;
				break;
			}
		}

		if ($errors) {
			$this->db->rollback();
			return -1;
		} else {
			$this->db->commit();
			return 1;
		}
	}

    /**
     *  Get a remote warehouse of a site by the remote warehouse code or warehouse id
     *
     * @param   int         $site_id                Site ID
	 * @param   string      $remote_code  			Remote warehouse code on site
     * @param   int         $warehouse_id  			Warehouse ID on Dolibarr
     * @return  array|int                           0 if not found, <0 if errors or array of infos
     * @throws  Exception
     */
    public function get($site_id, $remote_code='', $warehouse_id=0)
    {
        global $conf;
        dol_syslog(__METHOD__ . " site_id=$site_id, remote_code=$remote_code, warehouse_id=$warehouse_id");

        $sql = 'SELECT remote_code, remote_id, remote_name, warehouse_id, set_even_if_empty_stock, old_entry FROM ' . MAIN_DB_PREFIX . $this->table_element;
        $sql .= ' WHERE site_id = ' . $site_id . ' AND entity = ' . $conf->entity;
        if ($warehouse_id > 0) {
			$sql .= ' AND warehouse_id = ' . $warehouse_id;
			$sql .= ' AND old_entry IS NULL';
		} else {
            $sql .= " AND remote_code = '" . $this->db->escape($remote_code) . "'";
        }
        $resql = $this->db->query($sql);
        if ($resql) {
            if ($this->db->num_rows($resql) == 0)
                return 0;

            if ($obj = $this->db->fetch_object($resql)) {
                return array(
					'remote_code' => $obj->remote_code,
                    'remote_id' => $obj->remote_id,
					'remote_name' => $obj->remote_name,
					'warehouse_id' => $obj->warehouse_id > 0 ? $obj->warehouse_id : 0,
					'set_even_if_empty_stock' => !empty($obj->set_even_if_empty_stock) ? 1 : 0,
					'old_entry' => !empty($obj->old_entry) ? 1 : 0,
                );
            }
        } else {
            dol_syslog(__METHOD__ . ' SQL: ' . $sql . '; Errors: ' . $this->db->lasterror(), LOG_ERR);
            $this->errors[] = $this->db->lasterror();
            return -1;
        }
    }

    /**
     *  Get all remote warehouses of a site
     *
     * @param   int         $site_id    Site ID
     * @return  array|int               List of all remote warehouses infos
     * @throws  Exception
     */
    public function get_all($site_id)
    {
        global $conf;
        dol_syslog(__METHOD__ . " site_id=$site_id");

        $warehouses = array();

        $sql = 'SELECT remote_code, remote_id, remote_name, warehouse_id, set_even_if_empty_stock, old_entry FROM ' . MAIN_DB_PREFIX . $this->table_element;
        $sql .= ' WHERE site_id = ' . $site_id . ' AND entity = ' . $conf->entity;
        $resql = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
				$warehouses[$obj->remote_code] = array(
                    'remote_id' => $obj->remote_id,
                    'remote_name' => $obj->remote_name,
                    'warehouse_id' => $obj->warehouse_id,
                    'set_even_if_empty_stock' => !empty($obj->set_even_if_empty_stock) ? 1 : 0,
					'old_entry' => !empty($obj->old_entry) ? 1 : 0,
                );
            }
        } else {
            dol_syslog(__METHOD__ . ' SQL: ' . $sql . '; Errors: ' . $this->db->lasterror(), LOG_ERR);
            $this->errors[] = $this->db->lasterror();
            return -1;
        }

        return $warehouses;
    }

    /**
     *  Delete all remote warehouses of a site
     *
     * @param   int         $site_id    Site ID
     * @return  int                     >0 if OK, <0 if KO
     * @throws  Exception
     */
    public function delete_all($site_id)
    {
        global $conf;
        dol_syslog(__METHOD__ . " site_id=$site_id");

        // Delete all line for the site
        $sql = 'DELETE FROM ' . MAIN_DB_PREFIX . $this->table_element . ' WHERE site_id = ' . $site_id . ' AND entity = ' . $conf->entity;
        $resql = $this->db->query($sql);
        if (!$resql) {
            dol_syslog(__METHOD__ . ' SQL: ' . $sql . '; Errors: ' . $this->db->lasterror(), LOG_ERR);
            $this->errors[] = $this->db->lasterror();
            return -1;
        }

        return 1;
    }

	/**
	 * Method to output saved errors
	 *
	 * @param   string      $separator      Separator between each error
	 * @return	string		                String with errors
	 */
	public function errorsToString($separator = ', ')
	{
		return (is_array($this->errors) ? join($separator, $this->errors) : '');
	}
}