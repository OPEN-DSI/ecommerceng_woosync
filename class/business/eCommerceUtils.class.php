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

dol_include_once('/ecommerceng/class/business/eCommerceSynchro.class.php');
dol_include_once('/ecommerceng/class/data/eCommerceSite.class.php');


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

    //Data access
	public $db;

	/**
	 * @var array 	List of chronometer data
	 */
    protected static $chronometer = array();


    /**
     * Constructor
     *
     * @param Database          $db           Database handler
     * @param eCommerceSite     $site         Object eCommerceSite
     * @param datetime          $toDate       Ending date to synch all data modified before this date (null by default = until now)
     * @param int               $toNb         Max nb of record to count or synch (Used only for synch, not for count for the moment !)
     */
    function __construct($db)
    {
        global $langs, $user;

        $this->langs = $langs;
        $this->user = $user;
        $this->db = $db;
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

