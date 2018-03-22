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


class eCommerceSociete // extends CommonObject
{
	var $db;							//!< To store db handler
	var $error;							//!< To return error code (or message)
	var $errors=array();				//!< To return several error codes (or messages)
	//var $element='ecommerce_societe';			//!< Id that identify managed objects
	//var $table_element='ecommerce_societe';	//!< Name of table without prefix where object is stored

    var $id;
    var $fk_societe;
    var $fk_site;
    var $remote_id;
    var $last_update;

    /**
     *    Database access to ecommerce_societe
     *    @param      DoliDB 		$db      Database handler
     */
    function eCommerceSociete($db)
    {
        $this->db = $db;
        return 1;
    }


    /**
     *      Create in database
     *
     *      @param      User    $user        	User that create
     *      @param      int     $notrigger	    0=launch triggers after, 1=disable triggers
     *      @return     int         	        <0 if KO, Id of created object if OK
     */
    function create($user, $notrigger=0)
    {
    	global $conf, $langs;
		$error=0;

		// Clean parameters
		if (isset($this->fk_societe)) $this->fk_societe=intval($this->fk_societe);
		if (isset($this->fk_site)) $this->fk_site=intval($this->fk_site);
		if (isset($this->remote_id)) $this->remote_id=trim($this->remote_id);
		if (isset($this->last_update)) $this->last_update=trim($this->last_update);

		// Check parameters
		// Put here code to add control on parameters values

        // Insert request
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."ecommerce_societe(";
		$sql.= "fk_societe,";
		$sql.= "fk_site,";
		$sql.= "remote_id,";
		$sql.= "last_update";
        $sql.= ") VALUES (";
		$sql.= " ".(isset($this->fk_societe)?intval($this->fk_societe):0).",";
		$sql.= " ".(isset($this->fk_site)?intval($this->fk_site):0).",";
		$sql.= " ".(isset($this->remote_id)?"'".$this->db->escape($this->remote_id)."'":"").",";
		$sql.= " ".(isset($this->last_update)?"'".$this->last_update."'" : 'null')."";
		$sql.= ")";

		$this->db->begin();

	   	dol_syslog(get_class($this)."::create sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
    	if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }

		if (! $error)
        {
            $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."ecommerce_societe");
            if (! ($this->id > 0)) dol_syslog("last_insert_id did not return an int", LOG_ERR);

			if (! $notrigger)
			{
	            // Uncomment this and change MYOBJECT to your own tag if you
	            // want this action call a trigger.

	            //// Call triggers
	            //include_once(DOL_DOCUMENT_ROOT . "/core/interfaces.class.php");
	            //$interface=new Interfaces($this->db);
	            //$result=$interface->run_triggers('MYOBJECT_CREATE',$this,$user,$langs,$conf);
	            //if ($result < 0) { $error++; $this->errors=$interface->errors; }
	            //// End call triggers
			}
        }

