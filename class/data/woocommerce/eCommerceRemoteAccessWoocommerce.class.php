<?php
/* Copyright (C) 2017 Open-DSI                     <support@open-dsi.fr>
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

dol_include_once('/ecommerceng/admin/class/data/eCommerceDict.class.php');
dol_include_once('/ecommerceng/class/data/eCommerceCategory.class.php');
dol_include_once('/ecommerceng/class/data/eCommerceSociete.class.php');
dol_include_once('/ecommerceng/class/data/eCommerceProduct.class.php');
dol_include_once('/ecommerceng/class/data/eCommerceCommande.class.php');
dol_include_once('/ecommerceng/class/business/eCommerceUtils.class.php');

dol_include_once('/ecommerceng/includes/WooCommerce/Client.php');
dol_include_once('/ecommerceng/includes/WooCommerce/HttpClient/BasicAuth.php');
dol_include_once('/ecommerceng/includes/WooCommerce/HttpClient/HttpClient.php');
dol_include_once('/ecommerceng/includes/WooCommerce/HttpClient/HttpClientException.php');
dol_include_once('/ecommerceng/includes/WooCommerce/HttpClient/OAuth.php');
dol_include_once('/ecommerceng/includes/WooCommerce/HttpClient/Options.php');
dol_include_once('/ecommerceng/includes/WooCommerce/HttpClient/Request.php');
dol_include_once('/ecommerceng/includes/WooCommerce/HttpClient/Response.php');

dol_include_once('/ecommerceng/lib/eCommerce.lib.php');

dol_include_once('/ecommerceng/includes/WordPressClient.php');

require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';

use Automattic\WooCommerce\Client;
use Automattic\WooCommerce\HttpClient\HttpClientException;

/**
 * Class for access remote sites
 */
class eCommerceRemoteAccessWoocommerce
{
    /**
     * eCommerceSite object.
     *
     * @var eCommerceSite
     */
    private $site;

    /**
     * @var eCommerceSociete
     */
    private $eCommerceSociete;
    /**
     * @var eCommerceProduct
     */
    private $eCommerceProduct;
    /**
     * @var eCommerceCommande
     */
    private $eCommerceCommande;

    /**
     * Woocommerce client new API v2.
     *
     * @var Client
     */
    private $client;
    /**
     * Woocommerce client old API v3.
     *
     * @var Client
     */
    private $clientOld;
    /**
     * WordPress client.
     *
     * @var WordPressClient
     */
    private $worpressclient;

    /**
     * Dolibarr tax rates.
     *
     * @var array
     */
    private $dolibarrTaxes;

    /**
     * Woocommerce taxes.
     *
     * @var array
     */
    private $woocommerceTaxes;

    /**
     * Database handler.
     *
     * @var DoliDB
     */
    private $db;

    /**
     * Errors list.
     *
     * @var array
     */
    public $errors;

    /**
     * GMT timezone.
     *
     * @var DateTimeZone
     */
    public $gmtTimeZone;

    /**
     * Current timezone.
     *
     * @var DateTimeZone
     */
    public $currentTimeZone;

	/**
	 * Woocommerce taxes classes cached.
	 *
	 * @var array
	 */
	private static $taxes_classes_cached;
	/**
	 * Woocommerce taxes class by rates cached.
	 *
	 * @var array
	 */
	private static $taxes_classes_by_rate_cached;
	/**
	 * Woocommerce taxes rates cached.
	 *
	 * @var array
	 */
	private static $taxes_rates_cached;
	/**
	 * Woocommerce first position taxes rates by class cached.
	 *
	 * @var array
	 */
	private static $taxes_rates_by_class_cached;

	/**
	 * Disabled all call af the api for method PUT and POST for testing.
	 *
	 * @var bool
	 */
	private static $disable_put_post_to_api = false;

    /**
     * Constructor
     * @param   DoliDB          $db     Database handler
     * @param   eCommerceSite   $site   eCommerceSite object
     */
    function __construct($db, $site)
    {
        global $langs;

        $langs->load("ecommerce@ecommerceng");
        $langs->load("woocommerce@ecommerceng");

        $this->db = $db;
        $this->site = $site;
        $this->errors = [];

        $this->gmtTimeZone = new DateTimeZone('GMT');
        $this->currentTimeZone = new DateTimeZone(date_default_timezone_get());

        return 1;
    }

    /**
     * Instanciate eCommerceSociete data class access
     */
    private function initECommerceSociete()
    {
        $this->eCommerceSociete = new eCommerceSociete($this->db);
    }

    /**
     * Instanciate eCommerceProduct data class access
     */
    private function initECommerceProduct()
    {
        $this->eCommerceProduct = new eCommerceProduct($this->db);
    }

    /**
     * Instanciate eCommerceCommande data class access
     */
    private function initECommerceCommande()
    {
        $this->eCommerceCommande = new eCommerceCommande($this->db);
    }

