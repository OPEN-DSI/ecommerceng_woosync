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
    private $db;


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
				if ($result >= 0) $result=$synchro->synchProduct($toNb);
				if ($result >= 0) $result=$synchro->synchSociete($toNb);
				if ($result >= 0) $result=$synchro->synchCommande($toNb);
				if ($result >= 0) $result=$synchro->synchFacture($toNb);
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

}

