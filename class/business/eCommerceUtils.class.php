<?php
/* Copyright (C) 2013 Laurent Destailleur          <eldy@users.sourceforge.net>
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
 * File of class with method used by scheduled job to launch automatic synchronization
 */

require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
dol_include_once('/ecommerceng/class/business/eCommerceSynchro.class.php');
dol_include_once('/ecommerceng/class/data/eCommerceSite.class.php');
dol_include_once('/ecommerceng/class/data/eCommerceProduct.class.php');


/**
 * Class with method used by scheduled job to launch automatic synchronization
 */
class eCommerceUtils
{
    public $error;
    public $errors=array();
    public $success;
    public $langs;
    public $user;

    /**
     * @var DoliDB    Database handler
     */
	public $db;

	/**
	 * @var array 	List of chronometer data
	 */
    protected static $chronometer = array();


    /**
     * Constructor
     *
     * @param DoliDB            $db           Database handler
     */
    function __construct($db)
    {
        global $langs, $user;

        $this->langs = $langs;
        $this->user = $user;
        $this->db = $db;
    }

    /**
     *  Synchronize all new movement stocks from dolibarr to site (cron)
     *
     *  @return	int				0 if OK, < 0 if KO (this function is used also by cron so only 0 is OK)
     */
    public function cronSynchronizeStocksToSite()
    {
        global $conf, $user, $langs;

        $langs->load('ecommerceng@ecommerceng');
        $error = 0;
        $output = '';

        $stopwatch_id = eCommerceUtils::startAndLogStopwatch(__METHOD__);

		$eCommerceSite = new eCommerceSite($this->db);
		$sites = $eCommerceSite->listSites('object');

		foreach ($sites as $site) {
			$output .= $langs->trans('ECommerceSynchronizeStockToSite', $site->name) . " :<br>";
			if ($site->stock_sync_direction != 'dolibarr2ecommerce') {
				$output .= $langs->trans('None') . "<br>";
				continue;
			}

			$eCommerceSynchro = new eCommerceSynchro($this->db, $site);
			$update_virtual_stock = !empty($site->parameters['update_virtual_stock']);
			$date_now = $this->db->idate(dol_now());

			// Connect to site
			try {
				$eCommerceSynchro->connect();
				if (count($eCommerceSynchro->errors)) {
					$output .= '<span style="color: red;">' . $langs->trans('Error') . ' - Connect to site ' . $site->name . ' : ' . $eCommerceSynchro->errorsToString() . '</span>' . "<br>";
					$error++;
					continue;
				}
			} catch (Exception $e) {
				$output .= '<span style="color: red;">' . $langs->trans('Error') . ' - Connect to site ' . $site->name . ' : ' . $e->getMessage() . '</span>' . "<br>";
				$error++;
				continue;
			}

			$sql = "SELECT ep.rowid AS link_id, ep.fk_product, ep.remote_id";
			if ($update_virtual_stock) {
				$sql .= ", GREATEST(COALESCE(sm.max_date, '1971-01-01')";
				// Order
				if (!empty($conf->commande->enabled)) {
					$sql .= ", COALESCE(cv.max_date, '1971-01-01')";
				}
				// Shipping
				if (!empty($conf->expedition->enabled)) {
					$sql .= ", COALESCE(sv.max_date, '1971-01-01')";
				}
				// Supplier order
				if ((!empty($conf->fournisseur->enabled) && empty($conf->global->MAIN_USE_NEW_SUPPLIERMOD)) || !empty($conf->supplier_order->enabled)) {
					$sql .= ", COALESCE(sov.max_date, '1971-01-01')";
				}
				// Delivery order
				if (((!empty($conf->fournisseur->enabled) && empty($conf->global->MAIN_USE_NEW_SUPPLIERMOD)) || !empty($conf->supplier_order->enabled) || !empty($conf->supplier_invoice->enabled))) {
					$sql .= ", COALESCE(dov.max_date, '1971-01-01')";
				}
				// Mrp
				if (!empty($conf->mrp->enabled)) {
					$sql .= ", COALESCE(mv.max_date, '1971-01-01')";
				}
				$sql .= ") AS update_date";
			} else {
				$sql .= ", COALESCE(sm.max_date, '1971-01-01') AS update_date";
			}
			$sql .= " FROM " . MAIN_DB_PREFIX . "ecommerce_product AS ep";
			$sql .= " LEFT JOIN (";
			$sql .= "   SELECT fk_product, MAX(datem) AS max_date";
			$sql .= "   FROM " . MAIN_DB_PREFIX . "stock_mouvement";
			$sql .= "   GROUP BY fk_product";
			$sql .= ") AS sm ON sm.fk_product = ep.fk_product";
			if ($update_virtual_stock) {
				// Order
				if (!empty($conf->commande->enabled)) {
					$sql .= " LEFT JOIN (";
					$sql .= "   SELECT cd.fk_product, MAX(c.tms) AS max_date";
					$sql .= "   FROM " . MAIN_DB_PREFIX . "commandedet AS cd";
					$sql .= "   LEFT JOIN " . MAIN_DB_PREFIX . "commande AS c ON c.rowid = cd.fk_commande";
					$sql .= "   GROUP BY cd.fk_product";
					$sql .= ") AS cv ON cv.fk_product = ep.fk_product";
				}
				// Shipping
				if (!empty($conf->expedition->enabled)) {
					$sql .= " LEFT JOIN (";
					$sql .= "   SELECT cd.fk_product, GREATEST(MAX(e.tms), COALESCE(MAX(c.tms), '1971-01-01')) AS max_date";
					$sql .= "   FROM " . MAIN_DB_PREFIX . "expeditiondet AS ed";
					$sql .= "   LEFT JOIN " . MAIN_DB_PREFIX . "expedition AS e ON e.rowid = ed.fk_expedition";
					$sql .= "   LEFT JOIN " . MAIN_DB_PREFIX . "commandedet AS cd ON cd.rowid = ed.fk_origin_line";
					$sql .= "   LEFT JOIN " . MAIN_DB_PREFIX . "commande AS c ON c.rowid = cd.fk_commande";
					$sql .= "   GROUP BY cd.fk_product";
					$sql .= ") AS sv ON sv.fk_product = ep.fk_product";
				}
				// Supplier order
				if ((!empty($conf->fournisseur->enabled) && empty($conf->global->MAIN_USE_NEW_SUPPLIERMOD)) || !empty($conf->supplier_order->enabled)) {
					$sql .= " LEFT JOIN (";
					$sql .= "   SELECT cfd.fk_product, MAX(cf.tms) AS max_date";
					$sql .= "   FROM " . MAIN_DB_PREFIX . "commande_fournisseurdet AS cfd";
					$sql .= "   LEFT JOIN " . MAIN_DB_PREFIX . "commande_fournisseur AS cf ON cf.rowid = cfd.fk_commande";
					$sql .= "   GROUP BY cfd.fk_product";
					$sql .= ") AS sov ON sov.fk_product = ep.fk_product";
				}
				// Delivery order
				if (((!empty($conf->fournisseur->enabled) && empty($conf->global->MAIN_USE_NEW_SUPPLIERMOD)) || !empty($conf->supplier_order->enabled) || !empty($conf->supplier_invoice->enabled))) {
					$sql .= " LEFT JOIN (";
					$sql .= "   SELECT cfd.fk_product, MAX(cf.tms) AS max_date";
					$sql .= "   FROM " . MAIN_DB_PREFIX . "commande_fournisseur_dispatch AS cfd";
					$sql .= "   LEFT JOIN " . MAIN_DB_PREFIX . "commande_fournisseur AS cf ON cf.rowid = cfd.fk_commande";
					$sql .= "   GROUP BY cfd.fk_product";
					$sql .= ") AS dov ON dov.fk_product = ep.fk_product";
				}
				// Mrp
				if (!empty($conf->mrp->enabled)) {
					$sql .= " LEFT JOIN (";
					$sql .= "   SELECT mp.fk_product, MAX(mp.tms) AS max_date";
					$sql .= "   FROM " . MAIN_DB_PREFIX . "mrp_production AS mp";
					$sql .= "   GROUP BY mp.fk_product";
					$sql .= ") AS mv ON mv.fk_product = ep.fk_product";
				}
			}
			$sql .= " WHERE ep.fk_site = " . $site->id;
			$sql .= " AND (ep.last_update_stock IS NULL OR (sm.max_date IS NOT NULL AND (sm.max_date > ep.last_update_stock";
			if ($update_virtual_stock) {
				// Order
				if (!empty($conf->commande->enabled)) {
					$sql .= " OR cv.max_date > ep.last_update_stock";
				}
				// Shipping
				if (!empty($conf->expedition->enabled)) {
					$sql .= " OR sv.max_date > ep.last_update_stock";
				}
				// Supplier order
				if ((!empty($conf->fournisseur->enabled) && empty($conf->global->MAIN_USE_NEW_SUPPLIERMOD)) || !empty($conf->supplier_order->enabled)) {
					$sql .= " OR sov.max_date > ep.last_update_stock";
				}
				// Delivery order
				if (((!empty($conf->fournisseur->enabled) && empty($conf->global->MAIN_USE_NEW_SUPPLIERMOD)) || !empty($conf->supplier_order->enabled) || !empty($conf->supplier_invoice->enabled))) {
					$sql .= " OR dov.max_date > ep.last_update_stock";
				}
				// Mrp
				if (!empty($conf->mrp->enabled)) {
					$sql .= " OR mv.max_date > ep.last_update_stock";
				}
			}
			$sql .= ")))";
			$sql .= " GROUP BY ep.rowid, ep.fk_product, ep.remote_id";

			$resql = $this->db->query($sql);
			if (!$resql) {
				$output .= '<span style="color: red;">' . $langs->trans('Error') . ': ' . $this->db->lasterror() . '</span>' . "<br>";
				$error++;
				continue;
			}

			while ($obj = $this->db->fetch_object($resql)) {
				$eCommerceProduct = new eCommerceProduct($this->db);
				$result = $eCommerceProduct->fetch($obj->link_id);
				if ($result < 0) {
					$output .= '<span style="color: red;">' . $langs->trans('ECommerceErrorFetchProductLink', $obj->fk_product, $site->name) . ': ' . $eCommerceProduct->error . '</span>' . "<br>";
					$error++;
					continue;
				} elseif ($result == 0) {
					continue;
				}

				$product = new Product($this->db);
				$result = $product->fetch($obj->fk_product);
				if ($result < 0) {
					$output .= '<span style="color: red;">' . $langs->trans('ECommerceErrorFetchProduct', $obj->fk_product) . ': ' . $product->errorsToString() . '</span>' . "<br>";
					$error++;
					continue;
				}

				try {
					$result = $eCommerceSynchro->eCommerceRemoteAccess->updateRemoteStockProduct($obj->remote_id, $product, $eCommerceProduct);
					if (!$result) {
						$output .= '<span style="color: red;">' . $langs->trans('Error') . ' - Update stock of ' . $product->ref . ' to site ' . $site->name . ' : ' . $eCommerceSynchro->eCommerceRemoteAccess->errorsToString() . '</span>' . "<br>";
						$error++;
						continue;
					}
				} catch (Exception $e) {
					$output .= '<span style="color: red;">' . $langs->trans('Error') . ' - Update stock of ' . $product->ref . ' to site ' . $site->name . ' : ' . $e->getMessage() . '</span>' . "<br>";
					$error++;
					continue;
				}

				if ($eCommerceProduct->id > 0) { // test for when the link is deleted because the product in WooCommerce is not found
					// Update link
					$eCommerceProduct->last_update_stock = isset($obj->update_date) ? $obj->update_date : $date_now;
					$result = $eCommerceProduct->update($user);
					if ($result < 0) {
						$output .= '<span style="color: red;">' . $langs->trans('ECommerceUpdateRemoteProductLink', $product->id, $site->name) . ': ' . $eCommerceProduct->error . '</span>' . "<br>";
						$error++;
					}
				}
			}

			$this->db->free($resql);
		}

        eCommerceUtils::stopAndLogStopwatch($stopwatch_id);

        if (!$error) {
            $output .= $langs->trans('ECommerceSynchronizeStocksToSiteSuccess');
            $this->error = "";
            $this->errors = array();
            $this->output = $output;
            $this->result = array("commandbackuplastdone" => "", "commandbackuptorun" => "");

            return 0;
        } else {
            $output = $langs->trans('ECommerceErrorWhenSynchronizeStocksToSite') . ":<br>" . $output;
            dol_syslog(__METHOD__ . " Error: " . $output, LOG_ERR);

            $this->error = $output;
            $this->errors = array();
            return -1;
        }
    }

