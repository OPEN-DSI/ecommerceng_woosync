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
 * This class is used to abstract the Xxxx of class eCommerceRemoteAccessXxxx.
 * Includind directly the eCommerceRemoteAccessXxxx by caller would be better, but well, it was done like this.
 */
class eCommerceRemoteAccess
{
	private $site;
	private $className;
	private $dirName;
	private $class;
	private $db;

    /**
     * Class for access remote sites
     * Creates an instance of the appropriate class according to type of site
     *
     * @param   Database            $db         Databse handler
     * @param   eCommerceSite       $site       eCommerceSite
     */
    function eCommerceRemoteAccess($db, $site)
    {
    	$this->db = $db;
        $this->site = $site;

        $this->setName();

        dol_include_once('/ecommerceng/class/data/'.$this->dirName.'/eCommerceRemoteAccess'.$this->className.'.class.php');

        $this->setClass();

        return 1;
    }

	private function setName()
	{
		$types = $this->site->getSiteTypes();
		$name = $types[$this->site->type];
		$this->className = str_replace(' ','',ucwords($name));
		$this->dirName = str_replace(' ','',strtolower($name));
	}

    private function setClass()
    {
    	$className = get_class($this).$this->className;
    	$class = new $className($this->db, $this->site);
    	$this->class = $class;
    }

    /**
     * Call the connect method of the class instantiated in the constructor
     */
    public function connect()
    {
		$result = $this->class->connect();
		$this->error=$this->class->error;
		$this->errors=$this->class->errors;
    	return $result;
    }

    /**
     * Get societe to update from instantiated class in the constructor
     *
     * @param   datetime    $fromDate Updates from this date
     * @param   datetime    $toDate   Updates to this date
     * @return  array                 Array of remote societe
     */
    public function getSocieteToUpdate($fromDate, $toDate)
	{
	    $result = $this->class->getSocieteToUpdate($fromDate, $toDate);
		$this->error=$this->class->error;
		$this->errors=$this->class->errors;
	    return $result;
	}

    /**
     * Get product to update from instantiated class in the constructor
     *
     * @param   datetime    $fromDate Updates from this date
     * @param   datetime    $toDate   Updates to this date
     * @return array                Array of remote product
     */
    public function getProductToUpdate($fromDate, $toDate)
	{
	    $result=$this->class->getProductToUpdate($fromDate, $toDate);
		$this->error=$this->class->error;
		$this->errors=$this->class->errors;
	    return $result;
	}

    /**
     * Get commande to update from instantiated class in the constructor
     *
     * @param   datetime    $fromDate Updates from this date
     * @param   datetime    $toDate   Updates to this date
     * @return array                Array of remote commande or false if error
     */
    public function getCommandeToUpdate($fromDate, $toDate)
	{
		$result=$this->class->getCommandeToUpdate($fromDate, $toDate);
		$this->error=$this->class->error;
		$this->errors=$this->class->errors;
		return $result;
	}

    /**
     * Get facture to update from instantiated class in the constructor
     *
     * @param   datetime    $fromDate Updates from this date
     * @param   datetime    $toDate   Updates to this date
     * @return array                Array of remote facture
     */
    public function getFactureToUpdate($fromDate, $toDate)
	{
	    $result=$this->class->getFactureToUpdate($fromDate, $toDate);
		$this->error=$this->class->error;
		$this->errors=$this->class->errors;
	    return $result;
	}


    /**
     * Put the remote data into category dolibarr data from instantiated class in the constructor
     *
     * @param   array   $remoteObject   Array of remote object
     * @param   int     $toNb           Max nb
     * @return $dolibarrObject array
     */
    public function convertRemoteObjectIntoDolibarrCategory($remoteObject,$toNb=0)
	{
	    $result=$this->class->convertRemoteObjectIntoDolibarrCategory($remoteObject,$toNb);
		$this->error=$this->class->error;
		$this->errors=$this->class->errors;
	    return $result;
	}

	/**
     * Put the remote data into societe dolibarr data from instantiated class in the constructor
     *
	 * @param	int		$from_date		Synchronize from date
	 * @param	int		$to_date		Synchronize to date
     * @param   array   $remoteObject   Array of remote object
     * @param   int     $toNb           Max nb
     * @return $dolibarrObject array
     */
    public function convertRemoteObjectIntoDolibarrSociete($from_date = null, $to_date = null, $remoteObject = array(), $toNb = 0)
	{
	    $result=$this->class->convertRemoteObjectIntoDolibarrSociete($from_date, $to_date, $remoteObject, $toNb);
		$this->error=$this->class->error;
		$this->errors=$this->class->errors;
	    return $result;
	}

