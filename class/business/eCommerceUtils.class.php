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

        $sql = "SELECT sm.fk_product, MAX(sm.datem) AS update_date, GROUP_CONCAT(DISTINCT CONCAT(ep.rowid, ':', ep.fk_site, ':', ep.remote_id) SEPARATOR ';') AS links";
        $sql .= " FROM " . MAIN_DB_PREFIX . "stock_mouvement AS sm";
		$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "ecommerce_product AS ep ON ep.fk_product = sm.fk_product";
        $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "ecommerce_site AS es ON es.rowid = ep.fk_site";
        $sql .= " WHERE es.entity IN (" . getEntity('ecommerceng') . ")";
        $sql .= " AND (ep.last_update_stock IS NULL OR sm.datem > ep.last_update_stock)";
        $sql .= " GROUP BY sm.fk_product";

        $resql = $this->db->query($sql);
        if (!$resql) {
            $output .= '<span style="color: red;">' . $langs->trans('Error') . ': ' . $this->db->lasterror() . '</span>' . "<br>";
            $error++;
        } elseif ($this->db->num_rows($resql) > 0) {
            $eCommerceSite = new eCommerceSite($this->db);
            $sites = $eCommerceSite->listSites('object');

            while ($obj = $this->db->fetch_object($resql)) {
                $update_date = $obj->update_date;
                $links = explode(';', $obj->links);

                $product = new Product($this->db);
                $product->fetch($obj->fk_product);

                foreach ($links as $link) {
                    $sub_error = 0;
                    $link_info = explode(':', $link);
                    $link_id = $link_info[0];
                    $site_id = $link_info[1];
                    $remote_id = $link_info[2];

                    if (isset($sites[$site_id]) && $sites[$site_id]->id > 0) {
                        $site = $sites[$site_id];

						if ($site->stock_sync_direction == 'dolibarr2ecommerce') {
							$eCommerceProduct = new eCommerceProduct($this->db);
							$result = $eCommerceProduct->fetch($link_id);
							if ($result < 0) {
								$output .= '<span style="color: red;">' . $langs->trans('ECommerceUpdateRemoteProductLink', $product->id, $site->name) . ': ' . $eCommerceProduct->error . '</span>' . "<br>";
								$sub_error++;
							} elseif ($result == 0) {
								continue;
							}

							if (!$sub_error) {
								try {
									// Connect to site
									$eCommerceSynchro = new eCommerceSynchro($this->db, $site);
									$eCommerceSynchro->connect();
									if (count($eCommerceSynchro->errors)) {
										$output .= '<span style="color: red;">' . $langs->trans('Error') . ' - Connect to site ' . $site->name . ' : ' . $eCommerceSynchro->errorsToString() . '</span>' . "<br>";
										$sub_error++;
									}

									if (!$sub_error) {
										$result = $eCommerceSynchro->eCommerceRemoteAccess->updateRemoteStockProduct($remote_id, $product, $eCommerceProduct);
										if (!$result) {
											$output .= '<span style="color: red;">' . $langs->trans('Error') . ' - Update stock of ' . $product->ref . ' to site ' . $site->name . ' : ' . $eCommerceSynchro->eCommerceRemoteAccess->errorsToString() . '</span>' . "<br>";
											$sub_error++;
										}
									}
								} catch (Exception $e) {
									$output .= '<span style="color: red;">' . $langs->trans('Error') . ' - Update stock of ' . $product->ref . ' to site ' . $site->name . ' : ' . $e->getMessage() . '</span>' . "<br>";
									$sub_error++;
								}
							}

							if (!$sub_error && $eCommerceProduct->id > 0) { // test for when the link is deleted because the product in WooCommerce is not found
								// Update link
								$eCommerceProduct->last_update_stock = $update_date;
								$result = $eCommerceProduct->update($user);
								if ($result < 0) {
									$output .= '<span style="color: red;">' . $langs->trans('ECommerceUpdateRemoteProductLink', $product->id, $site->name) . ': ' . $eCommerceProduct->error . '</span>' . "<br>";
									$sub_error++;
								}
							}
						}
                    } else {
                        $output .= '<span style="color: red;">' . $langs->trans('Error') . ': Site not found (ID: ' . $site_id . ')</span>' . "<br>";
                        $sub_error++;
                    }

                    if ($sub_error) $error++;
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

