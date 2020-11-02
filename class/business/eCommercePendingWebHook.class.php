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
 * \file    htdocs/custom/ecommerceng/class/business/eCommercePendingWebHook.class.php
 * \ingroup ecommerceng
 * \brief
 */


/**
 * Class eCommercePendingWebHook
 *
 * Put here description of your class
 */
class eCommercePendingWebHook
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

	/**
	 * @var int		ID
	 */
	public $id;

	/**
	 * @var int		Site ID
	 */
	public $site_id;
	/**
	 * @var string	WebHook ID
	 */
	public $delivery_id;
	/**
	 * @var string	WebHook ID
	 */
	public $webhook_id;
	/**
	 * @var string	WebHook topic
	 */
	public $webhook_topic;
	/**
	 * @var string	WebHook resource
	 */
	public $webhook_resource;
	/**
	 * @var string	WebHook event
	 */
	public $webhook_event;
	/**
	 * @var string	WebHook data
	 */
	public $webhook_data;
	/**
	 * @var string	WebHook signature
	 */
	public $webhook_signature;
	/**
	 * @var string	WebHook source
	 */
	public $webhook_source;

	/**
	 * @var int		Date creation
	 */
	public $datec;
	/**
	 * @var int		Date processed
	 */
	public $datep;
	/**
	 * @var int		Date error
	 */
	public $datee;
	/**
	 * @var int		Error message when processing
	 */
	public $error_msg;

	/**
	 * @var eCommerceSite[]		eCommerceSite handler cached
	 */
	public static $site_cached;
	/**
	 * @var eCommerceSynchro[]		eCommerceSynchro handler cached
	 */
	public static $synchro_cached;

	const STATUS_NOT_PROCESSED = 0;
	const STATUS_PROCESSED = 1;
	const STATUS_ERROR = 2;

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
	 * Check if the data is OK
	 *
	 * @return	int 			-1 if bad values, -2 if unauthorized, 1 if OK
	 */
	public function check()
	{
		if (!($this->site_id > 0) || empty($this->delivery_id) || empty($this->webhook_id) || empty($this->webhook_topic) || empty($this->webhook_resource) ||
			empty($this->webhook_event) || empty($this->webhook_data) || empty($this->webhook_signature) || empty($this->webhook_source)
		) {
			// Bad values
			return -1;
		}

		$site = $this->getSite($this->site_id);
		if (!is_object($site)) {
			// Bad values
			return -1;
		}

		if (empty($site->parameters['web_hooks_secret']) || $this->webhook_signature != base64_encode(hash_hmac("sha256", $this->webhook_data, $site->parameters['web_hooks_secret'], true))) {
			// Unauthorized
			return -2;
		}

		return 1;
	}

	/**
	 * Create webhook
	 *
	 * @return	int 			<0 if KO, >0 if OK
	 */
	public function create()
	{
		dol_syslog(__METHOD__, LOG_DEBUG);

		$error = 0;
		$now = dol_now();

		// Insert webhook
		$sql = "INSERT INTO " . MAIN_DB_PREFIX . "ecommerce_pending_webhooks (";
		$sql .= "  site_id";
		$sql .= ", delivery_id";
		$sql .= ", webhook_id";
		$sql .= ", webhook_topic";
		$sql .= ", webhook_resource";
		$sql .= ", webhook_event";
		$sql .= ", webhook_data";
		$sql .= ", webhook_signature";
		$sql .= ", webhook_source";
		$sql .= ", status";
		$sql .= ", datec";
		$sql .= ")";
		$sql .= " VALUES (";
		$sql .= "   " . $this->site_id;
		$sql .= ", '" . $this->db->escape($this->delivery_id) . "'";
		$sql .= ", '" . $this->db->escape($this->webhook_id) . "'";
		$sql .= ", '" . $this->db->escape($this->webhook_topic) . "'";
		$sql .= ", '" . $this->db->escape($this->webhook_resource) . "'";
		$sql .= ", '" . $this->db->escape($this->webhook_event) . "'";
		$sql .= ", '" . $this->db->escape($this->webhook_data) . "'";
		$sql .= ", '" . $this->db->escape($this->webhook_signature) . "'";
		$sql .= ", '" . $this->db->escape($this->webhook_source) . "'";
		$sql .= ", " . self::STATUS_NOT_PROCESSED;
		$sql .= ", '" . $this->db->idate($now) . "'";
		$sql .= ")";

		$this->db->begin();

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->errors[] = 'Error ' . $this->db->lasterror();
			dol_syslog(__METHOD__ . " SQL: " . $sql . "; Error: " . $this->db->lasterror(), LOG_ERR);
			$error++;
		}

		if (!$error) {
			$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX . 'ecommerce_pending_webhooks');
		}

		// Commit or rollback
		if ($error) {
			$this->db->rollback();
			return -1 * $error;
		} else {
			$this->db->commit();
			return $this->id;
		}
	}

	/**
	 * Delete webhook
	 *
	 * @param 	int		$row_id		WebHook line ID
	 * @return	int 				<0 if KO, >0 if OK
	 */
	public function delete($row_id  = 0)
	{
		dol_syslog(__METHOD__ . " row_id=$row_id", LOG_DEBUG);

		if (!($row_id > 0)) $row_id = $this->id;
		$error = 0;

		// Delete webhook
		$sql = 'DELETE FROM ' . MAIN_DB_PREFIX . 'ecommerce_pending_webhooks WHERE rowid = ' . $row_id;

		$this->db->begin();

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->errors[] = 'Error ' . $this->db->lasterror();
			dol_syslog(__METHOD__ . " SQL: " . $sql . "; Error: " . $this->db->lasterror(), LOG_ERR);
			$error++;
		}

		// Commit or rollback
		if ($error) {
			$this->db->rollback();
			return -1 * $error;
		} else {
			$this->db->commit();
			return 1;
		}
	}

	/**
	 * Set status of the webhook to processed
	 *
	 * @param 	int		$row_id		WebHook line ID
	 * @return	int 				<0 if KO, >0 if OK
	 */
	public function setStatusProcessed($row_id)
	{
		dol_syslog(__METHOD__ . " row_id=$row_id", LOG_DEBUG);

		if (!($row_id > 0)) $row_id = $this->id;
		$now = dol_now();
		$error = 0;

		// Set status processed
		$sql = "UPDATE " . MAIN_DB_PREFIX . "ecommerce_pending_webhooks SET" .
			"  status = " . self::STATUS_PROCESSED .
			", datep = '" . $this->db->idate($now) . "'" .
			", datee = NULL" .
			", error_msg = NULL" .
			" WHERE rowid = " . $row_id .
			" AND status IN (" . self::STATUS_NOT_PROCESSED . "," . self::STATUS_ERROR . ")";

		$this->db->begin();

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->errors[] = 'Error ' . $this->db->lasterror();
			dol_syslog(__METHOD__ . " SQL: " . $sql . "; Error: " . $this->db->lasterror(), LOG_ERR);
			$error++;
		}

		// Commit or rollback
		if ($error) {
			$this->db->rollback();
			return -1 * $error;
		} else {
			$this->db->commit();
			return 1;
		}
	}

	/**
	 * Set status of the webhook to processed
	 *
	 * @param 	int		$row_id		WebHook line ID
	 * @param 	string	$error_msg	Error message
	 * @return	int 				<0 if KO, >0 if OK
	 */
	public function setStatusError($row_id, $error_msg)
	{
		dol_syslog(__METHOD__ . " row_id=$row_id", LOG_DEBUG);

		if (!($row_id > 0)) $row_id = $this->id;
		$now = dol_now();
		$error = 0;

		// Set status error
		$sql = "UPDATE " . MAIN_DB_PREFIX . "ecommerce_pending_webhooks SET" .
			"  status = " . self::STATUS_ERROR .
			", datee = '" . $this->db->idate($now) . "'" .
			", error_msg = '" . $this->db->escape($error_msg) . "'" .
			" WHERE rowid = " . $row_id .
			" AND status IN (" . self::STATUS_NOT_PROCESSED . "," . self::STATUS_ERROR . ")";

		$this->db->begin();

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->errors[] = 'Error ' . $this->db->lasterror();
			dol_syslog(__METHOD__ . " SQL: " . $sql . "; Error: " . $this->db->lasterror(), LOG_ERR);
			$error++;
		}

		// Commit or rollback
		if ($error) {
			$this->db->rollback();
			return -1 * $error;
		} else {
			$this->db->commit();
			return 1;
		}
	}

	/**
	 * Synchronize webhook
	 *
	 * @param 	int		$row_id		WebHook line ID
	 * @return	int 				<0 if KO, >0 if OK
	 */
	public function synchronize($site_id = 0, $webhook_topic = '', $webhook_resource = '', $webhook_event = '', $data = array())
	{
		dol_syslog(__METHOD__ . " site_id=$site_id, webhook_topic=$webhook_topic, webhook_resource=$webhook_resource, webhook_event=$webhook_event, data=" . json_encode($data), LOG_DEBUG);

		if (empty($site_id) && $this->site_id > 0) $site_id = $this->site_id;
		if (empty($webhook_topic) && !empty($this->webhook_topic)) $webhook_topic = $this->webhook_topic;
		if (empty($webhook_resource) && !empty($this->webhook_resource)) $webhook_resource = $this->webhook_resource;
		if (empty($webhook_event) && !empty($this->webhook_event)) $webhook_event = $this->webhook_event;
		if (empty($data) && !empty($this->webhook_data)) $data = $this->webhook_data;

		$this->error = '';
		$this->errors = array();

		$synchro = $this->getSynchro($site_id);
		if (!is_object($synchro)) {
			return -1;
		}

		if (empty($webhook_resource) || empty($webhook_event)) {
			$tmp = explode('.', $webhook_topic);
			$webhook_resource = $tmp[0];
			$webhook_event = $tmp[1];
		}

		$result = 0;

		// Order
		if ($webhook_resource == 'order') {
			if ($webhook_event == 'created' || $webhook_event == 'updated') {
				$result = $synchro->synchronizeOrderFromData($data);
			}
		}

		if ($result < 0) {
			$this->error = $synchro->error;
			$this->errors = $synchro->errors;
			return -1;
		}

		return 1;
	}

	/**
	 *  Process all pending webhooks (cron)
	 *
	 *  @return	int				0 if OK, < 0 if KO (this function is used also by cron so only 0 is OK)
	 */
	public function cronProcessPendingWebHooks()
	{
		global $const, $user, $langs;

		if (!$user->rights->ecommerceng->write) {
			$langs->load('errors');
			$this->error = $langs->trans('ErrorForbidden');
			$this->errors = array();
			dol_syslog(__METHOD__ . " Error: " . $this->error, LOG_ERR);
			return -1;
		}

		require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
		$langs->load('ecommerceng@ecommerceng');
		$error = 0;
		$output = '';

		if (empty($const->global->ECOMMERCE_PROCESSING_WEBHOOK_SYNCHRONIZATION)) {
			dolibarr_set_const($this->db, 'ECOMMERCE_PROCESSING_WEBHOOK_SYNCHRONIZATION', dol_print_date(dol_now(), 'dayhour'), 'chaine', 1, 'Token the processing of the synchronization of the site by webhooks', 0);

			$sql = "SELECT rowid, site_id, webhook_topic, webhook_resource, webhook_event, webhook_data";
			$sql .= " FROM " . MAIN_DB_PREFIX . "ecommerce_pending_webhooks";
			$sql .= " WHERE status IN (" . self::STATUS_NOT_PROCESSED . (empty($const->global->ECOMMERCE_PROCESSING_WEBHOOK_SYNCHRONIZATION_WITHOUT_ERRORS) ? "," . self::STATUS_ERROR : '') . ")";
			$sql .= " ORDER BY rowid ASC";

			$resql = $this->db->query($sql);
			if (!$resql) {
				dolibarr_del_const($this->db, 'ECOMMERCE_PROCESSING_WEBHOOK_SYNCHRONIZEATION', 0);
				$this->error = 'Error ' . $this->db->lasterror();
				$this->errors = array();
				dol_syslog(__METHOD__ . " SQL: " . $sql . "; Error: " . $this->db->lasterror(), LOG_ERR);
				return -1;
			}

			while ($obj = $this->db->fetch_object($resql)) {
				$result = $this->synchronize($obj->site_id, $obj->webhook_topic, $obj->webhook_resource, $obj->webhook_event, json_decode($obj->webhook_data));
				if ($result > 0) $result = $this->setStatusProcessed($obj->rowid);
				else $this->setStatusError($obj->rowid, $this->errorsToString());
				if ($result < 0) {
					$output .= $langs->trans('ECommerceErrorSynchronizeWebHook', $obj->rowid, $obj->webhook_topic) . ":<br>";
					$output .= '<span style="color: red;">' . $langs->trans('Error') . ': ' . $this->errorsToString() . '</span>' . "<br>";
					$error++;
				}
			}
			$this->db->free($resql);

			if ($error) {
				dolibarr_del_const($this->db, 'ECOMMERCE_PROCESSING_WEBHOOK_SYNCHRONIZATION', 0);
				$this->error = $output;
				$this->errors = array();
				return -1;
			} else {
				$output .= $langs->trans('ECommerceSynchronizeWebHooksSuccess');
			}
		} else {
			$output .= $langs->trans('ECommerceAlreadyProcessingWebHooksSynchronization') . ' (' . $langs->trans('ECommerceSince') . ' : ' . $const->global->ECOMMERCE_PROCESSING_WEBHOOK_SYNCHRONIZATION . ')';
		}

		dolibarr_del_const($this->db, 'ECOMMERCE_PROCESSING_WEBHOOK_SYNCHRONIZATION', 0);

		$this->error = "";
		$this->errors = array();
		$this->output = $output;
		$this->result = array("commandbackuplastdone" => "", "commandbackuptorun" => "");

		return 0;
	}

	/**
	 * Get eCommerceSite handler by site ID
	 *
	 * @param	int						$site_id		Site ID
	 * @return	eCommerceSite|int						<0 if KO, 0 if not found otherwise Site handler
	 */
	public function getSite($site_id)
	{
		global $langs;

		if (!isset(self::$site_cached[$site_id])) {
			dol_include_once("/ecommerceng/class/data/eCommerceSite.class.php");
			$site = new eCommerceSite($this->db);
			$result = $site->fetch($site_id);
			if ($result < 0) {
				$this->error = $site->error;
				$this->errors = $site->errors;
				dol_syslog(__METHOD__ . ' Site ID: ' . $site_id. '; Errors: ' . $this->errorsToString(), LOG_ERR);
				return -1;
			} elseif ($result == 0) {
				$langs->load('errors');
				$this->errors[] = $langs->trans('ErrorRecordNotFound') . '; Site ID: ' . $site_id;
				dol_syslog(__METHOD__ . ' ' . $this->errorsToString(), LOG_ERR);
				return -1;
			}

			self::$site_cached[$site_id] = $site;
		}

		return self::$site_cached[$site_id];
	}

	/**
	 * Get eCommerceSynchro handler by site ID
	 *
	 * @param	int						$site_id		Site ID
	 * @return	eCommerceSynchro|int					<0 if KO, 0 if not found otherwise Synchro handler
	 */
	public function getSynchro($site_id)
	{
		if (!isset(self::$synchro_cached[$site_id])) {
			$site = $this->getSite($site_id);
			if (!is_object($site)) {
				return -1;
			}

			dol_include_once('/ecommerceng/class/business/eCommerceSynchro.class.php');
			$synchro = new eCommerceSynchro($this->db, $site);

			$result = $synchro->connect();
			if ($result < 0) {
				$this->error = $synchro->error;
				$this->errors = $synchro->errors;
				return -1;
			}

			self::$synchro_cached[$site_id] = $synchro;
		}

		return self::$synchro_cached[$site_id];
	}

	/**
	 * Method to output saved errors
	 *
	 * @param   string      $separator      Separator between each error
	 * @return	string		                String with errors
	 */
	public function errorsToString($separator = ', ')
	{
		return $this->error . (is_array($this->errors) ? (!empty($this->error) ? $separator : '') . join($separator, $this->errors) : '');
	}
}