    /**
     * Put the remote data into societe dolibarr data from instantiated class in the constructor
     *
     * @param   array   $remoteObject   Array of remote object
     * @param   int     $toNb           Max nb
     * @return $dolibarrObject array
     */
    public function convertRemoteObjectIntoDolibarrSocpeople($remoteObject,$toNb=0)
	{
	    $result=$this->class->convertRemoteObjectIntoDolibarrSocpeople($remoteObject,$toNb);
		$this->error=$this->class->error;
		$this->errors=$this->class->errors;
	    return $result;
	}

    /**
     * Put the remote data into product dolibarr data from instantiated class in the constructor
     *
	 * @param	int		$from_date		Synchronize from date
	 * @param	int		$to_date		Synchronize to date
     * @param   array   $remoteObject   Array of remote object
     * @param   int     $toNb           Max nb
     * @return $dolibarrObject array
     */
    public function convertRemoteObjectIntoDolibarrProduct($from_date = null, $to_date = null, $remoteObject = array(), $toNb = 0)
	{
	    $result=$this->class->convertRemoteObjectIntoDolibarrProduct($from_date, $to_date, $remoteObject,$toNb);
		$this->error=$this->class->error;
		$this->errors=$this->class->errors;
	    return $result;
	}

    /**
     * Put the remote data into commande dolibarr data from instantiated class in the constructor
     *
	 * @param	int		$from_date		Synchronize from date
	 * @param	int		$to_date		Synchronize to date
     * @param   array   $remoteObject   Array of remote object
     * @param   int     $toNb           Max nb
     * @return $dolibarrObject array
     */
    public function convertRemoteObjectIntoDolibarrCommande($from_date = null, $to_date = null, $remoteObject = array(), $toNb = 0)
	{
	    $result=$this->class->convertRemoteObjectIntoDolibarrCommande($from_date, $to_date, $remoteObject,$toNb);
		$this->error=$this->class->error;
		$this->errors=$this->class->errors;
	    return $result;
	}

    /**
     * Put the remote data into facture dolibarr data from instantiated class in the constructor
     *
     * @param   array   $remoteObject   Array of remote object
     * @param   int     $toNb           Max nb
     * @return $dolibarrObject array
     */
    public function convertRemoteObjectIntoDolibarrFacture($remoteObject,$toNb=0)
	{
	    $result=$this->class->convertRemoteObjectIntoDolibarrFacture($remoteObject,$toNb);
		$this->error=$this->class->error;
		$this->errors=$this->class->errors;
	    return $result;
	}


	/**
	 * Put the remote data into commande dolibarr data from instantiated class in the constructor
	 *
	 * @param	array		$remote_data 	Remote data
	 * @return  array                 		Data processed.
	 */
	public function convertOrderDataIntoProcessedData($remote_data)
	{
		$result=$this->class->convertOrderDataIntoProcessedData($remote_data);
		$this->error=$this->class->error;
		$this->errors=$this->class->errors;
		return $result;
	}

    /**
     * Get a commande from instantiated class in the constructor
     *
     * @param   int     $remoteCommandeId   string
     * @return  array                       dolibarrObject array
     */
	public function getRemoteCommande($remoteCommandeId)
	{
	    $result=$this->class->getRemoteCommande($remoteCommandeId);
		$this->error=$this->class->error;
		$this->errors=$this->class->errors;
	    return $result;
	}

	/**
	 * Get a remote category tree from magento
	 *
	 * @return	array	An array containing magento's categories as arrays
	 */
	public function getRemoteCategoryTree()
	{
	    $result=$this->class->getRemoteCategoryTree();
		$this->error=$this->class->error;
		$this->errors=$this->class->errors;
	    return $result;
	}

	/**
	 * Get a remote category att from magento
	 *
	 * @return	array	An array containing magento's categories as arrays
	 */
	/*public function getRemoteCategoryAtt()
	{
	    $result=$this->class->getRemoteCategoryAtt();
	    $this->error=$this->class->error;
	    $this->errors=$this->class->errors;
	    return $result;
	}*/

    /**
     * Get list of address id for a customer
     *
     * @param   int     $remote_thirdparty_id   Remote thirdparty id
     * @return $dolibarrObject array
     */
    public function getRemoteAddressIdForSociete($remote_thirdparty_id)
	{
	    $result=$this->class->getRemoteAddressIdForSociete($remote_thirdparty_id);
		$this->error=$this->class->error;
		$this->errors=$this->class->errors;
	    return $result;
	}

	/**
	 *  Get a remote category tree from magento
	 *
	 *  @param   int   $category_id            Remote category id
	 * 	@return	array	An array containing magento's categories as arrays
	 */
	public function getCategoryData($category_id)
	{
	    $result=$this->class->getCategoryData($category_id);
		$this->error=$this->class->error;
		$this->errors=$this->class->errors;
	    return $result;
	}


	/**
	 * Update remote product
	 *
	 * @param  int     $remote_product_id    Id of product on remote ecommerce
	 * @param  Product $object               Product object
	 * @return bool
	 */
	public function updateRemoteProduct($remote_product_id, $object)
	{
	    $result=$this->class->updateRemoteProduct($remote_product_id, $object);
	    $this->error=$this->class->error;
	    $this->errors=$this->class->errors;
	    return $result;
	}

