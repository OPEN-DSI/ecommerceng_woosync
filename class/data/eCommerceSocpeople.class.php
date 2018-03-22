<?php
/* Copyright (C) 2010 Franck Charpentier - Auguria <franck.charpentier@auguria.net>
 * Copyright (C) 2013 Laurent Destailleur          <eldy@users.sourceforge.net>
 * Copyright (C) 2017 Open-DSI                     <support@open-dsi.fr>
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

class eCommerceSocpeople // extends CommonObject
{
	var $db;							//!< To store db handler
	var $error;							//!< To return error code (or message)
	var $errors=array();				//!< To return several error codes (or messages)
	//var $element='ecommerce_socpeople';			//!< Id that identify managed objects
	//var $table_element='ecommerce_socpeople';	//!< Name of table without prefix where object is stored

    var $id;
    var $fk_socpeople;
    var $fk_site;
    var $remote_id;
    var $type;
    var $last_update;

    const CONTACT_TYPE_COMPANY = 1;
    const CONTACT_TYPE_ORDER = 2;
    const CONTACT_TYPE_INVOICE = 3;
    const CONTACT_TYPE_DELIVERY = 4;

    /**
     *    Constructor
     *
     *    @param	DoliDB		$DB      Database handler
     */
    function eCommerceSocpeople($DB)
    {
        $this->db = $DB;
        return 1;
    }


    /**
     *    Create in database
     *
     *    @param      User	$user        	User that create
     *    @param      int	$notrigger	    0=launch triggers after, 1=disable triggers
     *    @return     int    		     	<0 if KO, Id of created object if OK
     */
    function create($user, $notrigger=0)
    {
    	global $conf, $langs;
		$error=0;

		// Clean parameters
		if (isset($this->fk_socpeople)) $this->fk_socpeople=intval($this->fk_socpeople);
		if (isset($this->fk_site)) $this->fk_site=intval($this->fk_site);
		if (isset($this->remote_id)) $this->remote_id=trim($this->remote_id);
		if (isset($this->type)) $this->type=intval($this->type);
		if (isset($this->last_update)) $this->last_update=trim($this->last_update);

		// Check parameters
		// Put here code to add control on parameters values

        // Insert request
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."ecommerce_socpeople(";

		$sql.= "fk_socpeople,";
		$sql.= "fk_site,";
		$sql.= "remote_id,";
		$sql.= "type,";
		$sql.= "last_update";

        $sql.= ") VALUES (";

		$sql.= " ".(isset($this->fk_socpeople)?intval($this->fk_socpeople):0).",";
		$sql.= " ".(isset($this->fk_site)?intval($this->fk_site):0).",";
		$sql.= " ".(isset($this->remote_id)?"'".$this->remote_id."'":"").",";
		$sql.= " ".(isset($this->type)?intval($this->type):1).",";
		$sql.= " ".(isset($this->last_update)?"'".$this->db->idate($this->last_update)."'" : 'null')."";

		$sql.= ")";

		$this->db->begin();

	   	dol_syslog(get_class($this)."::create sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
    	if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }

		if (! $error)
        {
            $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."ecommerce_socpeople");

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
     *    @param      int   $id          id object
     *    @return     int                <0 if KO, >0 if OK
     */
    function fetch($id)
    {
    	global $langs;
        $sql = "SELECT";
		$sql.= " t.rowid,";
		$sql.= " t.fk_socpeople,";
		$sql.= " t.fk_site,";
		$sql.= " t.remote_id,";
		$sql.= " t.type,";
		$sql.= " t.last_update";
		$sql.= " FROM ".MAIN_DB_PREFIX."ecommerce_socpeople as t";
        $sql.= " WHERE t.rowid = ".$id;

    	dol_syslog(get_class($this)."::fetch sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql)
        {
            if ($this->db->num_rows($resql))
            {
                $obj = $this->db->fetch_object($resql);

                $this->id = $obj->rowid;
                $this->fk_socpeople = $obj->fk_socpeople;
                $this->fk_site = $obj->fk_site;
                $this->remote_id = $obj->remote_id;
                $this->type = $obj->type;
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
     *      \brief      Update database
     *      \param      user        	User that modify
     *      \param      notrigger	    0=launch triggers after, 1=disable triggers
     *      \return     int         	<0 if KO, >0 if OK
     */
    function update($user=0, $notrigger=0)
    {
    	global $conf, $langs;
		$error=0;

		// Clean parameters
		if (isset($this->fk_socpeople)) $this->fk_socpeople=intval($this->fk_socpeople);
		if (isset($this->fk_site)) $this->fk_site=intval($this->fk_site);
		if (isset($this->remote_id)) $this->remote_id=trim($this->remote_id);
		if (isset($this->type)) $this->type=intval($this->type);
		if (isset($this->last_update)) $this->last_update=trim($this->last_update);

		// Check parameters
		// Put here code to add control on parameters values

        // Update request
        $sql = "UPDATE ".MAIN_DB_PREFIX."ecommerce_socpeople SET";

		$sql.= " fk_socpeople=".(isset($this->fk_socpeople)?intval($this->fk_socpeople):0).",";
		$sql.= " fk_site=".(isset($this->fk_site)?intval($this->fk_site):0).",";
		$sql.= " remote_id=".(isset($this->remote_id)?"'".$this->db->escape($this->remote_id)."'":"").",";
		$sql.= " type=".(isset($this->type)?intval($this->type):1).",";
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
	 *   \brief      Delete object in database
     *	\param      user        	User that delete
     *   \param      notrigger	    0=launch triggers after, 1=disable triggers
	 *	\return		int				<0 if KO, >0 if OK
	 */
	function delete($user, $notrigger=0)
	{
		global $conf, $langs;
		$error=0;

		$sql = "DELETE FROM ".MAIN_DB_PREFIX."ecommerce_socpeople";
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
     *    Load object in memory from database by remote_id
     *
     *    @param	$remoteId string remote_id
     *    @param	$siteId int fk_site
     *    @param	$typeId int contact type (1=>'company', 2=>'order', 3=>'invoice', 4=>'delivery')
     *    @return	int <0 if KO, >0 if OK
     */
	public function fetchByRemoteId($remoteId, $typeId, $siteId)
    {
    	// Clean parameters
		if (isset($remoteId)) $remoteId=trim($remoteId);
		if (isset($typeId)) $typeId=intval($typeId);
		if (isset($siteId)) $siteId=intval($siteId);

    	global $langs;
        $sql = "SELECT";
		$sql.= " t.rowid,";
		$sql.= " t.fk_socpeople,";
		$sql.= " t.fk_site,";
		$sql.= " t.remote_id,";
		$sql.= " t.type,";
		$sql.= " t.last_update";
        $sql.= " FROM ".MAIN_DB_PREFIX."ecommerce_socpeople as t";
        $sql.= " WHERE t.fk_site = ".$siteId;
        $sql.= " AND t.type = ".$typeId;
        $sql.= " AND t.remote_id = '".$remoteId."'";
    	dol_syslog(get_class($this)."::fetchByRemoteId sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql)
        {
            if ($this->db->num_rows($resql)==1)
            {
                $obj = $this->db->fetch_object($resql);
                $this->id    = $obj->rowid;
                $this->fk_socpeople = $obj->fk_socpeople;
                $this->fk_site = $obj->fk_site;
                $this->remote_id = $obj->remote_id;
                $this->type = $obj->type;
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
     *    Load object in memory from database by fkContact
     *
     *    @param	$fkSocpeople int fk_contact
     *    @param	$siteId int fk_site
     *    @return	int <0 if KO, >0 if OK
     */
    public function fetchByFkSocpeople($fkSocpeople, $siteId)
    {
        // Clean parameters
        if (isset($fkSocpeople)) $fkSocpeople=trim($fkSocpeople);
        if (isset($siteId)) $siteId=intval($siteId);

        global $langs;
        $sql = "SELECT";
        $sql.= " t.rowid,";
        $sql.= " t.fk_socpeople,";
        $sql.= " t.fk_site,";
        $sql.= " t.remote_id,";
        $sql.= " t.type,";
        $sql.= " t.last_update";
        $sql.= " FROM ".MAIN_DB_PREFIX."ecommerce_socpeople as t";
        $sql.= " WHERE t.fk_site = ".$siteId;
        $sql.= " AND t.type = 1";       // TODO what about other types ?
        $sql.= " AND t.fk_socpeople = ".$fkSocpeople;
        dol_syslog(get_class($this)."::fetchByFkSocpeople sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql)
        {
            if ($this->db->num_rows($resql) > 0)
            {
                $obj = $this->db->fetch_object($resql);
                $this->id    = $obj->rowid;
                $this->fk_socpeople = $obj->fk_socpeople;
                $this->fk_site = $obj->fk_site;
                $this->remote_id = $obj->remote_id;
                $this->type = $obj->type;
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
            dol_syslog(get_class($this)."::fetchByFkSocpeople ".$this->error, LOG_ERR);
            return -1;
        }
    }

	/**
     * 		Select all the ids from eCommerceSocpeople for a site
     * 		@param int		siteId
     * 		@return array	synchObject ids for this site
     */
    public function getAllECommerceSocpeopleIds($siteId)
    {
   		global $langs;
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."ecommerce_socpeople";
        $sql.= " WHERE fk_site = ".$siteId;
    	dol_syslog(get_class($this)."::getAllECommerceSocpeopleIds sql=".$sql, LOG_DEBUG);
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
            dol_syslog(get_class($this)."::getAllECommerceSocpeopleIds ".$this->error, LOG_ERR);
            return -1;
        }
    }
}

