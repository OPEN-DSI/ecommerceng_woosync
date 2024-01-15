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

dol_include_once('/ecommerceng/lib/eCommerce.lib.php');

dol_include_once('/ecommerceng/class/client/eCommerceClientWooCommerceApi.class.php');
dol_include_once('/ecommerceng/class/client/eCommerceClientWordpressApi.class.php');

require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';


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
     * Woocommerce client.
     *
     * @var eCommerceClientWooCommerceApi
     */
    public $client;
    /**
     * WordPress client.
     *
     * @var eCommerceClientWordpressApi
     */
	public $worpressclient;

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
	 * Error.
	 *
	 * @var string
	 */
	public $error;

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
	 * Language object by language code cached.
	 *
	 * @var Translate[]
	 */
	private static $languages_cached = array();
	/**
	 * Remote product data by id/variation id/language cached.
	 *
	 * @var array
	 */
	private $product_language_cached = array();

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
		$this->error = '';
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
        dol_syslog(__METHOD__ . " - site ID {$this->site->id}", LOG_DEBUG);
		$this->errors = array();

		$this->client = new eCommerceClientWooCommerceApi($this->db, $this->site->debug);
		$result = $this->client->connection($this->site);
		if ($result < 0) {
			$this->errors[] = $this->client->errorsToString();
			dol_syslog(__METHOD__ . ' - Error:' . $this->errorsToString(), LOG_ERR);
			return false;
		}

		$this->worpressclient = new eCommerceClientWordpressApi($this->db, $this->site->wordpress_debug);
        $result = $this->worpressclient->connection($this->site);
        if ($result < 0) {
			$this->errors[] = $this->worpressclient->errorsToString();
			dol_syslog(__METHOD__ . ' - Error:' . $this->errorsToString(), LOG_ERR);
			return false;
		}

        dol_syslog(__METHOD__ . " - end, ok", LOG_DEBUG);
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
		do {
			$filters =  [
				'page' => $idxPage++,
				'per_page' => $per_page,
				'orderby' => 'registered_date',
				'order' => 'desc',
				'role' => empty($this->site->parameters['customer_roles']) ? 'all' : $this->site->parameters['customer_roles'],
			];

			$page = $this->client->sendToApi(eCommerceClientApi::METHOD_GET, 'customers', [GuzzleHttp\RequestOptions::QUERY => $filters], false, $status_code);
			if (!isset($page) && ($idxPage == 2 || $status_code != 403)) {
				$this->errors[] = $langs->trans('ECommerceWoocommerceGetSocieteToUpdate', $this->site->name);
				$this->errors[] = $this->client->errorsToString();
				dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
				return false;
			}

			if (!isset($page)) $page = array();

            foreach ($page as $customer) {
                $id = $customer['id'];
                $date_customer = $this->getDateTimeFromGMTDateTime(/*!empty($customer['date_modified_gmt']) ? $customer['date_modified_gmt'] : */$customer['date_created_gmt']);
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
		} while (count($page) == $per_page);

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

		do {
        	$filters =  [
				'page' => $idxPage++,
				'filter' => $filter,
				'fields' => 'id,created_at,updated_at,variations',
			];

			$page = $this->client->sendToApi(eCommerceClientApi::METHOD_GET, 'products', [GuzzleHttp\RequestOptions::QUERY => $filters], false, $status_code);
			if (!isset($page) && ($idxPage == 2 || $status_code != 403)) {
				$this->errors[] = $langs->trans('ECommerceWoocommerceGetProductToUpdate', $this->site->name);
				$this->errors[] = $this->client->errorsToString();
				dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
				return false;
			}

			if (!isset($page) || !isset($page['products'])) $page['products'] = array();

            foreach ($page['products'] as $product) {
                $id = $product['id'];
                $update = false;
                $date_product = $this->getDateTimeFromGMTDateTime(!empty($product['updated_at']) ? $product['updated_at'] : $product['created_at']);
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
                foreach ($product['variations'] as $variation) {
                    $id = $product['id'] . '|' . $variation['id'];
                    $date_variation = $this->getDateTimeFromGMTDateTime(!empty($variation['updated_at']) ? $variation['updated_at'] : $variation['created_at']);
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
		} while (count($page['products']) == $per_page);

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

		do {
			$filters =  [
				'page' => $idxPage++,
				'filter' => $filter,
				'fields' => 'id,created_at,updated_at'
			];

			$page = $this->client->sendToApi(eCommerceClientApi::METHOD_GET, 'orders', [GuzzleHttp\RequestOptions::QUERY => $filters], false, $status_code);
			if (!isset($page) && ($idxPage == 2 || $status_code != 403)) {
				$this->errors[] = $langs->trans('ECommerceWoocommerceGetCommandeToUpdate', $this->site->name);
				$this->errors[] = $this->client->errorsToString();
				dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
				return false;
			}

			if (!isset($page) || !isset($page['orders'])) $page['orders'] = array();

            foreach ($page['orders'] as $order) {
                $id = $order['id'];
                $date_order = $this->getDateTimeFromGMTDateTime(!empty($order['updated_at']) ? $order['updated_at'] : $order['created_at']);
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
		} while (count($page['orders']) == $per_page);

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
		$from_date = isset($from_date) && !empty($from_date) ? dol_print_date($from_date, '%Y-%m-%dT%H:%M:%S') : null;
		$to_date = isset($to_date) && !empty($to_date) ? dol_print_date($to_date, '%Y-%m-%dT%H:%M:%S') : null;

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
		do {
			$filters['page'] = $idxPage++;

			$page = $this->client->sendToApi(eCommerceClientApi::METHOD_GET, 'customers', [GuzzleHttp\RequestOptions::QUERY => $filters], false, $status_code);
			if (!isset($page) && ($idxPage == 2 || $status_code != 403)) {
				$this->errors[] = $langs->trans('ECommerceWoocommerceConvertRemoteObjectIntoDolibarrSociete', $this->site->name);
				$this->errors[] = $this->client->errorsToString();
				dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
				return false;
			}

			if (!isset($page)) $page = array();

			foreach ($page as $company) {
				$company_data = $this->convertCustomerDataIntoProcessedData($company);
				if ($company_data === false) return false;
				$companies[] = $company_data;
				if ($toNb > 0 && ++$nbTotalRecords >= $toNb) break;
			}

			if ($toNb > 0 && $nbTotalRecords >= $toNb) break;
		} while (count($page) == $nb_max_by_request);

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

		$create_date = $this->getDateTimeFromGMTDateTime($remote_data['date_created_gmt']);
		$last_update = $this->getDateTimeFromGMTDateTime(/*!empty($remote_data['date_modified_gmt']) ? $remote_data['date_modified_gmt'] :*/ $remote_data['date_created_gmt']);

		// Global infos
		$item = [
			'create_date' => $create_date->getTimestamp(),
			'last_update' => $last_update->format('Y-m-d H:i:s'),
			'remote_id' => $remote_data['id'],
			'name_alias' => null,
			'email_key' => $remote_data['email'],
			'client' => 1,
			'vatnumber' => null,
			'note_private' => "Site: '{$this->site->name}' - ID: {$remote_data['id']}",
			'address' => $remote_data['billing']['address_1'] . (!empty($remote_data['billing']['address_1']) && !empty($remote_data['billing']['address_2']) ? "\n" : "") . $remote_data['billing']['address_2'],
			'zip' => $remote_data['billing']['postcode'],
			'town' => $remote_data['billing']['city'],
			'country_id' => getCountry($remote_data['billing']['country'], 3),
			'phone' => $remote_data['billing']['phone'],
			'default_lang' => $mysoc->default_lang,
			'remote_datas' => $remote_data,
			'extrafields' => [
				"ecommerceng_wc_role_{$this->site->id}_{$conf->entity}" => $langs->trans('ECommercengWoocommerceCompanyRole_' . $remote_data['role']),
			],
		];

		// Default language
		if ($item['country_id'] != $mysoc->country_id && !empty($conf->global->ECOMMERCENG_WOOCOMMERCE_DEFAULT_LANG_OTHER_COUNTRY)) {
			$item['default_lang'] = $conf->global->ECOMMERCENG_WOOCOMMERCE_DEFAULT_LANG_OTHER_COUNTRY;
		}

		// Meta datas
		if (!empty($conf->global->ECOMMERCENG_WOOCOMMERCE_VAT_NUMBER_META_NAME)) {
			foreach ($remote_data['meta_data'] as $data) {
				if ($data['key'] == $conf->global->ECOMMERCENG_WOOCOMMERCE_VAT_NUMBER_META_NAME) {
					$item['vatnumber'] = $data['value'];
					break;
				}
			}
		}

		// Company
		if (!empty($remote_data['billing']['company'])) {
			$item['type'] = 'company';
			$item['name'] = $remote_data['billing']['company'];
			$item['email'] = !empty($conf->global->ECOMMERCENG_WOOCOMMERCE_GET_EMAIL_ON_COMPANY) ? $remote_data['email'] : null;
		} // User
		else {
			if (!empty($remote_data['billing']['first_name']) || !empty($remote_data['billing']['last_name'])) {
				$firstname = $remote_data['billing']['first_name'];
				$lastname = $remote_data['billing']['last_name'];
			}else {
				$firstname = $remote_data['first_name'];
				$lastname = $remote_data['last_name'];
			}
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
			$item['email'] = $remote_data['email'];
		}

		// Get metadata
		$metas_data = array();
		if (is_array($remote_data['meta_data'])) {
			foreach ($remote_data['meta_data'] as $meta) {
				$metas_data[$meta['key']] = $meta;
			}
		}

		// Synchronize metadata to extra fields
		if (!empty($this->site->parameters['extra_fields']['societe']['activated']['mdt']) && !empty($metas_data)) {
			$correspondences = array();
			foreach ($this->site->parameters['extra_fields']['societe']['activated']['mdt'] as $key => $v) {
				$data_key = $this->site->parameters['extra_fields']['societe']['values']['mdt'][$key];
				if (!empty($data_key)) {
					$correspondences[$data_key] = $key;
				}
			}
			foreach ($metas_data as $meta) {
				if (isset($correspondences[$meta['key']])) {
					$item['extrafields'][$correspondences[$meta['key']]] = $meta['value'];
				}
			}
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
        dol_syslog(__METHOD__ . ": Get remote contacts ID: {$remoteCompany['id']} for site ID {$this->site->id}", LOG_DEBUG);
        global $conf, $langs;

		$this->errors = array();
		$contacts = [];
        $last_update = $this->getDateTimeFromGMTDateTime(/*!empty($remoteCompany['date_modified_gmt']) ? $remoteCompany['date_modified_gmt'] :*/ $remoteCompany['date_created_gmt']);

        $bContact = $remoteCompany['billing'];
        if (!empty($bContact['address_1']) || !empty($bContact['address_2']) || !empty($bContact['postcode']) ||
            !empty($bContact['city']) || !empty($bContact['country']) ||
            !empty($bContact['email']) || !empty($bContact['company']) || !empty($bContact['phone'])
        ) {
            $firstname = !empty($bContact['first_name']) ? $bContact['first_name'] : $remoteCompany['first_name'];
            $lastname = !empty($bContact['last_name']) ? $bContact['last_name'] : $remoteCompany['last_name'];
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
                'address' => $bContact['address_1'] . (!empty($bContact['address_1']) && !empty($bContact['address_2']) ? "\n" : "") . $bContact['address_2'],
                'zip' => $bContact['postcode'],
                'town' => $bContact['city'],
                'country_id' => getCountry($bContact['country'], 3),
                'email' => !empty($bContact['email']) ? $bContact['email'] : $remoteCompany['email'],
                'phone' => $bContact['phone'],
                'fax' => null,
            ];
        }

        $sContact = $remoteCompany['shipping'];
        if (!empty($sContact['address_1']) || !empty($sContact['address_2']) ||
            !empty($sContact['postcode']) || !empty($sContact['city']) ||
            !empty($sContact['country'])
        ) {
            if ($bContact['first_name'] != $sContact['first_name'] || $bContact['last_name'] != $sContact['last_name'] ||
                $bContact['address_1'] != $sContact['address_1'] || $bContact['address_2'] != $sContact['address_2'] ||
                $bContact['postcode'] != $sContact['postcode'] || $bContact['city'] != $sContact['city'] ||
                $bContact['country'] != $sContact['country']
            ) {
                $firstname = !empty($sContact['first_name']) ? $sContact['first_name'] : $remoteCompany['first_name'];
                $lastname = !empty($sContact['last_name']) ? $sContact['last_name'] : $remoteCompany['last_name'];
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
                    'address' => $sContact['address_1'] . (!empty($sContact['address_1']) && !empty($sContact['address_2']) ? "\n" : "") . $sContact['address_2'],
                    'zip' => $sContact['postcode'],
                    'town' => $sContact['city'],
                    'country_id' => getCountry($sContact['country'], 3),
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

        if (!empty($conf->global->ECOMMERCENG_DISABLED_PRODUCT_SYNCHRO_STOD)) {
        	return [];
		}

		$this->errors = array();
		$products = [];
        $nb_max_by_request = empty($conf->global->ECOMMERCENG_MAXSIZE_MULTICALL) ? 100 : min($conf->global->ECOMMERCENG_MAXSIZE_MULTICALL, 100);
		$from_date = isset($from_date) ? dol_print_date($from_date, '%Y-%m-%dT%H:%M:%S') : null;
		$to_date = isset($to_date) ? dol_print_date($to_date, '%Y-%m-%dT%H:%M:%S') : null;
		$product_variation_mode_all_to_one = !empty($this->site->parameters['product_variation_mode']) && $this->site->parameters['product_variation_mode'] == 'all_to_one';

		$one_product_id = '';
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
		if (!empty($include_ids)) {
			if (count($include_ids) == 1) {
				$one_product_id = '/' . array_values($include_ids)[0];
			} else {
				$filters['include'] = implode(',', $include_ids);
			}
		}
		else {
			if (isset($from_date)) $filters['after'] = $from_date;
			if (isset($to_date)) $filters['before'] = $to_date;
		}

		$idxPage = 1;
		$nbTotalRecords = 0;
		do {
			$filters['page'] = $idxPage++;

			$page = $this->client->sendToApi(eCommerceClientApi::METHOD_GET, 'products' . $one_product_id, [GuzzleHttp\RequestOptions::QUERY => $filters], false, $status_code);
			if (!isset($page) && ($idxPage == 2 || $status_code != 403)) {
				$this->errors[] = $langs->trans('ECommerceWoocommerceConvertRemoteObjectIntoDolibarrProduct', $this->site->name);
				$this->errors[] = $this->client->errorsToString();
				dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
				return false;
			}

			if (!isset($page)) $page = array();
			if (!empty($one_product_id) && !empty($page)) $page = [$page];

			foreach ($page as $product) {
				// Don't synchronize the variation parent
				if (empty($product['variations']) || !empty($product['parent_id'])) {
					$data = $this->convertProductDataIntoProcessedData($product);
					if (!is_array($data)) {
						$this->errors = array_merge(array($langs->trans('ECommerceErrorWhenConvertProductData', $product['id'])), $this->errors);
						return false;
					}
					$products[] = $data;
				}

				// Synchronize all the variations of the product if only the parent is provided
				if (!empty($product['variations']) && empty($include_variation_ids[$product['id']])) {
					$include_variation_ids[$product['id']] = $product['variations'];
				}

				// Variations
				if (!empty($include_variation_ids[$product['id']])) {
					if ($product_variation_mode_all_to_one) {
						$tmp = array_values($include_variation_ids[$product['id']]);
						$include_variation_ids[$product['id']] = array($tmp[0]);
					}

					$requestGroupsVariations = $this->getRequestGroups($include_variation_ids[$product['id']], $nb_max_by_request);
					foreach ($requestGroupsVariations as $requestVariations) {
						dol_syslog(__METHOD__ . ": Get " . count($requestVariations) . " products variations of remote product (ID:{$product['id']}): " . implode(', ', $requestVariations), LOG_DEBUG);
						$variation_filters =  [
							'per_page' => $nb_max_by_request,
							'include' => implode(',', $requestVariations),
						];

						$variations = $this->client->sendToApi(eCommerceClientApi::METHOD_GET, 'products/' . $product['id'] . '/variations', [GuzzleHttp\RequestOptions::QUERY => $variation_filters]);
						if (!isset($variations)) {
							$this->errors[] = $langs->trans('ECommerceWoocommerceConvertRemoteObjectIntoDolibarrProductVariations', $this->site->name);
							$this->errors[] = $this->client->errorsToString();
							dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
							return false;
						}

						if (is_array($variations)) {
							foreach ($variations as $variation) {
								$data = $this->convertProductDataIntoProcessedData($variation, $product);
								if (!is_array($data)) {
									$this->errors = array_merge(array($langs->trans('ECommerceErrorWhenConvertProductData', $product['id'] . '|' . $variation['id'])), $this->errors);
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
		} while (count($page) == $nb_max_by_request);

        dol_syslog(__METHOD__ . ": end, converted " . count($products) . " remote products", LOG_DEBUG);
        return $products;
    }

	/**
	 * Call Woocommerce API to check if the remote product exist.
	 *
	 * @param	string		$remote_id          Remote ID of a product
	 * @return	int								<0 if KO, =0 if NO, >0 if Yes
	 */
	public function checkRemoteProductExist($remote_id)
	{
		dol_syslog(__METHOD__ . ": Check remote products ID: {$remote_id} exist on site ID {$this->site->id}", LOG_DEBUG);
		global $langs;

		$this->errors = array();

		$ids = explode('|', $remote_id);
		$product_id = $ids[0];
		if (isset($ids[1])) {
			$product_variation_id = $ids[1];
		}

		$page = $this->client->sendToApi(eCommerceClientApi::METHOD_GET, 'products/' . $product_id . (!empty($product_variation_id) ? '/variations/'.$product_variation_id : ''), [], false, $status_code);
		if (!isset($page)) {
			if ($status_code == 404) {
				return 0;
			}

			$this->errors[] = $langs->trans('ECommerceWoocommerceCheckRemoteProductExist', $remote_id, $this->site->name);
			$this->errors[] = $this->client->errorsToString();
			dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
			return -1;
		}

		return 1;
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
		$isVariation = isset($parent_remote_data) || $remote_data['parent_id'] > 0;
		$parent_id = isset($parent_remote_data) ? $parent_remote_data['id'] : ($remote_data['parent_id'] > 0 ? $remote_data['parent_id'] : 0);
		if ($isVariation && empty($parent_remote_data) && !empty($parent_id)) {
			$parent_remote_data = $this->client->sendToApi(eCommerceClientApi::METHOD_GET, 'products/' . $parent_id);
			if (!isset($parent_remote_data)) {
				$this->errors[] = $langs->trans('ECommerceWoocommerceConvertRemoteObjectIntoDolibarrProduct', $this->site->name);
				$this->errors[] = $this->client->errorsToString();
				dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
				return false;
			}
		}

		// if the parent has no variations (ex: webhook of a variation transformed in a simple product before the webhook is precessed)
		if ($isVariation && empty($parent_remote_data['variations'])) {
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
		$productDimensionSynchDirection = isset($this->site->parameters['product_synch_direction']['dimension']) ? $this->site->parameters['product_synch_direction']['dimension'] : '';
		$productTaxSynchDirection = isset($this->site->parameters['product_synch_direction']['tax']) ? $this->site->parameters['product_synch_direction']['tax'] : '';
		$productStatusSynchDirection = isset($this->site->parameters['product_synch_direction']['status']) ? $this->site->parameters['product_synch_direction']['status'] : '';
		$productWeightUnits = isset($this->site->parameters['product_weight_units']) ? $this->site->parameters['product_weight_units'] : 0; // 0 = Kg
		$productDimensionUnits = isset($this->site->parameters['product_dimension_units']) ? $this->site->parameters['product_dimension_units'] : -2; // -2 = cm
		$product_variation_mode_all_to_one = !empty($this->site->parameters['product_variation_mode']) && $this->site->parameters['product_variation_mode'] == 'all_to_one';

		// Categories
		$categories = [];
		$parent_categories = is_array($parent_remote_data['categories']) ? $parent_remote_data['categories'] : array();
		$categories_data = is_array($remote_data['categories']) ? $remote_data['categories'] : array();
		$categories_data = array_merge($categories_data, $parent_categories);
		foreach ($categories_data as $category) {
			$categories[$category['id']] = $category['id'];
		}

		// Label
		$label = $remote_data['name'];
		if ($isVariation && (empty($label) || $product_variation_mode_all_to_one)) {
			if (empty($label)) $label = $parent_remote_data['name'];
			// Attributes of the variation
			if (is_array($remote_data['attributes']) && !$product_variation_mode_all_to_one) {
				foreach ($remote_data['attributes'] as $attribute) {
					$label .= ' - ' . $attribute['option'];
				}
			}
		}

		$last_update_product = $this->getDateTimeFromGMTDateTime(!empty($remote_data['date_modified_gmt']) ? $remote_data['date_modified_gmt'] : $remote_data['date_created_gmt']);
		if (isset($parent_remote_data)) {
			$parent_last_update_product = $this->getDateTimeFromGMTDateTime(!empty($parent_remote_data['date_modified_gmt']) ? $parent_remote_data['date_modified_gmt'] : $parent_remote_data['date_created_gmt']);
			if ($parent_last_update_product > $last_update_product) {
				$last_update_product = $parent_last_update_product;
			}
		}
		$last_update = $last_update_product->format('Y-m-d H:i:s');

		$price = $productSynchPrice == 'selling' ? $remote_data['price'] : $remote_data['regular_price'];
		if (!empty($remote_data['date_on_sale_from_gmt'])) {
			$date_on_sale_from = $this->getDateTimeFromGMTDateTime($remote_data['date_on_sale_from_gmt']);
			$date_on_sale_from = isset($date_on_sale_from) ? $date_on_sale_from->getTimestamp() : '';
		} else {
			$date_on_sale_from = '';
		}
		if (!empty($remote_data['date_on_sale_to_gmt'])) {
			$date_on_sale_to = $this->getDateTimeFromGMTDateTime($remote_data['date_on_sale_to_gmt']);
			$date_on_sale_to = isset($date_on_sale_to) ? $date_on_sale_to->getTimestamp() : '';
		} else {
			$date_on_sale_to = '';
		}

		// Manage the variations products removed
		$variations = array();
		if (!$product_variation_mode_all_to_one) {
			$variations_list = array();
			if (!empty($parent_remote_data['variations'])) {
				foreach ($parent_remote_data['variations'] as $v) {
					$variations_list[] = $parent_id . '|' . $v;
				}
				$variations = array(
					'parent_remote_id' => $parent_id,
					'filter' => $parent_id . '|%',
					'list' => $variations_list,
				);
			} elseif (!empty($remote_data['variations'])) {
				foreach ($remote_data['variations'] as $v) {
					$variations_list[] = $remote_data['id'] . '|' . $v;
				}
				$variations = array(
					'parent_remote_id' => $remote_data['id'],
					'filter' => $remote_data['id'] . '|%',
					'list' => $variations_list,
				);
			}
		}

		$remote_id = $remote_data['id'];
		$remote_parent_id = 0;
		if ($isVariation) {
			if ($product_variation_mode_all_to_one) {
				$remote_id = $parent_id . '|' . implode('|', $parent_remote_data['variations']);
				$remote_parent_id = $parent_id;
			} else {
				$remote_id = $parent_id . '|' . $remote_data['id'];
				$remote_parent_id = $parent_id;
			}
		} elseif (!empty($remote_data['variations']) && $product_variation_mode_all_to_one) {
			$remote_id = $remote_data['id'] . '|' . implode('|', $remote_data['variations']);
			$remote_parent_id = $remote_data['id'];
		}

		$translates = array();
		if (!empty($conf->global->MAIN_MULTILANGS) && !empty($this->site->parameters['enable_product_plugin_wpml_support'])) {
			$language_list = $this->site->getLanguages();
			foreach ($language_list as $remote_lang => $language) {
				if ($remote_lang == 'ec_none') continue;

				$found = false;
				if ($remote_lang == $remote_data['lang']) {
					$translated_label = $label;
					$translated_description = $this->replace4byte(empty($remote_data['description']) ? $parent_remote_data['description'] : $remote_data['description']);
					$found = true;
				} else {
					$translated_product_data = $this->getProductLanguage($isVariation ? $remote_parent_id : $remote_data['id'], $isVariation ? $remote_data['id'] : 0, $remote_lang);
					if (!is_array($translated_product_data)) {
						return false;
					} elseif (!empty($translated_product_data)) {
						$translated_label = $translated_product_data['name'];
						$translated_description = $this->replace4byte($translated_product_data['description']); // short_description
						$found = true;
					}
				}

				if ($found) {
					$translates[$language] = array(
						'label' => $translated_label,
						'description' => $translated_description,
					);
				}
			}
		}

		$product = [
			'create_date' => strtotime($remote_data['date_created']),
			'remote_id' => $remote_id,
			'remote_parent_id' => $remote_parent_id,
			'last_update' => $last_update,
			'fk_product_type' => ($remote_data['virtual'] ? 1 : 0), // 0 (product) or 1 (service)
			'status' => $remote_data['status'],
			'label' => $label,
			'price' => $price,
			'envente' => ($isVariation || $product_variation_mode_all_to_one || empty($remote_data['variations']) ? 1 : 0),
			'enachat' => null,
			'finished' => 1,    // 1 = manufactured, 0 = raw material
			'canvas' => $canvas,
			'remote_datas' => ($isVariation && isset($parent_remote_data) ? [ 'variation' => $remote_data, 'parent' => $parent_remote_data ] : $remote_data),
			'categories' => $categories,
			'price_min' => '',
			'fk_country' => '',
			'url' => $isVariation && $product_variation_mode_all_to_one ? $parent_remote_data['permalink'] : $remote_data['permalink'],
			// Stock
			'stock_qty' => $remote_data['stock_quantity'] < 0 ? 0 : $remote_data['stock_quantity'],
			'is_in_stock' => $remote_data['in_stock'],   // not used
			'language' => !empty($this->site->parameters['enable_product_plugin_wpml_support']) && isset($parent_remote_data) ? $remote_data['lang'] : '',
			'translates' => $translates,
			'variations' => $variations,
			'has_variations' => !empty($remote_data['variations']) || $remote_data['type'] == 'variable',
			'extrafields' => [
				"ecommerceng_wc_regular_price_{$this->site->id}_{$conf->entity}" => $remote_data['regular_price'],
				"ecommerceng_wc_sale_price_{$this->site->id}_{$conf->entity}" => $remote_data['sale_price'],
				"ecommerceng_wc_date_on_sale_from_{$this->site->id}_{$conf->entity}" => $date_on_sale_from,
				"ecommerceng_wc_date_on_sale_to_{$this->site->id}_{$conf->entity}" => $date_on_sale_to,
				"ecommerceng_wc_manage_stock_{$this->site->id}_{$conf->entity}" => !empty($remote_data['manage_stock']) ? 1 : 0,
				"ecommerceng_stockable_product" => $remote_data['type'] == 'woosb' ? 0 : 1,
			],
		];

		// Synchronize ref
		if ($productRefSynchDirection == 'etod' || $productRefSynchDirection == 'all') {
			$product['ref'] = $remote_data['sku'];
		}
		// Synchronize short and long description
		if ($productDescriptionSynchDirection == 'etod' || $productDescriptionSynchDirection == 'all') {
			$product['extrafields']["ecommerceng_description_{$conf->entity}"] = $this->replace4byte(empty($remote_data['description']) ? $parent_remote_data['description'] : $remote_data['description']);
		}
		if ($productShortDescriptionSynchDirection == 'etod' || $productShortDescriptionSynchDirection == 'all') {
			$product['extrafields']["ecommerceng_short_description_{$conf->entity}"] = $this->replace4byte(empty($remote_data['short_description']) ? $parent_remote_data['short_description'] : $remote_data['short_description']);
		}
		// Synchronize weight
		if ($productWeightSynchDirection == 'etod' || $productWeightSynchDirection == 'all') {
			$product['weight'] = empty($remote_data['weight']) ? $parent_remote_data['weight'] : $remote_data['weight'];
			$product['weight_units'] = $productWeightUnits;
		}
		// Synchronize weight
		if ($productDimensionSynchDirection == 'etod' || $productDimensionSynchDirection == 'all') {
			$product['width'] = empty($remote_data['dimensions']['width']) ? $parent_remote_data['dimensions']['width'] : $remote_data['dimensions']['width'];
			$product['width_units'] = $productDimensionUnits;
			$product['height'] = empty($remote_data['dimensions']['height']) ? $parent_remote_data['dimensions']['height'] : $remote_data['dimensions']['height'];
			$product['height_units'] = $productDimensionUnits;
			$product['length'] = empty($remote_data['dimensions']['length']) ? $parent_remote_data['dimensions']['length'] : $remote_data['dimensions']['length'];
			$product['length_units'] = $productDimensionUnits;
		}
		// Synchronize tax
		$tax_info = $this->getTaxInfoFromTaxClass(empty($remote_data['tax_class']) ? $parent_remote_data['tax_class'] : $remote_data['tax_class'], empty($remote_data['tax_status']) ? $parent_remote_data['tax_status'] : $remote_data['tax_status']);
		if (!$isVariation) $product['tax_rate'] = $tax_info['tax_rate'];
		if ($productTaxSynchDirection == 'etod' || $productTaxSynchDirection == 'all') {
			if ($isVariation) $product['tax_rate'] = $tax_info['tax_rate'];
			$product['extrafields']["ecommerceng_tax_class_{$this->site->id}_{$conf->entity}"] = $tax_info['tax_class'];
		}
		// Synchronize status
		if ($productStatusSynchDirection == 'etod' || $productStatusSynchDirection == 'all') {
			$product['extrafields']["ecommerceng_wc_status_{$this->site->id}_{$conf->entity}"] = empty($remote_data['status']) ? $parent_remote_data['status'] : $remote_data['status'];
		}
		// Synchronize images
		if ($productImageSynchDirection == 'etod' || $productImageSynchDirection == 'all') {
			$images = [];

			// Image of the product or the parent product if is a variation
			$images_data = $isVariation ? $parent_remote_data['images'] : $remote_data['images'];
			$images_data = is_array($images_data) ? $images_data : array();
			// Image of the variation
			if ($isVariation && !empty($remote_data['image'])) $images_data[] = $remote_data['image'];

			if (!empty($images_data)) {
				$media_url = $this->site->webservice_address . (substr($this->site->webservice_address, -1, 1) != '/' ? '/' : '') . 'wp-content/uploads/';

				foreach ($images_data as $image) {
					$last_update = $this->getDateTimeFromGMTDateTime(!empty($image['date_modified_gmt']) ? $image['date_modified_gmt'] : $image['date_created_gmt']);
					$image_url = $this->getCleanedRelativeUrl($media_url, $image['src']);
					$images[] = [
						'filename' => dol_sanitizeFileName($image_url),
						'url' => $image['src'],
						'date_modified' => $last_update->format('Y-m-d H:i:s'),
					];
				}
			}

			$product['images'] = $images;
		}

		// Get metadata
		$metas_data = array();
		if (!empty($this->site->parameters['extra_fields']['product']['activated']['mdt']) ||
			!empty($this->site->parameters['extra_fields']['product']['activated']['att']) ||
			$remote_data['type'] == 'woosb'
		) {
			if (is_array($remote_data['meta_data'])) {
				foreach ($remote_data['meta_data'] as $meta) {
					$metas_data[$meta['key']] = $meta;
				}
			}
			if ($isVariation && is_array($parent_remote_data['meta_data'])) {
				foreach ($parent_remote_data['meta_data'] as $meta) {
					if (!isset($metas_data[$meta['key']])) {
						$metas_data[$meta['key']] = $meta;
					}
				}
			}
		}

		// Synchronize metadata to extra fields
		if (!empty($this->site->parameters['extra_fields']['product']['activated']['mdt']) && !empty($metas_data)) {
			$correspondences = array();
			foreach ($this->site->parameters['extra_fields']['product']['activated']['mdt'] as $key => $v) {
				$data_key = $this->site->parameters['extra_fields']['product']['values']['mdt'][$key];
				if (!empty($data_key)) {
					$correspondences[$data_key] = $key;
				}
			}
			foreach ($metas_data as $meta) {
				if (isset($correspondences[$meta['key']])) {
					$product['extrafields'][$correspondences[$meta['key']]] = $meta['value'];
				}
			}
		}

		// Synchronize attribute to extra fields
		if (!empty($this->site->parameters['extra_fields']['product']['activated']['att'])) {
			$attributes = array();
			if (is_array($remote_data['attributes'])) {
				foreach ($remote_data['attributes'] as $attribute) {
					$attributes[$attribute['name']] = $attribute;
				}
			}
			if ($isVariation && is_array($parent_remote_data['attributes'])) {
				foreach ($parent_remote_data['attributes'] as $attribute) {
					if (!isset($attributes[$attribute['name']])) {
						$attributes[$attribute['name']] = $attribute;
					}
				}
			}

			if (!empty($attributes)) {
				$correspondences = array();
				foreach ($this->site->parameters['extra_fields']['product']['activated']['att'] as $key => $v) {
					$data_key = $this->site->parameters['extra_fields']['product']['values']['att'][$key];
					if (!empty($data_key)) {
						$correspondences[$data_key] = $key;
					}
				}
				foreach ($attributes as $attribute) {
					if (isset($correspondences[$attribute['id']])) {
						if ($isVariation) {
							$product['extrafields'][$correspondences[$attribute['id']]] = $attribute['option'];
						} else {
							$product['extrafields'][$correspondences[$attribute['id']]] = implode(',', $attribute['options']);
						}
					}
				}
			}
		}

		if (!empty($this->site->parameters['enable_warehouse_plugin_support'])) {
			$product_stock_by_warehouse = array();

			if ($this->site->parameters['enable_warehouse_plugin_support'] == 'wmlim') {
				if (is_array($remote_data['meta_data'])) {
					foreach ($remote_data['meta_data'] as $meta) {
						if (preg_match('/^wcmlim_stock_at_(\d+)$/', $meta['key'], $matches)) {
							$product_stock_by_warehouse[$matches[1]] = $meta['value'];
						}
					}
				}
			} else { // == slfw
				if (is_array($remote_data['locations'])) {
					foreach ($remote_data['locations'] as $info) {
						$product_stock_by_warehouse[$info['id']] = $info['quantity'];
					}
				}
			}

			$product['stock_by_warehouse'] = $product_stock_by_warehouse;
		}

		// Synchronize bundle to virtual product
		if ($remote_data['type'] == 'woosb' && !empty($metas_data['woosb_ids'])) {
//			$components = [];
//			$list = explode(',', $metas_data['woosb_ids']['value']);
//			foreach ($list as $item) {
//				$tmp = explode('/', $item);
//				$components[$tmp[0]] = $tmp[1];
//			}
//			$product['components'] = $components;
			$product['extrafields']["ecommerceng_wc_manage_stock_{$this->site->id}_{$conf->entity}"] = $metas_data['woosb_manage_stock'] == 'on' ? 1 : 0; // disable stock management
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
		$from_date = isset($from_date) ? dol_print_date($from_date, '%Y-%m-%dT%H:%M:%S') : null;
		$to_date = isset($to_date) ? dol_print_date($to_date, '%Y-%m-%dT%H:%M:%S') : null;

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
		do {
			$filters['page'] = $idxPage++;

			$page = $this->client->sendToApi(eCommerceClientApi::METHOD_GET, 'orders', [GuzzleHttp\RequestOptions::QUERY => $filters], false, $status_code);
			if (!isset($page) && ($idxPage == 2 || $status_code != 403)) {
				$this->errors[] = $langs->trans('ECommerceWoocommerceConvertRemoteObjectIntoDolibarrCommande', $this->site->name);
				$this->errors[] = $this->client->errorsToString();
				dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
				return false;
			}

			if (!isset($page)) $page = array();

			foreach ($page as $order) {
				$order_data = $this->convertOrderDataIntoProcessedData($order);
				if ($order_data === false) {
					$this->errors = array_merge(array($langs->trans('ECommerceErrorWhenConvertOrderData', $order['id'])), $this->errors);
					return false;
				}
				$orders[] = $order_data;
				if ($toNb > 0 && ++$nbTotalRecords >= $toNb) break;
			}

			if ($toNb > 0 && $nbTotalRecords >= $toNb) break;
		} while (count($page) == $nb_max_by_request);

		dol_syslog(__METHOD__ . ": end, converted " . count($orders) . " remote orders", LOG_DEBUG);
		return $orders;
	}

	/**
	 * Get laguage object cached for the specified language
	 *
	 * @param string $language Language code
	 * @return  Translate
	 */
	public function getLanguage($language)
	{
		global $conf;

		if (!isset(self::$languages_cached[$language])) {
			$outputlangs = new Translate("", $conf);
			$outputlangs->setDefaultLang($language);
			$outputlangs->loadLangs(array('main', 'ecommerce@ecommerceng', 'woocommerce@ecommerceng'));

			self::$languages_cached[$language] = $outputlangs;
		}

		return self::$languages_cached[$language];
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

		// Get provided taxes info
		$tax_list = array();
		if (!empty($remote_data['tax_lines'])) {
			foreach ($remote_data['tax_lines'] as $tax) {
				if (!empty($tax['rate_percent'])) $tax_list[$tax['rate_id']] = price2num($tax['rate_percent']);
			}
		}

		// WPML and multi-language support
		$outlangs = $langs;
		$order_language = '';
		$select_language = 'ec_none';
		if (!empty($conf->global->MAIN_MULTILANGS) && !empty($this->site->parameters['enable_product_plugin_wpml_support'])) {
			// Get metadata
			$metas_data = array();
			if (is_array($remote_data['meta_data'])) {
				foreach ($remote_data['meta_data'] as $meta) {
					$metas_data[$meta['key']] = $meta['value'];
				}
			}

			$language_list = $this->site->getLanguages();
			$order_language = !empty($metas_data['wpml_language']) ? $metas_data['wpml_language'] : '';
			if (empty($language_list[$order_language])) {
				$this->errors[] = $langs->trans('ECommerceWooCommerceErrorLanguageMatchNotFound', $order_language, $this->site->id);
				return false;
			}
			$select_language = $language_list[$order_language];
			if ($select_language != $outlangs->defaultlang) $outlangs = $this->getLanguage($select_language);
		}

		// Set product lines
		$items = [];
		if (!empty($remote_data['line_items'])) {
			$product_variation_mode_all_to_one = !empty($this->site->parameters['product_variation_mode']) && $this->site->parameters['product_variation_mode'] == 'all_to_one';
			$order_metadata_product_lines_to_description_etod = !empty($this->site->parameters['order_metadata_product_lines_to_description_etod']);
			$order_filter_mode_metadata_product_lines_to_description_etod = !empty($this->site->parameters['order_filter_mode_metadata_product_lines_to_description_etod']) ? $this->site->parameters['order_filter_mode_metadata_product_lines_to_description_etod'] : 'exclude';
			$order_filter_keys_metadata_product_lines_to_description_etod = !empty($this->site->parameters['order_filter_keys_metadata_product_lines_to_description_etod']) ? array_filter(array_map('trim', explode(',', (string)$this->site->parameters['order_filter_keys_metadata_product_lines_to_description_etod'])), 'strlen') : array();

			$bundles_ids = [];
			$parent_match = array();
			foreach ($remote_data['line_items'] as $item) {
				// Get metadata
				$metas_data = array();
				if (is_array($item['meta_data'])) {
					foreach ($item['meta_data'] as $meta) {
						$metas_data[$meta['key']] = $meta;
					}
				}

				$remote_id = !empty($item['variation_id']) ? (!$product_variation_mode_all_to_one ? $item['product_id'] . '|' . $item['variation_id'] : $item['product_id'] . '|%') : $item['product_id'];
				$label = $item['name'];
				$description = '';

				// WPML and multi-language support
				if (!empty($order_language)) {
					$product_link = new eCommerceProduct($this->db);
					$result = $product_link->fetchByRemoteId($remote_id, $this->site->id);
					if ($result < 0 && !empty($product_link->error)) {
						$this->errors[] = $product_link->error;
						return false;
					}

					if (empty($product_link->lang) || $product_link->lang != $order_language) {
						$product_data = $this->getProductLanguage($item['product_id'], $item['variation_id'], $order_language);
						if (!is_array($product_data)) {
							return false;
						} elseif (!empty($product_data)) {
							$label = $product_data['name'];
							$description = $this->replace4byte($product_data['description']); // short_description

							if (empty($label) || empty($description) && $item['variation_id'] > 0) {
								// Parent product
								$parent_product_data = $this->getProductLanguage($item['product_id'], 0, $order_language);
								if (!is_array($parent_product_data)) {
									return false;
								} elseif (!empty($parent_product_data)) {
									$label = empty($label) ? $product_data['name'] : $label;
									$description = empty($description) ? $product_data['description'] : $description; // short_description
								}
							}
						}
					}
				}

				// Set prices
				$price = $item['subtotal'] != $item['total'] ? ($item['subtotal'] / $item['quantity']) : $item['price'];
				$total_ht = $item['subtotal'];
				$total_tva = $item['subtotal_tax'];
				$total_ttc = $item['subtotal'] + $item['subtotal_tax'];

				// Support module bundle to virtual product
				$item_id = null;
				if (!empty($metas_data['_woosb_ids'])) {
					$bundles_ids[$item['product_id']] = $item['id'];
				}
				if (!empty($metas_data['_woosb_parent_id']) && isset($bundles_ids[$metas_data['_woosb_parent_id']['value']])) {
					$item_id = $bundles_ids[$metas_data['_woosb_parent_id']['value']];
					if (!isset($items[$item_id]['additional_description'])) $items[$item_id]['additional_description'] = $outlangs->transnoentitiesnoconv('ECommerceWooCommerceBundleComposite');
					$items[$item_id]['additional_description'] .= "\n - " . $item['quantity'] . ' x ' . $label;
				}

				// Support produits composés
				if (!empty($item['composite_children']) && is_array($item['composite_children'])) {
					foreach ($item['composite_children'] as $child_id) {
						$parent_match[$child_id] = $item['id'];
					}
				}

				$item_data = [
					'type' => 'product',
					'parent_item_id' => isset($item_id) ? $item_id : (isset($parent_match[$item['id']]) ? $parent_match[$item['id']] : 0),
					'item_id' => $item['id'],
					'ref' => $item['sku'],
					'label' => $label,
					'description' => $description,
					'id_remote_product' => $remote_id,
					'product_type' => 'simple',
					'price' => $price,
					'total_ht' => $total_ht,
					'total_tva' => $total_tva,
					'total_ttc' => $total_ttc,
					'qty' => $item['quantity'],
					'discount' => 0,
					'buy_price' => null,
				];

				// Support warehouse plugins for split movement stocks
				if (!empty($this->site->parameters['enable_warehouse_plugin_support']) && $this->site->parameters['enable_warehouse_plugin_support'] == 'wmlim') {
					$item_data['remote_warehouse_id'] = isset($metas_data['_selectedLocTermId']) ? $metas_data['_selectedLocTermId']['value'] : 0;
				}

				// Taxes
				$taxes = $this->getTaxesInfoFromRemoteData($item['taxes'], $tax_list);
				if ($taxes === false) return false;
				$item_data['tva_tx'] = $taxes['tva_tx'];
				$item_data['local_tax1_tx'] = $taxes['local_tax1_tx'];
				$item_data['local_tax2_tx'] = $taxes['local_tax2_tx'];
				$item_data['total_local_tax1'] = $taxes['total_local_tax1'];
				$item_data['total_local_tax2'] = $taxes['total_local_tax2'];

				if (isset($item['cog_item_cost'])) $item_data['buy_price'] = $this->site->ecommerce_price_type == 'TTC' ? 100 * $item['cog_item_cost'] / (100 + $item_data['tva_tx']) : $item['cog_item_cost'];
				if ($this->site->ecommerce_price_type == 'TTC') $item_data['price'] = (100 * $total_ttc / (100 + $item_data['tva_tx'])) / $item['quantity'];

				if (!empty($item['meta_data'])) {
					// Synch extrafields <=> metadatas
					if (!empty($this->site->parameters['extra_fields']['commandedet']['activated']['mdt'])) {
						$correspondences = array();
						foreach ($this->site->parameters['extra_fields']['commandedet']['activated']['mdt'] as $key => $v) {
							$data_key = $this->site->parameters['extra_fields']['commandedet']['values']['mdt'][$key];
							if (!empty($data_key)) {
								$correspondences[$data_key] = $key;
							}
						}
						foreach ($item['meta_data'] as $meta) {
							if (isset($correspondences[$meta['key']])) {
								$item_data['extrafields'][$correspondences[$meta['key']]] = $meta['value'];
							}
						}
					}
					// Add meta-data in description
					if ($order_metadata_product_lines_to_description_etod) {
						$metadata_in_description = array();
						foreach ($item['meta_data'] as $meta) {
							if (!empty($meta['display_key']) && !empty($meta['display_value']) && !is_array($meta['display_value']) && !is_object($meta['display_value']) && (
									($order_filter_mode_metadata_product_lines_to_description_etod == 'include' && in_array($meta['key'], $order_filter_keys_metadata_product_lines_to_description_etod)) ||
									($order_filter_mode_metadata_product_lines_to_description_etod == 'exclude' && !in_array($meta['key'], $order_filter_keys_metadata_product_lines_to_description_etod))
								)
							) {
								$metadata_in_description[] = $meta['display_key'] . ' : ' . $meta['display_value'];
							}
						}
						if (!empty($metadata_in_description)) $item_data['additional_description'] = implode('<br>', $metadata_in_description);
					}
				}

				$items[$item['id']] = $item_data;
			}
		}

		// Set shipping lines
		if (!empty($remote_data['shipping_lines'])) {
			$shipment_service_id = $this->site->parameters['shipping_service'] > 0 ? $this->site->parameters['shipping_service'] : 0;
			foreach ($remote_data['shipping_lines'] as $item) {
				$item_data = [
					'type' => 'shipping',
					'item_id' => $item['id'],
					'id_product' => $shipment_service_id,
					'label' => $outlangs->transnoentitiesnoconv('ECommerceShipping') . (!empty($item['method_title']) ? ' - ' . $item['method_title'] : ''),
					'description' => $outlangs->transnoentitiesnoconv('ECommerceShipping') . (!empty($item['method_title']) ? ' - ' . $item['method_title'] : ''),
					'product_type' => 'shipment',
					'price' => $item['total'],
					'total_ht' => $item['total'],
					'total_tva' => $item['total_tax'],
					'total_ttc' => ($item['total'] + $item['total_tax']),
					'qty' => 1,
					'discount' => 0,
				];

				// Taxes
				$taxes = $this->getTaxesInfoFromRemoteData($item['taxes'], $tax_list);
				if ($taxes === false) return false;
				$item_data['tva_tx'] = $taxes['tva_tx'];
				$item_data['local_tax1_tx'] = $taxes['local_tax1_tx'];
				$item_data['local_tax2_tx'] = $taxes['local_tax2_tx'];
				$item_data['total_local_tax1'] = $taxes['total_local_tax1'];
				$item_data['total_local_tax2'] = $taxes['total_local_tax2'];

				if ($this->site->ecommerce_price_type == 'TTC') $item_data['price'] = 100 * ($item['total'] + $item['total_tax']) / (100 + $item_data['tva_tx']);
				$item_data['buy_price'] = $item_data['price'];

				// Synch extrafields <=> metadatas
				if (!empty($item['meta_data']) && !empty($this->site->parameters['extra_fields']['commandedet']['activated']['mdt'])) {
					$correspondences = array();
					foreach ($this->site->parameters['extra_fields']['commandedet']['activated']['mdt'] as $key => $v) {
						$data_key = $this->site->parameters['extra_fields']['commandedet']['values']['mdt'][$key];
						if (!empty($data_key)) {
							$correspondences[$data_key] = $key;
						}
					}
					foreach ($item['meta_data'] as $meta) {
						if (isset($correspondences[$meta['key']])) {
							$item_data['extrafields'][$correspondences[$meta['key']]] = $meta['value'];
						}
					}
				}

				$items[] = $item_data;
			}
		}

		// Set discount code lines
		if (!empty($remote_data['coupon_lines'])) {
			$discount_code_service_id = $this->site->parameters['discount_code_service'] > 0 ? $this->site->parameters['discount_code_service'] : 0;
//			if (!($discount_code_service_id > 0)) {
//				$this->errors[] = $langs->trans('ECommerceWooCommerceErrorDiscountCodeServiceNotConfigured', $this->site->name);
//				return false;
//			}
			foreach ($remote_data['coupon_lines'] as $item) {
				$item_data = [
					'type' => 'discount',
					'item_id' => $item['id'],
					'id_product' => $discount_code_service_id,
					'label' => $item['code'],
					'description' => $item['code'],
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
				foreach ($remote_data['tax_lines'] as $data) {
					if (empty($data['tax_total'])) continue;
					if ($data['rate_percent'] > $tax_rate) $tax_rate = $data['rate_percent'];
				}

				$ttc = $item['discount'] + $item['discount_tax'];
				$tva = $tax_rate * $ttc / ($tax_rate + 100);
				$ht = 100 * $ttc / ($tax_rate + 100);

				$item_data['tva_tx'] = $tax_rate;
				$item_data['price'] = -$ht;
				$item_data['total_ht'] = -$ht;
				$item_data['total_tva'] = -$tva;
				$item_data['total_ttc'] = -$ttc;

				// Synch extrafields <=> metadatas
				if (!empty($item['meta_data']) && !empty($this->site->parameters['extra_fields']['commandedet']['activated']['mdt'])) {
					$correspondences = array();
					foreach ($this->site->parameters['extra_fields']['commandedet']['activated']['mdt'] as $key => $v) {
						$data_key = $this->site->parameters['extra_fields']['commandedet']['values']['mdt'][$key];
						if (!empty($data_key)) {
							$correspondences[$data_key] = $key;
						}
					}
					foreach ($item['meta_data'] as $meta) {
						if (isset($correspondences[$meta['key']])) {
							$item_data['extrafields'][$correspondences[$meta['key']]] = $meta['value'];
						}
					}
				}

				$items[] = $item_data;
			}
		}

		// Set gift card lines
		if (!empty($remote_data['pw_gift_cards_redeemed'])) {
			$gift_cards_service_id = $this->site->parameters['pw_gift_cards_service'] > 0 ? $this->site->parameters['pw_gift_cards_service'] : 0;
//			if (!($gift_cards_service_id > 0)) {
//				$this->errors[] = $langs->trans('ECommerceWooCommerceErrorPwGiftCardsServiceNotConfigured', $this->site->name);
//				return false;
//			}
			foreach ($remote_data['pw_gift_cards_redeemed'] as $gift_cards) {
				$items[] = [
					'type' => 'gift_card',
					'product_type' => 'pw_gift_cards',
					'id_product' => $gift_cards_service_id,
					'description' => $gift_cards['number'],
					'label' => $gift_cards['number'],
					'price' => - $gift_cards['amount'],
					'total_ht' => - $gift_cards['amount'],
					'total_tva' => 0,
					'total_ttc' => - $gift_cards['amount'],
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

		// Support store credits order (Advanced Coupons for WooCommerce)
		if (is_array($remote_data['meta_data'])) {
			$store_credits_service_label = $outlangs->transnoentitiesnoconv('ECommerceWooCommerceStoreCredit');
			$store_credits_service_id = $this->site->parameters['acfw_store_credits_service'] > 0 ? $this->site->parameters['acfw_store_credits_service'] : 0;
			foreach ($remote_data['meta_data'] as $meta) {
				if ($meta['key'] == 'acfw_store_credits_order_paid') {
					$items[] = [
						'type' => 'store_credits',
						'product_type' => 'acfw_store_credits',
						'id_product' => $store_credits_service_id,
						'description' => $store_credits_service_label,
						'label' => $store_credits_service_label,
						'price' => -$meta['value']['amount'],
						'total_ht' => -$meta['value']['amount'],
						'total_tva' => 0,
						'total_ttc' => -$meta['value']['amount'],
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
		}

		// Set fee lines
		$fee_lines = [];
		$fee_line_as_item_line = !empty($this->site->parameters['order_actions']['fee_line_as_item_line']);
		if (!empty($remote_data['fee_lines'])) {
			$fee_service_id = $this->site->parameters['fee_service'] > 0 ? $this->site->parameters['fee_service'] : 0;
			foreach ($remote_data['fee_lines'] as $fee_line) {
				$line = [
					'label' => $fee_line['name'],
					'price' => $fee_line['total'],
					'qty' => 1,
					'total_ht' => $fee_line['total'],
					'total_tva' => $fee_line['total_tax'],
					'total_ttc' => ($fee_line['total'] + $fee_line['total_tax']),
				];

				// Taxes
				$taxes = $this->getTaxesInfoFromRemoteData($fee_line['taxes'], $tax_list);
				if ($taxes === false) return false;
				$line['tva_tx'] = $taxes['tva_tx'];
				$line['local_tax1_tx'] = $taxes['local_tax1_tx'];
				$line['local_tax2_tx'] = $taxes['local_tax2_tx'];
				$line['total_local_tax1'] = $taxes['total_local_tax1'];
				$line['total_local_tax2'] = $taxes['total_local_tax2'];

				if ($fee_line_as_item_line) {
					$line['type'] = 'fee';
					$line['product_type'] = 'service';
					$line['id_product'] = $fee_service_id;
					$line['description'] = $fee_line['name'];
					$line['discount'] = 0;
					$line['buy_price'] = 0;

					$items[] = $line;
				} else {
					$fee_lines[] = $line;
				}
			}
		}
		// Manage fees in meta data (stripe payment, ...)
		if (!empty($remote_data['meta_data'])) {
			foreach ($remote_data['meta_data'] as $meta) {
				if ($meta['key'] == '_stripe_fee') {
					$fee_lines[] = [
						'label' => 'Stripe',
						'qty' => 1,
						'price' => $meta['value'],
						'total_ht' => $meta['value'],
						'total_tva' => 0,
						'total_ttc' => $meta['value'],
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

		// Set refunds lines
		$refunds = array();
		if (!empty($remote_data['refunds'])) {
			foreach ($remote_data['refunds'] as $item) {
				$remote_refund_data = $this->client->sendToApi(eCommerceClientApi::METHOD_GET, 'orders/' . $remote_data['id'] . '/refunds/' . $item['id']);
				if (!isset($remote_refund_data)) {
					$this->errors[] = $langs->trans('ECommerceWoocommerceErrorGetRemoteRefund', $item['id'], $this->site->name);
					$this->errors[] = $this->client->errorsToString();
					dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
					return false;
				}
				$item_data = $this->convertRefundDataIntoProcessedData($remote_refund_data);
				if ($item_data === false) {
					return false;
				}
				$refunds[$item['id']] = $item_data;
			}
		}

		$create_date = $this->getDateTimeFromGMTDateTime($remote_data['date_created_gmt']);
		$last_update = $this->getDateTimeFromGMTDateTime(!empty($remote_data['date_modified_gmt']) ? $remote_data['date_modified_gmt'] : $remote_data['date_created_gmt']);

		// Set billing's address
		$bContact = $remote_data['billing'];
		$firstname = $bContact['first_name'];
		$lastname = $bContact['last_name'];
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
			'company' => $bContact['company'],
			'firstname' => $firstname,
			'lastname' => $lastname,
			'address' => $bContact['address_1'] . (!empty($bContact['address_1']) && !empty($bContact['address_2']) ? "\n" : "") . $bContact['address_2'],
			'zip' => $bContact['postcode'],
			'town' => $bContact['city'],
			'country_id' => getCountry($bContact['country'], 3),
			'email' => $bContact['email'],
			'phone' => $bContact['phone'],
			'fax' => null,
		];

		// Set invoice's address
		$contactInvoice = $contactBilling;
		$contactInvoice['type'] = 1; //eCommerceSocpeople::CONTACT_TYPE_INVOICE;

		// Set shipping's address
		$sContact = $remote_data['shipping'];
		if ((!empty($sContact['first_name']) || !empty($sContact['last_name'])) &&
			(!empty($sContact['address_1']) || !empty($sContact['address_2'])) &&
			!empty($sContact['postcode']) && !empty($sContact['city'])
		) {
			if ($bContact['first_name'] != $sContact['first_name'] || $bContact['last_name'] != $sContact['last_name'] ||
				$bContact['address_1'] != $sContact['address_1'] || $bContact['address_2'] != $sContact['address_2'] ||
				$bContact['postcode'] != $sContact['postcode'] || $bContact['city'] != $sContact['city'] ||
				$bContact['country'] != $sContact['country']
			) {
				$firstname = $sContact['first_name'];
				$lastname = $sContact['last_name'];
				if (!empty($conf->global->ECOMMERCENG_UPPERCASE_LASTNAME)) {
					$firstname = dol_ucwords(dol_strtolower($firstname));
					$lastname = dol_strtoupper($lastname);
				}
				if (!empty($firstname) && empty($lastname)) {
					$lastname = $langs->transnoentitiesnoconv("ECommerceLastNameNotInformed");
				} elseif (empty($firstname) && empty($lastname)) {
					$lastname = $langs->transnoentitiesnoconv('ECommerceFirstNameLastNameNotInformed');
				}
				$email = null;
				if (!empty($remote_data['meta_data'])) {
					foreach ($remote_data['meta_data'] as $meta) {
						if ($meta['key'] == '_shipping_email') {
							$email = $meta['value'];
							break;
						}
					}
				}
				$contactShipping = [
					'remote_id' => "",
					'type' => 1, //eCommerceSocpeople::CONTACT_TYPE_DELIVERY,
					'last_update' => $last_update->format('Y-m-d H:i:s'),
					'company' => $sContact['company'],
					'firstname' => $firstname,
					'lastname' => $lastname,
					'address' => $sContact['address_1'] . (!empty($sContact['address_1']) && !empty($sContact['address_2']) ? "\n" : "") . $sContact['address_2'],
					'zip' => $sContact['postcode'],
					'town' => $sContact['city'],
					'country_id' => getCountry($sContact['country'], 3),
					'email' => $email,
					'phone' => isset($sContact['phone']) ? $sContact['phone'] : null,
					'fax' => null,
				];

				if (empty($sContact['company'])) {
					if (!empty($firstname) && !empty($lastname)) {
						$name = dolGetFirstLastname($firstname, $lastname);
					} elseif (!empty($firstname)) {
						$name = dolGetFirstLastname($firstname, $langs->transnoentitiesnoconv("ECommerceLastNameNotInformed"));
					} else {
						$name = $langs->transnoentitiesnoconv('ECommerceFirstNameLastNameNotInformed');
					}
					$contactShipping['company_name'] = $name;
				} else {
					$contactShipping['company_name'] = $sContact['company'];
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
		// $remote_data['status'] is: 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed', 'trash'
		$status = '';
		if (isset($this->site->parameters['order_status_etod'][$remote_data['status']]))
			$status = substr($this->site->parameters['order_status_etod'][$remote_data['status']]['selected'], 1);
		if ($status == '') {
			dol_syslog(__METHOD__ . ": Status \"{$remote_data['status']}\" was not found for remote order ID {$remote_data['id']} and set in draft", LOG_ERR);
//			$status = Commande::STATUS_DRAFT;   // draft by default
			$this->errors[] = $langs->trans('ECommerceWooCommerceErrorOrderStatusNotConfigured', $remote_data['status'], $this->site->name);
			return false;
		}

		// Set dolibarr billed status (payed or not)
		$billed = -1;   // unknown
		if (isset($this->site->parameters['order_status_etod'][$remote_data['status']]))
			$billed = $this->site->parameters['order_status_etod'][$remote_data['status']]['billed'];
		// Note: with processing, billed can be 0 or 1, so we keep -1

		// Dont synchro order
		$synchronize = 1;
		if (isset($this->site->parameters['order_status_etod'][$remote_data['status']]))
			$synchronize = $this->site->parameters['order_status_etod'][$remote_data['status']]['synchronize'];

		$orderStatus = '';
		require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
		$efields = new ExtraFields($this->db);
		$efields->fetch_name_optionals_label('commande', true);
		if (version_compare(DOL_VERSION, "13.0.0") >= 0) {
			$options_list = isset($efields->attributes['commande']['param']["ecommerceng_wc_status_{$this->site->id}_{$conf->entity}"]['options']) ? $efields->attributes['commande']['param']["ecommerceng_wc_status_{$this->site->id}_{$conf->entity}"]['options'] : null;
		} else {
			$options_list = isset($efields->attribute_param["ecommerceng_wc_status_{$this->site->id}_{$conf->entity}"]['options']) ? $efields->attribute_param["ecommerceng_wc_status_{$this->site->id}_{$conf->entity}"]['options'] : null;
		}
		if (is_array($options_list)) {
			foreach ($options_list as $key => $value) {
				$key_test = ($pos = strpos($key, '_')) > 0 ? substr($key, $pos + 1) : $key;
				if ($key_test == $remote_data['status']) {
					$orderStatus = $key;
					break;
				}
			}
		}

		// Add order content to array or orders
		$order = [
			'create_date' => $create_date->getTimestamp(),
			'last_update' => $last_update->format('Y-m-d H:i:s'),
			'remote_id' => $remote_data['id'],
			'remote_increment_id' => $remote_data['id'],
			'remote_id_societe' => $remote_data['customer_id'],
			'ref_client' => $remote_data['number'],
			'language' => $select_language,
			'date_commande' => $remote_data['date_created'],
			'date_payment' => $remote_data['date_paid'],
			'date_livraison' => $remote_data['date_completed'],
			'total_ht' => $remote_data['total'] - $remote_data['total_tax'],
			'total_tva' => $remote_data['total_tax'],
			'total_ttc' => $remote_data['total'],
			'items' => $items,
			'note' => $this->replace4byte($remote_data['customer_note']),
			'socpeopleCommande' => $contactBilling,
			'socpeopleFacture' => $contactInvoice,
			'socpeopleLivraison' => $contactShipping,
			'status' => $status,                         // dolibarr status
			'billed' => $billed,
			'synchronize' => !empty($synchronize),
			'remote_state' => $remote_data['status'],        // remote state, for information only (less accurate than status)
			'remote_status' => $remote_data['status'],      // remote status, for information only (more accurate than state)
			'remote_order' => $remote_data,
			'payment_method' => $remote_data['payment_method_title'],
			'payment_method_id' => $remote_data['payment_method'],
			'payment_amount_ttc' => $remote_data['total'],
			'fee_lines' => $fee_lines,
			'refunds' => $refunds,
			'extrafields' => [
				"ecommerceng_online_payment_{$conf->entity}" => empty($remote_data['transaction_id']) ? 0 : 1,
				"ecommerceng_wc_status_{$this->site->id}_{$conf->entity}" => $orderStatus,
				"ecommerceng_wc_link_{$this->site->id}_{$conf->entity}" => rtrim($this->site->webservice_address, '/') . '/wp-admin/post.php?action=edit&post=' . $remote_data['id'],
			],
		];

		// Synch extrafields <=> metadatas
		if (!empty($remote_data['meta_data']) && !empty($this->site->parameters['extra_fields']['commande']['activated']['mdt'])) {
			$correspondences = array();
			foreach ($this->site->parameters['extra_fields']['commande']['activated']['mdt'] as $key => $v) {
				$data_key = $this->site->parameters['extra_fields']['commande']['values']['mdt'][$key];
				if (!empty($data_key)) {
					$correspondences[$data_key] = $key;
				}
			}
			foreach ($remote_data['meta_data'] as $meta) {
				if (isset($correspondences[$meta['key']])) {
					$extrafield_value = $meta['value'];
					$extrafield_key = $correspondences[$meta['key']];
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
		if (!empty($remote_data['meta_data'])) {
			foreach ($remote_data['meta_data'] as $meta) {
				if ($meta['key'] == '_payplug_metadata') {
					$order['payment_amount_ttc'] = $meta['value']['amount'] / 100;
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
	 * Call Woocommerce API to get order datas and put into dolibarr order class.
	 *
	 * @param	array			$remote_data	Remote data
	 * @return  array|bool						FALSE if KO otherwise data processed.
	 */
	public function convertRefundDataIntoProcessedData($remote_data)
	{
		dol_syslog(__METHOD__ . " remote_data=" . json_encode($remote_data), LOG_DEBUG);
		global $langs;

		$this->errors = array();

		// Get provided taxes info
		$tax_list = array();
		if (!empty($remote_data['tax_lines'])) {
			foreach ($remote_data['tax_lines'] as $tax) {
				if (!empty($tax['rate_percent'])) $tax_list[$tax['rate_id']] = price2num($tax['rate_percent']);
			}
		}

		// WPML and multi-language support
		$outlangs = $langs;
		$order_language = '';
		$select_language = 'ec_none';
		if (!empty($conf->global->MAIN_MULTILANGS) && !empty($this->site->parameters['enable_product_plugin_wpml_support'])) {
			// Get metadata
			$metas_data = array();
			if (is_array($remote_data['meta_data'])) {
				foreach ($remote_data['meta_data'] as $meta) {
					$metas_data[$meta['key']] = $meta['value'];
				}
			}

			$language_list = $this->site->getLanguages();
			$order_language = !empty($metas_data['wpml_language']) ? $metas_data['wpml_language'] : '';
			if (empty($language_list[$order_language])) {
				$this->errors[] = $langs->trans('ECommerceWooCommerceErrorLanguageMatchNotFound', $order_language, $this->site->id);
				return false;
			}
			$select_language = $language_list[$order_language];
			if ($select_language != $outlangs->defaultlang) $outlangs = $this->getLanguage($select_language);
		}

		$sum_total_ht = 0;

		// Set product lines
		$items = [];
		if (!empty($remote_data['line_items'])) {
			$product_variation_mode_all_to_one = !empty($this->site->parameters['product_variation_mode']) && $this->site->parameters['product_variation_mode'] == 'all_to_one';

			$bundles_ids = [];
			$parent_match = array();
			foreach ($remote_data['line_items'] as $item) {
				// Get metadata
				$metas_data = array();
				if (is_array($item['meta_data'])) {
					foreach ($item['meta_data'] as $meta) {
						$metas_data[$meta['key']] = $meta;
					}
				}

				$remote_id = !empty($item['variation_id']) ? (!$product_variation_mode_all_to_one ? $item['product_id'] . '|' . $item['variation_id'] : $item['product_id'] . '|%') : $item['product_id'];
				$label = $item['name'];
				$description = '';

				// WPML and multi-language support
				if (!empty($order_language)) {
					$product_link = new eCommerceProduct($this->db);
					$result = $product_link->fetchByRemoteId($remote_id, $this->site->id);
					if ($result < 0 && !empty($product_link->error)) {
						$this->errors[] = $product_link->error;
						return false;
					}

					if (empty($product_link->lang) || $product_link->lang != $order_language) {
						$product_data = $this->getProductLanguage($item['product_id'], $item['variation_id'], $order_language);
						if (!is_array($product_data)) {
							return false;
						} elseif (!empty($product_data)) {
							$label = $product_data['name'];
							$description = $this->replace4byte($product_data['description']); // short_description

							if (empty($label) || empty($description) && $item['variation_id'] > 0) {
								// Parent product
								$parent_product_data = $this->getProductLanguage($item['product_id'], 0, $order_language);
								if (!is_array($parent_product_data)) {
									return false;
								} elseif (!empty($parent_product_data)) {
									$label = empty($label) ? $product_data['name'] : $label;
									$description = empty($description) ? $product_data['description'] : $description; // short_description
								}
							}
						}
					}
				}

				$item_qty = abs($item['quantity']);
				if (empty($item_qty)) $item_qty = 1;

				// Set prices
				$price = $item['subtotal'] != $item['total'] ? ($item['subtotal'] / $item_qty) : $item['price'];
				$total_ht = $item['subtotal'];
				$total_tva = $item['subtotal_tax'];
				$total_ttc = $item['subtotal'] + $item['subtotal_tax'];

				// Support module bundle to virtual product
				$item_id = null;
				if (!empty($metas_data['_woosb_ids'])) {
					$bundles_ids[$item['product_id']] = $item['id'];
				}
				if (!empty($metas_data['_woosb_parent_id']) && isset($bundles_ids[$metas_data['_woosb_parent_id']['value']])) {
					$item_id = $bundles_ids[$metas_data['_woosb_parent_id']['value']];
					if (!isset($items[$item_id]['additional_description'])) $items[$item_id]['additional_description'] = $outlangs->transnoentitiesnoconv('ECommerceWooCommerceBundleComposite');
					$items[$item_id]['additional_description'] .= "\n - " . $item_qty . ' x ' . $label;
				}

				// Support produits composés
				if (!empty($item['composite_children']) && is_array($item['composite_children'])) {
					foreach ($item['composite_children'] as $child_id) {
						$parent_match[$child_id] = $item['id'];
					}
				}

				$item_data = [
					'refund_item_id' => $metas_data['_refunded_item_id']['value'],
					'parent_item_id' => isset($item_id) ? $item_id : (isset($parent_match[$item['id']]) ? $parent_match[$item['id']] : 0),
					'item_id' => $item['id'],
					'ref' => $item['sku'],
					'label' => $label,
					'description' => $description,
					'id_remote_product' => $remote_id,
					'product_type' => 'simple',
					'price' => $price,
					'total_ht' => $total_ht,
					'total_tva' => $total_tva,
					'total_ttc' => $total_ttc,
					'qty' => $item_qty,
					'discount' => 0,
					'buy_price' => null,
				];

				// Support warehouse plugins for split movement stocks
				if (!empty($this->site->parameters['enable_warehouse_plugin_support']) && $this->site->parameters['enable_warehouse_plugin_support'] == 'wmlim') {
					$item_data['remote_warehouse_id'] = isset($metas_data['_selectedLocTermId']) ? $metas_data['_selectedLocTermId']['value'] : 0;
				}

				// Taxes
				$taxes = $this->getTaxesInfoFromRemoteData($item['taxes'], $tax_list);
				if ($taxes === false) return false;
				$item_data['tva_tx'] = $taxes['tva_tx'];
				$item_data['local_tax1_tx'] = $taxes['local_tax1_tx'];
				$item_data['local_tax2_tx'] = $taxes['local_tax2_tx'];
				$item_data['total_local_tax1'] = $taxes['total_local_tax1'];
				$item_data['total_local_tax2'] = $taxes['total_local_tax2'];

				if (isset($item['cog_item_cost'])) $item_data['buy_price'] = $this->site->ecommerce_price_type == 'TTC' ? 100 * $item['cog_item_cost'] / (100 + $item_data['tva_tx']) : $item['cog_item_cost'];
				if ($this->site->ecommerce_price_type == 'TTC') $item_data['price'] = (100 * $total_ttc / (100 + $item_data['tva_tx'])) / $item_qty;

				$item_data['price'] = abs($item_data['price']);
				$item_data['total_ht'] = abs($item_data['total_ht']);
				$item_data['total_tva'] = abs($item_data['total_tva']);
				$item_data['total_ttc'] = abs($item_data['total_ttc']);
				$item_data['total_local_tax1'] = abs($item_data['total_local_tax1']);
				$item_data['total_local_tax2'] = abs($item_data['total_local_tax2']);
				if (isset($item_data['buy_price']) ) $item_data['buy_price'] = abs($item_data['buy_price']);

				$sum_total_ht += $item_data['total_ht'];

				$items[$item['id']] = $item_data;
			}
		}

		// Set shipping lines
		if (!empty($remote_data['shipping_lines'])) {
			$shipment_service_id = $this->site->parameters['shipping_service'] > 0 ? $this->site->parameters['shipping_service'] : 0;
			foreach ($remote_data['shipping_lines'] as $item) {
				// Get metadata
				$metas_data = array();
				if (is_array($item['meta_data'])) {
					foreach ($item['meta_data'] as $meta) {
						$metas_data[$meta['key']] = $meta;
					}
				}

				$item_data = [
					'refund_item_id' => $metas_data['_refunded_item_id']['value'],
					'item_id' => $item['id'],
					'id_product' => $shipment_service_id,
					'label' => $outlangs->transnoentitiesnoconv('ECommerceShipping') . (!empty($item['method_title']) ? ' - ' . $item['method_title'] : ''),
					'description' => $outlangs->transnoentitiesnoconv('ECommerceShipping') . (!empty($item['method_title']) ? ' - ' . $item['method_title'] : ''),
					'product_type' => 'shipment',
					'price' => $item['total'],
					'total_ht' => $item['total'],
					'total_tva' => $item['total_tax'],
					'total_ttc' => ($item['total'] + $item['total_tax']),
					'qty' => 1,
					'discount' => 0,
				];

				// Taxes
				$taxes = $this->getTaxesInfoFromRemoteData($item['taxes'], $tax_list);
				if ($taxes === false) return false;
				$item_data['tva_tx'] = $taxes['tva_tx'];
				$item_data['local_tax1_tx'] = $taxes['local_tax1_tx'];
				$item_data['local_tax2_tx'] = $taxes['local_tax2_tx'];
				$item_data['total_local_tax1'] = $taxes['total_local_tax1'];
				$item_data['total_local_tax2'] = $taxes['total_local_tax2'];

				// Todo check if not equal to $item['total'] - $item['total_tax']
				if ($this->site->ecommerce_price_type == 'TTC') $item_data['price'] = 100 * ($item['total'] + $item['total_tax']) / (100 + $item_data['tva_tx']);
				$item_data['buy_price'] = $item_data['price'];

				$item_data['price'] = abs($item_data['price']);
				$item_data['total_ht'] = abs($item_data['total_ht']);
				$item_data['total_tva'] = abs($item_data['total_tva']);
				$item_data['total_ttc'] = abs($item_data['total_ttc']);
				$item_data['total_local_tax1'] = abs($item_data['total_local_tax1']);
				$item_data['total_local_tax2'] = abs($item_data['total_local_tax2']);
				if (isset($item_data['buy_price']) ) $item_data['buy_price'] = abs($item_data['buy_price']);

				$sum_total_ht += $item_data['total_ht'];

				$items[] = $item_data;
			}
		}

		// Set discount code lines
		if (!empty($remote_data['coupon_lines'])) {
			$discount_code_service_id = $this->site->parameters['discount_code_service'] > 0 ? $this->site->parameters['discount_code_service'] : 0;
			foreach ($remote_data['coupon_lines'] as $item) {
				$item_data = [
					'item_id' => $item['id'],
					'id_product' => $discount_code_service_id,
					'label' => $item['code'],
					'description' => $item['code'],
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
				foreach ($remote_data['tax_lines'] as $data) {
					if (empty($data['tax_total'])) continue;
					if ($data['rate_percent'] > $tax_rate) $tax_rate = $data['rate_percent'];
				}

				$ttc = $item['discount'] + $item['discount_tax'];
				$tva = $tax_rate * $ttc / ($tax_rate + 100);
				$ht = 100 * $ttc / ($tax_rate + 100);

				$item_data['tva_tx'] = $tax_rate;

				$item_data['price'] = abs($item_data['price']);
				$item_data['total_ht'] = abs($item_data['total_ht']);
				$item_data['total_tva'] = abs($item_data['total_tva']);
				$item_data['total_ttc'] = abs($item_data['total_ttc']);
				$item_data['total_local_tax1'] = abs($item_data['total_local_tax1']);
				$item_data['total_local_tax2'] = abs($item_data['total_local_tax2']);
				if (isset($item_data['buy_price']) ) $item_data['buy_price'] = abs($item_data['buy_price']);

				$sum_total_ht += $item_data['total_ht'];

				$items[] = $item_data;
			}
		}

		// Set gift card lines
		if (!empty($remote_data['pw_gift_cards_redeemed'])) {
			$gift_cards_service_id = $this->site->parameters['pw_gift_cards_service'] > 0 ? $this->site->parameters['pw_gift_cards_service'] : 0;
			foreach ($remote_data['pw_gift_cards_redeemed'] as $gift_cards) {
				$items[] = [
					'product_type' => 'pw_gift_cards',
					'id_product' => $gift_cards_service_id,
					'description' => $gift_cards['number'],
					'label' => $gift_cards['number'],
					'price' => abs($gift_cards['amount']),
					'total_ht' => abs($gift_cards['amount']),
					'total_tva' => 0,
					'total_ttc' => abs($gift_cards['amount']),
					'qty' => 1,
					'discount' => 0,
					'buy_price' => 0,
					'tva_tx' => 0,
					'local_tax1_tx' => 0,
					'local_tax2_tx' => 0,
					'total_local_tax1' => 0,
					'total_local_tax2' => 0,
				];
				$sum_total_ht += - $gift_cards['amount'];
			}
		}

		// Set fee lines
		$fee_lines = [];
		$fee_line_as_item_line = !empty($this->site->parameters['order_actions']['fee_line_as_item_line']);
		if (!empty($remote_data['fee_lines'])) {
			$fee_service_id = $this->site->parameters['fee_service'] > 0 ? $this->site->parameters['fee_service'] : 0;
			foreach ($remote_data['fee_lines'] as $fee_line) {
				$line = [
					'label' => $fee_line['name'],
					'price' => $fee_line['total'],
					'qty' => 1,
					'total_ht' => $fee_line['total'],
					'total_tva' => $fee_line['total_tax'],
					'total_ttc' => ($fee_line['total'] + $fee_line['total_tax']),
				];

				// Taxes
				$taxes = $this->getTaxesInfoFromRemoteData($fee_line['taxes'], $tax_list);
				if ($taxes === false) return false;
				$line['tva_tx'] = $taxes['tva_tx'];
				$line['local_tax1_tx'] = $taxes['local_tax1_tx'];
				$line['local_tax2_tx'] = $taxes['local_tax2_tx'];
				$line['total_local_tax1'] = $taxes['total_local_tax1'];
				$line['total_local_tax2'] = $taxes['total_local_tax2'];

				$line['price'] = abs($line['price']);
				$line['total_ht'] = abs($line['total_ht']);
				$line['total_tva'] = abs($line['total_tva']);
				$line['total_ttc'] = abs($line['total_ttc']);
				$line['total_local_tax1'] = abs($line['total_local_tax1']);
				$line['total_local_tax2'] = abs($line['total_local_tax2']);
				if (isset($line['buy_price']) ) $line['buy_price'] = abs($line['buy_price']);

				if ($fee_line_as_item_line) {
					$line['product_type'] = 'service';
					$line['id_product'] = $fee_service_id;
					$line['description'] = $fee_line['name'];
					$line['discount'] = 0;
					$line['buy_price'] = 0;

					$sum_total_ht += $line['total_ht'];

					$items[] = $line;
				} else {
					$fee_lines[] = $line;
				}
			}
		}
		// Manage fees in meta data (stripe payment, ...)
		if (!empty($remote_data['meta_data'])) {
			foreach ($remote_data['meta_data'] as $meta) {
				if ($meta['key'] == '_stripe_fee') {
					$fee_lines[] = [
						'label' => 'Stripe',
						'qty' => 1,
						'price' => $meta['value'],
						'total_ht' => $meta['value'],
						'total_tva' => 0,
						'total_ttc' => $meta['value'],
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

		$create_date = $this->getDateTimeFromGMTDateTime($remote_data['date_created_gmt']);

		// Add refund content to array
		$refund = [
			'create_date' => $create_date->getTimestamp(),
			'last_update' => $create_date->format('Y-m-d H:i:s'),
			'remote_id' => $remote_data['id'],
			'reason' => $remote_data['reason'],
			'language' => $select_language,
			'total_ht' => $sum_total_ht,
			'total_tva' => abs($remote_data['amount']) - $sum_total_ht,
			'total_ttc' => abs($remote_data['amount']),
			'items' => $items,
			'fee_lines' => $fee_lines,
		];

		return $refund;
	}

	/**
	 *  Get product data for a language (support WPML) (cached).
	 *
	 * @param int $remote_product_id Remote product ID
	 * @param int $remote_product_variation_id Remote product variation ID
	 * @param string $language Language desired
	 * @param bool $forced Force reload
	 * @return  array|bool                                      false if KO, empty if not found otherwise the product data for the desired language
	 */
	public function getProductLanguage($remote_product_id, $remote_product_variation_id, $language, $forced = false)
	{
		global $langs;

		if (!isset($this->product_language_cached[$remote_product_id][$remote_product_variation_id][$language]) || $forced) {
			$remote_product = $this->client->sendToApi(eCommerceClientApi::METHOD_GET, "products/{$remote_product_id}" . (!empty($remote_product_variation_id) ? "/variations/{$remote_product_variation_id}" : ""));
			if (!isset($remote_product)) {
				$this->errors[] = $langs->trans('ECommerceWoocommerceGetRemoteProduct', $remote_product_id . (!empty($remote_product_variation_id) ? "|{$remote_product_variation_id}" : ""), $this->site->name);
				$this->errors[] = $this->client->errorsToString();
				dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
				return false;
			}

			if ($remote_product['lang'] != $language) {
				$found = false;
				foreach ($remote_product['translations'] as $k => $v) {
					if ($k == $language) {
						$sub_remote_product = $this->client->sendToApi(eCommerceClientApi::METHOD_GET, "products/" . (empty($remote_product_variation_id) ? $v : $remote_product_id) . (!empty($remote_product_variation_id) ? "/variations/" . $v : ""));
						if (!isset($sub_remote_product)) {
							$this->errors[] = $langs->trans('ECommerceWoocommerceGetRemoteProduct', $v . (!empty($remote_product_variation_id) ? "|{$remote_product_variation_id}" : ""), $this->site->name);
							$this->errors[] = $this->client->errorsToString();
							dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
							return false;
						}
						$found = true;
						break;
					}
				}
				$remote_product = $found && !empty($sub_remote_product) ? $sub_remote_product : array();
			}

			$this->product_language_cached[$remote_product_id][$remote_product_variation_id][$language] = $remote_product;
		}

		return $this->product_language_cached[$remote_product_id][$remote_product_variation_id][$language];
	}

	/**
	 * Desactivated because is not supported by woocommerce.
	 *
	 * @param array $remoteObject List of id of remote orders to convert
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

		if (!empty($conf->global->ECOMMERCENG_DISABLED_PRODUCT_SYNCHRO_STOD)) {
			return [];
		}

		$this->errors = array();
		$categories = [];
        $idxPage = 1;
        $per_page = empty($conf->global->ECOMMERCENG_MAXSIZE_MULTICALL) ? 100 : min($conf->global->ECOMMERCENG_MAXSIZE_MULTICALL, 100);

		do {
        	$filters = [
				'page' => $idxPage++,
				'per_page' => $per_page,
			];

			$page = $this->client->sendToApi(eCommerceClientApi::METHOD_GET, 'products/categories', [GuzzleHttp\RequestOptions::QUERY => $filters], false, $status_code);
			if (!isset($page) && ($idxPage == 2 || $status_code != 403)) {
				$this->errors[] = $langs->trans('ECommerceWoocommerceGetRemoteCategoryTree', $this->site->name);
				$this->errors[] = $this->client->errorsToString();
				dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
				return false;
			}

			if (!isset($page)) $page = array();

            foreach ($page as $category) {
                $categories[$category['id']] = [
                    'category_id' => $category['id'],  // id category
                    'parent_id' => $category['parent'],
                    'label' => $category['name'],
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'updated_at' => '',
                ];
            }
		} while (count($page) == $per_page);

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

		$result = $this->client->sendToApi(eCommerceClientApi::METHOD_GET, 'products/categories/' . $category_id, [], false, $code_status);
		if (!isset($result)) {
			if ($code_status == 404) {
				return $category;
			}

			$this->errors[] = $langs->trans('ECommerceWoocommerceGetCategoryData', $this->site->name);
			$this->errors[] = $this->client->errorsToString();
			dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
			return false;
		}

		$category = [
			'category_id' => $result['id'],  // id category
			'parent_id' => $result['parent'],
			'label' => $result['name'],
			'name' => $result['name'],
			'description' => $result['description'],
			'updated_at' => '',
		];

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
		$productDimensionSynchDirection = isset($this->site->parameters['product_synch_direction']['dimension']) ? $this->site->parameters['product_synch_direction']['dimension'] : '';
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
		$totalWeight = weight_convert((double)$object->weight, $from_unit, $to_unit);

		// Convert Dimension
		$from_unit = $object->width_units;
		$to_unit = isset($this->site->parameters['product_direction_units']) ? $this->site->parameters['product_direction_units'] : -2; // -2 = cm
		$totalWidth = weight_convert((double)$object->width, $from_unit, $to_unit);
		$from_unit = $object->height_units;
		$totalHeight = weight_convert((double)$object->height, $from_unit, $to_unit);
		$from_unit = $object->length_units;
		$totalLength = weight_convert((double)$object->length, $from_unit, $to_unit);

        // Price
//        $error_price = 0;
        if (!empty($conf->global->PRODUIT_MULTIPRICES)) {
            $price_level = !empty($this->site->price_level) ? $this->site->price_level : 1;
            if ($this->site->ecommerce_price_type == 'TTC') {
//                if ($object->multiprices_base_type[$price_level] == 'TTC' || $object->multiprices_ttc[$price_level] == 0) {
                    $price = $object->multiprices_ttc[$price_level];
//                } else {
//                    $error_price++;
//                }
            } else {
//                if ($object->multiprices_base_type[$price_level] == 'TTC' && $object->multiprices[$price_level] != 0) {
//                    $error_price++;
//                } else {
                    $price = $object->multiprices[$price_level];
//                }
            }
        } else {
            if ($this->site->ecommerce_price_type == 'TTC') {
//                if ($object->price_base_type == 'TTC' || $object->price_ttc == 0) {
                    $price = $object->price_ttc;
//                } else {
//                    $error_price++;
//                }
            } else {
//                if ($object->price_base_type == 'TTC' && $object->price != 0) {
//                    $error_price++;
//                } else {
                    $price = $object->price;
//                }
            }
        }
//        if ($error_price) {
//            $error_msg = $langs->trans('ECommerceWoocommerceErrorBaseTypeOfProductWithSiteParameter', $object->ref, $this->site->ecommerce_price_type, $this->site->name);
//            $this->errors[] = $error_msg;
//            dol_syslog(__METHOD__ . ': Error:' . $error_msg, LOG_ERR);
//            return array();
//        }
        $regular_price = $object->array_options["options_ecommerceng_wc_regular_price_{$this->site->id}_{$conf->entity}"];

        $sale_price = price2num($object->array_options["options_ecommerceng_wc_sale_price_{$this->site->id}_{$conf->entity}"]);
        if (!empty($sale_price)) {
			$date_on_sale_from = $object->array_options["options_ecommerceng_wc_date_on_sale_from_{$this->site->id}_{$conf->entity}"];
			if (!empty($date_on_sale_from)) {
				$date_on_sale_from = $this->getDateTimeToGMTDateTime(is_numeric($date_on_sale_from) ? dol_print_date($date_on_sale_from, '%Y-%m-%dT%H:%M:%S') : $date_on_sale_from);
				$date_on_sale_from = isset($date_on_sale_from) ? dol_print_date($date_on_sale_from->getTimestamp(), '%Y-%m-%dT%H:%M:%S') : '';
			} else {
				$date_on_sale_from = '';
			}
			$date_on_sale_to = $object->array_options["options_ecommerceng_wc_date_on_sale_to_{$this->site->id}_{$conf->entity}"];
			if (!empty($date_on_sale_to)) {
				$date_on_sale_to = $this->getDateTimeToGMTDateTime(is_numeric($date_on_sale_to) ? dol_print_date($date_on_sale_to, '%Y-%m-%dT%H:%M:%S') : $date_on_sale_to);
				$date_on_sale_to = isset($date_on_sale_to) ? dol_print_date($date_on_sale_to->getTimestamp(), '%Y-%m-%dT%H:%M:%S') : '';
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
						$results = $this->client->sendToApi(eCommerceClientApi::METHOD_GET, "products/{$remote_product_id}/variations/{$remote_product_variation_id}");
						if (!isset($results)) {
							$this->errors[] = $langs->trans('ECommerceWoocommerceUpdateRemoteProductGetRemoteProduct', $remote_product_id . '|' . $remote_product_variation_id, $this->site->name);
							$this->errors[] = $this->client->errorsToString();
							dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
							return array();
						}

						if (isset($results['image'])) {
							$current_images[$results['image']['name']] = $results['image']['id'];
						}
					}
				} else {
					$results = $this->client->sendToApi(eCommerceClientApi::METHOD_GET, "products/{$remote_product_id}");
					if (!isset($results)) {
						$this->errors[] = $langs->trans('ECommerceWoocommerceUpdateRemoteProductGetRemoteProduct', $remote_product_id, $this->site->name);
						$this->errors[] = $this->client->errorsToString();
						dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
						return array();
					}

					if (is_array($results['images'])) {
						foreach ($results['images'] as $image) {
							$current_images[$image['name']] = $image['id'];
						}
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
//                        return array();
//                    } elseif (!empty($result['message'])) {
//                        $this->errors[] = $langs->trans('ECommerceWoocommerceUpdateRemoteProductSendImage', $object->ref . ' - '  . $remote_id, $this->site->name, $result['code'] . ' - ' . $result['message']);
//                        dol_syslog(__METHOD__ . ': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceUpdateRemoteProductSendImage',
//                                $remote_id, $this->site->name, $result['code'] . ' - ' . $result['message']), LOG_ERR);
//                        return array();
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

				$filename = pathinfo($file['name'], PATHINFO_FILENAME);
				$filename = ecommerceng_wordpress_sanitize_file_name($filename);

				$img = [
					'name' => $filename,
					'alt' => $filename,
					'src' => $url_root . '/' . $file['share'] . '/' . $file['name'],
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

		// Multi-languages
		$translates = array();
		if (!empty($conf->global->MAIN_MULTILANGS) && !empty($this->site->parameters['enable_product_plugin_wpml_support'])) {
			$language_list = $this->site->getLanguages();
			foreach ($language_list as $remote_lang => $language) {
				if ($remote_lang == 'ec_none' || !isset($object->multilangs[$language])) continue;

				$translates[$remote_lang] = array(
					'label' => $object->multilangs[$language]['label'],
					// 'description' => $object->multilangs[$language]['description'],
				);
			}
		}
		$return_data['translates'] = $translates;

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
                $variationData['description'] = dol_textishtml($object->array_options["options_ecommerceng_description_{$conf->entity}"]) ? $object->array_options["options_ecommerceng_description_{$conf->entity}"] : nl2br($object->array_options["options_ecommerceng_description_{$conf->entity}"]);

				if (empty($variationData['description']) || $initial_data) {
					$variationData['description'] = dol_textishtml($object->description) ? $object->description : nl2br($object->description);
					$object->array_options["options_ecommerceng_description_{$conf->entity}"] = $variationData['description'];
				}
			}
            if ($productWeightSynchDirection == 'dtoe' || $productWeightSynchDirection == 'all') {
                $variationData['weight'] = (string)(!empty($totalWeight) ? $totalWeight : '');
            }
			if ($productDimensionSynchDirection == 'dtoe' || $productDimensionSynchDirection == 'all') {
				$variationData['dimensions'] = array(
					'length'    => (string)(!empty($totalLength) ? $totalLength : ''),
					'width'     => (string)(!empty($totalWidth) ? $totalWidth : ''),
					'height'    => (string)(!empty($totalHeight) ? $totalHeight : ''),
				);
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
						return array();
					}
				}
            }

			$stock_data = $this->convertObjectIntoProductStockData($remote_id, $object);
			if (!isset($stock_data)) {
				return array();
			} elseif (!empty($stock_data)) {
				$variationData = array_merge($variationData, $stock_data);
			}

			// Synch extrafields <=> metadatas and attributes
			if (!empty($object->array_options)) {
				$attributes_to_add = array();
				foreach ($object->array_options as $key => $value) {
					$cr_key = substr($key, 8);
					if (preg_match('/^ecommerceng_/', $cr_key)) continue;

					// Synch extrafields <=> metadatas
					if (!empty($this->site->parameters['extra_fields']['product']['activated']['mdt'][$cr_key])) {
						$data_key = $this->site->parameters['extra_fields']['product']['values']['mdt'][$cr_key];
						if (!empty($data_key)) {
							$variationData['meta_data'][] = array('key' => $data_key, 'value' => $value);
						}
					}

					// Synch extrafields <=> attributes
					if (!empty($this->site->parameters['extra_fields']['product']['activated']['att'][$cr_key])) {
						$data_key = $this->site->parameters['extra_fields']['product']['values']['att'][$cr_key];
						$show_attr = $this->site->parameters['extra_fields']['product']['show']['att'][$cr_key];
						if (!empty($data_key)) {
							$variationData['attributes'][] = array('id' => $data_key, 'option' => !empty($value) ? $value : '');
							if (!empty($value)) $attributes_to_add[$data_key] = array('value' => $value, 'visible' => $show_attr);
						}
					}
				}

				// Update parent attributs
				if (!empty($attributes_to_add)) {
					$parent_remote_data = $this->client->sendToApi(eCommerceClientApi::METHOD_GET, "products/{$remote_product_id}");
					if (!isset($parent_remote_data)) {
						$this->errors[] = $langs->trans('ECommerceWoocommerceUpdateRemoteProductGetRemoteProduct', $remote_product_id, $this->site->name);
						$this->errors[] = $this->client->errorsToString();
						dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
						return array();
					}

					$return_data['product'] = array(
						'remote_id' => $remote_product_id,
						'data' => array(
							'attributes' => array(),
						),
					);

					if (is_array($parent_remote_data['attributes'])) {
						foreach ($parent_remote_data['attributes'] as $attribute) {
							$return_data['product']['data']['attributes'][] = $attribute;
						}
					}

					foreach ($attributes_to_add as $key => $info) {
						$found = false;
						foreach ($return_data['product']['data']['attributes'] as $k => $v) {
							if ($v['id'] == $key) {
								$return_data['product']['data']['attributes'][$k]['options'] = array_filter(array_map('trim', array_flip(array_flip(array_merge(array($info['value']), $v['options'])))), 'strlen');
								$found = true;
								break;
							}
						}
						if (!$found) {
							$return_data['product']['data']['attributes'][] = array('id' => $key, 'visible' => $info['visible'] != 2, 'variation' => true, 'options' => array($info['value']));
						}
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
                $productData['description'] = dol_textishtml($object->array_options["options_ecommerceng_description_{$conf->entity}"]) ? $object->array_options["options_ecommerceng_description_{$conf->entity}"] : nl2br($object->array_options["options_ecommerceng_description_{$conf->entity}"]);

				if (empty($productData['description']) || $initial_data) {
					$productData['description'] =  dol_textishtml($object->description) ? $object->description : nl2br($object->description);
					$object->array_options["options_ecommerceng_description_{$conf->entity}"] = $productData['description'];
				}
            }
            if ($productShortDescriptionSynchDirection == 'dtoe' || $productShortDescriptionSynchDirection == 'all') {
                $productData['short_description'] = dol_textishtml($object->array_options["options_ecommerceng_short_description_{$conf->entity}"]) ? $object->array_options["options_ecommerceng_short_description_{$conf->entity}"] : nl2br($object->array_options["options_ecommerceng_short_description_{$conf->entity}"]);
            }
            if ($productWeightSynchDirection == 'dtoe' || $productWeightSynchDirection == 'all') {
                $productData['weight'] = (string)(!empty($totalWeight) ? $totalWeight : '');
            }
			if ($productDimensionSynchDirection == 'dtoe' || $productDimensionSynchDirection == 'all') {
				$productData['dimensions'] = array(
					'length'    => (string)(!empty($totalLength) ? $totalLength : ''),
					'width'     => (string)(!empty($totalWidth) ? $totalWidth : ''),
					'height'    => (string)(!empty($totalHeight) ? $totalHeight : ''),
				);
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
						return array();
					}
				}
            }
            if ($productStatusSynchDirection == 'dtoe' || $productStatusSynchDirection == 'all') {
                $productData['status'] = (!empty($status) ? $status : ($object->status ? 'publish' : 'draft'));
				$object->array_options["options_ecommerceng_wc_status_{$this->site->id}_{$conf->entity}"] = $productData['status'];
			}

			$stock_data = $this->convertObjectIntoProductStockData($remote_product_id, $object);
			if (!isset($stock_data)) {
				return array();
			} elseif (!empty($stock_data)) {
				$productData = array_merge($productData, $stock_data);
			}

            // Synch extrafields <=> metadatas and attributes
            if (!empty($object->array_options)) {
				foreach ($object->array_options as $key => $value) {
                    $cr_key = substr($key, 8);
                    if (preg_match('/^ecommerceng_/', $cr_key)) continue;

					// Synch extrafields <=> metadatas
					if (!empty($this->site->parameters['extra_fields']['product']['activated']['mdt'][$cr_key])) {
						$data_key = $this->site->parameters['extra_fields']['product']['values']['mdt'][$cr_key];
						if (!empty($data_key)) {
							$productData['meta_data'][] = array('key' => $data_key, 'value' => $value);
						}
					}

					// Synch extrafields <=> attributes
					if (!empty($this->site->parameters['extra_fields']['product']['activated']['att'][$cr_key])) {
						$data_key = $this->site->parameters['extra_fields']['product']['values']['att'][$cr_key];
						$show_attr = $this->site->parameters['extra_fields']['product']['show']['att'][$cr_key];
						if (!empty($data_key)) {
							$productData['attributes'][] = array('id' => $data_key, 'visible' => $show_attr != 2, 'options' => !empty($value) ? explode(',', $value) : array());
						}
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
	 * Convert Object to remote product stock data
	 *
	 * @param   int     $remote_id  Id of product on remote ecommerce
	 * @param   Product $object     Product object
	 *
	 * @return  array             	null if KO, Remote product stock data, empty if don't update
	 */
	public function convertObjectIntoProductStockData($remote_id, $object)
	{
		dol_syslog(__METHOD__ . ": Update the remote product ID $remote_id for Dolibarr product ID {$object->id} for site ID {$this->site->id}", LOG_DEBUG);
		global $conf, $langs, $user;

		$this->errors = array();
		$return_data = array();

		if ($this->site->stock_sync_direction == 'dolibarr2ecommerce') {
			$return_data['manage_stock'] = !empty($object->array_options["options_ecommerceng_wc_manage_stock_{$this->site->id}_{$conf->entity}"]);
			$object->array_options["options_ecommerceng_wc_manage_stock_{$this->site->id}_{$conf->entity}"] = $return_data['manage_stock'] ? 1 : 0;

			if ($return_data['manage_stock'] && empty($object->array_options["options_ecommerceng_wc_dont_update_stock_{$this->site->id}_{$conf->entity}"])) {
				$object->load_stock();

				$total_stock = 0;
				$plugin_support = !empty($this->site->parameters['enable_warehouse_plugin_support']) ? $this->site->parameters['enable_warehouse_plugin_support'] : '';
				if (!empty($plugin_support)) {
					dol_include_once('/ecommerceng/class/data/eCommerceRemoteWarehouses.class.php');
					$eCommerceRemoteWarehouses = new eCommerceRemoteWarehouses($this->db);
					$remote_warehouses = $eCommerceRemoteWarehouses->get_all($this->site->id);
					if (!is_array($remote_warehouses)) {
						$this->errors[] = $eCommerceRemoteWarehouses->errorsToString();
						return null;
					}

					$stock_by_location = array();
					foreach ($remote_warehouses as $info) {
						if (!($info['warehouse_id'] > 0)) {
							dol_syslog(__METHOD__ . ' Warehouse not configured for remote warehouse ID ' . $info['remote_id'] . ' so we don\'t process this remote warehouse', LOG_WARNING);
							continue;
						}
						$stock = isset($object->stock_warehouse[$info['warehouse_id']]->real) ? max(0, $object->stock_warehouse[$info['warehouse_id']]->real) : 0;
						$total_stock += $stock;
						if ($plugin_support == 'wmlim') {
							$return_data['meta_data'][] = array('key' => 'wcmlim_stock_at_' . $info['remote_id'], 'value' => $stock);
						} else { // == slfw
							$stock_by_location[$info['remote_id']] = [
								'id' => $info['remote_id'],
								'quantity' => $stock,
							];
						}
					}

					if ($plugin_support == 'slfw') {
						$idsProduct = explode('|', $remote_id);
						$remote_product_id = $idsProduct[0];
						if (count($idsProduct) > 1) {
							$remote_product_variation_id = $idsProduct[1];
						}
						$remote_product = $this->client->sendToApi(eCommerceClientApi::METHOD_GET, "products/{$remote_product_id}" . (!empty($remote_product_variation_id) ? "/variations/{$remote_product_variation_id}" : ""));
						if (!isset($remote_product)) {
							$this->errors[] = $langs->trans('ECommerceWoocommerceGetRemoteProduct', $remote_id, $this->site->name);
							$this->errors[] = $this->client->errorsToString();
							dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
							return null;
						}
						if (is_array($remote_product['locations'])) {
							foreach ($remote_product['locations'] as $location) {
								if (!isset($stock_by_location[$location['id']])) {
									$total_stock += $location['quantity'];
									$stock_by_location[$location['id']] = $location;
								}
							}
						}
					}

					if (!empty($stock_by_location)) {
						$return_data['locations'] = array_values($stock_by_location);
					}
				} else {
					if (!empty($this->site->parameters['update_virtual_stock'])) {
						$total_stock = $object->stock_theorique - $object->stock_reel;
					}

					$supported_warehouses = is_array($this->site->parameters['fk_warehouse_to_ecommerce']) ? $this->site->parameters['fk_warehouse_to_ecommerce'] : array();
					foreach ($supported_warehouses as $warehouse_id) {
						$stock = isset($object->stock_warehouse[$warehouse_id]->real) ? $object->stock_warehouse[$warehouse_id]->real : 0;
						$total_stock += $stock;
					}
				}
				$total_stock = max(0, floor($total_stock));

//				$return_data['manage_stock'] = false;			// boolean      Stock management at product or variation level. Default is false.
				$return_data['stock_quantity'] = $total_stock;	// integer      Stock quantity.
				$return_data['in_stock'] = $total_stock > 0;	// boolean      Controls whether or not the variation is listed as “in stock” or “out of stock” on the frontend. Default is true.
//				$return_data['backorders'] = 'no';				// string       If managing stock, this controls if backorders are allowed. Options: no, notify and yes. Default is no.
			}
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

		$remote_translations = $datas['translates'];

		// Product
		//--------------------
		if (!empty($datas['product'])) {
			$remote_id = $datas['product']['remote_id'];
			$remote_data = $datas['product']['data'];

			$result = $this->client->sendToApi(eCommerceClientApi::METHOD_PUT, "products/{$remote_id}", [GuzzleHttp\RequestOptions::FORM_PARAMS => $remote_data]);
			if (!isset($result)) {
				$this->errors[] = $langs->trans('ECommerceWoocommerceUpdateRemoteProduct', $remote_id, $this->site->name);
				$this->errors[] = $this->client->errorsToString();
				dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
				return false;
			}

			// Support for WPML (Update (others than name and descriptions) infos on translated post)
			if (!empty($this->site->parameters['enable_product_plugin_wpml_support'])) {
				if (isset($result['translations'])) {
					if (isset($remote_data['description'])) unset($remote_data['description']);
					if (isset($remote_data['short_description'])) unset($remote_data['short_description']);
					foreach ((array) $result['translations'] as $remote_lang => $product_id) {
						if ($result['lang'] == $remote_lang) continue;
						if (isset($remote_translations[$remote_lang])) {
							$remote_data['name'] = $remote_translations[$remote_lang]['label'];
							// $remote_data['description'] = $remote_translations[$remote_lang]['description'];
						} else {
							if (isset($remote_data['name'])) unset($remote_data['name']);
							// if (isset($remote_data['description'])) unset($remote_data['description']);
						}
						$res = $this->client->sendToApi(eCommerceClientApi::METHOD_PUT, "products/{$product_id}", [GuzzleHttp\RequestOptions::FORM_PARAMS => $remote_data]);
						if (!isset($res)) {
							$this->errors[] = $langs->trans('ECommerceWoocommerceUpdateRemoteTranslatedProduct', $product_id, $remote_id, $this->site->name);
							$this->errors[] = $this->client->errorsToString();
							dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
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

				$result = $this->client->sendToApi(eCommerceClientApi::METHOD_PUT, "products/{$remote_id}/variations/{$remote_variation_id}", [GuzzleHttp\RequestOptions::FORM_PARAMS => $remote_data]);
				if (!isset($result)) {
					$this->errors[] = $langs->trans('ECommerceWoocommerceUpdateRemoteProductVariation', $remote_variation_id, $remote_id, $this->site->name);
					$this->errors[] = $this->client->errorsToString();
					dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
					return false;
				}

				// Support for WPML (Update (others than name and descriptions) infos on translated post)
				if (!empty($this->site->parameters['enable_product_plugin_wpml_support'])) {
					if (isset($result['translations'])) {
						if (isset($remote_data['description'])) unset($remote_data['description']);
						if (isset($remote_data['short_description'])) unset($remote_data['short_description']);
						foreach ((array) $result['translations'] as $remote_lang => $product_id) {
							if ($result['lang'] == $remote_lang) continue;
							if (isset($remote_translations[$remote_lang])) {
								$remote_data['name'] = $remote_translations[$remote_lang]['label'];
								// $remote_data['description'] = $remote_translations[$remote_lang]['description'];
							} else {
								if (isset($remote_data['name'])) unset($remote_data['name']);
								// if (isset($remote_data['description'])) unset($remote_data['description']);
							}
							$result2 = $this->client->sendToApi(eCommerceClientApi::METHOD_GET, "products/{$product_id}");
							if (!isset($result2)) {
								$this->errors[] = $langs->trans('ECommerceWoocommerceGetTranslatedProductVariation', $product_id, $remote_variation_id, $remote_id, $this->site->name);
								$this->errors[] = $this->client->errorsToString();
								dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
								return false;
							}

							$res = $this->client->sendToApi(eCommerceClientApi::METHOD_PUT, "products/{$result2['parent_id']}/variations/$product_id", [GuzzleHttp\RequestOptions::FORM_PARAMS => $remote_data]);
							if (!isset($res)) {
								$this->errors[] = $langs->trans('ECommerceWoocommerceUpdateRemoteTranslatedProductVariation', $result2['parent_id'] . '|' . $product_id, $remote_variation_id, $remote_id, $this->site->name);
								$this->errors[] = $this->client->errorsToString();
								dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
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
     * @param   int             	$remote_id      	Id of product on remote ecommerce
	 * @param   Product				$product        	Product object
	 * @param   eCommerceProduct	$eCommerceProduct   Product link
	 *
     * @return  boolean                         True or false
     */
    public function updateRemoteStockProduct($remote_id, $product, &$eCommerceProduct)
    {
        dol_syslog(__METHOD__ . ": Update stock of the remote product ID $remote_id for product ID {$product->id} for site ID {$this->site->id}", LOG_DEBUG);
        global $conf, $langs, $user;

		$this->errors = array();

		if (empty($remote_id)) {
			$langs->load('errors');
			$this->errors[] = $langs->trans('ErrorBadParameters') . ' - remote_id is empty';
			dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
			return false;
		}

		$stock_data = $this->convertObjectIntoProductStockData($remote_id, $product);
		if (!isset($stock_data)) {
			return false;
		} elseif (empty($stock_data)) {
			dol_syslog(__METHOD__ . " - Ignore update stock of the remote product ID $remote_id for Product ID {$product->id} for site ID {$this->site->id}", LOG_INFO);
			return true;
		}

		$isProductVariation = false;
		$isProductVariationHasOne = false;
		$remote_product_id = $remote_id;
		$remote_product_variation_id = 0;

		$product_variation_mode_all_to_one = !empty($this->site->parameters['product_variation_mode']) && $this->site->parameters['product_variation_mode'] == 'all_to_one';
		if ($product_variation_mode_all_to_one) {
			$idsProduct = explode('|', $remote_id);
			if (count($idsProduct) > 1) {
				$isProductVariationHasOne = true;
				$remote_product_id = $idsProduct[0];
			}
		} else {
			if (preg_match('/^(\d+)\|(\d+)$/', $remote_id, $idsProduct) == 1) { // Variations
				$isProductVariation = true;
				$remote_product_id = $idsProduct[1];
				$remote_product_variation_id = $idsProduct[2];
			}
		}

		$not_found = false;

		// Variation
		$new_variations = array();
		if ($isProductVariation) {
			$status_code = 0;
			$error_info = array();
			$result = $this->client->sendToApi(eCommerceClientApi::METHOD_PUT, "products/{$remote_product_id}/variations/{$remote_product_variation_id}", [GuzzleHttp\RequestOptions::FORM_PARAMS => $stock_data], false, $status_code, $error_info);
			if (!isset($result)) {
				if ($status_code == 400 && $error_info['code'] == 'woocommerce_rest_product_variation_invalid_id') {
					$not_found = true;
				} else {
					$this->errors[] = $langs->trans('ECommerceWoocommerceUpdateRemoteStockProductVariation', $stock_data['stock_quantity'], $remote_product_variation_id, $remote_product_id, $this->site->name);
					$this->errors[] = $this->client->errorsToString();
					dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
					return false;
				}
			}
			$new_variations[] = $remote_product_variation_id;
		} elseif ($isProductVariationHasOne) {
			$remote_product = $this->client->sendToApi(eCommerceClientApi::METHOD_GET, "products/{$remote_product_id}");
			if (!isset($remote_product)) {
				$this->errors[] = $langs->trans('ECommerceWoocommerceGetRemoteProduct', $remote_id, $this->site->name);
				$this->errors[] = $this->client->errorsToString();
				dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
				return false;
			}

			if (!empty($remote_product['variations'])) {
				foreach ($remote_product['variations'] as $remote_product_variation_id) {
					$status_code = 0;
					$error_info = array();
					$result = $this->client->sendToApi(eCommerceClientApi::METHOD_PUT, "products/{$remote_product_id}/variations/{$remote_product_variation_id}", [GuzzleHttp\RequestOptions::FORM_PARAMS => $stock_data], false, $status_code, $error_info);
					if (!isset($result)) {
						if ($status_code == 400 && $error_info['code'] == 'woocommerce_rest_product_variation_invalid_id') {
							continue;
						} else {
							$this->errors[] = $langs->trans('ECommerceWoocommerceUpdateRemoteStockProductVariation', $stock_data['stock_quantity'], $remote_product_variation_id, $remote_product_id, $this->site->name);
							$this->errors[] = $this->client->errorsToString();
							dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
							return false;
						}
					}
					$new_variations[] = $remote_product_variation_id;
				}
			}

			if (empty($new_variations)) {
				$not_found = true;
			} else {
				$eCommerceProduct->remote_id = $remote_product_id . '|' . implode('|', $new_variations);
			}
		}
		// Product
		if (!$not_found && (!$isProductVariation || $isProductVariationHasOne)) {
			$status_code = 0;
			$error_info = array();
			$result = $this->client->sendToApi(eCommerceClientApi::METHOD_PUT, "products/{$remote_product_id}", [GuzzleHttp\RequestOptions::FORM_PARAMS => $stock_data], false, $status_code, $error_info);
			if (!isset($result)) {
				if ($status_code == 400 && $error_info['code'] == 'woocommerce_rest_product_invalid_id') {
					$not_found = true;
				} else {
					$this->errors[] = $langs->trans('ECommerceWoocommerceUpdateRemoteStockProduct', $stock_data['stock_quantity'], $remote_product_id, $this->site->name);
					$this->errors[] = $this->client->errorsToString();
					dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
					return false;
				}
			}
		}

		if ($not_found) {
			$this->db->begin();

			$result = $eCommerceProduct->delete($user);
			if ($result < 0) {
				$this->errors[] = $eCommerceProduct->errorsToString();
				$this->db->rollback();
				return false;
			}

			// Delete all categories of the ecommerce
			if (empty($conf->global->ECOMMERCE_DONT_UNSET_CATEGORIE_OF_PRODUCT_WHEN_DELINK)) {
				require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
				$cat = new Categorie($this->db);
				$cat_root = $this->site->fk_cat_product;
				$all_cat_full_arbo = $cat->get_full_arbo('product');
				$cats_full_arbo = array();
				foreach ($all_cat_full_arbo as $category) {
					$cats_full_arbo[$category['id']] = $category['fullpath'];
				}
				$categories = $cat->containing($product->id, 'product', 'id');
				foreach ($categories as $cat_id) {
					if (isset($cats_full_arbo[$cat_id]) &&
						(
							preg_match("/^{$cat_root}$/", $cats_full_arbo[$cat_id]) || preg_match("/^{$cat_root}_/", $cats_full_arbo[$cat_id]) ||
							preg_match("/_{$cat_root}_/", $cats_full_arbo[$cat_id]) || preg_match("/_{$cat_root}$/", $cats_full_arbo[$cat_id])
						)
					) {
						if ($cat->fetch($cat_id) > 0) {
							if ($cat->del_type($product, 'product') < 0) {
								$this->errors[] = $cat->errorsToString();
								$this->db->rollback();
								return false;
							}
						}
					}
				}
			}

			$this->db->commit();
			$eCommerceProduct->id = 0;
			return true;
		}

		// Support for WPML (Update stocks infos on translated post)
		if (!empty($this->site->parameters['enable_product_plugin_wpml_support'])) {
			if ($isProductVariation || $isProductVariationHasOne) {
				foreach ($new_variations as $remote_product_variation_id) {
					$result = $this->client->sendToApi(eCommerceClientApi::METHOD_GET, "products/{$remote_product_variation_id}");
					if (!isset($result)) {
						$this->errors[] = $langs->trans('ECommerceWoocommerceGetProductVariation', $remote_product_variation_id, $remote_product_id, $this->site->name);
						$this->errors[] = $this->client->errorsToString();
						dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
						return false;
					}

					if (isset($result['translations'])) {
						foreach ((array)$result['translations'] as $product_id) {
							$result2 = $this->client->sendToApi(eCommerceClientApi::METHOD_GET, "products/{$product_id}");
							if (!isset($result2)) {
								$this->errors[] = $langs->trans('ECommerceWoocommerceGetTranslatedProductVariation', $product_id, $remote_product_variation_id, $remote_product_id, $this->site->name);
								$this->errors[] = $this->client->errorsToString();
								dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
								return false;
							}

							$res = $this->client->sendToApi(eCommerceClientApi::METHOD_PUT, "products/{$result2['parent_id']}/variations/{$product_id}", [GuzzleHttp\RequestOptions::FORM_PARAMS => $stock_data]);
							if (!isset($res)) {
								$this->errors[] = $langs->trans('ECommerceWoocommerceUpdateRemoteStockTranslatedProductVariation', $stock_data['stock_quantity'], $result2['parent_id'] . '|' . $product_id, $remote_product_variation_id, $remote_product_id) . ': ' . $this->site->name;
								$this->errors[] = $this->client->errorsToString();
								dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
								return false;
							}
						}
					}
				}
			}
			if (!$isProductVariation || $isProductVariationHasOne) {
				$result = $this->client->sendToApi(eCommerceClientApi::METHOD_GET, "products/{$remote_product_id}");
				if (!isset($result)) {
					$this->errors[] = $langs->trans('ECommerceWoocommerceGetProduct', $remote_product_id, $this->site->name);
					$this->errors[] = $this->client->errorsToString();
					dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
					return false;
				}

				if (isset($result['translations'])) {
					foreach ((array)$result['translations'] as $product_id) {
						$res = $this->client->sendToApi(eCommerceClientApi::METHOD_PUT, "products/{$product_id}", [GuzzleHttp\RequestOptions::FORM_PARAMS => $stock_data]);
						if (!isset($res)) {
							$this->errors[] = $langs->trans('ECommerceWoocommerceUpdateRemoteStockTranslatedProduct', $stock_data['stock_quantity'], $product_id, $remote_id, $this->site->name);
							$this->errors[] = $this->client->errorsToString();
							dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
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

		// Synch extrafields <=> metadatas and attributes
		if (!empty($object->array_options)) {
			foreach ($object->array_options as $key => $value) {
				$cr_key = substr($key, 8);
				if (preg_match('/^ecommerceng_/', $cr_key)) continue;

				// Synch extrafields <=> metadatas
				if (!empty($this->site->parameters['extra_fields']['societe']['activated']['mdt'][$cr_key])) {
					$data_key = $this->site->parameters['extra_fields']['societe']['values']['mdt'][$cr_key];
					if (!empty($data_key)) {
						$variationData['meta_data'][] = array('key' => $data_key, 'value' => $value);
					}
				}
			}
		}

		$result = $this->client->sendToApi(eCommerceClientApi::METHOD_PUT, "customers/{$remote_id}", [GuzzleHttp\RequestOptions::FORM_PARAMS => $companyData]);
		if (!isset($result)) {
			$this->errors[] = $langs->trans('ECommerceWoocommerceUpdateRemoteSociete', $remote_id, $this->site->name);
			$this->errors[] = $this->client->errorsToString();
			dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
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
            if (preg_match('/^(\d+)\|(\d+)$/', $remote_id, $idsCustomer)) {
				$result = $this->client->sendToApi(eCommerceClientApi::METHOD_PUT, "customers/{$idsCustomer[1]}", [GuzzleHttp\RequestOptions::FORM_PARAMS => $contactData]);
				if (!isset($result)) {
					$this->errors[] = $langs->trans('ECommerceWoocommerceUpdateRemoteSocpeople', $idsCustomer[1], $this->site->name);
					$this->errors[] = $this->client->errorsToString();
					dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
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
			if (version_compare(DOL_VERSION, "13.0.0") >= 0) {
				$options_list = isset($efields->attributes['commande']['param']["ecommerceng_wc_status_{$this->site->id}_{$conf->entity}"]['options']) ? $efields->attributes['commande']['param']["ecommerceng_wc_status_{$this->site->id}_{$conf->entity}"]['options'] : null;
			} else {
				$options_list = isset($efields->attribute_param["ecommerceng_wc_status_{$this->site->id}_{$conf->entity}"]['options']) ? $efields->attribute_param["ecommerceng_wc_status_{$this->site->id}_{$conf->entity}"]['options'] : null;
			}
            $order_status = array();
            if (is_array($options_list)) {
                foreach ($options_list as $key => $value) {
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
                !empty($this->site->parameters['order_status_dtoe_check_lvl_status'])) $status = $wc_status;

            $orderData = [
                'status' => $status,  // string  Order status. Options: pending, processing, on-hold, completed, cancelled, refunded and failed.
            ];

            // Synch extrafields <=> metadatas
            if (!empty($object->array_options)) {
                foreach ($object->array_options as $key => $value) {
                    $cr_key = substr($key, 8);
                    if (preg_match('/^ecommerceng_/', $cr_key)) continue;

					if (!empty($this->site->parameters['extra_fields']['commande']['activated']['dft'][$cr_key])) {
						$data_key = $this->site->parameters['extra_fields']['commande']['values']['dft'][$cr_key];
						if (!empty($data_key)) {
							// Specific Altairis - Begin
							if (!empty($value) && ($cr_key == 'rental_start' || $cr_key == 'rental_end')) {
								$value = dol_print_date($value, 'day');
							}
							// Specific Altairis - End

							$variationData['meta_data'][] = array('key' => $data_key, 'value' => $value);
						}
					}
                }
            }

			$result = $this->client->sendToApi(eCommerceClientApi::METHOD_PUT, "orders/{$remote_id}", [GuzzleHttp\RequestOptions::FORM_PARAMS => $orderData]);
			if (!isset($result)) {
				$this->errors[] = $langs->trans('ECommerceWoocommerceUpdateRemoteCommande', $remote_id, $this->site->name);
				$this->errors[] = $this->client->errorsToString();
				dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
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
     * @return  boolean|array             False or data (Id, url, ...) of remote product created
     */
    public function createRemoteProduct($object)
    {
        dol_syslog(__METHOD__ . ": Create product from Dolibarr product ID {$object->id} for site ID {$this->site->id}", LOG_DEBUG);
        global $conf, $langs, $user;

		$this->errors = array();
		$filters = ['sku' => $object->ref];
		$results = $this->client->sendToApi(eCommerceClientApi::METHOD_GET, "products", [GuzzleHttp\RequestOptions::QUERY => $filters], false, $status_code);
		if (!isset($results) && $status_code != 404) {
			$this->errors[] = $langs->trans('ECommerceWoocommerceCheckRemoteProductExist', $object->ref, $this->site->name);
			$this->errors[] = $this->client->errorsToString();
			dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
			return false;
		}
		$results = isset($results['products']) ? $results['products'] : (is_array($results) ? $results : []);

		$remote_id = '';
		$remote_url = '';
		$remote_language = '';
		if (is_array($results) && count($results) > 0) {
			$remote_id = (!empty($results[0]['parent_id']) ? $results[0]['parent_id'] . '|' : '') . $results[0]['id'];
			$remote_url = $results[0]['permalink'];
			if (!empty($this->site->parameters['enable_product_plugin_wpml_support'])) $remote_language = $results[0]['language'];
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

				$result = $this->client->sendToApi(eCommerceClientApi::METHOD_POST, "products", [GuzzleHttp\RequestOptions::FORM_PARAMS => $remote_data]);
				if (!isset($result)) {
					$this->errors[] = $langs->trans('ECommerceWoocommerceCreateRemoteProduct', $object->id, $this->site->name);
					$this->errors[] = $this->client->errorsToString();
					dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
					return false;
				}

				$remote_id = $result['id'];
				$remote_url = $result['permalink'];
				if (!empty($this->site->parameters['enable_product_plugin_wpml_support'])) $remote_language = $result['language'];
			}
		}

		dol_syslog(__METHOD__ . ": end", LOG_DEBUG);
		return array('remote_id' => $remote_id, 'remote_url' => $remote_url, 'language' => $remote_language);
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
				$results = $this->client->sendToApi(eCommerceClientApi::METHOD_PUT, "products/categories/batch", [GuzzleHttp\RequestOptions::FORM_PARAMS => ['create' => $request]]);
				if (!isset($results)) {
					$this->errors[] = $langs->trans('ECommerceWoocommerceCreateRemoteBatchCategories', $this->site->name);
					$this->errors[] = $this->client->errorsToString();
					dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
					return false;
				}

                $results = isset($results['create']) ? $results['create'] : array();
                foreach ($results as $key => $item) {
                    if (isset($item['error'])) {
                    	$error_msg = $langs->trans('ECommerceWoocommerceCreateRemoteBatchCategory', $request[$key]['slug'], $this->site->name) . $item['error']['code'] . ': ' . $item['error']['message'] . ' (data : ' . json_encode($item['error']['data']) . ' )';
                        $this->errors[] = $error_msg;
                        dol_syslog(__METHOD__ . ' - Error: ' . $error_msg, LOG_ERR);
                    } else {
						$cats_id_remote_id[$cats_slug_id[$item['slug']]] = array('remote_id' => $item['id'], 'remote_parent_id' => $item['parent']);
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

        return array(); // Todo to remake
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
				$totalWeight = weight_convert((double)$product_static->weight, $from_unit, $to_unit);

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

//                // images
//                $images = [];
//                if ($productImageSynchDirection == 'dtoe' || $productImageSynchDirection == 'all') {
//                    // Get current images
//                    $current_images = [];
//					$stopwatch_id = -1;
//					try {
//                        if ($isProductVariation) { // Variations
//							$stopwatch_id = eCommerceUtils::startStopwatch(__METHOD__ . " - GET products/{$remote_product_id}/variations/{$remote_product_variation_id}");
//							$results = $this->client->get("products/$remote_product_id/variations/$remote_product_variation_id");
//                        } else {
//							$stopwatch_id = eCommerceUtils::startStopwatch(__METHOD__ . " - GET products/{$remote_product_id}");
//							$results = $this->client->get("products/$remote_product_id");
//                        }
//						eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
//
//                        if (!empty($results)) {
//                            if ($isProductVariation) {
//                                if (isset($results->image)) {
//                                    $current_images[$results->image->name] = $results->image->id;
//                                }
//                            } else {
//                                if (is_array($results->images)) {
//                                    foreach ($results->images as $image) {
//                                        $current_images[$image->name] = $image->id;
//                                    }
//                                }
//                            }
//                        }
//                    } catch (HttpClientException $fault) {
//						eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
//						$this->errors[] = $langs->trans('ECommerceWoocommerceUpdateRemoteProductGetRemoteProduct', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
//                       dol_syslog(__METHOD__ .
//                           ': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceUpdateRemoteProductGetRemoteProduct', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
//                           ' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
//                       continue;
//                    }
//
//                    // Product - Images properties
//                    $entity = isset($product_static->entity) ? $product_static->entity : $conf->entity;
//                    if (!empty($conf->global->PRODUCT_USE_OLD_PATH_FOR_PHOTO)) {    // For backward compatiblity, we scan also old dirs
//                        if ($product_static->type == Product::TYPE_PRODUCT) {
//                            $dir = $conf->product->multidir_output[$entity] . '/' . substr(substr("000" . $product_static->id, -2), 1, 1) . '/' . substr(substr("000" . $product_static->id, -2), 0, 1) . '/' . $product_static->id . "/photos/";
//                        } else {
//                            $dir = $conf->service->multidir_output[$entity] . '/' . substr(substr("000" . $product_static->id, -2), 1, 1) . '/' . substr(substr("000" . $product_static->id, -2), 0, 1) . '/' . $product_static->id . "/photos/";
//                        }
//                    } else {
//						if (version_compare(DOL_VERSION, "13.0.0") >= 0) {
//							if ($product_static->type == Product::TYPE_PRODUCT) {
//								$dir = $conf->product->multidir_output[$entity] . '/' . get_exdir(0, 0, 0, 1, $product_static, 'product') . '/';
//							} else {
//								$dir = $conf->service->multidir_output[$entity] . '/' . get_exdir(0, 0, 0, 1, $product_static, 'product') . '/';
//							}
//						} else {
//							if ($product_static->type == Product::TYPE_PRODUCT) {
//								$dir = $conf->product->multidir_output[$entity] . '/' . get_exdir(0, 0, 0, 0, $product_static, 'product') . dol_sanitizeFileName($product_static->ref) . '/';
//							} else {
//								$dir = $conf->service->multidir_output[$entity] . '/' . get_exdir(0, 0, 0, 0, $product_static, 'product') . dol_sanitizeFileName($product_static->ref) . '/';
//							}
//						}
//                    }
//                    $photos = $product_static->liste_photos($dir);
//                    foreach ($photos as $index => $photo) {
//                        $img = [];
//
//                        $filename = ecommerceng_wordpress_sanitize_file_name($photo['photo']);
//                        if (!isset($current_images[$filename])) {
//                            $result = $this->worpressclient->postmedia("media", $dir . $photo['photo'], [
//                                'slug' => $product_static->id . '_' . $filename,
//                                'ping_status' => 'closed',
//                                'comment_status' => 'closed',
//                            ]);
//
//                            if ($result === null) {
//                                $error_msg = $langs->trans($batch_infos['type'] == 'update' ? 'ECommerceWoocommerceUpdateRemoteProductSendImage' : 'ECommerceWoocommerceCreateRemoteProductSendImage', $batch_infos['type'] == 'update' ? $batch_infos['remote_id'] : $product_static->ref, $this->site->name, implode('; ', $this->worpressclient->errors));
//                                $this->errors[] = $error_msg;
//                                dol_syslog(__METHOD__ . ': Error:' . $error_msg, LOG_ERR);
//                                continue;
//                            } elseif (!empty($result['message'])) {
//                                $error_msg = $langs->trans($batch_infos['type'] == 'update' ? 'ECommerceWoocommerceUpdateRemoteProductSendImage' : 'ECommerceWoocommerceCreateRemoteProductSendImage', $batch_infos['type'] == 'update' ? $batch_infos['remote_id'] : $product_static->ref, $this->site->name, $result['code'] . ' - ' . $result['message']);
//                                $this->errors[] = $error_msg;
//                                dol_syslog(__METHOD__ . ': Error:' . $error_msg, LOG_ERR);
//                                continue;
//                            }
//
//                            $img['id'] = $result['id'];
//                        } else {
//                            $img['id'] = $current_images[$filename];
//                        }
//
//                        $img['name'] = $filename;
//                        $img['position'] = $index;
//                        $images[] = $img;
//
//                        if ($isProductVariation) { // Get only one image for variation
//                            break;
//                        }
//                    }
//                }

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
     * @param   Object      $object                 Object (invoice or shipping)
     * @param   string      $file                   File path
     * @param   Translate   $outputlangs            Lang output object
     *
     * @return  bool
     */
    public function sendFileForCommande($order_remote_id, $object, $file, $outputlangs)
    {
        dol_syslog(__METHOD__ . ": Send file '$file' for remote order ID $order_remote_id for site ID {$this->site->id}", LOG_DEBUG);
        global $langs;

		$this->errors = array();

		$order_data = $this->client->sendToApi(eCommerceClientApi::METHOD_GET, "orders/{$order_remote_id}");
		if (!isset($order_data)) {
			$this->errors[] = $langs->trans('ECommerceWoocommerceErrorGetOrderData', $order_remote_id, $this->site->name);
			$this->errors[] = $this->client->errorsToString();
			dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
			return false;
		}

		$data = [
			[
				'name'     => 'slug',
				'contents' => $order_remote_id . '_' . $object->element,
			],
			[
				'name'     => 'author',
				'contents' => $order_data['customer_id'],
			],
			[
				'name'     => 'post',
				'contents' => $order_remote_id,
			],
			[
				'name'     => 'ping_status',
				'contents' => 'closed',
			],
			[
				'name'     => 'comment_status',
				'contents' => 'closed',
			],
		];

		// Set File
		if (file_exists($file)) {
			if (function_exists('curl_file_create')) { // php 5.5+
				$cFile = curl_file_create($file);
			} else {
				$cFile = '@' . realpath($file);
			}
			$data[] = [
				'name'     => 'file',
				'filename' => basename($file),
				'contents' => $cFile,
//				'headers'  => [ 'Content-Type' => $content_type ],
			];
			$data['file'] = $cFile;
		} else {
			$this->errors[] = array('File not found ("'.$file.'").');
			return false;
		}

		// Send file to WordPress
		$result = $this->worpressclient->sendToApi(eCommerceClientApi::METHOD_POST, "media", [ GuzzleHttp\RequestOptions::MULTIPART => $data ]);
        if ($result === null) {
            $this->errors[] = $langs->trans('ECommerceWoocommerceSendFileForCommandeInWordpress', $order_remote_id, $this->site->name, $this->worpressclient->errorsToString('; '));
            dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
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
		$result = $this->client->sendToApi(eCommerceClientApi::METHOD_PUT, "orders/{$order_remote_id}", [GuzzleHttp\RequestOptions::FORM_PARAMS => $commandeData]);
		if (!isset($result)) {
			$this->errors[] = $langs->trans('ECommerceWoocommerceSendFileForCommande', $order_remote_id, $this->site->name);
			$this->errors[] = $this->client->errorsToString();
			dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
			return false;
		}

        dol_syslog(__METHOD__ . ": end", LOG_DEBUG);
        return true;
    }

	/**
	 * Retrieve Dolibarr taxes info from remote data
	 *
	 * @param 	array		$taxes_data		Info of the taxes
	 * @param 	array		$tax_list		Taxes list provided by the order
	 * @return  array|bool					Dolibarr taxes info
	 */
	private function getTaxesInfoFromRemoteData($taxes_data, $tax_list = array())
	{
		global $langs;
		$tva_tx = 0;
		$count = 0;

		$this->loadTaxes();

		if (!empty(self::$taxes_rates_cached) || !empty($tax_list)) {
			foreach ($taxes_data as $data) {
				if (empty($data['total'])) continue;
				$count++;
				if ($count > 1) break;

				$tax_id = $data['id'];
				if (isset($tax_list[$tax_id])) {
					$tva_tx = $tax_list[$tax_id];
				} else {
					if (isset(self::$taxes_rates_cached[$tax_id])) {
						$tva_tx = self::$taxes_rates_cached[$tax_id]['rate'];
					} else {
						$this->errors[] = $langs->trans('ECommerceWooCommerceErrorTaxNotFound', $tax_id);
						return false;
					}
				}
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
	 * Get all woocommerce attributes
	 *
	 * @return array|false    List of woocommerce attributes or false if error
	 */
	public function getAllWoocommerceAttributes()
	{
		dol_syslog(__METHOD__ . ": Retrieve all Woocommerce attributes", LOG_DEBUG);
		global $conf, $langs;

		$nb_max_by_request = empty($conf->global->ECOMMERCENG_MAXSIZE_MULTICALL) ? 100 : min($conf->global->ECOMMERCENG_MAXSIZE_MULTICALL, 100);

		$attributes_list = [];
		$idxPage = 1;
		do {
			$filters =  [
				'page' => $idxPage++,
				'per_page' => $nb_max_by_request,
			];

			$attributes = $this->client->sendToApi(eCommerceClientApi::METHOD_GET, "products/attributes", [GuzzleHttp\RequestOptions::QUERY => $filters]);
			if (!isset($attributes)) {
				$this->errors[] = $langs->trans('ECommerceWoocommerceGetAllWoocommerceAttributes', $this->site->name);
				$this->errors[] = $this->client->errorsToString();
				dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
				return false;
			}

			foreach ($attributes as $attribute) {
				$attributes_list[$attribute['id']] = $attribute;
			}
		} while (count($attributes) == $nb_max_by_request);

		dol_syslog(__METHOD__ . ": end, return: ".json_encode($attributes_list), LOG_DEBUG);
		return $attributes_list;
	}

	/**
	 * Get all woocommerce terms of a attribute
	 *
	 * @param	int				$attribute_id	Attribute ID
	 * @return	array|false    					List of woocommerce terms of a attribute or false if error
	 */
	public function getAllWoocommerceAttributeTerms($attribute_id)
	{
		dol_syslog(__METHOD__ . ": Retrieve all Woocommerce terms of a attribute", LOG_DEBUG);
		global $conf, $langs;

		$nb_max_by_request = empty($conf->global->ECOMMERCENG_MAXSIZE_MULTICALL) ? 100 : min($conf->global->ECOMMERCENG_MAXSIZE_MULTICALL, 100);

		$terms_list = [];
		$idxPage = 1;
		do {
			$filters =  [
				'page' => $idxPage++,
				'per_page' => $nb_max_by_request,
			];

			$terms = $this->client->sendToApi(eCommerceClientApi::METHOD_GET, "products/attributes/{$attribute_id}/terms", [GuzzleHttp\RequestOptions::QUERY => $filters]);
			if (!isset($terms)) {
				$this->errors[] = $langs->trans('ECommerceWoocommerceGetAllWoocommerceAttributeTerms', $attribute_id, $this->site->name);
				$this->errors[] = $this->client->errorsToString();
				dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
				return false;
			}

			foreach ($terms as $term) {
				$terms_list[$term['id']] = $term;
			}
		} while (count($terms) == $nb_max_by_request);

		dol_syslog(__METHOD__ . ": end, return: ".json_encode($terms_list), LOG_DEBUG);
		return $terms_list;
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

		$tax_classes = $this->client->sendToApi(eCommerceClientApi::METHOD_GET, "taxes/classes");
		if (!isset($tax_classes)) {
			$this->errors[] = $langs->trans('ECommerceWoocommerceGetAllWoocommerceTaxClass', $this->site->name);
			$this->errors[] = $this->client->errorsToString();
			dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
			return false;
		}

		$taxClassesTable = [];
		foreach ($tax_classes as $tax_class) {
			unset($tax_class['_links']);
			$taxClassesTable[$tax_class['slug']] = $tax_class;
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
		$idxPage = 1;
		do {
			$filters =  [
				'page' => $idxPage++,
				'per_page' => $nb_max_by_request,
			];

			$taxes = $this->client->sendToApi(eCommerceClientApi::METHOD_GET, 'taxes', [GuzzleHttp\RequestOptions::QUERY => $filters]);
			if (!isset($taxes)) {
				$this->errors[] = $langs->trans('ECommerceWoocommerceGetWoocommerceTaxes', $this->site->name);
				$this->errors[] = $this->client->errorsToString();
				dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
				return false;
			}

			foreach ($taxes as $tax) {
				unset($tax['_links']);
				$taxClassesTable[$tax['id']] = $tax;
			}
		} while (count($taxes) == $nb_max_by_request);

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
		global $conf, $langs;

		$nb_max_by_request = empty($conf->global->ECOMMERCENG_MAXSIZE_MULTICALL) ? 100 : min($conf->global->ECOMMERCENG_MAXSIZE_MULTICALL, 100);

		$paymentGatewaysTable = [];
		$idxPage = 1;
		do {
			$filters =  [
				'page' => $idxPage++,
				'per_page' => $nb_max_by_request,
			];

			$payment_gateways = $this->client->sendToApi(eCommerceClientApi::METHOD_GET, 'payment_gateways', [GuzzleHttp\RequestOptions::QUERY => $filters]);
			if (!isset($payment_gateways)) {
				$this->errors[] = $langs->trans('ECommerceWoocommerceGetAllWoocommercePaymentGateways', $this->site->name);
				$this->errors[] = $this->client->errorsToString();
				dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
				return false;
			}

			foreach ($payment_gateways as $infos) {
				if ($infos['enabled']) {
					$paymentGatewaysTable[$infos['id']] = $infos['method_title'] . (!empty($infos['title']) ? ' - ' . $infos['title'] : '');
				}
			}
		} while (count($payment_gateways) == $nb_max_by_request);

        dol_syslog(__METHOD__ . ": end, return: ".json_encode($paymentGatewaysTable), LOG_DEBUG);
        return $paymentGatewaysTable;
    }

	/**
	 * Get all remote warehouses
	 *
	 * @return array|false    List of remote warehouses or false if error
	 */
	public function getAllRemoteWarehouses()
	{
		dol_syslog(__METHOD__ . ": Retrieve all Woocommerce remote warehouses", LOG_DEBUG);
		global $langs;
		$remoteWarehousesTable = [];

		// plugin warehouse support
		if (!empty($this->site->parameters['enable_warehouse_plugin_support'])) {
			$plugin_support = $this->site->parameters['enable_warehouse_plugin_support'];
			if ($plugin_support == 'wmlim') {
				$remote_warehouses = $this->client->sendToApi(eCommerceClientApi::METHOD_GET, 'locations');
			} else {
				$remote_warehouses = $this->worpressclient->sendToApi(eCommerceClientApi::METHOD_GET, 'location');
			}
			$remote_warehouses = $this->worpressclient->sendToApi(eCommerceClientApi::METHOD_GET, $plugin_support == 'wmlim' ? 'locations' : 'location');
			if (!isset($remote_warehouses)) {
				$this->errors[] = $langs->trans('ECommerceWoocommerceGetAllWoocommerceRemoteWarehouses', $this->site->name);
                $this->errors[] = $this->worpressclient->errorsToString();
				dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
				return false;
			}

			foreach ($remote_warehouses as $infos) {
				$remote_id = $infos["id"];
				$remoteWarehousesTable[$remote_id] = [
					'remote_code' => $infos["slug"],
					'remote_id' => $remote_id,
					'name' => $infos["name"],
					'parent' => $infos["parent"],
				];
			}
		}

		dol_syslog(__METHOD__ . ": end, return: ".json_encode($remoteWarehousesTable), LOG_DEBUG);
		return $remoteWarehousesTable;
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
		$idxPage = 1;
		do {
			$filters =  [
				'page' => $idxPage++,
				'per_page' => $nb_max_by_request,
			];

			$webhooks = $this->client->sendToApi(eCommerceClientApi::METHOD_GET, 'webhooks', [GuzzleHttp\RequestOptions::QUERY => $filters]);
			if (!isset($webhooks)) {
				$this->errors[] = $langs->trans('ECommerceWoocommerceErrorGetWoocommerceWebHooks', $this->site->name);
				$this->errors[] = $this->client->errorsToString();
				dol_syslog(__METHOD__ . ': Error:' . $this->errorsToString(), LOG_ERR);
				return false;
			}

			foreach ($webhooks as $webhook) {
				if ($webhook['delivery_url'] == $eCommerceSiteWebHooksUrl) {
					$webhooks_list[$webhook['id']] = array(
						'remote_id' => $webhook['id'],
						'name' => $webhook['name'],
						'status' => $webhook['status'] == 'active',
						'infos' => json_encode(array(
							'topic' => $webhook['topic'],
							'resource' => $webhook['resource'],
							'event' => $webhook['event'],
						)),
					);
				}
			}
		} while (count($webhooks) == $nb_max_by_request);

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