    /**
     * 	Sync all
	 *  CAN BE A CRON TASK
     *
     *  @param  int     $toNb       Max nb to synch
	 *  @return	int					0 if OK, < 0 if KO (this function is used also by cron so only 0 is OK)
     */
    public function synchAll($toNb=0)
    {
    	global $db;

    	$toDate = 0;

    	$this->output = '';
    	$this->error='';

    	$error=0;

    	$eCommerceSite = new eCommerceSite($db);
    	$sites = $eCommerceSite->listSites('object');

    	// Loop on each site
		foreach($sites as $site)
		{
	    	$site->cleanOrphelins();

	    	$synchro = new eCommerceSynchro($db, $site, $toDate, $toNb);          // $synchro->toDate will be set to dol_now if toDate no defined.

	    	dol_syslog("site.php Try to connect to eCommerce site ".$site->name);
	    	$synchro->connect();
	    	if (count($synchro->errors))
	    	{
	    		$error++;
	    		setEventMessages($synchro->error, $synchro->errors, 'errors');
	    	}

			if (! $error)
			{
		    	$result=0;
				if ($result >= 0) $result=$synchro->synchCategory($toNb);
				if ($result >= 0) $result=$synchro->synchProduct(array(), $toNb);
				if ($result >= 0) $result=$synchro->synchSociete(array(), $toNb);
				if ($result >= 0) $result=$synchro->synchCommande(array(), $toNb);
				if ($result >= 0) $result=$synchro->synchFacture($toNb);

				if ($result >= 0) $result=$synchro->synchDtoECategory($toNb);
				if ($result >= 0) $result=$synchro->synchDtoEProduct($toNb);
			}

			if ($error || $result < 0)
			{
				$this->output .= 'Error during automatic synchronization of site '.$site->name."\n";
				$this->error .= 'Error during automatic synchronization of site '.$site->name.": ".$synchro->error;
			}
			else
			{
				$this->output .= 'Automatic synchronization of site '.$site->name.' done'."\n";
			}
		}

		if (! $error) return 0;
		else return 1;
    }

