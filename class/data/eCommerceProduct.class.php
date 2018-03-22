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


class eCommerceProduct // extends CommonObject
{
	var $db;							//!< To store db handler
	var $error;							//!< To return error code (or message)
	var $errors=array();				//!< To return several error codes (or messages)
	//var $element='ecommerce_product';			//!< Id that identify managed objects
	//var $table_element='ecommerce_product';	//!< Name of table without prefix where object is stored

    var $id;
    var $fk_product;
    var $fk_site;
    var $remote_id;
    var $last_update;

    /**
     *    Constructor
     *    @param      DoliDB			$db      	Database handler
     */
    function eCommerceProduct($db)
    {
        $this->db = $db;
        return 1;
    }


    /**
     *      Create in database
     *
     *      @param      User    $user        	User that create
     *      @param      int     $notrigger	    0=launch triggers after, 1=disable triggers
     *      @return     int                    	<0 if KO, Id of created object if OK
     */
    function create($user, $notrigger=0)
    {
    	global $conf, $langs;
		$error=0;

		// Clean parameters
		if (isset($this->fk_product)) $this->fk_product=intval($this->fk_product);
		if (isset($this->fk_site)) $this->fk_site=intval($this->fk_site);
		if (isset($this->remote_id)) $this->remote_id=trim($this->remote_id);
		if (isset($this->last_update)) $this->last_update=trim($this->last_update);

		// Check parameters
		// Put here code to add control on parameters values

        // Insert request
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."ecommerce_product(";
		$sql.= "fk_product,";
		$sql.= "fk_site,";
		$sql.= "remote_id,";
		$sql.= "last_update";
        $sql.= ") VALUES (";
		$sql.= " ".(isset($this->fk_product)?intval($this->fk_product):0).",";
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
            $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."ecommerce_product");

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
     *    @param      int				$id          id object
     *    @return     int         					 <0 if KO, >0 if OK
     */
    function fetch($id)
    {
    	global $langs;
        $sql = "SELECT";
		$sql.= " t.rowid,";
		$sql.= " t.fk_product,";
		$sql.= " t.fk_site,";
		$sql.= " t.remote_id,";
		$sql.= " t.last_update";
        $sql.= " FROM ".MAIN_DB_PREFIX."ecommerce_product as t";
        $sql.= " WHERE t.rowid = ".$id;

    	dol_syslog(get_class($this)."::fetch sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql)
        {
            if ($this->db->num_rows($resql))
            {
                $obj = $this->db->fetch_object($resql);

                $this->id = $obj->rowid;
                $this->fk_product = $obj->fk_product;
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
     *    @param      User  $user        	User that modify
     *    @param      int   $notrigger	    0=launch triggers after, 1=disable triggers
     *    @return     int                	<0 if KO, >0 if OK
     */
    function update($user=0, $notrigger=0)
    {
    	global $conf, $langs;
		$error=0;

		// Clean parameters
		if (isset($this->fk_product)) $this->fk_product=intval($this->fk_product);
		if (isset($this->fk_site)) $this->fk_site=intval($this->fk_site);
		if (isset($this->remote_id)) $this->remote_id=trim($this->remote_id);
		if (isset($this->last_update)) $this->last_update=trim($this->last_update);

		// Check parameters
		// Put here code to add control on parameters values

        // Update request
        $sql = "UPDATE ".MAIN_DB_PREFIX."ecommerce_product SET";

		$sql.= " fk_product=".(isset($this->fk_product)?intval($this->fk_product):0).",";
		$sql.= " fk_site=".(isset($this->fk_site)?intval($this->fk_site):0).",";
		$sql.= " remote_id='".$this->db->escape($this->remote_id)."',";
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
     *	@param      User    $user        	User that delete
     *  @param      int     $notrigger	    0=launch triggers after, 1=disable triggers
	 *	@return		int				        <0 if KO, >0 if OK
	 */
	function delete($user, $notrigger=0)
	{
		global $conf, $langs;
		$error=0;

		$sql = "DELETE FROM ".MAIN_DB_PREFIX."ecommerce_product";
		$sql.= " WHERE rowid=".$this->id;

		$this->db->begin();

		dol_syslog(get_class($this)."::delete sql=".$sql);
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
	 * Get the last date of the last updated product
	 *
	 * @param  int         $siteId         eCommerceSite id | * for each sites
	 * @return datetime
	 */
	public function getLastUpdate($siteId)
	{
		global $langs;
        $sql = "SELECT MAX(t.last_update) as lastdate FROM ".MAIN_DB_PREFIX."ecommerce_product as t";
        $sql.= " WHERE t.fk_site = ".$siteId;
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
     *    @param	int    $remoteId       string remote_id
     *    @param	int    $siteId         int fk_site
     *    @return	int                    <0 if KO, >0 if OK
     */
	public function fetchByRemoteId($remoteId, $siteId)
    {
    	global $langs;
        $sql = "SELECT";
		$sql.= " t.rowid,";
		$sql.= " t.fk_product,";
		$sql.= " t.fk_site,";
		$sql.= " t.remote_id,";
		$sql.= " t.last_update";
        $sql.= " FROM ".MAIN_DB_PREFIX."ecommerce_product as t";
        $sql.= " WHERE t.fk_site = ".$siteId;
        $sql.= " AND t.remote_id = '".$this->db->escape($remoteId)."'";
    	dol_syslog(get_class($this)."::fetchByRemoteId sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql)
        {
            if ($this->db->num_rows($resql)==1)
            {
                $obj = $this->db->fetch_object($resql);
                $this->id    = $obj->rowid;
                $this->fk_product = $obj->fk_product;
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
     *    Load object in memory from database by remote_id
     *
     *    @param	int    $productId  string product_id
     *    @param	int    $siteId     int fk_site
     *    @return	int                <0 if KO, >0 if OK
     */
	public function fetchByProductId($productId, $siteId)
    {
    	global $langs;
        $sql = "SELECT";
		$sql.= " t.rowid,";
		$sql.= " t.fk_product,";
		$sql.= " t.fk_site,";
		$sql.= " t.remote_id,";
		$sql.= " t.last_update";
        $sql.= " FROM ".MAIN_DB_PREFIX."ecommerce_product as t";
        $sql.= " WHERE t.fk_site = ".$siteId;
        $sql.= " AND t.fk_product = ".$productId;
    	dol_syslog(get_class($this)."::fetchByProductId sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql)
        {
            if ($this->db->num_rows($resql)==1)
            {
                $obj = $this->db->fetch_object($resql);
                $this->id    = $obj->rowid;
                $this->fk_product = $obj->fk_product;
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
            dol_syslog(get_class($this)."::fetchByProductId ".$this->error, LOG_ERR);
            return -1;
        }
    }

    /**
     * 		Select all the ids from eCommerceProduct for a site
     *
     * 		@param int		$siteId    Id site
     * 		@return array	           synchObject ids for this site
     */
    public function getAllECommerceProductIds($siteId)
    {
   		global $langs;
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."ecommerce_product";
        $sql.= " WHERE fk_site = ".$siteId;
    	dol_syslog(get_class($this)."::getAllECommerceProductIds sql=".$sql, LOG_DEBUG);
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
            dol_syslog(get_class($this)."::getAllECommerceProductIds ".$this->error, LOG_ERR);
            return -1;
        }
    }
}
