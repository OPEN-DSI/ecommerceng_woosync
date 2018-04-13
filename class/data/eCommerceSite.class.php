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


dol_include_once('/ecommerceng/class/data/eCommerceSociete.class.php');

class eCommerceSite // extends CommonObject
{
	var $db;							//!< To store db handler
	var $error;							//!< To return error code (or message)
	var $errors=array();				//!< To return several error codes (or messages)
	//var $element='ecommerce_site';			//!< Id that identify managed objects
	//var $table_element='ecommerce_site';	//!< Name of table without prefix where object is stored

    var $id;

	var $name;
	var $type;
	var $webservice_address;
	var $user_name;
	var $user_password;
	var $price_level;
	var $filter_label;
	var $filter_value;
	var $fk_cat_societe;
	var $fk_cat_product;
    var $fk_anonymous_thirdparty;
	var $fk_warehouse;
	var $stock_sync_direction;
	var $last_update;
	var $timeout;
	var $magento_use_special_price;
    var $ecommerce_price_type;

	var $oauth_id;
	var $oauth_secret;

    var $parameters;

	//The site type name is used to define class name in eCommerceRemoteAccess class
    private $siteTypes = array(2=>'woocommerce');

    /**
     * Constructor
     *
     * @param      DoliDB      $db      Database handler
     */
    function eCommerceSite($db)
    {
        $this->db = $db;
    }


