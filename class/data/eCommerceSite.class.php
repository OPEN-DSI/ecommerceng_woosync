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
dol_include_once('/ecommerceng/class/business/eCommerceUtils.class.php');

class eCommerceSite // extends CommonObject
{
	public $db;							//!< To store db handler
	public $error;							//!< To return error code (or message)
	public $errors=array();				//!< To return several error codes (or messages)
	//public $element='ecommerce_site';			//!< Id that identify managed objects
	//public $table_element='ecommerce_site';	//!< Name of table without prefix where object is stored

	public $id;

	public $name;
	public $type;
	public $webservice_address;
	public $api_version;
	public $authentication_type;
	public $user_name;
	public $user_password;
	public $price_level;
	public $filter_label;
	public $filter_value;
	public $fk_cat_societe;
	public $fk_cat_product;
    public $fk_anonymous_thirdparty;
	public $fk_warehouse;
	public $stock_sync_direction;
	public $last_update;
	public $timeout;
	public $debug;
	public $magento_use_special_price;
    public $ecommerce_price_type;
	public $entity;

	public $wordpress_authentication_type;
	public $wordpress_authentication_login;
	public $wordpress_authentication_password;
	public $wordpress_timeout;
	public $wordpress_debug;

    public $parameters;

    public $languages_cached;

	//The site type name is used to define class name in eCommerceRemoteAccess class
    private $siteTypes = array(2=>'woocommerce');

