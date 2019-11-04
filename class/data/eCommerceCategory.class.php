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


/**
 *      \file       ecommerceng/class/data/eCommerceCategory.class.php
 *      \ingroup    ecommerceng
 *      \brief      This file is an example for a CRUD class file (Create/Read/Update/Delete)
 */
// Put here all includes required by your class file
//require_once(DOL_DOCUMENT_ROOT."/core/class/commonobject.class.php");
//require_once(DOL_DOCUMENT_ROOT."/societe/class/societe.class.php");
//require_once(DOL_DOCUMENT_ROOT."/product/class/product.class.php");

/**
 *      eCommerceCategory
 */
class eCommerceCategory // extends CommonObject
{

    var $db;       //!< To store db handler
    var $error;       //!< To return error code (or message)
    var $errors = array();    //!< To return several error codes (or messages)
    //var $element='skeleton';			//!< Id that identify managed objects
    var $table_element = 'ecommerce_category';  //!< Name of table without prefix where object is stored
    var $id;
    var $label;
    var $type;
    var $description;
    var $fk_category;
    var $fk_site;
    var $remote_id;
    var $remote_parent_id;
    var $last_update;

    /**
     *      Constructor
     *
     *      @param      DoliDB  $DB      Database handler
     */
    function eCommerceCategory($DB)
    {
        $this->db = $DB;

        return 1;
    }

    /**
     *      Create in database
     *
     *      @param      User    $user        	User that create
     *      @param      int     $notrigger	    0=launch triggers after, 1=disable triggers
     *      @return     int                    	<0 if KO, Id of created object if OK
     */
    function create($user, $notrigger = 0)
    {
        global $conf, $langs;
        $error = 0;

        // Clean parameters
        if (isset($this->fk_category))
            $this->fk_category = intval($this->fk_category);
        if (isset($this->fk_site))
            $this->fk_site = intval($this->fk_site);
        if (isset($this->remote_id))
            $this->remote_id = trim($this->remote_id);
        if (isset($this->remote_parent_id))
            $this->remote_parent_id = trim($this->remote_parent_id);

        // Check parameters
        // Put here code to add control on parameters values

        // Insert request
        $sql = "INSERT INTO " . MAIN_DB_PREFIX . $this->table_element . "(";
        $sql.= " label,type,description,fk_category,fk_site,remote_id,remote_parent_id,last_update";
        $sql.= ") VALUES (";
        $sql.= " '" . $this->db->escape($this->label) . "',";
        $sql.= " '" . $this->db->escape($this->type) . "',";
        $sql.= " '" . $this->db->escape($this->description) . "',";
        $sql.= " " . $this->db->escape($this->fk_category) . ",";
        $sql.= " " . $this->db->escape($this->fk_site) . ",";
        $sql.= " " . (isset($this->remote_id) ? intval($this->remote_id) : 0) . ",";
        $sql.= " " . (isset($this->remote_parent_id) ? intval($this->remote_parent_id) : 0) . ",";
        $sql.= " " . ($this->last_update > 0 ? "'" . $this->db->idate($this->last_update) . "'" : "NULL");
        $sql.= ")";

        $this->db->begin();

        dol_syslog(get_class($this) . "::create sql=" . $sql, LOG_DEBUG);
        $resql = $this->db->query($sql);
        if (!$resql)
        {
            $error++;
            $this->errors[] = "Error " . $this->db->lasterror();
        }

        if (!$error)
        {
            $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX . "mytable");

            if (!$notrigger)
            {
                // Uncomment this and change MYOBJECT to your own tag if you
                // want this action call a trigger.
                //// Call triggers
                //include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
                //$interface=new Interfaces($this->db);
                //$result=$interface->run_triggers('MYOBJECT_CREATE',$this,$user,$langs,$conf);
                //if ($result < 0) { $error++; $this->errors=$interface->errors; }
                //// End call triggers
            }
        }

        // Commit or rollback
        if ($error)
        {
            foreach ($this->errors as $errmsg)
            {
                dol_syslog(get_class($this) . "::create " . $errmsg, LOG_ERR);
                $this->error.=($this->error ? ', ' . $errmsg : $errmsg);
            }
            $this->db->rollback();
            return -1 * $error;
        } else
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
        $sql = "SELECT ";
        $sql.= "t.rowid,";
        $sql.= "t.label,";
        $sql.= "t.type,";
        $sql.= "t.description,";
        $sql.= "t.fk_category,";
        $sql.= "t.fk_site,";
        $sql.= "t.remote_id,";
        $sql.= "t.remote_parent_id,";
        $sql.= "t.last_update";