        // Commit or rollback
        if ($error)
		{
			foreach($this->errors as $errmsg)
			{
	            dol_syslog(get_class($this)."::create ".$errmsg, LOG_ERR);
	            $this->error.=($this->error?', '.$errmsg:$errmsg);
			}
			$this->db->rollback();
			return -1*$error;
		}
		else
		{
			$this->db->commit();
            return $this->id;
		}
    }


    /**
     *    Load object in memory from database
     *
     *    @param    int				$id         id object
     *    @return   int         				<0 if KO, >0 if OK
     */
    function fetch($id)
    {
    	global $langs;
        $sql = "SELECT";
		$sql.= " t.rowid,";
		$sql.= " t.fk_societe,";
		$sql.= " t.fk_site,";
		$sql.= " t.remote_id,";
		$sql.= " t.last_update";
		$sql.= " FROM ".MAIN_DB_PREFIX."ecommerce_societe as t";
        $sql.= " WHERE t.rowid = ".$id;

    	dol_syslog(get_class($this)."::fetch sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql)
        {
            if ($this->db->num_rows($resql))
            {
                $obj = $this->db->fetch_object($resql);

                $this->id = $obj->rowid;
                $this->fk_societe = $obj->fk_societe;
                $this->fk_site = $obj->fk_site;
                $this->remote_id = $obj->remote_id;
                $this->last_update = $obj->last_update;
            }
            $this->db->free($resql);

            return 1;
        }
        else
        {
      	    $this->error="Error ".$this->db->lasterror();
            dol_syslog(get_class($this)."::fetch ".$this->error, LOG_ERR);
            return -1;
        }
    }


    /**
     *    Update database
     *
     *    @param      User 	$user        	User that modify
     *    @param      int	$notrigger	    0=launch triggers after, 1=disable triggers
     *    @return     int    		     	<0 if KO, >0 if OK
     */
    function update($user=0, $notrigger=0)
    {
    	global $conf, $langs;
		$error=0;

		// Clean parameters
		if (isset($this->fk_societe)) $this->fk_societe=intval($this->fk_societe);
		if (isset($this->fk_site)) $this->fk_site=intval($this->fk_site);
		if (isset($this->remote_id)) $this->remote_id=trim($this->remote_id);
		if (isset($this->last_update)) $this->last_update=trim($this->last_update);

		// Check parameters
		// Put here code to add control on parameters values

        // Update request
        $sql = "UPDATE ".MAIN_DB_PREFIX."ecommerce_societe SET";

		$sql.= " fk_societe=".(isset($this->fk_societe)?intval($this->fk_societe):0).",";
		$sql.= " fk_site=".(isset($this->fk_site)?intval($this->fk_site):0).",";
		$sql.= " remote_id=".(isset($this->remote_id)?"'".$this->db->escape($this->remote_id)."'":"").",";
		$sql.= " last_update=".(isset($this->last_update)?"'".$this->last_update."'" : 'null')."";

        $sql.= " WHERE rowid=".$this->id;

		$this->db->begin();

		dol_syslog(get_class($this)."::update sql=".$sql, LOG_DEBUG);
        $resql = $this->db->query($sql);
    	if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }

		if (! $error)
		{
			if (! $notrigger)
			{
	            // Uncomment this and change MYOBJECT to your own tag if you
	            // want this action call a trigger.

	            //// Call triggers
	            //include_once(DOL_DOCUMENT_ROOT . "/core/interfaces.class.php");
	            //$interface=new Interfaces($this->db);
	            //$result=$interface->run_triggers('MYOBJECT_MODIFY',$this,$user,$langs,$conf);
	            //if ($result < 0) { $error++; $this->errors=$interface->errors; }
	            //// End call triggers
	    	}
		}

        // Commit or rollback
		if ($error)
		{
			foreach($this->errors as $errmsg)
			{
	            dol_syslog(get_class($this)."::update ".$errmsg, LOG_ERR);
	            $this->error.=($this->error?', '.$errmsg:$errmsg);
			}
			$this->db->rollback();
			return -1*$error;
		}
		else
		{
			$this->db->commit();
			return 1;
		}
    }


 	/**
	 *  Delete object in database
	 *
     *	@param      $user        	User that delete
     *  @param      $notrigger	    0=launch triggers after, 1=disable triggers
     *  @param      $sitename       Site name to update alias
	 *	@return		int				<0 if KO, >0 if OK
	 */
	function delete($user, $notrigger=0, $sitename='')
	{
		global $conf, $langs;
		$error=0;

		dol_syslog("Delete into ecommerce_societe sitename=".$sitename);

		$sql = "DELETE FROM ".MAIN_DB_PREFIX."ecommerce_societe";
		$sql.= " WHERE rowid=".$this->id;

		$sql2 = "UPDATE ".MAIN_DB_PREFIX."societe";
		$sql2.= " SET name_alias = NULL where name_alias = '".$sitename.' id '.$this->remote_id."'";      // Magento id xxx

		$this->db->begin();

		dol_syslog(get_class($this)."::delete sql=".$sql);
		$resql = $this->db->query($sql);
		if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }

		if ($sitename)
		{
    		$resql2 = $this->db->query($sql2);
    		if (! $resql2) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }
		}

		if (! $error)
		{
			if (! $notrigger)
			{
				// Uncomment this and change MYOBJECT to your own tag if you
		        // want this action call a trigger.

		        //// Call triggers
		        //include_once(DOL_DOCUMENT_ROOT . "/core/interfaces.class.php");
		        //$interface=new Interfaces($this->db);
		        //$result=$interface->run_triggers('MYOBJECT_DELETE',$this,$user,$langs,$conf);
		        //if ($result < 0) { $error++; $this->errors=$interface->errors; }
		        //// End call triggers
			}
		}

        // Commit or rollback
		if ($error)
		{
			foreach($this->errors as $errmsg)
			{
	            dol_syslog(get_class($this)."::delete ".$errmsg, LOG_ERR);
	            $this->error.=($this->error?', '.$errmsg:$errmsg);
			}
			$this->db->rollback();
			return -1*$error;
		}
		else
		{
			$this->db->commit();
			return 1;
		}
	}

	/**
	 * Get the last date of the last updated society
	 *
	 * @param $site eCommerceSite id | * for each sites
	 * @return datetime
	 */
	public function getLastUpdate($site)
	{
		global $langs;

        $sql = "SELECT MAX(t.last_update) as lastdate FROM ".MAIN_DB_PREFIX."ecommerce_societe as t";
        $sql.= " WHERE t.fk_site = ".$site;
    	dol_syslog(get_class($this)."::getLastUpdate sql=".$sql, LOG_DEBUG);

    	$lastdate = null;

        $resql=$this->db->query($sql);
        if ($resql)
        {
            if ($this->db->num_rows($resql))
            {
                $obj = $this->db->fetch_object($resql);
				if ($obj->lastdate != null)
                	$lastdate = $this->db->jdate($obj->lastdate);
            }
            $this->db->free($resql);
        }
        else
        {
      	    $this->error="Error ".$this->db->lasterror();
            dol_syslog(get_class($this)."::getLastUpdate ".$this->error, LOG_ERR);
        }
        return $lastdate;
	}

	/**
     *    Load object in memory from database by remote_id
     *
     *    @param	string    $remoteId   remote_id
     *    @param	int       $siteId     fk_site
     *    @return	int                    <0 if KO, >0 if OK
     */
	public function fetchByRemoteId($remoteId, $siteId)
    {
    	global $langs;
        $sql = "SELECT";
		$sql.= " t.rowid,";
		$sql.= " t.fk_societe,";
		$sql.= " t.fk_site,";
		$sql.= " t.remote_id,";
		$sql.= " t.last_update";
        $sql.= " FROM ".MAIN_DB_PREFIX."ecommerce_societe as t";
        $sql.= " WHERE t.fk_site = ".$siteId;
        $sql.= " AND t.remote_id = ".$remoteId;
    	dol_syslog(get_class($this)."::fetchByRemoteId sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql)
        {
            if ($this->db->num_rows($resql)==1)
            {
                $obj = $this->db->fetch_object($resql);
                $this->id    = $obj->rowid;
                $this->fk_societe = $obj->fk_societe;
                $this->fk_site = $obj->fk_site;
                $this->remote_id = $obj->remote_id;
                $this->last_update = $obj->last_update;
           		$this->db->free($resql);
                return 1;
            }
            $this->db->free($resql);
            return -1;
        }
        else
        {
      	    $this->error="Error ".$this->db->lasterror();
            dol_syslog(get_class($this)."::fetchByRemoteId ".$this->error, LOG_ERR);
            return -1;
        }
    }

	/**
     *    Load object in memory from database by fk_societe
     *    @param	$fkSociete int fk_societe
     *    @param	$siteId int fk_site
     *    @return	int <0 if KO, >0 if OK
     */
	public function fetchByFkSociete($fkSociete, $siteId)
    {
    	global $langs;
        $sql = "SELECT";
		$sql.= " t.rowid,";
		$sql.= " t.fk_societe,";
		$sql.= " t.fk_site,";
		$sql.= " t.remote_id,";
		$sql.= " t.last_update";
        $sql.= " FROM ".MAIN_DB_PREFIX."ecommerce_societe as t";
        $sql.= " WHERE t.fk_site = ".$siteId;
        $sql.= " AND t.fk_societe = ".$fkSociete;
    	dol_syslog(get_class($this)."::fetchByFkSociete sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql)
        {
            if ($this->db->num_rows($resql)==1)
            {
                $obj = $this->db->fetch_object($resql);
                $this->id    = $obj->rowid;
                $this->fk_societe = $obj->fk_societe;
                $this->fk_site = $obj->fk_site;
                $this->remote_id = $obj->remote_id;
                $this->last_update = $obj->last_update;
           		$this->db->free($resql);
                return 1;
            }
            $this->db->free($resql);
            return -1;
        }
        else
        {
      	    $this->error="Error ".$this->db->lasterror();
            dol_syslog(get_class($this)."::fetchByFkSociete ".$this->error, LOG_ERR);
            return -1;
        }
    }

	/**
     * 		Select all the ids from eCommerceSociete for a site
     *
     * 		@param int		siteId
     * 		@return array	synchObject ids for this site
     */
    public function getAllECommerceSocieteIds($siteId)
    {
   		global $langs;
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."ecommerce_societe";
        $sql.= " WHERE fk_site = ".$siteId;
    	dol_syslog(get_class($this)."::getAllECommerceSocieteIds sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);

        if ($resql)
        {
        	$idsArray = array();
            $num = $this->db->num_rows($resql);
            $ii = 0;
            while($ii < $num)
            {
            	$obj = $this->db->fetch_object($resql);
            	$idsArray[] = intval($obj->rowid);
            	$ii++;
            }
            $this->db->free($resql);
            return $idsArray;
        }
        else
        {
      	    $this->error="Error ".$this->db->lasterror();
            dol_syslog(get_class($this)."::getAllECommerceSocieteIds ".$this->error, LOG_ERR);
            return -1;
        }
    }
}