	/**
	 * Start stopwatch
	 *
	 * @param 	string		$log_label		Log label
	 * @return	int							ID of the started stopwatch
	 */
	static public function startStopwatch($log_label)
	{
		$stopwatch_id = self::_getNextFreeStopwatchId();

		self::$chronometer[$stopwatch_id] = array(
			'start_time'	=> microtime(true),
			'log_label'		=> $log_label,
		);

		return $stopwatch_id;
	}

	/**
	 * Start stopwatch and log
	 *
	 * @param 	string		$log_label		Log label
	 * @return	int							ID of the started stopwatch
	 */
	static public function startAndLogStopwatch($log_label)
	{
		global $conf;

		$stopwatch_id = self::startStopwatch($log_label);
		$data = self::$chronometer[$stopwatch_id];

		if (!empty($conf->global->ECOMMERCENG_LOG_PROCESSING_TIMES)) {
			dol_syslog("Stopwatch " . sprintf("%04d", $stopwatch_id) . " - " . $data['log_label'] . " - Start", LOG_ALERT);
		}

		return $stopwatch_id;
	}

	/**
	 * Get next free stopwatch ID
	 *
	 * @return int				 The next free stopwatch ID
	 */
	static protected function _getNextFreeStopwatchId()
	{
		$stopwatch_id = 0;

		if (!empty(self::$chronometer)) {
			$stopwatch_id = max(array_keys(self::$chronometer)) + 1;
		}

		return $stopwatch_id;
	}

