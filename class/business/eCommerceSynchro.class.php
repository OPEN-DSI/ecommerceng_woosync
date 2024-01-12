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

/**
 * Class for synchronize remote sites with Dolibarr
 */

dol_include_once('/ecommerceng/class/data/eCommerceRemoteAccess.class.php');
dol_include_once('/ecommerceng/class/data/eCommerceCommande.class.php');
dol_include_once('/ecommerceng/class/data/eCommerceFacture.class.php');
dol_include_once('/ecommerceng/class/data/eCommerceProduct.class.php');
dol_include_once('/ecommerceng/class/data/eCommerceSociete.class.php');
dol_include_once('/ecommerceng/class/data/eCommerceSocpeople.class.php');
dol_include_once('/ecommerceng/class/data/eCommerceSite.class.php');
dol_include_once('/ecommerceng/class/data/eCommerceCategory.class.php');
dol_include_once('/ecommerceng/admin/class/data/eCommerceDict.class.php');
dol_include_once('/ecommerceng/class/business/eCommerceUtils.class.php');

require_once(DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php');
require_once(DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php');
require_once(DOL_DOCUMENT_ROOT . '/product/class/product.class.php');
require_once(DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php');
require_once(DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.facture.class.php');
require_once(DOL_DOCUMENT_ROOT . '/compta/paiement/class/paiement.class.php');
require_once(DOL_DOCUMENT_ROOT . '/fourn/class/paiementfourn.class.php');
require_once(DOL_DOCUMENT_ROOT . '/core/class/discount.class.php');
require_once(DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php');
require_once(DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php');
require_once(DOL_DOCUMENT_ROOT . '/expedition/class/expedition.class.php');
require_once(DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php');



class eCommerceSynchro
{
    public $error;
    public $errors=array();
    public $success=array();
	public $warnings=array();
    public $langs;
    public $user;

    //Data access
    /**
     * @var DoliDB
     */
    private $db;
    public $eCommerceRemoteAccess;

    /**
     * @var eCommerceSite
     */
    private $eCommerceSite;
    /**
     * @var eCommerceSociete
     */
    private $eCommerceSociete;
    /**
     * @var eCommerceSocpeople
     */
    private $eCommerceSocpeople;
    /**
     * @var eCommerceProduct
     */
    private $eCommerceProduct;
    /**
     * @var eCommerceCategory
     */
    private $eCommerceCategory;
    /**
     * @var eCommerceCategory
     */
    private $eCommerceMotherCategory;
    /**
     * @var eCommerceCommande
     */
    private $eCommerceCommande;
    /**
     * @var eCommerceFacture
     */
    private $eCommerceFacture;
    //class members
	public $fromDate;
	public $toDate;
    public $toNb;

    private $societeLastUpdateDate;
    private $sopeopleLastUpdateDate;
    private $productLastUpdateDate;
    private $commandeLastUpdateDate;
    private $factureLastUpdateDate;

    private $societeToUpdate;
    private $socpeopleToUpdate;
    private $productToUpdate;
    private $categoryToUpdate;
    private $commandeToUpdate;
    private $factureToUpdate;

	public $cache_categories;
	public static $default_extra_fields_cached;

	public $payment_gateways_cached;
	public $product_category_cached;


	/**
     * Constructor
     *
     * @param Database          $db           Database handler
     * @param eCommerceSite     $site         Object eCommerceSite
     * @param datetime          $fromDate     Beginning date to synch all data modified before this date
	 * @param datetime          $toDate       Ending date to synch all data modified before this date (null by default = until now)
     * @param int               $toNb         Max nb of record to count or synch (Used only for synch, not for count for the moment !)
     */
    function __construct($db, $site, $fromDate=null, $toDate=null, $toNb=0)
    {
        global $langs, $user;

        try {
            $this->langs = $langs;
            $this->user = $user;
            $this->db = $db;
            $this->eCommerceSite = $site;
			$this->langs->load('ecommerceng@ecommerceng');

            $this->eCommerceRemoteAccess = new eCommerceRemoteAccess($this->db, $this->eCommerceSite);

			$this->fromDate = is_numeric($fromDate) ? $fromDate : null;
            $this->toDate = is_numeric($toDate) ? $toDate : null;
//			if (empty($toDate)) $this->toDate = (dol_now() - 10);      // Set date to use as last update date (we remove 10 second to be sure we don't have pb with not sync date)
//			else $this->toDate = $toDate;
        }
        catch (Exception $e)
        {
            $this->errors[] = $this->langs->trans('ECommerceConnectErrorCheckUsernamePasswordAndAdress');
        }
    }

    /**
     * Connect to remote
     */
    function connect()
    {
        dol_syslog("eCommerceSynchro Connect to remote", LOG_DEBUG);

        try
        {
            if (! $this->eCommerceRemoteAccess->connect())
            {
                $this->error = $this->langs->trans('ECommerceConnectErrorCheckUsernamePasswordAndAdress');
                $this->errors[] = $this->error;
                $this->errors= array_merge($this->errors, $this->eCommerceRemoteAccess->errors);
                dol_syslog("eCommerceSynchro Connect error ".$this->error, LOG_DEBUG);
                return -1;
            }
            else
            {
                dol_syslog("eCommerceSynchro Connected", LOG_DEBUG);
            }

            return 1;
        }
        catch (Exception $e)
        {
            $this->errors[] = $this->langs->trans('ECommerceConnectErrorCheckUsernamePasswordAndAdress');
        }

        return -1;
    }

	/**
	 * Get default extrafields
	 *
	 * @param	string			$table_element	Table element
	 * @param	eCommerceSite	$site			Site handler
	 * @return	array							List of extrafields values by default
	 */
	public function getDefaultExtraFields($table_element, $site)
	{
		if (!isset(self::$default_extra_fields_cached[$table_element][$site->id])) {
			require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
			$extra_fields = new Extrafields($this->db);
			$extra_fields->fetch_name_optionals_label($table_element);

			$values = [];
            if (is_array($extra_fields->attributes[$table_element]['label'])) {
                foreach ($extra_fields->attributes[$table_element]['label'] as $key => $value) {
                    if (preg_match('/^ecommerceng_/', $key)) continue;

                    $value = null;
                    if (!empty($site->parameters['extra_fields'][$table_element]['activated']['dft'][$key])) {
                        $value = $site->parameters['extra_fields'][$table_element]['values']['dft'][$key];
                    }
                    if (!isset($value)) $value = $extra_fields->attributes[$table_element]['default'][$key];
                    $values['options_' . $key] = $value;
                }
            }

			self::$default_extra_fields_cached[$table_element] = $values;
		}

		return self::$default_extra_fields_cached[$table_element];
	}

    /**
     * Getter for toDate
     */
    public function getToDate()
    {
        return $this->toDate;
    }

    /**
     * Instanciate eCommerceSociete data class access
     */
    private function initECommerceSociete()
    {
        $this->eCommerceSociete = new eCommerceSociete($this->db);
    }

    /**
     * Instanciate eCommerceSocpeople data class access
     */
    private function initECommerceSocpeople()
    {
        $this->eCommerceSocpeople = new eCommerceSocpeople($this->db);
    }

    /**
     * Instanciate eCommerceProduct data class access
     */
    private function initECommerceProduct()
    {
        $this->eCommerceProduct = new eCommerceProduct($this->db);
    }

    /**
     * Instanciate eCommerceCategory data class access
     */
    private function initECommerceCategory()
    {
        $this->eCommerceCategory = new eCommerceCategory($this->db);
        $this->eCommerceMotherCategory = new eCommerceCategory($this->db);
    }

    /**
     * Instanciate eCommerceCommande data class access
     */
    private function initECommerceCommande()
    {
        $this->eCommerceCommande = new eCommerceCommande($this->db);
    }

    /**
     * Instanciate eCommerceFacture data class access
     */
    private function initECommerceFacture()
    {
        $this->eCommerceFacture = new eCommerceFacture($this->db);
    }



    /**
     * Get the last date of product update
     *
     * @param Boolean       $force      Bool to force update
     * @return datetime                 Datetime
     */
    public function getProductLastUpdateDate($force = false)
    {
		global $conf;
		try {
            if (!isset($this->productLastUpdateDate) || $force == true)
            {
//                if (!isset($this->eCommerceProduct))
//                    $this->initECommerceProduct();
//                $this->productLastUpdateDate = $this->eCommerceProduct->getLastUpdate($this->eCommerceSite->id);
				$last_sync_date = 'ECOMMERCE_LAST_SYNC_DATE_PRODUCT_' . $this->eCommerceSite->id;
				$this->productLastUpdateDate = isset($conf->global->$last_sync_date) ? $conf->global->$last_sync_date : null;
			}
            return $this->productLastUpdateDate;
        } catch (Exception $e) {
            $this->errors[] = $this->langs->trans('ECommerceErrorGetProductLastUpdateDate');
        }
    }

    /**
     * Get the last date of societe update
     *
     * @param   boolean     $force      Bool to force update
     * @return  datetime                Date time
     */
    public function getSocieteLastUpdateDate($force = false)
    {
    	global $conf;
        try {
            if (!isset($this->societeLastUpdateDate) || $force == true)
            {
//                if (!isset($this->eCommerceSociete))
//                    $this->initECommerceSociete();      // Init $this->eCommerceSociete
//                $this->societeLastUpdateDate = $this->eCommerceSociete->getLastUpdate($this->eCommerceSite->id);
				$last_sync_date = 'ECOMMERCE_LAST_SYNC_DATE_CUSTOMER_' . $this->eCommerceSite->id;
				$this->societeLastUpdateDate = isset($conf->global->$last_sync_date) ? $conf->global->$last_sync_date : null;
			}
            return $this->societeLastUpdateDate;
        } catch (Exception $e) {
            $this->errors[] = $this->langs->trans('ECommerceErrorGetSocieteLastUpdateDate');
        }
    }

    /**
     * Get the last date of commande update
     *
     * @param   boolean     $force      Bool to force update
     * @return  datetime                Date time
     */
    public function getCommandeLastUpdateDate($force = false)
    {
		global $conf;
		try {
            if (!isset($this->commandeLastUpdateDate) || $force == true)
            {
//                if (!isset($this->eCommerceCommande))
//                    $this->initECommerceCommande();
//                $this->commandeLastUpdateDate = $this->eCommerceCommande->getLastUpdate($this->eCommerceSite->id);
				$last_sync_date = 'ECOMMERCE_LAST_SYNC_DATE_ORDER_' . $this->eCommerceSite->id;
				$this->commandeLastUpdateDate = isset($conf->global->$last_sync_date) ? $conf->global->$last_sync_date : null;
			}
            return $this->commandeLastUpdateDate;
        } catch (Exception $e) {
            $this->errors[] = $this->langs->trans('ECommerceErrorGetCommandeLastUpdateDate');
        }
    }

    /**
     * Get the last date of facture update
     *
     * @param   boolean     $force      Bool to force update
     * @return  datetime                Date time
     */
    public function getFactureLastUpdateDate($force = false)
    {
        try {
            if (!isset($this->eCommerceFactureLastUpdateDate) || $force == true)
            {
                if (!isset($this->eCommerceFacture))
                    $this->initECommerceFacture();
                $this->factureLastUpdateDate = $this->eCommerceFacture->getLastUpdate($this->eCommerceSite->id);
            }
            return $this->factureLastUpdateDate;
        } catch (Exception $e) {
            $this->errors[] = $this->langs->trans('ECommerceErrorGetFactureLastUpdateDate');
        }
    }



    public function getNbCategoriesInDolibarr()
    {
        $sql="SELECT COUNT(rowid) as nb FROM ".MAIN_DB_PREFIX."categorie WHERE type = 0";
		$sql .= " AND entity IN (" . getEntity('category') . ")";
		$resql=$this->db->query($sql);
        if ($resql)
        {
            $obj=$this->db->fetch_object($resql);
            return $obj->nb;
        }
        else
        {
            return -1;
        }
    }

    public function getNbCategoriesInDolibarrLinkedToE($excludeid = 0)
    {
        $sql="SELECT COUNT(rowid) as nb FROM ".MAIN_DB_PREFIX."ecommerce_category WHERE type = 0 AND fk_site=".$this->eCommerceSite->id;
        $sql.=" AND fk_category <> ".$excludeid;
        $resql=$this->db->query($sql);
        if ($resql)
        {
            $obj=$this->db->fetch_object($resql);
            return $obj->nb;
        }
        else
        {
            return -1;
        }
    }

    /**
     * Rebuilding the category tree as an array
   	 * Return an array of table('id','id_mere',...) trie selon arbre et avec:
   	 *                id = id de la categorie
   	 *                id_mere = id de la categorie mere
   	 *                id_children = tableau des id enfant
   	 *                label = nom de la categorie
   	 *                fulllabel = nom avec chemin complet de la categorie
   	 *                fullpath = chemin complet compose des id
   	 *
   	 * @param   string $type        Type of categories ('customer', 'supplier', 'contact', 'product', 'member').
   	 *                              Old mode (0, 1, 2, ...) is deprecated.
   	 * @param   int    $parent_id   Id of parent category (0 for root).
   	 *
     * @return  array               Array of categories. this->cats and this->motherof are set.
   	 */
    public function fetch_categories($type, $parent_id=0) {
        if (!isset($this->cache_categories[$type])) {
            require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
            $cat = new Categorie($this->db);
            $all_cat = $cat->get_full_arbo($type);

            $parent_id = $parent_id > 0 ? $parent_id : '';
            $cat_arbo = array();
            foreach ($all_cat as $category) {
                if (preg_match('/_'.$parent_id.'(_|$)/', $category['fullpath'])) {
                    $cat_arbo[$category['id']] = $category;
                }
            }

            $this->cache_categories[$type] = $cat_arbo;
        }
    }

    public function getNbCategoriesInDolibarrNotLinkedToE($excludeid = 0)
    {
        $this->fetch_categories('product', $this->eCommerceSite->fk_cat_product);
        $cats_id = array_keys($this->cache_categories['product']);
        $nb_cat = count($this->cache_categories['product']) - 1;
        $sql="SELECT COUNT(rowid) as nb FROM ".MAIN_DB_PREFIX."ecommerce_category" .
             " WHERE type = 0 AND fk_site=".$this->eCommerceSite->id." AND fk_category IN (".implode(',', $cats_id).")".
             " AND fk_category <> ".$excludeid;
        $resql=$this->db->query($sql);
        if ($resql)
        {
            $obj=$this->db->fetch_object($resql);
            return $nb_cat - $obj->nb;
        }
        else
        {
            return -1;
        }
    }

    public function getNbProductInDolibarr()
    {
        $sql="SELECT COUNT(rowid) as nb FROM ".MAIN_DB_PREFIX."product";
		$sql .= " WHERE entity IN (" . getEntity('product') . ")";
		$resql=$this->db->query($sql);
        if ($resql)
        {
            $obj=$this->db->fetch_object($resql);
            return $obj->nb;
        }
        else
        {
            return -1;
        }
    }

    public function getNbProductInDolibarrLinkedToE()
    {
        $sql="SELECT COUNT(rowid) as nb FROM ".MAIN_DB_PREFIX."ecommerce_product WHERE fk_site=".$this->eCommerceSite->id;
        $resql=$this->db->query($sql);
        if ($resql)
        {
            $obj=$this->db->fetch_object($resql);
            return $obj->nb;
        }
        else
        {
            return -1;
        }
    }

    public function getNbProductInDolibarrNotLinkedToE()
    {
        $this->fetch_categories('product', $this->eCommerceSite->fk_cat_product);
        $cats_id = array_keys($this->cache_categories['product']);
        $sql="SELECT COUNT(DISTINCT p.rowid) as nb FROM ".MAIN_DB_PREFIX."product as p" .
            " INNER JOIN ".MAIN_DB_PREFIX."categorie_product as cp ON p.rowid = cp.fk_product AND cp.fk_categorie IN (".implode(',', $cats_id).")" .
			" LEFT JOIN ".MAIN_DB_PREFIX."ecommerce_product as ep ON p.rowid = ep.fk_product AND ep.fk_site=".$this->eCommerceSite->id .
			" LEFT JOIN ".MAIN_DB_PREFIX."product_extrafields as pf ON pf.fk_object = p.rowid" .
			" WHERE (ep.rowid IS NULL OR ep.last_update < p.tms OR (pf.tms IS NOT NULL AND ep.last_update < pf.tms))" .
			" AND p.entity IN (" . getEntity('product') . ")";
        $resql=$this->db->query($sql);
        if ($resql)
        {
            $obj=$this->db->fetch_object($resql);
            return $obj->nb;
        }
        else
        {
            return -1;
        }
    }

    public function getNbSocieteInDolibarr()
    {
        /*$sql="SELECT COUNT(s.rowid) as nb FROM ".MAIN_DB_PREFIX."societe as s, ".MAIN_DB_PREFIX."categorie_societe as cs";
        $sql.=" WHERE s.rowid = cs.fk_soc AND cs.fk_categorie = ".$this->eCommerceSite->fk_cat_societe;
		*/
    	$sql="SELECT COUNT(s.rowid) as nb FROM ".MAIN_DB_PREFIX."societe as s";
		$sql .= " WHERE s.entity IN (" . getEntity('societe') . ")";

        $resql=$this->db->query($sql);
        if ($resql)
        {
            $obj=$this->db->fetch_object($resql);
            return $obj->nb;
        }
        else
        {
            return -1;
        }
    }

    public function getNbSocieteInDolibarrLinkedToE()
    {
        $sql="SELECT COUNT(s.rowid) as nb FROM ".MAIN_DB_PREFIX."ecommerce_societe as s WHERE fk_site=".$this->eCommerceSite->id;

        $resql=$this->db->query($sql);
        if ($resql)
        {
            $obj=$this->db->fetch_object($resql);
            return $obj->nb;
        }
        else
        {
            return -1;
        }
    }

    public function getNbCommandeInDolibarr()
    {
        $sql="SELECT COUNT(rowid) as nb FROM ".MAIN_DB_PREFIX."commande";
		$sql .= " WHERE entity IN (" . getEntity('commande') . ")";
        $resql=$this->db->query($sql);
        if ($resql)
        {
            $obj=$this->db->fetch_object($resql);
            return $obj->nb;
        }
        else
        {
            return -1;
        }
    }

    public function getNbCommandeInDolibarrLinkedToE()
    {
        $sql="SELECT COUNT(rowid) as nb FROM ".MAIN_DB_PREFIX."ecommerce_commande WHERE fk_site=".$this->eCommerceSite->id;
        $resql=$this->db->query($sql);
        if ($resql)
        {
            $obj=$this->db->fetch_object($resql);
            return $obj->nb;
        }
        else
        {
            return -1;
        }
    }

    public function getNbFactureInDolibarr()
    {
        $sql="SELECT COUNT(rowid) as nb FROM ".MAIN_DB_PREFIX."facture";
		$sql .= " WHERE entity IN (" . getEntity('facture') . ")";
        $resql=$this->db->query($sql);
        if ($resql)
        {
            $obj=$this->db->fetch_object($resql);
            return $obj->nb;
        }
        else
        {
            return -1;
        }
    }

    public function getNbFactureInDolibarrLinkedToE()
    {
        $sql="SELECT COUNT(rowid) as nb FROM ".MAIN_DB_PREFIX."ecommerce_facture WHERE fk_site=".$this->eCommerceSite->id;
        $resql=$this->db->query($sql);
        if ($resql)
        {
            $obj=$this->db->fetch_object($resql);
            return $obj->nb;
        }
        else
        {
            return -1;
        }
    }

    /**
     * Return list o categories to update
     *
     * @param   boolean     $force      Force analysis of list, even if array list $this->categoryToUpdate is already defined
     */
    public function getCategoriesToUpdate($force = false)
	{
		if (!isset($this->categoryToUpdate) || $force == true) {
			$this->categoryToUpdate = array();

			// get a magento category tree in a one-leveled array
			$tmp = $this->eCommerceRemoteAccess->getRemoteCategoryTree();
			if (is_array($tmp)) {
				$resanswer = array();
				eCommerceCategory::cuttingCategoryTreeFromMagentoToDolibarrNew($tmp, $resanswer);

				$this->initECommerceCategory(); // Initialise 2 properties eCommerceCategory and eCommerceMotherCategory

				// $resanswer is array with all categories
				// We must loop on each categorie.
				foreach ($resanswer as $remoteCatToCheck) // Check update for each entry into $resanswer -> $remoteCatToCheck = array('category_id'=>, 'parent_id'=>...)
				{
					// Test if category is disabled or not
					if (isset($remoteCatToCheck['is_active']) && empty($remoteCatToCheck['is_active'])) // We keep because children may not be disabled.
					{
						dol_syslog("Category remote_id=" . $remoteCatToCheck['category_id'] . ", category is disabled.");
					}
					//else
					//{
					if (!isset($remoteCatToCheck['updated_at'])) {   // The api that returns list of category did not return the updated_at property
						// This is very long if there is a lot of categories because we make a WS call to get the 'updated_at' info at each loop pass.
						dol_syslog("Process category remote_id=" . $remoteCatToCheck['category_id'] . ", updated_at unknow.");

						// Complete info of $remoteCatToCheck['category_id']
						$tmp = $this->eCommerceRemoteAccess->getCategoryData($remoteCatToCheck['category_id']);   // This make a SOAP call

						$remoteCatToCheck['updated_at'] = $tmp['updated_at']; // Complete data we are missing
					} else {
						dol_syslog("Process category remote_id=" . $remoteCatToCheck['category_id'] . ", updated_at is defined to " . $remoteCatToCheck['updated_at']);
					}

					// If the category was updated before the max limit date this->toDate
					if (strtotime($remoteCatToCheck['updated_at']) <= $this->toDate) {
						// Check into link table ecommerce_category if record is older (so if has been modified on magento or not)
						if ($this->eCommerceCategory->checkForUpdate($this->eCommerceSite->id, $this->toDate, $remoteCatToCheck))   // compare date in remoteCatToCheck and date in sync table. $this->toDate is not used.
							$this->categoryToUpdate[] = $remoteCatToCheck;
					}
					//}
				}

				//var_dump($this->categoryToUpdate);exit;
				dol_syslog("Now tree are in an array ordered by hierarchy. Nb of record = " . count($this->categoryToUpdate));
				return $this->categoryToUpdate;
			} else {
				$this->errors[] = $this->langs->trans('ECommerceErrorGetCategoryToUpdate');
				$this->errors[] = $this->eCommerceRemoteAccess->errorsToString();
			}
		}

		return false;
	}

    /**
     * Get modified product since the last update
     *
     * @param   int     $force      Bool to force to reload cache list $this->productToUpdate
     * @return  array               Array of remote product (also stored into this->productToUpdate)
     */
    public function getProductToUpdate($force = false)
    {
        try {
            if (!isset($this->productToUpdate) || $force == true)
            {
                $lastupdatedate = $this->getProductLastUpdateDate($force);
                $this->productToUpdate = $this->eCommerceRemoteAccess->getProductToUpdate($lastupdatedate, $this->toDate);
            }
            if (empty($this->productToUpdate) && (! empty($this->error) || !empty($this->errors) || !empty($this->eCommerceRemoteAccess->error) || !empty($this->eCommerceRemoteAccess->errors)))
            {
                if (! empty($this->eCommerceRemoteAccess->error)) $this->error=$this->eCommerceRemoteAccess->error;
                if (! empty($this->eCommerceRemoteAccess->errors)) $this->errors=array_merge($this->errors, $this->eCommerceRemoteAccess->errors);
                return -1;
            }
            return $this->productToUpdate;
        } catch (Exception $e) {
            $this->errors[] = $this->langs->trans('ECommerceErrorGetProductToUpdate');
        }
    }

    /**
     * Get modified societe since the last update
     *
     * @param   int     $force      Bool to force to reload cache list $this->societeToUpdate
     * @return  array               Array of remote societe (also stored into this->societeToUpdate)
     */
    public function getSocieteToUpdate($force = false)
    {
        try {
            if (!isset($this->societeToUpdate) || $force == true)
            {
                $lastupdatedate=$this->getSocieteLastUpdateDate($force);
                $this->societeToUpdate = $this->eCommerceRemoteAccess->getSocieteToUpdate($lastupdatedate, $this->toDate);
            }
            if (empty($this->societeToUpdate) && (! empty($this->error) || !empty($this->errors) || !empty($this->eCommerceRemoteAccess->error) || !empty($this->eCommerceRemoteAccess->errors)))
            {
                if (! empty($this->eCommerceRemoteAccess->error)) $this->error=$this->eCommerceRemoteAccess->error;
                if (! empty($this->eCommerceRemoteAccess->errors)) $this->errors=array_merge($this->errors, $this->eCommerceRemoteAccess->errors);
                return -1;
            }
            return $this->societeToUpdate;
        } catch (Exception $e) {
            $this->errors[] = $this->langs->trans('ECommerceErrorGetSocieteToUpdate');
        }
    }

    /**
     * Get modified commande since the last update
     *
     * @param   int     $force      Bool to force to reload cache list $this->commandeToUpdate
     * @return  array               Array of remote order (also stored into this->commandeToUpdate)
     */
    public function getCommandeToUpdate($force = false)
    {
        try {
            if (!isset($this->commandeToUpdate) || $force == true)
            {
                $lastupdatedate=$this->getCommandeLastUpdateDate($force);
                $this->commandeToUpdate = $this->eCommerceRemoteAccess->getCommandeToUpdate($lastupdatedate, $this->toDate);
            }
            if (empty($this->commandeToUpdate) && (! empty($this->error) || !empty($this->errors) || !empty($this->eCommerceRemoteAccess->error) || !empty($this->eCommerceRemoteAccess->errors)))
            {
                $this->errors[] = $this->langs->trans('ECommerceErrorGetCommandeToUpdate');
                if (! empty($this->eCommerceRemoteAccess->error)) $this->error=$this->eCommerceRemoteAccess->error;
                if (! empty($this->eCommerceRemoteAccess->errors)) $this->errors=array_merge($this->errors, $this->eCommerceRemoteAccess->errors);
                return -1;
            }
            return $this->commandeToUpdate;
        } catch (Exception $e) {
            $this->errors[] = $this->langs->trans('ECommerceErrorGetCommandeToUpdate');
        }
    }

    /**
     * Get modified facture since the last update
     *
     * @param   int     $force      Bool to force to reload cache list $this->factureToUpdate
     * @return  array               Array of remote invoice (also stored into this->factureToUpdate)
     */
    public function getFactureToUpdate($force = false)
    {
        try {
            if (!isset($this->factureToUpdate) || $force == true)
            {
                $lastupdatedate=$this->getFactureLastUpdateDate($force);
                $this->factureToUpdate = $this->eCommerceRemoteAccess->getFactureToUpdate($lastupdatedate, $this->toDate);
            }
            if (empty($this->factureToUpdate) && (! empty($this->error) || !empty($this->errors) || !empty($this->eCommerceRemoteAccess->error) || !empty($this->eCommerceRemoteAccess->errors)))
            {
                if (! empty($this->eCommerceRemoteAccess->error)) $this->error=$this->eCommerceRemoteAccess->error;
                if (! empty($this->eCommerceRemoteAccess->errors)) $this->errors=array_merge($this->errors, $this->eCommerceRemoteAccess->errors);
                return -1;
            }
            return $this->factureToUpdate;
        } catch (Exception $e) {
            $this->errors[] = $this->langs->trans('ECommerceErrorGetFactureToUpdate');
        }
    }


    /* getNbXXXToUpdate */


    /**
     * Get count of modified product since the last update
     *
     * @param  boolean  $force      Bool to force update
     * @return int                  <0 if KO, >=0 if OK
     */
    public function getNbProductToUpdate($force = false)
    {
        try {
            $result = $this->getProductToUpdate($force);
            if (is_array($result)) return count($result);
            else return -1;
        } catch (Exception $e) {
            $this->errors[] = $this->langs->trans('ECommerceErrorGetNbSocieteToUpdate');
            return -2;
        }
    }

    /**
     * Get count of modified categories since the last update
     *
     * @param   boolean     $force      Bool to force update
     * @return  int                     <0 if KO, >=0 if OK
     */
    public function getNbCategoriesToUpdate($force = false)
    {
        try {
            $result = $this->getCategoriesToUpdate($force);
            if (is_array($result)) return count($result);
            else return -1;
        } catch (Exception $e) {
            $this->errors[] = $this->langs->trans('ECommerceErrorGetNbCategoriesToUpdate');
            return -2;
        }
    }

    /**
     * Get count of modified societe since the last update
     *
     * @param   boolean     $force      Bool to force update
     * @return  int                     <0 if KO, >=0 if OK
     */
    public function getNbSocieteToUpdate($force = false)
    {
        try {
            $result = $this->getSocieteToUpdate($force);
            if (is_array($result)) return count($result);
            else return -1;
        } catch (Exception $e) {
            $this->errors[] = $this->langs->trans('ECommerceErrorGetNbSocieteToUpdate');
            return -2;
        }
    }

    /**
     * Get count of modified commande since the last update
     *
     * @param   boolean     $force      Bool to force update
     * @return  int                     <0 if KO, >=0 if OK
     */
    public function getNbCommandeToUpdate($force = false)
    {
        try {
            $result = $this->getCommandeToUpdate($force);
            if (is_array($result)) return count($result);
            else return -1;
        } catch (Exception $e) {
            $this->errors[] = $this->langs->trans('ECommerceErrorGetNbSocieteToUpdate');
            return -2;
        }
    }

    /**
     * Get count of modified facture since the last update
     *
     * @param   boolean     $force      Bool to force update
     * @return  int                     <0 if KO, >=0 if OK
     */
    public function getNbFactureToUpdate($force = false)
    {
        try {
            $result = $this->getFactureToUpdate($force);
            if (is_array($result)) return count($result);
            else return -1;
        } catch (Exception $e) {
            $this->errors[] = $this->langs->trans('ECommerceErrorGetNbSocieteToUpdate');
            return -2;
        }
    }


    /**
     * 	Sync categories
     *
     *  @param  int     $toNb       Max nb to synch
     * 	@return int                 <0 if KO, >= 0 if ok
     */
    public function synchCategory($toNb=0)
    {
        $error=0;

		$stopwatch_id = eCommerceUtils::startAndLogStopwatch(__METHOD__ . " - toNb: {$toNb}");

        try {
            $nbgoodsunchronize = 0;
            $categories=array();

            dol_syslog("***** eCommerceSynchro synchCategory");

            // Safety check : importRootCategory exists
            $dBRootCategorie = new Categorie($this->db);
            $importRootExists = ($dBRootCategorie->fetch($this->eCommerceSite->fk_cat_product) > 0) ? 1 : 0;

            if ($importRootExists)
            {
                dol_syslog("synchCategory importRootExists=".$importRootExists);

                $resulttoupdate = $this->getCategoriesToUpdate();   // Return list of categories that were modified on ecommerce side
                /* Do not sort run this, we want to keep sort on parent categori first and not by updated_at date.
                if (is_array($resulttoupdate))
                {
                    if (count($resulttoupdate) > 0) $categories = $this->eCommerceRemoteAccess->convertRemoteObjectIntoDolibarrCategory($resulttoupdate,$toNb);
                }
                else
                {
                    $error++;
                }*/
                $categories=$resulttoupdate;

                // Check return of remote...
                if (is_array($resulttoupdate) && count($resulttoupdate) > 0 && (! is_array($categories) || count($categories) == 0))    // return of remote is bad or empty when input was not empty
                {
                    $error++;
                }
                if (! $error && is_array($categories))
                {
                    $counter = 0;
                    foreach ($categories as $categoryArray)     // Loop on each categories found on ecommerce side. Cursor is $categoryArray
                    {
                        $counter++;
                        if ($toNb > 0 && $counter > $toNb) break;

                        dol_syslog("synchCategory Process sync of ecommerce category remote_id=".$categoryArray['category_id']." name=".$categoryArray['name']." remote parent_id=".$categoryArray['parent_id']);

                        $this->db->begin();

                        $this->initECommerceCategory();             // Initialise new objects eCommerceMotherCategory and eCommerceCategory

                        $dBCategorie = new Categorie($this->db);

                        // Check if the ecommerce category has an ecommerce parent category, if not, that implies it is root
                        $motherExists = $this->eCommerceMotherCategory->fetchByRemoteId($categoryArray['parent_id'], $this->eCommerceSite->id);
                        // Now $this->eCommerceMotherCategory contains the mother category or null

                        // if fetch on eCommerceMotherCategory has failed, it is root
                        if ($motherExists < 1 && ($this->eCommerceMotherCategory->fetchByFKCategory($this->eCommerceSite->fk_cat_product, $this->eCommerceSite->id) < 0))
                        {
                            // get the importRootCategory of Dolibarr set for the eCommerceSite
                            $dBCategorie->fetch($this->eCommerceSite->fk_cat_product);

                            $this->eCommerceMotherCategory->label = $dBCategorie->label;
                            $this->eCommerceMotherCategory->type = $dBCategorie->type;
                            $this->eCommerceMotherCategory->description = $dBCategorie->description;
                            $this->eCommerceMotherCategory->fk_category = $dBCategorie->id;
                            $this->eCommerceMotherCategory->fk_site = $this->eCommerceSite->id;
                            $this->eCommerceMotherCategory->remote_id = $categoryArray['parent_id'];
                            $this->eCommerceMotherCategory->last_update = '';

                            // Create an entry to map importRootCategory in eCommerceCategory
                            $result = $this->eCommerceMotherCategory->create($this->user);
                            if ($result < 0) {
                                $error++;
                                $this->errors[] = $this->langs->trans('ECommerceSyncheCommerceMotherCategoryCreateError', $categoryArray['label'], $categoryArray['category_id'], $this->eCommerceSite->id);
                                $this->errors = array_merge($this->errors, $this->eCommerceMotherCategory->errors);
                                break;
                            }

                            // reset $dBCategorie
                            $dBCategorie = new Categorie($this->db);
                        }

                        // If we did not find mother yet (creation was not done in hierarchy order), we create category in root for magento
                        if (empty($this->eCommerceMotherCategory->fk_category))
                        {
                            dol_syslog("We did not found parent category in dolibarr, for parent remote_id=".$categoryArray['parent_id'].", so we create ".$categoryArray['name']." with remote_id=".$categoryArray['category_id']." on root.");
                            $fk_parent = $this->eCommerceSite->fk_cat_product;
                        }
                        else
                        {
                            dol_syslog("We found parent category dolibarr id=".$this->eCommerceMotherCategory->fk_category);
                            $fk_parent = $this->eCommerceMotherCategory->fk_category;
                        }

                        // Search if category link already exist
                        $synchExists = $this->eCommerceCategory->fetchByRemoteId($categoryArray['category_id'], $this->eCommerceSite->id);
                        if ($synchExists > 0) {
                            $eCommerceCatExists = $dBCategorie->fetch($this->eCommerceCategory->fk_category);
                        } else {
                            $eCommerceCatExists = -1;
                            // Search if already exist
                            $sql = "SELECT c.rowid";
                            $sql.= " FROM ".MAIN_DB_PREFIX."categorie as c ";
                            $sql.= " WHERE c.entity IN (".getEntity('category',1).")";
                            $sql.= " AND c.type = 0";
                            $sql.= " AND c.fk_parent = ".$fk_parent;
                            $sql.= " AND c.label = '".$this->db->escape($categoryArray['name'])."'";

                            $resql = $this->db->query($sql);
                            if ($resql) {
                                if ($obj = $this->db->fetch_object($resql)) {
                                    $eCommerceCatExists = $dBCategorie->fetch($obj->rowid);
                                    // Search if category link exist
                                    $synchExists = $this->eCommerceCategory->fetchByFKCategory($obj->rowid, $this->eCommerceSite->id);
                                }
                            }
                        }

						if ($dBCategorie->id > 0) $dBCategorie->oldcopy = clone $dBCategorie;

                        // Affect attributes of $categoryArray to $dBCategorie
                        $dBCategorie->type = 0; // for product category type
                        $dBCategorie->label = $categoryArray['name'];
                        $dBCategorie->description = $categoryArray['description'];
                        $dBCategorie->fk_parent = ($fk_parent != $dBCategorie->id) ? $fk_parent : 0;
                        $dBCategorie->context['fromsyncofecommerceid'] = $this->eCommerceSite->id;

						if (!($eCommerceCatExists > 0)) $dBCategorie->array_options = $this->getDefaultExtraFields($dBCategorie->table_element, $this->eCommerceSite);

						if ($eCommerceCatExists > 0)
                        {
                            $result = $dBCategorie->update($this->user);
                        }
                        else
                        {
                            $result = $dBCategorie->create($this->user);
                        }

                        // if synchro category ok
                        if ($result >= 0)
                        {
                            $this->eCommerceCategory->fk_site = $this->eCommerceSite->id;
                            $this->eCommerceCategory->type = $dBCategorie->type;
                            $this->eCommerceCategory->fk_category = $dBCategorie->id;
                            $this->eCommerceCategory->label = $dBCategorie->label;
                            $this->eCommerceCategory->description = $dBCategorie->description;
                            $this->eCommerceCategory->remote_id = $categoryArray['category_id'];
                            $this->eCommerceCategory->remote_parent_id = $categoryArray['parent_id'];
                            if (!empty($categoryArray['updated_at'])) $this->eCommerceCategory->last_update = strtotime($categoryArray['updated_at']);

                            if ($synchExists > 0)   // update it remotely
                            {
                                if ($this->eCommerceCategory->update($this->user) < 0)
                                {
                                    $error++;
                                    $this->errors[] = $this->langs->trans('ECommerceSyncheCommerceCategoryUpdateError');
                                    $this->errors = array_merge($this->errors, $this->eCommerceCategory->errors);
                                    break;
                                }
                            }
                            else       // create it remotely
                            {
                                if ($this->eCommerceCategory->create($this->user) < 0)  // insert into table lxx_ecommerce_category
                                {
                                    $error++;
                                    $this->errors[] = $this->langs->trans('ECommerceSyncheCommerceCategoryCreateError') . ' ' . $categoryArray['label'];
                                    $this->errors = array_merge($this->errors, $this->eCommerceCategory->errors);
                                    break;
                                }
                            }
                        }
                        else
                        {
                            $error++;
                            $this->errors[] = $this->langs->trans('ECommerceSynchCategoryError') . ' ' . $dBCategorie->error;
                            break;
                        }

                        //var_dump($nbgoodsunchronize);exit;
                        unset($dBCategorie);

                        if ($error || ! empty($this->errors))
                        {
                            $this->db->rollback();
                            $nbrecorderror++;
                            break;      // We decide to stop on first error
                        }
                        else
                        {
                            $this->db->commit();
                            $nbgoodsunchronize = $nbgoodsunchronize + 1;
                        }
                    }   // end foreach

                    if (empty($this->errors) && ! $error)
                    {
                        $this->success[] = $nbgoodsunchronize . ' ' . $this->langs->trans('ECommerceSynchCategorySuccess');

                        // TODO If we commit even if there was an error (to validate previous record ok), we must also remove 1 second the the higher
                        // date into table of links to be sure we will retry (during next synch) also record with same update_at than the last record ok.

						eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
						return $nbgoodsunchronize;
                    }
                    else
                    {
                        $this->success[] = $nbgoodsunchronize . ' ' . $this->langs->trans('ECommerceSynchCategorySuccess');
						eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
						return -1;
                    }
                }
                else
                {
                    $this->error=$this->langs->trans('ECommerceErrorsynchCategory').' (Code FailToGetDetailsOfRecord)';
                    $this->errors[] = $this->error;
                }
            }
            else
            {
                $this->error = $this->langs->trans('ECommerceSynchCategoryNoImportRoot');
                $this->errors[] = $this->error;
				eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
				return -1;
            }
        } catch (Exception $e) {
            $this->errors[] = $this->langs->trans('ECommerceSynchCategoryConnectError').': '.$e->getMessage();
			eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
			return -1;
        }

		eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
		return -1;
    }


    /**
     * Synchronize societe to update
     *
	 * @param   int     $from_date      Synchronize from date
	 * @param   int     $to_date       	Synchronize to date
	 * @param   array   $remote_ids 	Force to synchronize the specified remote_ids
	 * @param   int     $toNb       	Max nb to synch
	 * @return  int                 	>0 if OK, <0 if KO
     */
    public function synchSociete($remote_ids = array(), $toNb = 0)
	{
		dol_syslog(__METHOD__ . ' remote_ids=' . json_encode($remote_ids) . ', toNb=' . $toNb, LOG_DEBUG);

		$this->error = '';
		$this->errors = array();

		$stopwatch_id = eCommerceUtils::startAndLogStopwatch(__METHOD__ . " - remote_ids: " . json_encode($remote_ids). ", toNb: {$toNb}");

		try {
			$from_date = isset($this->fromDate) ? $this->fromDate : $this->getSocieteLastUpdateDate();
			$to_date = $this->toDate;
			if (!empty($remote_ids)) $toNb = count($remote_ids);

			$result = $this->synchronizeCustomers($from_date, $to_date, $remote_ids, $toNb);

			eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
			return $result;
		} catch (Exception $e) {
			$this->errors = array_merge(array($this->langs->trans('ECommerceErrorWhenSynchronizeCustomers')), $this->errors);
			$this->errors[] = $e->getMessage();
		}

		eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
		return -1;
	}


    /**
     * Synchronize socpeople to update for a society: Create or update it into dolibarr, then update the ecommerce_socpeople table.
     *
     * @param   array   $socpeopleArray     Array with all params to synchronize
     * @return  int                         Id of socpeople into Dolibarr if OK and false if KO
     */
    public function synchSocpeople($socpeopleArray)
    {
        global $conf;

        $error=0;
        $synchExists = 0;
        $contactExists = 0;

        $dBContact = new Contact($this->db);

        try {
            dol_syslog("***** eCommerceSynchro synchSocPeople remote_id=".$socpeopleArray['remote_id']." site=".$this->eCommerceSite->id);

            if (!isset($this->eCommerceSocpeople))
                $this->initECommerceSocpeople();

            //print "Work on remote_id = " .$socpeopleArray['remote_id']." type = ".$socpeopleArray['type']."\n";

            //check if contact exists in eCommerceSocpeople table
            if (!empty($socpeopleArray['remote_id'])) {
                // $socpeopleArray['type'] = 1 = Contact de tiers
                // $socpeopleArray['type'] = 2 = Contact de commande
                // $socpeopleArray['type'] = 3 = Contact de facture
                // $socpeopleArray['type'] = 4 = Contact de livraison
                $synchExists = $this->eCommerceSocpeople->fetchByRemoteId($socpeopleArray['remote_id'], $socpeopleArray['type'], $this->eCommerceSite->id);

                if ($synchExists > 0) {
                    $contactExists = $dBContact->fetch($this->eCommerceSocpeople->fk_socpeople);
                }
            }

			if ($contactExists > 0) $dBContact->oldcopy = clone $dBContact;

            //set data into contact
            $dBContact->socid = $socpeopleArray['fk_soc'];
            $dBContact->fk_soc = $socpeopleArray['fk_soc'];
            $dBContact->firstname = $socpeopleArray['firstname'];
            $dBContact->lastname = $socpeopleArray['lastname'];
            $dBContact->address = $socpeopleArray['address'];
            $dBContact->cp = $socpeopleArray['zip'];
            if ((float) DOL_VERSION >= 6.0)
            {
                $dBContact->zip = dol_trunc($socpeopleArray['zip'], 25, 'right', 'UTF-8', 1);
            }
            else
            {
                $dBContact->zip = dol_trunc($socpeopleArray['zip'], 10, 'right', 'UTF-8', 1);
            }
            $dBContact->town = dol_trunc($socpeopleArray['town'], 30, 'right', 'UTF-8', 1);
            $dBContact->ville = $dBContact->town;
            $dBContact->country_id = $socpeopleArray['country_id'];
            $dBContact->email = $socpeopleArray['email'];
            $dBContact->phone_pro = dol_trunc($socpeopleArray['phone'], 30, 'right', 'UTF-8', 1);
            $dBContact->fax = dol_trunc($socpeopleArray['fax'], 30, 'right', 'UTF-8', 1);
            $dBContact->context['fromsyncofecommerceid'] = $this->eCommerceSite->id;

            if (!$contactExists) {
                $contactExists = $this->getContactIdFromInfos($dBContact);
                if ($contactExists > 0) {
                    $contactExists = $dBContact->fetch($contactExists);
					$dBContact->oldcopy = clone $dBContact;
                    if (isset($socpeopleArray['country_id'])) $dBContact->country_id = $socpeopleArray['country_id'];
                    if (isset($socpeopleArray['email'])) $dBContact->email = $socpeopleArray['email'];
                    if (isset($socpeopleArray['phone'])) $dBContact->phone_pro = dol_trunc($socpeopleArray['phone'], 30, 'right', 'UTF-8', 1);
                    if (isset($socpeopleArray['fax'])) $dBContact->fax = dol_trunc($socpeopleArray['fax'], 30, 'right', 'UTF-8', 1);
                    $dBContact->context['fromsyncofecommerceid'] = $this->eCommerceSite->id;
                    $synchExists = $this->eCommerceSocpeople->fetchByFkSocpeople($dBContact->id, $this->eCommerceSite->id);
                }
            }

			if ($contactExists == 0) $dBContact->array_options = $this->getDefaultExtraFields($dBContact->table_element, $this->eCommerceSite);

            if ($contactExists > 0) {
                $result = $dBContact->update($dBContact->id, $this->user);
                if ($result < 0) {
                    $error++;
                    $this->error = $this->langs->trans('ECommerceSynchContactUpdateError') . ' ' . $dBContact->error;
                    $this->errors[] = $this->error;
                }
            } else if ($contactExists == 0) {
                $result = $dBContact->create($this->user);
                if ($result < 0) {
                    $error++;
                    $this->error = $this->langs->trans('ECommerceSynchContactCreateError') . ' ' . $dBContact->error;
                    $this->errors[] = $this->error;
                }
            } else if ($synchExists > 0 && $contactExists < 0) {
                $error++;
                $this->errors[] = $this->langs->trans('ECommerceSynchSocieteErrorBetweenECommerceSocpeopleAndContact');
            } else if ($contactExists < 0) {
                $error++;
                $this->errors[] = $this->langs->trans('ECommerceSynchContactFetchError') . ' ' . $dBContact->error;
            }

            //if create/update of contact table is ok
            if (! $error && $result >= 0)
            {
                $this->eCommerceSocpeople->last_update = $socpeopleArray['last_update'];
                $this->eCommerceSocpeople->fk_socpeople = $dBContact->id;
                //if a previous synchro exists
                if ($synchExists > 0)
                {
                    //eCommerce update
                    if ($this->eCommerceSocpeople->update($this->user) < 0)
                    {
                        $this->errors[] = $this->langs->trans('ECommerceSyncheCommerceSocpeopleUpdateError');
                        $this->errors = array_merge($this->errors, $this->eCommerceSocpeople->errors);
                        return false;
                    }
                }
                //if not previous synchro exists
                else
                {
                    //eCommerce create
                    $this->eCommerceSocpeople->fk_site = $this->eCommerceSite->id;
                    $this->eCommerceSocpeople->remote_id = !empty($socpeopleArray['remote_id']) ? $socpeopleArray['remote_id'] : 'none-'.$dBContact->id;
                    $this->eCommerceSocpeople->type = $socpeopleArray['type'];
                    if ($this->eCommerceSocpeople->create($this->user) < 0)
                    {
                        $this->errors[] = $this->langs->trans('ECommerceSynchECommerceSocpeopleCreateError', $socpeopleArray['fk_soc'], $socpeopleArray['firstname'], $socpeopleArray['lastname']) . ' : ' . $this->eCommerceSocpeople->error;
                        $this->errors = array_merge($this->errors, $this->eCommerceSocpeople->errors);
                        return false;
                    }
                }
                return $dBContact->id;
            }
            else
            {
                $this->errors[] = $this->langs->trans('ECommerceSynchSocpeopleErrorCreateUpdateSocpeople');
                return false;
            }
        } catch (Exception $e) {
            $this->errors[] = $this->langs->trans('ECommerceErrorsynchSocpeople').': '.$e->getMessage();
        }
        return false;
    }


    /**
     * Synchronize product to update
     *
     * @param   array   $remote_ids 	Force to synchronize the specified remote_ids
	 * @param   int     $toNb       	Max nb to synch
     * @return  int                 	>0 if OK, <0 if KO
     */
    public function synchProduct($remote_ids=array(), $toNb=0)
    {
		dol_syslog(__METHOD__ . ' remote_ids=' . json_encode($remote_ids) . ', toNb=' . $toNb, LOG_DEBUG);

		$this->error = '';
		$this->errors = array();

		$stopwatch_id = eCommerceUtils::startAndLogStopwatch(__METHOD__ . " - remote_ids: " . json_encode($remote_ids). ", toNb: {$toNb}");
		try {
			$from_date = isset($this->fromDate) ? $this->fromDate : $this->getProductLastUpdateDate();
			$to_date = $this->toDate;
			if (!empty($remote_ids)) $toNb = count($remote_ids);

			$result = $this->synchronizeProducts($from_date, $to_date, $remote_ids, $toNb);

			eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
			return $result;
		} catch (Exception $e) {
			$this->errors = array_merge(array($this->langs->trans('ECommerceErrorWhenSynchronizeProducts')), $this->errors);
			$this->errors[] = $e->getMessage();
		}

		eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
		return -1;
	}


    /**
     * Synchronize commande to update
     * Inclut synchProduct et synchSociete
     *
	 * @param   array   $remote_ids 					Force to synchronize the specified remote_ids
     * @param   int     $toNb      						Max nb to synch
	 * @param	bool	$dont_synchronize_products		Bypass the synchronization of the product of the order
	 * @return  int                 					>0 if OK, <0 if KO
     */
    public function synchCommande($remote_ids=array(), $toNb=0, $dont_synchronize_products = false)
    {
		dol_syslog(__METHOD__ . ' remote_ids=' . json_encode($remote_ids) . ', toNb=' . $toNb, LOG_DEBUG);

		$this->error = '';
		$this->errors = array();

		$stopwatch_id = eCommerceUtils::startAndLogStopwatch(__METHOD__ . " - remote_ids: " . json_encode($remote_ids). ", toNb: {$toNb}");
		try {
			$from_date = isset($this->fromDate) ? $this->fromDate : $this->getCommandeLastUpdateDate();
			$to_date = $this->toDate;
			if (!empty($remote_ids)) $toNb = count($remote_ids);

			$result = $this->synchronizeOrders($from_date, $to_date, $remote_ids, $toNb, true, $dont_synchronize_products);

			eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
			return $result;
		} catch (Exception $e) {
			$this->errors = array_merge(array($this->langs->trans('ECommerceErrorWhenSynchronizeOrders')), $this->errors);
			$this->errors[] = $e->getMessage();
		}

		eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
		return -1;
	}

    /**
     * Synchronize facture to update
     *
     * @param   int     $toNb       Max nb to synch
     * @return  int                 Id of product synchronized if OK, -1 if KO
     */
    public function synchFacture($toNb=0)
    {
        global $conf, $user;

        $error = 0;

        try {
            $factures = array();

            dol_syslog("***** eCommerceSynchro synchFacture");

            $resulttoupdate=$this->getFactureToUpdate();
            if (is_array($resulttoupdate))
            {
                if (count($resulttoupdate) > 0) $factures = $this->eCommerceRemoteAccess->convertRemoteObjectIntoDolibarrFacture($resulttoupdate, $toNb);
            }
            else
            {
                $error++;
            }

            // Check return of remote...
            if (is_array($resulttoupdate) && count($resulttoupdate) > 0 && (! is_array($factures) || count($factures) == 0))    // return of remote is bad or empty when input was not empty
            {
                $error++;
            }

            if (! $error && is_array($factures))
            {
                // Local filter to exclude bundles and other complex types
//                $productsTypesOk = array('simple', 'virtual', 'downloadable');

                $counter=0;
                foreach ($factures as $factureArray)
                {
                    dol_syslog("- Process synch of invoice with remote_order_id=".$factureArray['remote_order_id']);

                    $counter++;
                    if ($toNb > 0 && $counter > $toNb) break;

                    $this->db->begin();

                    $this->initECommerceCommande();
                    $this->initECommerceFacture();
                    $this->initECommerceSociete();

                    $dBFacture = new Facture($this->db);
                    $dBCommande = new Commande($this->db);
                    $dBExpedition = new Expedition($this->db);

                    //check if commande exists in eCommerceCommande (with remote_order_id)
                    $synchCommandeExists = $this->eCommerceCommande->fetchByRemoteId($factureArray['remote_order_id'], $this->eCommerceSite->id);

                    //check if ref exists in commande
                    $refCommandeExists = $dBCommande->fetch($this->eCommerceCommande->fk_commande);

                    //check if societe exists in eCommerceSociete (with remote id). This init ->fk_societe. This is a sql request.
                    //$societeExists will be 1 (found) or -1 (not found)
                    if (! empty($factureArray['remote_id_societe']))    // May be empty if customer is a non logged user or was deleted on magento side.
                    {
                        $societeExists = $this->eCommerceSociete->fetchByRemoteId($factureArray['remote_id_societe'], $this->eCommerceSite->id);
                    }
                    else
                    {
                        // This is an unknown customer. May be a non logged customer.
                        if ($this->eCommerceSite->fk_anonymous_thirdparty > 0)
                        {
                            $societeExists = 1;
                            $this->eCommerceSociete->fk_societe = $this->eCommerceSite->fk_anonymous_thirdparty;
                        }
                        else
                        {
                            $societeExists = 0;
                        }
                    }

                    //if societe and commande exists start
                    if ($societeExists > 0 && $synchCommandeExists > 0)
                    {
                        //check if facture exists in eCommerceFacture (with remote id)
                        $synchFactureExists = $this->eCommerceFacture->fetchByRemoteId($factureArray['remote_id'], $this->eCommerceSite->id);
                        if ($synchFactureExists > 0)
                        {
                            //check if facture exists in facture
                            $refFactureExists = $dBFacture->fetch($this->eCommerceFacture->fk_facture);
                            if ($refFactureExists > 0)
                            {
                                //update
                                if ($dBFacture->statut != $factureArray['status'])
                                {
                                    dol_syslog("Status of invoice has changed, we update invoice from status ".$dBFacture->statut." to status ".$factureArray['status']);

                                    // Draft or not draft
                                    if ($factureArray['status'] == Facture::STATUS_DRAFT)   // status draft. Should not happen with magento
                                    {
                                        // Target status is status draft. Should not happen with magento.
                                        // Nothing to do
                                    }
                                    else
                                    {
                                        // Target status is not draft. We validate if current status is still draft to get correct ref.
                                        if ($dBFacture->statut == Facture::STATUS_DRAFT)
                                        {
                                            $idWareHouse = 0;
                                            // We don't change stock here, even if dolibarr option is on because, this should be already done by product sync
                                            //if ($this->eCommerceSite->stock_sync_direction == 'ecommerce2dolibarr') $idWareHouse=$this->eCommerceSite->fk_warehouse;
                                            $dBFacture->validate($this->user, '', $idWareHouse);
                                        }
                                    }

                                    // Which target status ?
                                    if ($factureArray['status'] == Facture::STATUS_VALIDATED)
                                    {
                                        if ($dBFacture->statut != Facture::STATUS_VALIDATED)
                                        {
                                            $dBFacture->setStatut(Facture::STATUS_VALIDATED, $dBFacture->id, $dBFacture->table_element);
                                        }
                                    }
                                    if ($factureArray['status'] == Facture::STATUS_ABANDONED)
                                    {
                                        if ($dBFacture->statut != Facture::STATUS_ABANDONED)
                                        {
                                            $dBFacture->set_canceled($this->user, $factureArray['close_code'], $factureArray['close_note']);
                                        }
                                    }
                                    if ($factureArray['status'] == Facture::STATUS_CLOSED)
                                    {
                                        if ($dBFacture->statut != Facture::STATUS_CLOSED)
                                        {
                                            // Enter payments
                                            //$dBFacture->cloture($this->user);
                                            $payment = new Paiement($this->db);
                                            /*
                                             $payment->datepaye = 'ee';
                                             $payment->paiementid = 0;
                                             $payment->num_paiement = 0;
                                             $payment->amounts=array();
                                             $resultpayment = $payment->create($user);
                                             if ($resultpayment < 0)
                                             {
                                             $error++;
                                             $this->errors[] = "Failed to create payment";
                                             }
                                             */

                                            $dBFacture->set_paid($this->user, '', '');
                                        }
                                    }

                                }

                            }
                            else
                            {
                                $error++;
                                $this->errors[] = $this->langs->trans('ECommerceSynchFactureErrorFactureSynchExistsButNotFacture');
                                break;
                            }
                        }
                        else
                        {
                            //create invoice

                            // If we create invoice, we can force status of order in some cases
                            if ($refCommandeExists > 0 && $dBCommande->statut == Commande::STATUS_DRAFT)
                            {
                                $idWareHouse = 0;
                                // We don't change stock here, even if dolibarr option is on because, this should be already done by product sync
                                //if ($this->eCommerceSite->stock_sync_direction == 'ecommerce2dolibarr') $idWareHouse=$this->eCommerceSite->fk_warehouse;
                                $dBCommande->valid($this->user, $idWareHouse);
                            }
                            if ($refCommandeExists > 0 && $dBCommande->statut == Commande::STATUS_VALIDATED)
                            {
                                $dBCommande->cloture($this->user);
                            }
                            //var_dump($factureArray);exit;


                            $settlementTermsId = $this->getSettlementTermsId($factureArray['code_cond_reglement']);

                            // First, we check object does not alreay exists. If not, we create it, if it exists, do nothing.
                            $result = $dBFacture->fetch(0, '', $this->eCommerceSite->name.'-'.$factureArray['remote_id']);
                            if ($result == 0)
                            {
                                $origin = 'commande';
                                $originid = $dBCommande->id;

                                $dBFacture->ref_client = $factureArray['ref_client'];
                                $dBFacture->ref_ext = $this->eCommerceSite->name.'-'.$factureArray['remote_id'];
                                $dBFacture->date = strtotime($factureArray['date']);
                                $dBFacture->socid = $this->eCommerceSociete->fk_societe;
                                $dBFacture->cond_reglement_id = $settlementTermsId;
                                $dBFacture->context['fromsyncofecommerceid'] = $this->eCommerceSite->id;
                                $dBFacture->note_private="";
                                if (! empty($conf->global->ECOMMERCENG_ENABLE_LOG_IN_NOTE))
                                {
                                    $dBFacture->note_private .= "Last eCommerce invoice received:\n".dol_trunc(serialize(var_export($factureArray['remote_invoice'], true)), 65000);
                                    $dBFacture->note_private .= "\n\n";
                                    $dBFacture->note_private .= "Last eCommerce order received:\n".dol_trunc(serialize(var_export($factureArray['remote_order'], true)), 65000);
                                }

                                // Add link to order (cut takenf from facture card page)
                                $dBFacture->origin = $origin;
                                $dBFacture->origin_id = $originid;
                                $dBFacture->linked_objects[$dBFacture->origin] = $dBFacture->origin_id;
								$dBFacture->array_options = $this->getDefaultExtraFields($dBFacture->table_element, $this->eCommerceSite);

                                // Now we create invoice
                                $result = $dBFacture->create($this->user);

                                //add or update contacts of invoice
                                $factureArray['socpeopleLivraison']['fk_soc'] = $this->eCommerceSociete->fk_societe;
                                $factureArray['socpeopleFacture']['fk_soc'] = $this->eCommerceSociete->fk_societe;

                                $socpeopleLivraisonId = $this->synchSocpeople($factureArray['socpeopleLivraison']);
                                $socpeopleFactureId = $this->synchSocpeople($factureArray['socpeopleFacture']);

                                if ($socpeopleLivraisonId > 0)
                                    $dBFacture->add_contact($socpeopleLivraisonId, 'SHIPPING');
                                if ($socpeopleFactureId > 0)
                                    $dBFacture->add_contact($socpeopleFactureId, 'BILLING');

                                //add items
                                if (count($factureArray['items']))
                                    foreach ($factureArray['items'] as $item)
                                    {
                                        $this->initECommerceProduct();
                                        $this->eCommerceProduct->fetchByRemoteId($item['id_remote_product'], $this->eCommerceSite->id);

                                        // Define the buy price for margin calculation
                                        $buyprice=0;
                                        $fk_product = $this->eCommerceProduct->fk_product;
                                        if (($result = $dBFacture->defineBuyPrice($item['price'], 0, $fk_product)) < 0)
                                        {
                                            $error++;
                                            $this->errors[] = $this->langs->trans('ECommerceSyncheCommerceFactureUpdateError').' '.$dbFacture->error;
                                            $this->errors = array_merge($this->errors, $dbFacture->errors);
                                            break;	// break items
                                        }
                                        else
                                        {
                                            $buyprice = $result;
                                        }
                                        $dBFacture->addline(
                                            $item['description'],
                                            $item['price'],
                                            $item['qty'],
                                            $item['tva_tx'],
                                            0,
                                            0,
                                            $this->eCommerceProduct->fk_product,
                                            0,
                                            '', //date_start
                                            '', //date_end
                                            0, //ventil
                                            0, //info_bits
                                            0, //fk_remise_except
                                            'HT',
                                            0, //pu_ttc
                                            0, // FIXME Use type of article   0:product 1:service
                                            0, //rang
                                            0, //special code
                                            '', // This field seems not used
                                            0, // This field seems not used
                                            0, //fk_parent_line
                                            0, //fk_fourn_price
                                            $buyprice
                                            );
                                        unset($this->eCommerceProduct);
                                    }

                                //add delivery
                                if (! $error && $factureArray['delivery']['qty'] > 0)
                                {
                                    $delivery = $factureArray['delivery'];

                                    // TODO Get buy price depending on margin option. No margin on delivery line ?
                                    $buyprice=0;

                                    $dBFacture->addline($delivery['description'], $delivery['price'], $delivery['qty'], $delivery['tva_tx'], 0, 0, 0, //fk_product
                                            0, //remise_percent
                                            '', //date_start
                                            '', //date_end
                                            0, //ventil
                                            0, //info_bits
                                            0, //fk_remise_except
                                            'HT', //price_base_type
                                            0, //pu_ttc
                                            1, //type 0:product 1:service
                                            0, //rang
                                            0, //special code
                                            '', // origin
                                            0, // origin_id
                                            0, //fk_parent_line
                                            0, //fk_fourn_price
                                            $buyprice
                                    );
                                }
                            }

                            // Now update status
                            if (! $error)
                            {
                                //if ($dBFacture->statut != $factureArray['status'])      // Always when creating
                                //{
                                dol_syslog("synchFacture Status of invoice must be now set: we update invoice id=".$dBFacture->id." ref_client=".$dBFacture->ref_client." from status ".$dBFacture->statut." to status ".$factureArray['status']);

                                // Draft or not draft
                                if ($factureArray['status'] == Facture::STATUS_DRAFT)   // status draft. Should not happen with magento
                                {
                                        // Target status is status draft. Should not happen with magento.
                                        // Nothing to do
                                }
                                else
                                {
                                    // Target status is not draft. We validate if current status is still draft to get correct ref.
                                    if ($dBFacture->statut == Facture::STATUS_DRAFT)
                                    {
                                        $idWareHouse = 0;
                                        // We don't change stock here, even if dolibarr option is on because, this should be already done by product sync
                                        //if ($this->eCommerceSite->stock_sync_direction == 'ecommerce2dolibarr') $idWareHouse=$this->eCommerceSite->fk_warehouse;
                                        $dBFacture->validate($this->user, '', $idWareHouse);
                                    }
                                }

                                // Which target status ?
                                if ($factureArray['status'] == Facture::STATUS_VALIDATED)
                                {
                                    if ($dBFacture->statut != Facture::STATUS_VALIDATED)
                                    {
                                        $dBFacture->setStatut(Facture::STATUS_VALIDATED, $dBFacture->id, $dBFacture->table_element);
                                    }
                                }
                                if ($factureArray['status'] == Facture::STATUS_ABANDONED)
                                {
                                    if ($dBFacture->statut != Facture::STATUS_ABANDONED)
                                    {
                                        $dBFacture->set_canceled($this->user, $factureArray['close_code'], $factureArray['close_note']);
                                    }
                                }
                                if ($factureArray['status'] == Facture::STATUS_CLOSED)
                                {
                                    if ($dBFacture->statut != Facture::STATUS_CLOSED)
                                    {
                                        // Enter payment
                                        // Magento seems to do one payment for one invoice

                                        $payment = new Paiement($this->db);
                                        /*
                                        $payment->datepaye = 'ee';
                                        $payment->paiementid = 0;
                                        $payment->num_paiement = 0;
                                        $payment->amounts=array();
                                        $resultpayment = $payment->create($user);
                                        if ($resultpayment < 0)
                                        {
                                            $error++;
                                            $this->errors[] = "Failed to create payment";
                                            $this->errors = array_merge($this->errors, $payment->errors);
                                        }
                                        */

                                        //$factureArray['remote_order']["payment"] is one record with summ of different payments/invoices.

                                        //exit;

                                        $dBFacture->set_paid($this->user, '', '');
                                    }
                                }

                            }

                        }

                        /* **************************************************************
                         *
                         * register into eCommerceFacture
                         *
                         * ************************************************************** */
                        //if synchro invoice ok
                        if (! $error)
                        {
                            $this->eCommerceFacture->last_update = $factureArray['last_update'];
                            $this->eCommerceFacture->fk_facture = $dBFacture->id;
                            //if a previous synchro exists
                            if ($synchFactureExists > 0)
                            {
                                //eCommerce update
                                if ($this->eCommerceFacture->update($this->user) < 0)
                                {
                                    $error++;
                                    $this->errors[] = $this->langs->trans('ECommerceSyncheCommerceFactureUpdateError').' '.$this->eCommerceFacture->error;
                                    $this->errors = array_merge($this->errors, $this->eCommerceFacture->errors);
                                }
                            }
                            //if not previous synchro exists
                            else
                            {
                                // May be an old record with an old product removed on eCommerce still exists, we delete it before insert.
                                $sql = "DELETE FROM ".MAIN_DB_PREFIX."ecommerce_facture WHERE fk_facture=".$this->eCommerceFacture->fk_facture;
                                $resql = $this->db->query($sql);

                                //eCommerce create
                                $this->eCommerceFacture->fk_site = $this->eCommerceSite->id;
                                $this->eCommerceFacture->remote_id = $factureArray['remote_id'];
                                if ($this->eCommerceFacture->create($this->user) < 0)
                                {
                                    $error++;
                                    $this->errors[] = $this->langs->trans('ECommerceSyncheCommerceFactureCreateError').' '.$dBFacture->id.', '.$this->eCommerceFacture->error;
                                    $this->errors = array_merge($this->errors, $this->eCommerceFacture->errors);
                                    dol_syslog($this->langs->trans('ECommerceSyncheCommerceFactureCreateError') . ' ' . $dBFacture->id.', '.$this->eCommerceFacture->error, LOG_WARNING);
                                }
                            }
                        }
                        else
                        {
                            $error++;
                            $this->errors[] = $this->langs->trans('ECommerceSynchCommandeError');
                        }
                    }
                    else
                    {
                        $error++;
                        if ($societeExists <= 0)
                        {
                            $this->errors[] = $this->langs->trans('ECommerceSynchFactureErrorSocieteNotExists', $factureArray['remote_id_societe']);
                        }
                        if ($synchCommandeExists <= 0)
                        {
                            $this->errors[] = $this->langs->trans('ECommerceSynchFactureErrorCommandeNotExists', $factureArray['remote_order_id']);
                        }
                    }

                    unset($dBFacture);
                    unset($dBCommande);
                    unset($dBExpedition);
                    unset($this->eCommerceSociete);
                    unset($this->eCommerceFacture);
                    unset($this->eCommerceCommande);

                    if ($error || ! empty($this->errors))
                    {
                        $this->db->rollback();
                        $nbrecorderror++;
                        break;      // We decide to stop on first error
                    }
                    else
                    {
                        $this->db->commit();
                        $nbgoodsunchronize = $nbgoodsunchronize + 1;
                    }
                }

                if (! $error)
                {
                    $this->success[] = $nbgoodsunchronize . ' ' . $this->langs->trans('ECommerceSynchFactureSuccess');

                    // TODO If we commit even if there was an error (to validate previous record ok), we must also remove 1 second the the higher
                    // date into table of links to be sure we will retry also record with same update_at than the last record ok

                    return $nbgoodsunchronize;
                }
                else
                {
                    $this->success[] = $nbgoodsunchronize . ' ' . $this->langs->trans('ECommerceSynchFactureSuccess');

                    return -1;
                }
            }
            else
            {
                $this->error=$this->langs->trans('ECommerceErrorsynchFacture').' (Code FailToGetDetailsOfRecord)';
                $this->errors[] = $this->error;
            }
        } catch (Exception $e) {
            $this->errors[] = $this->langs->trans('ECommerceErrorsynchFacture').': '.$e->getMessage();
        }

        return -1;
    }

    /**
     * Synchronize shipment
     *
     * @param   Shipment    $livraison          Shipment object
     * @param   int         $remote_order_id    Remote id of order
     * @return  bool                            true or false
     */
    public function synchLivraison($livraison, $remote_order_id)
    {
        $error = 0;

        try {
            dol_syslog("***** eCommerceSynchro syncLivraison");

            return $this->eCommerceRemoteAccess->createRemoteLivraison($livraison, $remote_order_id);
        } catch (Exception $e) {
            $this->errors[] = $this->langs->trans('ECommerceErrorrCeateRemoteLivraison').': '.$e->getMessage();
        }
        return false;
    }


    /**
     * 	Sync categories from Dolibarr to ECommerce
     *
     * @param  int     $toNb       Max nb to synch
     * @return int                 <0 if KO, >= 0 if ok
     */
    public function synchDtoECategory($toNb=0)
    {
        global $user;

        $error = 0;
        $nbgoodsunchronize = 0;

        dol_syslog("***** eCommerceSynchro synchDtoECategory");
		$stopwatch_id = eCommerceUtils::startAndLogStopwatch(__METHOD__ . " - toNb: {$toNb}");

        $this->initECommerceCategory(); // Initialise 2 properties eCommerceCategory and eCommerceMotherCategory

        $this->fetch_categories('product', $this->eCommerceSite->fk_cat_product);
        $categories = $this->cache_categories['product'];

        $already_synch = array();
        $sql="SELECT fk_category FROM ".MAIN_DB_PREFIX."ecommerce_category WHERE type = 0 AND fk_site=".$this->eCommerceSite->id;
        $resql=$this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $already_synch[] = $obj->fk_category;
            }
        } else {
            $error_msg = $this->langs->trans('ECommerceErrorGetCategoryIdsAlreadyLinked', $this->eCommerceSite->name, $this->db->lasterror());
            $this->errors[] = $error_msg;
            dol_syslog(__METHOD__ . ': Error:' . $error_msg, LOG_WARNING);
			eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
			return -1;
        }

        $index = 0;
        $group = array();
        foreach ($categories as $cat_id => $category) {
            if (in_array($cat_id, $already_synch)) continue;

            $index++;
            $group[$cat_id] = $category;
            if ($index == $toNb) {
                break;
            }
        }

        $cats_id_remote_id = $this->eCommerceRemoteAccess->createRemoteCategories($group);
        $now = dol_now();
        if ($cats_id_remote_id === false || !empty($this->errors)) {
            $error++;
        }

        if (is_array($cats_id_remote_id) && count($cats_id_remote_id)) {
            foreach ($cats_id_remote_id as $cat_id => $remote_ids) {
                if ($this->eCommerceCategory->fetchByFKCategory($cat_id, $this->eCommerceSite->id) > 0) {
                    $this->eCommerceCategory->delete($user);
                }

                // Create remote link
                $category = $categories[$cat_id];
                $this->eCommerceCategory->label = $category['label'];
                $this->eCommerceCategory->type = 0; // product
                $this->eCommerceCategory->description = $category['description'];
                $this->eCommerceCategory->fk_category = $cat_id;
                $this->eCommerceCategory->fk_site = $this->eCommerceSite->id;
                $this->eCommerceCategory->remote_id = $remote_ids['remote_id'];
                $this->eCommerceCategory->remote_parent_id = $remote_ids['remote_parent_id'];
                $this->eCommerceCategory->last_update = dol_print_date($now, '%Y-%m-%d %H:%M:%S');
                $res = $this->eCommerceCategory->create($user);
                if ($res < 0) {
                    $error++;
                    $error_msg = $this->langs->trans('ECommerceCreateRemoteCategoryLink', $cat_id, $this->eCommerceSite->name, $this->eCommerceCategory->error);
                    $this->errors[] = $error_msg;
                    dol_syslog(__METHOD__ . ': Error:' . $error_msg, LOG_WARNING);
                } else {
                    $nbgoodsunchronize++;
                }
            }
        }

        if ($nbgoodsunchronize > 0) {
            $this->success[] = $nbgoodsunchronize . ' ' . $this->langs->trans('ECommerceSynchCategorySuccess');
        }

		eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
		if (!$error) {
            return $nbgoodsunchronize;
        } else {
            return -1;
        }
    }

    /**
     *  Synchronize product from Dolibarr to ECommerce
     *
     * @param   int     $toNb       Max nb to synch
     * @return int                  <0 if KO, >= 0 if ok
     */
    public function synchDtoEProduct($toNb=0)
    {
        global $conf, $langs, $user;

		$langs->load('ecommerceng@ecommerceng');
        $error = 0;
        $nbgoodsunchronize = 0;

        dol_syslog("***** eCommerceSynchro synchDtoEProduct");
		$stopwatch_id = eCommerceUtils::startAndLogStopwatch(__METHOD__ . " - toNb: {$toNb}");

        $this->fetch_categories('product', $this->eCommerceSite->fk_cat_product);
        $cats_id = array_keys($this->cache_categories['product']);

        $sql = "SELECT DISTINCT p.rowid, IFNULL(ep.remote_id, '') as remote_id FROM " . MAIN_DB_PREFIX . "product as p" .
            " INNER JOIN " . MAIN_DB_PREFIX . "categorie_product as cp ON p.rowid = cp.fk_product AND cp.fk_categorie IN (" . implode(',', $cats_id) . ")" .
            " LEFT JOIN " . MAIN_DB_PREFIX . "ecommerce_product as ep ON p.rowid = ep.fk_product AND ep.fk_site=" . $this->eCommerceSite->id .
			" LEFT JOIN ".MAIN_DB_PREFIX."product_extrafields as pf ON pf.fk_object = p.rowid" .
			" WHERE (ep.rowid IS NULL OR ep.last_update < p.tms OR (pf.tms IS NOT NULL AND ep.last_update < pf.tms))" .
			" AND p.entity IN (" . getEntity('product') . ")";
		$resql = $this->db->query($sql);
        if ($resql) {
			require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
			$product_static = new Product($this->db);

			$ec_price_entities = explode(',', getEntity('productprice'));

			$idx = 0;
			while ($obj = $this->db->fetch_object($resql)) {
				$idx++;

				if ($product_static->fetch($obj->rowid) > 0) {
					$this->initECommerceProduct();

					$product_static->context['ec_price_entities'] = $ec_price_entities;
					if (empty($obj->remote_id)) {
						$result = $this->eCommerceRemoteAccess->createRemoteProduct($product_static);
						$now = dol_now();
						if (!$result) {
							$error++;
							$this->error = $this->eCommerceRemoteAccess->error;
							$this->errors = $this->eCommerceRemoteAccess->errors;
						} else {
                            $remote_id = $result['remote_id'];
                            $remote_language = $result['language'];

							$product_static->url = $result['remote_url'];
							$product_static->context['fromsyncofecommerceid'] = $this->eCommerceSite->id;
							$result = $product_static->update($obj->rowid, $user);
							if ($result < 0) {
								$error++;
								$error_msg = $langs->trans('ECommerceUpdateProduct');
								$this->errors[] = $error_msg;
								$this->errors = array_merge($this->errors, $product_static->errors);
								dol_syslog(__METHOD__ . ': Error:' . $error_msg, LOG_WARNING);
							}

							// Create remote link
							if (!$error) {
								$this->eCommerceProduct->remote_id = $remote_id;
								$this->eCommerceProduct->fk_site = $this->eCommerceSite->id;
                                $this->eCommerceProduct->fk_product = $product_static->id;
                                $this->eCommerceProduct->lang = $remote_language;
								$this->eCommerceProduct->last_update = dol_print_date($now, '%Y-%m-%d %H:%M:%S');
								if ($this->eCommerceSite->stock_sync_direction == 'dolibarr2ecommerce') $this->eCommerceProduct->last_update_stock = $this->eCommerceProduct->last_update;
								if ($this->eCommerceProduct->create($user) < 0) {
									$error++;
									$error_msg = $langs->trans('ECommerceCreateRemoteProductLink', $product_static->id, $this->eCommerceSite->name, $this->eCommerceProduct->error);
									$this->errors[] = $error_msg;
									$this->errors = array_merge($this->errors, $this->eCommerceProduct->errors);
									dol_syslog(__METHOD__ . ': Error:' . $error_msg, LOG_WARNING);
								}
							}
						}
					} else {
						$result = $this->eCommerceRemoteAccess->updateRemoteProduct($obj->remote_id, $product_static);
						if (!$result) {
							$error++;
							$this->error = $this->eCommerceRemoteAccess->error;
							$this->errors = $this->eCommerceRemoteAccess->errors;
						} else {
							//eCommerce update link
							$now = dol_now();
							$this->eCommerceProduct->fetchByRemoteId($obj->remote_id, $this->eCommerceSite->id);
							$this->eCommerceProduct->last_update = dol_print_date($now, '%Y-%m-%d %H:%M:%S');
							if ($this->eCommerceSite->stock_sync_direction == 'dolibarr2ecommerce') $this->eCommerceProduct->last_update_stock = $this->eCommerceProduct->last_update;
							if ($this->eCommerceProduct->update($user) < 0) {
								$error++;
								$error_msg = $langs->trans('ECommerceUpdateRemoteProductLink', $product_static->id, $this->eCommerceSite->name, $this->eCommerceProduct->error);
								$this->errors[] = $error_msg;
								$this->errors = array_merge($this->errors, $this->eCommerceProduct->errors);
								dol_syslog(__METHOD__ . ': Error:' . $error_msg, LOG_WARNING);
							}
						}
					}

					if (!$error) {
						$nbgoodsunchronize++;
					}
				}

				if ($idx == $toNb) {
					break;
				}
			}

			if ($nbgoodsunchronize > 0) {
                $this->success[] = $nbgoodsunchronize . ' ' . $this->langs->trans('ECommerceSynchProductSuccess');
            }

			eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
            if (!$error) {
                return $nbgoodsunchronize;
            } else {
                return -1;
            }
        } else {
            $this->error = $this->db->lasterror();
			eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
            return -1;
        }
    }


    /**
     * Return dictionnary entry for a code
     *
     * @param   string    $code         Code of payment term
     * @return  mixed                   Record
     */
    public function getSettlementTermsId($code)
    {
        $table = MAIN_DB_PREFIX . "c_payment_term";
        $eCommerceDict = new eCommerceDict($this->db, $table);
        $settlementTerms = $eCommerceDict->fetchByCode($code);
        return $settlementTerms['rowid'];
    }

    /*private function getAnonymousConstValue()
    {
        $table = MAIN_DB_PREFIX . "const";
        $eCommerceDict = new eCommerceDict($this->db, $table);
        return $eCommerceDict->getAnonymousConstValue();
    }*/

    /**
     * Check if constant ECOMMERCE_COMPANY_ANONYMOUS exists with value of the generic thirdparty id.
     *
     * @return	int		    <0 if KO, eCommerceAnonymous->id if OK
     */
    /*public function checkAnonymous()
    {
        $dbAnonymousExists=0;

        //check if dbSociete anonymous exists
        $dBSociete = new Societe($this->db);
        $anonymousId = $this->getAnonymousConstValue();             // Get id into var ECOMMERCE_COMPANY_ANONYMOUS if it exists
        if ($anonymousId > 0)
        {
            $dbAnonymousExists = $dBSociete->fetch($anonymousId);
        }
        if ($dbAnonymousExists > 0)
        {
    		Todo fetchByFkSociete modified to getAllLinksByFkSociete
            $eCommerceSocieteAnonymous = new eCommerceSociete($this->db);
            $eCommerceAnonymousExists = $eCommerceSocieteAnonymous->fetchByFkSociete($anonymousId, $this->eCommerceSite->id);   // search into llx_ecommerce_societe
            if ($eCommerceAnonymousExists < 0)  // If entry not found into llx_ecommerce_site, we create it.
            {
                $eCommerceSocieteAnonymous->fk_societe = $anonymousId;
                $eCommerceSocieteAnonymous->fk_site = $this->eCommerceSite->id;
                $eCommerceSocieteAnonymous->remote_id = 0;

                if ($eCommerceSocieteAnonymous->create($this->user) < 0)
                {
                    $this->errors[] = $this->langs->trans('ECommerceAnonymousCreateFailed') . ' ' . $this->langs->trans('ECommerceReboot');
                    return -1;
                }
            }
            return $eCommerceSocieteAnonymous->id;
        }
        else
        {
            $this->errors[] = $this->langs->trans('ECommerceNoDbAnonymous') . ' ' . $this->langs->trans('ECommerceReboot');
            return -1;
        }
    }*/

    /**
     * Delete any data linked to synchronization, then delete synchro's datas to clean sync
     *
     * @param   int     $deletealsoindolibarr       0=Delete only link table, 1=Delete also record in dolibarr
     * @param   string  $mode                       '' to delete all, 'categories', 'products', 'thirdparties', 'orders', 'invoices'
     * @return  void
     */
    public function dropImportedAndSyncData($deletealsoindolibarr, $mode='')
    {
    	global $conf;
        dol_syslog("***** eCommerceSynchro dropImportedAndSyncData");

		$stopwatch_id = eCommerceUtils::startAndLogStopwatch(__METHOD__ . " - mode: {$mode}");

        // Drop invoices
        if (empty($mode) || preg_match('/^invoices/', $mode))
        {
            $dolObjectsDeleted = 0;
            $synchObjectsDeleted = 0;
            $this->initECommerceFacture();
            $arrayECommerceFactureIds = $this->eCommerceFacture->getAllECommerceFactureIds($this->eCommerceSite->id);

            $this->db->begin();

            foreach ($arrayECommerceFactureIds as $idFacture)
            {
                $this->initECommerceFacture();
                if ($this->eCommerceFacture->fetch($idFacture) > 0)
                {
                    if ($deletealsoindolibarr)
                    {
                        $dbFacture = new Facture($this->db);
                        if ($dbFacture->fetch($this->eCommerceFacture->fk_facture) > 0)
                        {
                            $idWarehouse = 0;
                            // We don't change stock here, it's a clean of database that don't change stock
                            if ((float) DOL_VERSION < 5.0) $resultdelete = $dbFacture->delete($dbFacture->id, 0, $idWarehouse);
                            else $resultdelete = $dbFacture->delete($this->user, 0, $idWarehouse);

                            if ($resultdelete > 0)
                                $dolObjectsDeleted++;
                        }
                    }
                    if ($this->eCommerceFacture->delete($this->user) > 0)
                        $synchObjectsDeleted++;
                }
            }

            if ($deletealsoindolibarr) $this->success[] = $dolObjectsDeleted . ' ' . $this->langs->trans('ECommerceResetDolFactureSuccess');
            $this->success[] = $synchObjectsDeleted . ' ' . $this->langs->trans('ECommerceResetSynchFactureSuccess');
            unset($this->eCommerceFacture);

            $this->db->commit();
        }

        //Drop commands
        if (empty($mode) || preg_match('/^orders/', $mode))
        {
            $dolObjectsDeleted = 0;
            $synchObjectsDeleted = 0;
            $this->initECommerceCommande();
            $arrayECommerceCommandeIds = $this->eCommerceCommande->getAllECommerceCommandeIds($this->eCommerceSite->id);

            $this->db->begin();

            foreach ($arrayECommerceCommandeIds as $idCommande)
            {
                $this->initECommerceCommande();
                if ($this->eCommerceCommande->fetch($idCommande) > 0)
                {
                    if ($deletealsoindolibarr)
                    {
                        $dbCommande = new Commande($this->db);
                        if ($dbCommande->fetch($this->eCommerceCommande->fk_commande) > 0)
                        {
                            $resultdelete = $dbCommande->delete($this->user);
                            if ($resultdelete > 0)
                                $dolObjectsDeleted++;
                        }
                    }
                    if ($this->eCommerceCommande->delete($this->user) > 0)
                        $synchObjectsDeleted++;
                }
            }

            if ($deletealsoindolibarr) $this->success[] = $dolObjectsDeleted . ' ' . $this->langs->trans('ECommerceResetDolCommandeSuccess');
            $this->success[] = $synchObjectsDeleted . ' ' . $this->langs->trans('ECommerceResetSynchCommandeSuccess');
            unset($this->eCommerceCommande);

            $this->db->commit();
        }

        //Drop products
        if (empty($mode) || preg_match('/^products/', $mode))
        {
            $dolObjectsDeleted = 0;
            $synchObjectsDeleted = 0;
            $this->initECommerceProduct();
            $arrayECommerceProductIds = $this->eCommerceProduct->getAllECommerceProductIds($this->eCommerceSite->id);

            $this->db->begin();

            // Remove all categories of the ecommerce on the products
			if (empty($conf->global->ECOMMERCE_DONT_UNSET_CATEGORIE_OF_PRODUCT_WHEN_DELINK)) {
				require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
				$c = new Categorie($this->db);
				$all_cat_full_arbo = $c->get_full_arbo('product');
				$cat_root = $this->eCommerceSite->fk_cat_product;
				$error = 0;
				$synchCategoriesDeleted = 0;
				foreach ($all_cat_full_arbo as $cat_infos) {
					if (preg_match("/^{$cat_root}$/", $cat_infos['fullpath']) || preg_match("/^{$cat_root}_/", $cat_infos['fullpath']) ||
						preg_match("/_{$cat_root}_/", $cat_infos['fullpath']) || preg_match("/_{$cat_root}$/", $cat_infos['fullpath'])) {
						if ($c->fetch($cat_infos['id']) > 0) {
							$prods = $c->getObjectsInCateg("product");
							if (is_array($prods)) {
								foreach ($prods as $prod) {
									if ($c->del_type($prod, 'product') < 0) {
										setEventMessages($c->error, $c->errors, 'errors');
										$error++;
									} else {
										$synchCategoriesDeleted++;
									}
								}
							}
						}
					}
				}
			}

            foreach ($arrayECommerceProductIds as $idProduct)
            {
                $this->initECommerceProduct();
                if ($this->eCommerceProduct->fetch($idProduct) > 0)
                {
                    if ($deletealsoindolibarr)
                    {
                        $dbProduct = new Product($this->db);
                        if ($dbProduct->fetch($this->eCommerceProduct->fk_product) > 0)
                        {
                            if ((float)DOL_VERSION < 6.0) {
                                $resultdelete = $dbProduct->delete();
                            } else {
                                $resultdelete = $dbProduct->delete($this->user);
                            }
                            if ($resultdelete > 0)
                                $dolObjectsDeleted++;
                        }
                    }

                    if ($this->eCommerceProduct->delete($this->user, 0) > 0)
                        $synchObjectsDeleted++;
                }
            }

            if ($deletealsoindolibarr) $this->success[] = $dolObjectsDeleted . ' ' . $this->langs->trans('ECommerceResetDolProductSuccess');
            $this->success[] = $synchObjectsDeleted . ' ' . $this->langs->trans('ECommerceResetSynchProductSuccess');
            if ($synchCategoriesDeleted) $this->success[] = $synchCategoriesDeleted . ' ' . $this->langs->trans('ECommerceResetCategoriesProductSuccess');
            unset($this->eCommerceProduct);

            if ($error) {
                $this->db->rollback();
            } else {
                $this->db->commit();
            }
        }

        //Drop socPeople
        if (empty($mode) || preg_match('/^thirdparties/', $mode))
//        if (empty($mode) || preg_match('/^contacts/', $mode))
        {
            $dolObjectsDeleted = 0;
            $synchObjectsDeleted = 0;
            $this->initECommerceSocpeople();
            $arrayECommerceSocpeopleIds = $this->eCommerceSocpeople->getAllECommerceSocpeopleIds($this->eCommerceSite->id);

            $this->db->begin();

            foreach ($arrayECommerceSocpeopleIds as $idSocpeople)
            {
                $this->initECommerceSocpeople();
                if ($this->eCommerceSocpeople->fetch($idSocpeople) > 0)
                {
                    if ($deletealsoindolibarr)
                    {
                        $dbSocpeople = new Contact($this->db);
                        if ($dbSocpeople->fetch($this->eCommerceSocpeople->fk_socpeople) > 0)
                        {
                            $resultdelete = $dbSocpeople->delete(0);
                            if ($resultdelete > 0)
                                $dolObjectsDeleted++;
                        }
                    }
                    if ($this->eCommerceSocpeople->delete($this->user, 0) > 0)
                        $synchObjectsDeleted++;
                }
            }

            if ($deletealsoindolibarr) $this->success[] = $dolObjectsDeleted . ' ' . $this->langs->trans('ECommerceResetDolSocpeopleSuccess');
            $this->success[] = $synchObjectsDeleted . ' ' . $this->langs->trans('ECommerceResetSynchSocpeopleSuccess');
            unset($this->eCommerceSocpeople);

            $this->db->commit();
        }

        //Drop societes
        if (empty($mode) || preg_match('/^thirdparties/', $mode))
        {
            $dolObjectsDeleted = 0;
            $synchObjectsDeleted = 0;
            $this->initECommerceSociete();
            $arrayECommerceSocieteIds = $this->eCommerceSociete->getAllECommerceSocieteIds($this->eCommerceSite->id);

            $this->db->begin();

            foreach ($arrayECommerceSocieteIds as $idSociete)
            {
                $this->initECommerceSociete();
                if ($this->eCommerceSociete->fetch($idSociete) > 0)
                {
                    // Skip anonymous thirdparty
                    if ($this->eCommerceSociete->remote_id == 0) continue;

                    if ($deletealsoindolibarr)
                    {
                        $dbSociete = new Societe($this->db);
                        if ($dbSociete->fetch($this->eCommerceSociete->fk_societe) > 0)
                        {
                            $resultdelete = $dbSociete->delete($dbSociete->id, $this->user, 1);
                            if ($resultdelete > 0)
                                $dolObjectsDeleted++;
                        }
                    }
                    if ($this->eCommerceSociete->delete($this->user, 0, $this->eCommerceSite->name) > 0)
                        $synchObjectsDeleted++;
                }
            }

            if ($deletealsoindolibarr) $this->success[] = $dolObjectsDeleted . ' ' . $this->langs->trans('ECommerceResetDolSocieteSuccess');
            $this->success[] = $synchObjectsDeleted . ' ' . $this->langs->trans('ECommerceResetSynchSocieteSuccess');
            unset($this->eCommerceSociete);

            $this->db->commit();
        }

        //Drop categories
        if (empty($mode) || preg_match('/^categories/', $mode))
        {
            $dolObjectsDeleted = 0;
            $dolObjectsNotDeleted = 0;
            $synchObjectsDeleted = 0;
            $this->initECommerceCategory();
            $arrayECommerceCategoryIds = $this->eCommerceCategory->getAllECommerceCategoryIds($this->eCommerceSite);

            $this->db->begin();

            foreach ($arrayECommerceCategoryIds as $idCategory)
            {
                $this->initECommerceCategory();
                if ($this->eCommerceCategory->fetch($idCategory) > 0)
                {
                    if ($deletealsoindolibarr)
                    {
                        $dbCategory = new Categorie($this->db);
                        if ($dbCategory->fetch($this->eCommerceCategory->fk_category) > 0)
                        {
                            $resultdelete = $dbCategory->delete($this->user);
                            if ($resultdelete > 0)
                                $dolObjectsDeleted++;
                            else
                                $dolObjectsNotDeleted++;
                        }
                    }
                    if ($this->eCommerceCategory->delete($this->user, 0) > 0)
                        $synchObjectsDeleted++;
                }
            }

            if ($deletealsoindolibarr) $this->success[] = $dolObjectsDeleted . ' ' . $this->langs->trans('ECommerceResetDolCategorySuccess').($dolObjectsNotDeleted?' ('.$dolObjectsNotDeleted.' ko)':'');
            $this->success[] = $synchObjectsDeleted . ' ' . $this->langs->trans('ECommerceResetSynchCategorySuccess');
            unset($this->eCommerceCategory);

            $this->db->commit();
        }

		eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
    }


    public function __destruct()
    {
        unset($this->eCommerceRemoteAccess);
    }


    /**
	 * Function to check if a contact informations passed by params exists in DB.
	 *
	 * @param      Contact     $contact        Object Contact
	 * @return	   int                         <0 if KO, >0 id of first contact corresponding if OK
	 */
	function getContactIdFromInfos($contact)
	{
		global $conf;
		$contactId = -1;

		// Clean parameters
		$lastname = $contact->lastname ? trim($contact->lastname) : trim($contact->name);
		$firstname = trim($contact->firstname);
		if (!empty($conf->global->ECOMMERCENG_UPPERCASE_LASTNAME)) {
			$firstname = dol_ucwords(dol_strtolower($firstname));
			$lastname = dol_strtoupper($lastname);
		}
		if (!empty($conf->global->MAIN_FIRST_TO_UPPER)) $lastname = ucwords($lastname);
		if (!empty($conf->global->MAIN_FIRST_TO_UPPER)) $firstname = ucwords($firstname);
		$socid = $contact->socid;
		if (empty($socid)) $contact->socid = 0;
		$email = trim($contact->email);
		$phone_pro = trim($contact->phone_pro);
		$fax = trim($contact->fax);
		$zip = (empty($contact->zip) ? '' : $contact->zip);
		$town = (empty($contact->town) ? '' : $contact->town);
		$country_id = ($contact->country_id > 0 ? $contact->country_id : $contact->country_id);

		$sql = 'SELECT rowid FROM ' . MAIN_DB_PREFIX . 'socpeople';
		$sql .= ' WHERE lastname LIKE "' . $this->db->escape($lastname) . '"';
		$sql .= ' AND firstname LIKE "' . $this->db->escape($firstname) . '"';
		$sql .= ' AND address LIKE "' . $this->db->escape($contact->address) . '"';
		$sql .= ' AND town LIKE "' . $this->db->escape($town) . '"';
		$sql .= ' AND zip LIKE "' . $this->db->escape($zip) . '"';
		if (isset($contact->country_id)) $sql .= ' AND fk_pays ' . ($country_id > 0 ? '= '.$country_id : ' IS NULL');
		if (isset($contact->email)) $sql .= ' AND email LIKE "' . $this->db->escape($email) . '"';
		if (isset($contact->phone_pro)) $sql .= ' AND phone LIKE "' . $this->db->escape($phone_pro) . '"';
		if (isset($contact->fax)) $sql .= ' AND fax LIKE "' . $this->db->escape($fax) . '"';
		if ($socid > 0) $sql .= ' AND fk_soc = "' . $this->db->escape($socid) . '"';
		else if ($socid == -1) $sql .= " AND fk_soc IS NULL";
		$sql .= ' AND entity IN ('.getEntity('socpeople').')';

		$resql = $this->db->query($sql);
		if ($resql) {
			if ($this->db->num_rows($resql)) {
				$obj = $this->db->fetch_object($resql);

				$contactId = $obj->rowid;
			} else {
				$contactId = 0;
			}
			$this->db->free($resql);
			return $contactId;
		} else {
			$this->error = $this->db->lasterror();
			dol_syslog("eCommerceSynchro::getContactIdFromInfos " . $this->error, LOG_ERR);
			return $contactId;
		}
	}

	/**
	 * Get all payment gateways
	 *
	 * @return array|false    List of payment gateways or false if error
	 */
	public function getAllPaymentGateways()
	{
		$result = $this->eCommerceRemoteAccess->getAllPaymentGateways();
		if (!$result) {
			$this->error = $this->eCommerceRemoteAccess->error;
			$this->errors = $this->eCommerceRemoteAccess->errors;
		}
		return $result;
	}

	/**
	 * Get all remote warehouses
	 *
	 * @return array|false    List of payment gateways or false if error
	 */
	public function getAllRemoteWarehouses()
	{
		$result = $this->eCommerceRemoteAccess->getAllRemoteWarehouses();
		if (!$result) {
			$this->error = $this->eCommerceRemoteAccess->error;
			$this->errors = $this->eCommerceRemoteAccess->errors;
		}
		return $result;
	}

	/**
	 * Get all webhooks
	 *
	 * @return array|false    List of webhooks or false if error
	 */
	public function getAllWebHooks()
	{
		$result = $this->eCommerceRemoteAccess->getAllWebHooks();
		if (!$result) {
			$this->error = $this->eCommerceRemoteAccess->error;
			$this->errors = $this->eCommerceRemoteAccess->errors;
		}
		return $result;
	}

	/**
	 * Synchronize order to update from raw data
	 *
	 * @param   array   $raw_data 		Raw data to synchronize
	 * @return  int                 	>0 if OK, <0 if KO
	 */
	public function synchronizeOrderFromData($raw_data)
	{
		dol_syslog(__METHOD__ . ' raw_data=' . json_encode($raw_data), LOG_DEBUG);

		$this->error = '';
		$this->errors = array();

		try {
			$order_data = $this->eCommerceRemoteAccess->convertOrderDataIntoProcessedData($raw_data);
			if ($order_data === false) {
				$this->errors = array_merge(array($this->langs->trans('ECommerceErrorWhenConvertOrderData', $raw_data->id)), $this->errors);
				$this->errors = array_merge($this->errors, $this->eCommerceRemoteAccess->errors);
			} else {
				$result = $this->synchronizeOrder($order_data);

				return $result;
			}
		} catch (Exception $e) {
			$this->errors = array_merge(array($this->langs->trans('ECommerceErrorWhenSynchronizeOrders')), $this->errors);
			$this->errors[] = $e->getMessage();
		}

		return -1;
	}

	/**
	 * Synchronize product to update from raw data
	 *
	 * @param   array   $raw_data 		Raw data to synchronize
	 * @return  int                 	>0 if OK, <0 if KO
	 */
	public function synchronizeProductFromData($raw_data)
	{
		global $conf;
		dol_syslog(__METHOD__ . ' raw_data=' . json_encode($raw_data), LOG_DEBUG);

		$this->error = '';
		$this->errors = array();

		try {
			$product_data = $this->eCommerceRemoteAccess->convertProductDataIntoProcessedData($raw_data);
			if ($product_data === false) {
				$this->errors = array_merge(array($this->langs->trans('ECommerceErrorWhenConvertProductData', $raw_data->id)), $this->errors);
				$this->errors = array_merge($this->errors, $this->eCommerceRemoteAccess->errors);
			} else {
				$product_ref = dol_string_nospecial(trim($product_data['ref']));
				// if the ref not defined in the data provided
				if (!empty($product_data['has_variations']) || (empty($product_ref) && (empty($conf->global->PRODUCT_CODEPRODUCT_ADDON) || $conf->global->PRODUCT_CODEPRODUCT_ADDON == 'mod_codeproduct_leopard'))) {
					$tmp = explode('|', $product_data['remote_id']);
					if (count($tmp) > 2) {
						$remote_id_to_synchronize = $product_data['remote_parent_id'];
					} else {
						$remote_id_to_synchronize = $product_data['remote_id'];
					}

					// Check if product already synchronized
					$this->initECommerceProduct();
					$result = $this->eCommerceProduct->fetchByRemoteId($product_data['remote_id'], $this->eCommerceSite->id);
					if ($result < 0 && !empty($this->eCommerceProduct->error)) {
						$this->errors[] = $this->langs->trans('ECommerceErrorFetchProductLinkByRemoteId', $product_data['remote_id'], $this->eCommerceSite->id);
						$this->errors[] = $this->eCommerceProduct->error;
						return -1;
					}

					// Need to synchronize ?
					$bypass = $this->eCommerceProduct->fk_product > 0 && !empty($this->eCommerceProduct->last_update) && strtotime($product_data['last_update']) <= strtotime($this->eCommerceProduct->last_update);
					if ($bypass) {
						return 0;
					}

					// Get last data of product
					$products_data = $this->eCommerceRemoteAccess->convertRemoteObjectIntoDolibarrProduct(null, null, array($remote_id_to_synchronize), 1);
					if ($products_data === false) {
						$this->errors = array_merge($this->errors, $this->eCommerceRemoteAccess->errors);
						return -1;
					}

					if (empty($products_data)) {
						// Product not found
						dol_syslog(__METHOD__ . " - Product not found (Remote ID: {$remote_id_to_synchronize})", LOG_NOTICE);
						return 0;
					}
				} else {
					$products_data = array($product_data);
				}

				if (is_array($products_data)) {
					foreach ($products_data as $product_data) {
						$result = $this->synchronizeProduct($product_data);
						if ($result < 0) {
							return -1;
						}
					}
				}

				return 1;
			}
		} catch (Exception $e) {
			$this->errors = array_merge(array($this->langs->trans('ECommerceErrorWhenSynchronizeProducts')), $this->errors);
			$this->errors[] = $e->getMessage();
		}

		return -1;
	}

	/**
	 * Delete product link
	 *
	 * @param   string   $remote_id 		Remote ID
	 * @return  int                 	>0 if OK, <0 if KO
	 */
	public function deleteProductLink($remote_id)
	{
		dol_syslog(__METHOD__ . " remote_id=$remote_id", LOG_DEBUG);

		$this->error = '';
		$this->errors = array();
		$error = 0;

		if (empty($remote_id)) {
			$this->langs->load('errors');
			$this->errors[] = $this->langs->trans('ErrorBadParameters') . '; remote_id=' . $remote_id;
			$error++;
		}

		if (!$error) {
			$this->db->begin();

			try {
				// Check if product already synchronized
				$this->initECommerceProduct();
				$result = $this->eCommerceProduct->fetchByRemoteId($remote_id, $this->eCommerceSite->id);
				if ($result < 0 && !empty($this->eCommerceProduct->error)) {
					$this->errors[] = $this->langs->trans('ECommerceErrorFetchProductLinkByRemoteId', $remote_id, $this->eCommerceSite->id);
					$this->errors[] = $this->eCommerceProduct->error;
					$error++;
				}

				// Delete the link of the synchronization
				if (!$error && $this->eCommerceProduct->id > 0) {
					$result = $this->eCommerceProduct->delete($this->user);
					if ($result < 0) {
						$this->errors[] = $this->langs->trans('ECommerceErrorDeleteProductLink', $remote_id);
						$this->errors = array_merge($this->errors, $this->eCommerceProduct->errors);
						$error++;
					}
				}
			} catch (Exception $e) {
				$this->errors[] = $e->getMessage();
				$error++;
			}

			// Commit / rollback actions
			if ($error) {
				$this->db->rollback();
			} else {
				$this->db->commit();
			}
		}

		if ($error) {
			return -1;
		} else {
			return 1;
		}
	}

	/**
	 * Check if order is too old (create date) in dolibarr from the remote order data
	 *
	 * @param   array   $raw_data 		Raw data to synchronize
	 * @return	int						<0 if KO, =0 if not found, Id of the order in Dolibarr if OK
	 */
	public function isOrderTooOldFromData($raw_data)
	{
		dol_syslog(__METHOD__ . ' raw_data=' . json_encode($raw_data), LOG_DEBUG);

		$this->error = '';
		$this->errors = array();

		try {
			$order_data = $this->eCommerceRemoteAccess->convertOrderDataIntoProcessedData($raw_data);
			if ($order_data === false) {
				$this->errors = array_merge(array($this->langs->trans('ECommerceErrorWhenConvertOrderData', $raw_data->id)), $this->errors);
				$this->errors = array_merge($this->errors, $this->eCommerceRemoteAccess->errors);
			} else {
				$result = $this->isOrderExist(0, $order_data);

				return $result;
			}
		} catch (Exception $e) {
			$this->errors = array_merge(array($this->langs->trans('ECommerceErrorWhenCheckOrderExistFromData')), $this->errors);
			$this->errors[] = $e->getMessage();
		}

		return -1;
	}

	/**
	 * Synchronize a list of customer remote id
	 *
	 * @param	int		$from_date					Synchronize from date
	 * @param	int		$to_date					Synchronize to date
	 * @param	array	$remote_ids					List of customer remote id to synchronize
	 * @param	int		$toNb						Max nb
	 * @param	bool	$only_not_synchronized		Synchronize only customer not already synchronized
	 * @param	bool	$success_log				Keep success log
	 * @param	bool	$force_update				Force update even if update date not more recent
	 * @return	int									<0 if KO, >0 if OK
	 */
	public function synchronizeCustomers($from_date = null, $to_date = null, $remote_ids = array(), $toNb = 0, $only_not_synchronized = false, $success_log = true, $force_update = false)
	{
		dol_syslog(__METHOD__ . ' remote_ids=' . json_encode($remote_ids) . ', toNb=' . $toNb . ', only_not_synchronized=' . $only_not_synchronized . ', force_update=' . $force_update, LOG_DEBUG);

		$this->error = '';
		$this->errors = array();
		$error = 0;
		$nb_synchronized = 0;

		if (!is_array($remote_ids)) {
			$this->langs->load('errors');
			$this->errors[] = $this->langs->trans('ErrorBadParameters') . '; remote_ids=' . json_encode($remote_ids);
			$error++;
		}

		if (!$error) {
			try {
				if ($only_not_synchronized) {
					// Get list of customer remote id not already synchronized
					$remote_ids_to_synchronize = array();
					foreach ($remote_ids as $remote_id) {
						// Check if customer already synchronized
						$this->initECommerceSociete();
						$result = $this->eCommerceSociete->fetchByRemoteId($remote_id, $this->eCommerceSite->id);
						if ($result < 0) {
							if (!empty($this->eCommerceSociete->error)) {
								$this->errors[] = $this->langs->trans('ECommerceErrorFetchThirdPartyLinkByRemoteId', $remote_id, $this->eCommerceSite->id);
								$this->errors[] = $this->eCommerceSociete->error;
								$error++;
								break;
							} else {
								$remote_ids_to_synchronize[] = $remote_id;
							}
						}
					}
				} else {
					$remote_ids_to_synchronize = $remote_ids;
				}

				// Get list of customer data to synchronize
				if (!$error) {
					$customers_data = $this->eCommerceRemoteAccess->convertRemoteObjectIntoDolibarrSociete($from_date, $to_date, $remote_ids_to_synchronize, $toNb);
					if ($customers_data === false) {
						$this->errors = array_merge($this->errors, $this->eCommerceRemoteAccess->errors);
						$error++;
					}
				}

				if (!$error && !empty($customers_data)) {
					foreach ($customers_data as $customer_data) {
						// Synchronize the customer
						$result = $this->synchronizeCustomer($customer_data, $only_not_synchronized, $force_update);
						if ($result < 0) {
							$error++;
							break;
						} else {
							$nb_synchronized++;
						}
					}
				}
			} catch (Exception $e) {
				$this->errors[] = $e->getMessage();
				$error++;
			}
		}

		if ($error) {
			$this->errors = array_merge(array($this->langs->trans('ECommerceErrorWhenSynchronizeCustomers')), $this->errors);
			if ($success_log && $nb_synchronized) $this->success[] = $this->langs->trans('ECommerceSynchronizeCustomersSuccess', $nb_synchronized);
			return -1 - $nb_synchronized;
		} else {
			if ($success_log) $this->success[] = $this->langs->trans('ECommerceSynchronizeCustomersSuccess', $nb_synchronized);
			return $nb_synchronized;
		}
	}

	/**
	 * Synchronize a customer data in the third party in Dolibarr database
	 *
	 * @param	array	$customer_data				Customer data to synchronize
	 * @param	bool	$only_not_synchronized		Synchronize only customer not already synchronized
	 * @param	bool	$force_update				Force update even if update date not more recent
	 * @return	int									<0 if KO, Id of the third party in Dolibarr if OK
	 */
	public function synchronizeCustomer($customer_data, $only_not_synchronized = false, $force_update = false)
	{
		global $conf;
		dol_syslog(__METHOD__ . ' customer_data=' . json_encode($customer_data) . ', only_not_synchronized=' . $only_not_synchronized . ', force_update=' . $force_update, LOG_DEBUG);

		$this->error = '';
		$this->errors = array();
		$error = 0;

		require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
		$third_party = new Societe($this->db);

		if (!is_array($customer_data)) {
			$this->langs->load('errors');
			$this->errors[] = $this->langs->trans('ErrorBadParameters') . '; customer_data=' . json_encode($customer_data);
			$error++;
		}

		if (!$error && !empty($customer_data)) {
			$this->db->begin();

			try {
				// Search the customer in the third parties
				//-------------------------------------------
				$anonymous_customer = false;
				$third_party_id = 0;

				// Check if the customer is anonymous
				if (empty($customer_data['remote_id'])) {
					if ($this->eCommerceSite->fk_anonymous_thirdparty > 0) {
						$anonymous_customer = true;
					} else {
						$this->errors[] = $this->langs->trans('ECommerceErrorAnonymousThirdPartyNotConfigured', $this->eCommerceSite->id);
						$error++;
					}
				}

				// Check if customer already synchronized
				if (!$error) {
					$this->initECommerceSociete();
					if (!$only_not_synchronized && !$anonymous_customer) {
						$result = $this->eCommerceSociete->fetchByRemoteId(abs($customer_data['remote_id']), $this->eCommerceSite->id);
						if ($result < 0 && !empty($this->eCommerceSociete->error)) {
							$this->errors[] = $this->langs->trans('ECommerceErrorFetchThirdPartyLinkByRemoteId', abs($customer_data['remote_id']), $this->eCommerceSite->id);
							$this->errors[] = $this->eCommerceSociete->error;
							$error++;
						} else {
							$third_party_id = $this->eCommerceSociete->fk_societe;
						}
					}
				}

				// Need to synchronize ?
				$bypass = !$force_update && !empty($this->eCommerceSociete->last_update) && strtotime($customer_data['last_update']) <= strtotime($this->eCommerceSociete->last_update);

				// if remote_id < 0 then bypass the creation of the third party and contact. Only create the link with the third party ID equal 0
				if (!$error && $customer_data['remote_id'] >= 0 && !$bypass) {
					// Search by email or name if the customer is anonymous or not already synchronized
					if (!$error && (!($third_party_id > 0) || $anonymous_customer)) {
						// Search customer by email
						$result = $this->getThirdPartyByEmail($customer_data['email_key']);
						if ($result < 0) {
							if ($result == -2) {
								$this->warnings[] = $this->langs->trans('ECommerceErrorTooManyThirdPartyFoundDueToEmail', $customer_data['email_key'], 0);
								$result = $this->getFirstThirdPartyWhoNotAnonymousByEmail($customer_data['email_key']);
								if ($result < 0) {
									$error++;
								} else {
									$third_party_id = $result;
								}
							} else {
								$error++;
							}
						} else {
							$third_party_id = $result;
							// Check is the third party found is the anonymous third party
							if ($third_party_id > 0 && $this->eCommerceSite->fk_anonymous_thirdparty == $third_party_id) {
								$anonymous_customer = true;
							}
						}

						// Search customer by name if it's a company
						if (!$error && !($third_party_id > 0) && (!isset($customer_data['type']) || $customer_data['type'] == 'company')) {
							$result = $this->getThirdPartyByNameAndZip($customer_data['name'], $customer_data['zip']);
							if ($result < 0) {
								if ($result != -2) $error++;
							} else {
								$third_party_id = $result;
							}
						}
					}

					// Fetch third party
					if (!$error && $third_party_id > 0 && !$anonymous_customer) {
						$result = $third_party->fetch($third_party_id);
						if ($result < 0) {
							$this->errors[] = $this->langs->trans('ECommerceErrorFetchThirdParty', $third_party_id);
							if (!empty($third_party->error)) $this->errors[] = $third_party->error;
							$this->errors = array_merge($this->errors, $third_party->errors);
							$error++;
						}
					}

					// Set the third party
					//---------------------------
					if (!$error && (!$anonymous_customer || $third_party_id > 0)) {
						if ($third_party->id > 0) $third_party->oldcopy = clone $third_party;

						$third_party->name = $customer_data['name'];
						$third_party->client = $customer_data['client'];
						if (isset($customer_data['name_alias'])) $third_party->name_alias = $customer_data['name_alias'];
						if (isset($customer_data['email'])) $third_party->email = $customer_data['email'];
						if (isset($customer_data['vatnumber'])) {
							if (!empty($customer_data['vatnumber'])) {
								$third_party->tva_intra = $customer_data['vatnumber'];    // dol_trunc($customer_data['vatnumber'], 20, 'right', 'UTF-8', 1);
								$third_party->tva_assuj = 1;                              // tva_intra is not saved if this field is not set
							} else {
								$third_party->tva_assuj = 0;                              // tva_intra is not saved if this field is not set
							}
						}
						if (isset($customer_data['country_id'])) $third_party->country_id = $customer_data['country_id'];
						if (isset($customer_data['default_lang'])) $third_party->default_lang = $customer_data['default_lang'];
						if (!($third_party->id > 0)) $third_party->array_options = $this->getDefaultExtraFields($third_party->table_element, $this->eCommerceSite);
						if (is_array($customer_data['extrafields'])) {
							foreach ($customer_data['extrafields'] as $key => $value) {
								$third_party->array_options['options_' . $key] = $value;
							}
						}
						// Only when create third party
						if (isset($customer_data['address'])) $third_party->address = $customer_data['address'];
						if (isset($customer_data['zip'])) $third_party->zip = $customer_data['zip'];
						if (isset($customer_data['town'])) $third_party->town = $customer_data['town'];
						if (isset($customer_data['phone'])) $third_party->phone = $customer_data['phone'];
						$third_party->context['fromsyncofecommerceid'] = $this->eCommerceSite->id;

						if ($customer_data['type'] == 'user') {
							$third_party->typent_code = 'TE_PRIVATE';
							$third_party->typent_id = dol_getIdFromCode($this->db, $third_party->typent_code, 'c_typent', 'code', 'id');
							if (!($third_party->typent_id > 0)) {
								$this->errors[] = $this->langs->trans('ECommerceErrorPrivateThirdPartyTypeNotFound');
								$error++;
							}
						}

						if (!$error) {
							// Update third party
							if ($third_party->id > 0) {
								if (empty($this->eCommerceSite->parameters['dont_update_dolibarr_company'])) {
									if (!empty($conf->global->ECOMMERCENG_ENABLE_LOG_IN_NOTE)) {
										$third_party->note_private = dol_concatdesc($third_party->note_private, $this->langs->trans('ECommerceUpdateThirdPartyFromSiteNote', dol_print_date(dol_now(), 'dayhour'), $this->eCommerceSite->name, $customer_data['remote_id']));
										if (!empty($conf->global->ECOMMERCENG_ENABLE_DETAILED_UPDATE_LOG_IN_NOTE)) {
											$third_party->note_private = dol_concatdesc($third_party->note_private . " :", json_encode($customer_data['remote_datas']));
										}
									}

									$third_party->error = '';
									$third_party->errors = array();
									$result = $third_party->update($third_party->id, $this->user);
									if ($result < 0) {
										$this->errors[] = $this->langs->trans('ECommerceErrorUpdateThirdParty', $third_party->id);
									}
								}
							} // Create third party
							else {
								$third_party->code_client = -1;            // Automatic code
								$third_party->code_fournisseur = -1;        // Automatic code
								$third_party->note_private = isset($customer_data['note']) ? $customer_data['note'] : "";
								if (!empty($conf->global->ECOMMERCENG_ENABLE_LOG_IN_NOTE)) {
									$third_party->note_private = dol_concatdesc($third_party->note_private, $this->langs->trans('ECommerceCreateThirdPartyFromSiteNote', $this->eCommerceSite->name) . " :\n" . json_encode($customer_data['remote_datas']));
								}

								$third_party->error = '';
								$third_party->errors = array();
								$result = $third_party->create($this->user);
								if ($result < 0) {
									$this->errors[] = $this->langs->trans('ECommerceErrorCreateThirdParty');
								}
							}
							if ($result < 0) {
								if (!empty($third_party->error)) $this->errors[] = $third_party->error;
								$this->errors = array_merge($this->errors, $third_party->errors);
								if (empty($third_party->error) && empty($third_party->errors)) $this->errors[] = $this->db->lasterror();
								$error++;
							}
						}

						// Set category
						//------------------------------------------
						if (!$error) {
							if ($this->eCommerceSite->fk_cat_societe > 0) {
								require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
								$cat = new Categorie($this->db);
								$cat->fetch($this->eCommerceSite->fk_cat_societe);
								$cat->add_type($third_party, 'customer');
							} else {
								$this->errors[] = $this->langs->trans('ECommerceErrorThirdPartyCategoryNotConfigured', $this->eCommerceSite->id);
								$error++;
							}
						}
					}

					// Synchronize address / contacts
					//------------------------------------------
					if (!$error) {
						$remote_ids_to_synchronize = $this->eCommerceRemoteAccess->getRemoteAddressIdForSociete(abs($customer_data['remote_id']));   // Ask contacts to magento
						if (is_array($remote_ids_to_synchronize)) {
							if ($this->eCommerceSite->type == 2) { // Woocommerce
								$remote_ids_to_synchronize = $customer_data['remote_datas'];
							}

							// Move the contact in the anonymous third party to the third party found or newly created
							if ($anonymous_customer && $third_party->id > 0) {
								$sql = "UPDATE " . MAIN_DB_PREFIX . "socpeople AS sp";
								$sql .= " SET sp.fk_soc = " . $third_party->id;
								$sql .= " WHERE sp.email = '" . $this->db->escape($customer_data['email_key']) . "'";
								$sql .= " AND sp.fk_soc = " . $this->eCommerceSite->fk_anonymous_thirdparty;
								$sql .= " AND sp.entity IN (" . getEntity('socpeople') . ")";

								$resql = $this->db->query($sql);
								if (!$resql) {
									dol_syslog(__METHOD__ . ' SQL: ' . $sql . '; Error: ' . $this->db->lasterror(), LOG_ERR);
									$this->errors[] = $this->langs->trans('ECommerceErrorMoveContactsInAnonymousThirdPartyToNewOrFoundThirdParty', $third_party->id, $customer_data['email_key']);
									$this->errors[] = $this->db->error();
									$error++;
								}
							}

							// Get list of address/contact data to synchronize
							if (!$error) {
								$contacts_data = $this->eCommerceRemoteAccess->convertRemoteObjectIntoDolibarrSocpeople($remote_ids_to_synchronize);
								if (!is_array($contacts_data)) {
									$this->error = $this->eCommerceRemoteAccess->error;
									$this->errors = array_merge($this->errors, $this->eCommerceRemoteAccess->errors);
									$error++;
								} else {
									foreach ($contacts_data as $contact_data) {
										$contact_data['fk_soc'] = $third_party->id > 0 ? $third_party->id : $this->eCommerceSite->fk_anonymous_thirdparty;
										$contact_data['type'] = 1;    // address of company
										// Synchronize the contact
										$contact_id = $this->synchSocpeople($contact_data);
										if (!($contact_id > 0)) {
											$error++;
											break;
										}
									}
								}
							}
						}
					}
				}

				// Update the link of the synchronization
				//--------------------------------------------
				if (!$error && !empty($customer_data['remote_id']) && !$bypass) {
					$this->eCommerceSociete->last_update = $customer_data['last_update'];
					$this->eCommerceSociete->fk_societe = $third_party->id > 0 ? $third_party->id : 0;

					// Update link
					if ($this->eCommerceSociete->id > 0) {
						$result = $this->eCommerceSociete->update($this->user);
						if ($result < 0) {
							$this->errors[] = $this->langs->trans('ECommerceErrorUpdateThirdPartyLink');
						}
					} // Create link
					else {
						$this->eCommerceSociete->fk_site = $this->eCommerceSite->id;
						$this->eCommerceSociete->remote_id = abs($customer_data['remote_id']);
						$result = $this->eCommerceSociete->create($this->user);
						if ($result < 0) {
							$this->errors[] = $this->langs->trans('ECommerceErrorCreateThirdPartyLink');
						}
					}
					if ($result < 0) {
						$this->errors = array_merge($this->errors, $this->eCommerceSociete->errors);
						$error++;
					}
				}

				$last_sync_date = 'ECOMMERCE_LAST_SYNC_DATE_CUSTOMER_' . $this->eCommerceSite->id;
				if (!$error && (!is_numeric($conf->global->$last_sync_date) || $conf->global->$last_sync_date < $customer_data['create_date'])) {
					$result = dolibarr_set_const($this->db, $last_sync_date, $customer_data['create_date'], 'chaine', 0, '', $conf->entity);
					if ($result < 0) {
						$this->errors[] = $this->langs->trans('ECommerceErrorSetLastSyncDateCustomer');
						$this->errors[] = $this->db->lasterror();
						$error++;
					}
				}
			} catch (Exception $e) {
				$this->errors[] = $e->getMessage();
				$error++;
			}

			// Commit / rollback actions
			if ($error) {
				$this->db->rollback();
			} else {
				$this->db->commit();
			}
		}

		if ($error) {
			$this->errors = array_merge(array($this->langs->trans('ECommerceErrorWhenSynchronizeCustomer', $customer_data['remote_id'])), $this->errors);
			dol_syslog(__METHOD__ . ' Error=' . $this->errorsToString(), LOG_ERR);
			return -1;
		} else {
			return $third_party->id > 0 ? $third_party->id : $this->eCommerceSite->fk_anonymous_thirdparty;
		}
	}

	/**
	 * Synchronize a list of product remote id
	 *
	 * @param	int				$from_date				Synchronize from date
	 * @param	int				$to_date				Synchronize to date
	 * @param	array			$remote_ids				List of customer remote id to synchronize
	 * @param	int				$toNb					Max nb
	 * @param	bool			$success_log			Keep success log
	 * @param	CommonObject	$object_origin			Object origin who initialize the update of the products
	 * @return	int										<0 if KO, >0 if OK
	 */
	public function synchronizeProducts($from_date = null, $to_date = null, $remote_ids = array(), $toNb = 0, $success_log = true, $object_origin = null)
	{
		dol_syslog(__METHOD__ . ' remote_ids=' . json_encode($remote_ids) . ', toNb=' . $toNb, LOG_DEBUG);

		$this->error = '';
		$this->errors = array();
		$error = 0;
		$nb_synchronized = 0;
		$nb_updated = 0;

		if (!is_array($remote_ids)) {
			$this->langs->load('errors');
			$this->errors[] = $this->langs->trans('ErrorBadParameters') . '; remote_ids=' . json_encode($remote_ids);
			$error++;
		}

		if (!$error) {
			try {
				// Get list of product data to synchronize
				$products_data = $this->eCommerceRemoteAccess->convertRemoteObjectIntoDolibarrProduct($from_date, $to_date, $remote_ids, $toNb);
				if ($products_data === false) {
					$this->errors = array_merge($this->errors, $this->eCommerceRemoteAccess->errors);
					$error++;
				}

				if (!$error && !empty($products_data)) {
					foreach ($products_data as $product_data) {
						// Synchronize the product
						$result = $this->synchronizeProduct($product_data, $object_origin);
						if ($result < 0) {
							$error++;
							break;
						} else {
							$nb_synchronized++;
							if($result > 0) $nb_updated++;
						}
					}
				}
			} catch (Exception $e) {
				$this->errors[] = $e->getMessage();
				$error++;
			}
		}

		if ($error) {
			$this->errors = array_merge(array($this->langs->trans('ECommerceErrorWhenSynchronizeProducts')), $this->errors);
			if ($success_log && $nb_synchronized) $this->success[] = $this->langs->trans('ECommerceSynchronizeProductsSuccess', $nb_synchronized, $nb_updated);
			return -1 - $nb_synchronized;
		} else {
			if ($success_log) $this->success[] = $this->langs->trans('ECommerceSynchronizeProductsSuccess', $nb_synchronized, $nb_updated);
			return $nb_synchronized;
		}
	}

	/**
	 * Synchronize a product data in the third party in Dolibarr database
	 *
	 * @param	array			$product_data			Product data to synchronize
	 * @param	CommonObject	$object_origin			Object origin who initialize the update of the products
	 * @return	int										<0 if KO, Id of the product in Dolibarr if OK
	 */
	public function synchronizeProduct($product_data, $object_origin = null)
	{
		global $mysoc, $conf, $langs;
		dol_syslog(__METHOD__ . ' product_data=' . json_encode($product_data), LOG_DEBUG);

		$this->error = '';
		$this->errors = array();
		$error = 0;

		require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
		$product = new Product($this->db);

		if (!is_array($product_data)) {
			$this->langs->load('errors');
			$this->errors[] = $this->langs->trans('ErrorBadParameters') . '; product_data=' . json_encode($product_data);
			$error++;
		}

		$supported_status = empty($object->parameters['product_status_supported']) ? array() : array_filter(array_map('trim', explode(',', $object->parameters['product_status_supported'])), 'strlen');

		if (!$error && !empty($product_data) && (empty($supported_status) || in_array($product_data['status'], $supported_status) || is_object($object_origin))) {
			$this->db->begin();

			try {
				$product_ref = dol_string_nospecial(trim($product_data['ref']));
				$new_product = false;

				if (empty($product_ref) && (empty($conf->global->PRODUCT_CODEPRODUCT_ADDON) || $conf->global->PRODUCT_CODEPRODUCT_ADDON == 'mod_codeproduct_leopard')) {
					$this->errors[] = $this->langs->trans('ECommerceErrorProductRefMandatory');
					$error++;
				} else {
					// Unlink product of remote product removed
					if (!empty($product_data['variations'])) {
						$variations = array();
						foreach ($product_data['variations']['list'] as $v) {
							$variations[] = $this->db->escape($v);
						}

						// Get all product to unlink (product variations removed)
						$sql = "SELECT remote_id FROM " . MAIN_DB_PREFIX . "ecommerce_product" .
							" WHERE fk_site = " . $this->eCommerceSite->id .
							" AND (remote_id = '" . $this->db->escape($product_data['variations']['parent_remote_id']) . "'" .
							"      OR (remote_id LIKE '" . $this->db->escape($product_data['variations']['filter']) . "'" .
							"          AND remote_id NOT IN ('" . implode("','", $variations) . "')))";

						$resql = $this->db->query($sql);
						if (!$resql) {
							dol_syslog(__METHOD__ . ' SQL: ' . $sql . '; Errors: ' . $this->db->lasterror(), LOG_ERR);
							$errors[] = $this->langs->trans('ECommerceErrorWhenGetProductToUnlink', $this->eCommerceSite->name, json_encode($product_data['variations']));
							$errors[] = $this->db->lasterror();
							$error++;
						} else {
							while($obj = $this->db->fetch_object($resql)) {
								$result = $this->unlinkProduct($this->eCommerceSite->id, 0, $obj->remote_id);
								if ($result < 0) {
									$error++;
									break;
								}
							}
						}
					}

					// Check if product already synchronized
					if (!$error) {
						$this->initECommerceProduct();
						$result = $this->eCommerceProduct->fetchByRemoteId($product_data['remote_id'], $this->eCommerceSite->id);
						if ($result < 0 && !empty($this->eCommerceProduct->error)) {
							$this->errors[] = $this->langs->trans('ECommerceErrorFetchProductLinkByRemoteId', $product_data['remote_id'], $this->eCommerceSite->id);
							$this->errors[] = $this->eCommerceProduct->error;
							$error++;
						}
					}

					// Fetch product
					if (!$error && $this->eCommerceProduct->fk_product > 0) {
						$result = $product->fetch($this->eCommerceProduct->fk_product);
						if ($result < 0) {
							$this->errors[] = $this->langs->trans('ECommerceErrorFetchProduct', $this->eCommerceProduct->fk_product);
							if (!empty($product->error)) $this->errors[] = $product->error;
							$this->errors = array_merge($this->errors, $product->errors);
							$error++;
						}
					}

					// Fetch product by ref
					if (!$error && empty($product->id) && !empty($product_ref)) {
						$result = $product->fetch('', $product_ref);
						if ($result < 0) {
							$this->errors[] = $this->langs->trans('ECommerceErrorFetchProductByRef', $product_ref);
							if (!empty($product->error)) $this->errors[] = $product->error;
							$this->errors = array_merge($this->errors, $product->errors);
							$error++;
						} elseif ($result > 0) {
							$product_variation_mode_all_to_one = !empty($this->eCommerceSite->parameters['product_variation_mode']) && $this->eCommerceSite->parameters['product_variation_mode'] == 'all_to_one';

							// Check if product already synchronized
							$this->initECommerceProduct();
							$result = $this->eCommerceProduct->fetchByProductId($product->id, $this->eCommerceSite->id);
							if ($result < 0 && !empty($this->eCommerceProduct->error)) {
								$this->errors[] = $this->langs->trans('ECommerceErrorFetchProductLinkByProductId', $product->id, $this->eCommerceSite->id);
								$this->errors[] = $this->eCommerceProduct->error;
								$error++;
							} elseif ($result > 0 && strpos($product_data['remote_id'], '|') === false && preg_match('/^' . preg_quote($product_data['remote_id'] . '|') . '/', $this->eCommerceProduct->remote_id)) {
								// Variation who is transformed to simple

								// Get all product to unlink (product variations)
								$sql = "SELECT remote_id FROM " . MAIN_DB_PREFIX . "ecommerce_product" .
									" WHERE fk_site = " . $this->eCommerceSite->id .
									" AND remote_id LIKE '" . $this->db->escape($product_data['remote_id'] . '|%') . "'";

								$resql = $this->db->query($sql);
								if (!$resql) {
									dol_syslog(__METHOD__ . ' SQL: ' . $sql . '; Errors: ' . $this->db->lasterror(), LOG_ERR);
									$errors[] = $this->langs->trans('ECommerceErrorWhenGetProductToUnlink', $this->eCommerceSite->name, $product_data['remote_id'] . '|%');
									$errors[] = $this->db->lasterror();
									$error++;
								} else {
									while($obj = $this->db->fetch_object($resql)) {
										$result = $this->unlinkProduct($this->eCommerceSite->id, 0, $obj->remote_id);
										if ($result < 0) {
											$error++;
											break;
										}
									}
								}

								$this->initECommerceProduct();
							} elseif ($result > 0 && strpos($this->eCommerceProduct->remote_id, '|') === false && preg_match('/^' . preg_quote($this->eCommerceProduct->remote_id . '|') . '/', $product_data['remote_id'])) {
								// Simple who is transformed to variable
								$result = $this->unlinkProduct($this->eCommerceSite->id, 0, $this->eCommerceProduct->remote_id);
								if ($result < 0) {
									$error++;
								}

								$this->initECommerceProduct();
							} elseif ($result > 0 && $this->eCommerceProduct->remote_id !== $product_data['remote_id']) {
								$check = false;
								// Check same parent if option 'all variations in one product' set
								if ($product_variation_mode_all_to_one && preg_match('/^([^|]+)/', $this->eCommerceProduct->remote_id, $matches1) && preg_match('/^([^|]+)/', $product_data['remote_id'], $matches2)) {
									$check = $matches1[1] == $matches2[1];
								}
								if (!$check) {
									// Check if the remote product exist
									$remote_product_exist = $this->eCommerceRemoteAccess->checkRemoteProductExist($this->eCommerceProduct->remote_id);
									if ($remote_product_exist < 0) {
										$this->errors = array_merge($this->errors, $this->eCommerceRemoteAccess->errors);
										$error++;
									} elseif ($remote_product_exist > 0) {
										$this->errors[] = $this->langs->trans('ECommerceErrorProductAlreadyLinkedWithRemoteProduct', $product_ref, $this->eCommerceProduct->remote_id);
										$error++;
									} else {
										$result = $this->unlinkProduct($this->eCommerceSite->id, 0, $this->eCommerceProduct->remote_id);
										if ($result < 0) {
											$error++;
										}

										$this->initECommerceProduct();
									}
								}
							}
						}
					}

					// Need to synchronize ?
					$bypass = !empty($this->eCommerceProduct->last_update) && strtotime($product_data['last_update']) <= strtotime($this->eCommerceProduct->last_update);

					// Update if not found or the last update date more recent
					if (!$error && (empty($product->id) || empty($this->eCommerceProduct->id) || !$bypass)) {
						if ($product->id > 0) $product->oldcopy = clone $product;

						// Set the product
						$product->ref = !empty($product_ref) ? $product_ref : $product->ref;
						$product->ref_ext = $this->eCommerceSite->name . '-' . $product_data['remote_id'];
						$product->label = $product_data['label'];
						$product->description = isset($product_data['description']) ? $product_data['description'] : $product->description;
						$product->weight = isset($product_data['weight']) ? $product_data['weight'] : $product->weight;
						$product->weight_units = isset($product_data['weight_units']) ? $product_data['weight_units'] : $product->weight_units;
						$product->width = isset($product_data['width']) ? $product_data['width'] : $product->width;
						$product->width_units = isset($product_data['width_units']) ? $product_data['width_units'] : $product->width_units;
						$product->height = isset($product_data['height']) ? $product_data['height'] : $product->height;
						$product->height_units = isset($product_data['height_units']) ? $product_data['height_units'] : $product->height_units;
						$product->length = isset($product_data['length']) ? $product_data['length'] : $product->length;
						$product->length_units = isset($product_data['length_units']) ? $product_data['length_units'] : $product->length_units;
						$product->type = $product_data['fk_product_type'];
						$product->finished = $product_data['finished'];
						$product->status = $product_data['envente'];
						if (isset($product_data['enachat'])) $product->status_buy = $product_data['enachat'];

						$product->country_id = $product_data['fk_country'];
						$product->url = strlen($product_data['url']) > 255 ? '' : $product_data['url'];

						if (!isset($product->stock_reel)) $product->stock_reel = 0;
						if (property_exists($product, 'stockable_product')) $product->stockable_product = $product_data['extrafields']['ecommerceng_stockable_product'];

						if (!($product->id > 0)) $product->array_options = $this->getDefaultExtraFields($product->table_element, $this->eCommerceSite);
						if (is_array($product_data['extrafields'])) {
							foreach ($product_data['extrafields'] as $key => $value) {
								$product->array_options['options_' . $key] = $value;
							}
						}

						$product->context['fromsyncofecommerceid'] = $this->eCommerceSite->id;

						// Update product
						if ($product->id > 0) {
							if (!empty($conf->global->ECOMMERCENG_ENABLE_LOG_IN_NOTE)) {
								if (empty($product->note_private) && !empty($product->note)) $product->note_private = $product->note;
								$product->note_private = dol_concatdesc($product->note_private, $this->langs->trans('ECommerceUpdateProductFromSiteNote', dol_print_date(dol_now(), 'dayhour'), $this->eCommerceSite->name, $product_data['remote_id']));
								if (!empty($conf->global->ECOMMERCENG_ENABLE_DETAILED_UPDATE_LOG_IN_NOTE)) {
									$product->note_private = dol_concatdesc($product->note_private . " :", json_encode($product_data['remote_datas']));
								}
								$product->note = $product->note_private;
							}

							$product->error = '';
							$product->errors = array();
							$result = $product->update($product->id, $this->user, false, 'update', true);
							if ($result < 0) {
								$this->errors[] = $this->langs->trans('ECommerceErrorUpdateProduct', $product->id);
								if (!empty($product_ref) && $product_ref != $product->oldcopy->ref && $product->db->errno() == 'DB_ERROR_RECORD_ALREADY_EXISTS' && empty($conf->barcode->enabled) || empty($product->barcode)) {
									$this->errors[] = $this->langs->trans('ECommerceErrorUpdateRefProduct', $product->oldcopy->ref, $product_ref);
								}
							}
						} // Create product
						else {
							$new_product = true;
							if (!isset($product_data['enachat']) && !empty($conf->global->ECOMMERCENG_PRODUCT_IN_PURCHASE_WHEN_CREATED)) $product->status_buy = 1;
							$product->canvas = $product_data['canvas'];
							$product->note_private = isset($product_data['note']) ? $product_data['note'] : "";
							if (!empty($conf->global->ECOMMERCENG_ENABLE_LOG_IN_NOTE)) {
								$product->note_private = dol_concatdesc($product->note_private, $this->langs->trans('ECommerceCreateProductFromSiteNote', $this->eCommerceSite->name) . " :\n" . json_encode($product_data['remote_datas']));
							}
							$product->note = $product->note_private;

							if ($product->type == Product::TYPE_PRODUCT) {
								$product->accountancy_code_sell = isset($this->eCommerceSite->parameters['default_account']['accounting_product_sold_account']) ? $this->eCommerceSite->parameters['default_account']['accounting_product_sold_account'] : $conf->global->ACCOUNTING_PRODUCT_SOLD_ACCOUNT;
								$product->accountancy_code_sell_intra = isset($this->eCommerceSite->parameters['default_account']['accounting_product_sold_intra_account']) ? $this->eCommerceSite->parameters['default_account']['accounting_product_sold_intra_account'] : $conf->global->ACCOUNTING_PRODUCT_SOLD_INTRA_ACCOUNT;
								$product->accountancy_code_sell_export = isset($this->eCommerceSite->parameters['default_account']['accounting_product_sold_export_account']) ? $this->eCommerceSite->parameters['default_account']['accounting_product_sold_export_account'] : $conf->global->ACCOUNTING_PRODUCT_SOLD_EXPORT_ACCOUNT;
								$product->accountancy_code_buy = isset($this->eCommerceSite->parameters['default_account']['accounting_product_buy_account']) ? $this->eCommerceSite->parameters['default_account']['accounting_product_buy_account'] : $conf->global->ACCOUNTING_PRODUCT_BUY_ACCOUNT;
								$product->accountancy_code_buy_intra = isset($this->eCommerceSite->parameters['default_account']['accounting_product_buy_intra_account']) ? $this->eCommerceSite->parameters['default_account']['accounting_product_buy_intra_account'] : $conf->global->ACCOUNTING_PRODUCT_BUY_INTRA_ACCOUNT;
								$product->accountancy_code_buy_export = isset($this->eCommerceSite->parameters['default_account']['accounting_product_buy_export_account']) ? $this->eCommerceSite->parameters['default_account']['accounting_product_buy_export_account'] : $conf->global->ACCOUNTING_PRODUCT_BUY_EXPORT_ACCOUNT;
							} elseif ($product->type == Product::TYPE_SERVICE) {
								$product->accountancy_code_sell = isset($this->eCommerceSite->parameters['default_account']['accounting_service_sold_account']) ? $this->eCommerceSite->parameters['default_account']['accounting_service_sold_account'] : $conf->global->ACCOUNTING_SERVICE_SOLD_ACCOUNT;
								$product->accountancy_code_sell_intra = isset($this->eCommerceSite->parameters['default_account']['accounting_service_sold_intra_account']) ? $this->eCommerceSite->parameters['default_account']['accounting_service_sold_intra_account'] : $conf->global->ACCOUNTING_SERVICE_SOLD_INTRA_ACCOUNT;
								$product->accountancy_code_sell_export = isset($this->eCommerceSite->parameters['default_account']['accounting_service_sold_export_account']) ? $this->eCommerceSite->parameters['default_account']['accounting_service_sold_export_account'] : $conf->global->ACCOUNTING_SERVICE_SOLD_EXPORT_ACCOUNT;
								$product->accountancy_code_buy = isset($this->eCommerceSite->parameters['default_account']['accounting_service_buy_account']) ? $this->eCommerceSite->parameters['default_account']['accounting_service_buy_account'] : $conf->global->ACCOUNTING_SERVICE_BUY_ACCOUNT;
								$product->accountancy_code_buy_intra = isset($this->eCommerceSite->parameters['default_account']['accounting_service_buy_intra_account']) ? $this->eCommerceSite->parameters['default_account']['accounting_service_buy_intra_account'] : $conf->global->ACCOUNTING_SERVICE_BUY_INTRA_ACCOUNT;
								$product->accountancy_code_buy_export = isset($this->eCommerceSite->parameters['default_account']['accounting_service_buy_export_account']) ? $this->eCommerceSite->parameters['default_account']['accounting_service_buy_export_account'] : $conf->global->ACCOUNTING_SERVICE_BUY_EXPORT_ACCOUNT;
							}

							$product->error = '';
							$product->errors = array();
							$result = $product->create($this->user);
							if ($result < 0) {
								$this->errors[] = $this->langs->trans('ECommerceErrorCreateProduct');
							}
						}
						if ($result < 0) {
							if (!empty($product->error)) $this->errors[] = $product->error;
							$this->errors = array_merge($this->errors, $product->errors);
							if (empty($product->error) && empty($product->errors)) $this->errors[] = $this->db->lasterror();
							$error++;
						}

						// Update multi-languages
						if (!$error && !empty($product_data['translates'])) {
							$current_lang = $mysoc->default_lang;

							foreach ($product_data['translates'] as $lang => $infos) {
								if ($current_lang == $lang) {
									$product->label = $infos['label'];
									// $product->description = dol_htmlcleanlastbr($infos['description']);
									// $product->other = '';
								} else {
									$product->multilangs[$lang]["label"] = $infos['label'];
									// $product->multilangs[$lang]["description"] = dol_htmlcleanlastbr($infos['description']);
									// $product->multilangs[$lang]["other"] = '';
								}
							}

							$result = $product->update($product->id, $this->user);
							if ($result > 0) $result = $product->setMultiLangs($this->user);
							if ($result < 0) {
								$this->errors[] = $langs->trans('ECommerceErrorUpdateProductMultiLanguages');
								$this->errors[] = $product->errorsToString();
								$error++;
							}
						}

						// Set price
						if (!$error) {
							if ($new_product) {
								// Set price
								$price_level = !empty($this->eCommerceSite->price_level) ? $this->eCommerceSite->price_level : 1;

								// The price type from eCommerce is defined for the site: TI/TE (Tax Include / Tax Excluded)
								if (empty($conf->global->PRODUIT_MULTIPRICES)) {
									$result = $product->updatePrice($product_data['price'], $this->eCommerceSite->ecommerce_price_type, $this->user, $product_data['tax_rate'], $product_data['price_min']);
								} else {
									$result = $product->updatePrice($product_data['price'], $this->eCommerceSite->ecommerce_price_type, $this->user, $product_data['tax_rate'], $product_data['price_min'], $price_level);
								}
								if ($result <= 0) {
									$this->errors[] = $this->langs->trans('ECommerceErrorUpdateProductStock');
									if (!empty($product->error)) $this->errors[] = $product->error;
									$this->errors = array_merge($this->errors, $product->errors);
									$error++;
								}
							} else {
								// Update price if need
								$price_level = empty($this->eCommerceSite->price_level) ? 1 : $this->eCommerceSite->price_level;

								// Get current product values
								if (empty($conf->global->PRODUIT_MULTIPRICES)) {
									$price_base_type_org = $product->price_base_type;
									$price_org = $product->price;
									$price_min_org = $product->price_min;
									$tax_rate_org = $product->tva_tx;
								} else {
									$price_base_type_org = $product->multiprices_base_type[$price_level];
									$price_org = $product->multiprices[$price_level];
									$price_min_org = $product->multiprices_min[$price_level];
									$tax_rate_org = $product->multiprices_tva_tx[$price_level];
								}

								$price_base_type = $this->eCommerceSite->ecommerce_price_type;
								if (isset($product_data['price_base_type'])) $price_base_type = $product_data['price_base_type'];

								if ($price_base_type_org != $price_base_type ||
									$price_org != $product_data['price'] ||
									(isset($product_data['price_min']) && $price_min_org != $product_data['price_min']) ||
									price2num((float)$product_data['tax_rate']) != price2num((float)$tax_rate_org)
								) {
									if ($product_data['price_min'] === '') {
										$product_data['price_min'] = $price_min_org <= $product_data['price'] ? $price_min_org : $product_data['price'];
									}
									// The price type from eCommerce is defined for the site: TI/TE (Tax Include / Tax Excluded)
									if (empty($conf->global->PRODUIT_MULTIPRICES)) {
										$result = $product->updatePrice($product_data['price'], $price_base_type, $this->user, $product_data['tax_rate'], $product_data['price_min']);
									} else {
										$result = $product->updatePrice($product_data['price'], $price_base_type, $this->user, $product_data['tax_rate'], $product_data['price_min'], $price_level);
									}
									if ($result <= 0) {
										$this->errors[] = $this->langs->trans('ECommerceErrorUpdateProductPrice');
										if (!empty($product->error)) $this->errors[] = $product->error;
										$this->errors = array_merge($this->errors, $product->errors);
										$error++;
									}
								}
							}
						}

						// Set stock
						if (!$error && ($product->type != Product::TYPE_SERVICE || !empty($conf->global->STOCK_SUPPORTS_SERVICES)) &&
							$this->eCommerceSite->stock_sync_direction == 'ecommerce2dolibarr' && !empty($product->array_options["options_ecommerceng_wc_manage_stock_{$this->eCommerceSite->id}_{$conf->entity}"]) &&
							empty($product->array_options["options_ecommerceng_wc_dont_update_stock_{$this->eCommerceSite->id}_{$conf->entity}"])
						) {
                            $product->load_stock();

                            $new_warehouses_stock = array();
                            if (!empty($this->eCommerceSite->parameters['enable_warehouse_plugin_support'])) {
                                dol_include_once('/ecommerceng/class/data/eCommerceRemoteWarehouses.class.php');
                                $eCommerceRemoteWarehouses = new eCommerceRemoteWarehouses($this->db);
                                $remote_warehouses = $eCommerceRemoteWarehouses->get_all($this->eCommerceSite->id);
                                if (!is_array($remote_warehouses)) {
                                    $error++;
                                    $this->errors[] = $eCommerceRemoteWarehouses->errorsToString();
                                }

                                if (!$error && !empty($product_data['stock_by_warehouse'])) {
                                    foreach ($product_data['stock_by_warehouse'] as $remote_warehouse_id => $stock) {
                                        $local_warehouse_id = isset($remote_warehouses[$remote_warehouse_id]['warehouse_id']) && $remote_warehouses[$remote_warehouse_id]['warehouse_id'] > 0 ? $remote_warehouses[$remote_warehouse_id]['warehouse_id'] : 0;
                                        if (empty($local_warehouse_id)) {
											dol_syslog(__METHOD__ . ' Warehouse not configured for remote warehouse ID ' . $remote_warehouse_id . ' so we don\'t process this remote warehouse', LOG_WARNING);
//                                            $error++;
//                                            $this->errors[] = 'Error - Unknown remote warehouse : ' . $remote_warehouse_id;
                                        } else {
                                            $current_stock = isset($product->stock_warehouse[$local_warehouse_id]->real) ? $product->stock_warehouse[$local_warehouse_id]->real : 0;
                                            $new_warehouses_stock[$local_warehouse_id] = price2num($stock - $current_stock);
                                        }
                                    }
                                }
                            } else {
                                if ($this->eCommerceSite->fk_warehouse > 0) {
                                    $current_stock = isset($product->stock_warehouse[$this->eCommerceSite->fk_warehouse]->real) ? $product->stock_warehouse[$this->eCommerceSite->fk_warehouse]->real : 0;
                                    $new_warehouses_stock[$this->eCommerceSite->fk_warehouse] = price2num($product_data['stock_qty'] - $current_stock);
                                } else {
                                    $error++;
                                    $this->errors[] = 'SetupOfWarehouseNotDefinedForThisSite';
                                }
                            }

                            if (!$error) {
                                foreach ($new_warehouses_stock as $warehouse_id => $new_stock_delta) {
                                    if (empty($new_stock_delta)) continue;

                                    // Update/init stock
                                    include_once DOL_DOCUMENT_ROOT . '/product/stock/class/mouvementstock.class.php';
                                    $movement = new MouvementStock($this->db);
                                    $movement->context['fromsyncofecommerceid'] = $this->eCommerceSite->id;

                                    if (isset($object_origin->element) && isset($object_origin->id) && $object_origin->id > 0) {
                                        $movement->origin = $object_origin;
                                    }
                                    $lot = $product->status_batch ? '000000' : null;
                                    if ($new_stock_delta < 0) {
                                        $result = $movement->livraison($this->user, $product->id, $warehouse_id, abs($new_stock_delta), 0, $langs->trans($new_product ? 'ECommerceStockInitFromWooSync' : 'ECommerceStockUpdateFromWooSync'), '', '', $lot);
                                    } else {
                                        $result = $movement->reception($this->user, $product->id, $warehouse_id, $new_stock_delta, 0, $langs->trans($new_product ? 'ECommerceStockInitFromWooSync' : 'ECommerceStockUpdateFromWooSync'), '', '', $lot);
                                    }
                                    if ($result <= 0) {
                                        $this->errors[] = $this->langs->trans('ECommerceErrorUpdateProductStock');
                                        if (!empty($movement->error)) $this->errors[] = $movement->error;
                                        $this->errors = array_merge($this->errors, $movement->errors);
                                        $error++;
                                    }
                                }
                            }
						}

						// Set update date of the product with last update date from site
						if (!$error) {
							$sql = "UPDATE " . MAIN_DB_PREFIX . "product SET tms = '" . $this->db->escape($product_data['last_update']) . "' WHERE rowid = " . $product->id;
							$resql = $this->db->query($sql);
							if (!$resql) {
								$this->errors[] = $this->langs->trans('ECommerceErrorSetProductUpdateDate');
								$this->errors[] = $this->db->lasterror();
								$error++;
							}
						}

						// Set category
						if (!$error) {
							if (!($this->eCommerceSite->fk_cat_product > 0)) {
								$this->errors[] = $this->langs->trans('ECommerceErrorProductCategoryNotConfigured', $this->eCommerceSite->id);
								$error++;
							} else {
								require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';

								$this->initECommerceCategory();
								$category_ids = $this->eCommerceCategory->getDolibarrCategoryFromRemoteIds($product_data['categories'], $this->eCommerceSite->id);     // Return array of dolibarr category ids found into link table
								if (is_array($category_ids)) {
									if (count($category_ids) != count($product_data['categories'])) {
										$result = $this->synchCategory();
										if ($result < 0) {
											$this->errors = array_merge(array($this->langs->trans('ECommerceErrorWhenSynchronizeCategories')), $this->errors);
											$error++;
										} else {
											$category_ids = $this->eCommerceCategory->getDolibarrCategoryFromRemoteIds($product_data['categories'], $this->eCommerceSite->id);     // Return array of dolibarr category ids found into link table
										}
									}
								}

								if (is_array($category_ids)) {
									if (count($category_ids) != count($product_data['categories'])) {
										$this->errors = array_merge(array($this->langs->trans('ECommerceErrorNumberCategoryToLinkMismatched', implode(',', $product_data['categories']), implode(',', $category_ids))), $this->errors);
										$error++;
									} else {
										// Add root category if not in the remote product categories
										if (!in_array($this->eCommerceSite->fk_cat_product, $category_ids)) $category_ids[] = $this->eCommerceSite->fk_cat_product;

										// Get all current categories on product
										$cat = new Categorie($this->db);
										$product_category_ids = $cat->containing($product->id, 'product', 'id');
										$product_category_ids = is_array($product_category_ids) ? $product_category_ids : array();

										// Load all synchronized categories of the site
										$this->loadProductCategories();

										// Add new categories
										foreach ($category_ids as $category_id) {
											if (!in_array($category_id, $product_category_ids)) {
												$cat = new Categorie($this->db); // Instanciate a new cat without id (to avoid fetch)
												$cat->id = $category_id;     // Affecting id (for calling add_type)
												$cat->add_type($product, 'product');
											}
										}

										// Delete old categories
										foreach ($product_category_ids as $category_id) {
											if (isset($this->product_category_cached[$this->eCommerceSite->id][$category_id]) && !in_array($category_id, $category_ids)) {
												$cat = new Categorie($this->db);
												$cat->fetch($category_id);
												$cat->del_type($product, 'product');
											}
										}
									}
								} else {
									$cat = new Categorie($this->db);
									$cat->id = $this->eCommerceSite->fk_cat_product;
									$cat->add_type($product, 'product');
								}
							}
						}

						// Synchronize images
						if (!$error) {
							$productImageSynchDirection = isset($this->eCommerceSite->parameters['product_synch_direction']['image']) ? $this->eCommerceSite->parameters['product_synch_direction']['image'] : '';
							if ($productImageSynchDirection == 'etod' || $productImageSynchDirection == 'all') {
								if (is_array($product_data['images'])) {
									foreach ($product_data['images'] as $image) {
										if (!preg_match('@woocommerce/assets/images@i', $image['url'])) {
											$ret = ecommerceng_download_image($image, $product, $error_message);
											if (!$ret) {
												$warning_label = $this->langs->trans('ECommerceErrorDownloadProductImage', implode(',', $image), $product->id, $product_data['remote_id'], $this->eCommerceSite->name) . ': ' . $error_message;
												$this->warnings[] = $warning_label;
												dol_syslog($warning_label, LOG_WARNING);
											}
										}
									}
								}

								// Remove obsolete image
								$ret = ecommerceng_remove_obsolete_image($product, $product_data['images'], $error_message);
								if (!$ret) {
									$warning_label = $this->langs->trans('ECommerceErrorRemoveObsoleteProductImage', $product->id, $product_data['remote_id'], $this->eCommerceSite->name) . ': ' . $error_message;
									$this->warnings[] = $warning_label;
									dol_syslog($warning_label, LOG_WARNING);
								}
							}
						}

						// Manage virtual product
						if (!empty($product_data['components'])) {
							// Get new components
							$new_component = [];
							foreach ($product_data['components'] as $sub_product_id => $sub_product_qty) {
								$eCommerceProduct = new eCommerceProduct($this->db);
								$result = $eCommerceProduct->fetchByRemoteId($sub_product_id, $this->eCommerceSite->id); // load info of table ecommerce_product
								if ($result < 0 && empty($eCommerceProduct->error)) $result = $eCommerceProduct->fetchByRemoteId('%|' . $sub_product_id, $this->eCommerceSite->id); // load info of table ecommerce_product
								if ($result < 0 && !empty($eCommerceProduct->error)) {
									$this->errors[] = $this->langs->trans('ECommerceErrorFetchProductLinkByRemoteId', $sub_product_id, $this->eCommerceSite->id);
									$this->errors[] = $eCommerceProduct->error;
									$error++;
									break;
								} elseif ($result > 0) {
									// Add new components already synchronized
									$new_component[$eCommerceProduct->fk_product] = $sub_product_qty;
								} else {
									// Synchronize missing new components
									$result = $this->synchronizeProducts(null, null, [$sub_product_id], 1, false);
									if ($result < 0) {
										$error++;
										break;
									} else {
										$this->initECommerceProduct();
										$result = $eCommerceProduct->fetchByRemoteId($sub_product_id, $this->eCommerceSite->id); // load info of table ecommerce_product
										if ($result < 0 && empty($eCommerceProduct->error)) $result = $eCommerceProduct->fetchByRemoteId('%|' . $sub_product_id, $this->eCommerceSite->id); // load info of table ecommerce_product
										if ($result < 0 && !empty($eCommerceProduct->error)) {
											$this->errors[] = $this->langs->trans('ECommerceErrorFetchProductLinkByRemoteId', $sub_product_id, $this->eCommerceSite->id);
											$this->errors[] = $eCommerceProduct->error;
											$error++;
											break;
										} elseif ($result > 0) {
											// Add new components already synchronized
											$new_component[$eCommerceProduct->fk_product] = $sub_product_qty;
										} else {
											$this->errors[] = $this->langs->trans('ECommerceErrorSynchronizeProductComponent', $sub_product_id, $this->eCommerceSite->id);
											$error++;
											break;
										}
									}
								}
							}

							// Get current components
							if (!$error) {
								$current_components = $product->getChildsArbo($product->id, 1);
								if (!is_array($current_components)) {
									$this->errors[] = $this->langs->trans('ECommerceErrorFetchProductComponents', $product->id);
									$error++;
								}
							}

							// Add / Update components
							if (!$error) {
								foreach ($new_component as $sub_product_id => $sub_product_qty) {
									if (isset($current_components[$sub_product_id])) {
										$result = $product->update_sousproduit($product->id, $sub_product_id, $sub_product_qty, 1);
										if ($result < 0) {
											$this->errors[] = $this->langs->trans('ECommerceErrorUpdateProductComponent', $sub_product_id, $sub_product_qty, $product->id);
											$error++;
											break;
										}
									} else {
										$result = $product->add_sousproduit($product->id, $sub_product_id, $sub_product_qty, 1);
										if ($result < 0) {
											$this->errors[] = $this->langs->trans('ECommerceErrorAddProductComponent', $sub_product_id, $sub_product_qty, $product->id);
											$error++;
											break;
										}
									}
								}
							}

							// Remove missing components
							if (!$error) {
								foreach ($current_components as $sub_product_id => $sub_product_info) {
									if (!isset($new_component[$sub_product_id])) {
										$result = $product->del_sousproduit($product->id, $sub_product_id);
										if ($result < 0) {
											$this->errors[] = $this->langs->trans('ECommerceErrorDelProductComponent', $sub_product_id, $product->id);
											$error++;
											break;
										}
									}
								}
							}
						}

						// Update the link of the synchronization
						//--------------------------------------------
						if (!$error && !empty($product_data['remote_id'])) {
							$this->eCommerceProduct->last_update = $product_data['last_update'];
							if ($this->eCommerceSite->stock_sync_direction == 'ecommerce2dolibarr') $this->eCommerceProduct->last_update_stock = $product_data['last_update'];
							$this->eCommerceProduct->fk_product = $product->id > 0 ? $product->id : 0;
                            $this->eCommerceProduct->remote_id = $product_data['remote_id'];
                            $this->eCommerceProduct->lang = $product_data['language'];

							// Update link
							if ($this->eCommerceProduct->id > 0) {
								$result = $this->eCommerceProduct->update($this->user);
								if ($result < 0) {
									$this->errors[] = $this->langs->trans('ECommerceErrorUpdateProductLink');
								}
							} // Create link
							else {
								$this->eCommerceProduct->fk_site = $this->eCommerceSite->id;
								$result = $this->eCommerceProduct->create($this->user);
								if ($result < 0) {
									$this->errors[] = $this->langs->trans('ECommerceErrorCreateProductLink');
								}
							}
							if ($result < 0) {
								$this->errors = array_merge($this->errors, $this->eCommerceProduct->errors);
								$error++;
							}
						}

						$last_sync_date = 'ECOMMERCE_LAST_SYNC_DATE_PRODUCT_' . $this->eCommerceSite->id;
						if (!$error && (!is_numeric($conf->global->$last_sync_date) || $conf->global->$last_sync_date < $product_data['create_date'])) {
							$result = dolibarr_set_const($this->db, $last_sync_date, $product_data['create_date'], 'chaine', 0, '', $conf->entity);
							if ($result < 0) {
								$this->errors[] = $this->langs->trans('ECommerceErrorSetLastSyncDateProduct');
								$this->errors[] = $this->db->lasterror();
								$error++;
							}
						}
					}
				}
			} catch (Exception $e) {
				$this->errors[] = $e->getMessage();
				$error++;
			}

			// Commit / rollback actions
			if ($error) {
				$this->db->rollback();
			} else {
				$this->db->commit();
			}
		}

		if ($error) {
			$this->errors = array_merge(array($this->langs->trans('ECommerceErrorWhenSynchronizeProduct', $product_data['remote_id'])), $this->errors);
			dol_syslog(__METHOD__ . ' Error=' . $this->errorsToString(), LOG_ERR);
			return -1;
		} else {
			return $product->id > 0 && empty($bypass) ? $product->id : 0;
		}
	}

	/**
	 *  Unlink product
	 *
	 * @param	int		$site_id		Site ID
	 * @param	int		$product_id		Product ID
	 * @param	string	$remote_id		Remote ID
	 * @return	int						<0 if KO, >0 if OK
	 */
	public function unlinkProduct($site_id, $product_id = 0, $remote_id = '')
	{
		dol_syslog(__METHOD__ . " site_id=$site_id, product_id=$product_id, remote_id=$remote_id", LOG_DEBUG);
		global $user;

		$error = 0;
		$this->db->begin();

		// Delete link to ecommerce
		$eCommerceProduct = new eCommerceProduct($this->db);
		if ($product_id > 0 && $eCommerceProduct->fetchByProductId($product_id, $site_id) > 0) {
			if ($eCommerceProduct->delete($user) < 0) {
				$errors[] = $this->langs->trans('ECommerceErrorWhenUnlinkProductByProductId', $this->eCommerceSite->name, $product_id);
				$error++;
			}
		} elseif ($remote_id > 0 && $eCommerceProduct->fetchByRemoteId($remote_id, $site_id) > 0) {
			if ($eCommerceProduct->delete($user) < 0) {
				$errors[] = $this->langs->trans('ECommerceErrorWhenUnlinkProductByRemoteId', $this->eCommerceSite->name, $remote_id);
				$error++;
			}
		} else {
			$errors[] = $this->langs->trans('ECommerceErrorWhenUnlinkProduct', $this->eCommerceSite->name, $product_id, $remote_id);
			$error++;
		}

		// Delete all categories of the ecommerce
		if (!$error && empty($conf->global->ECOMMERCE_DONT_UNSET_CATEGORIE_OF_PRODUCT_WHEN_DELINK)) {
			require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
			$eCommerceSite = new eCommerceSite($this->db);
			$product = new Product($this->db);
			if ($eCommerceSite->fetch($site_id) > 0 && $product->fetch($eCommerceProduct->fk_product) > 0) {
				require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
				$cat = new Categorie($this->db);
				$cat_root = $eCommerceSite->fk_cat_product;
				$all_cat_full_arbo = $cat->get_full_arbo('product');
				$cats_full_arbo = array();
				foreach ($all_cat_full_arbo as $category) {
					$cats_full_arbo[$category['id']] = $category['fullpath'];
				}
				$categories = $cat->containing($product->id, 'product', 'id');
				foreach ($categories as $cat_id) {
					if (isset($cats_full_arbo[$cat_id]) &&
						(preg_match("/^{$cat_root}$/", $cats_full_arbo[$cat_id]) || preg_match("/^{$cat_root}_/", $cats_full_arbo[$cat_id]) ||
							preg_match("/_{$cat_root}_/", $cats_full_arbo[$cat_id]) || preg_match("/_{$cat_root}$/", $cats_full_arbo[$cat_id])
						)
					) {
						if ($cat->fetch($cat_id) > 0) {
							if ($cat->del_type($product, 'product') < 0) {
								$errors[] = $this->langs->trans('ECommerceErrorWhenUnlinkProductCategory', $this->eCommerceSite->name, $product->id, $cat_id);
								$error++;
							}
						}
					}
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
	 * Synchronize a list of order remote id
	 *
	 * @param	int		$from_date						Synchronize from date
	 * @param	int		$to_date						Synchronize to date
	 * @param	array	$remote_ids						List of order remote id to synchronize
	 * @param	int		$toNb							Max nb
	 * @param	bool	$success_log					Keep success log
	 * @param	bool	$dont_synchronize_products		Bypass the synchronization of the product of the order
	 * @return	int										<0 if KO, >0 if OK
	 */
	public function synchronizeOrders($from_date = null, $to_date = null, $remote_ids = array(), $toNb = 0, $success_log = true, $dont_synchronize_products = false)
	{
		dol_syslog(__METHOD__ . ' remote_ids=' . json_encode($remote_ids) . ', toNb=' . $toNb, LOG_DEBUG);

		$this->error = '';
		$this->errors = array();
		$this->warnings = array();
		$error = 0;
		$nb_synchronized = 0;

		if (!is_array($remote_ids)) {
			$this->langs->load('errors');
			$this->errors[] = $this->langs->trans('ErrorBadParameters') . '; remote_ids=' . json_encode($remote_ids);
			$error++;
		}

		if (!$error) {
			try {
				// Get list of customer data to synchronize
				$orders_data = $this->eCommerceRemoteAccess->convertRemoteObjectIntoDolibarrCommande($from_date, $to_date, $remote_ids, $toNb);
				if ($orders_data === false) {
					$this->errors = array_merge($this->errors, $this->eCommerceRemoteAccess->errors);
					$error++;
				}

				if (!$error && !empty($orders_data)) {
					foreach ($orders_data as $order_data) {
						// Synchronize the order
						$result = $this->synchronizeOrder($order_data, $dont_synchronize_products);
						if ($result < 0) {
							$error++;
							break;
						} else {
							$nb_synchronized++;
						}
					}
				}
			} catch (Exception $e) {
				$this->errors[] = $e->getMessage();
				$error++;
			}
		}

		if ($error) {
			$this->errors = array_merge(array($this->langs->trans('ECommerceErrorWhenSynchronizeOrders')), $this->errors);
			if ($success_log && $nb_synchronized) $this->success[] = $this->langs->trans('ECommerceSynchronizeOrdersSuccess', $nb_synchronized);
			return -1 - $nb_synchronized;
		} else {
			if ($success_log) $this->success[] = $this->langs->trans('ECommerceSynchronizeOrdersSuccess', $nb_synchronized);
			return $nb_synchronized;
		}
	}

	/**
	 * Check if order exist in dolibarr from the remote order data or id
	 *
	 * @param	int		$order_remote_id	Order Id
	 * @param	array	$order_data			Order data
	 * @return	int							<0 if KO, =0 if not found, Id of the order in Dolibarr if OK
	 */
	public function isOrderExist($order_remote_id = 0, $order_data = array())
	{
		dol_syslog(__METHOD__ . ' order_remote_id=' . $order_remote_id . ' order_data=' . json_encode($order_data), LOG_DEBUG);

		$this->error = '';
		$this->errors = array();
		$error = 0;
		$order_id = 0;

		if (!($order_remote_id > 0) && (empty($order_data) || !is_array($order_data))) {
			$this->langs->load('errors');
			$this->errors[] = $this->langs->trans('ErrorBadParameters') . '; order_remote_id=' . $order_remote_id . '; order_data=' . json_encode($order_data);
			$error++;
		}

		if (!$error) {
			try {
				$order_remote_id = $order_remote_id > 0 ? $order_remote_id : $order_data['remote_id']; // todo attention peu arriver =>>> remote_id != $order_data['ref_client'];

				$this->initECommerceCommande();

				// Check if order already synchronized
				$result = $this->eCommerceCommande->fetchByRemoteId($order_remote_id, $this->eCommerceSite->id);
				if ($result < 0 && !empty($this->eCommerceCommande->error)) {
					$this->errors[] = $this->langs->trans('ECommerceErrorFetchOrderLinkByRemoteId', $order_remote_id, $this->eCommerceSite->id);
					$this->errors[] = $this->eCommerceCommande->error;
					$error++;
				} else {
					$order_id = $this->eCommerceCommande->fk_commande;
				}

				// Fetch order
				if (!$error && !($order_id > 0)) {
					require_once DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php';
					$order = new Commande($this->db);

					$order_ref_ext = $this->eCommerceSite->name . '-' . $order_remote_id;

					// Fetch order by ref_ext if already created but the link is deleted
					$result = $order->fetch(0, '', $order_ref_ext);
					if ($result < 0) {
						$this->errors[] = $this->langs->trans('ECommerceErrorFetchOrderByRefExt', $order_ref_ext);
						if (!empty($order->error)) $this->errors[] = $order->error;
						$this->errors = array_merge($this->errors, $order->errors);
						$error++;
					} else {
						$order_id = $order->id;
					}

					unset($order);
				}
			} catch (Exception $e) {
				$this->errors[] = $e->getMessage();
				$error++;
			}
		}

		if ($error) {
			$this->errors = array_merge(array($this->langs->trans('ECommerceErrorWhenCheckOrderExist', $order_remote_id)), $this->errors);
			dol_syslog(__METHOD__ . ' Error=' . $this->errorsToString(), LOG_ERR);
			return -1;
		} else {
			return $order_id > 0 ? $order_id : 0;
		}
	}

	/**
	 * Synchronize a order data in the order in Dolibarr database
	 *
	 * @param	array	$order_data						Order data to synchronize
	 * @param	bool	$dont_synchronize_products		Bypass the synchronization of the product of the order
	 * @return	int										<0 if KO, =0 if bypassed, Id of the order in Dolibarr if OK
	 */
	public function synchronizeOrder($order_data, $dont_synchronize_products = false)
	{
		dol_syslog(__METHOD__ . ' order_data=' . json_encode($order_data), LOG_DEBUG);
		global $conf;

		$this->error = '';
		$this->errors = array();
		$this->warnings = array();
		$error = 0;

        $infV14 = (version_compare(DOL_VERSION, "14.0.0") < 0);

		require_once DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php';
		$order = new Commande($this->db);

		if (!is_array($order_data)) {
			$this->langs->load('errors');
			$this->errors[] = $this->langs->trans('ErrorBadParameters') . '; order_data=' . json_encode($order_data);
			$error++;
		}

		if (!$error) {
			$order_ref_ext = $this->eCommerceSite->name . '-' . $order_data['remote_id'];
			$order_id = 0;
			$third_party_id = 0;
			$selected_payment_gateways = null;
			$payment_method_id = 0;
			$bypass = false;

			$this->db->begin();

			try {
				if (!empty($order_data) && !empty($this->eCommerceSite->parameters['order_actions']['create_order'])) {
					$this->initECommerceCommande();

					// Check if order already synchronized
					$result = $this->eCommerceCommande->fetchByRemoteId($order_data['remote_id'], $this->eCommerceSite->id);
					if ($result < 0 && !empty($this->eCommerceCommande->error)) {
						$this->errors[] = $this->langs->trans('ECommerceErrorFetchOrderLinkByRemoteId', $order_data['remote_id'], $this->eCommerceSite->id);
						$this->errors[] = $this->eCommerceCommande->error;
						$error++;
					} else {
						$order_id = $this->eCommerceCommande->fk_commande;
					}

					// Need to synchronize ?
					$bypass = $order_data['create_date'] < $this->eCommerceSite->parameters['order_first_date_etod'] ||
						(!($this->eCommerceCommande->id > 0) && empty($order_data['synchronize'])) ||
						($this->eCommerceCommande->id > 0 && !empty($this->eCommerceCommande->last_update) && strtotime($order_data['last_update']) <= strtotime($this->eCommerceCommande->last_update));

					// Bypass the synchronization ?
					if (!$error && !$bypass) {
						// Check if third party already synchronized
						if ($order_data['remote_id_societe'] > 0) {
							$this->initECommerceSociete();
							// Synchronize the customer
							$result = $this->synchronizeCustomers(null, null, array($order_data['remote_id_societe']), 1, false, false, true);
							if ($result < 0) {
								$error++;
							} elseif ($result > 0) {
								$result = $this->eCommerceSociete->fetchByRemoteId($order_data['remote_id_societe'], $this->eCommerceSite->id);
								if ($result < 0 && !empty($this->eCommerceSociete->error)) {
									$this->errors[] = $this->langs->trans('ECommerceErrorFetchThirdPartyLinkByRemoteId', $order_data['remote_id_societe'], $this->eCommerceSite->id);
									$this->errors[] = $this->eCommerceSociete->error;
									$error++;
								} elseif ($result > 0) {
									$third_party_id = $this->eCommerceSociete->fk_societe;
								} else {
									// Customer not supported (eg. order created by admin so we don't need it)
									$third_party_id = null;
								}
							} else {
								// Customer not supported (eg. order created by admin so we don't need it)
								$third_party_id = null;
							}
						}
						if (!$error && $third_party_id === 0) {
							// This is an guest customer.
							if ($this->eCommerceSite->fk_anonymous_thirdparty > 0) {
								$third_party_id = $this->eCommerceSite->fk_anonymous_thirdparty;
							} else {
								$this->errors[] = $this->langs->trans('ECommerceErrorAnonymousThirdPartyNotConfigured', $this->eCommerceSite->id);
								$error++;
							}
						}

						// Create the order only if the third party ID is found (otherwise it's bypassed)
						if (!$error) {
							if (isset($third_party_id)) {
								// Fetch order
								if (!$error) {
									if (!($order_id > 0)) {
										// Fetch order by ref_ext if already created but the link is deleted
										$result = $order->fetch(0, '', $order_ref_ext);
										if ($result < 0) {
											$this->errors[] = $this->langs->trans('ECommerceErrorFetchOrderByRefExt', $order_ref_ext);
											if (!empty($order->error)) $this->errors[] = $order->error;
											$this->errors = array_merge($this->errors, $order->errors);
											$error++;
										}
									} elseif ($order_id > 0) {
										$result = $order->fetch($order_id);
										if ($result < 0) {
											$this->errors[] = $this->langs->trans('ECommerceErrorFetchOrder', $order_id);
											if (!empty($order->error)) $this->errors[] = $order->error;
											$this->errors = array_merge($this->errors, $order->errors);
											$error++;
										}
									}
								}

                                // Set third party language
                                //---------------------------
                                if (!$error) {
                                    $third_party = new Societe($this->db);
                                    $third_party->fetch($third_party_id);
                                    if (empty($third_party->default_lang) && !empty($order_data['language']) && $order_data['language'] != 'ec_none') {
                                        $third_party->default_lang = $order_data['language'];

                                        $result = $third_party->update($third_party->id, $this->user);
                                        if ($result < 0) {
                                            $this->errors[] = $this->langs->trans('ECommerceErrorUpdateThirdPartyLanguage', $third_party->id);
                                            $this->errors[] = $third_party->errorsToString();
                                            $error++;
                                        }
                                    }
                                }

								// Set the order
								//---------------------------
								if (!$error) {
									if ($order->id > 0) $order->oldcopy = clone $order;

									$order->socid = $third_party_id;
									$order->ref_client = $order_data['ref_client'];
									$order->ref_ext = $order_ref_ext;
									$order->date = strtotime($order_data['date_commande']);
									$order->date_commande = strtotime($order_data['date_commande']);
									$order->date_livraison = strtotime($order_data['date_livraison']);

									if (!($order->id > 0)) {
										$order->array_options = $this->getDefaultExtraFields($order->table_element, $this->eCommerceSite);
										if (!empty($conf->global->THIRDPARTY_PROPAGATE_EXTRAFIELDS_TO_ORDER) && $order->socid > 0 && $order->fetch_thirdparty() > 0) {
											// Only on create
											foreach ($order->thirdparty->array_options as $key => $value) {
												$order->array_options[$key] = $value;
											}
										}
									}

									if (is_array($order_data['extrafields'])) {
										foreach ($order_data['extrafields'] as $key => $value) {
											$order->array_options['options_' . $key] = $value;
										}
									}

									$order->context['fromsyncofecommerceid'] = $this->eCommerceSite->id;

									// Update order
									if ($order->id > 0) {
										$new_order = false;
										if (!empty($conf->global->ECOMMERCENG_ENABLE_LOG_IN_NOTE)) {
											$order->note_private = dol_concatdesc($order->note_private, $this->langs->trans('ECommerceUpdateOrderFromSiteNote', dol_print_date(dol_now(), 'dayhour'), $this->eCommerceSite->name, $order_data['remote_id']));
											if (!empty($conf->global->ECOMMERCENG_ENABLE_DETAILED_UPDATE_LOG_IN_NOTE)) {
												$order->note_private = dol_concatdesc($order->note_private . " :", json_encode($order_data['remote_order']));
											}
										}

										$order->error = '';
										$order->errors = array();
										$result = $order->update($this->user);
										if ($result < 0) {
											$this->errors[] = $this->langs->trans('ECommerceErrorUpdateOrder', $order->id);
										}
									} // Create order
									else {
										$new_order = true;
										$order->statut = Commande::STATUS_DRAFT;             // STATUS_DRAFT by default at creation
										$order->cond_reglement_id = $third_party->cond_reglement_id > 0 ? $third_party->cond_reglement_id : (isset($this->eCommerceSite->parameters['payment_cond']) ? $this->eCommerceSite->parameters['payment_cond'] : null);
										$order->source = dol_getIdFromCode($this->db, 'OrderByWWW', 'c_input_method', 'code', 'rowid'); // Order mode. Not visible with some Dolibarr versions
										$order->note_private = "";
										if (isset($order_data['note'])) {
											if (!empty($this->eCommerceSite->parameters['order_actions']['order_note_into_public_note'])) {
												$order->note_public = $order_data['note'];
											} else {
												$order->note_private = $order_data['note'];
											}
										}
										if (!empty($conf->global->ECOMMERCENG_ENABLE_LOG_IN_NOTE)) {
											$order->note_private = dol_concatdesc($order->note_private, $this->langs->trans('ECommerceCreateOrderFromSiteNote', $this->eCommerceSite->name) . " :\n" . json_encode($order_data['remote_order']));
										}

										$order->error = '';
										$order->errors = array();
										$result = $order->create($this->user);
										if ($result < 0) {
											$this->errors[] = $this->langs->trans('ECommerceErrorCreateOrder');
										}
									}
									if ($result < 0) {
										if (!empty($order->error)) $this->errors[] = $order->error;
										$this->errors = array_merge($this->errors, $order->errors);
										if (empty($order->error) && empty($order->errors)) $this->errors[] = $this->db->lasterror();
										$error++;
									}

									// Add / update contacts
									if (!$error) {
										$result = $this->addContacts($order, $order_data, $new_order);
										if ($result < 0) {
											$error++;
										}
									}

									// Add product line if new created
									$warehouseByLine = array();
									if (!$error && is_array($order_data['items'])) {
										if ($new_order) {
											$result = $this->addProductLines($order, $order_data['items'], $order_data, $warehouseByLine, $dont_synchronize_products);
											if ($result < 0) {
												$error++;
											}
										} else {
											foreach ($order->lines as $line) {
												// Get import key of the line
												$import_key = dol_getIdFromCode($this->db, $line->id, $order->table_element_line, 'rowid', 'import_key');
												if ($import_key == -1) {
													$this->errors[] = $this->langs->trans('ECommerceErrorGetImportKeyOfLine', $line->id, $order->ref);
													$this->errors[] = $this->db->lasterror();
													$error++;
													break;  // break on items
												}

												// Support movement stock different by product
												if ($line->fk_product > 0 && !empty($import_key) && !empty($order_data['items'][$import_key]['remote_warehouse_id']) && $order_data['items'][$import_key]['remote_warehouse_id'] > 0) {
													$warehouseByLine[$line->id] = $order_data['items'][$import_key]['remote_warehouse_id'];
												}
											}
										}
									}

									// Force set amount if another amount paid
									if (!$error) {
										$result = $this->fixTotalAmounts($order, $order_data['payment_amount_ttc']);
										if ($result < 0) {
											$error++;
										}
									}

									// Get payment gateways
									if (!$error && !empty($order_data['payment_method_id'])) {
										$result = $this->loadPaymentGateways();
										if ($result < 0) {
											$error++;
										} else {
											if (isset($this->payment_gateways_cached[$order_data['payment_method_id']])) {
												$selected_payment_gateways = $this->payment_gateways_cached[$order_data['payment_method_id']];
											} else {
												$this->errors[] = $this->langs->trans('ECommerceErrorPaymentGatewaysNotFound', $order_data['payment_method_id'], $order_data['payment_method']);
												$error++;
											}
										}
									}

									// Search payment mode by ID if payment gateways found
									if (!$error && isset($selected_payment_gateways)) {
										if ($selected_payment_gateways['payment_mode_id'] > 0) {
											$payment_method_id = $selected_payment_gateways['payment_mode_id'];
										} else {
											$this->errors[] = $this->langs->trans('ECommerceErrorPaymentGatewaysPaymentMethodNotConfigured', $order_data['payment_method_id'], $order_data['payment_method']);
											$error++;
										}
									}

									// Search payment mode by label
									if (!$error && !($payment_method_id > 0) && !empty($order_data['payment_method'])) {
										require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
										$result = dol_getIdFromCode($this->db, $order_data['payment_method'], 'c_paiement', 'libelle', 'id');
										if ($result < 0) {
											$this->errors[] = $this->langs->trans('ECommerceErrorWhenFetchPaymentMethodByLabel', $order_data['payment_method']);
											$this->errors[] = $this->db->lasterror();
											$error++;
										} elseif ($result != '' && $result > 0) {
											$payment_method_id = $result;
										}
									}

									// Set payment mode
									$payment_methods_already_set = false;
									if (!$error && $payment_method_id > 0 && $order->statut >= 0) {
										$result = $order->setPaymentMethods($payment_method_id);
										if ($result < 0) {
											$this->errors[] = $this->langs->trans('ECommerceErrorOrderSetPaymentMethods');
											if (!empty($order->error)) $this->errors[] = $order->error;
											$this->errors = array_merge($this->errors, $order->errors);
											$error++;
										} else {
											$payment_methods_already_set = true;
										}
									}

									// Update the order status
									if (!$error && ($new_order || ($order->statut != $order_data['status']))) {        // Always when creating
										$warehouse_id = $this->eCommerceSite->parameters['order_actions']['valid_order_fk_warehouse'] > 0 && empty($warehouseByLine) ? $this->eCommerceSite->parameters['order_actions']['valid_order_fk_warehouse'] : 0;
										if (empty($warehouse_id) && empty($warehouseByLine) && !empty($conf->global->STOCK_CALCULATE_ON_VALIDATE_ORDER) && $conf->global->STOCK_CALCULATE_ON_VALIDATE_ORDER == 1) {
											$this->errors[] = $this->langs->trans('ECommerceErrorValidOrderWarehouseNotConfigured', $this->eCommerceSite->name);
											$error++;
										}

										// Valid the order if the distant order is not at the draft status but the order is draft. For set the order ref.
										if (!$error && $order_data['status'] != Commande::STATUS_DRAFT && $order->statut == Commande::STATUS_DRAFT) {
											$result = $order->valid($this->user, $warehouse_id);
											if ($result < 0) {
												$this->errors[] = $this->langs->trans('ECommerceErrorValidOrder');
												if (!empty($order->error)) $this->errors[] = $order->error;
												$this->errors = array_merge($this->errors, $order->errors);
												$error++;
											} else {
												$result = $this->setLinesMovementStockOnDifferentWarehouse($order, $warehouseByLine);
												if ($result < 0) {
													$this->errors[] = $this->langs->trans("ECommerceErrorWhenMovementStockOnDifferentWarehouse");
													$error++;
												}
											}
										}

										// Update to the specific status
										if (!$error) {
											if ($order_data['status'] == Commande::STATUS_VALIDATED && $order->statut != Commande::STATUS_VALIDATED) {
												$result = $order->setStatut(Commande::STATUS_VALIDATED, $order->id, $order->table_element);
												if ($result < 0) {
													$this->errors[] = $this->langs->trans('ECommerceErrorSetStatusOrderValidated');
													if (!empty($order->error)) $this->errors[] = $order->error;
													$this->errors = array_merge($this->errors, $order->errors);
													$error++;
												}
											} elseif ($order_data['status'] == Commande::STATUS_SHIPMENTONPROCESS && $order->statut != Commande::STATUS_SHIPMENTONPROCESS) {
												$result = $order->setStatut(2, $order->id, $order->table_element);
												if ($result < 0) {
													$this->errors[] = $this->langs->trans('ECommerceErrorSetStatusOrderShipmentOnProcess');
													if (!empty($order->error)) $this->errors[] = $order->error;
													$this->errors = array_merge($this->errors, $order->errors);
													$error++;
												}
											} elseif ($order_data['status'] == Commande::STATUS_CANCELED && $order->statut != Commande::STATUS_CANCELED) {
												$result = $order->cancel($warehouse_id);
												if ($result < 0) {
													$this->errors[] = $this->langs->trans('ECommerceErrorCancelOrder');
													if (!empty($order->error)) $this->errors[] = $order->error;
													$this->errors = array_merge($this->errors, $order->errors);
													$error++;
												} else {
													$result = $this->setLinesMovementStockOnDifferentWarehouse($order, $warehouseByLine, true);
													if ($result < 0) {
														$this->errors[] = $this->langs->trans("ECommerceErrorWhenMovementStockOnDifferentWarehouse");
														$error++;
													}
												}
											} elseif ($order_data['status'] == Commande::STATUS_CLOSED) {
												if ($order->statut != Commande::STATUS_CLOSED) {
													$result = $order->cloture($this->user);
													if ($result < 0) {
														$this->errors[] = $this->langs->trans('ECommerceErrorCloseOrder');
														if (!empty($order->error)) $this->errors[] = $order->error;
														$this->errors = array_merge($this->errors, $order->errors);
														$error++;
													}
												}
												// Classify billed
												$isDepositType = isset($this->eCommerceSite->parameters['create_invoice_type']) && $this->eCommerceSite->parameters['create_invoice_type'] == Facture::TYPE_DEPOSIT;
												if (!$error && empty($order->billed) && $order_data['billed'] == 1 && !$isDepositType) {
													$result = $order->classifyBilled($this->user);
													if ($result < 0) {
														$this->errors[] = $this->langs->trans('ECommerceErrorClassifyOrderBilled');
														if (!empty($order->error)) $this->errors[] = $order->error;
														$this->errors = array_merge($this->errors, $order->errors);
														$error++;
													}
												}
											}
										}
									}

									// Set payment mode
									if (!$error && !$payment_methods_already_set && $payment_method_id > 0 && $order->statut >= 0) {
										$result = $order->setPaymentMethods($payment_method_id);
										if ($result < 0) {
											$this->errors[] = $this->langs->trans('ECommerceErrorOrderSetPaymentMethods');
											if (!empty($order->error)) $this->errors[] = $order->error;
											$this->errors = array_merge($this->errors, $order->errors);
											$error++;
										}
									}
								}
							} else {
								$this->warnings[] = $this->langs->trans('ECommerceWarningOrderThirdPartyNotSupported', $order_data['remote_id'], $order_data['remote_id_societe']);
								$bypass = true;
							}
						}
					}

					// Update the link of the synchronization
					//--------------------------------------------
					if (!$error && !$bypass) {
						$this->eCommerceCommande->last_update = $order_data['last_update'];
						$this->eCommerceCommande->fk_commande = $order->id > 0 ? $order->id : 0;

						// Update link
						if ($this->eCommerceCommande->id > 0) {
							$result = $this->eCommerceCommande->update($this->user);
							if ($result < 0) {
								$this->errors[] = $this->langs->trans('ECommerceErrorUpdateOrderLink');
							}
						} // Create link
						else {
							$this->eCommerceCommande->fk_site = $this->eCommerceSite->id;
							$this->eCommerceCommande->remote_id = $order_data['remote_id'];
							$result = $this->eCommerceCommande->create($this->user);
							if ($result < 0) {
								$this->errors[] = $this->langs->trans('ECommerceErrorCreateOrderLink');
							}
						}
						if ($result < 0) {
							$this->errors = array_merge($this->errors, $this->eCommerceCommande->errors);
							$error++;
						}
					}

					// Generate PDF
					if (!$error && !$bypass && $order->id > 0 && empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE)) {
						// Define output language
						$outputlangs = $this->langs;
						$newlang = '';
						if ($conf->global->MAIN_MULTILANGS && !empty($order_data['language']) && $order_data['language'] != 'ec_none') $newlang = $order_data['language'];
						if (!empty($newlang)) {
							$outputlangs = new Translate("", $conf);
							$outputlangs->setDefaultLang($newlang);
							$outputlangs->load('products');
						}
						$order->fetch($order->id); // Reload to get new records
						$order->fetch_thirdparty();

						$hidedetails = !empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DETAILS) ? 1 : 0;
						$hidedesc = !empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DESC) ? 1 : 0;
						$hideref = !empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_REF) ? 1 : 0;

						$result = $order->generateDocument($order->modelpdf, $outputlangs, $hidedetails, $hidedesc, $hideref);
					}

					$last_sync_date = 'ECOMMERCE_LAST_SYNC_DATE_ORDER_' . $this->eCommerceSite->id;
					if (!$error && (!is_numeric($conf->global->$last_sync_date) || $conf->global->$last_sync_date < $order_data['create_date'])) {
						$result = dolibarr_set_const($this->db, $last_sync_date, $order_data['create_date'], 'chaine', 0, '', $conf->entity);
						if ($result < 0) {
							$this->errors[] = $this->langs->trans('ECommerceErrorSetLastSyncDateOrder');
							$this->errors[] = $this->db->lasterror();
							$error++;
						}
					}
				}
			} catch (Exception $e) {
				$this->errors[] = $e->getMessage();
				$error++;
			}

			if (!$error && !$bypass) {
				$result = $this->synchronizeInvoiceFromOrder($order_data, $dont_synchronize_products);
				if ($result < 0) {
					$error++;
				}
			}

			if (!$error && !$bypass) {
				$result = $this->synchronizeInvoiceRefundsFromOrder($order_data, $dont_synchronize_products);
				if ($result < 0) {
					$error++;
				}
			}

			// Commit / rollback actions
			if ($error) {
				$this->db->rollback();
			} else {
				$this->db->commit();
			}
		}

		if ($error) {
			$this->errors = array_merge(array($this->langs->trans('ECommerceErrorWhenSynchronizeOrder', $order_data['remote_id'])), $this->errors);
			dol_syslog(__METHOD__ . ' Error=' . $this->errorsToString(), LOG_ERR);
			return -1;
		} else {
			return $order->id > 0 ? $order->id : 0;
		}
	}

	/**
	 * Set movement stock for each product line on different warehouse
	 *
	 * @param	CommonObject	$object				Object handler
	 * @param	array			$warehouseByLine	List of warehouse ID for each line
	 * @param	boolean			$undoMovement		True to undo movements
	 * @return	int									<0 if KO, >0 if OK
	 */
	public function setLinesMovementStockOnDifferentWarehouse($object, $warehouseByLine, $undoMovement = false)
	{
		global $conf;

		$canMakeMovement = !empty($warehouseByLine) && !empty($conf->stock->enabled);
		if ($object->element == 'commande') {
			$canMakeMovement &= !empty($conf->global->STOCK_CALCULATE_ON_VALIDATE_ORDER) && $conf->global->STOCK_CALCULATE_ON_VALIDATE_ORDER == 1;
			$movementLabel = $this->langs->trans($undoMovement ? "OrderCanceledInDolibarr" : "OrderValidatedInDolibarr", $object->ref);
			$movementDelivery = empty($undoMovement);
		} elseif ($object->element == 'facture') {
			$canMakeMovement &= $object->type != Facture::TYPE_DEPOSIT && !empty($conf->global->STOCK_CALCULATE_ON_BILL);
			$movementLabel = $this->langs->trans($undoMovement ? "InvoiceBackToDraftInDolibarr" : "InvoiceValidatedInDolibarr", $object->ref);
			$movementDelivery = empty($undoMovement) && $object->type != Facture::TYPE_CREDIT_NOTE;
		} else {
			$this->errors[] = $this->langs->trans("ECommerceErrorElementNotSupportedForLinesMovementStockOnDifferentWarehouse", $object->element);
			return -1;
		}

		// If stock can be incremented/decremented
		if ($canMakeMovement) {
			// Get all payment gateways
			dol_include_once('/ecommerceng/class/data/eCommerceRemoteWarehouses.class.php');
			$remote_warehouses = new eCommerceRemoteWarehouses($this->db);
			$remoteWarehousesList = $remote_warehouses->get_all($this->eCommerceSite->id);
			if (!is_array($remoteWarehousesList) && $remoteWarehousesList < 0) {
				$this->errors[] = $remote_warehouses->errorsToString();
				return -1;
			}

			require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';
			$this->langs->load("agenda");

			$error = 0;
			$this->db->begin();
			foreach ($object->lines as $line) {
				if ($line->fk_product > 0) {
					$remoteWarehouseId = isset($warehouseByLine[$line->id]) && $warehouseByLine[$line->id] > 0 ? $warehouseByLine[$line->id] : 0;
					if (empty($remoteWarehouseId) && $line->product_type == Product::TYPE_PRODUCT) {
						$this->errors[] = $this->langs->trans("ECommerceErrorWarehouseIdNotSpecifiedForTheLine", $line->fk_product);
						$error++;
						break;
					}

					$warehouseId = isset($remoteWarehousesList[$remoteWarehouseId]['warehouse_id']) && $remoteWarehousesList[$remoteWarehouseId]['warehouse_id'] > 0 ? $remoteWarehousesList[$remoteWarehouseId]['warehouse_id'] : 0;
					if ($remoteWarehouseId > 0 && empty($warehouseId)) {
						dol_syslog(__METHOD__ . ' Warehouse not configured for remote warehouse ID ' . $remoteWarehouseId . ' so we don\'t process this remote warehouse', LOG_WARNING);
						continue;
//						$this->errors[] = $this->langs->trans("ECommerceErrorWarehouseIdNotConfiguredForRemoteWarehouse", $remoteWarehouseId, $this->eCommerceSite->name);
//						$error++;
//						break;
					}

					$mouvP = new MouvementStock($this->db);
					$mouvP->origin = &$object;
					if ($movementDelivery) {
						// We decremented stock of product (and sub-products)
						$result = $mouvP->livraison($this->user, $line->fk_product, $warehouseId, $line->qty, $line->subprice, $movementLabel);
					} else {
						// We increment stock of product (and sub-products)
						$result = $mouvP->reception($this->user, $line->fk_product, $warehouseId, $line->qty, 0, $movementLabel); // price is 0, we don't want WAP to be changed
					}
					if ($result < 0) {
						$this->errors[] = $mouvP->errorsToString();
						$error++;
						break;
					}
				}
			}

			if ($error) {
				$this->db->rollback();
				return -1;
			} else {
				$this->db->commit();
			}
		}

		return 1;
	}

	/**
	 * Synchronize a order data in the invoice in Dolibarr database
	 *
	 * @param	array	$order_data						Order data to synchronize
	 * @param	bool	$dont_synchronize_products		Bypass the synchronization of the product of the order
	 * @return	int										<0 if KO, =0 if bypassed, Id of the invoice in Dolibarr if OK
	 */
	public function synchronizeInvoiceFromOrder($order_data, $dont_synchronize_products = false)
	{
		dol_syslog(__METHOD__ . ' order_data=' . json_encode($order_data), LOG_DEBUG);
		global $conf;

		$this->error = '';
		$this->errors = array();
		$error = 0;

		$infV14 = (version_compare(DOL_VERSION, "14.0.0") < 0);

		require_once DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php';
		require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
		$order = new Commande($this->db);
		$invoice = new Facture($this->db);

		if (!is_array($order_data)) {
			$this->langs->load('errors');
			$this->errors[] = $this->langs->trans('ErrorBadParameters') . '; order_data=' . json_encode($order_data);
			$error++;
		}

		if (!$error && !empty($order_data)) {
			$order_ref_ext = $this->eCommerceSite->name . '-' . $order_data['remote_id'];
			$invoice_ref_ext = 'eCommerce-' . $this->eCommerceSite->id . '-' . $order_data['remote_id'];
			$order_id = 0;
			$third_party_id = 0;
			$selected_payment_gateways = null;
			$payment_method_id = 0;
			$bank_account_id = 0;

			$this->db->begin();

			try {
				// Fetch invoice by ref_ext
				$result = $invoice->fetch(0, '', $invoice_ref_ext);
				if ($result < 0) {
					$this->error = $invoice->error;
					$this->errors = array_merge($this->errors, $invoice->errors);
					$error++;
				} elseif (!empty($this->eCommerceSite->parameters['order_actions']['create_invoice']) && !empty($order_data['billed']) &&
					(!empty($this->eCommerceSite->parameters['order_actions']['create_invoice_if_amount_0']) || price2num($order_data['payment_amount_ttc']) != 0)
				) {
					// Check if order / invoice already synchronized
					$this->initECommerceCommande();
					$result = $this->eCommerceCommande->fetchByRemoteId($order_data['remote_id'], $this->eCommerceSite->id);
					if ($result < 0 && !empty($this->eCommerceCommande->error)) {
						$this->errors[] = $this->langs->trans('ECommerceErrorFetchOrderLinkByRemoteId', $order_data['remote_id'], $this->eCommerceSite->id);
						$this->errors[] = $this->eCommerceCommande->error;
						$error++;
					} else {
						if ($this->eCommerceCommande->fk_commande > 0) {
							$order_id = $this->eCommerceCommande->fk_commande;
						}
					}

					// Fetch order
					if (!$error) {
						if (!($order_id > 0)) {
							// Fetch order by ref_ext if already created but the link is deleted
							$result = $order->fetch(0, '', $order_ref_ext);
							if ($result < 0) {
								$this->errors[] = $this->langs->trans('ECommerceErrorFetchOrderByRefExt', $order_ref_ext);
								if (!empty($order->error)) $this->errors[] = $order->error;
								$this->errors = array_merge($this->errors, $order->errors);
								$error++;
							}
						} elseif ($order_id > 0) {
							$result = $order->fetch($order_id);
							if ($result < 0) {
								$this->errors[] = $this->langs->trans('ECommerceErrorFetchOrder', $order_id);
								if (!empty($order->error)) $this->errors[] = $order->error;
								$this->errors = array_merge($this->errors, $order->errors);
								$error++;
							}
						}
					}

					// Fetch linked invoice of the order
					if (!$error && !($invoice->id > 0) && $order->id > 0) {
						$result = $order->fetchObjectLinked();
						if ($result < 0) {
							$this->errors[] = $this->langs->trans('ECommerceErrorFetchObjectLinked');
							$this->errors[] = $order->errorsToString();
							$error++;
						}
						if (!empty($order->linkedObjects['facture'])) {
							$invoice_linked = array_values($order->linkedObjects['facture']);
							$invoice = $invoice_linked[0];
						}
					}

					if (!$error) {
						// Recreate the link to the order if not exist
						if ($invoice->id > 0) {
							if ($order->id > 0) $invoice->add_object_linked($order->element, $order->id);
						} // Create the invoice only the first time
						else {
							// Need to synchronize ?
							$bypass = $order_data['create_date'] < $this->eCommerceSite->parameters['order_first_date_etod'];

							if (!$bypass) {
								if ($order->id > 0) {
									$third_party_id = $order->socid;
								} else {
									// Check if third party already synchronized
									if ($order_data['remote_id_societe'] > 0) {
										$this->initECommerceSociete();
										// Synchronize the customer
										$result = $this->synchronizeCustomers(null, null, array($order_data['remote_id_societe']), 1, false, false, true);
										if ($result < 0) {
											$error++;
										} elseif ($result > 0) {
											$result = $this->eCommerceSociete->fetchByRemoteId($order_data['remote_id_societe'], $this->eCommerceSite->id);
											if ($result < 0 && !empty($this->eCommerceSociete->error)) {
												$this->errors[] = $this->langs->trans('ECommerceErrorFetchThirdPartyLinkByRemoteId', $order_data['remote_id_societe'], $this->eCommerceSite->id);
												$this->errors[] = $this->eCommerceSociete->error;
												$error++;
											} elseif ($result > 0) {
												$third_party_id = $this->eCommerceSociete->fk_societe;
											} else {
												// Customer not supported (eg. order created by admin so we don't need it)
												$third_party_id = null;
											}
										} else {
											// Customer not supported (eg. order created by admin so we don't need it)
											$third_party_id = null;
										}
									}
									if ($third_party_id === 0) {
										// This is an guest customer.
										if ($this->eCommerceSite->fk_anonymous_thirdparty > 0) {
											$third_party_id = $this->eCommerceSite->fk_anonymous_thirdparty;
										} else {
											$this->errors[] = $this->langs->trans('ECommerceErrorAnonymousThirdPartyNotConfigured', $this->eCommerceSite->id);
											$error++;
										}
									}
								}

								// Create the invoice only if the third party ID is found (otherwise it's bypassed)
								if (!$error) {
									if (isset($third_party_id)) {
										$third_party = new Societe($this->db);
										$third_party->fetch($third_party_id);
                                        // Set third party language
                                        if (empty($third_party->default_lang) && !empty($order_data['language']) && $order_data['language'] != 'ec_none') {
                                            $third_party->default_lang = $order_data['language'];

                                            $result = $third_party->update($third_party->id, $this->user);
                                            if ($result < 0) {
                                                $this->errors[] = $this->langs->trans('ECommerceErrorUpdateThirdPartyLanguage', $third_party->id);
                                                $this->errors[] = $third_party->errorsToString();
                                                $error++;
                                            }
                                        }

										$isDepositType = isset($this->eCommerceSite->parameters['create_invoice_type']) && $this->eCommerceSite->parameters['create_invoice_type'] == Facture::TYPE_DEPOSIT;
										$typeAmount = isset($this->eCommerceSite->parameters['order_actions']['create_invoice_deposit_type']) ? $this->eCommerceSite->parameters['order_actions']['create_invoice_deposit_type'] : '';
										$valueDeposit = isset($this->eCommerceSite->parameters['order_actions']['create_invoice_deposit_value']) ? $this->eCommerceSite->parameters['order_actions']['create_invoice_deposit_value'] : 0;

										// Set the invoice
										$invoice->socid = $third_party_id;
										$invoice->type = isset($this->eCommerceSite->parameters['create_invoice_type']) ? $this->eCommerceSite->parameters['create_invoice_type'] : Facture::TYPE_STANDARD;
										$invoice->date = isset($order_data['date_payment']) && $order_data['date_payment'] !== '' ? strtotime($order_data['date_payment']) : ($order->id > 0 ? $order->date_commande : strtotime($order_data['date_commande']));
										$invoice->ref_client = $order->id > 0 ? $order->ref_client : $order_data['ref_client'];
										$invoice->ref_ext = $invoice_ref_ext;
										$invoice->modelpdf = $conf->global->FACTURE_ADDON_PDF;
										$invoice->model_pdf = $conf->global->FACTURE_ADDON_PDF;
										$invoice->cond_reglement_id = $order->id > 0 ? $order->cond_reglement_id : ($third_party->cond_reglement_id > 0 ? $third_party->cond_reglement_id : (isset($this->eCommerceSite->parameters['payment_cond']) ? $this->eCommerceSite->parameters['payment_cond'] : null));
										$invoice->multicurrency_code = $order->id > 0 ? $order->multicurrency_code : null;
										$invoice->multicurrency_tx = $order->id > 0 ? $order->multicurrency_tx : null;
										$invoice->entity = $conf->entity;
										$invoice->statut = Facture::STATUS_DRAFT;

										$invoice->note_private = "";
										if (isset($order_data['note'])) {
											if (!empty($this->eCommerceSite->parameters['order_actions']['order_note_into_public_note'])) {
												$invoice->note_public = $order_data['note'];
											} else {
												$invoice->note_private = $order_data['note'];
											}
										}
										if (!empty($conf->global->ECOMMERCENG_ENABLE_LOG_IN_NOTE)) {
											$invoice->note_private = dol_concatdesc($invoice->note_private, $this->langs->trans('ECommerceCreateInvoiceFromSiteNote', $this->eCommerceSite->name) . " :\n" . json_encode($order_data['remote_order']));
										}

										if ($order->id > 0) {
											$invoice->linkedObjectsIds[$order->element] = $order->id;
										}

										$invoice->array_options = $this->getDefaultExtraFields($invoice->table_element, $this->eCommerceSite);
										if ($order->id > 0) {
											foreach ($order->array_options as $key => $value) {
												$invoice->array_options[$key] = $value;
											}
										} elseif (is_array($order_data['extrafields'])) {
											foreach ($order_data['extrafields'] as $key => $value) {
												$invoice->array_options['options_' . $key] = $value;
											}
										}

										$invoice->context['fromsyncofecommerceid'] = $this->eCommerceSite->id;

										// Create invoice
										$result = $invoice->create($this->user);
										if ($result < 0) {
											$this->errors[] = $this->langs->trans('ECommerceErrorInvoiceCreate');
											if (!empty($invoice->error)) $this->errors[] = $invoice->error;
											$this->errors = array_merge($this->errors, $invoice->errors);
											$error++;
										}

										// Add / update contacts
										if (!$error) {
											$result = $this->addContacts($invoice, $order_data, true);
											if ($result < 0) {
												$error++;
											}
										}

										// Add product line if new created
										$warehouseByLine = array();
										if (!$error && is_array($order_data['items'])) {
											$result = $this->addProductLines($invoice, $order_data['items'], $order_data, $warehouseByLine, $order->id > 0 || $dont_synchronize_products);
											if ($result < 0) {
												$error++;
											}
										}

										// Force set amount if another amount paid
										if (!$error) {
											$result = $this->fixTotalAmounts($invoice, $order_data['payment_amount_ttc']);
											if ($result < 0) {
												$error++;
											}
										}

										// Get payment gateways
										if (!$error && !empty($order_data['payment_method_id'])) {
											$result = $this->loadPaymentGateways();
											if ($result < 0) {
												$error++;
											} else {
												if (isset($this->payment_gateways_cached[$order_data['payment_method_id']])) {
													$selected_payment_gateways = $this->payment_gateways_cached[$order_data['payment_method_id']];
												} else {
													$this->errors[] = $this->langs->trans('ECommerceErrorPaymentGatewaysNotFound', $order_data['payment_method_id'], $order_data['payment_method']);
													$error++;
												}
											}
										}

										// Search payment mode by ID if payment gateways found
										if (!$error && isset($selected_payment_gateways)) {
											if ($selected_payment_gateways['payment_mode_id'] > 0) {
												$payment_method_id = $selected_payment_gateways['payment_mode_id'];
											} else {
												$this->errors[] = $this->langs->trans('ECommerceErrorPaymentGatewaysPaymentMethodNotConfigured', $order_data['payment_method_id'], $order_data['payment_method']);
												$error++;
											}
										}

										// Search payment mode by label
										if (!$error && !($payment_method_id > 0) && !empty($order_data['payment_method'])) {
											require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
											$result = dol_getIdFromCode($this->db, $order_data['payment_method'], 'c_paiement', 'libelle', 'id');
											if ($result < 0) {
												$this->errors[] = $this->langs->trans('ECommerceErrorWhenFetchPaymentMethodByLabel', $order_data['payment_method']);
												$this->errors[] = $this->db->lasterror();
												$error++;
											} elseif ($result != '' && $result > 0) {
												$payment_method_id = $result;
											}
										}

										// Set payment mode
										if (!$error && $payment_method_id > 0) {
											$result = $invoice->setPaymentMethods($payment_method_id);
											if ($result < 0) {
												$this->errors[] = $this->langs->trans('ECommerceErrorInvoiceSetPaymentMethods');
												if (!empty($invoice->error)) $this->errors[] = $invoice->error;
												$this->errors = array_merge($this->errors, $invoice->errors);
												$error++;
											}
										}

										// Set bank account
										$bank_account_id = empty($order_data['extrafields']["ecommerceng_online_payment_{$conf->entity}"]) && $third_party->fk_account > 0 ? $third_party->fk_account : (!empty($selected_payment_gateways['bank_account_id']) ? $selected_payment_gateways['bank_account_id'] : 0);
										if (!$error) {
											if ($bank_account_id == 0 && $conf->banque->enabled && (!empty($selected_payment_gateways['create_invoice_payment']) || !empty($selected_payment_gateways['create_supplier_invoice_payment']))) {
												$this->errors[] = $this->langs->trans('ECommerceErrorPaymentGatewaysBankAccountNotConfigured', $order_data['payment_method_id'], $order_data['payment_method']);
												$error++;
											} elseif ($bank_account_id > 0) {
												$result = $invoice->setBankAccount($bank_account_id);
												if ($result < 0) {
													$this->errors[] = $this->langs->trans('ECommerceErrorInvoiceSetBankAccount');
													if (!empty($invoice->error)) $this->errors[] = $invoice->error;
													$this->errors = array_merge($this->errors, $invoice->errors);
													$error++;
												}
											}
										}

										// Get warehouse ID
										$warehouse_id = $this->eCommerceSite->parameters['order_actions']['valid_invoice_fk_warehouse'] > 0 && empty($warehouseByLine) ? $this->eCommerceSite->parameters['order_actions']['valid_invoice_fk_warehouse'] : 0;
										if (empty($warehouse_id) && empty($warehouseByLine) && !empty($conf->global->STOCK_CALCULATE_ON_BILL)) {
											$this->errors[] = $this->langs->trans('ECommerceErrorInvoiceValidateWarehouseNotConfigured');
											$error++;
										}

										if ($isDepositType) {
											$save_WORKFLOW_INVOICE_AMOUNT_CLASSIFY_BILLED_ORDER = $conf->global->WORKFLOW_INVOICE_AMOUNT_CLASSIFY_BILLED_ORDER;
											$save_WORKFLOW_INVOICE_CLASSIFY_BILLED_PROPAL = $conf->global->WORKFLOW_INVOICE_CLASSIFY_BILLED_PROPAL;
											$save_WORKFLOW_INVOICE_CLASSIFY_BILLED_ORDER = $conf->global->WORKFLOW_INVOICE_CLASSIFY_BILLED_ORDER;
											$conf->global->WORKFLOW_INVOICE_AMOUNT_CLASSIFY_BILLED_ORDER = 0;
											$conf->global->WORKFLOW_INVOICE_CLASSIFY_BILLED_PROPAL = 0;
											$conf->global->WORKFLOW_INVOICE_CLASSIFY_BILLED_ORDER = 0;
										}

										// Validate invoice
										if (!$error) {
											$result = $invoice->validate($this->user, '', $warehouse_id);
											if ($result < 0) {
												$this->errors[] = $this->langs->trans('ECommerceErrorInvoiceValidate');
												if (!empty($invoice->error)) $this->errors[] = $invoice->error;
												$this->errors = array_merge($this->errors, $invoice->errors);
												$error++;
											} else {
												$result = $this->setLinesMovementStockOnDifferentWarehouse($invoice, $warehouseByLine);
												if ($result < 0) {
													$this->errors[] = $this->langs->trans("ECommerceErrorWhenMovementStockOnDifferentWarehouse");
													$error++;
												}
											}
										}

										// Force set amount if another amount paid
										if (!$error) {
											$result = $this->fixTotalAmounts($invoice, $order_data['payment_amount_ttc']);
											if ($result < 0) {
												$error++;
											}
										}

										// Creation of payment line
										if (!$error) {
											if ($invoice->total_ttc != 0) {
												if ($conf->banque->enabled && !empty($selected_payment_gateways['create_invoice_payment'])) {
													$payment = new Paiement($this->db);
													$payment->datepaye = $invoice->date;
													$payment->amounts = array($invoice->id => $invoice->total_ttc);   // Array with all payments dispatching with invoice id
													$payment->multicurrency_amounts = array();   // Array with all payments dispatching
													$payment->paiementid = $invoice->mode_reglement_id;
													$payment->num_paiement = '';
													$payment->note = 'Created by WooSync';

													$payment_id = $payment->create($this->user, 1);
													if ($payment_id < 0) {
														$this->errors[] = $this->langs->trans('ECommerceErrorInvoiceCreatePayment');
														if (!empty($payment->error)) $this->errors[] = $payment->error;
														$this->errors = array_merge($this->errors, $payment->errors);
														$error++;
													} else {
														$result = $payment->addPaymentToBank($this->user, 'payment', '(CustomerInvoicePayment)', $bank_account_id, '', '');
														if ($result < 0) {
															$this->errors[] = $this->langs->trans('ECommerceErrorInvoiceAddPaymentToBank');
															if (!empty($payment->error)) $this->errors[] = $payment->error;
															$this->errors = array_merge($this->errors, $payment->errors);
															$error++;
														}
													}
												}
											}
											$invoice->fetch($invoice->id); // Reload to get new records
											if (empty($invoice->total_ttc) || (empty($invoice->paye) && ((string)$invoice->getRemainToPay()) == 0)) {
												$result = $invoice->set_paid($this->user);
												if ($result < 0) {
													$this->errors[] = $this->langs->trans('ECommerceErrorInvoiceSetPaid');
													if (!empty($invoice->error)) $this->errors[] = $invoice->error;
													$this->errors = array_merge($this->errors, $invoice->errors);
													$error++;
												}
											}
										}

										if ($isDepositType) {
											$conf->global->WORKFLOW_INVOICE_AMOUNT_CLASSIFY_BILLED_ORDER = $save_WORKFLOW_INVOICE_AMOUNT_CLASSIFY_BILLED_ORDER;
											$conf->global->WORKFLOW_INVOICE_CLASSIFY_BILLED_PROPAL = $save_WORKFLOW_INVOICE_CLASSIFY_BILLED_PROPAL;
											$conf->global->WORKFLOW_INVOICE_CLASSIFY_BILLED_ORDER = $save_WORKFLOW_INVOICE_CLASSIFY_BILLED_ORDER;
										}

										// Generate document
										if (!$error && !empty($invoice->modelpdf) && empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE)) {
											// Define output language
											$outputlangs = $this->langs;
											$newlang = '';
											if ($conf->global->MAIN_MULTILANGS && !empty($order_data['language']) && $order_data['language'] != 'ec_none') $newlang = $order_data['language'];
											if (!empty($newlang)) {
												$outputlangs = new Translate("", $conf);
												$outputlangs->setDefaultLang($newlang);
												$outputlangs->load('products');
											}
											$invoice->fetch($invoice->id); // Reload to get new records

											$hidedetails = !empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DETAILS) ? 1 : 0;
											$hidedesc = !empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DESC) ? 1 : 0;
											$hideref = !empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_REF) ? 1 : 0;
											$result = $invoice->generateDocument($invoice->modelpdf, $outputlangs, $hidedetails, $hidedesc, $hideref);
											if ($result < 0) {
												$this->errors[] = $this->langs->trans('ECommerceErrorInvoiceGenerateDocument');
												if (!empty($invoice->error)) $this->errors[] = $invoice->error;
												$this->errors = array_merge($this->errors, $invoice->errors);
												$error++;
											}
										}

										// Create supplier invoice
										if (!$error && !empty($this->eCommerceSite->parameters['order_actions']['create_supplier_invoice']) && is_array($order_data['fee_lines']) && count($order_data['fee_lines']) > 0) {
											// Check if supplier third party configured
											$supplier_id = $selected_payment_gateways['supplier_id'] > 0 ? $selected_payment_gateways['supplier_id'] : 0;
											if ($supplier_id == 0) {
												$this->errors[] = $this->langs->trans('ECommerceErrorPaymentGatewaysSupplierNotConfigured', $order_data['payment_method_id'], $order_data['payment_method']);
												$error++;
											} else {
												// Set the supplier invoice
												$supplier_invoice = new FactureFournisseur($this->db);
												$supplier_invoice->type = FactureFournisseur::TYPE_STANDARD;
												$supplier_invoice->ref = '';
												$supplier_invoice->ref_supplier = $invoice_ref_ext;
												$supplier_invoice->socid = $supplier_id;
												$supplier_invoice->libelle = '';
												$supplier_invoice->date = $invoice->date;
												$supplier_invoice->date_echeance = '';
												$supplier_invoice->cond_reglement_id = 0;
												$supplier_invoice->mode_reglement_id = $invoice->mode_reglement_id;
												$supplier_invoice->fk_account = $bank_account_id;
												$supplier_invoice->array_options = $this->getDefaultExtraFields($supplier_invoice->table_element, $this->eCommerceSite);

												if (!empty($conf->global->ECOMMERCENG_ENABLE_LOG_IN_NOTE)) {
													$supplier_invoice->note_private = dol_concatdesc($supplier_invoice->note_private, $this->langs->trans('ECommerceCreateSupplierInvoiceFromSiteNote', $this->eCommerceSite->name) . " :\n" . json_encode($order_data['remote_order']));
												}

												$supplier_invoice->linkedObjectsIds[$invoice->element] = $invoice->id;
												if ($order->id > 0) {
													$supplier_invoice->linkedObjectsIds[$order->element] = $order->id;
												}

												// Create supplier invoice
												$id = $supplier_invoice->create($this->user);
												if ($id < 0) {
													$this->errors[] = $this->langs->trans('ECommerceErrorSupplierInvoiceCreate');
													if (!empty($supplier_invoice->error)) $this->errors[] = $supplier_invoice->error;
													$this->errors = array_merge($this->errors, $supplier_invoice->errors);
													$error++;
												}

												// Add lines
												if (!$error) {
													$product_id = $selected_payment_gateways['product_id_for_fee'] > 0 ? $selected_payment_gateways['product_id_for_fee'] : 0;
													$array_options = $this->getDefaultExtraFields('facture_fourn_det', $this->eCommerceSite);
													foreach ($order_data['fee_lines'] as $fee_line) {
														if (floatval(DOL_VERSION) < 8) $this->db->begin(); // Not exist in addline function but commit and rollback exist
														$result = $supplier_invoice->addline(
															$fee_line['label'],
															$fee_line['price'],
															$fee_line['tax'],
															$fee_line['local_tax1_tx'],
															$fee_line['local_tax2_tx'],
															$fee_line['qty'],
															$product_id,
															0, '', '', 0, '','HT', 0, -1, false, $array_options);
														if ($result < 0) {
															$this->errors[] = $this->langs->trans('ECommerceErrorSupplierInvoiceAddLine');
															if (!empty($supplier_invoice->error)) $this->errors[] = $supplier_invoice->error;
															$this->errors = array_merge($this->errors, $supplier_invoice->errors);
															$error++;
															break;
														}
													}
												}

												// Validate supplier invoice
												if (!$error) {
													// Get warehouse ID
													$warehouse_id = $this->eCommerceSite->parameters['order_actions']['valid_supplier_invoice_fk_warehouse'] > 0 ? $this->eCommerceSite->parameters['order_actions']['valid_supplier_invoice_fk_warehouse'] : 0;

													$result = $supplier_invoice->validate($this->user, '', $warehouse_id);
													if ($result < 0) {
														$this->errors[] = $this->langs->trans('ECommerceErrorSupplierInvoiceValidate');
														if (!empty($supplier_invoice->error)) $this->errors[] = $supplier_invoice->error;
														$this->errors = array_merge($this->errors, $supplier_invoice->errors);
														$error++;
													}
												}

												// Creation of payment line
												if (!$error && $conf->banque->enabled && !empty($selected_payment_gateways['create_supplier_invoice_payment'])) {
													$payment = new PaiementFourn($this->db);
													$payment->datepaye = $supplier_invoice->date;
													$payment->amounts = array($supplier_invoice->id => $supplier_invoice->total_ttc);   // Array of amounts
													$payment->multicurrency_amounts = array();
													$payment->paiementid = $supplier_invoice->mode_reglement_id;
													$payment->num_paiement = '';
													$payment->note = 'Created by WooSync';

													$payment_id = $payment->create($this->user, 1);
													if ($payment_id < 0) {
														$this->errors[] = $this->langs->trans('ECommerceErrorSupplierInvoiceCreatePayment');
														if (!empty($payment->error)) $this->errors[] = $payment->error;
														$this->errors = array_merge($this->errors, $payment->errors);
														$error++;
													} else {
														$result = $payment->addPaymentToBank($this->user, 'payment_supplier', '(SupplierInvoicePayment)', $bank_account_id, '', '');
														if ($result < 0) {
															$this->errors[] = $this->langs->trans('ECommerceErrorSupplierInvoiceAddPaymentToBank');
															if (!empty($payment->error)) $this->errors[] = $payment->error;
															$this->errors = array_merge($this->errors, $payment->errors);
															$error++;
														}
													}
												}

												// Generate document
												if (!$error && !empty($conf->global->INVOICE_SUPPLIER_ADDON_PDF) && empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE)) {
													// Define output language
													$outputlangs = $this->langs;
													$newlang = '';
                                                    if ($conf->global->MAIN_MULTILANGS && !empty($order_data['language']) && $order_data['language'] != 'ec_none') $newlang = $order_data['language'];
													if (!empty($newlang)) {
														$outputlangs = new Translate("", $conf);
														$outputlangs->setDefaultLang($newlang);
														$outputlangs->load('products');
													}
													$supplier_invoice->fetch($supplier_invoice->id); // Reload to get new records

													$hidedetails = !empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DETAILS) ? 1 : 0;
													$hidedesc = !empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DESC) ? 1 : 0;
													$hideref = !empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_REF) ? 1 : 0;
													$result = $supplier_invoice->generateDocument($conf->global->INVOICE_SUPPLIER_ADDON_PDF, $outputlangs, $hidedetails, $hidedesc, $hideref);
													if ($result < 0) {
														$this->errors[] = $this->langs->trans('ECommerceErrorSupplierInvoiceGenerateDocument');
														if (!empty($supplier_invoice->error)) $this->errors[] = $supplier_invoice->error;
														$this->errors = array_merge($this->errors, $supplier_invoice->errors);
														$error++;
													}
												}
											}
										}

										// Auto send invoice by mail
										if (!$error && !empty($this->eCommerceSite->parameters['order_actions']['send_invoice_by_mail'])) {
                                            $selected_language = empty($order_data['language']) ? 'ec_none' : $order_data['language'];
											$send_to = trim($order_data['socpeopleCommande']['email']);
											if (empty($send_to)) {
												$this->errors[] = $this->langs->trans('ECommerceErrorCustomerEmailEmptyForSendInvoiceByEmail');
												$error++;
											} elseif ((!isset($selected_payment_gateways['mail_model_for_send_invoice'][$selected_language]) ||
												!($selected_payment_gateways['mail_model_for_send_invoice'][$selected_language] > 0)) &&
												empty($conf->global->ECOMMERCENG_BYPASS_SEND_INVOICE_WHEN_NO_EMAIL_TEMPLATE)
											) {
												$this->errors[] = $this->langs->trans('ECommerceErrorPaymentGatewaysMailModelNotConfigured', $order_data['payment_method_id'], $order_data['payment_method'], $selected_language);
												$error++;
											}

											if (!$error && (empty($conf->global->ECOMMERCENG_BYPASS_SEND_INVOICE_WHEN_NO_EMAIL_TEMPLATE) ||
													(isset($selected_payment_gateways['mail_model_for_send_invoice'][$selected_language]) && $selected_payment_gateways['mail_model_for_send_invoice'][$selected_language] > 0))
											) {
												$ret = $invoice->fetch($invoice->id);
												$ret = $invoice->fetch_thirdparty();

												require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
												require_once DOL_DOCUMENT_ROOT . '/core/class/html.formmail.class.php';
												$formmail = new FormMail($this->db);

												$this->langs->load('mails');
												$this->langs->load("commercial");
												if (!empty($conf->dolimail->enabled)) $this->langs->load("dolimail@dolimail");

												$trackid = 'inv' . $invoice->id;
												$deliveryreceipt = 0;

												// from / send to / ...
												$from = $conf->global->MAIN_INFO_SOCIETE_NOM . ' <' . $conf->global->MAIN_INFO_SOCIETE_MAIL . '>';
												$sendtocc = '';
												$sendtobcc = !empty($conf->global->MAIN_MAIL_AUTOCOPY_INVOICE_TO) ? $conf->global->MAIN_MAIL_AUTOCOPY_INVOICE_TO : '';

												// Define output language
												$outputlangs = $this->langs;
												$newlang = '';
                                                if ($conf->global->MAIN_MULTILANGS && $selected_language != 'ec_none') $newlang = $selected_language;
												if (!empty($newlang)) {
													$outputlangs = new Translate('', $conf);
													$outputlangs->setDefaultLang($newlang);
													$outputlangs->loadLangs(array('commercial', 'bills', 'orders', 'contracts', 'members', 'propal', 'products', 'supplier_proposal', 'interventions'));
												}

												// Make substitution in email content
												$substitutionarray = getCommonSubstitutionArray($outputlangs, 0, null, $invoice);
												$substitutionarray['__CHECK_READ__'] = (is_object($invoice) && is_object($invoice->thirdparty)) ? '<img src="' . DOL_MAIN_URL_ROOT . '/public/emailing/mailing-read.php?tag=' . $invoice->thirdparty->tag . '&securitykey=' . urlencode($conf->global->MAILING_EMAIL_UNSUBSCRIBE_KEY) . '" width="1" height="1" style="width:1px;height:1px" border="0"/>' : '';
												$substitutionarray['__PERSONALIZED__'] = '';    // deprecated
												$substitutionarray['__CONTACTCIVNAME__'] = '';
												$parameters = array('mode' => 'formemail');
												complete_substitutions_array($substitutionarray, $outputlangs, $invoice, $parameters);

												// Get email template
												$type_template = 'facture_send';
												$arraydefaultmessage = $formmail->getEMailTemplate($this->db, $type_template, $this->user, $outputlangs, $selected_payment_gateways['mail_model_for_send_invoice'][$selected_language]);
												if (is_numeric($arraydefaultmessage) && $arraydefaultmessage < 0) {
													$this->errors[] = $this->langs->trans('ECommerceErrorGetEMailTemplate');
													if (!empty($formmail->error)) $this->errors[] = $formmail->error;
													else $this->errors[] = $this->db->lasterror();
													$error++;
												}

												if (!$error) {
													// Complete substitution array
													if (empty($substitutionarray['__REF__'])) {
														$paymenturl = '';
													} else {
														// Set the online payment url link into __ONLINE_PAYMENT_URL__ key
														require_once DOL_DOCUMENT_ROOT . '/core/lib/payments.lib.php';
														$outputlangs->load('paypal');
														$paymenturl = getOnlinePaymentUrl(0, 'invoice', $substitutionarray['__REF__']);
													}
													$substitutionarray['__ONLINE_PAYMENT_URL__'] = $paymenturl;

													// Define subject / message
													$message = str_replace('\n', "\n", is_array($arraydefaultmessage) ? $arraydefaultmessage['content'] : $arraydefaultmessage->content);
													// Deal with format differences between message and signature (text / HTML)
													if (dol_textishtml($message) && !dol_textishtml($substitutionarray['__USER_SIGNATURE__'])) {
														$substitutionarray['__USER_SIGNATURE__'] = dol_nl2br($substitutionarray['__USER_SIGNATURE__']);
													} else if (!dol_textishtml($message) && dol_textishtml($substitutionarray['__USER_SIGNATURE__'])) {
														$message = dol_nl2br($message);
													}

													$subject = make_substitutions(is_array($arraydefaultmessage) ? $arraydefaultmessage['topic'] : $arraydefaultmessage->topic, $substitutionarray);
													$message = make_substitutions($message, $substitutionarray);
													if (method_exists($invoice, 'makeSubstitution')) {
														$subject = $invoice->makeSubstitution($subject);
														$message = $invoice->makeSubstitution($message);
													}

													// Clean first \n and br (to avoid empty line when CONTACTCIVNAME is empty)
													$message = preg_replace("/^(<br>)+/", "", $message);
													$message = preg_replace("/^\n+/", "", $message);

													// Define $urlwithroot
													global $dolibarr_main_url_root;
													$urlwithouturlroot = preg_replace('/' . preg_quote(DOL_URL_ROOT, '/') . '$/i', '', trim($dolibarr_main_url_root));
													$urlwithroot = $urlwithouturlroot . DOL_URL_ROOT;        // This is to use external domain name found into config file
													//$urlwithroot=DOL_MAIN_URL_ROOT;					// This is to use same domain name than current
													// Make a change into HTML code to allow to include images from medias directory with an external reabable URL.
													// <img alt="" src="/dolibarr_dev/htdocs/viewimage.php?modulepart=medias&amp;entity=1&amp;file=image/ldestailleur_166x166.jpg" style="height:166px; width:166px" />
													// become
													// <img alt="" src="'.$urlwithroot.'viewimage.php?modulepart=medias&amp;entity=1&amp;file=image/ldestailleur_166x166.jpg" style="height:166px; width:166px" />
													$message = preg_replace('/(<img.*src=")[^\"]*viewimage\.php([^\"]*)modulepart=medias([^\"]*)file=([^\"]*)("[^\/]*\/>)/', '\1' . $urlwithroot . '/viewimage.php\2modulepart=medias\3file=\4\5', $message);

													// Attach invoice file
													$formmail->trackid = $trackid;      // $trackid must be defined
													$formmail->clear_attached_files();
													if (!empty(is_array($arraydefaultmessage) ? $arraydefaultmessage['joinfiles'] : $arraydefaultmessage->joinfiles)) {
														$ref = dol_sanitizeFileName($invoice->ref);
														$fileparams = dol_most_recent_file($conf->facture->dir_output . '/' . $ref, preg_quote($ref, '/') . '[^\-]+');
														$file = $fileparams['fullname'];
														$formmail->add_attached_files($file, basename($file), dol_mimetype($file));
													}
													$attachedfiles = $formmail->get_attached_files();
													$filepath = $attachedfiles['paths'];
													$filename = $attachedfiles['names'];
													$mimetype = $attachedfiles['mimes'];

													// Send mail (substitutionarray must be done just before this)
													require_once DOL_DOCUMENT_ROOT . '/core/class/CMailFile.class.php';
													$sendcontext = 'standard';
													$mailfile = new CMailFile($subject, $send_to, $from, $message, $filepath, $mimetype, $filename, $sendtocc, $sendtobcc, $deliveryreceipt, -1, '', '', $trackid, '', $sendcontext);
													if ($mailfile->error) {
														$this->errors[] = $this->langs->trans('ECommerceErrorInvoiceCreateMail');
														if (!empty($mailfile->error)) $this->errors[] = $mailfile->error;
														$this->errors = array_merge($this->errors, $mailfile->errors);
														$error++;
													} else {
														$result = $mailfile->sendfile();
														if ($result) {
															// Get order contacts
															$contact_list = $invoice->liste_contact(-1, 'external');
															if (!is_array($contact_list)) {
																$this->errors[] = $this->langs->trans('ECommerceErrorInvoiceGetExternalContacts', $invoice->id);
																if (!empty($invoice->error)) $this->errors[] = $invoice->error;
																$this->errors = array_merge($this->errors, $invoice->errors);
																$error++;
															} else {
																// Event send email
																$sendtoid = array();
																foreach ($contact_list as $contact_infos) {
																	$sendtoid[$contact_infos['id']] = $contact_infos['id'];
																}
																$sendtoid = array_values($sendtoid);

																$actionmsg = '';
																$actionmsg2 = $this->langs->transnoentities('MailSentBy') . ' ' . CMailFile::getValidAddress($from, 4, 0, 1) . ' ' . $this->langs->transnoentities('To') . ' ' . CMailFile::getValidAddress($send_to, 4, 0, 1);
																if ($message) {
																	$actionmsg = $this->langs->transnoentities('MailFrom') . ': ' . dol_escape_htmltag($from);
																	$actionmsg = dol_concatdesc($actionmsg, $this->langs->transnoentities('MailTo') . ': ' . dol_escape_htmltag($send_to));
																	$actionmsg = dol_concatdesc($actionmsg, $this->langs->transnoentities('MailTopic') . ": " . $subject);
																	$actionmsg = dol_concatdesc($actionmsg, $this->langs->transnoentities('TextUsedInTheMessageBody') . ":");
																	$actionmsg = dol_concatdesc($actionmsg, $message);
																}

																$invoice->sendtoid = $sendtoid;       // To link to contacts/addresses. This is an array.
																$invoice->actiontypecode = 'AC_OTH_AUTO'; // Type of event ('AC_OTH', 'AC_OTH_AUTO', 'AC_XXX'...)
																$invoice->actionmsg = $actionmsg;      // Long text
																$invoice->actionmsg2 = $actionmsg2;     // Short text
																$invoice->trackid = $trackid;
																$invoice->fk_element = $invoice->id;
																$invoice->elementtype = $invoice->element;
																if (is_array($attachedfiles) && count($attachedfiles) > 0) {
																	$invoice->attachedfiles = $attachedfiles;
																}

																include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
																$interface = new Interfaces($this->db);
																$result = $interface->run_triggers('BILL_SENTBYMAIL', $invoice, $this->user, $this->langs, $conf);
																if ($result < 0) {
																	$this->errors[] = $this->langs->trans('ECommerceErrorInvoiceCreateSendMailEvent');
																	if (!empty($interface->error)) $this->errors[] = $interface->error;
																	$this->errors = array_merge($this->errors, $interface->errors);
																	$error++;
																}
															}
														} else {
															$this->langs->load("other");
															$this->errors[] = $this->langs->trans('ECommerceErrorInvoiceSendByMail');
															if ($mailfile->error) {
																$this->errors[] = $this->langs->trans('ErrorFailedToSendMail', $from, $send_to);
																$this->errors[] = $mailfile->error;
															} else {
																$this->errors[] = ' No mail sent. Feature is disabled by option MAIN_DISABLE_ALL_MAILS';
															}
															$error++;
														}
													}
												}
											}
										}
									} elseif (empty($this->eCommerceSite->parameters['order_actions']['create_order'])) {
										$this->warnings[] = $this->langs->trans('ECommerceWarningOrderThirdPartyNotSupported', $order_data['remote_id'], $order_data['remote_id_societe']);
										$bypass = true;
									}
								}
							}

							// Update the link of the synchronization
							//--------------------------------------------
							if (!$error && !$bypass) {
								$this->eCommerceCommande->last_update = $order_data['last_update'];
								$this->eCommerceCommande->fk_commande = $order->id > 0 ? $order->id : ($invoice->id > 0 ? -$invoice->id : 0);

								// Update link
								if ($this->eCommerceCommande->id > 0) {
									$result = $this->eCommerceCommande->update($this->user);
									if ($result < 0) {
										$this->errors[] = $this->langs->trans('ECommerceErrorUpdateOrderLink');
									}
								} // Create link
								else {
									$this->eCommerceCommande->fk_site = $this->eCommerceSite->id;
									$this->eCommerceCommande->remote_id = $order_data['remote_id'];
									$result = $this->eCommerceCommande->create($this->user);
									if ($result < 0) {
										$this->errors[] = $this->langs->trans('ECommerceErrorCreateOrderLink');
									}
								}
								if ($result < 0) {
									$this->errors = array_merge($this->errors, $this->eCommerceCommande->errors);
									$error++;
								}
							}

							$last_sync_date = 'ECOMMERCE_LAST_SYNC_DATE_ORDER_' . $this->eCommerceSite->id;
							if (!$error && (!is_numeric($conf->global->$last_sync_date) || $conf->global->$last_sync_date < $order_data['create_date'])) {
								$result = dolibarr_set_const($this->db, $last_sync_date, $order_data['create_date'], 'chaine', 0, '', $conf->entity);
								if ($result < 0) {
									$this->errors[] = $this->langs->trans('ECommerceErrorSetLastSyncDateOrder');
									$this->errors[] = $this->db->lasterror();
									$error++;
								}
							}
						}
					}
				}
			} catch (Exception $e) {
				$this->errors[] = $e->getMessage();
				$error++;
			}

			// Commit / rollback actions
			if ($error) {
				$this->db->rollback();
			} else {
				$this->db->commit();
			}
		}

		if ($error) {
			$this->errors = array_merge(array($this->langs->trans('ECommerceErrorWhenSynchronizeOrderToInvoice', $order_data['remote_id'])), $this->errors);
			dol_syslog(__METHOD__ . ' Error=' . $this->errorsToString(), LOG_ERR);
			return -1;
		} else {
			return $invoice->id > 0 ? $invoice->id : 0;
		}
	}

	/**
	 * Synchronize a order data in the invoice refunds in Dolibarr database
	 *
	 * @param	array	$order_data						Order data to synchronize
	 * @param	bool	$dont_synchronize_products		Bypass the synchronization of the product of the order
	 * @return	int										<0 if KO, =0 if bypassed, Id of the invoice in Dolibarr if OK
	 */
	public function synchronizeInvoiceRefundsFromOrder($order_data, $dont_synchronize_products = false)
	{
		dol_syslog(__METHOD__ . ' order_data=' . json_encode($order_data), LOG_DEBUG);
		global $conf;

		$this->error = '';
		$this->errors = array();
		$error = 0;

		$infV14 = (version_compare(DOL_VERSION, "14.0.0") < 0);

		require_once DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php';
		require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
		$order = new Commande($this->db);
		$invoice = new Facture($this->db);

		if (!is_array($order_data)) {
			$this->langs->load('errors');
			$this->errors[] = $this->langs->trans('ErrorBadParameters') . '; order_data=' . json_encode($order_data);
			$error++;
		}

		if (!$error && !empty($order_data)) {
			$order_ref_ext = $this->eCommerceSite->name . '-' . $order_data['remote_id'];
			$invoice_ref_ext = 'eCommerce-' . $this->eCommerceSite->id . '-' . $order_data['remote_id'];
			$invoice_refund_ref_ext_prefix = 'eCommerce-' . $this->eCommerceSite->id . '-' . $order_data['remote_id'] . '-refund-';
			$order_id = 0;
			$selected_payment_gateways = null;
			$payment_method_id = 0;

			$this->db->begin();

			try {
				if (!empty($this->eCommerceSite->parameters['order_actions']['create_invoice']) && !empty($order_data['billed']) &&
					(!empty($this->eCommerceSite->parameters['order_actions']['create_invoice_if_amount_0']) || price2num($order_data['payment_amount_ttc']) != 0)
				) {
					// Fetch invoice by ref_ext
					$result = $invoice->fetch(0, '', $invoice_ref_ext);
					if ($result < 0) {
						$this->error = $invoice->error;
						$this->errors = array_merge($this->errors, $invoice->errors);
						$error++;
					}

					// Search order already synchronized
					$this->initECommerceCommande();
					$result = $this->eCommerceCommande->fetchByRemoteId($order_data['remote_id'], $this->eCommerceSite->id);
					if ($result < 0 && !empty($this->eCommerceCommande->error)) {
						$this->errors[] = $this->langs->trans('ECommerceErrorFetchOrderLinkByRemoteId', $order_data['remote_id'], $this->eCommerceSite->id);
						$this->errors[] = $this->eCommerceCommande->error;
						$error++;
					} else {
						if ($this->eCommerceCommande->fk_commande > 0) {
							$order_id = $this->eCommerceCommande->fk_commande;
						}
					}

					// Fetch order
					if (!$error) {
						if ($order_id > 0) {
							$result = $order->fetch($order_id);
							if ($result < 0) {
								$this->errors[] = $this->langs->trans('ECommerceErrorFetchOrder', $order_id);
								if (!empty($order->error)) $this->errors[] = $order->error;
								$this->errors = array_merge($this->errors, $order->errors);
								$error++;
							}
						} else {
							// Fetch order by ref_ext if already created but the link is deleted
							$result = $order->fetch(0, '', $order_ref_ext);
							if ($result < 0) {
								$this->errors[] = $this->langs->trans('ECommerceErrorFetchOrderByRefExt', $order_ref_ext);
								if (!empty($order->error)) $this->errors[] = $order->error;
								$this->errors = array_merge($this->errors, $order->errors);
								$error++;
							}
						}
					}

					// Fetch first linked invoice of the order if invoice not found
					if (!$error && !($invoice->id > 0) && $order->id > 0) {
						$result = $order->fetchObjectLinked();
						if ($result < 0) {
							$this->errors[] = $this->langs->trans('ECommerceErrorFetchObjectLinked');
							$this->errors[] = $order->errorsToString();
							$error++;
						}
						if (!empty($order->linkedObjects['facture'])) {
							$invoice_linked = array_values($order->linkedObjects['facture']);
							$invoice = $invoice_linked[0];
						}
					}

					// Need to synchronize ?
					$bypass = $order_data['create_date'] < $this->eCommerceSite->parameters['order_first_date_etod'];

					foreach ($order_data['refunds'] as $refund_info) {
						$third_party_id = 0;
						$invoice_refund = new Facture($this->db);
						$invoice_refund_ref_ext = $invoice_refund_ref_ext_prefix . $refund_info['remote_id'];

						// Fetch invoice by ref_ext
						$result = $invoice_refund->fetch(0, '', $invoice_refund_ref_ext);
						if ($result < 0) {
							$this->error[] = $invoice_refund->errorsToString();
							$error++;
						} elseif ($invoice_refund->id > 0) {
							if ($order->id > 0) $invoice_refund->add_object_linked($order->element, $order->id);
							if ($invoice->id > 0) $invoice_refund->add_object_linked($invoice->element, $invoice->id);
						} elseif (!$bypass) {
							if ($order->id > 0) {
								$third_party_id = $order->socid;
							} elseif ($invoice->id > 0) {
								$third_party_id = $invoice->socid;
							} else {
								// Check if third party already synchronized
								if ($order_data['remote_id_societe'] > 0) {
									$this->initECommerceSociete();
									// Synchronize the customer
									$result = $this->synchronizeCustomers(null, null, array($order_data['remote_id_societe']), 1, false, false, true);
									if ($result < 0) {
										$error++;
									} elseif ($result > 0) {
										$result = $this->eCommerceSociete->fetchByRemoteId($order_data['remote_id_societe'], $this->eCommerceSite->id);
										if ($result < 0 && !empty($this->eCommerceSociete->error)) {
											$this->errors[] = $this->langs->trans('ECommerceErrorFetchThirdPartyLinkByRemoteId', $order_data['remote_id_societe'], $this->eCommerceSite->id);
											$this->errors[] = $this->eCommerceSociete->error;
											$error++;
										} elseif ($result > 0) {
											$third_party_id = $this->eCommerceSociete->fk_societe;
										} else {
											// Customer not supported (eg. order created by admin so we don't need it)
											$third_party_id = null;
										}
									} else {
										// Customer not supported (eg. order created by admin so we don't need it)
										$third_party_id = null;
									}
								}
								if ($third_party_id === 0) {
									// This is an guest customer.
									if ($this->eCommerceSite->fk_anonymous_thirdparty > 0) {
										$third_party_id = $this->eCommerceSite->fk_anonymous_thirdparty;
									} else {
										$this->errors[] = $this->langs->trans('ECommerceErrorAnonymousThirdPartyNotConfigured', $this->eCommerceSite->id);
										$error++;
									}
								}
							}

							// Create the invoice only if the third party ID is found (otherwise it's bypassed)
							if (!$error) {
								if (isset($third_party_id)) {
									$third_party = new Societe($this->db);
									$third_party->fetch($third_party_id);

									// Set the invoice
									$invoice_refund->socid = $third_party_id;
									$invoice_refund->type = Facture::TYPE_CREDIT_NOTE;
									$invoice_refund->date = !empty($refund_info['create_date']) ? $refund_info['create_date'] : (isset($order_data['date_payment']) && $order_data['date_payment'] !== '' ? strtotime($order_data['date_payment']) : ($order->id > 0 ? $order->date_commande : strtotime($order_data['date_commande'])));
									$invoice_refund->ref_client = $order->id > 0 ? $order->ref_client : $order_data['ref_client'];
									$invoice_refund->ref_ext = $invoice_refund_ref_ext;
									$invoice_refund->modelpdf = $conf->global->FACTURE_ADDON_PDF;
									$invoice_refund->cond_reglement_id = $order->id > 0 ? $order->cond_reglement_id : ($third_party->cond_reglement_id > 0 ? $third_party->cond_reglement_id : (isset($this->eCommerceSite->parameters['payment_cond']) ? $this->eCommerceSite->parameters['payment_cond'] : null));
									$invoice_refund->multicurrency_code = $order->id > 0 ? $order->multicurrency_code : null;
									$invoice_refund->multicurrency_tx = $order->id > 0 ? $order->multicurrency_tx : null;
									$invoice_refund->entity = $conf->entity;
									$invoice_refund->statut = Facture::STATUS_DRAFT;

									$invoice_refund->note_private = "";
									if (isset($refund_info['reason'])) {
										if (!empty($this->eCommerceSite->parameters['order_actions']['order_note_into_public_note'])) {
											$invoice_refund->note_public = $refund_info['reason'];
										} else {
											$invoice_refund->note_private = $refund_info['reason'];
										}
									}
									if (!empty($conf->global->ECOMMERCENG_ENABLE_LOG_IN_NOTE)) {
										$invoice_refund->note_private = dol_concatdesc($invoice_refund->note_private, $this->langs->trans('ECommerceCreateInvoiceFromSiteNote', $this->eCommerceSite->name) . " :\n" . json_encode($refund_info['remote_id']));
									}

									if ($order->id > 0) $invoice_refund->linkedObjectsIds[$order->element] = $order->id;
									if ($invoice->id > 0) $invoice_refund->linkedObjectsIds[$invoice->element] = $invoice->id;

									$invoice_refund->array_options = $this->getDefaultExtraFields($invoice_refund->table_element, $this->eCommerceSite);
									if ($order->id > 0) {
										foreach ($order->array_options as $key => $value) {
											$invoice_refund->array_options[$key] = $value;
										}
									} elseif (is_array($order_data['extrafields'])) {
										foreach ($order_data['extrafields'] as $key => $value) {
											$invoice_refund->array_options['options_' . $key] = $value;
										}
									}

									$invoice_refund->context['fromsyncofecommerceid'] = $this->eCommerceSite->id;

									// Create invoice
									$result = $invoice_refund->create($this->user);
									if ($result < 0) {
										$this->errors[] = $this->langs->trans('ECommerceErrorInvoiceCreate');
										if (!empty($invoice_refund->error)) $this->errors[] = $invoice_refund->error;
										$this->errors = array_merge($this->errors, $invoice_refund->errors);
										$error++;
									}

									// Add / update contacts
									if (!$error) {
										$result = $this->addContacts($invoice_refund, $order_data, true);
										if ($result < 0) {
											$error++;
										}
									}

									// Add product line if new created
									$warehouseByLine = array();
									if (!$error && is_array($refund_info['items'])) {
										$result = $this->addProductLines($invoice_refund, $refund_info['items'], $order_data, $warehouseByLine, $order->id > 0 || $dont_synchronize_products);
										if ($result < 0) {
											$error++;
										}
									}

									// Force set amount if another amount paid
									if (!$error) {
										$result = $this->fixTotalAmounts($invoice_refund, -$refund_info['total_ttc']);
										if ($result < 0) {
											$error++;
										}
									}

									// Get payment gateways
									if (!$error && !empty($order_data['payment_method_id'])) {
										$result = $this->loadPaymentGateways();
										if ($result < 0) {
											$error++;
										} else {
											if (isset($this->payment_gateways_cached[$order_data['payment_method_id']])) {
												$selected_payment_gateways = $this->payment_gateways_cached[$order_data['payment_method_id']];
											} else {
												$this->errors[] = $this->langs->trans('ECommerceErrorPaymentGatewaysNotFound', $order_data['payment_method_id'], $order_data['payment_method']);
												$error++;
											}
										}
									}

									// Search payment mode by ID if payment gateways found
									if (!$error && isset($selected_payment_gateways)) {
										if ($selected_payment_gateways['payment_mode_id'] > 0) {
											$payment_method_id = $selected_payment_gateways['payment_mode_id'];
										} else {
											$this->errors[] = $this->langs->trans('ECommerceErrorPaymentGatewaysPaymentMethodNotConfigured', $order_data['payment_method_id'], $order_data['payment_method']);
											$error++;
										}
									}

									// Search payment mode by label
									if (!$error && !($payment_method_id > 0) && !empty($order_data['payment_method'])) {
										require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
										$result = dol_getIdFromCode($this->db, $order_data['payment_method'], 'c_paiement', 'libelle', 'id');
										if ($result < 0) {
											$this->errors[] = $this->langs->trans('ECommerceErrorWhenFetchPaymentMethodByLabel', $order_data['payment_method']);
											$this->errors[] = $this->db->lasterror();
											$error++;
										} elseif ($result != '' && $result > 0) {
											$payment_method_id = $result;
										}
									}

									// Set payment mode
									if (!$error && $payment_method_id > 0) {
										$result = $invoice_refund->setPaymentMethods($payment_method_id);
										if ($result < 0) {
											$this->errors[] = $this->langs->trans('ECommerceErrorInvoiceSetPaymentMethods');
											if (!empty($invoice_refund->error)) $this->errors[] = $invoice_refund->error;
											$this->errors = array_merge($this->errors, $invoice_refund->errors);
											$error++;
										}
									}

									// Set bank account
									$bank_account_id = empty($order_data['extrafields']["ecommerceng_online_payment_{$conf->entity}"]) && $third_party->fk_account > 0 ? $third_party->fk_account : (!empty($selected_payment_gateways['bank_account_id']) ? $selected_payment_gateways['bank_account_id'] : 0);
									if (!$error) {
										if ($bank_account_id == 0 && $conf->banque->enabled && (!empty($selected_payment_gateways['create_invoice_payment']) || !empty($selected_payment_gateways['create_supplier_invoice_payment']))) {
											$this->errors[] = $this->langs->trans('ECommerceErrorPaymentGatewaysBankAccountNotConfigured', $order_data['payment_method_id'], $order_data['payment_method']);
											$error++;
										} elseif ($bank_account_id > 0) {
											$result = $invoice_refund->setBankAccount($bank_account_id);
											if ($result < 0) {
												$this->errors[] = $this->langs->trans('ECommerceErrorInvoiceSetBankAccount');
												if (!empty($invoice_refund->error)) $this->errors[] = $invoice_refund->error;
												$this->errors = array_merge($this->errors, $invoice_refund->errors);
												$error++;
											}
										}
									}

									// Get warehouse ID
									$warehouse_id = $this->eCommerceSite->parameters['order_actions']['valid_invoice_fk_warehouse'] > 0 ? $this->eCommerceSite->parameters['order_actions']['valid_invoice_fk_warehouse'] : 0;
									if (empty($warehouse_id) && empty($warehouseByLine) && !empty($conf->global->STOCK_CALCULATE_ON_BILL)) {
										$this->errors[] = $this->langs->trans('ECommerceErrorInvoiceValidateWarehouseNotConfigured');
										$error++;
									}

									// Validate invoice
									if (!$error) {
										$result = $invoice_refund->validate($this->user, '', $warehouse_id);
										if ($result < 0) {
											$this->errors[] = $this->langs->trans('ECommerceErrorInvoiceValidate');
											if (!empty($invoice_refund->error)) $this->errors[] = $invoice_refund->error;
											$this->errors = array_merge($this->errors, $invoice_refund->errors);
											$error++;
										} else {
											$result = $this->setLinesMovementStockOnDifferentWarehouse($invoice_refund, $warehouseByLine);
											if ($result < 0) {
												$this->errors[] = $this->langs->trans("ECommerceErrorWhenMovementStockOnDifferentWarehouse");
												$error++;
											}
										}
									}

									// Force set amount if another amount paid
									if (!$error) {
										$result = $this->fixTotalAmounts($invoice_refund, -$refund_info['total_ttc']);
										if ($result < 0) {
											$error++;
										}
									}

									// Creation of payment line
									if (!$error) {
										if ($invoice_refund->total_ttc != 0) {
											if ($conf->banque->enabled && !empty($selected_payment_gateways['create_invoice_payment'])) {
												$payment = new Paiement($this->db);
												$payment->datepaye = $invoice_refund->date;
												$payment->amounts = array($invoice_refund->id => $invoice_refund->total_ttc);   // Array with all payments dispatching with invoice id
												$payment->multicurrency_amounts = array();   // Array with all payments dispatching
												$payment->paiementid = $invoice_refund->mode_reglement_id;
												$payment->num_paiement = '';
												$payment->note = 'Created by WooSync';

												$payment_id = $payment->create($this->user, 1);
												if ($payment_id < 0) {
													$this->errors[] = $this->langs->trans('ECommerceErrorInvoiceCreatePayment');
													if (!empty($payment->error)) $this->errors[] = $payment->error;
													$this->errors = array_merge($this->errors, $payment->errors);
													$error++;
												} else {
													$result = $payment->addPaymentToBank($this->user, 'payment', '(CustomerInvoicePayment)', $bank_account_id, '', '');
													if ($result < 0) {
														$this->errors[] = $this->langs->trans('ECommerceErrorInvoiceAddPaymentToBank');
														if (!empty($payment->error)) $this->errors[] = $payment->error;
														$this->errors = array_merge($this->errors, $payment->errors);
														$error++;
													}
												}
											}
										}
										$invoice_refund->fetch($invoice_refund->id); // Reload to get new records
										if (empty($invoice_refund->total_ttc) || (empty($invoice_refund->paye) && ((string)$invoice_refund->getRemainToPay()) == 0)) {
											$result = $invoice_refund->set_paid($this->user);
											if ($result < 0) {
												$this->errors[] = $this->langs->trans('ECommerceErrorInvoiceSetPaid');
												if (!empty($invoice_refund->error)) $this->errors[] = $invoice_refund->error;
												$this->errors = array_merge($this->errors, $invoice_refund->errors);
												$error++;
											}
										}
									}

									// Generate document
									if (!$error && !empty($invoice_refund->modelpdf) && empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE)) {
										// Define output language
										$outputlangs = $this->langs;
										$newlang = '';
										if ($conf->global->MAIN_MULTILANGS && !empty($refund_info['language']) && $refund_info['language'] != 'ec_none') $newlang = $refund_info['language'];
										if (!empty($newlang)) {
											$outputlangs = new Translate("", $conf);
											$outputlangs->setDefaultLang($newlang);
											$outputlangs->load('products');
										}
										$invoice_refund->fetch($invoice_refund->id); // Reload to get new records

										$hidedetails = !empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DETAILS) ? 1 : 0;
										$hidedesc = !empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DESC) ? 1 : 0;
										$hideref = !empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_REF) ? 1 : 0;
										$result = $invoice_refund->generateDocument($invoice_refund->modelpdf, $outputlangs, $hidedetails, $hidedesc, $hideref);
										if ($result < 0) {
											$this->errors[] = $this->langs->trans('ECommerceErrorInvoiceGenerateDocument');
											if (!empty($invoice_refund->error)) $this->errors[] = $invoice_refund->error;
											$this->errors = array_merge($this->errors, $invoice_refund->errors);
											$error++;
										}
									}

//									// Auto send invoice by mail
//									if (!$error && !empty($this->eCommerceSite->parameters['order_actions']['send_invoice_by_mail'])) {
//										$send_to = trim($order_data['socpeopleCommande']['email']);
//										if (empty($send_to)) {
//											$this->errors[] = $this->langs->trans('ECommerceErrorCustomerEmailEmptyForSendInvoiceByEmail');
//											$error++;
//										} elseif (!($selected_payment_gateways['mail_model_for_send_invoice'] > 0)) {
//											$this->errors[] = $this->langs->trans('ECommerceErrorPaymentGatewaysMailModelNotConfigured', $order_data['payment_method_id'], $order_data['payment_method']);
//											$error++;
//										}
//
//										if (!$error) {
//											$ret = $invoice_refund->fetch($invoice_refund->id);
//											$ret = $invoice_refund->fetch_thirdparty();
//
//											require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
//											require_once DOL_DOCUMENT_ROOT . '/core/class/html.formmail.class.php';
//											$formmail = new FormMail($this->db);
//
//											$this->langs->load('mails');
//											$this->langs->load("commercial");
//											if (!empty($conf->dolimail->enabled)) $this->langs->load("dolimail@dolimail");
//
//											$trackid = 'inv' . $invoice_refund->id;
//											$deliveryreceipt = 0;
//
//											// from / send to / ...
//											$from = $conf->global->MAIN_INFO_SOCIETE_NOM . ' <' . $conf->global->MAIN_INFO_SOCIETE_MAIL . '>';
//											$sendtocc = '';
//											$sendtobcc = !empty($conf->global->MAIN_MAIL_AUTOCOPY_INVOICE_TO) ? $conf->global->MAIN_MAIL_AUTOCOPY_INVOICE_TO : '';
//
//											// Define output language
//											$outputlangs = $this->langs;
//                                          if ($conf->global->MAIN_MULTILANGS && !empty($refund_info['language']) && $refund_info['language'] != 'ec_none') $newlang = $refund_info['language'];
//											if (!empty($newlang)) {
//												$outputlangs = new Translate('', $conf);
//												$outputlangs->setDefaultLang($newlang);
//												$outputlangs->loadLangs(array('commercial', 'bills', 'orders', 'contracts', 'members', 'propal', 'products', 'supplier_proposal', 'interventions'));
//											}
//
//											// Make substitution in email content
//											$substitutionarray = getCommonSubstitutionArray($outputlangs, 0, null, $invoice_refund);
//											$substitutionarray['__CHECK_READ__'] = (is_object($invoice_refund) && is_object($invoice_refund->thirdparty)) ? '<img src="' . DOL_MAIN_URL_ROOT . '/public/emailing/mailing-read.php?tag=' . $invoice_refund->thirdparty->tag . '&securitykey=' . urlencode($conf->global->MAILING_EMAIL_UNSUBSCRIBE_KEY) . '" width="1" height="1" style="width:1px;height:1px" border="0"/>' : '';
//											$substitutionarray['__PERSONALIZED__'] = '';    // deprecated
//											$substitutionarray['__CONTACTCIVNAME__'] = '';
//											$parameters = array('mode' => 'formemail');
//											complete_substitutions_array($substitutionarray, $outputlangs, $invoice_refund, $parameters);
//
//											// Get email template
//											$type_template = 'facture_send';
//											$arraydefaultmessage = $formmail->getEMailTemplate($this->db, $type_template, $this->user, $outputlangs, $selected_payment_gateways['mail_model_for_send_invoice']);
//											if (is_numeric($arraydefaultmessage) && $arraydefaultmessage < 0) {
//												$this->errors[] = $this->langs->trans('ECommerceErrorGetEMailTemplate');
//												if (!empty($formmail->error)) $this->errors[] = $formmail->error;
//												else $this->errors[] = $this->db->lasterror();
//												$error++;
//											}
//
//											if (!$error) {
//												// Complete substitution array
//												if (empty($substitutionarray['__REF__'])) {
//													$paymenturl = '';
//												} else {
//													// Set the online payment url link into __ONLINE_PAYMENT_URL__ key
//													require_once DOL_DOCUMENT_ROOT . '/core/lib/payments.lib.php';
//													$outputlangs->load('paypal');
//													$paymenturl = getOnlinePaymentUrl(0, 'invoice', $substitutionarray['__REF__']);
//												}
//												$substitutionarray['__ONLINE_PAYMENT_URL__'] = $paymenturl;
//
//												// Define subject / message
//												$message = str_replace('\n', "\n", is_array($arraydefaultmessage) ? $arraydefaultmessage['content'] : $arraydefaultmessage->content);
//												// Deal with format differences between message and signature (text / HTML)
//												if (dol_textishtml($message) && !dol_textishtml($substitutionarray['__USER_SIGNATURE__'])) {
//													$substitutionarray['__USER_SIGNATURE__'] = dol_nl2br($substitutionarray['__USER_SIGNATURE__']);
//												} else if (!dol_textishtml($message) && dol_textishtml($substitutionarray['__USER_SIGNATURE__'])) {
//													$message = dol_nl2br($message);
//												}
//
//												$subject = make_substitutions(is_array($arraydefaultmessage) ? $arraydefaultmessage['topic'] : $arraydefaultmessage->topic, $substitutionarray);
//												$message = make_substitutions($message, $substitutionarray);
//												if (method_exists($invoice_refund, 'makeSubstitution')) {
//													$subject = $invoice_refund->makeSubstitution($subject);
//													$message = $invoice_refund->makeSubstitution($message);
//												}
//
//												// Clean first \n and br (to avoid empty line when CONTACTCIVNAME is empty)
//												$message = preg_replace("/^(<br>)+/", "", $message);
//												$message = preg_replace("/^\n+/", "", $message);
//
//												// Define $urlwithroot
//												global $dolibarr_main_url_root;
//												$urlwithouturlroot = preg_replace('/' . preg_quote(DOL_URL_ROOT, '/') . '$/i', '', trim($dolibarr_main_url_root));
//												$urlwithroot = $urlwithouturlroot . DOL_URL_ROOT;        // This is to use external domain name found into config file
//												//$urlwithroot=DOL_MAIN_URL_ROOT;					// This is to use same domain name than current
//												// Make a change into HTML code to allow to include images from medias directory with an external reabable URL.
//												// <img alt="" src="/dolibarr_dev/htdocs/viewimage.php?modulepart=medias&amp;entity=1&amp;file=image/ldestailleur_166x166.jpg" style="height:166px; width:166px" />
//												// become
//												// <img alt="" src="'.$urlwithroot.'viewimage.php?modulepart=medias&amp;entity=1&amp;file=image/ldestailleur_166x166.jpg" style="height:166px; width:166px" />
//												$message = preg_replace('/(<img.*src=")[^\"]*viewimage\.php([^\"]*)modulepart=medias([^\"]*)file=([^\"]*)("[^\/]*\/>)/', '\1' . $urlwithroot . '/viewimage.php\2modulepart=medias\3file=\4\5', $message);
//
//												// Attach invoice file
//												$formmail->trackid = $trackid;      // $trackid must be defined
//												$formmail->clear_attached_files();
//												if (!empty(is_array($arraydefaultmessage) ? $arraydefaultmessage['joinfiles'] : $arraydefaultmessage->joinfiles)) {
//													$ref = dol_sanitizeFileName($invoice_refund->ref);
//													$fileparams = dol_most_recent_file($conf->facture->dir_output . '/' . $ref, preg_quote($ref, '/') . '[^\-]+');
//													$file = $fileparams['fullname'];
//													$formmail->add_attached_files($file, basename($file), dol_mimetype($file));
//												}
//												$attachedfiles = $formmail->get_attached_files();
//												$filepath = $attachedfiles['paths'];
//												$filename = $attachedfiles['names'];
//												$mimetype = $attachedfiles['mimes'];
//
//												// Send mail (substitutionarray must be done just before this)
//												require_once DOL_DOCUMENT_ROOT . '/core/class/CMailFile.class.php';
//												$sendcontext = 'standard';
//												$mailfile = new CMailFile($subject, $send_to, $from, $message, $filepath, $mimetype, $filename, $sendtocc, $sendtobcc, $deliveryreceipt, -1, '', '', $trackid, '', $sendcontext);
//												if ($mailfile->error) {
//													$this->errors[] = $this->langs->trans('ECommerceErrorInvoiceCreateMail');
//													if (!empty($mailfile->error)) $this->errors[] = $mailfile->error;
//													$this->errors = array_merge($this->errors, $mailfile->errors);
//													$error++;
//												} else {
//													$result = $mailfile->sendfile();
//													if ($result) {
//														// Get order contacts
//														$contact_list = $invoice_refund->liste_contact(-1, 'external');
//														if (!is_array($contact_list)) {
//															$this->errors[] = $this->langs->trans('ECommerceErrorInvoiceGetExternalContacts', $invoice_refund->id);
//															if (!empty($invoice_refund->error)) $this->errors[] = $invoice_refund->error;
//															$this->errors = array_merge($this->errors, $invoice_refund->errors);
//															$error++;
//														} else {
//															// Event send email
//															$sendtoid = array();
//															foreach ($contact_list as $contact_infos) {
//																$sendtoid[$contact_infos['id']] = $contact_infos['id'];
//															}
//															$sendtoid = array_values($sendtoid);
//
//															$actionmsg = '';
//															$actionmsg2 = $this->langs->transnoentities('MailSentBy') . ' ' . CMailFile::getValidAddress($from, 4, 0, 1) . ' ' . $this->langs->transnoentities('To') . ' ' . CMailFile::getValidAddress($send_to, 4, 0, 1);
//															if ($message) {
//																$actionmsg = $this->langs->transnoentities('MailFrom') . ': ' . dol_escape_htmltag($from);
//																$actionmsg = dol_concatdesc($actionmsg, $this->langs->transnoentities('MailTo') . ': ' . dol_escape_htmltag($send_to));
//																$actionmsg = dol_concatdesc($actionmsg, $this->langs->transnoentities('MailTopic') . ": " . $subject);
//																$actionmsg = dol_concatdesc($actionmsg, $this->langs->transnoentities('TextUsedInTheMessageBody') . ":");
//																$actionmsg = dol_concatdesc($actionmsg, $message);
//															}
//
//															$invoice_refund->sendtoid = $sendtoid;       // To link to contacts/addresses. This is an array.
//															$invoice_refund->actiontypecode = 'AC_OTH_AUTO'; // Type of event ('AC_OTH', 'AC_OTH_AUTO', 'AC_XXX'...)
//															$invoice_refund->actionmsg = $actionmsg;      // Long text
//															$invoice_refund->actionmsg2 = $actionmsg2;     // Short text
//															$invoice_refund->trackid = $trackid;
//															$invoice_refund->fk_element = $invoice_refund->id;
//															$invoice_refund->elementtype = $invoice_refund->element;
//															if (is_array($attachedfiles) && count($attachedfiles) > 0) {
//																$invoice_refund->attachedfiles = $attachedfiles;
//															}
//
//															include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
//															$interface = new Interfaces($this->db);
//															$result = $interface->run_triggers('BILL_SENTBYMAIL', $invoice_refund, $this->user, $this->langs, $conf);
//															if ($result < 0) {
//																$this->errors[] = $this->langs->trans('ECommerceErrorInvoiceCreateSendMailEvent');
//																if (!empty($interface->error)) $this->errors[] = $interface->error;
//																$this->errors = array_merge($this->errors, $interface->errors);
//																$error++;
//															}
//														}
//													} else {
//														$this->langs->load("other");
//														$this->errors[] = $this->langs->trans('ECommerceErrorInvoiceSendByMail');
//														if ($mailfile->error) {
//															$this->errors[] = $this->langs->trans('ErrorFailedToSendMail', $from, $send_to);
//															$this->errors[] = $mailfile->error;
//														} else {
//															$this->errors[] = ' No mail sent. Feature is disabled by option MAIN_DISABLE_ALL_MAILS';
//														}
//														$error++;
//													}
//												}
//											}
//										}
//									}
								} elseif (empty($this->eCommerceSite->parameters['order_actions']['create_order'])) {
									$this->warnings[] = $this->langs->trans('ECommerceWarningOrderThirdPartyNotSupported', $order_data['remote_id'], $order_data['remote_id_societe']);
									$bypass = true;
								}
							}
						}

						// Update the link of the synchronization
						//--------------------------------------------
						if (!$error && !$bypass) {
							$this->eCommerceCommande->last_update = $order_data['last_update'];
							$this->eCommerceCommande->fk_commande = $order->id > 0 ? $order->id : ($invoice_refund->id > 0 ? -$invoice_refund->id : 0);

							// Update link
							if ($this->eCommerceCommande->id > 0) {
								$result = $this->eCommerceCommande->update($this->user);
								if ($result < 0) {
									$this->errors[] = $this->langs->trans('ECommerceErrorUpdateOrderLink');
								}
							} // Create link
							else {
								$this->eCommerceCommande->fk_site = $this->eCommerceSite->id;
								$this->eCommerceCommande->remote_id = $order_data['remote_id'];
								$result = $this->eCommerceCommande->create($this->user);
								if ($result < 0) {
									$this->errors[] = $this->langs->trans('ECommerceErrorCreateOrderLink');
								}
							}
							if ($result < 0) {
								$this->errors = array_merge($this->errors, $this->eCommerceCommande->errors);
								$error++;
							}
						}

						$last_sync_date = 'ECOMMERCE_LAST_SYNC_DATE_ORDER_' . $this->eCommerceSite->id;
						if (!$error && (!is_numeric($conf->global->$last_sync_date) || $conf->global->$last_sync_date < $order_data['create_date'])) {
							$result = dolibarr_set_const($this->db, $last_sync_date, $order_data['create_date'], 'chaine', 0, '', $conf->entity);
							if ($result < 0) {
								$this->errors[] = $this->langs->trans('ECommerceErrorSetLastSyncDateOrder');
								$this->errors[] = $this->db->lasterror();
								$error++;
							}
						}


					}
				}
			} catch (Exception $e) {
				$this->errors[] = $e->getMessage();
				$error++;
			}

			// Commit / rollback actions
			if ($error) {
				$this->db->rollback();
			} else {
				$this->db->commit();
			}
		}

		if ($error) {
			$this->errors = array_merge(array($this->langs->trans('ECommerceErrorWhenSynchronizeOrderToInvoice', $order_data['remote_id'])), $this->errors);
			dol_syslog(__METHOD__ . ' Error=' . $this->errorsToString(), LOG_ERR);
			return -1;
		} else {
			return $invoice_refund->id > 0 ? $invoice_refund->id : 0;
		}
	}

	/**
	 * Force set amount if another amount paid
	 *
	 * @param	CommonObject	$object			Object to fix
	 * @param	double			$total_ttc		Total TTC
	 * @return	int								<0 if KO, >0 if OK
	 */
	public function fixTotalAmounts(&$object, $total_ttc)
	{
		global $conf;
		$infV14 = (version_compare(DOL_VERSION, "14.0.0") < 0);

		$total_ttc = price2num($total_ttc);
		$current_total_ttc_origin = $current_total_ttc = price2num($object->total_ttc);

//		// Calcul total with mode 1 (total of rounding)
//		if ($total_ttc != $current_total_ttc) {
//			$object->update_price(1, 0);
//			$current_total_ttc = price2num($object->total_ttc);
//		}
//
//		// Calcul total with mode 2 (rounding of total)
//		if ($total_ttc != $current_total_ttc) {
//			$object->update_price(1, 1);
//			$current_total_ttc = price2num($object->total_ttc);
//		}

		// Force set amount if another amount paid
		if ($total_ttc != $current_total_ttc) {
			$error = 0;
			$this->db->begin();

			$delta_fix = !empty($conf->global->ECOMMERCE_DELTA_FIX_TOTAL_AMOUNTS) && $conf->global->ECOMMERCE_DELTA_FIX_TOTAL_AMOUNTS > 0 ? $conf->global->ECOMMERCE_DELTA_FIX_TOTAL_AMOUNTS : 0.1;
			$delta = $current_total_ttc_origin - $total_ttc;
			if (abs($delta) < $delta_fix) {
				// Force the invoice virtuality at draft
				$save_statut = $object->statut;
				$save_status = $object->status;
				$save_brouillon = $object->brouillon;
				$object->statut = $object->status = 0;
				$object->brouillon = 1;

				$object->fetch_lines();
				if (is_array($object->lines)) {
					foreach ($object->lines as $line) {
						if ($line->fk_product > 0) {
							$price = ((($line->total_ttc - $delta) / (1 - ($line->remise_percent / 100))) * 100 / (100 + $line->tva_tx)) / $line->qty;

							if ($object->element == 'facture') {
								/**
								 * @var Facture $object
								 * @var FactureLigne $line
								 */
								$result = $object->updateline($line->id, $line->desc, $price, $line->qty,
									$line->remise_percent, $line->date_start, $line->date_end,
									$line->tva_tx, $line->localtax1_tx, $line->localtax2_tx, 'HT', $line->info_bits,
									$line->product_type, $line->fk_parent_line, 0, $line->fk_fournprice, $line->pa_ht,
									$line->label, $line->special_code, $line->array_options, $line->situation_percent,
									$line->fk_unit, $line->multicurrency_subprice, 0, $line->ref_ext, $line->rang);
							} else { // if ($object->element == 'commande')
								/**
								 * @var Commande $object
								 * @var OrderLine $line
								 */
								$result = $object->updateline(
									$line->id, $line->desc, $price, $line->qty, $line->remise_percent,
									$line->tva_tx, $line->localtax1_tx, $line->localtax2_tx, 'HT',
									$line->info_bits, $line->date_start, $line->date_end,
									$line->product_type, $line->fk_parent_line, 0,
									$line->fk_fournprice, $line->pa_ht, $line->label, $line->special_code,
									$line->array_options, $line->fk_unit, $line->multicurrency_subprice,
									0, $line->ref_ext);
							}
							if ($result < 0) {
								$this->errors[] = $this->langs->trans('ECommerceErrorSetCorrectAmountLine');
								$this->errors[] = $object->errorsToString();
								$error++;
							}
							break;
						}
					}
				}

				$object->statut = $save_statut;
				$object->status = $save_status;
				$object->brouillon = $save_brouillon;
			}

			$sql = "UPDATE " . MAIN_DB_PREFIX . $object->table_element .
				" SET total_ttc = " . $total_ttc .
				" WHERE rowid = " . $object->id;
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->errors[] = $this->langs->trans('ECommerceErrorSetCorrectAmount');
				$this->errors[] = $this->db->lasterror();
				$error++;
			} else {
				$object->total_ttc = $total_ttc;
			}

			if ($error) {
				$this->db->rollback();
				return -1;
			} else {
				$this->db->commit();
			}
		}

		return 1;
	}

	/**
	 * Add all items line into the specified object (order, invoice)
	 *
	 * @param	CommonObject	$object							Object to add items line
	 * @param	array			$items							List of item product
	 * @param	array			$order_data						Converted data of the remote order
	 * @param	array			$warehouseByLine				Warehouse used for each line
	 * @param	boolean			$dont_synchronize_products		Don't synchronise products
	 * @return	int												<0 if KO, >0 if OK
	 */
	public function addProductLines(&$object, $items, $order_data, &$warehouseByLine, $dont_synchronize_products = false)
	{
		global $conf;

		$error = 0;
		$this->db->begin();

		$isDepositType = $object->element == 'facture' && $object->type == Facture::TYPE_DEPOSIT;
		$typeAmount = isset($this->eCommerceSite->parameters['order_actions']['create_invoice_deposit_type']) ? $this->eCommerceSite->parameters['order_actions']['create_invoice_deposit_type'] : '';
		$valueDeposit = isset($this->eCommerceSite->parameters['order_actions']['create_invoice_deposit_value']) ? $this->eCommerceSite->parameters['order_actions']['create_invoice_deposit_value'] : 0;

		if ($isDepositType && in_array($typeAmount, array('amount', 'variable'))) {
			/**
			 * @var Facture	$object
			 */
			$amountdeposit = array();
			$order_total_ttc = price2num($order_data['payment_amount_ttc'], 'MT');

			if (!empty($conf->global->MAIN_DEPOSIT_MULTI_TVA)) {
				if ($typeAmount == 'amount') $amount = $order_total_ttc;
				else $amount = $order_total_ttc * ($valueDeposit / 100);

				$TTotalByTva = array();
				foreach ($items as $item) {
					$TTotalByTva[$item['tva_tx']] += $item['total_ttc'];
				}

				foreach ($TTotalByTva as $tva => &$total) {
					$coef = $total / $order_total_ttc; // Calc coef
					$am = $amount * $coef;
					$amountdeposit[$tva] += $am / (1 + $tva / 100); // Convert into HT for the addline
				}
			} else {
				if ($typeAmount == 'amount') {
					$amountdeposit[0] = $order_total_ttc;
				} elseif ($typeAmount == 'variable') {
					$totalamount = 0;
					foreach ($items as $item) {
						$qualified = 1;
						if (empty($item['qty'])) $qualified = 0; // We discard qty=0, it is an option
						if ($qualified) {
							$totalamount += $item['total_ht']; // Fixme : is it not for the customer ? Shouldn't we take total_ttc ?
							$tva_tx = $item['tva_tx'];
							$amountdeposit[$tva_tx] += ($item['total_ht'] * $valueDeposit) / 100;
						}
					}

					if ($totalamount == 0) {
						$amountdeposit[0] = 0;
					}
				}
			}

			$array_options = $this->getDefaultExtraFields('facturedet', $this->eCommerceSite);

			foreach ($amountdeposit as $tva => $amount) {
				if (empty($amount)) continue;

				$descline = '(DEPOSIT)';
				if ($typeAmount == 'amount') {
					$descline .= ' (' . price($order_total_ttc, '', $this->langs, 0, -1, -1, (!empty($object->multicurrency_code) ? $object->multicurrency_code : $conf->currency)) . ')';
				} elseif ($typeAmount == 'variable') {
					$descline .= ' (' . $valueDeposit . '%)';
				}
				$descline .= ' - ' . $order_data['ref_client'];

				// Add deposit line
				$result = $object->addline($descline, $amount, 1, $tva, 0, 0,
					(empty($conf->global->INVOICE_PRODUCTID_DEPOSIT) ? 0 : $conf->global->INVOICE_PRODUCTID_DEPOSIT),
					0, '', '', 0, 0, '', 'HT', 0, $object::TYPE_STANDARD,
					-1, 0, '', 0, 0, null, 0, '', $array_options);
				if ($result < 0) {
					$this->errors[] = $this->langs->trans('ECommerceErrorInvoiceAddLine');
					$this->errors[] = $object->errorsToString();
					$error++;
					break;
				}
			}
		} elseif (is_array($items)) {
			// Synchronize all the remote product
			if (empty($conf->global->ECOMMERCENG_DISABLED_PRODUCT_SYNCHRO_STOD) && !$dont_synchronize_products) {
				// Get products to synchronize
				$remote_id_to_synchronize = array();
				foreach ($items as $item) {
					if (!empty($item['id_remote_product'])) {
						$remote_id_to_synchronize[] = str_replace('|%', '', $item['id_remote_product']);
					}
				}
				// Synchronize products
				if (!empty($remote_id_to_synchronize)) {
					// Todo We don't change stock here, even if dolibarr option is on because, this should be already done by product sync ?
					$result = $this->synchronizeProducts(null, null, $remote_id_to_synchronize, count($remote_id_to_synchronize), false, $object);
					if ($result < 0) {
						$error++;
					}
				}
			}

			// If we create a deposit with all lines and a percent, we change the quantity
			if ($isDepositType && $typeAmount == 'variablealllines') {
				foreach ($items as $k => $item) {
					// We keep 'price', but we change the 'qty'
					$items[$k]['qty'] = price2num($item['qty'] * $valueDeposit / 100, 'MS');
				}
			}

			$parent_match = array();
			foreach ($items as $item) {
				// Get product ID
				$fk_product = 0;
				if (!empty($item['id_remote_product'])) {
					if (empty($conf->global->ECOMMERCENG_DISABLED_PRODUCT_SYNCHRO_STOD)) {
						$this->initECommerceProduct();
						$result = $this->eCommerceProduct->fetchByRemoteId($item['id_remote_product'], $this->eCommerceSite->id); // load info of table ecommerce_product
						if ($result < 0 && !empty($this->eCommerceProduct->error)) {
							$this->errors[] = $this->langs->trans('ECommerceErrorFetchProductLinkByRemoteId', $item['id_remote_product'], $this->eCommerceSite->id);
							$this->errors[] = $this->eCommerceProduct->error;
							$error++;
							break;  // break on items
						} elseif ($result > 0) {
							$fk_product = $this->eCommerceProduct->fk_product;
						}
						unset($this->eCommerceProduct);
					} else {
						$product_ref = trim($item['ref']);
						if (!empty($product_ref)) {
							$product = new Product($this->db);
							$result = $product->fetch(0, $product_ref);
							if ($result < 0) {
								$this->errors[] = $this->langs->trans('ECommerceErrorFetchProductByRef', $product_ref);
								$this->errors[] = $product->errorsToString();
								$error++;
								break;  // break on items
							} elseif ($result == 0) {
								$this->errors[] = $this->langs->trans('ECommerceErrorProductNotFoundByRef', $product_ref);
								$error++;
								break;  // break on items
							}
							$fk_product = $product->id;
						} elseif (!empty($conf->global->ECOMMERCENG_PRODUCT_REF_MANDATORY)) {
							$this->errors[] = $this->langs->trans('ECommerceErrorProductRefMandatory2', $item['label']);
							$error++;
							break;  // break on items
						}
					}
				} elseif (!empty($item['id_product'])) {
					$fk_product = $item['id_product'];
				}

				if (!$error) {
					$price = $item['price'];
					$total_ht = $item['total_ht'];
					$total_tva = $item['total_tva'];
					$total_ttc = $item['total_ttc'];
					$discount = $item['discount'];
					$product_type = $item['product_type'] != "simple" ? 1 : 0;
					$label = !empty($item['label']) ? $item['label'] : '';
					$description = $item['description'];
					if (empty($description) && $fk_product > 0) {
						$product = new Product($this->db);
						$product->fetch($fk_product);
						$description = $product->description;
					}
					$description = dol_concatdesc($description, $item['additional_description']);

					// Define the buy price for margin calculation
					if (isset($item['buy_price'])) {
						$buy_price = $item['buy_price'];
					} else {
						$buy_price = 0;
						if ($fk_product > 0) {
							$result = $object->defineBuyPrice(0, 0, $fk_product);
							if ($result < 0) {
								$this->errors[] = $this->langs->trans('ECommerceErrorDefineBuyPrice', $fk_product, $buy_price);
								$this->errors[] = $object->errorsToString();
								$error++;
								break;    // break on items
							} else {
								$buy_price = $result;
							}
						}
						if (empty($buy_price) && isset($conf->global->ForceBuyingPriceIfNull) && $conf->global->ForceBuyingPriceIfNull > 0) {
							$buy_price = $item['price'];
						}
					}

					if ($object->element == 'commande') {
						$table_line = 'commandedet';
					} else { // if ($object->element == 'facture')
						$table_line = 'facturedet';
					}
					$array_options = $this->getDefaultExtraFields($table_line, $this->eCommerceSite);
					if (is_array($item['extrafields'])) {
						foreach ($item['extrafields'] as $extrafield => $extrafield_value) {
							$array_options['options_' . $extrafield] = $extrafield_value;
						}
					}

					$fk_parent_line = isset($parent_match[$item['parent_item_id']]) ? $parent_match[$item['parent_item_id']] : 0;
					if ($object->element == 'commande') {
						/**
						 * @var Commande	$object
						 */
						$result = $object->addline($description, $price, $item['qty'], $item['tva_tx'], $item['local_tax1_tx'], $item['local_tax2_tx'],
							$fk_product, $discount, 0, 0, 'HT', 0, '', '',
							$product_type, -1, 0, $fk_parent_line, 0, $buy_price, $label, $array_options,
							0, '', 0, 0);
					} else { // if ($object->element == 'facture')
						/**
						 * @var Facture	$object
						 */
						$result = $object->addline($description, $price, $item['qty'], $item['tva_tx'], $item['local_tax1_tx'], $item['local_tax2_tx'],
							$fk_product, $discount, '', '', 0, 0, '', 'HT',
							0, $product_type, -1, 0, '', 0, $fk_parent_line, null, $buy_price,
							$label, $array_options, 100, 0, null, 0, '');
					}
					if ($result <= 0) {
						$this->errors[] = $this->langs->trans('ECommerceErrorAddLine');
						$this->errors[] = $object->errorsToString();
						$error++;
						break;  // break on items
					}
					$parent_match[$item['item_id']] = $result;

					// Set import_key on the line with remote line ID
					if ($item['type'] == 'product' && $item['item_id'] > 0) {
						$sql = "UPDATE " . MAIN_DB_PREFIX . $object->table_element_line .
							" SET import_key = '" . $this->db->escape($item['item_id']) . "'" .
							" WHERE rowid = " . $result;
						$resql = $this->db->query($sql);
						if (!$resql) {
							$this->errors[] = $this->langs->trans('ECommerceErrorSetRemoteProductLineIdIntoImportKey');
							$this->errors[] = $this->db->lasterror();
							$error++;
							break;  // break on items
						}
					}

					// Support movement stock different by product
					if ($fk_product > 0 && !empty($item['remote_warehouse_id']) && $item['remote_warehouse_id'] > 0) {
						$warehouseByLine[$result] = $item['remote_warehouse_id'];
					}
				}
			}

			if (method_exists($object, 'fetch_lines')) $object->fetch_lines();
			elseif (method_exists($object, 'getLinesArray')) $object->getLinesArray();
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
	 * Add all contacts into the specified object (order, invoice)
	 *
	 * @param	CommonObject	$object						Object to add items line
	 * @param	array			$contact_data				Converted data of the remote contact
	 * @param	array			$order_data					Converted data of the remote order
	 * @param	string			$contact_type				Contact type (SHIPPING, CUSTOMER, ...)
	 * @param	boolean			$update_object_socid		Update third party of the object if different
	 * @param	bool			$new_object					Is new created object ?
	 * @return	int											<0 if KO, >0 if OK
	 */
	public function searchAndAddContact(&$object, &$contact_data, $order_data, $contact_type, $update_object_socid = false, $new_object = true)
	{
		global $conf;

		$error = 0;
		$this->db->begin();

		// Search or create the third party for customer contact
		$result = $this->getContactInfosFromData($contact_data, $object->socid);
		if (!is_array($result) || empty($result) || !($result['company_id'] > 0)) $result = $this->getContactInfosFromData($contact_data);
		if (!is_array($result)) {
			$error++;
		} elseif (!empty($result) && $result['company_id'] > 0) {
			$contact_data['fk_soc'] = $result['company_id'];
		} else {
			if (empty($contact_data['company'])) {
				if (!empty($contact_data['firstname']) && !empty($contact_data['lastname'])) {
					$third_party_name = dolGetFirstLastname($contact_data['firstname'], $contact_data['lastname']);
				} elseif (!empty($contact_data['firstname'])) {
					$third_party_name = dolGetFirstLastname($contact_data['firstname'], $this->langs->transnoentitiesnoconv("ECommerceLastNameNotInformed"));
				} else {
					$third_party_name = $this->langs->transnoentitiesnoconv('ECommerceFirstNameLastNameNotInformed');
				}
			} else {
				$third_party_name = $contact_data['company'];
			}
			$result = $this->getThirdPartyByInfos($contact_data['email'], $third_party_name, $contact_data['zip']);
			if ($result < 0) {
				$error++;
			} elseif ($result > 0) {
				$contact_data['fk_soc'] = $result;
			} else {
				$result = $this->createThirdParty($order_data['ref_client'],
					$contact_data['company'], $contact_data['firstname'], $contact_data['lastname'],
					$contact_data['address'], $contact_data['zip'], $contact_data['town'],
					$contact_data['country_id'], $contact_data['email'], $contact_data['phone'], $contact_data['fax']);
				if ($result < 0) {
					$error++;
				} elseif ($result > 0) {
					$contact_data['fk_soc'] = $result;
				}
			}
		}

		// Add / Update customer contact
		if (!$error) {
			$result = $this->synchSocpeople($contact_data);
			if ($result > 0) {
				$result = $this->addUpdateContact($object, $new_object, $result, $contact_type);
				if ($result < 0) {
					$error++;
				} elseif ($update_object_socid && $contact_data['fk_soc'] > 0 && $third_party_id != $contact_data['fk_soc']) {
					// Update thirdparty of the order
					$object->socid = $contact_data['fk_soc'];
					$result = $object->update($this->user);
					if ($result < 0) {
						$this->errors[] = $this->langs->trans('ECommerceErrorUpdateThirdParty');
						$this->errors[] = $object->errorsToString();
						$error++;
					}
				}
			} else {
				$this->errors = array_merge(array($this->langs->trans('ECommerceErrorWhenSynchronizeContact')), $this->errors);
				$error++;
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
	 * Add all contacts into the specified object (order, invoice)
	 *
	 * @param	CommonObject	$object			Object to add items line
	 * @param	array			$order_data		Converted data of the remote order
	 * @param	bool			$new_object		Is new created object ?
	 * @return	int								<0 if KO, >0 if OK
	 */
	public function addContacts(&$object, &$order_data, $new_object = true)
	{
		global $conf;

		$error = 0;
		$this->db->begin();

		$result = $this->searchAndAddContact($object, $order_data['socpeopleCommande'], $order_data, 'CUSTOMER', true, $new_object);
		if ($result < 0) {
			$error++;
		}

		if (!$error) {
			$result = $this->searchAndAddContact($object, $order_data['socpeopleFacture'], $order_data, 'BILLING', false, $new_object);
			if ($result < 0) {
				$error++;
			}
		}

		if (!$error) {
			$result = $this->searchAndAddContact($object, $order_data['socpeopleLivraison'], $order_data, 'SHIPPING', false, $new_object);
			if ($result < 0) {
				$error++;
			}
		}

		// Add sales contact
		if (!$error && $this->eCommerceSite->parameters['default_sales_representative_follow'] > 0 && $new_object) { // Todo update this contact when update order ?
			$result = $this->addUpdateContact($object, $new_order, $this->eCommerceSite->parameters['default_sales_representative_follow'], 'SALESREPFOLL', 'internal');
			if ($result < 0) {
				$error++;
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
	 * Get third party by email
	 *
	 * @param	string		$email			Third party email
	 * @param	int			$site_id		Site ID
	 * @return	int							<0 if KO, =0 if not found, otherwise the third party ID
	 */
	public function getThirdPartyByEmail($email, $site_id = 0)
	{
		$email = trim($email);

		if (empty($email)) {
			return 0;
		}

		// Search by email
		$email = $this->db->escape($email);

		$sql = "SELECT DISTINCT s.rowid FROM " . MAIN_DB_PREFIX . "societe AS s";
		$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "socpeople AS sp ON sp.fk_soc = s.rowid";
		if ($site_id > 0) $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "ecommerce_societe AS es ON es.fk_societe = s.rowid";
		$sql .= " WHERE (s.email = '$email' OR sp.email = '$email')";
		if ($site_id > 0) $sql .= " AND es.fk_site = $site_id";
		$sql .= " AND s.status = 1";
		$sql .= " AND s.entity IN (" . getEntity('societe') . ")";

		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__ . ' SQL: ' . $sql . '; Error: ' . $this->db->lasterror(), LOG_ERR);
			$this->errors[] = $this->langs->trans('ECommerceErrorSearchThirdPartyByEmail', $email, $site_id);
			$this->errors[] = $this->db->error();
			return -1;
		}

		if ($this->db->num_rows($resql) > 1) {
			$this->errors[] = $this->langs->trans('ECommerceErrorTooManyThirdPartyFoundDueToEmail', $email, $site_id);
			return -2;
		}

		if ($obj = $this->db->fetch_object($resql)) {
			return $obj->rowid;
		}

		$this->db->free($resql);

		return 0;
	}

	/**
	 * Get first third party who not anonymous by email
	 *
	 * @param	string		$email			Third party email
	 * @param	int			$site_id		Site ID
	 * @return	int							<0 if KO, =0 if not found, otherwise the third party ID
	 */
	public function getFirstThirdPartyWhoNotAnonymousByEmail($email, $site_id = 0)
	{
		$email = trim($email);

		if (empty($email)) {
			return 0;
		}

		// Search by email
		$email = $this->db->escape($email);

		$sql = "SELECT DISTINCT s.rowid FROM " . MAIN_DB_PREFIX . "societe AS s";
		$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "socpeople AS sp ON sp.fk_soc = s.rowid";
		if ($site_id > 0) $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "ecommerce_societe AS es ON es.fk_societe = s.rowid";
		$sql .= " WHERE (s.email = '$email' OR sp.email = '$email')";
		if ($this->eCommerceSite->fk_anonymous_thirdparty > 0) $sql .= " AND s.rowid != " . $this->eCommerceSite->fk_anonymous_thirdparty;
		if ($site_id > 0) $sql .= " AND es.fk_site = $site_id";
		$sql .= " AND s.status = 1";
		$sql .= " AND s.entity IN (" . getEntity('societe') . ")";

		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__ . ' SQL: ' . $sql . '; Error: ' . $this->db->lasterror(), LOG_ERR);
			$this->errors[] = $this->langs->trans('ECommerceErrorSearchThirdPartyByEmail', $email, $site_id);
			$this->errors[] = $this->db->error();
			return -1;
		}

		if ($obj = $this->db->fetch_object($resql)) {
			return $obj->rowid;
		}

		$this->db->free($resql);

		return 0;
	}

	/**
	 * Get third party by name
	 *
	 * @param	string		$name			Third party name
	 * @param	string		$zip			Third party zip code
	 * @param	int			$site_id		Site ID
	 * @return	int							<0 if KO, =0 if not found, otherwise the third party ID
	 */
	public function getThirdPartyByNameAndZip($name, $zip = '', $site_id = 0)
	{
		if (empty($name) || !empty($this->eCommerceSite->parameters['dont_search_company_by_name_and_zip'])) {
			return 0;
		}

		// Search by name
		$name = $this->db->escape($name);
		$zip = $this->db->escape($zip);

		$sql = "SELECT DISTINCT s.rowid FROM " . MAIN_DB_PREFIX . "societe AS s";
		if ($site_id > 0) $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "ecommerce_societe AS es ON es.fk_societe = s.rowid";
		$sql .= " WHERE (s.nom = '$name' OR s.name_alias = '$name')";
		if (!empty($zip)) $sql .= " AND s.zip = '$zip'";
		else  $sql .= " AND (s.zip IS NULL OR s.zip = '')";
		if ($site_id > 0) $sql .= " AND es.fk_site = $site_id";
		$sql .= " AND s.status = 1";
		$sql .= " AND s.entity IN (" . getEntity('societe') . ")";

		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__ . ' SQL: ' . $sql . '; Error: ' . $this->db->lasterror(), LOG_ERR);
			$this->errors[] = $this->langs->trans('ECommerceErrorWhenSearchThirdPartyByName', $name, $site_id);
			$this->errors[] = $this->db->error();
			return -1;
		}

		if ($this->db->num_rows($resql) > 1) {
			$this->errors[] = $this->langs->trans('ECommerceErrorTooManyThirdPartyFoundDueToName', $name, $site_id);
			return -2;
		}

		if ($obj = $this->db->fetch_object($resql)) {
			// Check is the third party found is the anonymous third party
			if ($this->eCommerceSite->fk_anonymous_thirdparty == $obj->rowid) {
				$this->errors[] = $this->langs->trans('ECommerceErrorThirdPartyFoundDueToNameIsTheAnonymousThirdParty');
				return -3;
			}

			return $obj->rowid;
		}

		$this->db->free($resql);

		return 0;
	}

	/**
	 * Get third party by email otherwise by name
	 *
	 * @param	string		$email			Third party email
	 * @param	string		$name			Third party name
	 * @param	string		$zip			Third party zip code
	 * @param	int			$site_id		Site ID
	 * @return	int							<0 if KO, =0 if not found, otherwise the third party ID
	 */
	public function getThirdPartyByInfos($email, $name = '', $zip = '', $site_id = 0)
	{
		// Search by email
		$result = $this->getThirdPartyByEmail($email, $site_id);
		// Search by name and zip
		if ($result == 0) $result = $this->getThirdPartyByNameAndZip($name, $zip, $site_id);

		return $result;
	}

	/**
	 *  Get contact infos (contact id and company id) from data
	 *
	 * @param	array			$contact_data		Contact datas
	 * @param	int				$company_id			Specified company
	 * @return	array|int							<0 if KO, otherwise empty array if not found or if found array('contact_id' => xxx, 'company_id' => xxx)
	 */
	public function getContactInfosFromData($contact_data, $company_id = 0)
	{
		global $conf;

		// Clean parameters
		$socid = $contact_data['fk_soc'];
		$lastname = trim($contact_data['lastname']);
		$firstname = trim($contact_data['firstname']);
		if (!empty($conf->global->ECOMMERCENG_UPPERCASE_LASTNAME)) {
			$firstname = dol_ucwords(dol_strtolower($firstname));
			$lastname = dol_strtoupper($lastname);
		}
		if (!empty($conf->global->MAIN_FIRST_TO_UPPER)) $lastname = ucwords($lastname);
		if (!empty($conf->global->MAIN_FIRST_TO_UPPER)) $firstname = ucwords($firstname);
		$address = $contact_data['address'];
		if (version_compare(DOL_VERSION, '6.0') >= 0) {
			$zip = dol_trunc($contact_data['zip'], 25, 'right', 'UTF-8', 1);
		} else {
			$zip = dol_trunc($contact_data['zip'], 10, 'right', 'UTF-8', 1);
		}
		$town = dol_trunc($contact_data['town'], 30, 'right', 'UTF-8', 1);
		$country_id = $contact_data['country_id'];
		$email = trim($contact_data['email']);
		$phone_pro = trim(dol_trunc($contact_data['phone'], 30, 'right', 'UTF-8', 1));
		$fax = trim(dol_trunc($contact_data['fax'], 30, 'right', 'UTF-8', 1));

		$sql = 'SELECT rowid, fk_soc FROM ' . MAIN_DB_PREFIX . 'socpeople';
		$sql .= ' WHERE lastname LIKE "' . $this->db->escape($lastname) . '"';
		$sql .= ' AND firstname LIKE "' . $this->db->escape($firstname) . '"';
		$sql .= ' AND address LIKE "' . $this->db->escape($address) . '"';
		$sql .= ' AND town LIKE "' . $this->db->escape($town) . '"';
		$sql .= ' AND zip LIKE "' . $this->db->escape($zip) . '"';
		if (isset($contact->country_id)) $sql .= ' AND fk_pays' . ($country_id > 0 ? ' = ' . $country_id : ' IS NULL');
		if (isset($contact->email)) $sql .= ' AND email LIKE "' . $this->db->escape($email) . '"';
		if (isset($contact->phone_pro)) $sql .= ' AND phone LIKE "' . $this->db->escape($phone_pro) . '"';
		if (isset($contact->fax)) $sql .= ' AND fax LIKE "' . $this->db->escape($fax) . '"';
		if ($company_id > 0) {
			$sql .= ' AND fk_soc = ' . $this->db->escape($company_id);
		} else {
			if ($socid > 0) $sql .= ' AND fk_soc = ' . $this->db->escape($socid);
			else if ($socid == -1) $sql .= " AND fk_soc IS NULL";
		}
		$sql .= ' AND entity IN ('.getEntity('socpeople').')';

		$resql = $this->db->query($sql);
		if ($resql) {
			$result = array();

			if ($obj = $this->db->fetch_object($resql)) {
				$result = array('contact_id' => $obj->rowid, 'company_id' => $obj->fk_soc);
			}

			$this->db->free($resql);

			return $result;
		} else {
			$this->error = $this->db->lasterror();
			dol_syslog(__METHOD__ . " - Error: " . $this->error, LOG_ERR);
			return -1;
		}
	}

	/**
	 *  Create a third party
	 *
	 * @param	string		$order_customer_ref		Customer ref of the remote order
	 * @param	string		$company				Company name
	 * @param	string		$firstname				First name
	 * @param	string		$lastname				Last name
	 * @param	string		$address				Address
	 * @param	string		$zip					Zip
	 * @param	string		$town					Town
	 * @param	int			$country_id				Country ID
	 * @param	string		$email					Email
	 * @param	string		$phone					Phone
	 * @param	string		$fax					Fax
	 * @return	int									<0 if KO, otherwise the third party ID
	 */
	public function createThirdParty($order_customer_ref, $company, $firstname, $lastname, $address, $zip, $town, $country_id, $email, $phone, $fax)
	{
		global $conf, $mysoc;

		// Create company
		$third_party = new Societe($this->db);
		$third_party->client = 1;
		if (empty($company)) {
			if (!empty($firstname) && !empty($lastname)) {
				$third_party->name = dolGetFirstLastname($firstname, $lastname);
			} elseif (!empty($firstname)) {
				$third_party->name = dolGetFirstLastname($firstname, $this->langs->transnoentitiesnoconv("ECommerceLastNameNotInformed"));
			} else {
				$third_party->name = $this->langs->transnoentitiesnoconv('ECommerceFirstNameLastNameNotInformed');
			}
			$third_party->typent_code = 'TE_PRIVATE';
			$third_party->typent_id = dol_getIdFromCode($this->db, $third_party->typent_code, 'c_typent', 'code', 'id');
			if (!($third_party->typent_id > 0)) {
				$this->errors[] = $this->langs->trans('ECommerceErrorPrivateThirdPartyTypeNotFound');
				return -1;
			}
		} else {
			$third_party->name = $company;
		}
		$third_party->address = $address;
		$third_party->zip = $zip;
		$third_party->town = $town;
		$third_party->country_id = $country_id;
		$third_party->default_lang = $country_id == $mysoc->country_id ? $mysoc->default_lang : (!empty($conf->global->ECOMMERCENG_DEFAULT_LANG_OTHER_COUNTRY) ? $conf->global->ECOMMERCENG_DEFAULT_LANG_OTHER_COUNTRY : null);
		$third_party->email = $email;
		$third_party->phone = $phone;
		$third_party->fax = $fax;
		$third_party->code_client = -1;           // Automatic code
		$third_party->code_fournisseur = -1;      // Automatic code
		$third_party->array_options = $this->getDefaultExtraFields($third_party->table_element, $this->eCommerceSite);

		$result = $third_party->create($this->user);
		if ($result < 0) {
			$this->errors[] = $this->langs->trans('ECommerceErrorCreateThirdParty') . ' - Name: ' . $company . ' -  First name: ' . $firstname . ' -  Last name: ' . $lastname . ' -  Email: ' . $email;
			if (!empty($third_party->error)) $this->errors[] = $third_party->error;
			$this->errors = array_merge($this->errors, $third_party->errors);
			return -1;
		}

		// Set category
		//------------------------------------------
		if ($this->eCommerceSite->fk_cat_societe > 0) {
			require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
			$cat = new Categorie($this->db);
			$cat->fetch($this->eCommerceSite->fk_cat_societe);
			$cat->add_type($third_party, 'customer');
		} else {
			$this->errors[] = $this->langs->trans('ECommerceErrorCreateThirdParty') . ' - Name: ' . $company . ' -  First name: ' . $firstname . ' -  Last name: ' . $lastname . ' -  Email: ' . $email;
			$this->errors[] = $this->langs->trans('ECommerceErrorThirdPartyCategoryNotConfigured', $this->eCommerceSite->id);
			return -1;
		}

		if (!empty($order_customer_ref)) $third_party->update_note("Site: '{$this->eCommerceSite->name}' - Order: {$order_customer_ref}", '_private');

		return $third_party->id;
	}

	/**
	 *  Add / Update contact to a object
	 *
	 * @param	CommonObject	$object			Object handler
	 * @param	bool			$new			Is new created object ?
	 * @param	int				$contact_id		Contact ID
	 * @param	string			$contact_type	Contact type
	 * @param	string			$source			Source of contact: external or thirdparty (llx_socpeople) or internal (llx_user)
	 * @return	int								<0 if KO, >0 if OK
	 */
	public function addUpdateContact(&$object, $new, $contact_id, $contact_type, $source = 'external')
	{
		$contact_line_info = null;
		if (!$new) {
			$result = $object->liste_contact(-1, $source, 0, $contact_type);
			if (!is_array($result)) {
				$this->errors[] = $this->langs->trans('ECommerceErrorSearchContact', $contact_type);
				$this->errors[] = $object->error;
				$this->errors = array_merge($this->errors, $object->errors);
				return -1;
			} elseif (!empty($result)) {
				$tmp = array_values($result);
				$contact_line_info = $tmp[0];
			}
		}

		if (is_array($contact_line_info)) {
			$result = $object->update_contact($contact_line_info['rowid'], $contact_line_info['status'], $contact_line_info['fk_c_type_contact'], $contact_id);
			if ($result < 0) {
				$this->errors[] = $this->langs->trans('ECommerceErrorUpdateContact', $contact_line_info['rowid'], $contact_id, $contact_line_info['fk_c_type_contact'], $contact_line_info['status']);
			}
		} else {
			$result = $object->add_contact($contact_id, $contact_type, $source);
			if ($result < 0 && $this->db->errno() != 'DB_ERROR_RECORD_ALREADY_EXISTS' && $object->error != 'CODE_NOT_VALID_FOR_THIS_ELEMENT') {
				$this->errors[] = $this->langs->trans('ECommerceErrorAddContact', $contact_id, $contact_type, $source);
			} else {
				$result = max($result, 0);
			}
		}
		if ($result < 0) {
			$this->errors[] = $object->error;
			$this->errors = array_merge($this->errors, $object->errors);
			return -1;
		}

		return 1;
	}

	/**
	 *	Add an Order or Invoice line into database (linked to product/service or not)
	 *
	 *	@param      Commande|Facture	$object            	Order or Invoice handler
	 *	@param      string				$desc            	Description of line
	 *	@param      float				$pu_ht    	        Unit price (without tax)
	 *	@param      float				$qty             	Quantite
	 * 	@param    	float				$txtva           	Force Vat rate, -1 for auto (Can contain the vat_src_code too with syntax '9.9 (CODE)')
	 * 	@param		float				$txlocaltax1		Local tax 1 rate (deprecated, use instead txtva with code inside)
	 * 	@param		float				$txlocaltax2		Local tax 2 rate (deprecated, use instead txtva with code inside)
	 *	@param      int					$fk_product      	Id of product
	 *	@param      float				$remise_percent  	Percentage discount of the line
	 *	@param      int					$info_bits			Bits de type de lignes
	 *	@param      int					$fk_remise_except	Id remise
	 *	@param      string				$price_base_type	HT or TTC
	 *	@param      float				$pu_ttc    		    Prix unitaire TTC
	 *	@param      int					$date_start       	Start date of the line - Added by Matelli (See http://matelli.fr/showcases/patchs-dolibarr/add-dates-in-order-lines.html)
	 *	@param      int					$date_end         	End date of the line - Added by Matelli (See http://matelli.fr/showcases/patchs-dolibarr/add-dates-in-order-lines.html)
	 *	@param      int					$type				Type of line (0=product, 1=service). Not used if fk_product is defined, the type of product is used.
	 *	@param      int					$rang             	Position of line
	 *	@param		int					$special_code		Special code (also used by externals modules!)
	 *	@param		int					$fk_parent_line		Parent line
	 *  @param		int					$fk_fournprice		Id supplier price
	 *  @param		int					$pa_ht				Buying price (without tax)
	 *  @param		string				$label				Label
	 *  @param		array				$array_options		extrafields array. Example array('options_codeforfield1'=>'valueforfield1', 'options_codeforfield2'=>'valueforfield2', ...)
	 * 	@param 		string				$fk_unit 			Code of the unit to use. Null to use the default one
	 * 	@param		string		    	$origin				'order', ...
	 *  @param		int			    	$origin_id			Id of origin object
	 * 	@param		double				$pu_ht_devise		Unit price in currency
	 *	@return     int             						>0 if OK, <0 if KO
	 */
	public function addLineToObject($object, $desc, $pu_ht, $qty, $txtva, $total_ht, $total_tva, $total_ttc,
									$fk_product = 0, $type = 0, $label = '', $array_options = 0, $fk_fournprice = null, $pa_ht = 0,
									$txlocaltax1 = 0, $txlocaltax2 = 0, $total_localtax1 = 0, $total_localtax2 = 0,
									$remise_percent = 0, $fk_unit = null, $special_code = 0, $info_bits = 0, $fk_remise_except = 0,
									$date_start = '', $date_end = '', $fk_parent_line = 0, $rang = -1, $origin = '', $origin_id = 0,
									$ventil = 0, $situation_percent = 100, $fk_prev_id = 0,
									$pu_ht_devise = 0, $multicurrency_total_ht = 0, $multicurrency_total_tva = 0, $multicurrency_total_ttc = 0)
	{
		global $mysoc, $conf, $langs, $user;

		dol_syslog(__METHOD__ . " object_element={$object->element}, object_id={$object->id}, desc=$desc, pu_ht=$pu_ht," .
			" qty=$qty, txtva=$txtva, total_ht=$total_ht, total_tva=$total_tva, total_ttc=$total_ttc, fk_product=$fk_product, type=$type,".
			" label=$label, array_options=".json_encode($array_options) . ", fk_fournprice=$fk_fournprice, pa_ht=$pa_ht,".
			" txlocaltax1=$txlocaltax1, txlocaltax2=$txlocaltax2, total_localtax1=$total_localtax1, total_localtax2=$total_localtax2,".
			" remise_percent=$remise_percent, fk_unit=$fk_unit, special_code=$special_code, info_bits=$info_bits, fk_remise_except=$fk_remise_except,".
			" date_start=$date_start, date_end=$date_end, fk_parent_line=$fk_parent_line, rang=$rang, origin=$origin, origin_id=$origin_id,".
			" ventil=$ventil, situation_percent=$situation_percent, fk_prev_id=$fk_prev_id,".
			" pu_ht_devise=$pu_ht_devise, multicurrency_total_ht=$multicurrency_total_ht, multicurrency_total_tva=$multicurrency_total_tva, multicurrency_total_ttc=$multicurrency_total_ttc", LOG_DEBUG);

		if ($object->statut == $object::STATUS_DRAFT) {
			include_once DOL_DOCUMENT_ROOT . '/core/lib/price.lib.php';

			// Clean parameters
			if (empty($remise_percent)) $remise_percent = 0;
			if (empty($qty)) $qty = 0;
			if (empty($info_bits)) $info_bits = 0;
			if (empty($rang)) $rang = 0;
			if (empty($txtva)) $txtva = 0;
			if (empty($txlocaltax1)) $txlocaltax1 = 0;
			if (empty($txlocaltax2)) $txlocaltax2 = 0;
			if (empty($total_localtax1)) $total_localtax1 = 0;
			if (empty($total_localtax2)) $total_localtax2 = 0;
			if (empty($fk_parent_line) || $fk_parent_line < 0) $fk_parent_line = 0;
			if (empty($object->fk_multicurrency)) $object->fk_multicurrency = 0;
			if (empty($fk_prev_id)) $fk_prev_id = 'null';
			if (! isset($situation_percent) || $situation_percent > 100 || (string) $situation_percent == '') $situation_percent = 100;
			if (empty($ventil)) $ventil=0;

			$remise_percent = price2num($remise_percent);
			$qty = price2num($qty);
			$pu_ht = price2num($pu_ht);
			$pu_ht_devise = price2num($pu_ht_devise);
			$pa_ht = price2num($pa_ht);
			$total_ht = price2num($total_ht);
			$total_tva = price2num($total_tva);
			$total_ttc = price2num($total_ttc);
			$multicurrency_total_ht = price2num($multicurrency_total_ht);
			$multicurrency_total_tva = price2num($multicurrency_total_tva);
			$multicurrency_total_ttc = price2num($multicurrency_total_ttc);
			if (!preg_match('/\((.*)\)/', $txtva)) {
				$txtva = price2num($txtva);               // $txtva can have format '5,1' or '5.1' or '5.1(XXX)', we must clean only if '5,1'
			}
			$txlocaltax1 = price2num($txlocaltax1);
			$txlocaltax2 = price2num($txlocaltax2);
			$total_localtax1 = price2num($total_localtax1);
			$total_localtax2 = price2num($total_localtax2);
			$label = trim($label);
			$desc = trim($desc);

			$localtaxes_type = getLocalTaxesFromRate($txtva, 0, $object->thirdparty, $mysoc);

			// Clean vat code
			$vat_src_code = '';
			if (preg_match('/\((.*)\)/', $txtva, $reg)) {
				$vat_src_code = $reg[1];
				$txtva = preg_replace('/\s*\(.*\)/', '', $txtva);    // Remove code into vatrate.
			}

			// Check parameters
			if ($type < 0) return -1;

			if ($date_start && $date_end && $date_start > $date_end) {
				$langs->load("errors");
				$this->errors[] = $langs->trans('ErrorStartDateGreaterEnd');
				return -1;
			}

			$product_type = $type;
			if (!empty($fk_product)) {
				$product = new Product($this->db);
				$result = $product->fetch($fk_product);
				$product_type = $product->type;

				if ($object->element == 'commande') {
					if (!empty($conf->global->STOCK_MUST_BE_ENOUGH_FOR_ORDER) && $product_type == 0 && $product->stock_reel < $qty) {
						$langs->load("errors");
						$this->errors[] = $langs->trans('ErrorStockIsNotEnoughToAddProductOnOrder', $product->ref);
						return -4;
					}
				} else {
					if (!empty($conf->global->STOCK_MUST_BE_ENOUGH_FOR_INVOICE) && $product_type == 0 && $product->stock_reel < $qty) {
						$langs->load("errors");
						$this->errors[] = $langs->trans('ErrorStockIsNotEnoughToAddProductOnInvoice', $product->ref);
						return -4;
					}
				}
			}

			// Rang to use
			$rangtouse = $rang;
			if ($rangtouse == -1) {
				$rangmax = $object->line_max($fk_parent_line);
				$rangtouse = $rangmax + 1;
			}

			// Insert line
			if ($object->element == 'commande') {
				$line = new OrderLine($this->db);
			} else {
				$line = new FactureLigne($this->db);
			}

			$line->context = $object->context;

			if ($object->element == 'commande') {
				$line->fk_commande = $object->id;
			} else {
				$line->fk_facture = $object->id;
			}
			$line->label = $label;
			$line->desc = $desc;

			if ($object->element == 'commande') {
				$line->qty = $qty;
				$line->subprice = $pu_ht;
			} else {
				$line->qty = ($object->type == Facture::TYPE_CREDIT_NOTE ? abs($qty) : $qty);        // For credit note, quantity is always positive and unit price negative
				$line->subprice = ($object->type == Facture::TYPE_CREDIT_NOTE ? -abs($pu_ht) : $pu_ht); // For credit note, unit price always negative, always positive otherwise
			}

			$line->vat_src_code = $vat_src_code;
			$line->tva_tx = $txtva;
			$line->localtax1_tx = ($total_localtax1 ? $localtaxes_type[1] : 0);
			$line->localtax2_tx = ($total_localtax2 ? $localtaxes_type[3] : 0);
			$line->localtax1_type = $localtaxes_type[0];
			$line->localtax2_type = $localtaxes_type[2];

			if ($object->element == 'commande') {
				$line->total_ht = $total_ht;
				$line->total_tva = $total_tva;
				$line->total_ttc = $total_ttc;
				$line->total_localtax1 = $total_localtax1;
				$line->total_localtax2 = $total_localtax2;
			} else {
				$line->total_ht = (($object->type == Facture::TYPE_CREDIT_NOTE || $qty < 0) ? -abs($total_ht) : $total_ht);    // For credit note and if qty is negative, total is negative
				$line->total_tva = (($object->type == Facture::TYPE_CREDIT_NOTE || $qty < 0) ? -abs($total_tva) : $total_tva);  // For credit note and if qty is negative, total is negative
				$line->total_ttc = (($object->type == Facture::TYPE_CREDIT_NOTE || $qty < 0) ? -abs($total_ttc) : $total_ttc);  // For credit note and if qty is negative, total is negative
				$line->total_localtax1 = (($object->type == Facture::TYPE_CREDIT_NOTE || $qty < 0) ? -abs($total_localtax1) : $total_localtax1);  // For credit note and if qty is negative, total is negative
				$line->total_localtax2 = (($object->type == Facture::TYPE_CREDIT_NOTE || $qty < 0) ? -abs($total_localtax2) : $total_localtax2);  // For credit note and if qty is negative, total is negative
			}

			$line->fk_product = $fk_product;
			$line->product_type = $product_type;
			$line->remise_percent = $remise_percent;
			$line->date_start = $date_start;
			$line->date_end = $date_end;
			$line->info_bits = $info_bits;
			$line->rang = $rangtouse;
			$line->fk_remise_except = $fk_remise_except;
			if ($object->element == 'facture') {
				$line->ventil = $ventil;
			}

			$line->special_code = $special_code;
			$line->fk_parent_line = $fk_parent_line;
			$line->origin = $origin;
			$line->origin_id = $origin_id;
			$line->fk_unit = $fk_unit;
			if ($object->element == 'facture') {
				$line->situation_percent = $situation_percent;
				$line->fk_prev_id = $fk_prev_id;
			}

			$line->fk_fournprice = $fk_fournprice;
			$line->pa_ht = $pa_ht;

			// Multicurrency
			$line->fk_multicurrency = $object->fk_multicurrency;
			$line->multicurrency_code = $object->multicurrency_code;
			$line->multicurrency_subprice = $pu_ht_devise;
			$line->multicurrency_total_ht = $multicurrency_total_ht;
			$line->multicurrency_total_tva = $multicurrency_total_tva;
			$line->multicurrency_total_ttc = $multicurrency_total_ttc;

			if (is_array($array_options) && count($array_options) > 0) {
				$line->array_options = $array_options;
			}

			$this->db->begin();

			$result = $line->insert($user);
			if ($result > 0) {
				// Reorder if child line
				if (!empty($fk_parent_line)) $object->line_order(true, 'DESC');

				// Mise a jour informations denormalisees au niveau de la commande meme
				$result = $object->update_price(1, 'auto', 0, $mysoc);    // This method is designed to add line from user input so total calculation must be done using 'auto' mode.
				if ($result > 0) {
					$this->db->commit();
					return $line->rowid;
				} else {
					if (!empty($object->error)) $this->errors[] = $object->error;
					$this->errors = array_merge($this->errors, $object->errors);
					$this->db->rollback();
					return -1;
				}
			} else {
				if (!empty($line->error)) $this->errors[] = $line->error;
				$this->errors = array_merge($this->errors, $line->errors);
				$this->db->rollback();
				return -2;
			}
		} else {
			dol_syslog(__METHOD__ . " status of the object '{$object->element}' must be Draft to allow use of ->addLineToObject()", LOG_ERR);
			return -3;
		}
	}

	/**
	 * Load payment gateways
	 *
	 * @return	int				<0 if KO, >0 if OK
	 */
	public function loadPaymentGateways()
	{
		if (!isset($this->payment_gateways_cached)) {
			// Payment gateways correspondence
			dol_include_once('/ecommerceng/class/data/eCommercePaymentGateways.class.php');
			$pay_gateways = new eCommercePaymentGateways($this->db);
			$result = $pay_gateways->get_all($this->eCommerceSite->id);
			if (!is_array($result)) {
				$this->errors[] = $this->langs->trans('ECommerceErrorGetPaymentGateways', $this->eCommerceSite->id);
				if (!empty($pay_gateways->error)) $this->errors[] = $pay_gateways->error;
				$this->errors = array_merge($this->errors, $pay_gateways->errors);
				return -1;
			}

			$this->payment_gateways_cached = $result;
		}

		return 1;
	}

	/**
	 * Load product categories
	 *
	 * @return	int				<0 if KO, >0 if OK
	 */
	public function loadProductCategories()
	{
		if (!isset($this->product_category_cached[$this->eCommerceSite->id])) {
			require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
			$cat = new Categorie($this->db);
			$result = $cat->get_full_arbo('product', $this->eCommerceSite->fk_cat_product, 1);
			if (!is_array($result)) {
				$this->errors[] = $this->langs->trans('ECommerceErrorGetProductCategories');
				if (!empty($cat->error)) $this->errors[] = $cat->error;
				$this->errors = array_merge($this->errors, $cat->errors);
				return -1;
			}

			$list = array();
			foreach ($result as $item) {
				$list[$item['id']] = $item['id'];
			}

			$this->product_category_cached[$this->eCommerceSite->id] = $list;
		}

		return 1;
	}

	/**
	 * Method to output saved errors
	 *
	 * @param   string      $separator      Separator between each error
	 * @return	string		                String with errors
	 */
	public function errorsToString($separator = ', ')
	{
		return $this->error . (is_array($this->errors) ? (!empty($this->error) ? $separator : '') . join($separator, $this->errors) : '');
	}
}