        $sql.= " FROM " . MAIN_DB_PREFIX . $this->table_element . " as t";
        $sql.= " WHERE t.rowid = " . $id;

        dol_syslog(get_class($this) . "::fetch sql=" . $sql, LOG_DEBUG);
        $resql = $this->db->query($sql);
        if ($resql)
        {
            if ($this->db->num_rows($resql))
            {
                $obj = $this->db->fetch_object($resql);

                $this->id = $obj->rowid;
                $this->label = $obj->label;
                $this->type = $obj->type;
                $this->description = $obj->description;
                $this->fk_category = $obj->fk_category;
                $this->fk_site = $obj->fk_site;
                $this->remote_id = $obj->remote_id;
                $this->remote_parent_id = $obj->remote_parent_id;
                $this->last_update = $obj->last_update;
            }
            $this->db->free($resql);

            return 1;
        } else
        {
            $this->error = "Error " . $this->db->lasterror();
            dol_syslog(get_class($this) . "::fetch " . $this->error, LOG_ERR);
            return -1;
        }
    }

    /**
     *      Update database
     *
     *      @param      User    $user        	User that modify
     *      @param      int     $notrigger	    0=launch triggers after, 1=disable triggers
     *      @return     int                    	<0 if KO, >0 if OK
     */
    function update($user = 0, $notrigger = 0)
    {
        global $conf, $langs;
        $error = 0;

        // Clean parameters
        if (isset($this->fk_category))
            $this->fk_category = intval($this->fk_category);
        if (isset($this->fk_site))
            $this->fk_site = intval($this->fk_site);
        if (isset($this->remote_id))
            $this->remote_id = trim($this->remote_id);
        if (isset($this->remote_parent_id))
            $this->remote_parent_id = trim($this->remote_parent_id);
        if (isset($this->last_update))
            $this->last_update = trim($this->last_update);
        //...
        // Check parameters
        // Put here code to add control on parameters values
        // Update request
        $sql = "UPDATE " . MAIN_DB_PREFIX . $this->table_element . " SET";
        $sql.= " label='" . $this->db->escape($this->label) . "',";
        $sql.= " type=" . (isset($this->type) ? intval($this->type) : 0) . ",";
        $sql.= " description='" . $this->db->escape($this->description) . "',";
        $sql.= " fk_category=" . (isset($this->fk_category) ? intval($this->fk_category) : 0) . ",";
        $sql.= " fk_site=" . (isset($this->fk_site) ? intval($this->fk_site) : 0) . ",";
        $sql.= " remote_id=" . (isset($this->remote_id) ? intval($this->remote_id) : 0) . ",";
        $sql.= " remote_parent_id=" . (isset($this->remote_parent_id) ? intval($this->remote_parent_id) : 0) . ",";
        $sql.= " last_update=" . (isset($this->last_update) ? "'" . $this->db->idate($this->last_update) . "'" : "null") . "";
        $sql.= " WHERE rowid=" . $this->id;

        $this->db->begin();

        dol_syslog(get_class($this) . "::update sql=" . $sql, LOG_DEBUG);
        $resql = $this->db->query($sql);
        if (!$resql)
        {
            $error++;
            $this->errors[] = "Error " . $this->db->lasterror();
        }

        if (!$error)
        {
            if (!$notrigger)
            {
                // Uncomment this and change MYOBJECT to your own tag if you
                // want this action call a trigger.
                //// Call triggers
                //include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
                //$interface=new Interfaces($this->db);
                //$result=$interface->run_triggers('MYOBJECT_MODIFY',$this,$user,$langs,$conf);
                //if ($result < 0) { $error++; $this->errors=$interface->errors; }
                //// End call triggers
            }
        }

        // Commit or rollback
        if ($error)
        {
            foreach ($this->errors as $errmsg)
            {
                dol_syslog(get_class($this) . "::update " . $errmsg, LOG_ERR);
                $this->error.=($this->error ? ', ' . $errmsg : $errmsg);
            }
            $this->db->rollback();
            return -1 * $error;
        } else
        {
            $this->db->commit();
            return 1;
        }
    }

    /**
     *   Delete object in database
     *
     * 	 @param     User    $user        	User that delete
     *   @param     int     $notrigger	    0=launch triggers after, 1=disable triggers
     * 	 @return	int		          		<0 if KO, >0 if OK
     */
    function delete($user, $notrigger = 0)
    {
        global $conf, $langs;
        $error = 0;

        $sql = "DELETE FROM " . MAIN_DB_PREFIX . $this->table_element;
        $sql.= " WHERE rowid=" . $this->id;

        $this->db->begin();

        dol_syslog(get_class($this) . "::delete sql=" . $sql);
        $resql = $this->db->query($sql);
        if (!$resql)
        {
            $error++;
            $this->errors[] = "Error " . $this->db->lasterror();
        }

        if (!$error)
        {
            if (!$notrigger)
            {
                // Uncomment this and change MYOBJECT to your own tag if you
                // want this action call a trigger.
                //// Call triggers
                //include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
                //$interface=new Interfaces($this->db);
                //$result=$interface->run_triggers('MYOBJECT_DELETE',$this,$user,$langs,$conf);
                //if ($result < 0) { $error++; $this->errors=$interface->errors; }
                //// End call triggers
            }
        }

        // Commit or rollback
        if ($error)
        {
            foreach ($this->errors as $errmsg)
            {
                dol_syslog(get_class($this) . "::delete " . $errmsg, LOG_ERR);
                $this->error.=($this->error ? ', ' . $errmsg : $errmsg);
            }
            $this->db->rollback();
            return -1 * $error;
        } else
        {
            $this->db->commit();
            return 1;
        }
    }

    /**
     * Get the last date of the last updated category
     *
     * @param   int     $site       eCommerceSite id | * for each sites
     * @return  datetime
     */
    public function getLastUpdate($site)
    {
        global $langs;
        $sql = "SELECT MAX(t.last_update) as lastdate FROM " . MAIN_DB_PREFIX . $this->table_element . " as t";
        $sql.= " WHERE t.fk_site = " . $site;
        dol_syslog(get_class($this) . "::getLastUpdate sql=" . $sql, LOG_DEBUG);

        $lastdate = null;

        $resql = $this->db->query($sql);
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
            $this->error = "Error " . $this->db->lasterror();
            dol_syslog(get_class($this) . "::getLastUpdate " . $this->error, LOG_ERR);
        }
        return $lastdate;
    }

    /**
     * 		Function to compare date in remoteCatToCheck and date into sync table to see if category was changed on Magento side
     *
     * 		@param		int        $siteId				site id from eCommerceSynchro
     * 		@param		string     $toDate				$toDate is now from eCommerceSynchro. Not used.
     * 		@param		string     $remoteCatToCheck    Magento category cutted from magento tree (is an array)
     * 		@return		1 to add this category to update array, 0 to not add this category
     */
    public function checkForUpdate($siteId, $toDate, $remoteCatToCheck)
    {
        global $langs;
        $updateRequired = 0;  // If any error occurs, category won't appears in update array

        $sql = "SELECT t.last_update as lastdate, t.remote_parent_id as parentid, t.fk_category FROM " . MAIN_DB_PREFIX . $this->table_element . " as t";
        $sql.= " WHERE t.remote_id=" . $remoteCatToCheck['category_id'] . " AND t.fk_site = " . $siteId;

        $resql = $this->db->query($sql);
        if ($resql)
        {
            if ($this->db->num_rows($resql))
            {
                $obj = $this->db->fetch_object($resql);

                $diffdates = false;
                $diffvalues = false;
                if (empty($remoteCatToCheck['updated_at'])) {
                    $catObj = new Categorie($this->db);
                    $ret = $catObj->fetch($obj->fk_category);
                    if ($ret > 0) {
                        $diffvalues = $catObj->label != trim($remoteCatToCheck['name']) || $catObj->description != trim($remoteCatToCheck['description']);
                    }
                } else {
                    $now = $toDate;  // Dolibarr's category time
                    $lu = $this->db->jdate($obj->lastdate);                 // date of last update process
                    $lumage = strtotime($remoteCatToCheck['updated_at']);
                    //var_dump($lu);
                    //var_dump($lumage);
                    $diffdates = $lu < $lumage;
                }

                $updateRequired = ($obj->parentid != $remoteCatToCheck['parent_id'] || $diffdates || $diffvalues) ? 1 : 0;
            } else
            {
                $updateRequired = 1;
            }
            $this->db->free($resql);
        }
        else
        {
            $this->error = "Error " . $this->db->lasterror();
            dol_syslog(get_class($this) . "::checkForUpdate " . $this->error, LOG_ERR);
            return -1;
        }
        return $updateRequired;
    }

    /**
     *    Load object in memory from database by remote_id
     *
     *    @param	int    $remoteId      string remote_id
     *    @param	int    $siteId        int fk_site
     *    @return	int                   <0 if KO or not found, >0 if OK
     */
    public function fetchByRemoteId($remoteId, $siteId)
    {
        global $langs;
        $sql = "SELECT ";
        $sql.= "t.rowid,";
        $sql.= "t.label,";
        $sql.= "t.type,";
        $sql.= "t.description,";
        $sql.= "t.fk_category,";
        $sql.= "t.fk_site,";
        $sql.= "t.remote_id,";
        $sql.= "t.remote_parent_id,";
        $sql.= "t.last_update";
        $sql.= " FROM " . MAIN_DB_PREFIX . $this->table_element . " as t";
        $sql.= " WHERE t.fk_site = " . $siteId;
        $sql.= " AND t.remote_id = " . $remoteId;

        dol_syslog(get_class($this) . "::fetchByRemoteId sql=" . $sql, LOG_DEBUG);
        $resql = $this->db->query($sql);

        if ($resql)
        {
            if ($this->db->num_rows($resql))
            {
                $obj = $this->db->fetch_object($resql);

                $this->id = $obj->rowid;
                $this->label = $obj->label;
                $this->type = $obj->type;
                $this->description = $obj->description;
                $this->fk_category = $obj->fk_category;
                $this->fk_site = $obj->fk_site;
                $this->remote_id = $obj->remote_id;
                $this->remote_parent_id = $obj->remote_parent_id;
                $this->last_update = $obj->last_update;

                $this->db->free($resql);
                return 1;
            }
            $this->db->free($resql);
            return 0;
        } else
        {
            $this->error = "Error " . $this->db->lasterror();
            dol_syslog(get_class($this) . "::fetchByRemoteId " . $this->error, LOG_ERR);
            return -1;
        }
    }

    /**
     * 		Function used to call a fetchByRemoteId by fKCat (to check if an eCommerceCat
     * 		exists corresponding with the importRoot defined in eCommerceSite)
     *
     * 		@param		int     $fKCategory     $fKCategory from eCommerceSite
     *      @param      int     $siteId         eCommerceSite Id
     * 		@return		fetchByRemoteId's result, -2 if error in this method
     */
    public function fetchByFKCategory($fKCategory, $siteId)
    {
        global $langs;

        $sql = "SELECT ";
        $sql.= "t.remote_id";
        $sql.= " FROM " . MAIN_DB_PREFIX . $this->table_element . " as t";
        $sql.= " WHERE t.fk_site = " . $siteId;
        $sql.= " AND t.fk_category= " . $fKCategory;

        dol_syslog(get_class($this) . "::fetchByFKCategory sql=" . $sql, LOG_DEBUG);
        $resql = $this->db->query($sql);

        if ($resql)
        {
            if ($this->db->num_rows($resql))
            {
                $obj = $this->db->fetch_object($resql);
                $resCall = $this->fetchByRemoteId($obj->remote_id, $siteId);

                $this->db->free($resql);
                return $resCall;
            }
            $this->db->free($resql);
            return -2;
        } else
        {
            $this->error = "Error " . $this->db->lasterror();
            dol_syslog(get_class($this) . "::fetchByFKCategory " . $this->error, LOG_ERR);
            return -2;
        }
    }

    /**
     * 		Function to put each category of a tree in a unique array. Children are always after parents.
     *
     * 		@param		array 		$tree      Array containing arrays of the same kind
     * 		@return		array		           array containing each category on a unique level (without the tree root)
     */
    public function cuttingCategoryTreeFromMagentoToDolibarr($tree)
    {
        foreach ($tree['children'] as $subCat)
        {
            // For each child, we check if it has children to, and call this function again in this case
            if (!empty($subCat['children']))
            // We put the return of this call in a temp array
                $subRes = array_values(self::cuttingCategoryTreeFromMagentoToDolibarr($subCat));

            array_pop($subCat);   // Now children are useless in $subcat, we can pop it (to relieve array work)
            $res[] = $subCat;   // We add the popped $subcat to $res
            // $subres is a one-leveled array, so we iterate over it and put each cat it contains in $res
            if ($subRes)
            {
                array_reverse($subRes); // For children of a child to be position-ordered after its parent
                foreach ($subRes as $cat)
                    $res[] = $cat; // We add children behind parent
            }
        }
        return $res;
    }

    /**
     * 		Function to put each category of a tree in a unique array. Children are always after parents.
     *
     * 		@param	   array       $tree          Array containing arrays of the same kind
     *      @param     array       $resanswer     Array containing each category on a unique level (without the tree root)
     * 		@return    void
     */
    public function cuttingCategoryTreeFromMagentoToDolibarrNew($tree, &$resanswer)
    {
        $tmp=$tree;
        if (isset($tmp['level']) && $tmp['level']==0) $tmp=$tree['children'];

        foreach ($tmp as $subCat)
        {
            $savchildren = null;
            if (isset($subCat['children']))
            {
                $savchildren=$subCat['children'];
                unset($subCat['children']);
            }

            //var_dump($subCat);

            $resanswer[] = $subCat;   // We add the popped $subcat to $res

            // For each child, we check if it has children to, and call this function again in this case
            if (!empty($savchildren))
            {
                // We put the return of this call in a temp array
                self::cuttingCategoryTreeFromMagentoToDolibarrNew($savchildren, $resanswer);
            }
        }
    }

    /**
     * 		Function to return list of id of Dolibarr categories from a list of remoteCategoriesIds
     *
     * 		@param		array	$ids        Array of remote ids
     * 		@return		array	            Array of categories' ids
     */
    public function getDolibarrCategoryFromRemoteIds($ids)
    {
        if (count($ids))
        {
            $catsIds = array();
            $idsCount = count($ids);
            $ii = 0;

            $sql = "SELECT fk_category FROM " . MAIN_DB_PREFIX . $this->table_element;
            $sql .= " WHERE remote_id IN(";
            foreach ($ids as $remoteId)
            {
                $ii++;
                $sql .= $remoteId . ($ii == $idsCount ? ")" : ",");
            }

            dol_syslog(get_class($this) . "::getDolibarrCategoryFromRemoteIds sql=" . $sql, LOG_DEBUG);
            $resql = $this->db->query($sql);

            if ($resql)
            {
                $ii = $this->db->num_rows($resql);

                while ($ii > 0) {
                    $obj = $this->db->fetch_object($resql);
                    $catsIds[] = intval($obj->fk_category);
                    $ii--;
                }
                $this->db->free($resql);
            } else
            {
                $this->error = "Error " . $this->db->lasterror();
                dol_syslog(get_class($this) . "::getDolibarrCategoryFromRemoteIds " . $this->error, LOG_ERR);
            }

            return $catsIds;
        }
    }

    /**
     * 		Select all the ids from eCommerceCategory for a site
     *
     * 		@param 	ECommerceSite  $eCommerceSite      Object eCommerceSite
     * 		@return array	                           synchObject ids for this site
     */
    public function getAllECommerceCategoryIds($eCommerceSite)
    {
        global $langs;

        $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "ecommerce_category";
        $sql.= " WHERE fk_site = " . $eCommerceSite->id . " AND fk_category <> " . intval($eCommerceSite->fk_cat_product);
        dol_syslog(get_class($this) . "::getAllECommerceCategoryIds sql=" . $sql, LOG_DEBUG);
        $resql = $this->db->query($sql);

        if ($resql)
        {
            $idsArray = array();
            $num = $this->db->num_rows($resql);
            $ii = 0;
            while ($ii < $num) {
                $obj = $this->db->fetch_object($resql);
                $idsArray[] = intval($obj->rowid);
                $ii++;
            }
            $this->db->free($resql);
            return $idsArray;
        } else
        {
            $this->error = "Error " . $this->db->lasterror();
            dol_syslog(get_class($this) . "::getAllECommerceCategoryIds " . $this->error, LOG_ERR);
            return -1;
        }
    }

}