	/**
	 * Stop stopwatch
	 *
	 * @param	int		$stopwatch_id		ID of the started stopwatch
	 * @return	int							Elapsed time (in second), =-1 if stopwatch not exist
	 */
	static public function stopStopwatch($stopwatch_id)
	{
		if (isset(self::$chronometer[$stopwatch_id])) {
			$elapsed_time = microtime(true) - self::$chronometer[$stopwatch_id]['start_time'];

			unset(self::$chronometer[$stopwatch_id]);

			return $elapsed_time;
		}

		return -1;
	}

	/**
	 * Stop and log stopwatch
	 *
	 * @param	int		$stopwatch_id		ID of the started stopwatch
	 * @return	void
	 */
	static public function stopAndLogStopwatch($stopwatch_id)
	{
		global $conf;

		if (isset(self::$chronometer[$stopwatch_id]) && !empty($conf->global->ECOMMERCENG_LOG_PROCESSING_TIMES)) {
			$data = self::$chronometer[$stopwatch_id];
			$elapsed_time = microtime(true) - $data['start_time'];

			dol_syslog("Stopwatch " . sprintf("%04d", $stopwatch_id) . " - " . $data['log_label'] . " - Elapsed time : " . self::microTimeToTime($elapsed_time), LOG_ALERT);

			unset(self::$chronometer[$stopwatch_id]);
		}
	}

	/**
	 * Convert micro time to string time
	 *
	 * @param	int			$micro_time		Micro time
	 * @return	string						Time formatted (Hours:Minutes:Seconds)
	 */
	public static function microTimeToTime($micro_time)
	{
		$hours = (int)($micro_time / 60 / 60);
		$minutes = (int)(($micro_time / 60) - $hours * 60);
		$seconds = $micro_time - $hours * 60 * 60 - $minutes * 60;
		return sprintf("%02d:%02d:%09.6f", $hours, $minutes, $seconds);
	}
}