    /**
     * Connect to API
     *
     * @return boolean      True if OK, False if KO
     */
    public function connect()
    {
        dol_syslog(__METHOD__ . ": Connect to API webservice_address=" . $this->site->webservice_address . " user_name=" .
            $this->site->user_name . " user_password=" . $this->site->user_password . " for site ID {$this->site->id}", LOG_DEBUG);
        global $conf, $langs;

		$this->errors = array();
        $response_timeout = (empty($conf->global->MAIN_USE_RESPONSE_TIMEOUT) ? 30 : $conf->global->MAIN_USE_RESPONSE_TIMEOUT);    // Response timeout

//		$stopwatch_id = -1;
        try {
            $this->client = new Client(
                $this->site->webservice_address,
                $this->site->user_name,
                $this->site->user_password,
                [
                    'wp_api' => true,
                    'version' => 'wc/v3',
                    'timeout' => $response_timeout,
                    'query_string_auth' => !empty($conf->global->ECOMMERCENG_WOOCOMMERCE_QUERY_STRING_AUTH),
                ]
            );
//            $filters = [ 'page' => 1, 'per_page' => 1 ];
//			$stopwatch_id = eCommerceUtils::startStopwatch(__METHOD__ . " - GET products/categories (filters: " . json_encode($filters). ")");
//            $this->client->get('products/categories', $filters);
//			eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
//
//            $this->clientOld = new Client(
//                $this->site->webservice_address,
//                $this->site->user_name,
//                $this->site->user_password,
//                [
//                    'wp_api' => false,
//                	'version' => 'v3',
//                    'timeout' => $response_timeout,
//                    'query_string_auth' => !empty($conf->global->ECOMMERCENG_WOOCOMMERCE_QUERY_STRING_AUTH),
//                ]
//            );
//            $this->clientOld->get('products/categories', [ 'page' => 1, 'filter' => [ 'limit' => 1 ] ]);
        } catch (HttpClientException $fault) {
//			eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
            $this->errors[] = $langs->trans('ECommerceWoocommerceConnect', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
            dol_syslog(__METHOD__ .
                ': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceConnect', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
                ' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
            return false;
        }

        try {
            $this->worpressclient = new WordPressClient(
                $this->site->webservice_address,
                $this->site->oauth_id,
                $this->site->oauth_secret,
                dol_buildpath('/ecommerceng/core/modules/oauth/wordpress_oauthcallback.php', 2) . '?ecommerce_id=' . $this->site->id
            );
        } catch (Exception $e) {
            $this->errors[] = $langs->trans('ECommerceWoocommerceConnect', $this->site->name, $e->getMessage());
            dol_syslog(__METHOD__ . ': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceConnect', $this->site->name, $e->getMessage()), LOG_ERR);
            return false;
        }

        dol_syslog(__METHOD__ . ": end, ok", LOG_DEBUG);
        return true;
    }

    /**
     * Call Woocommerce API to get last updated companies. We are interested here by list of id only. We will retreive properties later.
     *
     * @param   int             $fromDate   From date
     * @param   int             $toDate     To date
     *
     * @return  array|boolean               List of companies ID to update or false if error
     */
    public function getSocieteToUpdate($fromDate, $toDate)
    {
        dol_syslog(__METHOD__ . ": start gt = " . (!empty($fromDate) ? dol_print_date($fromDate, 'standard') : 'none') .
            ", lt = " . (!empty($toDate) ? dol_print_date($toDate, 'standard') : 'none') . " for site ID {$this->site->id}", LOG_DEBUG);
        global $conf, $langs;

		$this->errors = array();
		$this->initECommerceSociete();
        $last_update = [];
        $result = [];
        $idxPage = 1;
        $per_page = empty($conf->global->ECOMMERCENG_MAXSIZE_MULTICALL) ? 100 : min($conf->global->ECOMMERCENG_MAXSIZE_MULTICALL, 100);
        $from_date = isset($fromDate) && !empty($fromDate) ? new DateTime(dol_print_date($fromDate, 'standard')) : null;
        $to_date = isset($toDate) && !empty($toDate) ? new DateTime(dol_print_date($toDate, 'standard')) : null;

        $no_more = false;
        while (true) {
			$filters =  [
				'page' => $idxPage++,
				'per_page' => $per_page,
				'orderby' => 'registered_date',
				'order' => 'desc',
				'role' => empty($this->site->parameters['customer_roles']) ? 'all' : $this->site->parameters['customer_roles'],
			];
			$stopwatch_id = eCommerceUtils::startStopwatch(__METHOD__ . " - GET products/categories (filters: " . json_encode($filters). ")");
            try {
                $page = $this->client->get('customers', $filters);
				eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
            } catch (HttpClientException $fault) {
				eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
				$this->errors[] = $langs->trans('ECommerceWoocommerceGetSocieteToUpdate', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
                dol_syslog(__METHOD__ .
                    ': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceGetSocieteToUpdate', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
                    ' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
                return false;
            }

            if (!is_array($page) || ($nbCustomers = count($page)) == 0) break;

            foreach ($page as $customer) {
                $id = $customer->id;
                $date_customer = $this->getDateTimeFromGMTDateTime(/*!empty($customer->date_modified_gmt) ? $customer->date_modified_gmt : */$customer->date_created_gmt);
                $update_customer = false;
                if ($from_date == $date_customer) {
                    if ($this->eCommerceSociete->fetchByRemoteId($id, $this->site->id) > 0) {
                        if (isset($this->eCommerceSociete->last_update) && !empty($this->eCommerceSociete->last_update)) {
                            $date = new DateTime(dol_print_date($this->eCommerceSociete->last_update, 'standard'));
                            if ($date < $from_date) {
                                $update_customer = true;
                            }
                        }
                    } else {
                        $update_customer = true;
                    }
                }

                if ($update_customer || (!isset($from_date) || $from_date < $date_customer) && (!isset($to_date) || $date_customer <= $to_date)) {
                    $result[$id] = $id;
                    $last_update[$id] = $date_customer->format('Y-m-d H:i:s');
                } else {
                    $no_more = true;
                }

                if ($no_more) break;
            }

            if ($no_more) break;
        }

        //important - order by last update
        if (count($result)) {
            array_multisort($last_update, SORT_ASC, $result);
        }

        dol_syslog(__METHOD__ . ": end", LOG_DEBUG);
        return $result;
    }

    /**
     * Call Woocommerce API to get last updated products. We are interested here by list of id only. We will retreive properties later.
     *
     * @param   int             $fromDate   From date
     * @param   int             $toDate     To date
     *
     * @return  array|boolean               List of products ID to update or false if error
     */
    public function getProductToUpdate($fromDate, $toDate)
    {
        dol_syslog(__METHOD__ . ": start gt = " . (!empty($fromDate) ? dol_print_date($fromDate, 'standard') : 'none') .
            ", lt = " . (!empty($toDate) ? dol_print_date($toDate, 'standard') : 'none') . " for site ID {$this->site->id}", LOG_DEBUG);
        global $conf, $langs;

		$this->errors = array();
		$this->initECommerceProduct();
        $last_update = [];
        $product_variation = [];
        $result = [];
        $idxPage = 1;
        $per_page = empty($conf->global->ECOMMERCENG_MAXSIZE_MULTICALL) ? 100 : min($conf->global->ECOMMERCENG_MAXSIZE_MULTICALL, 100);
        $from_date = isset($fromDate) && !empty($fromDate) ? new DateTime(dol_print_date($fromDate, 'standard')) : null;
        $to_date = isset($toDate) && !empty($toDate) ? new DateTime(dol_print_date($toDate, 'standard')) : null;

        $filter = ['limit' => $per_page];
        if (isset($fromDate) && !empty($fromDate)) $filter['updated_at_min'] = dol_print_date($fromDate - (24 * 60 * 60), 'dayrfc');
        if (isset($toDate) && !empty($toDate)) $filter['updated_at_max'] = dol_print_date($toDate + (24 * 60 * 60), 'dayrfc');

        while (true) {
            try {
                $page = $this->clientOld->get('products',
                    [
                        'page' => $idxPage++,
                        'filter' => $filter,
                        'fields' => 'id,created_at,updated_at,variations',
                    ]
                );
            } catch (HttpClientException $fault) {
                $this->errors[] = $langs->trans('ECommerceWoocommerceGetProductToUpdate', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
                dol_syslog(__METHOD__ .
                    ': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceGetProductToUpdate', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
                    ' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
                return false;
            }

            if (!isset($page->products) || ($nbProducts = count($page->products)) == 0) break;
            $page = $page->products;

            foreach ($page as $product) {
                $id = $product->id;
                $update = false;
                $date_product = $this->getDateTimeFromGMTDateTime(!empty($product->updated_at) ? $product->updated_at : $product->created_at);
                $update_parent = false;
                if ($from_date == $date_product) {
                    if ($this->eCommerceProduct->fetchByRemoteId($id, $this->site->id) > 0) {
                        if (isset($this->eCommerceProduct->last_update) && !empty($this->eCommerceProduct->last_update)) {
                            $date = new DateTime(dol_print_date($this->eCommerceProduct->last_update, 'standard'));
                            if ($date < $from_date) {
                                $update_parent = true;
                            }
                        }
                    } else {
                        $update_parent = true;
                    }
                }

                // Product
                if ($update_parent || (!isset($from_date) || $from_date < $date_product) && (!isset($to_date) || $date_product <= $to_date)) {
                    $result[$id] = $id;
                    $last_update[$id] = $date_product->format('Y-m-d H:i:s');
                    $product_variation[$id] = 0;
                    $update = true;
                }

                // Variations
                foreach ($product->variations as $variation) {
                    $id = $product->id . '|' . $variation->id;
                    $date_variation = $this->getDateTimeFromGMTDateTime(!empty($variation->updated_at) ? $variation->updated_at : $variation->created_at);
                    $update_variante = false;
                    if ($from_date == $date_product) {
                        if ($this->eCommerceProduct->fetchByRemoteId($id, $this->site->id) > 0) {
                            if (isset($this->eCommerceProduct->last_update) && !empty($this->eCommerceProduct->last_update)) {
                                $date = new DateTime(dol_print_date($this->eCommerceProduct->last_update, 'standard'));
                                if ($date < $from_date) {
                                    $update_variante = true;
                                }
                            }
                        } else {
                            $update_variante = true;
                        }
                    }

                    if ($update || $update_variante || ((!isset($from_date) || $from_date < $date_variation) && (!isset($to_date) || $date_variation <= $to_date))) {
                        $result[$id] = $id;
                        $last_update[$id] = $date_product->format('Y-m-d H:i:s'); //$date_product > $date_variation ? $date_product->format('Y-m-d H:i:s') : $date_variation->format('Y-m-d H:i:s');
                        $product_variation[$id] = 1;
                    }
                }
            }
        }

        //important - order by last update
        if (count($result)) {
            uasort($result, function($a, $b) use ($product_variation, $last_update) {
                if($last_update[$a] == $last_update[$b]) {
                    if($product_variation[$a] == $product_variation[$b])
                        return strcmp($a, $b);

                    return $product_variation[$a] > $product_variation[$b] ? 1 : -1;
                }
                return $last_update[$a] > $last_update[$b] ? 1 : -1;
            });
        }

        dol_syslog(__METHOD__ . ": end", LOG_DEBUG);
        return $result;
    }

    /**
     * Call Woocommerce API to get last updated orders. We are interested here by list of id only. We will retreive properties later.
     *
     * @param   int             $fromDate   From date
     * @param   int             $toDate     To date
     *
     * @return  array|boolean               List of orders ID to update or false if error
     */
    public function getCommandeToUpdate($fromDate, $toDate)
    {
        dol_syslog(__METHOD__ . ": start gt = " . (!empty($fromDate) ? dol_print_date($fromDate, 'standard') : 'none') .
            ", lt = " . (!empty($toDate) ? dol_print_date($toDate, 'standard') : 'none') . " for site ID {$this->site->id}", LOG_DEBUG);
        global $conf, $langs;

		$this->errors = array();
		$this->initECommerceCommande();
        $last_update = [];
        $result = [];
        $idxPage = 1;
        $per_page = empty($conf->global->ECOMMERCENG_MAXSIZE_MULTICALL) ? 100 : min($conf->global->ECOMMERCENG_MAXSIZE_MULTICALL, 100);
        $from_date = isset($fromDate) && !empty($fromDate) ? new DateTime(dol_print_date($fromDate, 'standard')) : null;
        $to_date = isset($toDate) && !empty($toDate) ? new DateTime(dol_print_date($toDate, 'standard')) : null;

        $filter = ['limit' => $per_page];
        if (isset($fromDate) && !empty($fromDate)) $filter['updated_at_min'] = dol_print_date($fromDate - (24 * 60 * 60), 'dayrfc');
        if (isset($toDate) && !empty($toDate)) $filter['updated_at_max'] = dol_print_date($toDate + (24 * 60 * 60), 'dayrfc');

        while (true) {
            try {
                $page = $this->clientOld->get('orders',
                    [
                        'page' => $idxPage++,
                        'filter' => $filter,
                        'fields' => 'id,created_at,updated_at'
                    ]
                );
            } catch (HttpClientException $fault) {
                $this->errors[] = $langs->trans('ECommerceWoocommerceGetCommandeToUpdate', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
                dol_syslog(__METHOD__ .
                    ': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceGetCommandeToUpdate', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
                    ' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
                return false;
            }

            if (!isset($page->orders) || ($nbOrders = count($page->orders)) == 0) break;
            $page = $page->orders;

            foreach ($page as $order) {
                $id = $order->id;
                $date_order = $this->getDateTimeFromGMTDateTime(!empty($order->updated_at) ? $order->updated_at : $order->created_at);
                $update_order = false;
                if ($from_date == $date_order) {
                    if ($this->eCommerceCommande->fetchByRemoteId($id, $this->site->id) > 0) {
                        if (isset($this->eCommerceCommande->last_update) && !empty($this->eCommerceCommande->last_update)) {
                            $date = new DateTime(dol_print_date($this->eCommerceCommande->last_update, 'standard'));
                            if ($date < $from_date) {
                                $update_order = true;
                            }
                        }
                    } else {
                        $update_order = true;
                    }
                }

                if ($update_order || (!isset($from_date) || $from_date < $date_order) && (!isset($to_date) || $date_order <= $to_date)) {
                    $result[$id] = $id;
                    $last_update[$id] = $date_order->format('Y-m-d H:i:s');
                }
            }
        }

        //important - order by last update
        if (count($result)) {
            array_multisort($last_update, SORT_ASC, $result);
        }

        dol_syslog(__METHOD__ . ": end", LOG_DEBUG);
        return $result;
    }

    /**
     * Desactivated because is not supported by woocommerce.
     *
     * @param   int     $fromDate   From date
     * @param   int     $toDate     To date
     *
     * @return  array               Empty list
     */
    public function getFactureToUpdate($fromDate, $toDate)
    {
        dol_syslog(__METHOD__ . ": Desactivated for site ID {$this->site->id}", LOG_DEBUG);
		$this->errors = array();
		return [];
    }

	/**
	 * Call Woocommerce API to get company datas and put into dolibarr company class.
	 *
	 * @param	int				$from_date			Synchronize from date
	 * @param	int				$to_date			Synchronize to date
	 * @param   array           $remoteObject 		List of id of remote companies to convert
	 * @param   int             $toNb         		Max nb
	 * @return  array|boolean                 		List of companies sorted by update time or false if error.
	 */
	public function convertRemoteObjectIntoDolibarrSociete($from_date = null, $to_date = null, $remoteObject = array(), $toNb=0)
	{
		dol_syslog(__METHOD__ . ": Get " . count($remoteObject) . " remote companies ID: " . implode(', ', $remoteObject) . " for site ID {$this->site->id}", LOG_DEBUG);
		global $conf, $langs;

		$this->errors = array();
		$companies = [];
		$nb_max_by_request = empty($conf->global->ECOMMERCENG_MAXSIZE_MULTICALL) ? 100 : min($conf->global->ECOMMERCENG_MAXSIZE_MULTICALL, 100);
		$from_date = isset($from_date) && !empty($from_date) ? dol_print_date($from_date, 'dayhourrfc') : null;
		$to_date = isset($to_date) && !empty($to_date) ? dol_print_date($to_date, 'dayhourrfc') : null;

		$filters = [
			'per_page' => $nb_max_by_request,
			'orderby' => 'registered_date',
			'order' => 'desc',
			'role' => empty($this->site->parameters['customer_roles']) ? 'all' : $this->site->parameters['customer_roles'],
		];
		if ($toNb > 0) $filters['per_page'] = min($nb_max_by_request, $toNb);
		if (!empty($remoteObject)) $filters['include'] = implode(',', $remoteObject);
		else {
			if (isset($from_date)) $filters['after'] = $from_date;
			if (isset($to_date)) $filters['before'] = $to_date;
		}

		$idxPage = 1;
		$nbTotalRecords = 0;
		while (true) {
			$filters['page'] = $idxPage++;
			$stopwatch_id = -1;
			try {
				$stopwatch_id = eCommerceUtils::startStopwatch(__METHOD__ . " - GET customers (filters: " . json_encode($filters). ")");
				$page = $this->client->get('customers', $filters);
				eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
			} catch (HttpClientException $fault) {
				eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
				$this->errors[] = $langs->trans('ECommerceWoocommerceConvertRemoteObjectIntoDolibarrSociete', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
				dol_syslog(__METHOD__ .
					': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceConvertRemoteObjectIntoDolibarrSociete', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
					' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
				return false;
			}

			if (!isset($page) || count($page) == 0) break;

			foreach ($page as $company) {
				$company_data = $this->convertCustomerDataIntoProcessedData($company);
				if ($company_data === false) return false;
				$companies[] = $company_data;
				if ($toNb > 0 && ++$nbTotalRecords >= $toNb) break;
			}

			if ($toNb > 0 && $nbTotalRecords >= $toNb) break;
		}

		dol_syslog(__METHOD__ . ": end, converted " . count($companies) . " remote companies", LOG_DEBUG);
		return $companies;
	}

	/**
	 * Call Woocommerce API to get company datas and put into dolibarr company class.
	 *
	 * @param	array		$remote_data 	Remote data
	 * @return  array                 		Data processed.
	 */
	public function convertCustomerDataIntoProcessedData($remote_data)
	{
		dol_syslog(__METHOD__ . " remote_data=" . json_encode($remote_data), LOG_DEBUG);
		global $conf, $langs, $mysoc;

		$this->errors = array();
		$last_update = $this->getDateTimeFromGMTDateTime(/*!empty($remote_data->date_modified_gmt) ? $remote_data->date_modified_gmt :*/ $remote_data->date_created_gmt);

		// Global infos
		$item = [
			'create_date' => strtotime($remote_data->date_created),
			'remote_id' => $remote_data->id,
			'last_update' => $last_update->format('Y-m-d H:i:s'),
			'name_alias' => null,
			'email_key' => $remote_data->email,
			'client' => 1,
			'vatnumber' => null,
			'note_private' => "Site: '{$this->site->name}' - ID: {$remote_data->id}",
			'address' => $remote_data->billing->address_1 . (!empty($remote_data->billing->address_1) && !empty($remote_data->billing->address_2) ? "\n" : "") . $remote_data->billing->address_2,
			'zip' => $remote_data->billing->postcode,
			'town' => $remote_data->billing->city,
			'country_id' => getCountry($remote_data->billing->country, 3),
			'phone' => $remote_data->billing->phone,
			'default_lang' => $mysoc->default_lang,
			'remote_datas' => $remote_data,
			'extrafields' => [
				"ecommerceng_wc_role_{$this->site->id}_{$conf->entity}" => $langs->trans('ECommercengWoocommerceCompanyRole_' . $remote_data->role),
			],
		];

		// Default language
		if ($item['country_id'] != $mysoc->country_id && !empty($conf->global->ECOMMERCENG_WOOCOMMERCE_DEFAULT_LANG_OTHER_COUNTRY)) {
			$item['default_lang'] = $conf->global->ECOMMERCENG_WOOCOMMERCE_DEFAULT_LANG_OTHER_COUNTRY;
		}

		// Meta datas
		if (!empty($conf->global->ECOMMERCENG_WOOCOMMERCE_VAT_NUMBER_META_NAME)) {
			foreach ($remote_data->meta_data as $data) {
				if ($data->key == $conf->global->ECOMMERCENG_WOOCOMMERCE_VAT_NUMBER_META_NAME) {
					$item['vatnumber'] = $data->value;
					break;
				}
			}
		}

		// Company
		if (!empty($remote_data->billing->company)) {
			$item['type'] = 'company';
			$item['name'] = $remote_data->billing->company;
			$item['email'] = !empty($conf->global->ECOMMERCENG_WOOCOMMERCE_GET_EMAIL_ON_COMPANY) ? $remote_data->email : null;
		} // User
		else {
			$firstname = !empty($remote_data->first_name) ? $remote_data->first_name : $remote_data->billing->first_name;
			$lastname = !empty($remote_data->last_name) ? $remote_data->last_name : $remote_data->billing->last_name;
			if (!empty($conf->global->ECOMMERCENG_UPPERCASE_LASTNAME)) {
				$firstname = dol_ucwords(dol_strtolower($firstname));
				$lastname = dol_strtoupper($lastname);
			}
			if (!empty($firstname) && !empty($lastname)) {
				$name = dolGetFirstLastname($firstname, $lastname);
			} elseif (!empty($firstname)) {
				$name = dolGetFirstLastname($firstname, $langs->transnoentitiesnoconv("ECommerceLastNameNotInformed"));
			} else {
				$name = $langs->transnoentitiesnoconv('ECommerceFirstNameLastNameNotInformed');
			}
			$item['type'] = 'user';
			$item['name'] = $name;
			$item['email'] = $remote_data->email;
		}

		return $item;
	}

    /**
     * Call Woocommerce API to get contact datas and put into dolibarr contact class.
     *
     * @param   array           $remoteCompany Remote company infos
     * @return  array|boolean                  List of contact sorted by update time or false if error.
     */
    public function convertRemoteObjectIntoDolibarrSocpeople($remoteCompany)
    {
        dol_syslog(__METHOD__ . ": Get remote contacts ID: {$remoteCompany->id} for site ID {$this->site->id}", LOG_DEBUG);
        global $conf, $langs;

		$this->errors = array();
		$contacts = [];
        $last_update = $this->getDateTimeFromGMTDateTime(/*!empty($remoteCompany->date_modified_gmt) ? $remoteCompany->date_modified_gmt :*/ $remoteCompany->date_created_gmt);

        $bContact = $remoteCompany->billing;
        if (!empty($bContact->address_1) || !empty($bContact->address_2) || !empty($bContact->postcode) ||
            !empty($bContact->city) || !empty($bContact->country) ||
            !empty($bContact->email) || !empty($bContact->company) || !empty($bContact->phone)
        ) {
            $firstname = !empty($bContact->first_name) ? $bContact->first_name : $remoteCompany->first_name;
            $lastname = !empty($bContact->last_name) ? $bContact->last_name : $remoteCompany->last_name;
			if (!empty($conf->global->ECOMMERCENG_UPPERCASE_LASTNAME)) {
				$firstname = dol_ucwords(dol_strtolower($firstname));
				$lastname = dol_strtoupper($lastname);
			}
            if (!empty($firstname) && empty($lastname)) {
                $lastname = $langs->transnoentitiesnoconv("ECommerceLastNameNotInformed");
            } elseif (empty($firstname) && empty($lastname)) {
                $lastname = $langs->transnoentitiesnoconv('ECommerceFirstNameLastNameNotInformed');
            }
            $contacts[] = [
                'remote_id' => null,
                'last_update' => $last_update->format('Y-m-d H:i:s'),
                'firstname' => $firstname,
                'lastname' => $lastname,
                'address' => $bContact->address_1 . (!empty($bContact->address_1) && !empty($bContact->address_2) ? "\n" : "") . $bContact->address_2,
                'zip' => $bContact->postcode,
                'town' => $bContact->city,
                'country_id' => getCountry($bContact->country, 3),
                'email' => !empty($bContact->email) ? $bContact->email : $remoteCompany->email,
                'phone' => $bContact->phone,
                'fax' => null,
            ];
        }

        $sContact = $remoteCompany->shipping;
        if (!empty($sContact->address_1) || !empty($sContact->address_2) ||
            !empty($sContact->postcode) || !empty($sContact->city) ||
            !empty($sContact->country)
        ) {
            if ($bContact->first_name != $sContact->first_name || $bContact->last_name != $sContact->last_name ||
                $bContact->address_1 != $sContact->address_1 || $bContact->address_2 != $sContact->address_2 ||
                $bContact->postcode != $sContact->postcode || $bContact->city != $sContact->city ||
                $bContact->country != $sContact->country
            ) {
                $firstname = !empty($sContact->first_name) ? $sContact->first_name : $remoteCompany->first_name;
                $lastname = !empty($sContact->last_name) ? $sContact->last_name : $remoteCompany->last_name;
				if (!empty($conf->global->ECOMMERCENG_UPPERCASE_LASTNAME)) {
					$firstname = dol_ucwords(dol_strtolower($firstname));
					$lastname = dol_strtoupper($lastname);
				}
                if (!empty($firstname) && empty($lastname)) {
                    $lastname = $langs->transnoentitiesnoconv("ECommerceLastNameNotInformed");
                } elseif (empty($firstname) && empty($lastname)) {
                    $lastname = $langs->transnoentitiesnoconv('ECommerceFirstNameLastNameNotInformed');
                }
                $contacts[] = [
                    'remote_id' => null,
                    'last_update' => $last_update->format('Y-m-d H:i:s'),
                    'firstname' => $firstname,
                    'lastname' => $lastname,
                    'address' => $sContact->address_1 . (!empty($sContact->address_1) && !empty($sContact->address_2) ? "\n" : "") . $sContact->address_2,
                    'zip' => $sContact->postcode,
                    'town' => $sContact->city,
                    'country_id' => getCountry($sContact->country, 3),
                    'email' => null,
                    'phone' => null,
                    'fax' => null,
                ];
            }
        }

        dol_syslog(__METHOD__ . ": end, converted " . count($contacts) . " remote contacts", LOG_DEBUG);
        return $contacts;
    }

    /**
     * Call Woocommerce API to get product datas and put into dolibarr product class.
     *
	 * @param	int				$from_date			Synchronize from date
	 * @param	int				$to_date			Synchronize to date
     * @param   array           $remoteObject List of id of remote products to convert
     * @param   int             $toNb         Max nb
     * @return  array|boolean                 List of products sorted by update time or false if error.
     */
    public function convertRemoteObjectIntoDolibarrProduct($from_date = null, $to_date = null, $remoteObject = array(), $toNb=0)
    {
        dol_syslog(__METHOD__ . ": Get " . count($remoteObject) . " remote products ID: " . implode(', ', $remoteObject) . " for site ID {$this->site->id}", LOG_DEBUG);
        global $conf, $langs;

		$this->errors = array();
		$products = [];
        $nb_max_by_request = empty($conf->global->ECOMMERCENG_MAXSIZE_MULTICALL) ? 100 : min($conf->global->ECOMMERCENG_MAXSIZE_MULTICALL, 100);
		$from_date = isset($from_date) ? dol_print_date($from_date, 'dayhourrfc') : null;
		$to_date = isset($to_date) ? dol_print_date($to_date, 'dayhourrfc') : null;
		$product_variation_mode_all_to_one = !empty($this->site->parameters['product_variation_mode']) && $this->site->parameters['product_variation_mode'] == 'all_to_one';

		$include_ids = [];
		$include_variation_ids = [];
		foreach ($remoteObject as $id) {
			$ids = explode('|', $id);
			$remote_id = $ids[0];
			$include_ids[$remote_id] = $remote_id;
			if (isset($ids[1])) {
				if (!isset($include_variation_ids[$remote_id])) $include_variation_ids[$remote_id] = array();
				$remote_variation_id = $ids[1];
				$include_variation_ids[$remote_id][$remote_variation_id] = $remote_variation_id;
			}
		}

		$filters = [
			'per_page' => $nb_max_by_request,
			'orderby' => 'date',
			'order' => 'asc',
		];
		if ($toNb > 0) $filters['per_page'] = min($nb_max_by_request, $toNb);
		if (empty($remoteObject)) $filters['status'] = 'publish';
		if (!empty($include_ids)) $filters['include'] = implode(',', $include_ids);
		else {
			if (isset($from_date)) $filters['after'] = $from_date;
			if (isset($to_date)) $filters['before'] = $to_date;
		}

		$idxPage = 1;
		$nbTotalRecords = 0;
		while (true) {
			$filters['page'] = $idxPage++;
			$stopwatch_id = eCommerceUtils::startStopwatch(__METHOD__ . " - GET products (filters: " . json_encode($filters). ")");
			try {
				$page = $this->client->get('products', $filters);
				eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
			} catch (HttpClientException $fault) {
				eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
				$this->errors[] = $langs->trans('ECommerceWoocommerceConvertRemoteObjectIntoDolibarrProduct', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
				dol_syslog(__METHOD__ .
					': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceConvertRemoteObjectIntoDolibarrProduct', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
					' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
				return false;
			}

			if (!isset($page) || count($page) == 0) break;

			foreach ($page as $product) {
				// Don't synchronize the variation parent
				if (empty($product->variations) || !empty($product->parent_id)) {
					$data = $this->convertProductDataIntoProcessedData($product);
					if (!is_array($data)) {
						$this->errors = array_merge(array($langs->trans('ECommerceErrorWhenConvertProductData', $product->id)), $this->errors);
						return false;
					}
					$products[] = $data;
				}

				// Synchronize all the variations of the product if only the parent is provided
				if (!empty($product->variations) && empty($include_variation_ids[$product->id])) {
					$include_variation_ids[$product->id] = $product->variations;
				}

				// Variations
				if (!empty($include_variation_ids[$product->id])) {
					if ($product_variation_mode_all_to_one) {
						$tmp = array_values($include_variation_ids[$product->id]);
						$include_variation_ids[$product->id] = array($tmp[0]);
					}

					$requestGroupsVariations = $this->getRequestGroups($include_variation_ids[$product->id], $nb_max_by_request);
					foreach ($requestGroupsVariations as $requestVariations) {
						dol_syslog(__METHOD__ . ": Get " . count($requestVariations) . " products variations of remote product (ID:{$product->id}): " . implode(', ', $requestVariations), LOG_DEBUG);
						$filters =  [
							'per_page' => $nb_max_by_request,
							'include' => implode(',', $requestVariations),
						];
						$stopwatch_id = eCommerceUtils::startStopwatch(__METHOD__ . " - GET products/{$product->id}/variations (filters: " . json_encode($filters). ")");
						try {
							$results = $this->client->get('products/' . $product->id . '/variations', $filters);
							eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
						} catch (HttpClientException $fault) {
							eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
							$this->errors[] = $langs->trans('ECommerceWoocommerceConvertRemoteObjectIntoDolibarrProductVariations', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
							dol_syslog(__METHOD__ .
								': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceConvertRemoteObjectIntoDolibarrProductVariations', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
								' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
							return false;
						}

						if (is_array($results)) {
							foreach ($results as $variation) {
								$data = $this->convertProductDataIntoProcessedData($variation, $product);
								if (!is_array($data)) {
									$this->errors = array_merge(array($langs->trans('ECommerceErrorWhenConvertProductData', $product->id . '|' . $variation->id)), $this->errors);
									return false;
								}
								$products[] = $data;
							}
						}
					}
				}

				if ($toNb > 0 && ++$nbTotalRecords >= $toNb) break;
			}

			if ($toNb > 0 && $nbTotalRecords >= $toNb) break;
		}

        dol_syslog(__METHOD__ . ": end, converted " . count($products) . " remote products", LOG_DEBUG);
        return $products;
    }

	/**
	 * Call Woocommerce API to get product datas and put into dolibarr product class.
	 *
	 * @param	array		$remote_data 			Remote data
	 * @param	array		$parent_remote_data 	Parent remote data if the product is a variation
	 * @return  array|bool             				FALSE if KO otherwise data processed.
	 */
	public function convertProductDataIntoProcessedData($remote_data, $parent_remote_data = null)
	{
		dol_syslog(__METHOD__ . " remote_data=" . json_encode($remote_data), LOG_DEBUG);
		global $conf, $langs;

		$this->errors = array();
		$isVariation = isset($parent_remote_data) || $remote_data->parent_id > 0;
		$parent_id = isset($parent_remote_data) ? $parent_remote_data->id : ($remote_data->parent_id > 0 ? $remote_data->parent_id : 0);
		if ($isVariation && empty($parent_remote_data) && !empty($parent_id)) {
			$stopwatch_id = eCommerceUtils::startStopwatch(__METHOD__ . " - GET products/{$parent_id}");
			try {
				$parent_remote_data = $this->client->get('products/' . $parent_id);
				eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
			} catch (HttpClientException $fault) {
				eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
				$this->errors[] = $langs->trans('ECommerceWoocommerceConvertRemoteObjectIntoDolibarrProduct', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
				dol_syslog(__METHOD__ .
					': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceConvertRemoteObjectIntoDolibarrProduct', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
					' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
				return false;
			}
		}

		// if the parent has no variations (ex: webhook of a variation transformed in a simple product before the webhook is precessed)
		if ($isVariation && empty($parent_remote_data->variations)) {
			$isVariation = false;
			$parent_id = 0;
			$remote_data = $parent_remote_data;
			$parent_remote_data = null;
		}

		$canvas = '';
		$productSynchPrice = isset($this->site->parameters['product_synch_price']) ? $this->site->parameters['product_synch_price'] : 'regular';
		$productImageSynchDirection = isset($this->site->parameters['product_synch_direction']['image']) ? $this->site->parameters['product_synch_direction']['image'] : '';
		$productRefSynchDirection = isset($this->site->parameters['product_synch_direction']['ref']) ? $this->site->parameters['product_synch_direction']['ref'] : '';
		$productDescriptionSynchDirection = isset($this->site->parameters['product_synch_direction']['description']) ? $this->site->parameters['product_synch_direction']['description'] : '';
		$productShortDescriptionSynchDirection = isset($this->site->parameters['product_synch_direction']['short_description']) ? $this->site->parameters['product_synch_direction']['short_description'] : '';
		$productWeightSynchDirection = isset($this->site->parameters['product_synch_direction']['weight']) ? $this->site->parameters['product_synch_direction']['weight'] : '';
		$productTaxSynchDirection = isset($this->site->parameters['product_synch_direction']['tax']) ? $this->site->parameters['product_synch_direction']['tax'] : '';
		$productStatusSynchDirection = isset($this->site->parameters['product_synch_direction']['status']) ? $this->site->parameters['product_synch_direction']['status'] : '';
		$productWeightUnits = isset($this->site->parameters['product_weight_units']) ? $this->site->parameters['product_weight_units'] : (empty($conf->global->MAIN_WEIGHT_DEFAULT_UNIT)?0:$conf->global->MAIN_WEIGHT_DEFAULT_UNIT);
		$product_variation_mode_all_to_one = !empty($this->site->parameters['product_variation_mode']) && $this->site->parameters['product_variation_mode'] == 'all_to_one';

		// Categories
		$categories = [];
		$parent_categories = is_array($parent_remote_data->categories) ? $parent_remote_data->categories : array();
		$categories_data = is_array($remote_data->categories) ? $remote_data->categories : array();
		$categories_data = array_merge($categories_data, $parent_categories);
		foreach ($categories_data as $category) {
			$categories[$category->id] = $category->id;
		}

		// Label
		$label = $remote_data->name;
		if ($isVariation && (empty($label) || $product_variation_mode_all_to_one)) {
			$label = $parent_remote_data->name;
			// Attributes of the variation
			if (is_array($remote_data->attributes) && !$product_variation_mode_all_to_one) {
				foreach ($remote_data->attributes as $attribute) {
					$label .= ' - ' . $attribute->option;
				}
			}
		}

		$last_update_product = $this->getDateTimeFromGMTDateTime(!empty($remote_data->date_modified_gmt) ? $remote_data->date_modified_gmt : $remote_data->date_created_gmt);
		$last_update = $last_update_product->format('Y-m-d H:i:s');

		$price = $productSynchPrice == 'selling' ? $remote_data->price : $remote_data->regular_price;
		if (!empty($remote_data->date_on_sale_from_gmt)) {
			$date_on_sale_from = $this->getDateTimeFromGMTDateTime($remote_data->date_on_sale_from_gmt);
			$date_on_sale_from = isset($date_on_sale_from) ? $date_on_sale_from->getTimestamp() : '';
		} else {
			$date_on_sale_from = '';
		}
		if (!empty($remote_data->date_on_sale_to_gmt)) {
			$date_on_sale_to = $this->getDateTimeFromGMTDateTime($remote_data->date_on_sale_to_gmt);
			$date_on_sale_to = isset($date_on_sale_to) ? $date_on_sale_to->getTimestamp() : '';
		} else {
			$date_on_sale_to = '';
		}

		// Manage the variations products removed
		$variations = array();
		if (!$product_variation_mode_all_to_one) {
			$variations_list = array();
			if (!empty($parent_remote_data->variations)) {
				foreach ($parent_remote_data->variations as $v) {
					$variations_list[] = $parent_id . '|' . $v;
				}
				$variations = array(
					'parent_remote_id' => $parent_id,
					'filter' => $parent_id . '|%',
					'list' => $variations_list,
				);
			} elseif (!empty($remote_data->variations)) {
				foreach ($remote_data->variations as $v) {
					$variations_list[] = $remote_data->id . '|' . $v;
				}
				$variations = array(
					'parent_remote_id' => $remote_data->id,
					'filter' => $remote_data->id . '|%',
					'list' => $variations_list,
				);
			}
		}

		$remote_id = $remote_data->id;
		$remote_parent_id = 0;
		if ($isVariation) {
			if ($product_variation_mode_all_to_one) {
				$remote_id = $parent_id . '|' . implode('|', $parent_remote_data->variations);
				$remote_parent_id = $parent_id;
			} else {
				$remote_id = $parent_id . '|' . $remote_data->id;
				$remote_parent_id = $parent_id;
			}
		} elseif (!empty($remote_data->variations) && $product_variation_mode_all_to_one) {
			$remote_id = $remote_data->id . '|' . implode('|', $remote_data->variations);
			$remote_parent_id = $remote_data->id;
		}

		$product = [
			'create_date' => strtotime($remote_data->date_created),
			'remote_id' => $remote_id,
			'remote_parent_id' => $remote_parent_id,
			'last_update' => $last_update,
			'fk_product_type' => ($remote_data->virtual ? 1 : 0), // 0 (product) or 1 (service)
			'status' => $remote_data->status,
			'label' => $label,
			'price' => $price,
			'envente' => ($isVariation || $product_variation_mode_all_to_one || empty($remote_data->variations) ? 1 : 0),
			'enachat' => null,
			'finished' => 1,    // 1 = manufactured, 0 = raw material
			'canvas' => $canvas,
			'remote_datas' => ($isVariation && isset($parent_remote_data) ? [ 'variation' => $remote_data, 'parent' => $parent_remote_data ] : $remote_data),
			'categories' => $categories,
			'price_min' => '',
			'fk_country' => '',
			'url' => $isVariation && $product_variation_mode_all_to_one ? $parent_remote_data->permalink : $remote_data->permalink,
			// Stock
			'stock_qty' => $remote_data->stock_quantity,
			'is_in_stock' => $remote_data->in_stock,   // not used
			'variations' => $variations,
			'extrafields' => [
				"ecommerceng_wc_regular_price_{$this->site->id}_{$conf->entity}" => $remote_data->regular_price,
				"ecommerceng_wc_sale_price_{$this->site->id}_{$conf->entity}" => $remote_data->sale_price,
				"ecommerceng_wc_date_on_sale_from_{$this->site->id}_{$conf->entity}" => $date_on_sale_from,
				"ecommerceng_wc_date_on_sale_to_{$this->site->id}_{$conf->entity}" => $date_on_sale_to,
				"ecommerceng_wc_manage_stock_{$this->site->id}_{$conf->entity}" => !empty($remote_data->manage_stock) ? 1 : 0,
			],
		];

		// Synchronize ref
		if ($productRefSynchDirection == 'etod' || $productRefSynchDirection == 'all') {
			$product['ref'] = $remote_data->sku;
		}
		// Synchronize short and long description
		if ($productDescriptionSynchDirection == 'etod' || $productDescriptionSynchDirection == 'all') {
			$product['extrafields']["ecommerceng_description_{$conf->entity}"] = $this->replace4byte(empty($remote_data->description) ? $parent_remote_data->description : $remote_data->description);
		}
		if ($productShortDescriptionSynchDirection == 'etod' || $productShortDescriptionSynchDirection == 'all') {
			$product['extrafields']["ecommerceng_short_description_{$conf->entity}"] = $this->replace4byte(empty($remote_data->short_description) ? $parent_remote_data->short_description : $remote_data->short_description);
		}
		// Synchronize weight
		if ($productWeightSynchDirection == 'etod' || $productWeightSynchDirection == 'all') {
			$product['weight'] = empty($remote_data->weight) ? $parent_remote_data->weight : $remote_data->weight;
			$product['weight_units'] = $productWeightUnits;
		}
		// Synchronize tax
		$tax_info = $this->getTaxInfoFromTaxClass(empty($remote_data->tax_class) ? $parent_remote_data->tax_class : $remote_data->tax_class, empty($remote_data->tax_status) ? $parent_remote_data->tax_status : $remote_data->tax_status);
		if (!$isVariation) $product['tax_rate'] = $tax_info['tax_rate'];
		if ($productTaxSynchDirection == 'etod' || $productTaxSynchDirection == 'all') {
			if ($isVariation) $product['tax_rate'] = $tax_info['tax_rate'];
			$product['extrafields']["ecommerceng_tax_class_{$this->site->id}_{$conf->entity}"] = $tax_info['tax_class'];
		}
		// Synchronize status
		if ($productStatusSynchDirection == 'etod' || $productStatusSynchDirection == 'all') {
			$product['extrafields']["ecommerceng_wc_status_{$this->site->id}_{$conf->entity}"] = empty($remote_data->status) ? $parent_remote_data->status : $remote_data->status;
		}
		// Synchronize images
		if ($productImageSynchDirection == 'etod' || $productImageSynchDirection == 'all') {
			$images = [];

			// Image of the product or the parent product if is a variation
			$images_data = $isVariation ? $parent_remote_data->images : $remote_data->images;
			$images_data = is_array($images_data) ? $images_data : array();
			// Image of the variation
			if ($isVariation && !empty($remote_data->image)) $images_data[] = $remote_data->image;

			if (!empty($images_data)) {
				$media_url = $this->site->webservice_address . (substr($this->site->webservice_address, -1, 1) != '/' ? '/' : '') . 'wp-content/uploads/';

				foreach ($images_data as $image) {
					$last_update = $this->getDateTimeFromGMTDateTime(!empty($image->date_modified_gmt) ? $image->date_modified_gmt : $image->date_created_gmt);
					$image_url = $this->getCleanedRelativeUrl($media_url, $image->src);
					$images[] = [
						'filename' => dol_sanitizeFileName($image_url),
						'url' => $image->src,
						'date_modified' => $last_update->format('Y-m-d H:i:s'),
					];
				}
			}

			$product['images'] = $images;
		}

		// Synchronize metadata to extra fields
		if (!empty($this->site->parameters['ef_crp']['product'])) {
			$metas_data = array();
			if (is_array($remote_data->meta_data)) {
				foreach ($remote_data->meta_data as $meta) {
					$metas_data[$meta->key] = $meta;
				}
			}
			if ($isVariation && is_array($parent_remote_data->meta_data)) {
				foreach ($parent_remote_data->meta_data as $meta) {
					if (!isset($metas_data[$meta->key])) {
						$metas_data[$meta->key] = $meta;
					}
				}
			}

			if (!empty($metas_data)) {
				$correspondences = array();
				foreach ($this->site->parameters['ef_crp']['product'] as $key => $options_saved) {
					if ($options_saved['activated'] && !empty($options_saved['correspondences'])) {
						$correspondences[$options_saved['correspondences']] = $key;
					}
				}
				foreach ($metas_data as $meta) {
					if (isset($correspondences[$meta->key])) {
						$product['extrafields'][$correspondences[$meta->key]] = $meta->value;
					}
				}
			}
		}

		// Synchronize attribute to extra fields
		if (!empty($this->site->parameters['ef_crp_attribute'])) {
			$attributes = array();
			if (is_array($remote_data->attributes)) {
				foreach ($remote_data->attributes as $attribute) {
					$attributes[$attribute->name] = $attribute;
				}
			}
			if ($isVariation && is_array($parent_remote_data->attributes)) {
				foreach ($parent_remote_data->attributes as $attribute) {
					if (!isset($attributes[$attribute->name])) {
						$attributes[$attribute->name] = $attribute;
					}
				}
			}

			if (!empty($attributes)) {
				$correspondences = array();
				foreach ($this->site->parameters['ef_crp_attribute'] as $key => $options_saved) {
					if ($options_saved['activated'] && !empty($options_saved['correspondences'])) {
						$correspondences[$options_saved['correspondences']] = $key;
					}
				}
				foreach ($attributes as $attribute) {
					if (isset($correspondences[$attribute->name])) {
						$product['extrafields'][$correspondences[$attribute->name]] = implode(',', $attribute->options);
					}
				}
			}
		}

		return $product;
	}

	/**
	 *  Get the cleaned image path (without the base url path to the media folder and other parameters after character '?')
	 *
	 * @param	string		$root_url		Root url to remove
	 * @param	string		$img_url		Image URL
	 * @return  string                 		The cleaned image path
	 */
	public function getCleanedRelativeUrl($root_url, $img_url)
	{
		if (!empty($img_url)) {
			$img_url = str_replace($root_url, '/', $img_url);
			$pos = strpos($img_url, '?');
			if ($pos !== false) $img_url = substr($img_url, 0, $pos);
		}

		return $img_url;
	}

    /**
     * Call Woocommerce API to get order datas and put into dolibarr order class.
     *
	 * @param	int				$from_date			Synchronize from date
	 * @param	int				$to_date			Synchronize to date
     * @param   array           $remoteObject 		List of id of remote orders to convert
     * @param   int             $toNb        		Max nb
     * @return  array|boolean                 		List of orders sorted by update time or false if error.
     */
    public function convertRemoteObjectIntoDolibarrCommande($from_date = null, $to_date = null, $remoteObject = array(), $toNb=0)
    {
        dol_syslog(__METHOD__ . ": Get " . count($remoteObject) . " remote orders ID: " . implode(', ', $remoteObject) . " for site ID {$this->site->id}", LOG_DEBUG);
        global $conf, $langs;

		$this->errors = array();
		$orders = [];
		$nb_max_by_request = empty($conf->global->ECOMMERCENG_MAXSIZE_MULTICALL) ? 100 : min($conf->global->ECOMMERCENG_MAXSIZE_MULTICALL, 100);
		$from_date = isset($from_date) ? dol_print_date($from_date, 'dayhourrfc') : null;
		$to_date = isset($to_date) ? dol_print_date($to_date, 'dayhourrfc') : null;

		$filters = [
			'per_page' => $nb_max_by_request,
			'orderby' => 'date',
			'order' => 'asc',
		];
		if ($toNb > 0) $filters['per_page'] = min($nb_max_by_request, $toNb);
		if (!empty($remoteObject)) $filters['include'] = implode(',', $remoteObject);
		else {
			if (isset($from_date)) $filters['after'] = $from_date;
			if (isset($to_date)) $filters['before'] = $to_date;
		}

		$idxPage = 1;
		$nbTotalRecords = 0;
		while (true) {
			$filters['page'] = $idxPage++;
			$stopwatch_id = eCommerceUtils::startStopwatch(__METHOD__ . " - GET orders (filters: " . json_encode($filters). ")");
			try {
				$page = $this->client->get('orders', $filters);
				eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
			} catch (HttpClientException $fault) {
				eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
				$this->errors[] = $langs->trans('ECommerceWoocommerceConvertRemoteObjectIntoDolibarrCommande', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
				dol_syslog(__METHOD__ .
					': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceConvertRemoteObjectIntoDolibarrCommande', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
					' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
				return false;
			}

			if (!isset($page) || count($page) == 0) break;

			foreach ($page as $order) {
				$order_data = $this->convertOrderDataIntoProcessedData($order);
				if ($order_data === false) {
					$this->errors = array_merge(array($langs->trans('ECommerceErrorWhenConvertOrderData', $order->id)), $this->errors);
					return false;
				}
				$orders[] = $order_data;
				if ($toNb > 0 && ++$nbTotalRecords >= $toNb) break;
			}

			if ($toNb > 0 && $nbTotalRecords >= $toNb) break;
		}

        dol_syslog(__METHOD__ . ": end, converted " . count($orders) . " remote orders", LOG_DEBUG);
        return $orders;
    }

	/**
	 * Call Woocommerce API to get order datas and put into dolibarr order class.
	 *
	 * @param	array			$remote_data	Remote data
	 * @return  array|bool						FALSE if KO otherwise data processed.
	 */
	public function convertOrderDataIntoProcessedData($remote_data)
	{
		dol_syslog(__METHOD__ . " remote_data=" . json_encode($remote_data), LOG_DEBUG);
		global $conf, $langs;

		$this->errors = array();

		// Set product lines
		$items = [];
		if (!empty($remote_data->line_items)) {
			$product_variation_mode_all_to_one = !empty($this->site->parameters['product_variation_mode']) && $this->site->parameters['product_variation_mode'] == 'all_to_one';
			$order_metadata_product_lines_to_description_etod = !empty($this->site->parameters['order_metadata_product_lines_to_description_etod']);
			$order_filter_mode_metadata_product_lines_to_description_etod = !empty($this->site->parameters['order_filter_mode_metadata_product_lines_to_description_etod']) ? $this->site->parameters['order_filter_mode_metadata_product_lines_to_description_etod'] : 'exclude';
			$order_filter_keys_metadata_product_lines_to_description_etod = !empty($this->site->parameters['order_filter_keys_metadata_product_lines_to_description_etod']) ? array_filter(array_map('trim', explode(',', (string)$this->site->parameters['order_filter_keys_metadata_product_lines_to_description_etod'])), 'strlen') : array();

			$parent_match = array();
			foreach ($remote_data->line_items as $item) {
				if (!empty($item->composite_children) && is_array($item->composite_children)) {
					foreach ($item->composite_children as $child_id) {
						$parent_match[$child_id] = $item->id;
					}
				}
				$item_data = [
					'parent_item_id' => isset($parent_match[$item->id]) ? $parent_match[$item->id] : 0,
					'item_id' => $item->id,
					'label' => $item->name,
					'id_remote_product' => !empty($item->variation_id) ? (!$product_variation_mode_all_to_one ? $item->product_id . '|' . $item->variation_id : $item->product_id . '|%') : $item->product_id,
					'product_type' => 'simple',
					'price' => $item->subtotal != $item->total ? ($item->subtotal / $item->quantity) : $item->price,
					'total_ht' => $item->subtotal,
					'total_tva' => $item->subtotal_tax,
					'total_ttc' => $item->subtotal + $item->subtotal_tax,
					'qty' => $item->quantity,
					'discount' => 0,
					'buy_price' => null,
				];

				// Taxes
				$taxes = $this->getTaxesInfoFromRemoteData($item->taxes);
				if ($taxes === false) return false;
				$item_data['tva_tx'] = $taxes['tva_tx'];
				$item_data['local_tax1_tx'] = $taxes['local_tax1_tx'];
				$item_data['local_tax2_tx'] = $taxes['local_tax2_tx'];
				$item_data['total_local_tax1'] = $taxes['total_local_tax1'];
				$item_data['total_local_tax2'] = $taxes['total_local_tax2'];

				if (isset($item->cog_item_cost)) $item_data['buy_price'] = $this->site->ecommerce_price_type == 'TTC' ? 100 * $item->cog_item_cost / (100 + $item_data['tva_tx']) : $item->cog_item_cost;
				if ($this->site->ecommerce_price_type == 'TTC') $item_data['price'] = (100 * ($item->subtotal + $item->subtotal_tax) / (100 + $item_data['tva_tx'])) / $item->quantity;

				if (!empty($item->meta_data)) {
					// Synch extrafields <=> metadatas
					if (!empty($this->site->parameters['ef_crp']['commandedet'])) {
						$correspondences = array();
						foreach ($this->site->parameters['ef_crp']['commandedet'] as $key => $options_saved) {
							if ($options_saved['activated'] && !empty($options_saved['correspondences'])) {
								$correspondences[$options_saved['correspondences']] = $key;
							}
						}
						foreach ($item->meta_data as $meta) {
							if (isset($correspondences[$meta->key])) {
								$item_data['extrafields'][$correspondences[$meta->key]] = $meta->value;
							}
						}
					}
					// Add meta-data in description
					if ($order_metadata_product_lines_to_description_etod) {
						$metadata_in_description = array();
						foreach ($item->meta_data as $meta) {
							if (!empty($meta->display_key) && !empty($meta->display_value) && !is_array($meta->display_value) && !is_object($meta->display_value) && (
									($order_filter_mode_metadata_product_lines_to_description_etod == 'include' && in_array($meta->key, $order_filter_keys_metadata_product_lines_to_description_etod)) ||
									($order_filter_mode_metadata_product_lines_to_description_etod == 'exclude' && !in_array($meta->key, $order_filter_keys_metadata_product_lines_to_description_etod))
								)
							) {
								$metadata_in_description[] = $meta->display_key . ' : ' . $meta->display_value;
							}
						}
						if (!empty($metadata_in_description)) $item_data['additional_description'] = implode('<br>', $metadata_in_description);
					}
				}

				$items[] = $item_data;
			}
		}

		// Set shipping lines
		if (!empty($remote_data->shipping_lines)) {
			$shipment_service_id = $this->site->parameters['shipping_service'] > 0 ? $this->site->parameters['shipping_service'] : 0;
			foreach ($remote_data->shipping_lines as $item) {
				$item_data = [
					'item_id' => $item->id,
					'id_product' => $shipment_service_id,
					'label' => $langs->trans('ECommerceShipping') . (!empty($item->method_title) ? ' - ' . $item->method_title : ''),
					'description' => $langs->trans('ECommerceShipping') . (!empty($item->method_title) ? ' - ' . $item->method_title : ''),
					'product_type' => 'shipment',
					'price' => $item->total,
					'total_ht' => $item->total,
					'total_tva' => $item->total_tax,
					'total_ttc' => ($item->total + $item->total_tax),
					'qty' => 1,
					'discount' => 0,
				];

				// Taxes
				$taxes = $this->getTaxesInfoFromRemoteData($item->taxes);
				if ($taxes === false) return false;
				$item_data['tva_tx'] = $taxes['tva_tx'];
				$item_data['local_tax1_tx'] = $taxes['local_tax1_tx'];
				$item_data['local_tax2_tx'] = $taxes['local_tax2_tx'];
				$item_data['total_local_tax1'] = $taxes['total_local_tax1'];
				$item_data['total_local_tax2'] = $taxes['total_local_tax2'];

				if ($this->site->ecommerce_price_type == 'TTC') $item_data['price'] = 100 * ($item->total + $item->total_tax) / (100 + $item_data['tva_tx']);
				$item_data['buy_price'] = $item_data['price'];

				// Synch extrafields <=> metadatas
				if (!empty($item->meta_data) && !empty($this->site->parameters['ef_crp']['commandedet'])) {
					$correspondences = array();
					foreach ($this->site->parameters['ef_crp']['commandedet'] as $key => $options_saved) {
						if ($options_saved['activated'] && !empty($options_saved['correspondences'])) {
							$correspondences[$options_saved['correspondences']] = $key;
						}
					}
					foreach ($item->meta_data as $meta) {
						if (isset($correspondences[$meta->key])) {
							$item_data['extrafields'][$correspondences[$meta->key]] = $meta->value;
						}
					}
				}

				$items[] = $item_data;
			}
		}

		// Set discount code lines
		if (!empty($remote_data->coupon_lines)) {
			$discount_code_service_id = $this->site->parameters['discount_code_service'] > 0 ? $this->site->parameters['discount_code_service'] : 0;
//			if (!($discount_code_service_id > 0)) {
//				$this->errors[] = $langs->trans('ECommerceWooCommerceErrorDiscountCodeServiceNotConfigured', $this->site->name);
//				return false;
//			}
			foreach ($remote_data->coupon_lines as $item) {
				$item_data = [
					'item_id' => $item->id,
					'id_product' => $discount_code_service_id,
					'label' => $item->code,
					'description' => $item->code,
					'product_type' => 'discount_code',
					'qty' => 1,
					'discount' => 0,
					'buy_price' => 0,
					'local_tax1_tx' => 0,
					'local_tax2_tx' => 0,
					'total_local_tax1' => 0,
					'total_local_tax2' => 0,
				];

				// Taxes
				$tax_rate = 0;
				foreach ($remote_data->tax_lines as $data) {
					if (empty($data->tax_total)) continue;
					if ($data->rate_percent > $tax_rate) $tax_rate = $data->rate_percent;
				}

				$ttc = $item->discount + $item->discount_tax;
				$tva = $tax_rate * $ttc / ($tax_rate + 100);
				$ht = 100 * $ttc / ($tax_rate + 100);

				$item_data['tva_tx'] = $tax_rate;
				$item_data['price'] = -$ht;
				$item_data['total_ht'] = -$ht;
				$item_data['total_tva'] = -$tva;
				$item_data['total_ttc'] = -$ttc;

				// Synch extrafields <=> metadatas
				if (!empty($item->meta_data) && !empty($this->site->parameters['ef_crp']['commandedet'])) {
					$correspondences = array();
					foreach ($this->site->parameters['ef_crp']['commandedet'] as $key => $options_saved) {
						if ($options_saved['activated'] && !empty($options_saved['correspondences'])) {
							$correspondences[$options_saved['correspondences']] = $key;
						}
					}
					foreach ($item->meta_data as $meta) {
						if (isset($correspondences[$meta->key])) {
							$item_data['extrafields'][$correspondences[$meta->key]] = $meta->value;
						}
					}
				}

				$items[] = $item_data;
			}
		}

		// Set gift card lines
		if (!empty($remote_data->pw_gift_cards_redeemed)) {
			$gift_cards_service_id = $this->site->parameters['pw_gift_cards_service'] > 0 ? $this->site->parameters['pw_gift_cards_service'] : 0;
//			if (!($gift_cards_service_id > 0)) {
//				$this->errors[] = $langs->trans('ECommerceWooCommerceErrorPwGiftCardsServiceNotConfigured', $this->site->name);
//				return false;
//			}
			foreach ($remote_data->pw_gift_cards_redeemed as $gift_cards) {
				$items[] = [
					'product_type' => 'pw_gift_cards',
					'id_product' => $gift_cards_service_id,
					'description' => $gift_cards->number,
					'label' => $gift_cards->number,
					'price' => - $gift_cards->amount,
					'total_ht' => - $gift_cards->amount,
					'total_tva' => 0,
					'total_ttc' => - $gift_cards->amount,
					'qty' => 1,
					'discount' => 0,
					'buy_price' => 0,
					'tva_tx' => 0,
					'local_tax1_tx' => 0,
					'local_tax2_tx' => 0,
					'total_local_tax1' => 0,
					'total_local_tax2' => 0,
				];
			}
		}

		// Set fee lines
		$fee_lines = [];
		if (!empty($remote_data->fee_lines)) {
			foreach ($remote_data->fee_lines as $fee_line) {
				$line = [
					'label' => $fee_line->name,
					'total_ht' => $fee_line->total,
					'total_tva' => $fee_line->total_tax,
					'total_ttc' => ($fee_line->total + $fee_line->total_tax),
				];

				// Taxes
				$taxes = $this->getTaxesInfoFromRemoteData($fee_line->taxes);
				if ($taxes === false) return false;
				$line['tva_tx'] = $taxes['tva_tx'];
				$line['local_tax1_tx'] = $taxes['local_tax1_tx'];
				$line['local_tax2_tx'] = $taxes['local_tax2_tx'];
				$line['total_local_tax1'] = $taxes['total_local_tax1'];
				$line['total_local_tax2'] = $taxes['total_local_tax2'];

				$fee_lines[] = $line;
			}
		}
		// Manage fees in meta data (stripe payment, ...)
		if (!empty($remote_data->meta_data)) {
			foreach ($remote_data->meta_data as $meta) {
				if ($meta->key == '_stripe_fee') {
					$fee_lines[] = [
						'label' => 'Stripe',
						'qty' => 1,
						'price' => $meta->value,
						'total_ht' => $meta->value,
						'total_tva' => 0,
						'total_ttc' => $meta->value,
						'tva_tx' => 0,
						'local_tax1_tx' => 0,
						'local_tax2_tx' => 0,
						'total_local_tax1' => 0,
						'total_local_tax2' => 0,
					];
					break;
				}
			}
		}

		$create_date = $this->getDateTimeFromGMTDateTime($remote_data->date_created_gmt);
		$last_update = $this->getDateTimeFromGMTDateTime(!empty($remote_data->date_modified_gmt) ? $remote_data->date_modified_gmt : $remote_data->date_created_gmt);

		// Set billing's address
		$bContact = $remote_data->billing;
		$firstname = $bContact->first_name;
		$lastname = $bContact->last_name;
		if (!empty($conf->global->ECOMMERCENG_UPPERCASE_LASTNAME)) {
			$firstname = dol_ucwords(dol_strtolower($firstname));
			$lastname = dol_strtoupper($lastname);
		}
		if (!empty($firstname) && empty($lastname)) {
			$lastname = $langs->transnoentitiesnoconv("ECommerceLastNameNotInformed");
		} elseif (empty($firstname) && empty($lastname)) {
			$lastname = $langs->transnoentitiesnoconv('ECommerceFirstNameLastNameNotInformed');
		}
		$contactBilling = [
			'remote_id' => "",
			'type' => 1, //eCommerceSocpeople::CONTACT_TYPE_ORDER,
			'last_update' => $last_update->format('Y-m-d H:i:s'),
			'company' => $bContact->company,
			'firstname' => $firstname,
			'lastname' => $lastname,
			'address' => $bContact->address_1 . (!empty($bContact->address_1) && !empty($bContact->address_2) ? "\n" : "") . $bContact->address_2,
			'zip' => $bContact->postcode,
			'town' => $bContact->city,
			'country_id' => getCountry($bContact->country, 3),
			'email' => $bContact->email,
			'phone' => $bContact->phone,
			'fax' => null,
		];

		// Set invoice's address
		$contactInvoice = $contactBilling;
		$contactInvoice['type'] = 1; //eCommerceSocpeople::CONTACT_TYPE_INVOICE;

		// Set shipping's address
		$sContact = $remote_data->shipping;
		if (!empty($sContact->address_1) || !empty($sContact->address_2) ||
			!empty($sContact->postcode) || !empty($sContact->city) ||
			!empty($sContact->country)
		) {
			if ($bContact->first_name != $sContact->first_name || $bContact->last_name != $sContact->last_name ||
				$bContact->address_1 != $sContact->address_1 || $bContact->address_2 != $sContact->address_2 ||
				$bContact->postcode != $sContact->postcode || $bContact->city != $sContact->city ||
				$bContact->country != $sContact->country
			) {
				$firstname = $sContact->first_name;
				$lastname = $sContact->last_name;
				if (!empty($conf->global->ECOMMERCENG_UPPERCASE_LASTNAME)) {
					$firstname = dol_ucwords(dol_strtolower($firstname));
					$lastname = dol_strtoupper($lastname);
				}
				if (!empty($firstname) && empty($lastname)) {
					$lastname = $langs->transnoentitiesnoconv("ECommerceLastNameNotInformed");
				} elseif (empty($firstname) && empty($lastname)) {
					$lastname = $langs->transnoentitiesnoconv('ECommerceFirstNameLastNameNotInformed');
				}
				$contactShipping = [
					'remote_id' => "",
					'type' => 1, //eCommerceSocpeople::CONTACT_TYPE_DELIVERY,
					'last_update' => $last_update->format('Y-m-d H:i:s'),
					'company' => $sContact->company,
					'firstname' => $firstname,
					'lastname' => $lastname,
					'address' => $sContact->address_1 . (!empty($sContact->address_1) && !empty($sContact->address_2) ? "\n" : "") . $sContact->address_2,
					'zip' => $sContact->postcode,
					'town' => $sContact->city,
					'country_id' => getCountry($sContact->country, 3),
					'email' => null,
					'phone' => null,
					'fax' => null,
				];

				if (empty($sContact->company)) {
					if (!empty($firstname) && !empty($lastname)) {
						$name = dolGetFirstLastname($firstname, $lastname);
					} elseif (!empty($firstname)) {
						$name = dolGetFirstLastname($firstname, $langs->transnoentitiesnoconv("ECommerceLastNameNotInformed"));
					} else {
						$name = $langs->transnoentitiesnoconv('ECommerceFirstNameLastNameNotInformed');
					}
					$contactShipping['company_name'] = $name;
				} else {
					$contactShipping['company_name'] = $sContact->company;
				}
			} else {
				$contactShipping = $contactBilling;
				$contactShipping['type'] = 1; //eCommerceSocpeople::CONTACT_TYPE_DELIVERY;
			}
		} else {
			$contactShipping = $contactBilling;
			$contactShipping['type'] = 1; //eCommerceSocpeople::CONTACT_TYPE_DELIVERY;
		}

		// Set status of order
		// $remote_data->status is: 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed', 'trash'
		$status = '';
		if (isset($this->site->parameters['order_status_etod'][$remote_data->status]))
			$status = substr($this->site->parameters['order_status_etod'][$remote_data->status]['selected'], 1);
		if ($status == '') {
			dol_syslog(__METHOD__ . ": Status \"{$remote_data->status}\" was not found for remote order ID {$remote_data->id} and set in draft", LOG_ERR);
//			$status = Commande::STATUS_DRAFT;   // draft by default
			$this->errors[] = $langs->trans('ECommerceWooCommerceErrorOrderStatusNotConfigured', $remote_data->status, $this->site->name);
			return false;
		}

		// Set dolibarr billed status (payed or not)
		$billed = -1;   // unknown
		if (isset($this->site->parameters['order_status_etod'][$remote_data->status]))
			$billed = $this->site->parameters['order_status_etod'][$remote_data->status]['billed'];
		// Note: with processing, billed can be 0 or 1, so we keep -1

		// Dont synchro order
		$synchronize = 1;
		if (isset($this->site->parameters['order_status_etod'][$remote_data->status]))
			$synchronize = $this->site->parameters['order_status_etod'][$remote_data->status]['synchronize'];

		$orderStatus = '';
		require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
		$efields = new ExtraFields($this->db);
		$efields->fetch_name_optionals_label('commande', true);
		if (isset($efields->attribute_param["ecommerceng_wc_status_{$this->site->id}_{$conf->entity}"]['options']) &&
			is_array($efields->attribute_param["ecommerceng_wc_status_{$this->site->id}_{$conf->entity}"]['options'])) {
			foreach ($efields->attribute_param["ecommerceng_wc_status_{$this->site->id}_{$conf->entity}"]['options'] as $key => $value) {
				$key_test = ($pos = strpos($key, '_')) > 0 ? substr($key, $pos + 1) : $key;
				if ($key_test == $remote_data->status) {
					$orderStatus = $key;
					break;
				}
			}
		}

		// Add order content to array or orders
		$order = [
			'create_date' => $create_date->getTimestamp(),
			'last_update' => $last_update->format('Y-m-d H:i:s'),
			'remote_id' => $remote_data->id,
			'remote_increment_id' => $remote_data->id,
			'remote_id_societe' => $remote_data->customer_id,
			'ref_client' => $remote_data->id,
			'date_commande' => $remote_data->date_created,
			'date_livraison' => $remote_data->date_completed,
			'total_ht' => $remote_data->total - $remote_data->total_tax,
			'total_tva' => $remote_data->total_tax,
			'total_ttc' => $remote_data->total,
			'items' => $items,
			'note' => $this->replace4byte($remote_data->customer_note),
			'socpeopleCommande' => $contactBilling,
			'socpeopleFacture' => $contactInvoice,
			'socpeopleLivraison' => $contactShipping,
			'status' => $status,                         // dolibarr status
			'billed' => $billed,
			'synchronize' => !empty($synchronize),
			'remote_state' => $remote_data->status,        // remote state, for information only (less accurate than status)
			'remote_status' => $remote_data->status,      // remote status, for information only (more accurate than state)
			'remote_order' => $remote_data,
			'payment_method' => $remote_data->payment_method_title,
			'payment_method_id' => $remote_data->payment_method,
			'payment_amount_ttc' => $remote_data->total,
			'fee_lines' => $fee_lines,
			'extrafields' => [
				"ecommerceng_online_payment_{$conf->entity}" => empty($remote_data->date_paid) ? 0 : 1,
				"ecommerceng_wc_status_{$this->site->id}_{$conf->entity}" => $orderStatus,
				"ecommerceng_wc_link_{$this->site->id}_{$conf->entity}" => rtrim($this->site->webservice_address, '/') . '/wp-admin/post.php?action=edit&post=' . $remote_data->id,
			],
		];

		// Synch extrafields <=> metadatas
		if (!empty($remote_data->meta_data) && !empty($this->site->parameters['ef_crp']['commande'])) {
			$correspondences = array();
			foreach ($this->site->parameters['ef_crp']['commande'] as $key => $options_saved) {
				if ($options_saved['activated'] && !empty($options_saved['correspondences'])) {
					$correspondences[$options_saved['correspondences']] = $key;
				}
			}
			foreach ($remote_data->meta_data as $meta) {
				if (isset($correspondences[$meta->key])) {
					$extrafield_value = $meta->value;
					$extrafield_key = $correspondences[$meta->key];
					// Specific Altairis - Begin
					if (!empty($extrafield_value) && ($extrafield_key == 'rental_start' || $extrafield_key == 'rental_end')) {
						$extrafield_value = strtotime($extrafield_value);
					}
					// Specific Altairis - End
					$order['extrafields'][$extrafield_key] = $extrafield_value;
				}
			}
		}

		// Manage payment (stripe payment, ...)
		if (!empty($remote_data->meta_data)) {
			foreach ($remote_data->meta_data as $meta) {
				if ($meta->key == '_payplug_metadata') {
					$order['payment_amount_ttc'] = $meta->value->amount / 100;
					break;
				}
			}
		}

		// Specific Altairis - Begin
		$order['extrafields']['rental_doc'] = 1;
		// Specific Altairis - End

		return $order;
	}

    /**
     * Desactivated because is not supported by woocommerce.
     *
     * @param   array   $remoteObject   List of id of remote orders to convert
     * @param   int     $toNb           Max nb
     * @return  array                   Empty list
     */
    public function convertRemoteObjectIntoDolibarrFacture($remoteObject, $toNb=0)
    {
        dol_syslog(__METHOD__ . ": Desactivated for site ID {$this->site->id}", LOG_DEBUG);
		$this->errors = array();
		return [];
    }

    /**
     * Get remote category tree
     *
     * @return  array|boolean       Array with categories or false if error
     */
    public function getRemoteCategoryTree()
    {
        dol_syslog(__METHOD__ . ": Get remote category tree for site ID {$this->site->id}", LOG_DEBUG);
        global $conf, $langs;

		$this->errors = array();
		$categories = [];
        $idxPage = 1;
        $per_page = empty($conf->global->ECOMMERCENG_MAXSIZE_MULTICALL) ? 100 : min($conf->global->ECOMMERCENG_MAXSIZE_MULTICALL, 100);

        while (true) {
        	$filters = [
				'page' => $idxPage++,
				'per_page' => $per_page,
			];
			$stopwatch_id = eCommerceUtils::startStopwatch(__METHOD__ . " - GET products/categories (filters: " . json_encode($filters). ")");
            try {
                $results = $this->client->get('products/categories', $filters);
				eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
            } catch (HttpClientException $fault) {
				eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
                $this->errors[] = $langs->trans('ECommerceWoocommerceGetRemoteCategoryTree', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
                dol_syslog(__METHOD__ .
                    ': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceGetRemoteCategoryTree', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
                    ' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
                return false;
            }
            if (count($results) == 0) break;

            foreach ($results as $category) {
                $categories[$category->id] = [
                    'category_id' => $category->id,  // id category
                    'parent_id' => $category->parent,
                    'label' => $category->name,
                    'name' => $category->name,
                    'description' => $category->description,
                    'updated_at' => '',
                ];
            }
        }

        // Set tree
        foreach ($categories as $category) {
            $parent_id = $category['parent_id'];

            if (!empty($parent_id)) {
                if (!isset($categories[$parent_id]['children'])) {
                    $categories[$parent_id]['level'] = 0;
                    $categories[$parent_id]['children'] = [];
                }

                $categories[$parent_id]['children'][] = &$categories[$category['category_id']];
            }
        }

        // Make tree
        $categories_tree = ['level' => 0, 'children' => []];
        foreach ($categories as $category) {
            if (empty($category['parent_id'])) {
                $categories_tree['children'][] = $category;
            }
        }

        dol_syslog(__METHOD__ . ": end, " . count($categories) . " remote category recovered", LOG_DEBUG);
        return $categories_tree;
    }

    /**
     * Desactivated because is not supported by woocommerce.
     *
     * @param   int     $remote_company_id  Id of company
     *
     * @return  array                       Array with address id
     */
    public function getRemoteAddressIdForSociete($remote_company_id)
    {
        dol_syslog(__METHOD__ . ": Desactivated for site ID {$this->site->id}", LOG_DEBUG);
		$this->errors = array();
		return [$remote_company_id];
    }

    /**
     * Return content of one category
     *
     * @param   int             $category_id    Remote category id
     *
     * @return  array|boolean                   Return category data
     */
    public function getCategoryData($category_id)
    {
        dol_syslog(__METHOD__ . ": Get remote category for site ID {$this->site->id}", LOG_DEBUG);
        global $langs;

		$this->errors = array();
		$category = [];

		$stopwatch_id = eCommerceUtils::startStopwatch(__METHOD__ . " - GET products/categories/{$category_id}");
        try {
            $result = $this->client->get('products/categories/' . $category_id);
			eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
        } catch (HttpClientException $fault) {
			eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
            $this->errors[] = $langs->trans('ECommerceWoocommerceGetCategoryData', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
            dol_syslog(__METHOD__ .
                ': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceGetCategoryData', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
                ' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
            return false;
        }

        if (isset($result)) {
            $category = [
                'category_id' => $result->id,  // id category
                'parent_id' => $result->parent,
                'label' => $result->name,
                'name' => $result->name,
                'description' => $result->description,
                'updated_at' => '',
            ];
        }

        dol_syslog(__METHOD__ . ": end", LOG_DEBUG);
        return $category;
    }

    /**
     * Return content of one order
     *
     * @param   int     $remoteOrderId  Remote order id
     *
     * @return  array                   Empty
     */
    public function getRemoteCommande($remoteOrderId)
    {
        dol_syslog(__METHOD__ . ": Desactivated for site ID {$this->site->id}", LOG_DEBUG);
		$this->errors = array();
		return [];
    }

    /**
     * Conevrt Object to remote product data
     *
     * @param   int     $remote_id  Id of product on remote ecommerce
     * @param   Product $object     Product object
     *
     * @return  array             	Remote product data
     */
    public function convertObjectIntoProductData($remote_id, $object)
    {
        dol_syslog(__METHOD__ . ": Update the remote product ID $remote_id for Dolibarr product ID {$object->id} for site ID {$this->site->id}", LOG_DEBUG);
        global $conf, $langs, $user;

		$this->errors = array();
		$return_data = array();

		$initial_data = empty($remote_id) || !empty($conf->global->ECOMMERCE_INITIAL_DATA_PRODUCT);

		$isProductVariation = false;
		$isProductVariationHasOne = false;
        $remote_product_id = $remote_id;
		$remote_product_variation_ids = array();
        if (preg_match('/^(\d+)\|(\d+)$/', $remote_id, $idsProduct) == 1) { // Variations
            $isProductVariation = true;
            $remote_product_id = $idsProduct[1];
			$remote_product_variation_ids[] = $idsProduct[2];
        }

        $productSynchPrice = isset($this->site->parameters['product_synch_price']) ? $this->site->parameters['product_synch_price'] : 'regular';
        $productImageSynchDirection = isset($this->site->parameters['product_synch_direction']['image']) ? $this->site->parameters['product_synch_direction']['image'] : '';
        $productRefSynchDirection = isset($this->site->parameters['product_synch_direction']['ref']) ? $this->site->parameters['product_synch_direction']['ref'] : '';
        $productDescriptionSynchDirection = isset($this->site->parameters['product_synch_direction']['description']) ? $this->site->parameters['product_synch_direction']['description'] : '';
        $productShortDescriptionSynchDirection = isset($this->site->parameters['product_synch_direction']['short_description']) ? $this->site->parameters['product_synch_direction']['short_description'] : '';
        $productWeightSynchDirection = isset($this->site->parameters['product_synch_direction']['weight']) ? $this->site->parameters['product_synch_direction']['weight'] : '';
        $productTaxSynchDirection = isset($this->site->parameters['product_synch_direction']['tax']) ? $this->site->parameters['product_synch_direction']['tax'] : '';
        $productStatusSynchDirection = isset($this->site->parameters['product_synch_direction']['status']) ? $this->site->parameters['product_synch_direction']['status'] : '';
		$product_variation_mode_all_to_one = !empty($this->site->parameters['product_variation_mode']) && $this->site->parameters['product_variation_mode'] == 'all_to_one';

		if ($product_variation_mode_all_to_one) {
			$idsProduct = explode('|', $remote_id);
			if (count($idsProduct) > 1) {
				$isProductVariationHasOne = true;
				$remote_product_id = $idsProduct[0];
				$remote_product_variation_ids = array_splice($idsProduct, 1);
			}
		}

        // Convert Weight
		$from_unit = $object->weight_units;
		$to_unit = isset($this->site->parameters['product_weight_units']) ? $this->site->parameters['product_weight_units'] : (empty($conf->global->MAIN_WEIGHT_DEFAULT_UNIT)?0:$conf->global->MAIN_WEIGHT_DEFAULT_UNIT);
		$totalWeight = weight_convert($object->weight, $from_unit, $to_unit);

        // Price
        $error_price = 0;
        if (!empty($conf->global->PRODUIT_MULTIPRICES)) {
            $price_level = !empty($this->site->price_level) ? $this->site->price_level : 1;
            if ($this->site->ecommerce_price_type == 'TTC') {
                if ($object->multiprices_base_type[$price_level] == 'TTC' || $object->multiprices_ttc[$price_level] == 0) {
                    $price = $object->multiprices_ttc[$price_level];
                } else {
                    $error_price++;
                }
            } else {
                if ($object->multiprices_base_type[$price_level] == 'TTC' && $object->multiprices[$price_level] != 0) {
                    $error_price++;
                } else {
                    $price = $object->multiprices[$price_level];
                }
            }
        } else {
            if ($this->site->ecommerce_price_type == 'TTC') {
                if ($object->price_base_type == 'TTC' || $object->price_ttc == 0) {
                    $price = $object->price_ttc;
                } else {
                    $error_price++;
                }
            } else {
                if ($object->price_base_type == 'TTC' && $object->price != 0) {
                    $error_price++;
                } else {
                    $price = $object->price;
                }
            }
        }
        if ($error_price) {
            $error_msg = $langs->trans('ECommerceWoocommerceErrorBaseTypeOfProductWithSiteParameter', $object->ref, $this->site->ecommerce_price_type, $this->site->name);
            $this->errors[] = $error_msg;
            dol_syslog(__METHOD__ . ': Error:' . $error_msg, LOG_ERR);
            return $return_data;
        }
        $regular_price = $object->array_options["options_ecommerceng_wc_regular_price_{$this->site->id}_{$conf->entity}"];

        $sale_price = price2num($object->array_options["options_ecommerceng_wc_sale_price_{$this->site->id}_{$conf->entity}"]);
        if (!empty($sale_price)) {
			$date_on_sale_from = $object->array_options["options_ecommerceng_wc_date_on_sale_from_{$this->site->id}_{$conf->entity}"];
			if (!empty($date_on_sale_from)) {
				$date_on_sale_from = $this->getDateTimeToGMTDateTime(is_numeric($date_on_sale_from) ? dol_print_date($date_on_sale_from, 'dayhourrfc') : $date_on_sale_from);
				$date_on_sale_from = isset($date_on_sale_from) ? dol_print_date($date_on_sale_from->getTimestamp(), 'dayhourrfc') : '';
			} else {
				$date_on_sale_from = '';
			}
			$date_on_sale_to = $object->array_options["options_ecommerceng_wc_date_on_sale_to_{$this->site->id}_{$conf->entity}"];
			if (!empty($date_on_sale_to)) {
				$date_on_sale_to = $this->getDateTimeToGMTDateTime(is_numeric($date_on_sale_to) ? dol_print_date($date_on_sale_to, 'dayhourrfc') : $date_on_sale_to);
				$date_on_sale_to = isset($date_on_sale_to) ? dol_print_date($date_on_sale_to->getTimestamp(), 'dayhourrfc') : '';
			} else {
				$date_on_sale_to = '';
			}
		} else {
			$sale_price = '';
			$date_on_sale_from = '';
			$date_on_sale_to = '';
		}

        // images
        $images = [];
        if ($productImageSynchDirection == 'dtoe' || $productImageSynchDirection == 'all') {
            // Get current images
            $current_images = [];
            if (!empty($remote_product_id)) {
				if ($isProductVariation) { // Variations
					foreach ($remote_product_variation_ids as $remote_product_variation_id) {
						$stopwatch_id = -1;
						try {
							$stopwatch_id = eCommerceUtils::startStopwatch(__METHOD__ . " - GET products/{$remote_product_id}/variations/{$remote_product_variation_id}");
							$results = $this->client->get("products/$remote_product_id/variations/$remote_product_variation_id");
							eCommerceUtils::stopAndLogStopwatch($stopwatch_id);

							if (!empty($results)) {
								if ($isProductVariation) {
									if (isset($results->image)) {
										$current_images[$results->image->name] = $results->image->id;
									}
								} else {
									if (is_array($results->images)) {
										foreach ($results->images as $image) {
											$current_images[$image->name] = $image->id;
										}
									}
								}
							}
						} catch (HttpClientException $fault) {
							eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
							$this->errors[] = $langs->trans('ECommerceWoocommerceUpdateRemoteProductGetRemoteProduct', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
							dol_syslog(__METHOD__ .
								': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceUpdateRemoteProductGetRemoteProduct', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
								' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
							return $return_data;
						}
					}
				} else {
					$stopwatch_id = -1;
					try {
						$stopwatch_id = eCommerceUtils::startStopwatch(__METHOD__ . " - GET products/{$remote_product_id}");
						$results = $this->client->get("products/$remote_product_id");
						eCommerceUtils::stopAndLogStopwatch($stopwatch_id);

						if (!empty($results)) {
							if ($isProductVariation) {
								if (isset($results->image)) {
									$current_images[$results->image->name] = $results->image->id;
								}
							} else {
								if (is_array($results->images)) {
									foreach ($results->images as $image) {
										$current_images[$image->name] = $image->id;
									}
								}
							}
						}
					} catch (HttpClientException $fault) {
						eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
						$this->errors[] = $langs->trans('ECommerceWoocommerceUpdateRemoteProductGetRemoteProduct', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
						dol_syslog(__METHOD__ .
							': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceUpdateRemoteProductGetRemoteProduct', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
							' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
						return $return_data;
					}
				}
			}

            // Product - Images properties
            $entity = isset($object->entity) ? $object->entity : $conf->entity;
            if (!empty($conf->global->PRODUCT_USE_OLD_PATH_FOR_PHOTO)) {    // For backward compatiblity, we scan also old dirs
                if ($object->type == Product::TYPE_PRODUCT) {
                    $dir = $conf->product->multidir_output[$entity] . '/' . substr(substr("000" . $object->id, -2), 1, 1) . '/' . substr(substr("000" . $object->id, -2), 0, 1) . '/' . $object->id . "/photos/";
                } else {
                    $dir = $conf->service->multidir_output[$entity] . '/' . substr(substr("000" . $object->id, -2), 1, 1) . '/' . substr(substr("000" . $object->id, -2), 0, 1) . '/' . $object->id . "/photos/";
                }
            } else {
            	if (version_compare(DOL_VERSION, "13.0.0") >= 0) {
					if ($object->type == Product::TYPE_PRODUCT) {
						$dir = $conf->product->multidir_output[$entity] . '/' . get_exdir(0, 0, 0, 1, $object, 'product') . '/';
					} else {
						$dir = $conf->service->multidir_output[$entity] . '/' . get_exdir(0, 0, 0, 1, $object, 'product') . '/';
					}
				} else {
					if ($object->type == Product::TYPE_PRODUCT) {
						$dir = $conf->product->multidir_output[$entity] . '/' . get_exdir(0, 0, 0, 0, $object, 'product') . dol_sanitizeFileName($object->ref) . '/';
					} else {
						$dir = $conf->service->multidir_output[$entity] . '/' . get_exdir(0, 0, 0, 0, $object, 'product') . dol_sanitizeFileName($object->ref) . '/';
					}
				}
            }
//            $photos = $object->liste_photos($dir);
//            foreach ($photos as $index => $photo) {
//                $img = [];
//
//                $filename = ecommerceng_wordpress_sanitize_file_name($photo['photo']);
//                if (!isset($current_images[$filename])) {
//                    $result = $this->worpressclient->postmedia("media", $dir . $photo['photo'], [
//                        'slug' => $object->id . '_' . $filename,
//                        'ping_status' => 'closed',
//                        'comment_status' => 'closed',
//                    ]);
//
//                    if ($result === null) {
//                        $this->errors[] = $langs->trans('ECommerceWoocommerceUpdateRemoteProductSendImage', $object->ref . ' - '  . $remote_id, $this->site->name, implode('; ', $this->worpressclient->errors));
//                        dol_syslog(__METHOD__ . ': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceUpdateRemoteProductSendImage',
//                                $remote_id, $this->site->name, implode('; ', $this->worpressclient->errors)), LOG_ERR);
//                        return $return_data;
//                    } elseif (!empty($result['message'])) {
//                        $this->errors[] = $langs->trans('ECommerceWoocommerceUpdateRemoteProductSendImage', $object->ref . ' - '  . $remote_id, $this->site->name, $result['code'] . ' - ' . $result['message']);
//                        dol_syslog(__METHOD__ . ': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceUpdateRemoteProductSendImage',
//                                $remote_id, $this->site->name, $result['code'] . ' - ' . $result['message']), LOG_ERR);
//                        return $return_data;
//                    }
//
//                    $img['id'] = $result['id'];
//                } else {
//                    $img['id'] = $current_images[$filename];
//                }
//
//                $img['name'] = $filename;
//                $img['position'] = $index;
//                $images[] = $img;
//
//                if ($isProductVariation) { // Get only one image for variation
//                    break;
//                }
//            }

//          Don't work because of the character ?
//			https://github.com/woocommerce/woocommerce/issues/24484
//
			// Defined relative dir to DOL_DATA_ROOT
			$relativedir = '';
			if ($dir) {
				$relativedir = preg_replace('/^'.preg_quote(DOL_DATA_ROOT, '/').'/', '', $dir);
				$relativedir = preg_replace('/^[\\/]/', '', $relativedir);
			}

			// Build file list
			$filearray = dol_dir_list($dir, "files", 0, '', '(\.meta|_preview.*\.png)$', 'position_name', SORT_ASC, 1);
			if (!empty($conf->global->PRODUCT_USE_OLD_PATH_FOR_PHOTO)) {    // For backward compatiblity, we scan also old dirs
				$filearrayold = dol_dir_list($dir, "files", 0, '', '(\.meta|_preview.*\.png)$', 'position_name', SORT_ASC, 1);
				$filearray = array_merge($filearray, $filearrayold);
			}

			// Get list of files stored into database for same relative directory
			$relativedir = trim($relativedir, '/');
			if ($relativedir) {
				completeFileArrayWithDatabaseInfo($filearray, $relativedir);
				$filearray = dol_sort_array($filearray, 'position_name', 'ASC');
			}

			$url_root = dol_buildpath('/ecommerceng/document', 2);

			foreach ($filearray as $key => $file) {
				if (empty($file['share'])) continue;

				$filename = ecommerceng_wordpress_sanitize_file_name($file['name']);

				$img = [
					'name' => $filename,
					'src' => $url_root . '/' . $file['share']/* . '.jpg'*/,
					'position' => $key,
				];
				if (isset($current_images[$filename])) {
					$img['id'] = $current_images[$filename];
				}
				$images[] = $img;

				if ($isProductVariation) { // Get only one image for variation
					break;
				}
			}
        }

        // Product - Meta data properties
        $object->fetch_optionals();


		// Variations
        if ($isProductVariation || $isProductVariationHasOne) {
            /*
            // Product variation - Downloads properties
            $downloads = [
                [
                    'name' => '',       // string     File name.
                    'file' => '',       // string     File URL.
                ],
            ];

            // Product variation - Dimensions properties
            $dimensions = [
                'length'    => '',      // string   Product length (cm).
                'width'     => '',      // string   Product width (cm).
                'height'    => '',      // string   Product height (cm).
            ];

            // Product variation - Image properties
            $images = [
                [
                    'id'        => 0,       // integer	Image ID. Not required
                    'src'       => '',      // string	Image URL.
                    'name'      => '',      // string	Image name.
                    'alt'       => '',      // string	Image alternative text.
                    'position'  => 0,       // integer	Image position. 0 means that the image is featured.
                ],
            ];

            // Product variation - Attributes properties
            $attributes = [
                [
                    'id'        => 0,       // integer  Attribute ID.
                    'name'      => '',      // string   Attribute name.
                    'option'    => '',      // string   Selected attribute term name.
                ],
            ];

            // Product variation - Meta data properties
            $meta_data = [
                'key'   => '',  // string	Meta key.
                'value' => '',  // string	Meta value.
            ];
            */

            $variationData = [
                //'description' => nl2br($object->array_options["options_ecommerceng_description_{$conf->entity}"]),                    // string       Variation description.
                //'sku' => $object->ref,                                  // string       Unique identifier.
                //'regular_price' => $productSynchPrice == 'regular' ? $price : $regular_price,                              // string       Variation regular price.
                //'sale_price' => $sale_price,                                     // string       Variation sale price.
                //'date_on_sale_from' => '',                              // date-time    Start date of sale price, in the site’s timezone.
                'date_on_sale_from_gmt' => $date_on_sale_from,                          // date-time    Start date of sale price, as GMT.
                //'date_on_sale_to' => '',                                // date-time    End date of sale price, in the site’s timezone.
                'date_on_sale_to_gmt' => $date_on_sale_to,                            // date-time    End date of sale price, in the site’s timezone.
                //'visible' => '',                                        // boolean      Define if the attribute is visible on the “Additional information” tab in the product’s page. Default is true.
                //'virtual' => $object->type == Product::TYPE_SERVICE,    // boolean      If the variation is virtual. Default is false.
                //'downloadable' => '',                                   // boolean      If the variation is downloadable. Default is false.
                //'downloads' => $downloads,                              // array        List of downloadable files. See Product variation - Downloads properties
                //'download_limit' => '',                                 // integer      Number of times downloadable files can be downloaded after purchase. Default is -1.
                //'download_expiry' => '',                                // integer      Number of days until access to downloadable files expires. Default is -1.
                //'tax_status' => 'none',                                 // string       Tax status. Options: taxable, shipping and none. Default is taxable.
                //'tax_class' => '',                                      // string       Tax class.
                //'manage_stock' => $initial_data || !empty($object->array_options["options_ecommerceng_wc_manage_stock_{$this->site->id}_{$conf->entity}"]),                                   // boolean      Stock management at variation level. Default is false.
                //'stock_quantity' => '',                                 // integer      Stock quantity.
                //'in_stock' => '',                                       // boolean      Controls whether or not the variation is listed as “in stock” or “out of stock” on the frontend. Default is true.
                //'backorders' => '',                                     // string       If managing stock, this controls if backorders are allowed. Options: no, notify and yes. Default is no.
                //'weight' => (!empty($totalWeight) ? $totalWeight : ''),                               // string       Variation weight (kg).
                //'dimensions' => $dimensions,                            // object       Variation dimensions. See Product variation - Dimensions properties
                //'shipping_class' => '',                                 // string       Shipping class slug.
                //'image' => (!empty($images) ? $images[0] : ''),                                     // object       Variation image data. See Product variation - Image properties
                //'attributes' => $attributes,                            // array        List of attributes. See Product variation - Attributes properties
                //'menu_order' => '',                                     // integer      Menu order, used to custom sort products.
                //'meta_data' => $meta_data,                              // array        Meta data. See Product variation - Meta data properties
            ];

			if (is_array($object->context['ec_price_entities']) && in_array($this->site->entity, $object->context['ec_price_entities'])) {
				$variationData['regular_price'] = $productSynchPrice == 'regular' || $initial_data ? $price : $regular_price;
				$variationData['sale_price'] = $sale_price;
				$object->array_options["options_ecommerceng_wc_regular_price_{$this->site->id}_{$conf->entity}"] = $variationData['regular_price'];
			}
            if ($productImageSynchDirection == 'dtoe' || $productImageSynchDirection == 'all') {
                if (!empty($images)) {
                    $variationData['image'] = $images[0];
                }
            }
            if ($productRefSynchDirection == 'dtoe' || $productRefSynchDirection == 'all') {
                $variationData['sku'] = $object->ref;
            }
            if ($productDescriptionSynchDirection == 'dtoe' || $productDescriptionSynchDirection == 'all') {
                $variationData['description'] = nl2br($object->array_options["options_ecommerceng_description_{$conf->entity}"]);

				if (empty($variationData['description']) || $initial_data) {
					$variationData['description'] = nl2br($object->description);
					$object->array_options["options_ecommerceng_description_{$conf->entity}"] = $variationData['description'];
				}
			}
            if ($productWeightSynchDirection == 'dtoe' || $productWeightSynchDirection == 'all') {
                $variationData['weight'] = (!empty($totalWeight) ? $totalWeight : '');
            }
            if ($productTaxSynchDirection == 'dtoe' || $productTaxSynchDirection == 'all') {
                $variationData['tax_status'] = 'none';

                // Set tax
                if (!$initial_data && !empty($object->array_options["options_ecommerceng_tax_class_{$this->site->id}_{$conf->entity}"])) {
                    $variationData['tax_status'] = 'taxable';
                    $variationData['tax_class'] = $object->array_options["options_ecommerceng_tax_class_{$this->site->id}_{$conf->entity}"];
				} elseif ($initial_data && $object->tva_tx > 0) {
					$tax_class = $this->getTaxClassFromVatRate($object->tva_tx);
					if (!empty($tax_class)) {
						$variationData['tax_status'] = 'taxable';
						$variationData['tax_class'] = $tax_class;
						$object->array_options["options_ecommerceng_tax_class_{$this->site->id}_{$conf->entity}"] = $tax_class;
					} else {
						$this->errors[] = $langs->trans('ECommerceWoocommerceErrorTaxClassNotFoundFromTaxRate', $object->tva_tx, $this->site->name, $object->ref . ' - '  . $remote_id);
						return $return_data;
					}
				}
            }
			if ($this->site->stock_sync_direction == 'dolibarr2ecommerce') {
				$variationData['manage_stock'] = !empty($object->array_options["options_ecommerceng_wc_manage_stock_{$this->site->id}_{$conf->entity}"]);
				$object->array_options["options_ecommerceng_wc_manage_stock_{$this->site->id}_{$conf->entity}"] = $variationData['manage_stock'] ? 1 : 0;

				if ($variationData['manage_stock'] && empty($object->array_options["options_ecommerceng_wc_dont_update_stock_{$this->site->id}_{$conf->entity}"])) {
					$supported_warehouses = is_array($this->site->parameters['fk_warehouse_to_ecommerce']) ? $this->site->parameters['fk_warehouse_to_ecommerce'] : array();
					$object->load_stock();

					$stock = 0;
					foreach ($supported_warehouses as $warehouse_id) {
						$stock += isset($object->stock_warehouse[$warehouse_id]->real) ? $object->stock_warehouse[$warehouse_id]->real : 0;
					}
					$stock = floor($stock);

					$variationData['stock_quantity'] = $stock;
					$variationData['in_stock'] = $stock > 0;
				}
			}

			// Synch extrafields <=> metadatas and attributes
			if (!empty($object->array_options)) {
				foreach ($object->array_options as $key => $value) {
					$cr_key = substr($key, 8);
					if (preg_match('/^ecommerceng_/', $cr_key)) continue;

					// Synch extrafields <=> attributes
					$options_saved = $this->site->parameters['ef_crp_attribute'][$cr_key];
					if ($options_saved['activated']) {
						$rm_key = $cr_key;
						if (isset($options_saved['correspondences'])) $rm_key = $options_saved['correspondences'];
						$variationData['attributes'][] = array('name' => $rm_key, 'options' => explode(',', $value));
					}

					// Synch extrafields <=> metadatas
					$options_saved = $this->site->parameters['ef_crp']['product'][$cr_key];
					if ($options_saved['activated']) {
						$rm_key = $cr_key;
						if (isset($options_saved['correspondences'])) $rm_key = $options_saved['correspondences'];
						$variationData['meta_data'][] = array('key' => $rm_key, 'value' => $value);
					}
				}
			}

            // Product
            // 'name'    => $object->label,			                    // string		Product name.
            // 'status'  => $object->status ? 'publish' : 'pending',	// string		Product status (post status). Options: draft, pending, private and publish. Default is publish.

			foreach ($remote_product_variation_ids as $remote_product_variation_id) {
				$return_data['variations'][$remote_product_variation_id] = array(
					'parent_remote_id' => $remote_product_id,
					'remote_id' => $remote_product_variation_id,
					'data' => $variationData,
				);
			}
        }
		// Product
        if (!$isProductVariation || $isProductVariationHasOne) {
            /*
            // Product - Downloads properties
            $downloads = [
                [
                    'name' => '',       // string     File name.
                    'file' => '',       // string     File URL.
                ],
            ];

            // Product - Dimensions properties
            $dimensions = [
                'length' => '',     // string   Product length (cm).
                'width' => '',      // string   Product width (cm).
                'height' => '',     // string   Product height (cm).
            ];

            // Product - Categories properties
            $categories = [
                [
                    'id' => 0,      // integer  Category ID.
                ],
            ];

            // Product - Tags properties
            $tags = [
                [
                    'id' => 0,      // integer  Tag ID.
                ],
            ];

            // Product - Images properties
            $images = [
                [
                    'id' => 0,              // integer	Image ID. Not required
                    'src' => '',            // string	Image URL.
                    'name' => '',           // string	Image name.
                    'alt' => '',            // string	Image alternative text.
                    'position' => 0,        // integer	Image position. 0 means that the image is featured.
                ],
            ];

            // Product - Attributes properties
            $attributes = [
                [
                    'id' => 0,              // integer	Attribute ID. Not required
                    'name' => '',           // string	Attribute name.
                    'position' => 0,        // integer	Attribute position.
                    'visible' => false,     // boolean	Define if the attribute is visible on the “Additional information” tab in the product’s page. Default is false.
                    'variation' => false,   // boolean	Define if the attribute can be used as variation. Default is false.
                    'options' => [],        // array	List of available term names of the attribute.
                ],
            ];

            // Product - Default attributes properties
            $default_attributes = [
                'id' => 0,              // integer	Attribute ID. Not required
                'name' => '',           // string	Attribute name.
                'option' => '',         // string	Selected attribute term name.
            ];

            // Product - Meta data properties
            $meta_data = [
                'key' => '', // string	Meta key.
                'value' => '', // string	Meta value.
            ];
            */

            // Get categories
            $eCommerceCategory = new eCommerceCategory($this->db);
            $cat = new Categorie($this->db);
            $categories_list = $cat->containing($object->id, 'product');
            $categories = [];
            foreach ($categories_list as $category) {
                if ($this->site->fk_cat_product != $category->id) {
                    $ret = $eCommerceCategory->fetchByFKCategory($category->id, $this->site->id);
                    if ($ret > 0) {
                        $categories[] = ['id' => $eCommerceCategory->remote_id];
                    }
                }
            }

            $status = $object->array_options["options_ecommerceng_wc_status_{$this->site->id}_{$conf->entity}"];

            $productData = [
                'name' => $object->label,                            // string		Product name.
                //'slug'                  => '',			                            // string		Product slug.
                //'type'                  => '',			                            // string		Product type. Options: simple, grouped, external and variable. Default is simple.
                //'status' => (!empty($status) ? $status : ''), //$object->status ? 'publish' : 'pending',	// string		Product status (post status). Options: draft, pending, private and publish. Default is publish.
                //'featured'              => false,		                            // boolean		Featured product. Default is false.
                //'catalog_visibility'    => '',                                      // string		Catalog visibility. Options: visible, catalog, search and hidden. Default is visible.
                //'description' => nl2br($object->array_options["options_ecommerceng_description_{$conf->entity}"]),                    // string		Product description.
                //'short_description' => nl2br($object->array_options["options_ecommerceng_short_description_{$conf->entity}"]),                                      // string		Product short description.
                //'sku' => $object->ref,                            // string		Unique identifier.
                //'regular_price' => $productSynchPrice == 'regular' ? $price : $regular_price,                          // string		Product regular price.
                //'sale_price'            => $sale_price,                                      // string		Product sale price.
                //'date_on_sale_from'     => '',                                      // date-time	Start date of sale price, in the site’s timezone.
                'date_on_sale_from_gmt' => $date_on_sale_from,                                      // date-time	Start date of sale price, as GMT.
                //'date_on_sale_to'       => '',                                      // date-time	End date of sale price, in the site’s timezone.
                'date_on_sale_to_gmt'   => $date_on_sale_to,                                      // date-time	End date of sale price, in the site’s timezone.
                'virtual'               => $object->type == Product::TYPE_SERVICE,  // boolean		If the product is virtual. Default is false.
                //'downloadable'          => false,                                   // boolean		If the product is downloadable. Default is false.
                //'downloads'             => $downloads,                              // array		List of downloadable files. See Product - Downloads properties
                //'download_limit'        => -1,                                      // integer		Number of times downloadable files can be downloaded after purchase. Default is -1.
                //'download_expiry'       => -1,                                      // integer		Number of days until access to downloadable files expires. Default is -1.
                //'external_url'          => '',                                      // string		Product external URL. Only for external products.
                //'button_text'           => '',                                      // string		Product external button text. Only for external products.
                //'tax_status' => 'none',                                  // string		Tax status. Options: taxable, shipping and none. Default is taxable.
                //'tax_class'             => '',                                      // string		Tax class.
                //'manage_stock'          => $initial_data || !empty($object->array_options["options_ecommerceng_wc_manage_stock_{$this->site->id}_{$conf->entity}"]),                                   // boolean		Stock management at product level. Default is false.
                //'stock_quantity'        => $object->stock_reel,                     // integer		Stock quantity.
                //'in_stock'              => $object->stock_reel > 0,                 // boolean		Controls whether or not the product is listed as “in stock” or “out of stock” on the frontend. Default is true.
                //'backorders'            => '',                                      // string		If managing stock, this controls if backorders are allowed. Options: no, notify and yes. Default is no.
                //'sold_individually'     => false,                                   // boolean		Allow one item to be bought in a single order. Default is false.
                //'weight' => (!empty($totalWeight) ? $totalWeight : ''),                            // string		Product weight (kg).
                //'dimensions'            => $dimensions,                             // object		Product dimensions. See Product - Dimensions properties
                //'shipping_class'        => '',                                      // string		Shipping class slug.
                //'reviews_allowed'       => true,                                    // boolean		Allow reviews. Default is true.
                //'upsell_ids'            => [],                                      // array		List of up-sell products IDs.
                //'cross_sell_ids'        => [],                                      // array		List of cross-sell products IDs.
                //'parent_id'             => 0,                                       // integer		Product parent ID.
                //'purchase_note'         => '',                                      // string		Optional note to send the customer after purchase.
                'categories' => $categories,                             // array		List of categories. See Product - Categories properties
                //'tags'                  => $tags,                                   // array		List of tags. See Product - Tags properties
                //'images' => (!empty($images) ? $images : array()),                    // object		List of images. See Product - Images properties
                //'attributes'            => $attributes,			                    // array		List of attributes. See Product - Attributes properties
                //'default_attributes'    => $default_attributes,			            // array		Defaults variation attributes. See Product - Default attributes properties
                //'menu_order'            => 0,			                            // integer		Menu order, used to custom sort products.
                //'meta_data'             => $meta_data,                              // array		Meta data. See Product - Meta data properties
            ];

			if (is_array($object->context['ec_price_entities']) && in_array($this->site->entity, $object->context['ec_price_entities'])) {
				$productData['regular_price'] = $productSynchPrice == 'regular' || $initial_data ? $price : $regular_price;
				$productData['sale_price'] = $sale_price;
				$object->array_options["options_ecommerceng_wc_regular_price_{$this->site->id}_{$conf->entity}"] = $productData['regular_price'];
			}
            if ($productImageSynchDirection == 'dtoe' || $productImageSynchDirection == 'all') {
                $productData['images'] = (!empty($images) ? $images : array());
            }
            if ($productRefSynchDirection == 'dtoe' || $productRefSynchDirection == 'all') {
                $productData['sku'] = $object->ref;
            }
            if ($productDescriptionSynchDirection == 'dtoe' || $productDescriptionSynchDirection == 'all') {
                $productData['description'] = nl2br($object->array_options["options_ecommerceng_description_{$conf->entity}"]);

				if (empty($productData['description']) || $initial_data) {
					$productData['description'] = nl2br($object->description);
					$object->array_options["options_ecommerceng_description_{$conf->entity}"] = $productData['description'];
				}
            }
            if ($productShortDescriptionSynchDirection == 'dtoe' || $productShortDescriptionSynchDirection == 'all') {
                $productData['short_description'] = nl2br($object->array_options["options_ecommerceng_short_description_{$conf->entity}"]);
            }
            if ($productWeightSynchDirection == 'dtoe' || $productWeightSynchDirection == 'all') {
                $productData['weight'] = (!empty($totalWeight) ? $totalWeight : '');
            }
            if ($productTaxSynchDirection == 'dtoe' || $productTaxSynchDirection == 'all') {
                $productData['tax_status'] = 'none';

                // Set tax
                if (!$initial_data && !empty($object->array_options["options_ecommerceng_tax_class_{$this->site->id}_{$conf->entity}"])) {
                    $productData['tax_status'] = 'taxable';
                    $productData['tax_class'] = $object->array_options["options_ecommerceng_tax_class_{$this->site->id}_{$conf->entity}"];
				} elseif ($initial_data && $object->tva_tx > 0) {
					$tax_class = $this->getTaxClassFromVatRate($object->tva_tx);
					if (!empty($tax_class)) {
						$productData['tax_status'] = 'taxable';
						$productData['tax_class'] = $tax_class;
						$object->array_options["options_ecommerceng_tax_class_{$this->site->id}_{$conf->entity}"] = $tax_class;
					} else {
						$this->errors[] = $langs->trans('ECommerceWoocommerceErrorTaxClassNotFoundFromTaxRate', $object->tva_tx, $this->site->name, $object->ref . ' - '  . $remote_id);
						return $return_data;
					}
				}
            }
            if ($productStatusSynchDirection == 'dtoe' || $productStatusSynchDirection == 'all') {
                $productData['status'] = (!empty($status) ? $status : ($object->status ? 'publish' : 'draft'));
				$object->array_options["options_ecommerceng_wc_status_{$this->site->id}_{$conf->entity}"] = $productData['status'];
			}
			if ($this->site->stock_sync_direction == 'dolibarr2ecommerce') {
				$productData['manage_stock'] = !empty($object->array_options["options_ecommerceng_wc_manage_stock_{$this->site->id}_{$conf->entity}"]);
				$object->array_options["options_ecommerceng_wc_manage_stock_{$this->site->id}_{$conf->entity}"] = $productData['manage_stock'] ? 1 : 0;

				if ($productData['manage_stock'] && empty($object->array_options["options_ecommerceng_wc_dont_update_stock_{$this->site->id}_{$conf->entity}"])) {
					$supported_warehouses = is_array($this->site->parameters['fk_warehouse_to_ecommerce']) ? $this->site->parameters['fk_warehouse_to_ecommerce'] : array();
					$object->load_stock();

					$stock = 0;
					foreach ($supported_warehouses as $warehouse_id) {
						$stock += isset($object->stock_warehouse[$warehouse_id]->real) ? $object->stock_warehouse[$warehouse_id]->real : 0;
					}
					$stock = floor($stock);

					$productData['stock_quantity'] = $stock;
					$productData['in_stock'] = $stock > 0;
				}
			}

            // Synch extrafields <=> metadatas and attributes
            if (!empty($object->array_options)) {
                foreach ($object->array_options as $key => $value) {
                    $cr_key = substr($key, 8);
                    if (preg_match('/^ecommerceng_/', $cr_key)) continue;

					// Synch extrafields <=> attributes
					$options_saved = $this->site->parameters['ef_crp_attribute'][$cr_key];
					if ($options_saved['activated']) {
						$rm_key = $cr_key;
						if (isset($options_saved['correspondences'])) $rm_key = $options_saved['correspondences'];
						$productData['attributes'][] = array('name' => $rm_key, 'options' => explode(',', $value));
					}

					// Synch extrafields <=> metadatas
					$options_saved = $this->site->parameters['ef_crp']['product'][$cr_key];
                    if ($options_saved['activated']) {
                        $rm_key = $cr_key;
                        if (isset($options_saved['correspondences'])) $rm_key = $options_saved['correspondences'];
                        $productData['meta_data'][] = array('key' => $rm_key, 'value' => $value);
                    }
                }
            }

            $return_data['product'] = array(
				'remote_id' => $remote_product_id,
				'data' => $productData,
			);
        }

        return $return_data;
    }

	/**
	 * Update the remote product
	 *
	 * @param   int     $remote_id  Id of product on remote ecommerce
	 * @param   Product $object     Product object
	 *
	 * @return  boolean             True or false
	 */
	public function updateRemoteProduct($remote_id, $object)
	{
		dol_syslog(__METHOD__ . ": Update the remote product ID $remote_id for Dolibarr product ID {$object->id} for site ID {$this->site->id}", LOG_DEBUG);
		global $conf, $langs, $user;

		$datas = $this->convertObjectIntoProductData($remote_id, $object);
		if (empty($datas)) {
			return false;
		}

		// Update extrafields infos
		if (empty($conf->global->MAIN_EXTRAFIELDS_DISABLED)) $object->insertExtraFields();

		// Product
		//--------------------
		if (!empty($datas['product'])) {
			$remote_id = $datas['product']['remote_id'];
			$remote_data = $datas['product']['data'];

			$stopwatch_id = -1;
			try {
				$stopwatch_id = eCommerceUtils::startStopwatch(__METHOD__ . " - PUT products/{$remote_id}");
				if (!self::$disable_put_post_to_api) $result = $this->client->put("products/$remote_id", $remote_data);
				eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
				dol_syslog(__METHOD__ . " - Send PUT to API 'products/$remote_id' : Data: " . json_encode($remote_data), LOG_NOTICE);
			} catch (HttpClientException $fault) {
				eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
				$this->errors[] = $langs->trans('ECommerceWoocommerceUpdateRemoteProduct', $remote_id, $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
				dol_syslog(__METHOD__ .
					': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceUpdateRemoteProduct', $remote_id, $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
					' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
				return false;
			}

			// Support for WPML (Update (others than name and descriptions) infos on translated post)
			if (!empty($conf->global->ECOMMERCENG_WOOCOMMERCE_WPML_SUPPORT)) {
				if (isset($result->translations)) {
					if (isset($remote_data['name'])) unset($remote_data['name']);
					if (isset($remote_data['description'])) unset($remote_data['description']);
					if (isset($remote_data['short_description'])) unset($remote_data['short_description']);
					foreach ((array)$result->translations as $product_id) {
						$stopwatch_id = -1;
						try {
							$stopwatch_id = eCommerceUtils::startStopwatch(__METHOD__ . " - PUT products/{$product_id}");
							if (!self::$disable_put_post_to_api) $res = $this->client->put("products/$product_id", $remote_data);
							eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
							dol_syslog(__METHOD__ . " - Send PUT to API 'products/$product_id' : Data: " . json_encode($remote_data), LOG_NOTICE);
						} catch (HttpClientException $fault) {
							eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
							$this->errors[] = $langs->trans('ECommerceWoocommerceUpdateRemoteTranslatedProduct', $product_id, $remote_id, $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
							dol_syslog(__METHOD__ .
								': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceUpdateRemoteTranslatedProduct', $product_id, $remote_id, $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
								' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
							return false;
						}
					}
				}
			}
		}

		// Variations
		//--------------------
		if (!empty($datas['variations'])) {
			foreach ($datas['variations'] as $variation_data) {
				$remote_id = $variation_data['parent_remote_id'];
				$remote_variation_id = $variation_data['remote_id'];
				$remote_data = $variation_data['data'];

				$stopwatch_id = -1;
				try {
					$stopwatch_id = eCommerceUtils::startStopwatch(__METHOD__ . " - PUT products/{$remote_id}/variations/{$remote_variation_id}");
					if (!self::$disable_put_post_to_api) $result = $this->client->put("products/$remote_id/variations/$remote_variation_id", $remote_data);
					eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
					dol_syslog(__METHOD__ . " - Send PUT to API 'products/$remote_id/variations/$remote_variation_id' : Data: " . json_encode($remote_data), LOG_NOTICE);
				} catch (HttpClientException $fault) {
					eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
					$this->errors[] = $langs->trans('ECommerceWoocommerceUpdateRemoteProductVariation', $remote_variation_id, $remote_id, $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
					dol_syslog(__METHOD__ .
						': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceUpdateRemoteProductVariation', $remote_variation_id, $remote_id, $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
						' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
					return false;
				}

				// Support for WPML (Update (others than name and descriptions) infos on translated post)
				if (!empty($conf->global->ECOMMERCENG_WOOCOMMERCE_WPML_SUPPORT)) {
					if (isset($result->translations)) {
						if (isset($remote_data['name'])) unset($remote_data['name']);
						if (isset($remote_data['description'])) unset($remote_data['description']);
						if (isset($remote_data['short_description'])) unset($remote_data['short_description']);
						foreach ((array)$result->translations as $product_id) {
							$stopwatch_id = -1;
							try {
								$stopwatch_id = eCommerceUtils::startStopwatch(__METHOD__ . " - GET products/{$product_id}");
								$result2 = $this->client->get("products/$product_id");
								eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
							} catch (HttpClientException $fault) {
								eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
								$this->errors[] = $langs->trans('ECommerceWoocommerceGetTranslatedProductVariation', $product_id, $remote_variation_id, $remote_id, $this->site->name) . ': ' . $fault->getCode() . ': ' . $fault->getMessage();
								dol_syslog(__METHOD__ .
									': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceGetTranslatedProductVariation', $product_id, $remote_variation_id, $remote_id, $this->site->name) . ': ' . $fault->getCode() . ': ' . $fault->getMessage() .
									' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
								return false;
							}
							$stopwatch_id = -1;
							try {
								$stopwatch_id = eCommerceUtils::startStopwatch(__METHOD__ . " - PUT products/{$result2->parent_id}/variations/{$product_id}");
								if (!self::$disable_put_post_to_api) $res = $this->client->put("products/{$result2->parent_id}/variations/$product_id", $remote_data);
								eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
								dol_syslog(__METHOD__ . " - Send PUT to API 'products/{$result2->parent_id}/variations/$product_id' : Data: " . json_encode($remote_data), LOG_NOTICE);
							} catch (HttpClientException $fault) {
								eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
								$this->errors[] = $langs->trans('ECommerceWoocommerceUpdateRemoteTranslatedProductVariation', $result2->parent_id . '|' . $product_id, $remote_variation_id, $remote_id, $this->site->name) . ': ' . $fault->getCode() . ': ' . $fault->getMessage();
								dol_syslog(__METHOD__ .
									': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceUpdateRemoteTranslatedProductVariation', $result2->parent_id . '|' . $product_id, $remote_variation_id, $remote_id, $this->site->name) . ': ' . $fault->getCode() . ': ' . $fault->getMessage() .
									' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
								return false;
							}
						}
					}
				}
			}
		}

		dol_syslog(__METHOD__ . ": end", LOG_DEBUG);
		return true;
	}

    /**
     * Update the remote stock of product
     *
     * @param   int             $remote_id      Id of product on remote ecommerce
     * @param   MouvementStock  $object         MouvementStock object, enhanced with property qty_after be the trigger STOCK_MOVEMENT.
	 * @param   Product			$product        Product object
	 *
     * @return  boolean                         True or false
     */
    public function updateRemoteStockProduct($remote_id, $object, $product)
    {
        dol_syslog(__METHOD__ . ": Update stock of the remote product ID $remote_id for MouvementStock ID {$object->id}, new qty: {$object->qty_after} for site ID {$this->site->id}", LOG_DEBUG);
        global $conf, $langs, $user;

		if (empty($product->array_options["options_ecommerceng_wc_manage_stock_{$this->site->id}_{$conf->entity}"]) || !empty($product->array_options["options_ecommerceng_wc_dont_update_stock_{$this->site->id}_{$conf->entity}"])) {
			dol_syslog(__METHOD__ . " - Ignore update stock of the remote product ID $remote_id for MouvementStock ID {$object->id}, new qty: {$object->qty_after} for site ID {$this->site->id}", LOG_INFO);
			return true;
		}

		$this->errors = array();
		$new_stocks = floor($object->qty_after);
        $stocks_label = $object->qty_after . ' -> ' . $new_stocks;

		$isProductVariation = false;
		$isProductVariationHasOne = false;
		$remote_product_id = $remote_id;
		$remote_product_variation_ids = array();
		if (preg_match('/^(\d+)\|(\d+)$/', $remote_id, $idsProduct) == 1) { // Variations
			$isProductVariation = true;
			$remote_product_id = $idsProduct[1];
			$remote_product_variation_ids[] = $idsProduct[2];
		}

		$product_variation_mode_all_to_one = !empty($this->site->parameters['product_variation_mode']) && $this->site->parameters['product_variation_mode'] == 'all_to_one';

		if ($product_variation_mode_all_to_one) {
			$idsProduct = explode('|', $remote_id);
			if (count($idsProduct) > 1) {
				$isProductVariationHasOne = true;
				$remote_product_id = $idsProduct[0];
				$remote_product_variation_ids = array_splice($idsProduct, 1);
			}
		}

        if ($isProductVariation || $isProductVariationHasOne) {
            // Variations
            $variationData = [
                //'manage_stock'      => '',                      // boolean      Stock management at variation level. Default is false.
                'stock_quantity' => $new_stocks,         // integer      Stock quantity.
                'in_stock' => $new_stocks > 0,           // boolean      Controls whether or not the variation is listed as “in stock” or “out of stock” on the frontend. Default is true.
                //'backorders'        => '',                      // string       If managing stock, this controls if backorders are allowed. Options: no, notify and yes. Default is no.
            ];

			foreach ($remote_product_variation_ids as $remote_product_variation_id) {
				$stopwatch_id = -1;
				try {
					$stopwatch_id = eCommerceUtils::startStopwatch(__METHOD__ . " - PUT products/{$remote_product_id}/variations/{$remote_product_variation_id}");
					if (!self::$disable_put_post_to_api) $result = $this->client->put("products/$remote_product_id/variations/$remote_product_variation_id", $variationData);
					eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
					dol_syslog(__METHOD__ . " - Send PUT to API 'products/$remote_product_id/variations/$remote_product_variation_id' : Data: " . json_encode($variationData), LOG_NOTICE);
				} catch (HttpClientException $fault) {
					eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
					$this->errors[] = $langs->trans('ECommerceWoocommerceUpdateRemoteStockProductVariation', $stocks_label, $remote_product_variation_id, $remote_product_id, $this->site->name) . ' ' . $fault->getCode() . ': ' . $fault->getMessage();
					dol_syslog(__METHOD__ .
						': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceUpdateRemoteStockProductVariation', $stocks_label, $remote_product_variation_id, $remote_product_id, $this->site->name) . ' ' . $fault->getCode() . ': ' . $fault->getMessage()) .
					' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse(), LOG_ERR);
					return false;
				}
			}
        }
		// Product
		if (!$isProductVariation || $isProductVariationHasOne) {
			$productData = [
                //'manage_stock'      => false,                   // boolean      Stock management at product level. Default is false.
                'stock_quantity' => $new_stocks,         // integer      Stock quantity.
                'in_stock' => $new_stocks > 0,           // boolean      Controls whether or not the product is listed as “in stock” or “out of stock” on the frontend. Default is true.
                //'backorders'        => '',                      // string       If managing stock, this controls if backorders are allowed. Options: no, notify and yes. Default is no.
            ];

			$stopwatch_id = -1;
			try {
				$stopwatch_id = eCommerceUtils::startStopwatch(__METHOD__ . " - PUT products/{$remote_product_id}");
				if (!self::$disable_put_post_to_api) $result = $this->client->put("products/$remote_product_id", $productData);
				eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
				dol_syslog(__METHOD__ . " - Send PUT to API 'products/$remote_product_id' : Data: " . json_encode($productData), LOG_NOTICE);
            } catch (HttpClientException $fault) {
				eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
				$this->errors[] = $langs->trans('ECommerceWoocommerceUpdateRemoteStockProduct', $stocks_label, $remote_product_id, $this->site->name) . ' ' . $fault->getCode() . ': ' . $fault->getMessage();
                dol_syslog(__METHOD__ .
                    ': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceUpdateRemoteStockProduct', $stocks_label, $remote_product_id, $this->site->name) . ' ' . $fault->getCode() . ': ' . $fault->getMessage()) .
                                                            ' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse(), LOG_ERR);
                return false;
            }
        }

		// Support for WPML (Update stocks infos on translated post)
		if (!empty($conf->global->ECOMMERCENG_WOOCOMMERCE_WPML_SUPPORT)) {
			if ($isProductVariation || $isProductVariationHasOne) {
				foreach ($remote_product_variation_ids as $remote_product_variation_id) {
					$stopwatch_id = -1;
					try {
						$stopwatch_id = eCommerceUtils::startStopwatch(__METHOD__ . " - GET products/{$remote_product_variation_id}");
						$result = $this->client->get("products/$remote_product_variation_id");
						eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
					} catch (HttpClientException $fault) {
						eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
						$this->errors[] = $langs->trans('ECommerceWoocommerceGetProductVariation', $remote_product_variation_id, $remote_product_id, $this->site->name) . ': ' . $fault->getCode() . ': ' . $fault->getMessage();
						dol_syslog(__METHOD__ .
							': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceGetProductVariation', $remote_product_variation_id, $remote_product_id, $this->site->name) . ': ' . $fault->getCode() . ': ' . $fault->getMessage() .
							' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
						return false;
					}
					if (isset($result->translations)) {
						foreach ((array)$result->translations as $product_id) {
							$stopwatch_id = -1;
							try {
								$stopwatch_id = eCommerceUtils::startStopwatch(__METHOD__ . " - GET products/{$product_id}");
								$result2 = $this->client->get("products/$product_id");
								eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
							} catch (HttpClientException $fault) {
								eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
								$this->errors[] = $langs->trans('ECommerceWoocommerceGetTranslatedProductVariation', $product_id, $remote_product_variation_id, $remote_product_id, $this->site->name) . ': ' . $fault->getCode() . ': ' . $fault->getMessage();
								dol_syslog(__METHOD__ .
									': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceGetTranslatedProductVariation', $product_id, $remote_product_variation_id, $remote_product_id, $this->site->name) . ': ' . $fault->getCode() . ': ' . $fault->getMessage() .
									' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
								return false;
							}
							$stopwatch_id = -1;
							try {
								$stopwatch_id = eCommerceUtils::startStopwatch(__METHOD__ . " - PUT products/{$result2->parent_id}/variations/{$product_id}");
								if (!self::$disable_put_post_to_api) $res = $this->client->put("products/{$result2->parent_id}/variations/$product_id", $variationData);
								eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
								dol_syslog(__METHOD__ . " - Send PUT to API 'products/{$result2->parent_id}/variations/$product_id' : Data: " . json_encode($variationData), LOG_NOTICE);
							} catch (HttpClientException $fault) {
								eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
								$this->errors[] = $langs->trans('ECommerceWoocommerceUpdateRemoteStockTranslatedProductVariation', $stocks_label, $result2->parent_id . '|' . $product_id, $remote_product_variation_id, $remote_product_id) . ': ' . $this->site->name . ' - ' . $fault->getCode() . ': ' . $fault->getMessage();
								dol_syslog(__METHOD__ .
									': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceUpdateRemoteStockTranslatedProductVariation', $stocks_label, $result2->parent_id . '|' . $product_id, $remote_product_variation_id, $remote_product_id) . ': ' . $this->site->name . ' - ' . $fault->getCode() . ': ' . $fault->getMessage()) .
								' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse(), LOG_ERR);
								return false;
							}
						}
					}
				}
			}
			if (!$isProductVariation || $isProductVariationHasOne) {
				$stopwatch_id = -1;
				try {
					$stopwatch_id = eCommerceUtils::startStopwatch(__METHOD__ . " - GET products/{$remote_product_id}");
					$result = $this->client->get("products/$remote_product_id");
					eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
				} catch (HttpClientException $fault) {
					eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
					$this->errors[] = $langs->trans('ECommerceWoocommerceGetProduct', $remote_product_id, $this->site->name) . ': ' . $fault->getCode() . ': ' . $fault->getMessage();
					dol_syslog(__METHOD__ .
						': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceGetProduct', $remote_product_id, $this->site->name) . ': ' . $fault->getCode() . ': ' . $fault->getMessage() .
						' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
					return false;
				}
				if (isset($result->translations)) {
					foreach ((array)$result->translations as $product_id) {
						$stopwatch_id = -1;
						try {
							$stopwatch_id = eCommerceUtils::startStopwatch(__METHOD__ . " - PUT products/{$product_id}");
							if (!self::$disable_put_post_to_api) $res = $this->client->put("products/$product_id", $productData);
							eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
							dol_syslog(__METHOD__ . " - Send PUT to API 'products/$product_id' : Data: " . json_encode($productData), LOG_NOTICE);
						} catch (HttpClientException $fault) {
							eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
							$this->errors[] = $langs->trans('ECommerceWoocommerceUpdateRemoteStockTranslatedProduct', $stocks_label, $product_id, $remote_id, $this->site->name) . ' ' . $fault->getCode() . ': ' . $fault->getMessage();
							dol_syslog(__METHOD__ .
								': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceUpdateRemoteStockTranslatedProduct', $stocks_label, $product_id, $remote_id, $this->site->name) . ' ' . $fault->getCode() . ': ' . $fault->getMessage()) .
							' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse(), LOG_ERR);
							return false;
						}
					}
				}
			}
		}

        dol_syslog(__METHOD__ . ": end", LOG_DEBUG);
        return true;
    }

    /**
     * Update the remote company
     *
     * @param   int     $remote_id  Id of company on remote ecommerce
     * @param   Societe $object     Societe object
     *
     * @return  boolean             True or false
     */
    public function updateRemoteSociete($remote_id, $object)
    {
        dol_syslog(__METHOD__ . ": Update the remote company ID $remote_id for Dolibarr company ID {$object->id} for site ID {$this->site->id}", LOG_DEBUG);
        global $langs, $user;

		$this->errors = array();
		/*
		   // Customer - Meta data properties
		   $meta_data = [
			   'key' => '',        // string   Meta key.
			   'value' => '',      // string   Meta value.
		   ];
		   */
        $companyData = [
            //'email' => $object->email,              // string   The email address for the customer. MANDATORY
            //'first_name'    => '',                  // string   Customer first name.
            //'last_name'     => $object->name,       // string   Customer last name.
            //'username'      => '',                  // string   Customer login name.
            //'password'      => '',                  // string   Customer password.
            //'meta_data'     => $meta_data,          // array    Meta data. See Customer - Meta data properties
        ];

		$stopwatch_id = -1;
        try {
			$stopwatch_id = eCommerceUtils::startStopwatch(__METHOD__ . " - PUT customers/{$remote_id}");
			if (!self::$disable_put_post_to_api) $result = $this->client->put("customers/$remote_id", $companyData);
			eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
        } catch (HttpClientException $fault) {
			eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
			$this->errors[] = $langs->trans('ECommerceWoocommerceUpdateRemoteSociete', $remote_id, $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
            dol_syslog(__METHOD__ .
                ': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceUpdateRemoteSociete', $remote_id, $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
                ' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
            return false;
        }

        dol_syslog(__METHOD__ . ": end", LOG_DEBUG);
        return true;
    }

    /**
     * Update the remote contact
     *
     * @param   int     $remote_id  Id of contact on remote ecommerce
     * @param   Contact $object     Contact object
     *
     * @return  boolean             True or false
     */
    public function updateRemoteSocpeople($remote_id, $object)
    {
        dol_syslog(__METHOD__ . ": Update the remote contact ID $remote_id for Dolibarr contact ID {$object->id} for site ID {$this->site->id}", LOG_DEBUG);
        global $conf, $langs, $user;

		$this->errors = array();
		// Get societe
        //require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
        //$societe = new Societe($this->db);
        //$societe->fetch($object->socid);

        $billingName = (empty($conf->global->ECOMMERCENG_BILLING_CONTACT_NAME) ? 'Billing' : $conf->global->ECOMMERCENG_BILLING_CONTACT_NAME);      // Contact name treated as billing address.
        $shippingName = (empty($conf->global->ECOMMERCENG_SHIPPING_CONTACT_NAME) ? 'Shipping' : $conf->global->ECOMMERCENG_SHIPPING_CONTACT_NAME);  // Contact name treated as shipping address.

        if ($object->lastname == $billingName) {
            $address = explode("\n", $object->address);
            // Billing
            $contactData = [
                'billing' => [
                    //'first_name'    => '',                                  // string   First name.
                    //'last_name'     => '',                                  // string   Last name.
                    //'company'       => $societe->name,                      // string   Company name.
                    'address_1' => isset($address[0]) ? $address[0] : '',   // string   Address line 1
                    'address_2' => isset($address[1]) ? implode(" ", array_slice($address, 1)) : '',   // string   Address line 2
                    'city' => $object->town,                       // string   City name.
                    //'state'         => '',                                  // string   ISO code or name of the state, province or district.
                    'postcode' => $object->zip,                        // string   Postal code.
                    'country' => getCountry($object->country_id, 2),  // string   ISO code of the country.
                    'email' => $object->email,                      // string   Email address.
                    'phone' => $object->phone_pro,                  // string   Phone number.
                ],
            ];
        } elseif ($object->lastname == $shippingName) {
            $address = explode("\n", $object->address);
            // Shipping
            $contactData = [
                'shipping' => [
                    //'first_name'    => '',                                  // string   First name.
                    //'last_name'     => '',                                  // string   Last name.
                    //'company'       => $societe->name,                      // string   Company name.
                    'address_1' => isset($address[0]) ? $address[0] : '',   // string   Address line 1
                    'address_2' => isset($address[1]) ? implode(" ", array_slice($address, 1)) : '',   // string   Address line 2
                    'city' => $object->town,                       // string   City name.
                    //'state'         => '',                                  // string   ISO code or name of the state, province or district.
                    'postcode' => $object->zip,                        // string   Postal code.
                    'country' => getCountry($object->country_id, 2),  // string   ISO code of the country.
                ],
            ];
        }

        if (isset($contactData)) {
            if (preg_match('/^(\d+)\|(\d+)$/', $remote_id, $idsCustomer) == 1) {
				$stopwatch_id = -1;
				try {
					$stopwatch_id = eCommerceUtils::startStopwatch(__METHOD__ . " - PUT customers/{$idsCustomer[1]}");
					if (!self::$disable_put_post_to_api) $result = $this->client->put("customers/$idsCustomer[1]", $contactData);
					eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
				} catch (HttpClientException $fault) {
					eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
					$this->errors[] = $langs->trans('ECommerceWoocommerceUpdateRemoteSocpeople', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
                    dol_syslog(__METHOD__ .
                        ': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceUpdateRemoteSocpeople', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
                        ' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
                    return false;
                }
            }
        }

        dol_syslog(__METHOD__ . ": end", LOG_DEBUG);
        return true;
    }

    /**
     * Update the remote order
     *
     * @param   int         $remote_id  Id of order on remote ecommerce
     * @param   Commande    $object     Commande object
     *
     * @return  boolean                 True or false
     */
    public function updateRemoteCommande($remote_id, $object)
    {
        dol_syslog(__METHOD__ . ": Update the remote order ID $remote_id for Dolibarr order ID {$object->id} for site ID {$this->site->id}", LOG_DEBUG);
        global $conf, $langs, $user;

		$this->errors = array();
		$status = '';
        if (isset($this->site->parameters['order_status_dtoe'][$object->statut]))
            $status = $this->site->parameters['order_status_dtoe'][$object->statut];

        if (!empty($status)) {
            $object->fetch_optionals();

            require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
            $efields = new ExtraFields($this->db);
            $efields->fetch_name_optionals_label('commande', true);
            $order_status = array();
            if (isset($efields->attribute_param["ecommerceng_wc_status_{$this->site->id}_{$conf->entity}"]['options']) &&
               is_array($efields->attribute_param["ecommerceng_wc_status_{$this->site->id}_{$conf->entity}"]['options'])) {
                foreach ($efields->attribute_param["ecommerceng_wc_status_{$this->site->id}_{$conf->entity}"]['options'] as $key => $value) {
                    $status_lvl = 0;
                    if (($pos = strpos($key , '_')) > 0) {
                        $status_key = substr($key, $pos + 1);
                        $status_lvl = substr($key, 0, $pos);
                    }
                    $order_status[$status_key] = $status_lvl;
                }
            }

            $wc_status = $object->array_options["options_ecommerceng_wc_status_{$this->site->id}_{$conf->entity}"];
            if ($order_status[$status] < $order_status[$wc_status] &&
                !empty($conf->global->ECOMMERCENG_WOOCOMMERCE_ORDER_STATUS_LVL_CHECK)) $status = $wc_status;

            $orderData = [
                'status' => $status,  // string  Order status. Options: pending, processing, on-hold, completed, cancelled, refunded and failed.
            ];

            // Synch extrafields <=> metadatas
            if (!empty($object->array_options)) {
                foreach ($object->array_options as $key => $value) {
                    $cr_key = substr($key, 8);
                    if (preg_match('/^ecommerceng_/', $cr_key)) continue;
                    $options_saved = $this->site->parameters['ef_crp']['commande'][$cr_key];
                    if ($options_saved['activated']) {
                        $extrafield_value = $value;
                        // Specific Altairis - Begin
                        if (!empty($extrafield_value) && ($cr_key == 'rental_start' || $cr_key == 'rental_end')) {
                            $extrafield_value = dol_print_date($extrafield_value, 'day');
                        }
                        // Specific Altairis - End

                        $rm_key = $cr_key;
                        if (isset($options_saved['correspondences'])) $rm_key = $options_saved['correspondences'];
                        $orderData['meta_data'][] = array('key' => $rm_key, 'value' => $extrafield_value);
                    }
                }
            }

			$stopwatch_id = -1;
			try {
				$stopwatch_id = eCommerceUtils::startStopwatch(__METHOD__ . " - PUT orders/{$remote_id}");
				if (!self::$disable_put_post_to_api) $result = $this->client->put("orders/$remote_id", $orderData);
				eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
			} catch (HttpClientException $fault) {
				eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
				$this->errors[] = $langs->trans('ECommerceWoocommerceUpdateRemoteCommande', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
                dol_syslog(__METHOD__ .
                    ': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceUpdateRemoteCommande', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
                    ' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
                return false;
            }

            $object->array_options["options_ecommerceng_wc_status_{$this->site->id}_{$conf->entity}"] = $order_status[$status] . '_' . $status;
            $object->insertExtraFields();
        }

        dol_syslog(__METHOD__ . ": end", LOG_DEBUG);
        return true;
    }

    /**
     * Desactivated because is not supported by woocommerce.
     *
     * @param   int     $remote_id      Id of invoice on remote ecommerce
     * @param   Facture $object         Invoice object
     *
     * @return  boolean                 True or false
     */
    public function updateRemoteFacture($remote_id, $object)
    {
        dol_syslog(__METHOD__ . ": Desactivated for site ID {$this->site->id}", LOG_DEBUG);
		$this->errors = array();
		return true;
    }

    /**
     * Desactivated because is not supported by woocommerce.
     *
     * @param   int         $livraison          Object shipment ?
     * @param   int         $remote_order_id    Id of remote order
     *
     * @return  boolean                         True or false
     */
    public function createRemoteLivraison($livraison, $remote_order_id)
    {
        dol_syslog(__METHOD__ . ": Desactivated for site ID {$this->site->id}", LOG_DEBUG);
		$this->errors = array();
		return true;
    }

    /**
     * Create product
     *
     * @param   Product     $object     Object product
     *
     * @return  boolean|int             False of Id of remote product created
     */
    public function createRemoteProduct($object)
    {
        dol_syslog(__METHOD__ . ": Create product from Dolibarr product ID {$object->id} for site ID {$this->site->id}", LOG_DEBUG);
        global $conf, $langs, $user;

		$this->errors = array();
		$filters = ['sku' => $object->ref];
		$stopwatch_id = eCommerceUtils::startStopwatch(__METHOD__ . " - GET products (filters: " . json_encode($filters). ")");
		try {
            $results = $this->client->get('products', $filters);
			eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
		} catch (HttpClientException $fault) {
			eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
			$this->errors[] = $langs->trans('ECommerceWoocommerceCheckRemoteProductExist', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
            dol_syslog(__METHOD__ .
                ': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceCheckRemoteProductExist', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
                ' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
            return false;
        }
        $results = isset($results->products) ? $results->products : is_array($results) ? $results : [];

        if (is_array($results) && count($results) > 0) {
			$remote_id = $results[0]->id;
            if (!$this->updateRemoteProduct($remote_id, $object))
                return false;
        } else {
			$datas = $this->convertObjectIntoProductData('', $object);
			if (empty($datas)) {
				return false;
			}

			// Update extrafields infos
			if (empty($conf->global->MAIN_EXTRAFIELDS_DISABLED)) $object->insertExtraFields();

			// Product
			//--------------------
			if (!empty($datas['product'])) {
				$remote_data = $datas['product']['data'];

				$stopwatch_id = -1;
				try {
					$stopwatch_id = eCommerceUtils::startStopwatch(__METHOD__ . " - POST products - Product ID: {$object->id} - Data: " . json_encode($remote_data));
					if (!self::$disable_put_post_to_api) $res = $this->client->post("products", $remote_data);
					eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
					$remote_id = $res->id;
					dol_syslog(__METHOD__ . " - Send POST to API 'products' : Data: " . json_encode($remote_data) . "; Remote ID: $remote_id", LOG_NOTICE);
				} catch (HttpClientException $fault) {
					eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
					$this->errors[] = $langs->trans('ECommerceWoocommerceCreateRemoteProduct', $object->id, $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
					dol_syslog(__METHOD__ .
						': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceCreateRemoteProduct', $object->id, $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
						' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
					return false;
				}
			}
        }

        dol_syslog(__METHOD__ . ": end", LOG_DEBUG);
        return $remote_id;
    }

    /**
     * Create batch categories
     *
     * @param   array     $batch     Array of object category
     *
     * @return  bool|array           Array of association id <=> remote id
     */
    public function createRemoteCategories($batch)
    {
        $ids = implode(', ', array_keys($batch));
        dol_syslog(__METHOD__ . ": Create batch categories from Dolibarr categories IDs: '{$ids}' for site ID {$this->site->id}", LOG_DEBUG);
        global $conf, $langs;

        $this->errors = array();

        // Set datas to create
        $cats_by_level = array();
        $cats_slug_id = array();
        foreach ($batch as $cat_id => $category) {
            if ($category['level'] == 1) continue;

            $slug = 'cat_'.$cat_id;

            $categoryData = [
                'name'          => $category['label'],                  // string		Category name.
                'slug'          => $slug,			                    // string		An alphanumeric identifier for the resource unique to its type.
                'parent'        => $category['level'] > 2 ? $category['fk_parent'] : null,			    // integer		The ID for the parent of the resource.
                'description'   => $category['description'],            // string		HTML description of the resource.
                //'display'       => '',		                          // string		Category archive display type. Options: default, products, subcategories and both. Default is default.
                //'images'        => $images,                             // object		Image data. See Product category - Image properties
                //'menu_order'    => 0,                                   // integer	Menu order, used to custom sort the resource.
            ];

            $cats_slug_id[$slug]                 = $cat_id;
            $cats_by_level[$category['level']][] = $categoryData;
        }
        ksort($cats_by_level);

        // Create categories on Woocommerce
        $countCreated = 0;
        $cats_id_remote_id = array();
        $nb_max_by_request = empty($conf->global->ECOMMERCENG_MAXSIZE_BATCH) ? 100 : min($conf->global->ECOMMERCENG_MAXSIZE_BATCH, 100);
        foreach ($cats_by_level as $lvl => $group) {
            foreach ($group as $key => $categoryData) {
                if (isset($cats_id_remote_id[$categoryData['parent']])) {
                    $group[$key]['parent'] = $cats_id_remote_id[$categoryData['parent']]['remote_id'];
                } elseif ($categoryData['parent'] > 0) {
                    $sql="SELECT remote_id FROM ".MAIN_DB_PREFIX."ecommerce_category WHERE fk_category =".$categoryData['parent'];
        			$resql=$this->db->query($sql);
        			if ($resql) {
        				if ($this->db->num_rows($resql)==1) {
                			$obj = $this->db->fetch_object($resql);
                			$group[$key]['parent'] = $obj->remote_id;
        				} else {
        					
        					$this->errors[] = $langs->trans('ECommerceWoocommerceCreateRemoteCategoryParentNotFound', $this->site->name, $categoryData['name'], $categoryData['slug']);
	                    	dol_syslog(__METHOD__ .
	                        ': Error:' . $langs->trans('ECommerceWoocommerceCreateRemoteCategoryParentNotFound', $this->site->name, $categoryData['name'], $categoryData['slug']), LOG_ERR);
	                    	return false;
        				}
            		} else {
	                	
	                    $this->errors[] = $langs->trans('ECommerceWoocommerceCreateRemoteCategoryParentNotCreated', $this->site->name, $categoryData['name'], $categoryData['slug']);
	                    dol_syslog(__METHOD__ .
	                        ': Error:' . $langs->trans('ECommerceWoocommerceCreateRemoteCategoryParentNotCreated', $this->site->name, $categoryData['name'], $categoryData['slug']), LOG_ERR);
	                    return false;
            		}
                }
            }

            $requestGroups = $this->getRequestGroups($group, $nb_max_by_request);
            foreach ($requestGroups as $request) {
                $error = 0;

				$stopwatch_id = -1;
				try {
					$stopwatch_id = eCommerceUtils::startStopwatch(__METHOD__ . " - POST products/categories/batch");
					if (!self::$disable_put_post_to_api) $results = $this->client->post("products/categories/batch", ['create' => $request]);
					eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
					dol_syslog(__METHOD__ . " - Send POST to API 'products/categories/batch' : Data: " . json_encode(['create' => $request]), LOG_NOTICE);
                } catch (HttpClientException $fault) {
					eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
					$this->errors[] = $langs->trans('ECommerceWoocommerceCreateRemoteBatchCategories', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
                    dol_syslog(__METHOD__ .
                        ': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceCreateRemoteBatchCategories', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
                        ' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
                    return false;
                }

                $results = isset($results->create) ? $results->create : array();
                foreach ($results as $key => $item) {
                    if (isset($item->error)) {
                        $this->errors[] = $langs->trans('ECommerceWoocommerceCreateRemoteBatchCategory', $request[$key]['slug'], $this->site->name,$item->error->code . ': ' . $item->error->message . ' (data : ' . json_encode($item->error->data) . ' )');
                        dol_syslog(__METHOD__ . ': Error:' .
                            $langs->trans('ECommerceWoocommerceCreateRemoteBatchCategory', $request[$key]['slug'], $this->site->name, $item->error->code . ': ' . $item->error->message . ' (data : ' . json_encode($item->error->data) . ' )'), LOG_ERR);
                    } else {
                        $cats_id_remote_id[$cats_slug_id[$item->slug]] = array('remote_id' => $item->id, 'remote_parent_id' => $item->parent);
                    }
                }
            }
        }

        dol_syslog(__METHOD__ . ": end", LOG_DEBUG);
        return $cats_id_remote_id;
    }

    /**
     * Batch update products to ECommerce
     *
     * @param   array     $batch    Array of id of product
     *
     * @return  array               Array of association id <=> remote id
     */
    public function batchUpdateRemoteProducts($batch)
    {
        $ids = implode(', ', $batch);
        dol_syslog(__METHOD__ . ": Create batch products from Dolibarr products IDs: '{$ids}' for site ID {$this->site->id}", LOG_DEBUG);
        global $conf, $langs;

		$this->errors = array();

		require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
		$product_static = new Product($this->db);

		$nb_max_by_request = empty($conf->global->ECOMMERCENG_MAXSIZE_BATCH) ? 100 : min($conf->global->ECOMMERCENG_MAXSIZE_BATCH, 100);

		// Get data to send
		$product_sku_id = array();
		$products = array();
		$product_variations = array();
		$product_batch = array();
		$product_variation_batch = array();
		$product_batch_count = 0;
		$product_variation_batch_count = 0;
		foreach ($batch as $product_id => $batch_infos) {
			if ($product_static->fetch($product_id) > 0) {
				$datas = $this->convertObjectIntoProductData($batch_infos['remote_id'], $product_static);
				if (empty($datas)) {
					return null;
				}

				// Update extrafields infos
				if (empty($conf->global->MAIN_EXTRAFIELDS_DISABLED)) $product_static->insertExtraFields();

				$to_be_created = $batch_infos['type'] == 'create';

				// Product
				//--------------------
				if (!empty($datas['product'])) {
					$remote_id = $datas['product']['remote_id'];
					$remote_data = $datas['product']['data'];

					// Set correspondence sku (ref) with Product ID
					if (empty($remote_data['sku'])) {
						$this->errors[] = $langs->trans('ECommerceErrorSkuNeededCheckModuleConfiguration');
						return null;
					}
					$product_sku_id[$remote_data['sku']] = $product_id;

					// Reset batch loop
					if ($product_batch_count > $nb_max_by_request) {
						$products[] = $product_batch;
						$product_batch = array();
						$product_batch_count = 0;
					}

					// Add data to batch
					if ($to_be_created) {
						$product_batch['create'][] = $remote_data;
					} else {
						$remote_data['id'] = $remote_id;
						$product_batch['update'][] = $remote_data;
					}
					$product_batch_count++;
				}

				if (!empty($datas['variations'])) {
					foreach ($datas['variations'] as $variation_data) {
						$remote_id = $variation_data['parent_remote_id'];
						$remote_variation_id = $variation_data['remote_id'];
						$remote_data = $variation_data['data'];

						// Set correspondence sku (ref) with Product ID
						if (empty($remote_data['sku'])) {
							$this->errors[] = $langs->trans('ECommerceErrorSkuNeededCheckModuleConfiguration');
							return null;
						}
						$product_sku_id[$remote_data['sku']] = $product_id;

						// Reset batch loop
						if ($product_variation_batch_count > $nb_max_by_request) {
							$product_variations[] = $product_variation_batch;
							$product_variation_batch = array();
							$product_variation_batch_count = 0;
						}

						// Add data to batch
						if ($to_be_created) {
							$product_variation_batch[$remote_id]['create'][] = $remote_data;
						} else {
							$remote_data['id'] = $remote_variation_id;
							$product_variation_batch[$remote_id]['update'][] = $remote_data;
						}
						$product_variation_batch_count++;
					}
				}
			}
		}

		$product_success = array();

		// Send product batch
		foreach ($products as $batch_data) {
			$error = 0;

			$stopwatch_id = -1;
			try {
				$stopwatch_id = eCommerceUtils::startStopwatch(__METHOD__ . " - POST products/batch");
				if (!self::$disable_put_post_to_api) $results = $this->client->post("products/batch", $batch_data);
				eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
				dol_syslog(__METHOD__ . " - Send POST to API 'products/batch' : Data: " . json_encode($batch_data), LOG_NOTICE);
			} catch (HttpClientException $fault) {
				eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
				$this->errors[] = $langs->trans('ECommerceWoocommerceCreateRemoteBatchProducts', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
				dol_syslog(__METHOD__ .
					': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceCreateRemoteBatchProducts', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
					' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
				$error++;
			}

			if (!$error && !empty($results)) {
				$created = isset($results->create) ? $results->create : array();
				foreach ($created as $key => $item) {
					if (isset($item->error)) {
						$error_msg = $langs->trans('ECommerceWoocommerceCreateRemoteBatchProduct', $batch_data[$key]['sku'], $this->site->name, $item->error->code . ': ' . $item->error->message . ' (data : ' . json_encode($item->error->data) . ' )');
						$this->errors[] = $error_msg;
						dol_syslog(__METHOD__ . ': Error: ' . $error_msg, LOG_ERR);
					} else {
						$product_success[] = array(
							'product_id' => $product_sku_id[$item->sku],
							'remote_id' => $item->id,
						);
					}
				}
				$updated = isset($results->update) ? $results->update : array();
				foreach ($updated as $key => $item) {
					if (isset($item->error)) {
						$error_msg = $langs->trans('ECommerceWoocommerceUpdateRemoteBatchProduct', $item->id, $this->site->name, $item->error->code . ': ' . $item->error->message . ' (data : ' . json_encode($item->error->data) . ' )');
						$this->errors[] = $error_msg;
						dol_syslog(__METHOD__ . ': Error: ' . $error_msg, LOG_ERR);
					} else {
						$product_success[] = array(
							'product_id' => $product_sku_id[$item->sku],
							'remote_id' => $item->id,
						);
					}
				}
			}
		}

		// Send product variation batch
		foreach ($product_variations as $remote_id => $batch_data) {
			$error = 0;

			$stopwatch_id = -1;
			try {
				$stopwatch_id = eCommerceUtils::startStopwatch(__METHOD__ . " - POST products/{$remote_id}/variations/batch");
				if (!self::$disable_put_post_to_api) $results = $this->client->post("products/$remote_id/variations/batch", $batch_data);
				eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
				dol_syslog(__METHOD__ . " - Send POST to API 'products/$remote_id/variations/batch' : Data: " . json_encode($batch_data), LOG_NOTICE);
			} catch (HttpClientException $fault) {
				eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
				$this->errors[] = $langs->trans('ECommerceWoocommerceCreateRemoteBatchProducts', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
				dol_syslog(__METHOD__ .
					': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceCreateRemoteBatchProducts', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
					' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
				$error++;
			}

			if (!$error && !empty($results)) {
				$created = isset($results->create) ? $results->create : array();
				foreach ($created as $key => $item) {
					if (isset($item->error)) {
						$error_msg = $langs->trans('ECommerceWoocommerceCreateRemoteBatchProduct', $batch_data[$key]['sku'], $this->site->name, $item->error->code . ': ' . $item->error->message . ' (data : ' . json_encode($item->error->data) . ' )');
						$this->errors[] = $error_msg;
						dol_syslog(__METHOD__ . ': Error: ' . $error_msg, LOG_ERR);
					} else {
						$product_success[] = array(
							'product_id' => $product_sku_id[$item->sku],
							'remote_id' => $item->id,
						);
					}
				}
				$updated = isset($results->update) ? $results->update : array();
				foreach ($updated as $key => $item) {
					if (isset($item->error)) {
						$error_msg = $langs->trans('ECommerceWoocommerceUpdateRemoteBatchProduct', $item->id, $this->site->name, $item->error->code . ': ' . $item->error->message . ' (data : ' . json_encode($item->error->data) . ' )');
						$this->errors[] = $error_msg;
						dol_syslog(__METHOD__ . ': Error: ' . $error_msg, LOG_ERR);
					} else {
						$product_success[] = array(
							'product_id' => $product_sku_id[$item->sku],
							'remote_id' => $item->id,
						);
					}
				}
			}
		}


		dol_syslog(__METHOD__ . ": end", LOG_DEBUG);
		return $product_success;

		$this->errors = array();
		$productSynchPrice = isset($this->site->parameters['product_synch_price']) ? $this->site->parameters['product_synch_price'] : 'regular';
        $productImageSynchDirection = isset($this->site->parameters['product_synch_direction']['image']) ? $this->site->parameters['product_synch_direction']['image'] : '';
        $productRefSynchDirection = isset($this->site->parameters['product_synch_direction']['ref']) ? $this->site->parameters['product_synch_direction']['ref'] : '';
        $productDescriptionSynchDirection = isset($this->site->parameters['product_synch_direction']['description']) ? $this->site->parameters['product_synch_direction']['description'] : '';
        $productShortDescriptionSynchDirection = isset($this->site->parameters['product_synch_direction']['short_description']) ? $this->site->parameters['product_synch_direction']['short_description'] : '';
        $productWeightSynchDirection = isset($this->site->parameters['product_synch_direction']['weight']) ? $this->site->parameters['product_synch_direction']['weight'] : '';
        $productTaxSynchDirection = isset($this->site->parameters['product_synch_direction']['tax']) ? $this->site->parameters['product_synch_direction']['tax'] : '';
        $productStatusSynchDirection = isset($this->site->parameters['product_synch_direction']['status']) ? $this->site->parameters['product_synch_direction']['status'] : '';

        require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
        $product_static = new Product($this->db);

        // Set datas to create
        $prod_sku_id = array();
        $products = array();
        $variations = array();
        foreach ($batch as $product_id => $batch_infos) {
            if ($product_static->fetch($product_id) > 0) {
                $sku = $product_static->ref;

                $isProductVariation = false;
                if ($batch_infos['type'] == 'update') {
                    $remote_product_id = $batch_infos['remote_id'];
                    $remote_product_variation_id = 0;
                    if (preg_match('/^(\d+)\|(\d+)$/', $batch_infos['remote_id'], $idsProduct) == 1) { // Variations
                        $isProductVariation = true;
                        $remote_product_id = $idsProduct[1];
                        $remote_product_variation_id = $idsProduct[2];
                    }
                }

				// Convert Weight
				$from_unit = $product_static->weight_units;
				$to_unit = isset($this->site->parameters['product_weight_units']) ? $this->site->parameters['product_weight_units'] : (empty($conf->global->MAIN_WEIGHT_DEFAULT_UNIT)?0:$conf->global->MAIN_WEIGHT_DEFAULT_UNIT);
				$totalWeight = weight_convert($product_static->weight, $from_unit, $to_unit);

                // Price
                $error_price = 0;
                if (!empty($conf->global->PRODUIT_MULTIPRICES)) {
                    $price_level = !empty($this->site->price_level) ? $this->site->price_level : 1;
                    if ($this->site->ecommerce_price_type == 'TTC') {
                        if ($product_static->multiprices_base_type[$price_level] == 'TTC') {
                            $price = $product_static->multiprices_ttc[$price_level];
                        } else {
                            $error_price++;
                        }
                    } else {
                        if ($product_static->multiprices_base_type[$price_level] == 'TTC') {
                            $error_price++;
                        } else {
                            $price = $product_static->multiprices[$price_level];
                        }
                    }
                } else {
                    if ($this->site->ecommerce_price_type == 'TTC') {
                        if ($product_static->price_base_type == 'TTC') {
                            $price = $product_static->price_ttc;
                        } else {
                            $error_price++;
                        }
                    } else {
                        if ($product_static->price_base_type == 'TTC') {
                            $error_price++;
                        } else {
                            $price = $product_static->price;
                        }
                    }
                }
                if ($error_price) {
                    $error_msg = $langs->trans('ECommerceWoocommerceErrorBaseTypeOfProductWithSiteParameter', $product_static->ref, $this->site->ecommerce_price_type, $this->site->name);
                    $this->errors[] = $error_msg;
                    dol_syslog(__METHOD__ . ': Error:' . $error_msg, LOG_ERR);
                    continue;
                }

                // images
                $images = [];
                if ($productImageSynchDirection == 'dtoe' || $productImageSynchDirection == 'all') {
                    // Get current images
                    $current_images = [];
					$stopwatch_id = -1;
					try {
                        if ($isProductVariation) { // Variations
							$stopwatch_id = eCommerceUtils::startStopwatch(__METHOD__ . " - GET products/{$remote_product_id}/variations/{$remote_product_variation_id}");
							$results = $this->client->get("products/$remote_product_id/variations/$remote_product_variation_id");
                        } else {
							$stopwatch_id = eCommerceUtils::startStopwatch(__METHOD__ . " - GET products/{$remote_product_id}");
							$results = $this->client->get("products/$remote_product_id");
                        }
						eCommerceUtils::stopAndLogStopwatch($stopwatch_id);

                        if (!empty($results)) {
                            if ($isProductVariation) {
                                if (isset($results->image)) {
                                    $current_images[$results->image->name] = $results->image->id;
                                }
                            } else {
                                if (is_array($results->images)) {
                                    foreach ($results->images as $image) {
                                        $current_images[$image->name] = $image->id;
                                    }
                                }
                            }
                        }
                    } catch (HttpClientException $fault) {
						eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
						$this->errors[] = $langs->trans('ECommerceWoocommerceUpdateRemoteProductGetRemoteProduct', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
                       dol_syslog(__METHOD__ .
                           ': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceUpdateRemoteProductGetRemoteProduct', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
                           ' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
                       continue;
                    }

                    // Product - Images properties
                    $entity = isset($product_static->entity) ? $product_static->entity : $conf->entity;
                    if (!empty($conf->global->PRODUCT_USE_OLD_PATH_FOR_PHOTO)) {    // For backward compatiblity, we scan also old dirs
                        if ($product_static->type == Product::TYPE_PRODUCT) {
                            $dir = $conf->product->multidir_output[$entity] . '/' . substr(substr("000" . $product_static->id, -2), 1, 1) . '/' . substr(substr("000" . $product_static->id, -2), 0, 1) . '/' . $product_static->id . "/photos/";
                        } else {
                            $dir = $conf->service->multidir_output[$entity] . '/' . substr(substr("000" . $product_static->id, -2), 1, 1) . '/' . substr(substr("000" . $product_static->id, -2), 0, 1) . '/' . $product_static->id . "/photos/";
                        }
                    } else {
						if (version_compare(DOL_VERSION, "13.0.0") >= 0) {
							if ($product_static->type == Product::TYPE_PRODUCT) {
								$dir = $conf->product->multidir_output[$entity] . '/' . get_exdir(0, 0, 0, 1, $product_static, 'product') . '/';
							} else {
								$dir = $conf->service->multidir_output[$entity] . '/' . get_exdir(0, 0, 0, 1, $product_static, 'product') . '/';
							}
						} else {
							if ($product_static->type == Product::TYPE_PRODUCT) {
								$dir = $conf->product->multidir_output[$entity] . '/' . get_exdir(0, 0, 0, 0, $product_static, 'product') . dol_sanitizeFileName($product_static->ref) . '/';
							} else {
								$dir = $conf->service->multidir_output[$entity] . '/' . get_exdir(0, 0, 0, 0, $product_static, 'product') . dol_sanitizeFileName($product_static->ref) . '/';
							}
						}
                    }
                    $photos = $product_static->liste_photos($dir);
                    foreach ($photos as $index => $photo) {
                        $img = [];

                        $filename = ecommerceng_wordpress_sanitize_file_name($photo['photo']);
                        if (!isset($current_images[$filename])) {
                            $result = $this->worpressclient->postmedia("media", $dir . $photo['photo'], [
                                'slug' => $product_static->id . '_' . $filename,
                                'ping_status' => 'closed',
                                'comment_status' => 'closed',
                            ]);

                            if ($result === null) {
                                $error_msg = $langs->trans($batch_infos['type'] == 'update' ? 'ECommerceWoocommerceUpdateRemoteProductSendImage' : 'ECommerceWoocommerceCreateRemoteProductSendImage', $batch_infos['type'] == 'update' ? $batch_infos['remote_id'] : $product_static->ref, $this->site->name, implode('; ', $this->worpressclient->errors));
                                $this->errors[] = $error_msg;
                                dol_syslog(__METHOD__ . ': Error:' . $error_msg, LOG_ERR);
                                continue;
                            } elseif (!empty($result['message'])) {
                                $error_msg = $langs->trans($batch_infos['type'] == 'update' ? 'ECommerceWoocommerceUpdateRemoteProductSendImage' : 'ECommerceWoocommerceCreateRemoteProductSendImage', $batch_infos['type'] == 'update' ? $batch_infos['remote_id'] : $product_static->ref, $this->site->name, $result['code'] . ' - ' . $result['message']);
                                $this->errors[] = $error_msg;
                                dol_syslog(__METHOD__ . ': Error:' . $error_msg, LOG_ERR);
                                continue;
                            }

                            $img['id'] = $result['id'];
                        } else {
                            $img['id'] = $current_images[$filename];
                        }

                        $img['name'] = $filename;
                        $img['position'] = $index;
                        $images[] = $img;

                        if ($isProductVariation) { // Get only one image for variation
                            break;
                        }
                    }
                }

                // Product - Meta data properties
                $product_static->fetch_optionals();

                /*
                // Product - Downloads properties
                $downloads = [
                    [
                        'name' => '',       // string     File name.
                        'file' => '',       // string     File URL.
                    ],
                ];

                // Product - Dimensions properties
                $dimensions = [
                    'length' => '',     // string   Product length (cm).
                    'width' => '',      // string   Product width (cm).
                    'height' => '',     // string   Product height (cm).
                ];

                // Product - Categories properties
                $categories = [
                    [
                        'id' => 0,      // integer  Category ID.
                    ],
                ];

                // Product - Tags properties
                $tags = [
                    [
                        'id' => 0,      // integer  Tag ID.
                    ],
                ];

                // Product - Images properties
                $images = [
                    [
                        'id' => 0,              // integer	Image ID. Not required
                        'src' => '',            // string	Image URL.
                        'name' => '',           // string	Image name.
                        'alt' => '',            // string	Image alternative text.
                        'position' => 0,        // integer	Image position. 0 means that the image is featured.
                    ],
                ];

                // Product - Attributes properties
                $attributes = [
                    [
                        'id' => 0,              // integer	Attribute ID. Not required
                        'name' => '',           // string	Attribute name.
                        'position' => 0,        // integer	Attribute position.
                        'visible' => false,     // boolean	Define if the attribute is visible on the “Additional information” tab in the product’s page. Default is false.
                        'variation' => false,   // boolean	Define if the attribute can be used as variation. Default is false.
                        'options' => [],        // array	List of available term names of the attribute.
                    ],
                ];

                // Product - Default attributes properties
                $default_attributes = [
                    'id' => 0,              // integer	Attribute ID. Not required
                    'name' => '',           // string	Attribute name.
                    'option' => '',         // string	Selected attribute term name.
                ];

                // Product - Meta data properties
                $meta_data = [
                    'key' => '', // string	Meta key.
                    'value' => '', // string	Meta value.
                ];
                */

                // Get categories
                $eCommerceCategory = new eCommerceCategory($this->db);
                $cat = new Categorie($this->db);
                $categories_list = $cat->containing($product_static->id, 'product');
                $categories = [];
                foreach ($categories_list as $category) {
                    if ($this->site->fk_cat_product != $category->id) {
                        $ret = $eCommerceCategory->fetchByFKCategory($category->id, $this->site->id);
                        if ($ret > 0) {
                            $categories[] = ['id' => $eCommerceCategory->remote_id];
                        }
                    }
                }

                $status = $product_static->array_options["options_ecommerceng_wc_status_{$this->site->id}_{$conf->entity}"];
                $description = $product_static->array_options["options_ecommerceng_description_{$conf->entity}"];
                $regular_price = $product_static->array_options["options_ecommerceng_wc_regular_price_{$this->site->id}_{$conf->entity}"];
                $sale_price = $product_static->array_options["options_ecommerceng_wc_sale_price_{$this->site->id}_{$conf->entity}"];
                $date_on_sale_from = $product_static->array_options["options_ecommerceng_wc_date_on_sale_from_{$this->site->id}_{$conf->entity}"];
                $date_on_sale_to = $product_static->array_options["options_ecommerceng_wc_date_on_sale_to_{$this->site->id}_{$conf->entity}"];

                // Product
                $productData = [
                    'name' => $product_static->label,                            // string		Product name.
                    //'slug'                  => '',			                            // string		Product slug.
                    //'type'                  => '',			                            // string		Product type. Options: simple, grouped, external and variable. Default is simple.
                    //'status' => (!empty($status) ? $status : 'publish'), //$product_static->status ? 'publish' : 'pending',	// string		Product status (post status). Options: draft, pending, private and publish. Default is publish.
                    //'featured'              => false,		                            // boolean		Featured product. Default is false.
                    //'catalog_visibility'    => '',                                      // string		Catalog visibility. Options: visible, catalog, search and hidden. Default is visible.
                    //'description' => (!empty($description) ? $description : $product_static->description),                    // string		Product description.
                    //'short_description' => $product_static->array_options["options_ecommerceng_short_description_{$conf->entity}"],                                      // string		Product short description.
                    //'sku' => $sku,                            // string		Unique identifier.
                    'regular_price' => $productSynchPrice == 'regular' ? $price : $regular_price,                          // string		Product regular price.
                    //'sale_price' => $productSynchPrice == 'selling' ? $price : $sale_price,                                      // string		Product sale price.
                    //'date_on_sale_from'     => '',                                      // date-time	Start date of sale price, in the site’s timezone.
                    //'date_on_sale_from_gmt' => '',                                      // date-time	Start date of sale price, as GMT.
                    //'date_on_sale_to'       => '',                                      // date-time	End date of sale price, in the site’s timezone.
                    //'date_on_sale_to_gmt'   => '',                                      // date-time	End date of sale price, in the site’s timezone.
                    //'virtual'               => $product_static->type == Product::TYPE_SERVICE,  // boolean		If the product is virtual. Default is false.
                    //'downloadable'          => false,                                   // boolean		If the product is downloadable. Default is false.
                    //'downloads'             => $downloads,                              // array		List of downloadable files. See Product - Downloads properties
                    //'download_limit'        => -1,                                      // integer		Number of times downloadable files can be downloaded after purchase. Default is -1.
                    //'download_expiry'       => -1,                                      // integer		Number of days until access to downloadable files expires. Default is -1.
                    //'external_url'          => '',                                      // string		Product external URL. Only for external products.
                    //'button_text'           => '',                                      // string		Product external button text. Only for external products.
                    //'tax_status' => 'none',                                  // string		Tax status. Options: taxable, shipping and none. Default is taxable.
                    //'tax_class'             => '',                                      // string		Tax class.
                    //'manage_stock'          => false,                                   // boolean		Stock management at product level. Default is false.
                    //'stock_quantity'        => $product_static->stock_reel,                     // integer		Stock quantity.
                    //'in_stock'              => $product_static->stock_reel > 0,                 // boolean		Controls whether or not the product is listed as “in stock” or “out of stock” on the frontend. Default is true.
                    //'backorders'            => '',                                      // string		If managing stock, this controls if backorders are allowed. Options: no, notify and yes. Default is no.
                    //'sold_individually'     => false,                                   // boolean		Allow one item to be bought in a single order. Default is false.
                    //'weight' => (!empty($totalWeight) ? $totalWeight : ''),                            // string		Product weight (kg).
                    //'dimensions'            => $dimensions,                             // object		Product dimensions. See Product - Dimensions properties
                    //'shipping_class'        => '',                                      // string		Shipping class slug.
                    //'reviews_allowed'       => true,                                    // boolean		Allow reviews. Default is true.
                    //'upsell_ids'            => [],                                      // array		List of up-sell products IDs.
                    //'cross_sell_ids'        => [],                                      // array		List of cross-sell products IDs.
                    //'parent_id'             => 0,                                       // integer		Product parent ID.
                    //'purchase_note'         => '',                                      // string		Optional note to send the customer after purchase.
                    'categories' => $categories,                             // array		List of categories. See Product - Categories properties
                    //'tags'                  => $tags,                                   // array		List of tags. See Product - Tags properties
                    //'images'                => $images,                                 // object		List of images. See Product - Images properties
                    //'attributes'            => $attributes,			                    // array		List of attributes. See Product - Attributes properties
                    //'default_attributes'    => $default_attributes,			            // array		Defaults variation attributes. See Product - Default attributes properties
                    //'menu_order'            => 0,			                            // integer		Menu order, used to custom sort products.
                    //'meta_data'             => $meta_data,                              // array		Meta data. See Product - Meta data properties
                ];

                if ($productImageSynchDirection == 'dtoe' || $productImageSynchDirection == 'all') {
                    $productData['images'] = $images;
                }
                if ($productRefSynchDirection == 'dtoe' || $productRefSynchDirection == 'all') {
                    $productData['sku'] = $sku;
                }
                if ($productDescriptionSynchDirection == 'dtoe' || $productDescriptionSynchDirection == 'all') {
                    $productData['description'] = (!empty($description) ? $description : $product_static->description);
                }
                if ($productShortDescriptionSynchDirection == 'dtoe' || $productShortDescriptionSynchDirection == 'all') {
                    $productData['short_description'] = $product_static->array_options["options_ecommerceng_short_description_{$conf->entity}"];
                }
                if ($productWeightSynchDirection == 'dtoe' || $productWeightSynchDirection == 'all') {
                    $productData['weight'] = (!empty($totalWeight) ? $totalWeight : '');
                }
                if ($productTaxSynchDirection == 'dtoe' || $productTaxSynchDirection == 'all') {
                    $productData['tax_status'] = 'none';

                    // Set tax
                    if (!empty($product_static->array_options["options_ecommerceng_tax_class_{$this->site->id}_{$conf->entity}"])) {
                        $productData['tax_status'] = 'taxable';
                        $productData['tax_class'] = $product_static->array_options["options_ecommerceng_tax_class_{$this->site->id}_{$conf->entity}"];
                    }
                }
                if ($productStatusSynchDirection == 'dtoe' || $productStatusSynchDirection == 'all') {
                    $productData['status'] = (!empty($status) ? $status : 'publish');
                }

                // Synch extrafields <=> metadatas
                if (!empty($product_static->array_options)) {
                    foreach ($product_static->array_options as $key => $value) {
                        $cr_key = substr($key, 8);
                        if (preg_match('/^ecommerceng_/', $cr_key)) continue;
                        $options_saved = $this->site->parameters['ef_crp']['product'][$cr_key];
                        if ($options_saved['activated']) {
                            $rm_key = $cr_key;
                            if (isset($options_saved['correspondences'])) $rm_key = $options_saved['correspondences'];
                            $productData['meta_data'][] = array('key' => $rm_key, 'value' => $value);
                        }
                    }
                }

                $productData['batch_type'] = $batch_infos['type'];
                if ($batch_infos['type'] == 'update') {
                    $productData['id'] = $remote_product_id;
                    if ($isProductVariation) { // Variations
                        $productData['id'] = $remote_product_variation_id;
                        unset($productData['name']);
                        if (isset($productData['status'])) unset($productData['status']);
                        if (isset($productData['short_description'])) unset($productData['short_description']);
                        unset($productData['categories']);
                        if ($productImageSynchDirection == 'dtoe' || $productImageSynchDirection == 'all') {
                            if (!empty($images)) {
                                $productData['image'] = $images[0];
                            }
                        }
                    }
                }

                $prod_sku_id[$sku] = $product_id;
                if ($isProductVariation) {
                    $variations[$remote_product_id][$product_id] = $productData;
                } else {
                    $products[$product_id] = $productData;
                }
            }
        }

        $prods_id_remote_id = array();
        $nb_max_by_request = empty($conf->global->ECOMMERCENG_MAXSIZE_BATCH) ? 100 : min($conf->global->ECOMMERCENG_MAXSIZE_BATCH, 100);

        // Create products on Woocommerce
        $requestGroups = $this->getRequestGroups($products, $nb_max_by_request);
        foreach ($requestGroups as $request) {
            $batch_datas = array();
            foreach ($request as $product_id => $productData) {
                $batch_type = $productData['batch_type'];
                unset($productData['batch_type']);
                $batch_datas[$batch_type][$product_id] = $productData;
            }

            $error = 0;

			$stopwatch_id = -1;
			try {
				$stopwatch_id = eCommerceUtils::startStopwatch(__METHOD__ . " - POST products/batch");
				if (!self::$disable_put_post_to_api) $results = $this->client->post("products/batch", $batch_datas);
				eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
				dol_syslog(__METHOD__ . " - Send POST to API 'products/batch' : Data: " . json_encode($batch_datas), LOG_NOTICE);
            } catch (HttpClientException $fault) {
				eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
				$this->errors[] = $langs->trans('ECommerceWoocommerceCreateRemoteBatchProducts', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
                dol_syslog(__METHOD__ .
                    ': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceCreateRemoteBatchProducts', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
                    ' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
                $error++;
            }

            $created = isset($results->create) ? $results->create : array();
            foreach ($created as $key => $item) {
                if (isset($item->error)) {
                    $error_msg = $langs->trans('ECommerceWoocommerceCreateRemoteBatchProduct', $request[$key]['sku'], $this->site->name,$item->error->code . ': ' . $item->error->message . ' (data : ' . json_encode($item->error->data) . ' )');
                    $this->errors[] = $error_msg;
                    dol_syslog(__METHOD__ . ': Error: ' . $error_msg, LOG_ERR);
                } else {
                    $prods_id_remote_id['create'][$prod_sku_id[$item->sku]] = $item->id;
                }
            }
            $updated = isset($results->update) ? $results->update : array();
            foreach ($updated as $key => $item) {
                if (isset($item->error)) {
                    $error_msg = $langs->trans('ECommerceWoocommerceUpdateRemoteBatchProduct', $request[$key]['sku'], $this->site->name,$item->error->code . ': ' . $item->error->message . ' (data : ' . json_encode($item->error->data) . ' )');
                    $this->errors[] = $error_msg;
                    dol_syslog(__METHOD__ . ': Error: ' . $error_msg, LOG_ERR);
                } else {
                    $prods_id_remote_id['update'][$prod_sku_id[$item->sku]] = $item->id;
                }
            }
            if ($error) {
                return $prods_id_remote_id;
            }
        }

        // Create variations on Woocommerce
        foreach ($variations as $remote_product_id => $product_variations) {
            $requestGroups = $this->getRequestGroups($product_variations, $nb_max_by_request);
            foreach ($requestGroups as $request) {
                $batch_datas = array();
                foreach ($request as $product_id => $variationData) {
                    $batch_type = $variationData['batch_type'];
                    unset($variationData['batch_type']);
                    $batch_datas[$batch_type][$product_id] = $variationData;
                }

                $error = 0;

				$stopwatch_id = -1;
				try {
					$stopwatch_id = eCommerceUtils::startStopwatch(__METHOD__ . " - POST products/{$remote_product_id}/variations/batch");
					if (!self::$disable_put_post_to_api) $results = $this->client->post("products/$remote_product_id/variations/batch", $batch_datas);
					eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
					dol_syslog(__METHOD__ . " - Send POST to API 'products/$remote_product_id/variations/batch' : Data: " . json_encode($batch_datas), LOG_NOTICE);
                } catch (HttpClientException $fault) {
					eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
					$this->errors[] = $langs->trans('ECommerceWoocommerceCreateRemoteBatchProducts', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
                    dol_syslog(__METHOD__ .
                        ': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceCreateRemoteBatchProducts', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
                        ' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
                    $error++;
                }

                $created = isset($results->create) ? $results->create : array();
                foreach ($created as $key => $item) {
                    if (isset($item->error)) {
                        $error_msg = $langs->trans('ECommerceWoocommerceCreateRemoteBatchProduct', $request[$key]['sku'], $this->site->name, $item->error->code . ': ' . $item->error->message . ' (data : ' . json_encode($item->error->data) . ' )');
                        $this->errors[] = $error_msg;
                        dol_syslog(__METHOD__ . ': Error: ' . $error_msg, LOG_ERR);
                    } else {
                        $prods_id_remote_id['create'][$prod_sku_id[$item->sku]] = $remote_product_id.'|'.$item->id;
                    }
                }
                $updated = isset($results->update) ? $results->update : array();
                foreach ($updated as $key => $item) {
                    if (isset($item->error)) {
                        $error_msg = $langs->trans('ECommerceWoocommerceUpdateRemoteBatchProduct', $request[$key]['sku'], $this->site->name, $item->error->code . ': ' . $item->error->message . ' (data : ' . json_encode($item->error->data) . ' )');
                        $this->errors[] = $error_msg;
                        dol_syslog(__METHOD__ . ': Error: ' . $error_msg, LOG_ERR);
                    } else {
                        $prods_id_remote_id['update'][$prod_sku_id[$item->sku]] = $remote_product_id.'|'.$item->id;
                    }
                }
                if ($error) {
                    return $prods_id_remote_id;
                }
            }
        }

        dol_syslog(__METHOD__ . ": end", LOG_DEBUG);
        return $prods_id_remote_id;
    }

    /**
     * Send a file for remote order
     *
     * @param   int         $order_remote_id        Id of order on remote ecommerce
     * @param   int         $company_remote_id      Id of company on remote ecommerce
     * @param   Object      $object                 Object (invoice or shipping)
     * @param   string      $file                   File path
     * @param   Translate   $outputlangs            Lang output object
     *
     * @return  bool
     */
    public function sendFileForCommande($order_remote_id, $company_remote_id, $object, $file, $outputlangs)
    {
        dol_syslog(__METHOD__ . ": Send file '$file' for remote order ID $order_remote_id for site ID {$this->site->id}", LOG_DEBUG);
        global $langs;

		$this->errors = array();
		// Send file to WordPress
        $result = $this->worpressclient->postmedia("media", $file, [
            'slug' => $order_remote_id . '_' . $object->element,
            'author' => $company_remote_id,
            'post' => $order_remote_id,
            'ping_status' => 'closed',
            'comment_status' => 'closed',
        ]);
        if ($result === null) {
            $this->errors[] = $langs->trans('ECommerceWoocommerceSendFileForCommandeInWordpress', $order_remote_id, $this->site->name, implode('; ', $this->worpressclient->errors));
            dol_syslog(__METHOD__ . ': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceSendFileForCommandeInWordpress',
                    $order_remote_id, $this->site->name, implode('; ', $this->worpressclient->errors)), LOG_ERR);
            return false;
        }

        // Set meta data in remote commande
        $commandeData = [
            'meta_data' => [
                [
                    'key' => 'file_for_' . $object->element.'_id',
                    'value' => $result['id'],
                ],
                [
                    'key' => 'file_for_' . $object->element.'_link',
                    'value' => $result['link'],
                ],
                [
                    'key' => 'file_for_' . $object->element.'_source_url',
                    'value' => $result['source_url'],
                ],
            ]
        ];
		$stopwatch_id = -1;
		try {
			$stopwatch_id = eCommerceUtils::startStopwatch(__METHOD__ . " - PUT orders/{$order_remote_id}");
            if (!self::$disable_put_post_to_api) $result = $this->client->put("orders/$order_remote_id", $commandeData);
			eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
		} catch (HttpClientException $fault) {
			eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
			$this->errors[] = $langs->trans('ECommerceWoocommerceSendFileForCommande', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
            dol_syslog(__METHOD__ .
                ': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceSendFileForCommande', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
                ' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
            return false;
        }

        dol_syslog(__METHOD__ . ": end", LOG_DEBUG);
        return true;
    }

	/**
	 * Retrieve Dolibarr taxes info from remote data
	 *
	 * @param 	array		$taxes_data		Info of the taxes
	 * @return  array|bool					Dolibarr taxes info
	 */
	private function getTaxesInfoFromRemoteData($taxes_data)
	{
		global $langs;
		$tva_tx = 0;
		$count = 0;

		$this->loadTaxes();

		foreach ($taxes_data as $data) {
			if (empty($data->total)) continue;
			$count++;
			if ($count > 1) break;

			$taxes_data = array_values($taxes_data);
			$tax_id = $taxes_data[0]->id;
			if (isset(self::$taxes_rates_cached[$tax_id])) {
				$tva_tx = self::$taxes_rates_cached[$tax_id]['rate'];
			} else {
				$this->errors[] = $langs->trans('ECommerceWooCommerceErrorTaxNotFound', $tax_id);
				return false;
			}
		}

		if ($count > 1) {
			$this->errors[] = $langs->trans('ECommerceWooCommerceErrorDontSupportMultiTaxes');
			return false;
		}

		return array(
			'tva_tx' => $tva_tx,
			'local_tax1_tx' => 0,
			'local_tax2_tx' => 0,
			'total_local_tax1' => 0,
			'total_local_tax2' => 0,
		);
	}

	/**
	 * Get tax info from tax class name
	 *
	 * @param  string   $tax_class      Tax class name
	 * @param  string   $tax_status     Tax status
	 *
	 * @return array                    Tax info
	 */
	private function getTaxInfoFromTaxClass($tax_class, $tax_status = 'taxable')
	{
		global $conf;

		$tax_rate = 0;
		$tax_class = '';

		// $tax_status => Tax status. Options: taxable, shipping and none. Default is taxable
		if ($tax_status != 'none') {
			$this->loadTaxes();
			$tax_class = !empty($tax_class) ? $tax_class : 'standard';
			$tax_rate = '';

			if (isset(self::$taxes_rates_by_class_cached[$tax_class])) {
				$tax_rate = self::$taxes_rates_by_class_cached[$tax_class]['rate'];
			}

			if ($tax_rate == '') {
				$tax_rate = !empty($conf->global->ECOMMERCE_WOOCOMMERCE_DEFAULT_TVA) ? $conf->global->ECOMMERCE_WOOCOMMERCE_DEFAULT_TVA : 0;
			}
		}

		return array(
			'tax_rate' => $tax_rate,
			'tax_class' => $tax_class,
		);
	}

	/**
	 * Get tax class name from tax rate
	 *
	 * @param  double   $tax_rate     Tax rate
	 *
	 * @return string                    Tax class name
	 */
	private function getTaxClassFromVatRate($tax_rate)
	{
		$tax_class = '';

		if ($tax_rate > 0) {
			$this->loadTaxes();
			$tax_rate = price2num($tax_rate, 'MU');

			if (isset(self::$taxes_classes_by_rate_cached[$tax_rate])) {
				$tax_class = self::$taxes_classes_by_rate_cached[$tax_rate];
			}
		}

		return $tax_class;
	}

	/**
	 * load taxes rates in cache
	 */
	public function loadTaxes()
	{
		global $conf;

		if (!isset(self::$taxes_classes_cached)) {
			dol_include_once('/ecommerceng/admin/class/data/eCommerceDict.class.php');
			self::$taxes_classes_cached = array();
			$eCommerceDict = new eCommerceDict($this->db, MAIN_DB_PREFIX . 'c_ecommerceng_tax_class');
			$taxes_classes = $eCommerceDict->search(['entity' => ['value' => $conf->entity], 'site_id' => ['value' => $this->site->id]]);
			foreach ($taxes_classes as $class) {
				self::$taxes_classes_cached[$class['code']] = array('code' => $class['code'], 'label' => $class['label']);
			}
		}

		if (!isset(self::$taxes_classes_by_rate_cached) || !isset(self::$taxes_rates_cached)) {
			dol_include_once('/ecommerceng/admin/class/data/eCommerceDict.class.php');
			$eCommerceDict = new eCommerceDict($this->db, MAIN_DB_PREFIX . 'c_ecommerceng_tax_rate');
			$taxes_rates = $eCommerceDict->search(['entity' => ['value' => $conf->entity], 'site_id' => ['value' => $this->site->id]]);

			if (!isset(self::$taxes_classes_by_rate_cached)) {
				self::$taxes_classes_by_rate_cached = array();
				foreach ($taxes_rates as $rate) {
					$tax_rate = price2num($rate['tax_rate'], 'MU');
					self::$taxes_classes_by_rate_cached[$tax_rate] = $rate['tax_class'];
				}
			}

			if (!isset(self::$taxes_rates_cached)) {
				self::$taxes_rates_cached = array();
				foreach ($taxes_rates as $rate) {
					self::$taxes_rates_cached[$rate['tax_id']] = array(
						'id' => $rate['tax_id'], 'country' => $rate['tax_country'], 'state' => $rate['tax_state'],
						'postcode' => $rate['tax_postcode'], 'city' => $rate['tax_city'], 'rate' => $rate['tax_rate'], 'name' => $rate['tax_name'],
						'priority' => $rate['tax_priority'], 'compound' => $rate['tax_compound'], 'shipping' => $rate['tax_shipping'],
						'order' => $rate['tax_order'], 'class' => $rate['tax_class']
					);
					if (!isset(self::$taxes_rates_by_class_cached[$rate['tax_class']]) || self::$taxes_rates_by_class_cached[$rate['tax_class']]['priority'] > $rate['tax_priority']) {
						self::$taxes_rates_by_class_cached[$rate['tax_class']] = array(
							'id' => $rate['tax_id'], 'country' => $rate['tax_country'], 'state' => $rate['tax_state'],
							'postcode' => $rate['tax_postcode'], 'city' => $rate['tax_city'], 'rate' => $rate['tax_rate'], 'name' => $rate['tax_name'],
							'priority' => $rate['tax_priority'], 'compound' => $rate['tax_compound'], 'shipping' => $rate['tax_shipping'],
							'order' => $rate['tax_order'], 'class' => $rate['tax_class']
						);
					}
				}
			}
		}
	}

	/**
	 * Update all Woocommerce tax classes in dict
	 *
	 * @return array|false    List of woocommerce tax class or false if error
	 */
	public function getAllWoocommerceTaxClass()
	{
		dol_syslog(__METHOD__ . ": Retrieve all Woocommerce tax classes", LOG_DEBUG);
		global $langs;

		$stopwatch_id = -1;
		try {
			$stopwatch_id = eCommerceUtils::startStopwatch(__METHOD__ . " - GET taxes/classes");
			$tax_classes = $this->client->get('taxes/classes');
			eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
		} catch (HttpClientException $fault) {
			eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
			$this->errors[] = $langs->trans('ECommerceWoocommerceGetAllWoocommerceTaxClass', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
			dol_syslog(__METHOD__ .
				': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceGetAllWoocommerceTaxClass', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
				' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
			return false;
		}

		$taxClassesTable = [];
		foreach ($tax_classes as $tax_class) {
			unset($tax_class->_links);
			$taxClassesTable[$tax_class->slug] = $tax_class;
		}

		dol_syslog(__METHOD__ . ": end, return: ".json_encode($taxClassesTable), LOG_DEBUG);
		return $taxClassesTable;
	}

	/**
	 * Update all Woocommerce tax classes in dict
	 *
	 * @return array|false    List of woocommerce tax class or false if error
	 */
	public function getAllWoocommerceTaxRate()
	{
		dol_syslog(__METHOD__ . ": Retrieve all Woocommerce tax classes", LOG_DEBUG);
		global $conf, $langs;

		$nb_max_by_request = empty($conf->global->ECOMMERCENG_MAXSIZE_MULTICALL) ? 100 : min($conf->global->ECOMMERCENG_MAXSIZE_MULTICALL, 100);

		$taxClassesTable = [];
		$idxPage = 0;
		do {
			$idxPage++;
			$stopwatch_id = -1;
			try {
				$stopwatch_id = eCommerceUtils::startStopwatch(__METHOD__ . " - GET taxes");
				$taxes = $this->client->get('taxes',
					[
						'page' => $idxPage,
						'per_page' => $nb_max_by_request,
					]
				);
				eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
			} catch (HttpClientException $fault) {
				eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
				$this->errors[] = $langs->trans('ECommerceWoocommerceGetWoocommerceTaxes', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
				dol_syslog(__METHOD__ .
					': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceGetWoocommerceTaxes', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
					' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
				return false;
			}

			foreach ($taxes as $tax) {
				unset($tax->_links);
				$taxClassesTable[$tax->id] = $tax;
			}
		} while (!empty($taxes));

		dol_syslog(__METHOD__ . ": end, return: ".json_encode($taxClassesTable), LOG_DEBUG);
		return $taxClassesTable;
	}

    /**
     * Get all payment gateways
     *
     * @return array|false    List of payment gateways or false if error
     */
    public function getAllPaymentGateways()
    {
        dol_syslog(__METHOD__ . ": Retrieve all Woocommerce payment gateways", LOG_DEBUG);
        global $langs;

		$stopwatch_id = -1;
		try {
			$stopwatch_id = eCommerceUtils::startStopwatch(__METHOD__ . " - GET payment_gateways");
			$payment_gateways = $this->client->get('payment_gateways');
			eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
		} catch (HttpClientException $fault) {
			eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
			$this->errors[] = $langs->trans('ECommerceWoocommerceGetAllWoocommercePaymentGateways', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
            dol_syslog(__METHOD__ .
                ': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceGetAllWoocommercePaymentGateways', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
                ' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
            return false;
        }

        $paymentGatewaysTable = [];
        foreach ($payment_gateways as $infos) {
            if ($infos->enabled) {
                $paymentGatewaysTable[$infos->id] = $infos->method_title . (!empty($infos->title) ? ' - ' . $infos->title : '');
            }
        }

        dol_syslog(__METHOD__ . ": end, return: ".json_encode($paymentGatewaysTable), LOG_DEBUG);
        return $paymentGatewaysTable;
    }

	/**
	 * Get all webhooks
	 *
	 * @return array|false    List of payment gateways or false if error
	 */
	public function getAllWebHooks()
	{
		dol_syslog(__METHOD__ . ": Retrieve all Woocommerce payment gateways", LOG_DEBUG);
		global $langs, $conf;

		$nb_max_by_request = empty($conf->global->ECOMMERCENG_MAXSIZE_MULTICALL) ? 100 : min($conf->global->ECOMMERCENG_MAXSIZE_MULTICALL, 100);

		require_once DOL_DOCUMENT_ROOT . '/includes/OAuth/bootstrap.php';
		$uriFactory = new \OAuth\Common\Http\Uri\UriFactory();
		$currentUri = $uriFactory->createFromAbsolute(dol_buildpath('/ecommerceng/webhooks.php', 2) . '?ecommerce_id=' . $this->site->id);
		$eCommerceSiteWebHooksUrl = $currentUri->getAbsoluteUri();

		$webhooks_list = [];
		$idxPage = 0;
		do {
			$idxPage++;
			$stopwatch_id = -1;
			try {
				$stopwatch_id = eCommerceUtils::startStopwatch(__METHOD__ . " - GET webhooks");
				$webhooks = $this->client->get('webhooks',
					[
						'page' => $idxPage,
						'per_page' => $nb_max_by_request,
					]
				);
				eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
			} catch (HttpClientException $fault) {
				eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
				$this->errors[] = $langs->trans('ECommerceWoocommerceErrorGetWoocommerceWebHooks', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
				dol_syslog(__METHOD__ .
					': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceErrorGetWoocommerceWebHooks', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
					' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
				return false;
			}

			foreach ($webhooks as $webhook) {
				if ($webhook->delivery_url == $eCommerceSiteWebHooksUrl) {
					$webhooks_list[$webhook->id] = array(
						'remote_id' => $webhook->id,
						'name' => $webhook->name,
						'status' => $webhook->status == 'active',
						'infos' => json_encode(array(
							'topic' => $webhook->topic,
							'resource' => $webhook->resource,
							'event' => $webhook->event,
						)),
					);
				}
			}
		} while (!empty($webhooks));

		dol_syslog(__METHOD__ . ": end, return: ".json_encode($webhooks_list), LOG_DEBUG);
		return $webhooks_list;
	}

	/**
	 * Replace 4bytes characters
	 * @see https://stackoverflow.com/questions/16496554/can-php-detect-4-byte-encoded-utf8-chars by cmbuckley
	 *
	 * @param 	string		$string			Text
	 * @param 	string		$replacement	Replacement text
	 * @return 	string    					Replaced text
	 */
	function replace4byte($string, $replacement = '')
	{
		return preg_replace('%(?:
			  \xF0[\x90-\xBF][\x80-\xBF]{2}      # planes 1-3
			| [\xF1-\xF3][\x80-\xBF]{3}          # planes 4-15
			| \xF4[\x80-\x8F][\x80-\xBF]{2}      # plane 16
		)%xs', $replacement, $string);
	}

    /**
     * Get request groups of ID for get datas of remotes objects.
     *
     * @param   array   $remoteObject       List of ids of remote objects
     * @param   int     $nb_max_by_request  Nb remote ID by request
     * @param   int     $toNb               Max nb
     * @return  array                       List of request groups of ID
     */
    private function getRequestGroups($remoteObject, $nb_max_by_request, $toNb=0)
    {
        //dol_syslog(__METHOD__ . ": Get request groups of ID: " . implode(', ', $remoteObject), LOG_DEBUG);

        $idx = 0;
        $request = [];
        $request_groups = [];

        if (isset($remoteObject)) {
            foreach ($remoteObject as $remote_object_id) {
                if ($toNb > 0 && $idx > $toNb) break;

                if (($idx++ % $nb_max_by_request) == 0) {
                    if (count($request)) $request_groups[] = $request;
                    $request = [];
                }

                $request[] = $remote_object_id;
            }
        }
        if (count($request)) $request_groups[] = $request;

        //dol_syslog(__METHOD__ . ": end", LOG_DEBUG);
        return $request_groups;
    }

	/**
	 * Get DateTime object in current timezone from gmt date time.
	 *
	 * @param   string   $datetime          GMT date time
	 *
	 * @return  DateTime                    DateTime in current Time Zone
	 */
	private function getDateTimeFromGMTDateTime($datetime)
	{
		//dol_syslog(__METHOD__ . ": Get DateTime object in current timezone from gmt date time: $datetime", LOG_DEBUG);

		$dt = new DateTime($datetime, $this->gmtTimeZone);
		$dt->setTimezone($this->currentTimeZone);

		//dol_syslog(__METHOD__ . ": end", LOG_DEBUG);
		return $dt;
	}

	/**
	 * Get DateTime object in current timezone from gmt date time.
	 *
	 * @param   string   $datetime          GMT date time
	 *
	 * @return  DateTime                    DateTime in current Time Zone
	 */
	private function getDateTimeToGMTDateTime($datetime)
	{
		//dol_syslog(__METHOD__ . ": Get DateTime object in current timezone from gmt date time: $datetime", LOG_DEBUG);

		$dt = new DateTime($datetime, $this->currentTimeZone);
		$dt->setTimezone($this->gmtTimeZone);

		//dol_syslog(__METHOD__ . ": end", LOG_DEBUG);
		return $dt;
	}

    public function __destruct()
    {
        ini_set("memory_limit", "528M");
    }
}