    /**
     * Clean orphelins record
     *
     * @return  void
     */
    function cleanOrphelins()
    {
        // Clean orphelins entries to have a clean database (having such records should not happen)
        /*$sql = "DELETE FROM ".MAIN_DB_PREFIX."ecommerce_category WHERE type = ".Categorie::TYPE_PRODUCT." AND fk_category NOT IN (select rowid from ".MAIN_DB_PREFIX."categorie)";
         $this->db->query($sql);
         $sql = "DELETE FROM ".MAIN_DB_PREFIX."ecommerce_category WHERE type = ".Categorie::TYPE_CUSTOMER." AND fk_category NOT IN (select rowid from ".MAIN_DB_PREFIX."categorie)";
         $this->db->query($sql);*/
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."ecommerce_category WHERE fk_category NOT IN (select rowid from ".MAIN_DB_PREFIX."categorie)";
        $this->db->query($sql);
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."ecommerce_product WHERE fk_product NOT IN (select rowid from ".MAIN_DB_PREFIX."product)";
        $this->db->query($sql);
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."ecommerce_societe WHERE fk_societe NOT IN (select rowid from ".MAIN_DB_PREFIX."societe)";
        $this->db->query($sql);
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."ecommerce_socpeople WHERE fk_socpeople NOT IN (select rowid from ".MAIN_DB_PREFIX."socpeople)";
        $this->db->query($sql);
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."ecommerce_commande WHERE fk_commande NOT IN (select rowid from ".MAIN_DB_PREFIX."commande)";
        $this->db->query($sql);
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."ecommerce_facture WHERE fk_facture NOT IN (select rowid from ".MAIN_DB_PREFIX."facture)";
        $this->db->query($sql);
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
		if (isset($this->name)) $this->name=trim($this->name);
		if (isset($this->type)) $this->type=trim($this->type);
		if (isset($this->webservice_address)) $this->webservice_address=trim($this->webservice_address);
		if (isset($this->user_name)) $this->user_name=trim($this->user_name);
		if (isset($this->user_password)) $this->user_password=trim($this->user_password);
        if (isset($this->price_level)) $this->price_level=trim($this->price_level);
		if (isset($this->filter_label)) $this->filter_label=trim($this->filter_label);
		if (isset($this->filter_value)) $this->filter_value=trim($this->filter_value);
		if (isset($this->fk_cat_societe)) $this->fk_cat_societe=trim($this->fk_cat_societe);
		if (isset($this->fk_cat_product)) $this->fk_cat_product=trim($this->fk_cat_product);
        if (isset($this->fk_anonymous_thirdparty)) $this->fk_anonymous_thirdparty=trim($this->fk_anonymous_thirdparty);
		if (isset($this->fk_warehouse)) $this->fk_warehouse=trim($this->fk_warehouse);
		if (isset($this->stock_sync_direction)) $this->stock_sync_direction=trim($this->stock_sync_direction);
		if (isset($this->timeout)) $this->timeout=trim($this->timeout);

		if (isset($this->oauth_id)) $this->oauth_id=trim($this->oauth_id);
		if (isset($this->oauth_secret)) $this->oauth_secret=trim($this->oauth_secret);

		// Check parameters
		// Put here code to add control on parameters values

        // Insert request
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."ecommerce_site(";
		$sql.= "name,";
		$sql.= "type,";
		$sql.= "webservice_address,";
		$sql.= "user_name,";
		$sql.= "user_password,";
        $sql.= "price_level,";
		$sql.= "filter_label,";
		$sql.= "filter_value,";
		$sql.= "fk_cat_societe,";
		$sql.= "fk_cat_product,";
        $sql.= "fk_anonymous_thirdparty,";
		$sql.= "fk_warehouse,";
		$sql.= "stock_sync_direction,";
		$sql.= "last_update,";
		$sql.= "timeout,";
		$sql.= "magento_use_special_price,";
		$sql.= "ecommerce_price_type,";
		$sql.= "oauth_id,";
		$sql.= "oauth_secret,";
        $sql.= "parameters";
        $sql.= ") VALUES (";
		$sql.= " ".(! isset($this->name)?'NULL':"'".$this->db->escape($this->name)."'").",";
		$sql.= " ".(! isset($this->type)?'NULL':"'".$this->type."'").",";
		$sql.= " ".(! isset($this->webservice_address)?'NULL':"'".$this->db->escape($this->webservice_address)."'").",";
		$sql.= " ".(! isset($this->user_name)?'NULL':"'".$this->db->escape($this->user_name)."'").",";
		$sql.= " ".(! isset($this->user_password)?'NULL':"'".$this->db->escape($this->user_password)."'").",";
        $sql.= " ".(! isset($this->price_level)?'1':"'".$this->db->escape($this->price_level)."'").",";
		$sql.= " ".(! isset($this->filter_label)?'NULL':"'".$this->db->escape($this->filter_label)."'").",";
		$sql.= " ".(! isset($this->filter_value)?'NULL':"'".$this->db->escape($this->filter_value)."'").",";
		$sql.= " ".($this->fk_cat_societe > 0 ? $this->fk_cat_societe : "NULL").",";
		$sql.= " ".($this->fk_cat_product > 0 ? $this->fk_cat_product : "NULL").",";
        $sql.= " ".($this->fk_anonymous_thirdparty > 0 ? $this->fk_anonymous_thirdparty : "NULL").",";
		$sql.= " ".($this->fk_warehouse > 0 ? $this->fk_warehouse : "NULL").",";
		$sql.= " ".($this->stock_sync_direction ? "'".$this->stock_sync_direction."'" : "'none'").",";
		$sql.= " ".(! isset($this->last_update) || strlen($this->last_update)==0?'NULL':"'".$this->db->idate($this->last_update)."'").",";
		$sql.= " ".(! isset($this->timeout)?'300':"'".intval($this->timeout)."'").",";
		$sql.= " ".(! isset($this->magento_use_special_price)?'0':"'".intval($this->magento_use_special_price)."'").",";
		$sql.= " ".(! isset($this->ecommerce_price_type)?'HT':"'".$this->ecommerce_price_type."'").",";
		$sql.= " ".(! isset($this->oauth_id)?"NULL":"'".$this->db->escape($this->oauth_id)."'").",";
		$sql.= " ".(! isset($this->oauth_secret)?"NULL":"'".$this->db->escape($this->oauth_secret)."'").",";
        $sql.= " ".(! isset($this->parameters)?"NULL":"'".$this->db->escape(json_encode($this->parameters))."'")."";
		$sql.= ")";

		$this->db->begin();

	   	dol_syslog(get_class($this)."::create sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
    	if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }

		if (! $error)
        {
            $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."ecommerce_site");

            // create an entry for anonymous company
            $eCommerceSociete = new eCommerceSociete($this->db);
            $eCommerceSociete->fk_societe = $this->fk_anonymous_thirdparty;
            $eCommerceSociete->fk_site = $this->id;
            $eCommerceSociete->remote_id = 0;
            if ($eCommerceSociete->create($user)<0)
            {
                $error++;
                $this->errors[]="Error ".$this->db->lasterror();
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
     *   Load object in memory from database
     *
     *   @param     int     $id         Id of object
     *   @return    int                 <0 if KO, >0 if OK
     */
    function fetch($id)
    {
    	global $langs;
        $sql = "SELECT";
		$sql.= " t.rowid,";
		$sql.= " t.name,";
		$sql.= " t.type,";
		$sql.= " t.webservice_address,";
		$sql.= " t.user_name,";
		$sql.= " t.user_password,";
        $sql.= " t.price_level,";
		$sql.= " t.filter_label,";
		$sql.= " t.filter_value,";
		$sql.= " t.fk_cat_societe,";
		$sql.= " t.fk_cat_product,";
        $sql.= " t.fk_anonymous_thirdparty,";
		$sql.= " t.fk_warehouse,";
		$sql.= " t.stock_sync_direction,";
		$sql.= " t.last_update,";
		$sql.= " t.timeout,";
		$sql.= " t.magento_use_special_price,";
		$sql.= " t.ecommerce_price_type,";
		$sql.= " t.oauth_id,";
		$sql.= " t.oauth_secret,";
        $sql.= " t.parameters";
        $sql.= " FROM ".MAIN_DB_PREFIX."ecommerce_site as t";
        $sql.= " WHERE t.rowid = ".$id;

    	dol_syslog(get_class($this)."::fetch sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql)
        {
            if ($this->db->num_rows($resql))
            {
                $obj = $this->db->fetch_object($resql);

                $this->id    = $obj->rowid;

				$this->name = $obj->name;
				$this->type = $obj->type;
				$this->webservice_address = $obj->webservice_address;
				$this->user_name = $obj->user_name;
				$this->user_password = $obj->user_password;
                $this->price_level = $obj->price_level;
				$this->filter_label = $obj->filter_label;
				$this->filter_value = $obj->filter_value;
				$this->fk_cat_societe = $obj->fk_cat_societe;
				$this->fk_cat_product = $obj->fk_cat_product;
                $this->fk_anonymous_thirdparty = $obj->fk_anonymous_thirdparty;
				$this->fk_warehouse = $obj->fk_warehouse;
				$this->stock_sync_direction = $obj->stock_sync_direction;
				$this->last_update = $this->db->jdate($obj->last_update);
				$this->timeout = $obj->timeout;
				$this->magento_use_special_price = $obj->magento_use_special_price;
				$this->ecommerce_price_type = $obj->ecommerce_price_type;

				$this->oauth_id = $obj->oauth_id;
				$this->oauth_secret = $obj->oauth_secret;

                $this->parameters = json_decode($obj->parameters, true);
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
     *   Update database
     *
     *   @param      User   $user        	User that modify
     *   @param      int    $notrigger	    0=launch triggers after, 1=disable triggers
     *   @return     int                	<0 if KO, >0 if OK
     */
    function update($user=0, $notrigger=0)
    {
    	global $conf, $langs;
		$error=0;

		// Clean parameters

		if (isset($this->name)) $this->name=trim($this->name);
		if (isset($this->type)) $this->type=trim($this->type);
		if (isset($this->webservice_address)) $this->webservice_address=trim($this->webservice_address);
		if (isset($this->user_name)) $this->user_name=trim($this->user_name);
		if (isset($this->user_password)) $this->user_password=trim($this->user_password);
        if (isset($this->price_level)) $this->price_level=trim($this->price_level);
		if (isset($this->filter_label)) $this->filter_label=trim($this->filter_label);
		if (isset($this->filter_value)) $this->filter_value=trim($this->filter_value);
		if (isset($this->fk_cat_societe)) $this->fk_cat_societe=trim($this->fk_cat_societe);
		if (isset($this->fk_cat_product)) $this->fk_cat_product=trim($this->fk_cat_product);
        if (isset($this->fk_anonymous_thirdparty)) $this->fk_anonymous_thirdparty=trim($this->fk_anonymous_thirdparty);
		if (isset($this->fk_warehouse)) $this->fk_warehouse=trim($this->fk_warehouse);
		if (isset($this->timeout)) $this->timeout=trim($this->timeout);
		if (isset($this->oauth_id)) $this->oauth_id=trim($this->oauth_id);
		if (isset($this->oauth_secret)) $this->oauth_secret=trim($this->oauth_secret);

		// Check parameters
		// Put here code to add control on parameters values

        // Update request
        $sql = "UPDATE ".MAIN_DB_PREFIX."ecommerce_site SET";

		$sql.= " name=".(isset($this->name)?"'".$this->db->escape($this->name)."'":"null").",";
		$sql.= " type=".(isset($this->type)?$this->type:"null").",";
		$sql.= " webservice_address=".(isset($this->webservice_address)?"'".$this->db->escape($this->webservice_address)."'":"null").",";
		$sql.= " user_name=".(isset($this->user_name)?"'".$this->db->escape($this->user_name)."'":"null").",";
		$sql.= " user_password=".(isset($this->user_password)?"'".$this->db->escape($this->user_password)."'":"null").",";
        $sql.= " price_level=".(isset($this->price_level)?"'".$this->db->escape($this->price_level)."'":"1").",";
		$sql.= " filter_label=".(isset($this->filter_label)?"'".$this->db->escape($this->filter_label)."'":"null").",";
		$sql.= " filter_value=".(isset($this->filter_value)?"'".$this->db->escape($this->filter_value)."'":"null").",";
		$sql.= " fk_cat_societe=".($this->fk_cat_societe > 0 ? $this->fk_cat_societe:"null").",";
		$sql.= " fk_cat_product=".($this->fk_cat_product > 0 ? $this->fk_cat_product:"null").",";
        $sql.= " fk_anonymous_thirdparty=".($this->fk_anonymous_thirdparty > 0 ? $this->fk_anonymous_thirdparty:"null").",";
		$sql.= " fk_warehouse=".($this->fk_warehouse > 0 ? $this->fk_warehouse:"null").",";
		$sql.= " stock_sync_direction=".($this->stock_sync_direction ? "'".$this->stock_sync_direction."'":"'none'").",";
		$sql.= " last_update=".((isset($this->last_update) && $this->last_update != '') ? "'".$this->db->idate($this->last_update)."'" : 'null').",";
		$sql.= " timeout=".(isset($this->timeout)? "'".intval($this->timeout)."'" : '300').",";
		$sql.= " magento_use_special_price=".(isset($this->magento_use_special_price)? "'".intval($this->magento_use_special_price)."'" : '0').",";
		$sql.= " ecommerce_price_type=".(isset($this->ecommerce_price_type)? "'".$this->ecommerce_price_type."'" : 'HT').",";
		$sql.= " oauth_id=".(isset($this->oauth_id)?"'".$this->db->escape($this->oauth_id)."'":"NULL").",";
		$sql.= " oauth_secret=".(isset($this->oauth_secret)?"'".$this->db->escape($this->oauth_secret)."'":"NULL").",";
        $sql.= " parameters=".(isset($this->parameters)?"'".$this->db->escape(json_encode($this->parameters))."'":"NULL")."";
        $sql.= " WHERE rowid=".$this->id;

		$this->db->begin();

		dol_syslog(get_class($this)."::update sql=".$sql, LOG_DEBUG);
        $resql = $this->db->query($sql);
    	if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }

		if (! $error)
		{
            $eCommerceSociete = new eCommerceSociete($this->db);
            if ($eCommerceSociete->fetchByRemoteId(0, $this->id) > 0) {
                if (isset($this->fk_anonymous_thirdparty)) {
                    // update an entry for anonymous company
                    $eCommerceSociete->fk_societe = $this->fk_anonymous_thirdparty;
                    if ($eCommerceSociete->update($user) < 0) {
                        $error++;
                        $this->errors[] = "Error " . $this->db->lasterror();
                    }
                } else {
                    // delete an entry for anonymous company
                    if ($eCommerceSociete->delete($user) < 0) {
                        $error++;
                        $this->errors[] = "Error " . $this->db->lasterror();
                    }
                }
            } else {
                // create an entry for anonymous company
                $eCommerceSociete = new eCommerceSociete($this->db);
                $eCommerceSociete->fk_societe = $this->fk_anonymous_thirdparty;
                $eCommerceSociete->fk_site = $this->id;
                $eCommerceSociete->remote_id = 0;
                if ($eCommerceSociete->create($user)<0)
                {
                    $error++;
                    $this->errors[]="Error ".$this->db->lasterror();
                }
            }

			if (! $notrigger && !$error)
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
	 * Delete object in database
     *
     * @param   User    $user        	 User that delete
     * @param   int     $notrigger	     0=launch triggers after, 1=disable triggers
	 * @return	int				         <0 if KO, >0 if OK
	 */
	function delete($user, $notrigger=0)
	{
		global $conf, $langs;
		$error=0;

		$sql = "DELETE FROM ".MAIN_DB_PREFIX."ecommerce_site";
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
	 *	Load an object from its id and create a new one in database
	 *
	 *	@param      int     $fromid     		Id of object to clone
	 * 	@return		int		             		New id of clone
	 */
	function createFromClone($fromid)
	{
		global $user,$langs;

		$error=0;

		$object=new Ecommerce_site($this->db);

		$this->db->begin();

		// Load source object
		$object->fetch($fromid);
		$object->id=0;
		$object->statut=0;

		// Clear fields
		// ...

		// Create clone
		$result=$object->create($user);

		// Other options
		if ($result < 0)
		{
			$this->error=$object->error;
			$error++;
		}

		if (! $error)
		{



		}

		// End
		if (! $error)
		{
			$this->db->commit();
			return $object->id;
		}
		else
		{
			$this->db->rollback();
			return -1;
		}
	}


	/**
	 *	Initialise object with example values
	 *  id must be 0 if object instance is a specimen.
	 *
	 *	@return     void
	 */
	function initAsSpecimen()
	{
		$this->id=0;

		$this->name='';
		$this->type='';
		$this->webservice_address='';
		$this->user_name='';
		$this->user_password='';
        $this->price_level='';
		$this->filter_label='';
		$this->filter_value='';
		$this->fk_cat_societe='';
		$this->fk_cat_product='';
        $this->fk_anonymous_thirdparty='';
		$this->fk_warehouse='';
		$this->stock_sync_direction='none';
		$this->last_update='';
		$this->timeout='';
		$this->magento_use_special_price='';
		$this->ecommerce_price_type='';
		$this->oauth_id='';
		$this->oauth_secret='';
        $this->parameters='';
	}

	/**
	 *    Return list of all defined ecommerce sites
	 *
	 *    @param	string		$mode		'array' or 'object'
	 *    @return 	array					List of sites
	 */
	function listSites($mode='array')
	{
		global $langs;

		$list = array();

        $sql = "SELECT";
		$sql.= " t.rowid,";
		$sql.= " t.name,";
		$sql.= " t.last_update";
        $sql.= " FROM ".MAIN_DB_PREFIX."ecommerce_site as t";

    	$result = $this->db->query($sql);
		if ($result)
		{
			$num = $this->db->num_rows($result);
			$i=0;
			while ($i < $num)
			{
				$obj = $this->db->fetch_object($result);
				if ($mode == 'array')
				{
					$list[$i]=array('id'=>$obj->rowid, 'name'=>$obj->name, 'last_update'=>$this->db->jdate($obj->last_update));
				}
				else
				{
					$tmpsite=new eCommerceSite($this->db);
					$tmpsite->fetch($obj->rowid);
					$list[$i]=$tmpsite;
				}
				$i++;
			}
		}
		return $list;
	}

    /**
   	 *    Check if type site is created
   	 *
   	 *    @param	integer		$typeSite		Type of site
   	 *    @return 	boolean					    true if type site has created
   	 */
   	function hasTypeSite($typeSite)
   	{
        $sql = "SELECT";
   		$sql.= " count(*) AS count";
        $sql.= " FROM ".MAIN_DB_PREFIX."ecommerce_site as t";
        $sql.= " WHERE t.type=".$typeSite;

       	$result = $this->db->query($sql);
   		if ($result) {
            $obj = $this->db->fetch_object($result);
            if ($obj->count > 0) {
                return true;
            }
   		}

   		return false;
   	}

	/**
	 * Return list of available site types
	 *
	 * @return string[]
	 */
	public function getSiteTypes()
	{
		return $this->siteTypes;
	}


	/**
	 * Return URL of visitors shop
	 *
	 * @return string
	 */
	public function getFrontUrl()
	{
        // Try to guess public home page of ecommerce web site from the api url
	    $url=preg_replace('/index\.php\/api.*$/', '', $this->webservice_address);
	    $url=preg_replace('/\/api\/.*$/', '', $url);
	    if ($url && ! preg_match('/\/$/', $url)) $url.='/';
	    return $url;
	}

	/**
	 * Return URL of admin backoffice
	 *
	 * @return string
	 */
	public function getBackUrl()
	{
	    // Try to guess public home page of ecommerce web site from the api url
	    $url=$this->getFrontUrl();
		switch ($this->type) {
			case 1: // Magento
				$url.='index.php/admin';
				break;
			case 2: // Woocommerce
				$url.=(substr($url, -1, 1)!='/'?'/':'').'wp-admin';
				break;
		}
	    return $url;
	}

}