	/**
	 * Update remote stock of product
	 *
	 * @param  int     $remote_product_id    Id of product on remote ecommerce
	 * @param  Product $object               Product object
	 * @return bool
	 */
	public function updateRemoteStockProduct($remote_product_id, $object)
	{
	    $result=$this->class->updateRemoteStockProduct($remote_product_id, $object);
	    $this->error=$this->class->error;
	    $this->errors=$this->class->errors;
	    return $result;
	}

	/**
	 * Update remote societe
	 *
	 * @param  int     $remote_societe_id    Id of societe on remote ecommerce
	 * @param  Societe $object               Societe object
	 * @return bool
	 */
	public function updateRemoteSociete($remote_societe_id, $object)
	{
	    $result=$this->class->updateRemoteSociete($remote_societe_id, $object);
	    $this->error=$this->class->error;
	    $this->errors=$this->class->errors;
	    return $result;
	}

	/**
	 * Update remote contact
	 *
	 * @param  int     $remote_contact_id    Id of contact on remote ecommerce
	 * @param  Contact $object               Contact object
	 * @return bool
	 */
	public function updateRemoteSocpeople($remote_contact_id, $object)
	{
	    $result=$this->class->updateRemoteSocpeople($remote_contact_id, $object);
	    $this->error=$this->class->error;
	    $this->errors=$this->class->errors;
	    return $result;
	}

	/**
	 * Update remote order
	 *
	 * @param  int      $remote_order_id      Id of order on remote ecommerce
	 * @param  Commande $object               Commande object
	 * @return bool
	 */
	public function updateRemoteCommande($remote_order_id, $object)
	{
	    $result=$this->class->updateRemoteCommande($remote_order_id, $object);
	    $this->error=$this->class->error;
	    $this->errors=$this->class->errors;
	    return $result;
	}

	/**
	 * Update remote invoice
	 *
	 * @param  int      $remote_invoice_id    Id of invoice on remote ecommerce
	 * @param  Facture  $object               Invoice object
	 * @return bool
	 */
	public function updateRemoteFactue($remote_invoice_id, $object)
	{
	    $result=$this->class->updateRemoteFacture($remote_invoice_id, $object);
	    $this->error=$this->class->error;
	    $this->errors=$this->class->errors;
	    return $result;
	}

	/**
     * Create a remote livraison from instantiated class in the constructor
     *
     * @param Object    $livraison              Object livraison
     * @param int       $remote_order_id        String id remote order
     * @return bool
     */
	public function createRemoteLivraison($livraison, $remote_order_id)
	{
	    $result=$this->class->createRemoteLivraison($livraison, $remote_order_id);
		$this->error=$this->class->error;
		$this->errors=$this->class->errors;
	    return $result;
	}

    /**
     * Create a remote product
     *
     * @param Object    $object              Object product
     * @return bool
     */
    public function createRemoteProduct($object)
    {
        $result=$this->class->createRemoteProduct($object);
        $this->error=$this->class->error;
        $this->errors=$this->class->errors;
        return $result;
    }

    /**
     * Create batch remote categories
     *
     * @param  array    $batch              Array of object category
     * @return bool|array
     */
    public function createRemoteCategories($batch)
    {
        $result=$this->class->createRemoteCategories($batch);
        $this->error=$this->class->error;
        $this->errors=$this->class->errors;
        return $result;
    }

    /**
     * Create batch remote products
     *
     * @param  array    $batch              Array of id product
     * @return array
     */
    public function batchUpdateRemoteProducts($batch)
    {
        $result=$this->class->batchUpdateRemoteProducts($batch);
        $this->error=$this->class->error;
        $this->errors=$this->class->errors;
        return $result;
    }

    /**
     * Send a file for remote commande
     *
     * @param   int         $order_remote_id    Id of order on remote ecommerce
     * @param   int         $societe_remote_id  Id of societe on remote ecommerce
     * @param   Object      $object             Object product
     * @param   string      $file               File path
     * @param   Translate   $outputlangs		Lang output object
     * @return  bool
     */
    public function sendFileForCommande($order_remote_id, $societe_remote_id, $object, $file, $outputlangs)
    {
        $result=$this->class->sendFileForCommande($order_remote_id, $societe_remote_id, $object, $file, $outputlangs);
        $this->error=$this->class->error;
        $this->errors=$this->class->errors;
        return $result;
    }

    /**
     * Get all payment gateways
     *
     * @return array|false    List of payment gateways or false if error
     */
    public function getAllPaymentGateways()
    {
        $result=$this->class->getAllPaymentGateways();
        $this->error=$this->class->error;
        $this->errors=$this->class->errors;
        return $result;
    }
}