    /**
     * Constructor
     *
     * @param      DoliDB      $db      Database handler
     */
    function __construct($db)
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
		$stopwatch_id = eCommerceUtils::startAndLogStopwatch(__METHOD__);
		// Clean orphelins entries to have a clean database (having such records should not happen)
		/*$sql = "DELETE FROM ".MAIN_DB_PREFIX."ecommerce_category WHERE type = ".Categorie::TYPE_PRODUCT." AND fk_category NOT IN (select rowid from ".MAIN_DB_PREFIX."categorie)";
		 $this->db->query($sql);
		 $sql = "DELETE FROM ".MAIN_DB_PREFIX."ecommerce_category WHERE type = ".Categorie::TYPE_CUSTOMER." AND fk_category NOT IN (select rowid from ".MAIN_DB_PREFIX."categorie)";
		 $this->db->query($sql);*/
		$sql = "DELETE FROM " . MAIN_DB_PREFIX . "ecommerce_category WHERE fk_category NOT IN (select rowid from " . MAIN_DB_PREFIX . "categorie)";
		$this->db->query($sql);
		$sql = "DELETE " . MAIN_DB_PREFIX . "ecommerce_product FROM " . MAIN_DB_PREFIX . "ecommerce_product LEFT JOIN (SELECT DISTINCT p.rowid AS product_id, es.rowid AS site_id FROM " . MAIN_DB_PREFIX . "product AS p LEFT JOIN " . MAIN_DB_PREFIX . "categorie_product AS cp ON cp.fk_product = p.rowid LEFT JOIN " . MAIN_DB_PREFIX . "ecommerce_site AS es ON es.fk_cat_product = cp.fk_categorie WHERE es.rowid IS NOT NULL) AS pl ON pl.product_id = " . MAIN_DB_PREFIX . "ecommerce_product.fk_product AND pl.site_id = " . MAIN_DB_PREFIX . "ecommerce_product.fk_site WHERE pl.product_id IS NULL";
		$this->db->query($sql);
		$sql = "DELETE " . MAIN_DB_PREFIX . "ecommerce_societe FROM " . MAIN_DB_PREFIX . "ecommerce_societe LEFT JOIN (SELECT DISTINCT s.rowid AS company_id, es.rowid AS site_id FROM " . MAIN_DB_PREFIX . "societe AS s LEFT JOIN " . MAIN_DB_PREFIX . "categorie_societe AS cs ON cs.fk_soc = s.rowid LEFT JOIN " . MAIN_DB_PREFIX . "ecommerce_site AS es ON es.fk_cat_societe = cs.fk_categorie WHERE es.rowid IS NOT NULL) AS sl ON sl.company_id = " . MAIN_DB_PREFIX . "ecommerce_societe.fk_societe AND sl.site_id = " . MAIN_DB_PREFIX . "ecommerce_societe.fk_site WHERE sl.company_id IS NULL";
		$this->db->query($sql);
		$sql = "DELETE FROM " . MAIN_DB_PREFIX . "ecommerce_socpeople WHERE fk_socpeople NOT IN (select rowid from " . MAIN_DB_PREFIX . "socpeople)";
		$this->db->query($sql);
		$sql = "DELETE FROM " . MAIN_DB_PREFIX . "ecommerce_commande WHERE (fk_commande > 0 AND fk_commande NOT IN (select rowid from " . MAIN_DB_PREFIX . "commande)) OR fk_site NOT IN (select rowid from " . MAIN_DB_PREFIX . "ecommerce_site)";
		$this->db->query($sql);
		$sql = "DELETE FROM " . MAIN_DB_PREFIX . "ecommerce_commande WHERE (fk_commande < 0 AND ABS(fk_commande) NOT IN (select rowid from " . MAIN_DB_PREFIX . "facture)) OR fk_site NOT IN (select rowid from " . MAIN_DB_PREFIX . "ecommerce_site)";
		$this->db->query($sql);
		$sql = "DELETE FROM " . MAIN_DB_PREFIX . "ecommerce_facture WHERE fk_facture NOT IN (select rowid from " . MAIN_DB_PREFIX . "facture)";
		$this->db->query($sql);
		eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
	}

	/**
	 * Clean duplicates remote id
	 *
	 * @return  void
	 */
	function cleanDuplicatesRemoteID()
	{
		$stopwatch_id = eCommerceUtils::startAndLogStopwatch(__METHOD__);
		$ids = array();
		$sql = "SELECT remote_id FROM " . MAIN_DB_PREFIX . "ecommerce_product GROUP BY remote_id HAVING COUNT(*) > 1";
		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$ids[] = $obj->remote_id;
			}
		}
		if (!empty($ids)) {
			$sql = "DELETE FROM " . MAIN_DB_PREFIX . "ecommerce_product WHERE remote_id IN (" . implode(',', $ids) . ")";
			$this->db->query($sql);
		}
		eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
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
		if (isset($this->api_version)) $this->api_version=trim($this->api_version);
		if (isset($this->authentication_type)) $this->authentication_type=trim($this->authentication_type);
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

		if (isset($this->wordpress_authentication_type)) $this->wordpress_authentication_type=trim($this->wordpress_authentication_type);
		if (isset($this->wordpress_authentication_login)) $this->wordpress_authentication_login=trim($this->wordpress_authentication_login);
		if (isset($this->wordpress_authentication_password)) $this->wordpress_authentication_password=trim($this->wordpress_authentication_password);
		if (isset($this->wordpress_timeout)) $this->wordpress_timeout=trim($this->wordpress_timeout);

		// Check parameters
		// Put here code to add control on parameters values

        // Insert request
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."ecommerce_site(";
		$sql.= "name,";
		$sql.= "type,";
		$sql.= "webservice_address,";
		$sql.= "api_version,";
		$sql.= "authentication_type,";
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
		$sql.= "debug,";
		$sql.= "magento_use_special_price,";
		$sql.= "ecommerce_price_type,";
		$sql.= "wordpress_authentication_type,";
		$sql.= "wordpress_authentication_login,";
		$sql.= "wordpress_authentication_password,";
		$sql.= "wordpress_timeout,";
		$sql.= "wordpress_debug,";
        $sql.= "parameters,";
		$sql.= "entity";
        $sql.= ") VALUES (";
		$sql.= " ".(! isset($this->name)?'NULL':"'".$this->db->escape($this->name)."'").",";
		$sql.= " ".(! isset($this->type)?'NULL':"'".$this->type."'").",";
		$sql.= " ".(! isset($this->webservice_address)?'NULL':"'".$this->db->escape($this->webservice_address)."'").",";
		$sql.= " ".(! isset($this->api_version)?'NULL':"'".$this->db->escape($this->api_version)."'").",";
		$sql.= " ".(! isset($this->authentication_type)?'NULL':"'".$this->db->escape($this->authentication_type)."'").",";
		$sql.= " ".(! isset($this->user_name)?'NULL':"'".$this->db->escape($this->user_name)."'").",";
		$sql.= " ".(! isset($this->user_password)?'NULL':"'".$this->db->escape($this->user_password)."'").",";
        $sql.= " ".(! isset($this->price_level)?'1':"'".$this->db->escape($this->price_level)."'").",";
		$sql.= " ".(! isset($this->filter_label)?'NULL':"'".$this->db->escape($this->filter_label)."'").",";
		$sql.= " ".(! isset($this->filter_value)?'NULL':"'".$this->db->escape($this->filter_value)."'").",";
		$sql.= " ".($this->fk_cat_societe > 0 ? $this->fk_cat_societe : "0").",";
		$sql.= " ".($this->fk_cat_product > 0 ? $this->fk_cat_product : "0").",";
        $sql.= " ".($this->fk_anonymous_thirdparty > 0 ? $this->fk_anonymous_thirdparty : "NULL").",";
		$sql.= " ".($this->fk_warehouse > 0 ? $this->fk_warehouse : "NULL").",";
		$sql.= " ".($this->stock_sync_direction ? "'".$this->stock_sync_direction."'" : "'none'").",";
		$sql.= " ".(! isset($this->last_update) || strlen($this->last_update)==0?'NULL':"'".$this->db->idate($this->last_update)."'").",";
		$sql.= " ".(! isset($this->timeout)?'30':"'".intval($this->timeout)."'").",";
		$sql.= " ".(! empty($this->debug)?'1':"NULL").",";
		$sql.= " ".(! isset($this->magento_use_special_price)?'0':"'".intval($this->magento_use_special_price)."'").",";
		$sql.= " ".(! isset($this->ecommerce_price_type)?"'HT'":"'".$this->ecommerce_price_type."'").",";
		$sql.= " ".(! isset($this->wordpress_authentication_type)?"NULL":"'".$this->db->escape($this->wordpress_authentication_type)."'").",";
		$sql.= " ".(! isset($this->wordpress_authentication_login)?"NULL":"'".$this->db->escape($this->wordpress_authentication_login)."'").",";
		$sql.= " ".(! isset($this->wordpress_authentication_password)?"NULL":"'".$this->db->escape($this->wordpress_authentication_password)."'").",";
		$sql.= " ".(! isset($this->wordpress_timeout)?'30':"'".intval($this->wordpress_timeout)."'").",";
		$sql.= " ".(! empty($this->wordpress_debug)?'1':"NULL").",";
        $sql.= " ".(! isset($this->parameters)?"NULL":"'".$this->db->escape(json_encode($this->parameters))."'").",";
		$sql.= " ".$conf->entity."";
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
		$sql.= " t.api_version,";
		$sql.= " t.authentication_type,";
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
		$sql.= " t.debug,";
		$sql.= " t.magento_use_special_price,";
		$sql.= " t.ecommerce_price_type,";
		$sql.= " t.wordpress_authentication_type,";
		$sql.= " t.wordpress_authentication_login,";
		$sql.= " t.wordpress_authentication_password,";
		$sql.= " t.wordpress_timeout,";
		$sql.= " t.wordpress_debug,";
		$sql.= " t.parameters,";
		$sql.= " t.entity";
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
				$this->api_version = $obj->api_version;
				$this->authentication_type = $obj->authentication_type;
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
				$this->timeout = $obj->timeout > 0 ? $obj->timeout : '';
				$this->debug = !empty($obj->debug);
				$this->magento_use_special_price = $obj->magento_use_special_price;
				$this->ecommerce_price_type = $obj->ecommerce_price_type;
				$this->entity = $obj->entity;

				$this->wordpress_authentication_type = $obj->wordpress_authentication_type;
				$this->wordpress_authentication_login = $obj->wordpress_authentication_login;
				$this->wordpress_authentication_password = $obj->wordpress_authentication_password;
				$this->wordpress_timeout = $obj->wordpress_timeout > 0 ? $obj->wordpress_timeout : '';
				$this->wordpress_debug = !empty($obj->wordpress_debug);

                $this->parameters = json_decode($obj->parameters, true);

                return 1;
            }
            $this->db->free($resql);

            return 0;
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
		if (isset($this->api_version)) $this->api_version=trim($this->api_version);
		if (isset($this->authentication_type)) $this->authentication_type=trim($this->authentication_type);
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
		if (isset($this->wordpress_authentication_type)) $this->wordpress_authentication_type=trim($this->wordpress_authentication_type);
		if (isset($this->wordpress_authentication_login)) $this->wordpress_authentication_login=trim($this->wordpress_authentication_login);
		if (isset($this->wordpress_authentication_password)) $this->wordpress_authentication_password=trim($this->wordpress_authentication_password);
		if (isset($this->wordpress_timeout)) $this->wordpress_timeout=trim($this->wordpress_timeout);

		// Check parameters
		// Put here code to add control on parameters values

        // Update request
        $sql = "UPDATE ".MAIN_DB_PREFIX."ecommerce_site SET";

		$sql.= " name=".(isset($this->name)?"'".$this->db->escape($this->name)."'":"null").",";
		$sql.= " type=".(isset($this->type)?$this->type:"null").",";
		$sql.= " webservice_address=".(isset($this->webservice_address)?"'".$this->db->escape($this->webservice_address)."'":"null").",";
		$sql.= " api_version=".(isset($this->api_version)?"'".$this->db->escape($this->api_version)."'":"null").",";
		$sql.= " authentication_type=".(isset($this->authentication_type)?"'".$this->db->escape($this->authentication_type)."'":"null").",";
		$sql.= " user_name=".(isset($this->user_name)?"'".$this->db->escape($this->user_name)."'":"null").",";
		$sql.= " user_password=".(isset($this->user_password)?"'".$this->db->escape($this->user_password)."'":"null").",";
        $sql.= " price_level=".(isset($this->price_level)?"'".$this->db->escape($this->price_level)."'":"1").",";
		$sql.= " filter_label=".(isset($this->filter_label)?"'".$this->db->escape($this->filter_label)."'":"null").",";
		$sql.= " filter_value=".(isset($this->filter_value)?"'".$this->db->escape($this->filter_value)."'":"null").",";
		$sql.= " fk_cat_societe=".($this->fk_cat_societe > 0 ? $this->fk_cat_societe:"0").",";
		$sql.= " fk_cat_product=".($this->fk_cat_product > 0 ? $this->fk_cat_product:"0").",";
        $sql.= " fk_anonymous_thirdparty=".($this->fk_anonymous_thirdparty > 0 ? $this->fk_anonymous_thirdparty:"null").",";
		$sql.= " fk_warehouse=".($this->fk_warehouse > 0 ? $this->fk_warehouse:"null").",";
		$sql.= " stock_sync_direction=".($this->stock_sync_direction ? "'".$this->stock_sync_direction."'":"'none'").",";
		$sql.= " last_update=".((isset($this->last_update) && $this->last_update != '') ? "'".$this->db->idate($this->last_update)."'" : 'null').",";
		$sql.= " timeout=".(isset($this->timeout)? "'".intval($this->timeout)."'" : '30').",";
		$sql.= " debug=".(!empty($this->debug)? "1" : 'NULL').",";
		$sql.= " magento_use_special_price=".(isset($this->magento_use_special_price)? "'".intval($this->magento_use_special_price)."'" : '0').",";
		$sql.= " ecommerce_price_type=".(isset($this->ecommerce_price_type)? "'".$this->ecommerce_price_type."'" : 'HT').",";
		$sql.= " wordpress_authentication_type=".(isset($this->wordpress_authentication_type)?"'".$this->db->escape($this->wordpress_authentication_type)."'":"NULL").",";
		$sql.= " wordpress_authentication_login=".(isset($this->wordpress_authentication_login)?"'".$this->db->escape($this->wordpress_authentication_login)."'":"NULL").",";
		$sql.= " wordpress_authentication_password=".(isset($this->wordpress_authentication_password)?"'".$this->db->escape($this->wordpress_authentication_password)."'":"NULL").",";
		$sql.= " wordpress_timeout=".(isset($this->wordpress_timeout)? "'".intval($this->wordpress_timeout)."'" : '30').",";
		$sql.= " wordpress_debug=".(!empty($this->wordpress_debug)? "1" : 'NULL').",";
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
		$this->api_version='';
		$this->authentication_type='';
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
		$this->debug='';
		$this->magento_use_special_price='';
		$this->ecommerce_price_type='';
		$this->wordpress_authentication_type='';
		$this->wordpress_authentication_login='';
		$this->wordpress_authentication_password='';
		$this->wordpress_timeout='';
		$this->wordpress_debug='';
		$this->parameters='';
	}

	/**
	 *    Return list of all defined ecommerce sites
	 *
	 *    @param	string				$mode		'array' or 'object'
	 *    @param	boolean				$all_entity	List site from all entities
	 *    @return 	eCommerceSite[]					List of sites
	 */
	function listSites($mode='array', $all_entity = false)
	{
		$list = array();

		$sql = "SELECT";
		$sql .= " t.rowid,";
		$sql .= " t.name,";
		$sql .= " t.last_update";
		$sql .= " FROM " . MAIN_DB_PREFIX . "ecommerce_site as t";
		if (!$all_entity) $sql .= " WHERE t.entity IN (" . getEntity('ecommerceng') . ")";

		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				if ($mode == 'array') {
					$list[$obj->rowid] = array('id' => $obj->rowid, 'name' => $obj->name, 'last_update' => $this->db->jdate($obj->last_update));
				} else {
					$tmpsite = new eCommerceSite($this->db);
					$result = $tmpsite->fetch($obj->rowid);
					if ($result > 0) {
						$list[$obj->rowid] = $tmpsite;
					} elseif ($result < 0) {
						dol_syslog(__METHOD__ . " - Error when fetch site (ID: " . $obj->rowid . ") :" . $tmpsite->errorsToString(), LOG_ERR);
					}
				}
			}
			$this->db->free($resql);
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
		$sql.= " AND t.entity IN (" . getEntity('ecommerceng') . ")";

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

	/**
	 *  Load setup values into conf object (read llx_const) for a specified entity
	 *  Note that this->db->xxx, this->file->xxx and this->multicompany have been already loaded when setValues is called.
	 *
	 * @param	int		$entity		Entity to get
	 * @return	int					< 0 if KO, >= 0 if OK
	 */
	function setEntityValues($entity)
	{
		global $conf;

		if (method_exists($conf, 'setEntityValues')) {
			return $conf->setEntityValues($this->db, $entity);
		} else {
			if ($conf->entity != $entity) {
				// If we ask to reload setup for a new entity
				$conf->entity = $entity;

				// Unset all old modules values
				if (!empty($conf->modules)) {
					foreach ($conf->modules as $m) {
						if (isset($conf->$m)) unset($conf->$m);
					}
				}

				// Common objects that are not modules
				$conf->mycompany	= new stdClass();
				$conf->admin		= new stdClass();
				$conf->medias		= new stdClass();
				$conf->global		= new stdClass();
				$conf->multicompany = new stdClass();

				// Common objects that are not modules and set by the main and not into the this->setValues()
				//$conf->browser = new stdClass();	// This is set by main and not into this setValues(), so we keep it intact.

				// First level object
				// TODO Remove this part.
				$conf->syslog			= new stdClass();
				$conf->expedition_bon	= new stdClass();
				$conf->delivery_note	= new stdClass();
				$conf->fournisseur		= new stdClass();
				$conf->product			= new stdClass();
				$conf->service			= new stdClass();
				$conf->contrat			= new stdClass();
				$conf->actions			= new stdClass();
				$conf->agenda			= new stdClass();
				$conf->commande			= new stdClass();
				$conf->propal			= new stdClass();
				$conf->facture			= new stdClass();
				$conf->contrat			= new stdClass();
				$conf->user				= new stdClass();
				$conf->usergroup		= new stdClass();
				$conf->adherent			= new stdClass();
				$conf->bank				= new stdClass();
				$conf->notification		= new stdClass();
				$conf->mailing			= new stdClass();
				$conf->expensereport	= new stdClass();
				$conf->productbatch		= new stdClass();

				// Common arrays
				$conf->cache = array();
				$conf->modules = array();;
				$conf->modules_parts = array(
					'css' => array(),
					'js' => array(),
					'tabs' => array(),
					'triggers' => array(),
					'login' => array(),
					'substitutions' => array(),
					'menus' => array(),
					'theme' => array(),
					'sms' => array(),
					'tpl' => array(),
					'barcode' => array(),
					'models' => array(),
					'societe' => array(),
					'hooks' => array(),
					'dir' => array(),
					'syslog' => array(),
				);

				return $conf->setValues($this->db);
			}

			return 0;
		}
	}

	/**
	 * Upgrade the parameters configuration to the new setup
	 *
	 * @return int						<0 if KO, >0 if OK
	 */
	public function upgradeParameters()
	{
		global $conf, $user;

		// setup
		if (!isset($this->api_version)) $this->api_version = 'v3';
		if (!isset($this->authentication_type)) $this->authentication_type = 'oauth1_header';
		// product
		if (!isset($this->parameters['enable_product_plugin_wpml_support'])) $this->parameters['enable_product_plugin_wpml_support'] = !empty($conf->global->ECOMMERCENG_WOOCOMMERCE_WPML_SUPPORT) ? 1 : 0;
		if (!isset($this->parameters['product_status_supported'])) $this->parameters['product_status_supported'] = 'publish';
		// stock
		if (!empty($this->parameters['enable_warehouse_plugin_sl_support'])) { $this->parameters['enable_warehouse_plugin_support'] = 'slfw'; unset($this->parameters['enable_warehouse_plugin_sl_support']); }
		if (!isset($this->parameters['order_actions']['valid_invoice_fk_warehouse'])) $this->parameters['order_actions']['valid_invoice_fk_warehouse'] = $this->parameters['order_actions']['valid_order_fk_warehouse'];
		if (!isset($this->parameters['order_actions']['valid_supplier_invoice_fk_warehouse'])) $this->parameters['order_actions']['valid_supplier_invoice_fk_warehouse'] = $this->parameters['order_actions']['valid_order_fk_warehouse'];
		// extra fields
		$table_elements = [ 'societe', 'product', 'commande', 'commandedet' ];
		foreach ($table_elements as $table_element) {
			if (!isset($this->parameters['extra_fields'][$table_element])) {
				if (isset($this->parameters['ef_crp'][$table_element])) {
					foreach ($this->parameters['ef_crp'][$table_element] as $key => $info) {
						$this->parameters['extra_fields'][$table_element]['activated']['mdt'][$key] = $info['activated'];
						$this->parameters['extra_fields'][$table_element]['values']['mdt'][$key] = $info['correspondences'];
					}
				}
				if ($table_element == 'product' && isset($this->parameters['ef_crp_attribute'])) {
					foreach ($this->parameters['ef_crp_attribute'] as $key => $info) {
						$this->parameters['extra_fields'][$table_element]['activated']['att'][$key] = $info['activated'];
						$this->parameters['extra_fields'][$table_element]['values']['att'][$key] = $info['correspondences'];
					}
				}
			}
		}
		if (isset($this->parameters['ef_crp'])) unset($this->parameters['ef_crp']);
		if (isset($this->parameters['ef_crp_attribute'])) unset($this->parameters['ef_crp_attribute']);

		$error = 0;
		$this->db->begin();

		$result = $this->update($user);
		if ($result < 0) {
			$error++;
		}

		// gateways
		if (!$error) {
			$language_list = $this->getLanguages();
			$language_list = array_flip(array_flip(array_values($language_list)));

			dol_include_once('/ecommerceng/class/data/eCommercePaymentGateways.class.php');
			$eCommercePaymentGateways = new eCommercePaymentGateways($this->db);
			$payment_gateways = $eCommercePaymentGateways->get_all($this->id);
			if (!is_array($payment_gateways)) {
				$this->error = $eCommercePaymentGateways->error;
				$this->errors = $eCommercePaymentGateways->errors;
				$error++;
			}

			if (!$error) {
				foreach ($payment_gateways as $key => $infos) {
					$save_value = !is_array($infos['mail_model_for_send_invoice']) ? $infos['mail_model_for_send_invoice'] : 0;
					if (!is_array($infos['mail_model_for_send_invoice'])) $payment_gateways[$key]['mail_model_for_send_invoice'] = array();
					foreach ($language_list as $lang) {
						$payment_gateways[$key]['mail_model_for_send_invoice'][$lang] = $save_value;
					}
				}

				$result = $eCommercePaymentGateways->set($this->id, $payment_gateways);
				if ($result < 0) {
					$this->error = $eCommercePaymentGateways->error;
					$this->errors = $eCommercePaymentGateways->errors;
					$error++;
				}
			}
		}

		if ($error) {
			$this->db->rollback();
			return -1;
		} else {
			$this->db->commit();
			return 1;
		}
	}

	/**
	 *    Return list of languages matching
	 *
	 * @param boolean $force Force reload
	 * @return    array                List of languages matching
	 */
	public function getLanguages($force = false)
	{
		global $conf;

		if (!isset($this->languages_cached) || $force) {
			$languages = array();
			if (!empty($this->parameters['enable_product_plugin_wpml_support']) && !empty($conf->global->MAIN_MULTILANGS)) {
				$sql = "SELECT code, lang FROM " . MAIN_DB_PREFIX . "c_ecommerceng_lang WHERE entity IN (" . getEntity('ecommerceng') . ")";
				$resql = $this->db->query($sql);
				if ($resql) {
					while ($obj = $this->db->fetch_object($resql)) {
						$languages[$obj->code] = $obj->lang;
					}
					$this->db->free($resql);
				}
			} else {
				$languages['ec_none'] = 'ec_none';
			}
			$this->languages_cached = $languages;
		}

		return $this->languages_cached;
	}

	/**
	 * Method to output saved errors
	 *
	 * @return	string		String with errors
	 */
	public function errorsToString()
	{
		return $this->error.(is_array($this->errors) ? (($this->error != '' ? ', ' : '').join(', ', $this->errors)) : '');
	}
}

