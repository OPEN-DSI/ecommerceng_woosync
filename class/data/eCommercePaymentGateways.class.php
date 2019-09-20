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
 * \file    htdocs/ecommerceng/class/data/eCommercePaymentGateways.class.php
 * \ingroup ecommerceng
 * \brief
 */


/**
 * Class eCommercePaymentGateways
 *
 * Put here description of your class
 */
class eCommercePaymentGateways
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

    public $table_element = 'ecommerceng_payment_gateways';

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
     *  Set all payment gateways of a site
     *
     * @param   int         $site_id                Site ID
     * @param   array       $payment_gateways       List of infos of each payment gateway
     * @return  int                                 >0 if OK, <0 if KO
     * @throws  Exception
     */
    public function set($site_id, $payment_gateways)
    {
        global $conf, $langs;
        dol_syslog(__METHOD__ . " site_id=$site_id, payment_gateways=".json_encode($payment_gateways));

        $errors = 0;
        $this->errors = array();

        // Clean values
        $site_id = $site_id > 0 ? $site_id : 0;
        $payment_gateways = is_array($payment_gateways) ? $payment_gateways : array();

        // Check values
        if ($site_id == 0) {
            $this->errors[] = $langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("ECommerceSite"));
            return -1;
        }

        $this->db->begin();

        // Delete all line for the site
        $result = $this->delete_all($site_id);
        if ($result < 0) {
            $errors++;
        }

        if (!$errors) {
            // Insert values
            foreach ($payment_gateways as $payment_gateway_id => $infos) {
                $sql = 'INSERT INTO ' . MAIN_DB_PREFIX . $this->table_element . '(site_id, payment_gateway_id, payment_gateway_label, payment_mode_id, bank_account_id, create_invoice_payment, mail_model_for_send_invoice, supplier_id, product_id_for_fee, create_supplier_invoice_payment, entity) VALUES (';
                $sql .= $site_id;
                $sql .= ", '" . $this->db->escape($payment_gateway_id) . "'";
                $sql .= ", '" . $this->db->escape($infos['payment_gateway_label']) . "'";
                $sql .= ', ' . ($infos['payment_mode_id'] > 0 ? $infos['payment_mode_id'] : 'NULL');
                $sql .= ', ' . ($infos['bank_account_id'] > 0 ? $infos['bank_account_id'] : 'NULL');
                $sql .= ', ' . (!empty($infos['create_invoice_payment']) ? 1 : 'NULL');
                $sql .= ', ' . ($infos['mail_model_for_send_invoice'] > 0 ? $infos['mail_model_for_send_invoice'] : 'NULL');
                $sql .= ', ' . ($infos['supplier_id'] > 0 ? $infos['supplier_id'] : 'NULL');
                $sql .= ', ' . ($infos['product_id_for_fee'] > 0 ? $infos['product_id_for_fee'] : 'NULL');
                $sql .= ', ' . (!empty($infos['create_supplier_invoice_payment']) ? 1 : 'NULL');
                $sql .= ', ' . $conf->entity;
                $sql .= ')';
                $resql = $this->db->query($sql);
                if (!$resql) {
                    dol_syslog(__METHOD__ . ' SQL: ' . $sql . '; Errors: ' . $this->db->lasterror(), LOG_ERR);
                    $this->errors[] = $this->db->lasterror();
                    $errors++;
                    break;
                }
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
     *  Get a payment gateway of a site by the payment gateway ID or payment mode ID
     *
     * @param   int         $site_id                Site ID
     * @param   string      $payment_gateway_id     Payment gateway ID on WooCommerce
     * @param   int         $payment_mode_id        Payment mode ID on Dolibarr
     * @return  array|int                           0 if not found, <0 if errors or array of infos
     * @throws  Exception
     */
    public function get($site_id, $payment_gateway_id='', $payment_mode_id=0)
    {
        global $conf;
        dol_syslog(__METHOD__ . " site_id=$site_id, payment_gateway_id=$payment_gateway_id, payment_mode_id=$payment_mode_id");

        $sql = 'SELECT payment_gateway_id, payment_gateway_label, payment_mode_id, bank_account_id, create_invoice_payment, mail_model_for_send_invoice, supplier_id, product_id_for_fee, create_supplier_invoice_payment FROM ' . MAIN_DB_PREFIX . $this->table_element;
        $sql .= ' WHERE site_id = ' . $site_id . ' AND entity = ' . $conf->entity;
        if ($payment_mode_id > 0) {
            $sql .= ' AND payment_mode_id = ' . $payment_mode_id;
        } else {
            $sql .= " AND payment_gateway_id = '" . $this->db->escape($payment_gateway_id) . "'";
        }
        $resql = $this->db->query($sql);
        if ($resql) {
            if ($this->db->num_rows($resql) == 0)
                return 0;

            if ($obj = $this->db->fetch_object($resql)) {
                return array(
                    'payment_gateway_id' => $obj->payment_gateway_id,
                    'payment_gateway_label' => $obj->payment_gateway_label,
                    'payment_mode_id' => $obj->payment_mode_id,
                    'bank_account_id' => $obj->bank_account_id,
                    'create_invoice_payment' => !empty($obj->create_invoice_payment) ? 1 : 0,
                    'mail_model_for_send_invoice' => $obj->mail_model_for_send_invoice,
                    'supplier_id' => $obj->supplier_id,
                    'product_id_for_fee' => $obj->product_id_for_fee,
                    'create_supplier_invoice_payment' => !empty($obj->create_supplier_invoice_payment) ? 1 : 0,
                );
            }
        } else {
            dol_syslog(__METHOD__ . ' SQL: ' . $sql . '; Errors: ' . $this->db->lasterror(), LOG_ERR);
            $errors[] = $this->db->lasterror();
            return -1;
        }
    }

    /**
     *  Get all payment gateway of a site
     *
     * @param   int         $site_id    Site ID
     * @return  array|int               List of all payment gateway infos
     * @throws  Exception
     */
    public function get_all($site_id)
    {
        global $conf;
        dol_syslog(__METHOD__ . " site_id=$site_id");

        $payment_gateways = array();

        $sql = 'SELECT payment_gateway_id, payment_gateway_label, payment_mode_id, bank_account_id, create_invoice_payment, mail_model_for_send_invoice, supplier_id, product_id_for_fee, create_supplier_invoice_payment FROM ' . MAIN_DB_PREFIX . $this->table_element;
        $sql .= ' WHERE site_id = ' . $site_id . ' AND entity = ' . $conf->entity;
        $resql = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $payment_gateways[$obj->payment_gateway_id] = array(
                    'payment_gateway_label' => $obj->payment_gateway_label,
                    'payment_mode_id' => $obj->payment_mode_id,
                    'bank_account_id' => $obj->bank_account_id,
                    'create_invoice_payment' => !empty($obj->create_invoice_payment) ? 1 : 0,
                    'mail_model_for_send_invoice' => $obj->mail_model_for_send_invoice,
                    'supplier_id' => $obj->supplier_id,
                    'product_id_for_fee' => $obj->product_id_for_fee,
                    'create_supplier_invoice_payment' => !empty($obj->create_supplier_invoice_payment) ? 1 : 0,
                );
            }
        } else {
            dol_syslog(__METHOD__ . ' SQL: ' . $sql . '; Errors: ' . $this->db->lasterror(), LOG_ERR);
            $errors[] = $this->db->lasterror();
            return -1;
        }

        return $payment_gateways;
    }

    /**
     *  Delete all payment gateway of a site
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