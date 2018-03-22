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
     * Constructor
     * @param   DoliDB          $db     Database handler
     * @param   eCommerceSite   $site   eCommerceSite object
     */
    function eCommerceRemoteAccessWoocommerce($db, $site)
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
     * Connect to API
     *
     * @return boolean      True if OK, False if KO
     */
    public function connect()
    {
        dol_syslog(__METHOD__ . ": Connect to API webservice_address=" . $this->site->webservice_address . " user_name=" .
            $this->site->user_name . " user_password=" . $this->site->user_password . " for site ID {$this->site->id}", LOG_DEBUG);
        global $conf, $langs;

        $response_timeout = (empty($conf->global->MAIN_USE_RESPONSE_TIMEOUT) ? 30 : $conf->global->MAIN_USE_RESPONSE_TIMEOUT);    // Response timeout
        $isHTTPS = (bool) preg_match('/^https/i', $this->site->webservice_address);

        try {
            $this->client = new Client(
                $this->site->webservice_address,
                $this->site->user_name,
                $this->site->user_password,
                [
                    'wp_api' => true,
                    'version' => 'wc/v2',
                    'timeout' => $response_timeout,
                    'query_string_auth' => $isHTTPS,
                ]
            );
            $this->client->get('customers', [ 'page' => 1, 'per_page' => 1 ]);

            $this->clientOld = new Client(
                $this->site->webservice_address,
                $this->site->user_name,
                $this->site->user_password,
                [
                    'version' => 'v3',
                    'timeout' => $response_timeout,
                    'query_string_auth' => $isHTTPS,
                ]
            );
            $this->clientOld->get('customers', [ 'page' => 1, 'filter' => [ 'limit' => 1 ] ]);
        } catch (HttpClientException $fault) {
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
                dol_buildpath('/custom/ecommerceng/core/modules/oauth/wordpress_oauthcallback.php', 2) . '?ecommerce_id=' . $this->site->id
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

        $last_update = [];
        $result = [];
        $idxPage = 1;
        $per_page = empty($conf->global->ECOMMERCENG_MAXSIZE_MULTICALL) ? 100 : min($conf->global->ECOMMERCENG_MAXSIZE_MULTICALL, 100);
        $from_date = isset($fromDate) && !empty($fromDate) ? new DateTime(dol_print_date($fromDate, 'standard')) : null;
        $to_date = isset($toDate) && !empty($toDate) ? new DateTime(dol_print_date($toDate, 'standard')) : null;

        $filter = ['limit' => $per_page];
        // Not work with customers
        //if (isset($fromDate) && !empty($fromDate)) $filter['updated_at_min'] = dol_print_date($fromDate - (24 * 60 * 60), 'dayrfc');
        //if (isset($toDate) && !empty($toDate)) $filter['updated_at_max'] = dol_print_date($toDate + (24 * 60 * 60), 'dayrfc');

        while (true) {
            try {
                $page = $this->clientOld->get('customers',
                    [
                        'page' => $idxPage++,
                        'filter' => $filter,
                        'fields' => 'id,created_at,last_update'
                    ]
                );
            } catch (HttpClientException $fault) {
                $this->errors[] = $langs->trans('ECommerceWoocommerceGetSocieteToUpdate', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
                dol_syslog(__METHOD__ .
                    ': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceGetSocieteToUpdate', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
                    ' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
                return false;
            }

            if (!isset($page->customers) || ($nbCustomers = count($page->customers)) == 0) break;
            $page = $page->customers;

            foreach ($page as $customer) {
                $date = $this->getDateTimeFromGMTDateTime(!empty($customer->updated_at) ? $customer->updated_at : $customer->created_at);

                if ((!isset($from_date) || $from_date < $date) && (!isset($to_date) || $date <= $to_date)) {
                    $id = $customer->id;
                    $result[$id] = $id;
                    $last_update[$id] = $date->format('Y-m-d H:i:s');
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
                $page = $this->clientOld->get('products',
                    [
                        'page' => $idxPage++,
                        'filter' => $filter,
                        'fields' => 'id,created_at,updated_at,variations'
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
                $update = false;
                $date_product = $this->getDateTimeFromGMTDateTime(!empty($product->updated_at) ? $product->updated_at : $product->created_at);

                // Product
                if ((!isset($from_date) || $from_date < $date_product) && (!isset($to_date) || $date_product <= $to_date)) {
                    $id = $product->id;
                    $result[$id] = $id;
                    $last_update[$id] = $date_product->format('Y-m-d H:i:s');
                    $update = true;
                }

                // Variations
                foreach ($product->variations as $variation) {
                    $date_variation = $this->getDateTimeFromGMTDateTime(!empty($variation->updated_at) ? $variation->updated_at : $variation->created_at);

                    if ($update || ((!isset($from_date) || $from_date < $date_variation) && (!isset($to_date) || $date_variation <= $to_date))) {
                        $id = $product->id . '|' . $variation->id;
                        $result[$id] = $id;
                        $last_update[$id] = $date_product > $date_variation ? $date_product->format('Y-m-d H:i:s') : $date_variation->format('Y-m-d H:i:s');
                    }
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
                $date = $this->getDateTimeFromGMTDateTime(!empty($order->updated_at) ? $order->updated_at : $order->created_at);

                if ((!isset($from_date) || $from_date < $date) && (!isset($to_date) || $date <= $to_date)) {
                    $id = $order->id;
                    $result[$id] = $id;
                    $last_update[$id] = $date->format('Y-m-d H:i:s');
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
        return [];
    }

    /**
     * Call Woocommerce API to get company datas and put into dolibarr company class.
     *
     * @param   array           $remoteObject List of id of remote companies to convert
     * @param   int             $toNb         Max nb
     * @return  array|boolean                 List of companies sorted by update time or false if error.
     */
    public function convertRemoteObjectIntoDolibarrSociete($remoteObject, $toNb=0)
    {
        dol_syslog(__METHOD__ . ": Get " . count($remoteObject) . " remote companies ID: " . implode(', ', $remoteObject) . " for site ID {$this->site->id}", LOG_DEBUG);
        global $conf, $langs;

        $companies = [];
        $nb_max_by_request = empty($conf->global->ECOMMERCENG_MAXSIZE_MULTICALL) ? 100 : min($conf->global->ECOMMERCENG_MAXSIZE_MULTICALL, 100);
        $requestGroups = $this->getRequestGroups($remoteObject, $nb_max_by_request, $toNb);

        foreach ($requestGroups as $request) {
            dol_syslog(__METHOD__ . ": Get partial remote companies ID: " . implode(', ', $request), LOG_DEBUG);
            try {
                $results = $this->client->get('customers',
                    [
                        'per_page' => $nb_max_by_request,
                        'include' => implode(',', $request),
                    ]
                );
            } catch (HttpClientException $fault) {
                $this->errors[] = $langs->trans('ECommerceWoocommerceConvertRemoteObjectIntoDolibarrSociete', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
                dol_syslog(__METHOD__ .
                    ': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceConvertRemoteObjectIntoDolibarrSociete', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
                    ' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
                return false;
            }

            if (is_array($results)) {
                foreach ($results as $company) {
                    $last_update = $this->getDateTimeFromGMTDateTime(!empty($company->date_modified_gmt) ? $company->date_modified_gmt : $company->date_created_gmt);

                    // Company
                    if (!empty($company->billing->company)) {
                        $companies[] = [
                            'remote_id' => $company->id,
                            'last_update' => $last_update->format('Y-m-d H:i:s'),
                            'type' => 'company',
                            'name' => $company->billing->company,
                            'name_alias' => null,
                            'email' => null,
                            'email_key' => $company->email,
                            'client' => 1,
                            'vatnumber' => null,
                            'note_private' => "Site: '{$this->site->name}' - ID: {$company->id}",
                            'country_id' => getCountry($company->billing->country, 3),
                            'remote_datas' => $company,
                        ];
                    }
                    // User
                    else {
                        $firstname = !empty($company->first_name) ? $company->first_name : $company->billing->first_name;
                        $lastname = !empty($company->last_name) ? $company->last_name : $company->billing->last_name;
                        if (!empty($firstname) && !empty($lastname)) {
                            $name = dolGetFirstLastname($firstname, $lastname);
                        } elseif (!empty($firstname)) {
                            $name = dolGetFirstLastname($firstname, $langs->trans("ECommercengWoocommerceLastnameNotInformed"));
                        } else {
                            $name = $langs->trans('ECommercengWoocommerceWithoutFirstnameLastname');
                        }
                        $companies[] = [
                            'remote_id' => $company->id,
                            'last_update' => $last_update->format('Y-m-d H:i:s'),
                            'type' => 'user',
                            'name' => $name,
                            'name_alias' => null,
                            'email' => $company->email,
                            'email_key' => $company->email,
                            'client' => 1,
                            'vatnumber' => null,
                            'note_private' => "Site: '{$this->site->name}' - ID: {$company->id}",
                            'country_id' => getCountry($company->billing->country, 3),
                            'remote_datas' => $company,
                        ];
                    }
                }
            }
        }

        //important - order by last update
        if (count($companies)) {
            $last_update = [];
            foreach ($companies as $key => $row) {
                $last_update[$key] = $row['last_update'];
            }
            array_multisort($last_update, SORT_ASC, $companies);
        }

        dol_syslog(__METHOD__ . ": end, converted " . count($companies) . " remote companies", LOG_DEBUG);
        return $companies;
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
        global $langs;

        $contacts = [];
        $last_update = $this->getDateTimeFromGMTDateTime(!empty($remoteCompany->date_modified_gmt) ? $remoteCompany->date_modified_gmt : $remoteCompany->date_created_gmt);

        $bContact = $remoteCompany->billing;
        if (!empty($bContact->address_1) || !empty($bContact->address_2) || !empty($bContact->postcode) ||
            !empty($bContact->city) || !empty($bContact->country) ||
            !empty($bContact->email) || !empty($bContact->phone)
        ) {
            $firstname = !empty($bContact->first_name) ? $bContact->first_name : $remoteCompany->first_name;
            $lastname = !empty($bContact->last_name) ? $bContact->last_name : $remoteCompany->last_name;
            if (!empty($firstname) && empty($lastname)) {
                $lastname = $langs->trans("ECommercengWoocommerceLastnameNotInformed");
            } elseif (empty($firstname) && empty($lastname)) {
                $lastname = $langs->trans('ECommercengWoocommerceWithoutFirstnameLastname');
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
                if (!empty($firstname) && empty($lastname)) {
                    $lastname = $langs->trans("ECommercengWoocommerceLastnameNotInformed");
                } elseif (empty($firstname) && empty($lastname)) {
                    $lastname = $langs->trans('ECommercengWoocommerceWithoutFirstnameLastname');
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
     * @param   array           $remoteObject List of id of remote products to convert
     * @param   int             $toNb         Max nb
     * @return  array|boolean                 List of products sorted by update time or false if error.
     */
    public function convertRemoteObjectIntoDolibarrProduct($remoteObject, $toNb=0)
    {
        dol_syslog(__METHOD__ . ": Get " . count($remoteObject) . " remote products ID: " . implode(', ', $remoteObject) . " for site ID {$this->site->id}", LOG_DEBUG);
        global $conf, $langs;

        $canvas = '';
        $products = [];
        $remoteVariationObject = [];
        $products_last_update = [];
        $nb_max_by_request = empty($conf->global->ECOMMERCENG_MAXSIZE_MULTICALL) ? 100 : min($conf->global->ECOMMERCENG_MAXSIZE_MULTICALL, 100);

        // Products
        $newRemoteObject = [];
        $remoteObject = array_slice($remoteObject, 0, $toNb);
        foreach ($remoteObject as $id) {
            if (($pos = strpos($id, '|')) !== false) {
                $variation_id = substr($id,$pos+1);
                $id = substr($id,0,$pos);
                if (!isset($remoteVariationObject[$id])) $remoteVariationObject[$id] = [];
                $remoteVariationObject[$id][] = $variation_id;
            }
            $newRemoteObject[$id] = $id;
        }
        $requestGroups = $this->getRequestGroups($newRemoteObject, $nb_max_by_request);
        foreach ($requestGroups as $request) {
            dol_syslog(__METHOD__ . ": Get ".count($request)." partial remote products ID: " . implode(', ', $request), LOG_DEBUG);
            try {
                $results = $this->client->get('products',
                    [
                        'per_page' => $nb_max_by_request,
                        'include' => implode(',', $request),
                    ]
                );
            } catch (HttpClientException $fault) {
                $this->errors[] = $langs->trans('ECommerceWoocommerceConvertRemoteObjectIntoDolibarrProduct', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
                dol_syslog(__METHOD__ .
                    ': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceConvertRemoteObjectIntoDolibarrProduct', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
                    ' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
                return false;
            }

            if (is_array($results)) {
                foreach ($results as $product) {
                    // Categories
                    $categories = [];
                    foreach ($product->categories as $category) {
                        $categories[] = $category->id;
                    }

                    // Images
                    $images = [];
                    if (!empty($conf->global->ECOMMERCENG_ENABLE_SYNCHRO_IMAGES)) {
                        foreach ($product->images as $image) {
                            $last_update = $this->getDateTimeFromGMTDateTime(!empty($image->date_modified_gmt) ? $image->date_modified_gmt : $image->date_created_gmt);
                            $images[] = [
                                'url' => $image->src,
                                'date_modified' => $last_update->format('Y-m-d H:i:s'),
                            ];
                        }
                        array_reverse($images);
                    }

                    $last_update_product = $this->getDateTimeFromGMTDateTime(!empty($product->date_modified_gmt) ? $product->date_modified_gmt : $product->date_created_gmt);

                    $remote_id = $product->id;  // id product
                    $last_update = $last_update_product->format('Y-m-d H:i:s');

                    // Produit de base
                    if (in_array($remote_id, $remoteObject, true)) {
                        $products_last_update[$remote_id] = $last_update;
                        $products[$remote_id] = [
                            'remote_id' => $remote_id,
                            'last_update' => $last_update,
                            'fk_product_type' => ($product->virtual ? 1 : 0), // 0 (product) or 1 (service)
                            'ref' => $product->sku,
                            'label' => $product->name,
                            'weight' => $product->weight,
                            'price' => $product->price,
                            'envente' => empty($product->variations) ? 1 : 0,
                            'enachat' => empty($product->variations) ? 1 : 0,
                            'finished' => 1,    // 1 = manufactured, 0 = raw material
                            'canvas' => $canvas,
                            'categories' => $categories,
                            'tax_rate' => $this->getTaxRate($product->tax_class, $product->tax_status),
                            'price_min' => $product->price,
                            'fk_country' => '',
                            'url' => $product->permalink,
                            // Stock
                            'stock_qty' => $product->stock_quantity,
                            'is_in_stock' => $product->in_stock,   // not used
                            'extrafields' => [
                                "ecommerceng_wc_status_{$this->site->id}_{$conf->entity}" => $product->status,
                                "ecommerceng_description_{$conf->entity}" => $product->description,
                                "ecommerceng_short_description_{$conf->entity}" => $product->short_description,
                                "ecommerceng_tax_class_{$this->site->id}_{$conf->entity}" => $this->getTaxClass($product->tax_class, $product->tax_status),
                            ],
                            'images' => $images,
                        ];
                    }

                    // Variations
                    $requestGroupsVariations = $this->getRequestGroups($remoteVariationObject[$product->id], $nb_max_by_request);
                    foreach ($requestGroupsVariations as $requestVariations) {
                        dol_syslog(__METHOD__ . ": Get ".count($requestVariations)." products variations of remote product (ID:{$product->id}): " . implode(', ', $requestVariations), LOG_DEBUG);
                        try {
                            $results = $this->client->get('products/' . $product->id . '/variations',
                                [
                                    'per_page' => $nb_max_by_request,
                                    'include' => implode(',', $requestVariations),
                                ]
                            );
                        } catch (HttpClientException $fault) {
                            $this->errors[] = $langs->trans('ECommerceWoocommerceConvertRemoteObjectIntoDolibarrProductVariations', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
                            dol_syslog(__METHOD__ .
                                ': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceConvertRemoteObjectIntoDolibarrProductVariations', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
                                ' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
                            return false;
                        }

                        if (is_array($results)) {
                            foreach ($results as $variation) {
                                $attributesLabel = '';
                                foreach ($variation->attributes as $attribute) {
                                    $attributesLabel .= ', ' . $attribute->name . ':' . $attribute->option;
                                }

                                // Images
                                $images = [];
                                if (!empty($conf->global->ECOMMERCENG_ENABLE_SYNCHRO_IMAGES)) {
                                    if (!empty($variation->image)) {
                                        $last_update = $this->getDateTimeFromGMTDateTime(!empty($variation->image->date_modified_gmt) ? $variation->image->date_modified_gmt : $variation->image->date_created_gmt);
                                        $images[] = [
                                            'url' => $variation->image->src,
                                            'date_modified' => $last_update->format('Y-m-d H:i:s'),
                                        ];
                                    }
                                }

                                $last_update_product_variation = $this->getDateTimeFromGMTDateTime(!empty($variation->date_modified_gmt) ? $variation->date_modified_gmt : $variation->date_created_gmt);
                                $last_update_product_variation = $last_update_product > $last_update_product_variation ? $last_update_product : $last_update_product_variation;

                                $remote_id = $product->id . '|' . $variation->id;  // id product | id variation
                                $last_update = $last_update_product_variation->format('Y-m-d H:i:s');

                                // Variation
                                $products_last_update[$remote_id] = $last_update;
                                $products[$remote_id] = [
                                    'remote_id' => $remote_id,
                                    'last_update' => $last_update,
                                    'fk_product_type' => ($variation->virtual ? 1 : 0), // 0 (product) or 1 (service)
                                    'ref' => $variation->sku,
                                    'label' => $product->name . $attributesLabel,
                                    'weight' => $variation->weight,
                                    'price' => $variation->price,
                                    'envente' => 1,
                                    'enachat' => 1,
                                    'finished' => 1,    // 1 = manufactured, 0 = raw material
                                    'canvas' => $canvas,
                                    'categories' => $categories,
                                    'tax_rate' => $this->getTaxRate($variation->tax_class, $variation->tax_status),
                                    'price_min' => $variation->price,
                                    'fk_country' => '',
                                    'url' => $variation->permalink,
                                    // Stock
                                    'stock_qty' => $variation->stock_quantity,
                                    'is_in_stock' => $variation->in_stock,   // not used
                                    'extrafields' => [
                                        "ecommerceng_description_{$conf->entity}" => $variation->description,
                                        "ecommerceng_tax_class_{$this->site->id}_{$conf->entity}" => $this->getTaxClass($variation->tax_class, $variation->tax_status),
                                    ],
                                    'images' => $images,
                                ];
                            }
                        }
                    }
                }
            }
        }

        //important - order by last update
        if (count($products)) {
            array_multisort($products_last_update, SORT_ASC, $products);
        }

        dol_syslog(__METHOD__ . ": end, converted " . count($products) . " remote products", LOG_DEBUG);
        return $products;
    }

    /**
     * Call Woocommerce API to get order datas and put into dolibarr order class.
     *
     * @param   array           $remoteObject List of id of remote orders to convert
     * @param   int             $toNb         Max nb
     * @return  array|boolean                 List of orders sorted by update time or false if error.
     */
    public function convertRemoteObjectIntoDolibarrCommande($remoteObject, $toNb=0)
    {
        dol_syslog(__METHOD__ . ": Get " . count($remoteObject) . " remote orders ID: " . implode(', ', $remoteObject) . " for site ID {$this->site->id}", LOG_DEBUG);
        global $conf, $langs;

        $orders = [];
        $nb_max_by_request = empty($conf->global->ECOMMERCENG_MAXSIZE_MULTICALL) ? 100 : min($conf->global->ECOMMERCENG_MAXSIZE_MULTICALL, 100);
        $requestGroups = $this->getRequestGroups($remoteObject, $nb_max_by_request, $toNb);

        foreach ($requestGroups as $request) {
            dol_syslog(__METHOD__ . ": Get partial remote orders ID: " . implode(', ', $request), LOG_DEBUG);
            try {
                $results = $this->client->get('orders',
                    [
                        'per_page' => $nb_max_by_request,
                        'include' => implode(',', $request),
                    ]
                );
            } catch (HttpClientException $fault) {
                $this->errors[] = $langs->trans('ECommerceWoocommerceConvertRemoteObjectIntoDolibarrCommande', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
                dol_syslog(__METHOD__ .
                    ': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceConvertRemoteObjectIntoDolibarrCommande', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
                    ' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
                return false;
            }

            if (is_array($results)) {
                foreach ($results as $order) {
                    // Set items
                    $items = [];
                    foreach ($order->line_items as $item) {
                        $items[] = [
                            'item_id' => $item->id,
                            'id_remote_product' => !empty($item->variation_id) ? $item->product_id . '|' . $item->variation_id : $item->product_id,
                            'description' => $item->name,
                            'product_type' => 'simple',
                            'price' => $item->price,
                            'qty' => $item->quantity,
                            'tva_tx' => $this->getClosestDolibarrTaxRate($item->total, $item->total_tax),
                        ];
                    }

                    // Set remote id company : 0 for anonymous
                    $eCommerceTempSoc = new eCommerceSociete($this->db);
                    if (empty($order->customer_id) || $eCommerceTempSoc->fetchByRemoteId($order->customer_id, $this->site->id) < 0) {
                        dol_syslog(__METHOD__ . ": The customer of the remote order ID " . $order->id . " was not found into companies table link", LOG_WARNING);
                        $remoteCompanyID = 0;   // If company was not found into companies table link
                    } else {
                        $remoteCompanyID = $order->customer_id;
                    }

                    $last_update = $this->getDateTimeFromGMTDateTime(!empty($order->date_modified_gmt) ? $order->date_modified_gmt : $order->date_created_gmt);

                    // Set billing's address
                    $bContact = $order->billing;
                    $firstname = $bContact->first_name;
                    $lastname = $bContact->last_name;
                    if (!empty($firstname) && empty($lastname)) {
                        $lastname = $langs->trans("ECommercengWoocommerceLastnameNotInformed");
                    } elseif (empty($firstname) && empty($lastname)) {
                        $lastname = $langs->trans('ECommercengWoocommerceWithoutFirstnameLastname');
                    }
                    $contactBilling = [
                        'remote_id' => "",
                        'type' => 1, //eCommerceSocpeople::CONTACT_TYPE_ORDER,
                        'last_update' => $last_update->format('Y-m-d H:i:s'),
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
                    $sContact = $order->shipping;
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
                            if (!empty($firstname) && empty($lastname)) {
                                $lastname = $langs->trans("ECommercengWoocommerceLastnameNotInformed");
                            } elseif (empty($firstname) && empty($lastname)) {
                                $lastname = $langs->trans('ECommercengWoocommerceWithoutFirstnameLastname');
                            }
                            $contactShipping = [
                                'remote_id' => "",
                                'type' => 1, //eCommerceSocpeople::CONTACT_TYPE_DELIVERY,
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
                        } else {
                            $contactShipping = $contactBilling;
                            $contactShipping['type'] = 1; //eCommerceSocpeople::CONTACT_TYPE_DELIVERY;
                        }
                    } else {
                        $contactShipping = $contactBilling;
                        $contactShipping['type'] = 1; //eCommerceSocpeople::CONTACT_TYPE_DELIVERY;
                    }

                    // Set delivery as service
                    $shippingDisplayIfNull = (empty($conf->global->ECOMMERCENG_SHIPPING_NOT_DISPLAY_IF_NULL) ? true : false);
                    $delivery = [
                        'description' => $langs->trans('ECommerceShipping') . (isset($order->shipping_lines[0]) ? ' - ' .
                                $order->shipping_lines[0]->method_title : ''), // $order->customer_note
                        'price' => $order->shipping_total,
                        'qty' => $shippingDisplayIfNull || isset($order->shipping_lines[0]) ? 1 : 0, //0 to not show
                        'tva_tx' => $this->getClosestDolibarrTaxRate($order->shipping_total, $order->shipping_tax)
                    ];

                    // Set status of order
                    // $order->status is: 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed'
                    $orderStatus = $order->status;

                    $status = '';
                    switch ($orderStatus) {
                        case 'on-hold': $status = Commande::STATUS_DRAFT; break;
                        case 'pending': $status = Commande::STATUS_DRAFT; break;
                        case 'processing': $status = !empty($conf->global->ECOMMERCENG_WOOCOMMERCE_ORDER_PROCESSING_STATUS_TO_DRAFT)?Commande::STATUS_DRAFT:Commande::STATUS_VALIDATED; break;
                        case 'completed': $status = Commande::STATUS_CLOSED; break;
                        case 'refunded': $status = Commande::STATUS_CANCELED; break;
                        case 'cancelled': $status = Commande::STATUS_CANCELED; break;
                        case 'failed': $status = Commande::STATUS_CANCELED; break;
                    }
                    if (!empty($conf->global->ECOMMERCENG_WOOCOMMERCE_FORCE_ORDER_STATUS_TO_DRAFT)) $status = Commande::STATUS_DRAFT;
                    if ($status == '') {
                        dol_syslog(__METHOD__ . ": Status \"$orderStatus\" was not found for remote order ID {$order->id} and set in draft", LOG_WARNING);
                        $status = Commande::STATUS_DRAFT;   // draft by default
                    }

                    // Set dolibarr billed status (payed or not)
                    $billed = -1;   // unknown
                    if ($order->status == 'pending') $billed = 0;
                    if ($order->status == 'processing') $billed = 0;   //
                    if ($order->status == 'on-hold') $billed = 0;      //
                    if ($order->status == 'completed') $billed = 1;    // We are sure for complete that order is payed
                    if ($order->status == 'cancelled') $billed = 0;    // We are sure for canceled that order was not payed
                    if ($order->status == 'refunded') $billed = 1;     //
                    if ($order->status == 'failed') $billed = 0;       //
                    // Note: with processing, billed can be 0 or 1, so we keep -1

                    // Add order content to array or orders
                    $orders[] = [
                        'last_update' => $last_update->format('Y-m-d H:i:s'),
                        'remote_id' => $order->id,
                        'remote_increment_id' => $order->id,
                        'remote_id_societe' => $remoteCompanyID,
                        'ref_client' => $order->id,
                        'date_commande' => $order->date_created,
                        'date_livraison' => $order->date_completed,
                        'items' => $items,
                        'delivery' => $delivery,
                        'note' => $order->customer_note,
                        'socpeopleCommande' => $contactBilling,
                        'socpeopleFacture' => $contactInvoice,
                        'socpeopleLivraison' => $contactShipping,
                        'status' => $status,                         // dolibarr status
                        'billed' => $billed,
                        'remote_state' => $order->status,        // remote state, for information only (less accurate than status)
                        'remote_status' => $order->status,      // remote status, for information only (more accurate than state)
                        'remote_order' => $order,
                        'payment_method' => $order->payment_method_title,
                        'extrafields' => [
                            "ecommerceng_online_payment_{$conf->entity}" => empty($order->date_paid) ? 0 : 1,
                            "ecommerceng_wc_status_{$conf->entity}" => $order->status,
                        ],
                    ];
                }
            }
        }

        //important - order by last update
        if (count($orders)) {
            $last_update = [];
            foreach ($orders as $key => $row) {
                $last_update[$key] = $row['last_update'];
            }
            array_multisort($last_update, SORT_ASC, $orders);
        }

        dol_syslog(__METHOD__ . ": end, converted " . count($orders) . " remote orders", LOG_DEBUG);
        return $orders;
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

        $categories = [];
        $idxPage = 1;
        $per_page = empty($conf->global->ECOMMERCENG_MAXSIZE_MULTICALL) ? 100 : min($conf->global->ECOMMERCENG_MAXSIZE_MULTICALL, 100);

        while (true) {
            try {
                $results = $this->client->get('products/categories',
                    [
                        'page' => $idxPage++,
                        'per_page' => $per_page,
                    ]
                );
            } catch (HttpClientException $fault) {
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

        $category = [];

        try {
            $result = $this->client->get('products/categories/' . $category_id);
        } catch (HttpClientException $fault) {
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
        return [];
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

        $isProductVariation = false;
        $remote_product_id = $remote_id;
        $remote_product_variation_id = 0;
        if (preg_match('/^(\d+)\|(\d+)$/', $remote_id, $idsProduct) == 1) { // Variations
            $isProductVariation = true;
            $remote_product_id = $idsProduct[1];
            $remote_product_variation_id = $idsProduct[2];
        }

        // Set Weight
        $totalWeight = $object->weight;
        if ($object->weight_units < 50)   // >50 means a standard unit (power of 10 of official unit), > 50 means an exotic unit (like inch)
        {
            $trueWeightUnit = pow(10, $object->weight_units);
            $totalWeight = sprintf("%f", $object->weight * $trueWeightUnit);
        }

        // images
        $images = [];
        if (!empty($conf->global->ECOMMERCENG_ENABLE_SYNCHRO_IMAGES)) {
            // Get current images
            $current_images = [];
            try {
                if ($isProductVariation) { // Variations
                    $results = $this->client->get("products/$remote_product_id/variations/$remote_product_variation_id");
                } else {
                    $results = $this->client->get("products/$remote_product_id");
                }

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
               $this->errors[] = $langs->trans('ECommerceWoocommerceUpdateRemoteProductGetRemoteProduct', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
               dol_syslog(__METHOD__ .
                   ': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceUpdateRemoteProductGetRemoteProduct', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
                   ' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
               return false;
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
                if ($object->type == Product::TYPE_PRODUCT) {
                    $dir = $conf->product->multidir_output[$entity] . '/' . get_exdir(0, 0, 0, 0, $object, 'product') . dol_sanitizeFileName($object->ref) . '/';
                } else {
                    $dir = $conf->service->multidir_output[$entity] . '/' . get_exdir(0, 0, 0, 0, $object, 'product') . dol_sanitizeFileName($object->ref) . '/';
                }
            }
            $photos = $object->liste_photos($dir);
            foreach ($photos as $index => $photo) {
                $img = [];

                $filename = ecommerceng_wordpress_sanitize_file_name($photo['photo']);
                if (!isset($current_images[$filename])) {
                    $result = $this->worpressclient->postmedia("media", $dir . $photo['photo'], [
                        'slug' => $object->id . '_' . $filename,
                        'ping_status' => 'closed',
                        'comment_status' => 'closed',
                    ]);

                    if ($result === null) {
                        $this->errors[] = $langs->trans('ECommerceWoocommerceUpdateRemoteProductSendImage', $remote_id, $this->site->name, implode('; ', $this->worpressclient->errors));
                        dol_syslog(__METHOD__ . ': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceUpdateRemoteProductSendImage',
                                $remote_id, $this->site->name, implode('; ', $this->worpressclient->errors)), LOG_ERR);
                        return false;
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
        $object->fetch_optionals();

        // Price
        if (!empty($conf->global->PRODUIT_MULTIPRICES)) {
            $price_level = !empty($this->site->price_level) ? $this->site->price_level : 1;
            $price = $object->multiprices[$price_level];
        } else {
            $price = $object->price;
        }

        if ($isProductVariation) { // Variations
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
                'description' => nl2br($object->array_options["options_ecommerceng_description_{$conf->entity}"]),                    // string       Variation description.
                'sku' => $object->ref,                                  // string       Unique identifier.
                'regular_price' => $price,                              // string       Variation regular price.
                //'sale_price' => '',                                     // string       Variation sale price.
                //'date_on_sale_from' => '',                              // date-time    Start date of sale price, in the site’s timezone.
                //'date_on_sale_from_gmt' => '',                          // date-time    Start date of sale price, as GMT.
                //'date_on_sale_to' => '',                                // date-time    End date of sale price, in the site’s timezone.
                //'date_on_sale_to_gmt' => '',                            // date-time    End date of sale price, in the site’s timezone.
                //'visible' => '',                                        // boolean      Define if the attribute is visible on the “Additional information” tab in the product’s page. Default is true.
                //'virtual' => $object->type == Product::TYPE_SERVICE,    // boolean      If the variation is virtual. Default is false.
                //'downloadable' => '',                                   // boolean      If the variation is downloadable. Default is false.
                //'downloads' => $downloads,                              // array        List of downloadable files. See Product variation - Downloads properties
                //'download_limit' => '',                                 // integer      Number of times downloadable files can be downloaded after purchase. Default is -1.
                //'download_expiry' => '',                                // integer      Number of days until access to downloadable files expires. Default is -1.
                'tax_status' => 'none',                                 // string       Tax status. Options: taxable, shipping and none. Default is taxable.
                //'tax_class' => '',                                      // string       Tax class.
                //'manage_stock' => '',                                   // boolean      Stock management at variation level. Default is false.
                //'stock_quantity' => '',                                 // integer      Stock quantity.
                //'in_stock' => '',                                       // boolean      Controls whether or not the variation is listed as “in stock” or “out of stock” on the frontend. Default is true.
                //'backorders' => '',                                     // string       If managing stock, this controls if backorders are allowed. Options: no, notify and yes. Default is no.
                'weight' => (!empty($totalWeight) ? $totalWeight : ''),                               // string       Variation weight (kg).
                //'dimensions' => $dimensions,                            // object       Variation dimensions. See Product variation - Dimensions properties
                //'shipping_class' => '',                                 // string       Shipping class slug.
                //'image' => (!empty($images) ? $images[0] : ''),                                     // object       Variation image data. See Product variation - Image properties
                //'attributes' => $attributes,                            // array        List of attributes. See Product variation - Attributes properties
                //'menu_order' => '',                                     // integer      Menu order, used to custom sort products.
                //'meta_data' => $meta_data,                              // array        Meta data. See Product variation - Meta data properties
            ];

            if (!empty($images)) {
                $variationData['image'] = $images[0];
            }

            // Set tax
            if (!empty($object->array_options["options_ecommerceng_tax_class_{$this->site->id}_{$conf->entity}"])) {
                $variationData['tax_status'] = 'taxable';
                $variationData['tax_class'] = $object->array_options["options_ecommerceng_tax_class_{$this->site->id}_{$conf->entity}"];
            }

            // Product
            // 'name'    => $object->label,			                    // string		Product name.
            // 'status'  => $object->status ? 'publish' : 'pending',	// string		Product status (post status). Options: draft, pending, private and publish. Default is publish.

            try {
                $result = $this->client->put("products/$remote_product_id/variations/$remote_product_variation_id", $variationData);
            } catch (HttpClientException $fault) {
                $this->errors[] = $langs->trans('ECommerceWoocommerceUpdateRemoteProductVariation', $remote_product_variation_id, $remote_product_id, $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
                dol_syslog(__METHOD__ .
                    ': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceUpdateRemoteProductVariation', $remote_product_variation_id, $remote_product_id, $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
                    ' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
                return false;
            }
        } else { // Product
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
                'status' => (!empty($status) ? $status : ''), //$object->status ? 'publish' : 'pending',	// string		Product status (post status). Options: draft, pending, private and publish. Default is publish.
                //'featured'              => false,		                            // boolean		Featured product. Default is false.
                //'catalog_visibility'    => '',                                      // string		Catalog visibility. Options: visible, catalog, search and hidden. Default is visible.
                'description' => nl2br($object->array_options["options_ecommerceng_description_{$conf->entity}"]),                    // string		Product description.
                'short_description' => nl2br($object->array_options["options_ecommerceng_short_description_{$conf->entity}"]),                                      // string		Product short description.
                'sku' => $object->ref,                            // string		Unique identifier.
                'regular_price' => $price,                          // string		Product regular price.
                //'sale_price'            => '',                                      // string		Product sale price.
                //'date_on_sale_from'     => '',                                      // date-time	Start date of sale price, in the site’s timezone.
                //'date_on_sale_from_gmt' => '',                                      // date-time	Start date of sale price, as GMT.
                //'date_on_sale_to'       => '',                                      // date-time	End date of sale price, in the site’s timezone.
                //'date_on_sale_to_gmt'   => '',                                      // date-time	End date of sale price, in the site’s timezone.
                //'virtual'               => $object->type == Product::TYPE_SERVICE,  // boolean		If the product is virtual. Default is false.
                //'downloadable'          => false,                                   // boolean		If the product is downloadable. Default is false.
                //'downloads'             => $downloads,                              // array		List of downloadable files. See Product - Downloads properties
                //'download_limit'        => -1,                                      // integer		Number of times downloadable files can be downloaded after purchase. Default is -1.
                //'download_expiry'       => -1,                                      // integer		Number of days until access to downloadable files expires. Default is -1.
                //'external_url'          => '',                                      // string		Product external URL. Only for external products.
                //'button_text'           => '',                                      // string		Product external button text. Only for external products.
                'tax_status' => 'none',                                  // string		Tax status. Options: taxable, shipping and none. Default is taxable.
                //'tax_class'             => '',                                      // string		Tax class.
                //'manage_stock'          => false,                                   // boolean		Stock management at product level. Default is false.
                //'stock_quantity'        => $object->stock_reel,                     // integer		Stock quantity.
                //'in_stock'              => $object->stock_reel > 0,                 // boolean		Controls whether or not the product is listed as “in stock” or “out of stock” on the frontend. Default is true.
                //'backorders'            => '',                                      // string		If managing stock, this controls if backorders are allowed. Options: no, notify and yes. Default is no.
                //'sold_individually'     => false,                                   // boolean		Allow one item to be bought in a single order. Default is false.
                'weight' => (!empty($totalWeight) ? $totalWeight : ''),                            // string		Product weight (kg).
                //'dimensions'            => $dimensions,                             // object		Product dimensions. See Product - Dimensions properties
                //'shipping_class'        => '',                                      // string		Shipping class slug.
                //'reviews_allowed'       => true,                                    // boolean		Allow reviews. Default is true.
                //'upsell_ids'            => [],                                      // array		List of up-sell products IDs.
                //'cross_sell_ids'        => [],                                      // array		List of cross-sell products IDs.
                //'parent_id'             => 0,                                       // integer		Product parent ID.
                //'purchase_note'         => '',                                      // string		Optional note to send the customer after purchase.
                'categories' => $categories,                             // array		List of categories. See Product - Categories properties
                //'tags'                  => $tags,                                   // array		List of tags. See Product - Tags properties
                'images' => (!empty($images) ? $images : array()),                    // object		List of images. See Product - Images properties
                //'attributes'            => $attributes,			                    // array		List of attributes. See Product - Attributes properties
                //'default_attributes'    => $default_attributes,			            // array		Defaults variation attributes. See Product - Default attributes properties
                //'menu_order'            => 0,			                            // integer		Menu order, used to custom sort products.
                //'meta_data'             => $meta_data,                              // array		Meta data. See Product - Meta data properties
            ];

            // Set tax
            if (!empty($object->array_options["options_ecommerceng_tax_class_{$this->site->id}_{$conf->entity}"])) {
                $productData['tax_status'] = 'taxable';
                $productData['tax_class'] = $object->array_options["options_ecommerceng_tax_class_{$this->site->id}_{$conf->entity}"];
            }

            try {
                $result = $this->client->put("products/$remote_product_id", $productData);
            } catch (HttpClientException $fault) {
                $this->errors[] = $langs->trans('ECommerceWoocommerceUpdateRemoteProduct', $remote_product_id, $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
                dol_syslog(__METHOD__ .
                    ': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceUpdateRemoteProduct', $remote_product_id, $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
                    ' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
                return false;
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
     *
     * @return  boolean                         True or false
     */
    public function updateRemoteStockProduct($remote_id, $object)
    {
        dol_syslog(__METHOD__ . ": Update stock of the remote product ID $remote_id for MouvementStock ID {$object->id}, new qty: {$object->qty_after} for site ID {$this->site->id}", LOG_DEBUG);
        global $langs, $user;

        if (preg_match('/^(\d+)\|(\d+)$/', $remote_id, $idsProduct) == 1) {
            // Variations
            $variationData = [
                //'manage_stock'      => '',                      // boolean      Stock management at variation level. Default is false.
                'stock_quantity' => $object->qty_after,         // integer      Stock quantity.
                'in_stock' => $object->qty_after > 0,           // boolean      Controls whether or not the variation is listed as “in stock” or “out of stock” on the frontend. Default is true.
                //'backorders'        => '',                      // string       If managing stock, this controls if backorders are allowed. Options: no, notify and yes. Default is no.
            ];

            try {
                $result = $this->client->put("products/$idsProduct[1]/variations/$idsProduct[2]", $variationData);
            } catch (HttpClientException $fault) {
                $this->errors[] = $langs->trans('ECommerceWoocommerceUpdateRemoteStockProductVariation', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
                dol_syslog(__METHOD__ .
                    ': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceUpdateRemoteStockProductVariation', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
                    ' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
                return false;
            }
        } else {
            $productData = [
                //'manage_stock'      => false,                   // boolean      Stock management at product level. Default is false.
                'stock_quantity' => $object->qty_after,         // integer      Stock quantity.
                'in_stock' => $object->qty_after > 0,           // boolean      Controls whether or not the product is listed as “in stock” or “out of stock” on the frontend. Default is true.
                //'backorders'        => '',                      // string       If managing stock, this controls if backorders are allowed. Options: no, notify and yes. Default is no.
            ];

            try {
                $result = $this->client->put("products/$remote_id", $productData);
            } catch (HttpClientException $fault) {
                $this->errors[] = $langs->trans('ECommerceWoocommerceUpdateRemoteStockProduct', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
                dol_syslog(__METHOD__ .
                    ': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceUpdateRemoteStockProduct', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
                    ' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
                return false;
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

        try {
            //$result = $this->client->put("customers/$remote_id", $companyData);
        } catch (HttpClientException $fault) {
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
                try {
                    $result = $this->client->put("customers/$idsCustomer[1]", $contactData);
                } catch (HttpClientException $fault) {
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

        $status = '';
        switch ($object->statut) {
            //case Commande::STOCK_NOT_ENOUGH_FOR_ORDER:  $status = ''; break;
            case Commande::STATUS_CANCELED:             $status = 'cancelled'; break;
            case Commande::STATUS_DRAFT:                $status = 'on-hold'; break;
            case Commande::STATUS_VALIDATED:            $status = 'processing'; break;
            //case Commande::STATUS_ACCEPTED:             $status = 'processing'; break;
            case Commande::STATUS_SHIPMENTONPROCESS:    $status = 'processing'; break;
            case Commande::STATUS_CLOSED:               $status = 'completed'; break;
        }

        if (!empty($status)) {
            $object->fetch_optionals();

            $order_status = [
                'pending' => 0,
                'on-hold' => 1,
                'processing' => 2,
                'completed' => 3,
                'cancelled' => 3,
                'refunded' => 3,
                'failed' => 3,
            ];

            $wc_status = $object->array_options["options_ecommerceng_wc_status_{$conf->entity}"];
            if ($order_status[$status] < $order_status[$wc_status]) $status = $wc_status;

            $orderData = [
                'status' => $status,  // string  Order status. Options: pending, processing, on-hold, completed, cancelled, refunded and failed.
            ];

            try {
                $result = $this->client->put("orders/$remote_id", $orderData);
            } catch (HttpClientException $fault) {
                $this->errors[] = $langs->trans('ECommerceWoocommerceUpdateRemoteCommande', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
                dol_syslog(__METHOD__ .
                    ': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceUpdateRemoteCommande', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
                    ' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
                return false;
            }
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

        try {
            $results = $this->clientOld->get('products', ['filter' => ['sku' => $object->ref], 'fields' => 'id']);
        } catch (HttpClientException $fault) {
            $this->errors[] = $langs->trans('ECommerceWoocommerceCheckRemoteProductExist', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
            dol_syslog(__METHOD__ .
                ': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceCheckRemoteProductExist', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
                ' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
            return false;
        }
        $results = isset($results->products) ? $results->products : [];

        if (is_array($results) && count($results) > 0) {
            $remoteId = $results[0]->id;
            if (!$this->updateRemoteProduct($remoteId, $object))
                return false;
        } else {
            // Set weight
            $totalWeight = $object->weight;
            if ($object->weight_units < 50)   // >50 means a standard unit (power of 10 of official unit), > 50 means an exotic unit (like inch)
            {
                $trueWeightUnit = pow(10, $object->weight_units);
                $totalWeight = sprintf("%f", $object->weight * $trueWeightUnit);
            }

            // Product - Meta data properties
            $object->fetch_optionals();

            // Price
            if (!empty($conf->global->PRODUIT_MULTIPRICES)) {
                $price_level = !empty($this->site->price_level) ? $this->site->price_level : 1;
                $price = $object->multiprices[$price_level];
            } else {
                $price = $object->price;
            }

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

            // Product
            $productData = [
                'name' => $object->label,                            // string		Product name.
                //'slug'                  => '',			                            // string		Product slug.
                //'type'                  => '',			                            // string		Product type. Options: simple, grouped, external and variable. Default is simple.
                'status' => (!empty($status) ? $status : ''), //$object->status ? 'publish' : 'pending',	// string		Product status (post status). Options: draft, pending, private and publish. Default is publish.
                //'featured'              => false,		                            // boolean		Featured product. Default is false.
                //'catalog_visibility'    => '',                                      // string		Catalog visibility. Options: visible, catalog, search and hidden. Default is visible.
                'description' => $object->array_options["options_ecommerceng_description_{$conf->entity}"],                    // string		Product description.
                'short_description' => $object->array_options["options_ecommerceng_short_description_{$conf->entity}"],                                      // string		Product short description.
                'sku' => $object->ref,                            // string		Unique identifier.
                'regular_price' => $price,                          // string		Product regular price.
                //'sale_price'            => '',                                      // string		Product sale price.
                //'date_on_sale_from'     => '',                                      // date-time	Start date of sale price, in the site’s timezone.
                //'date_on_sale_from_gmt' => '',                                      // date-time	Start date of sale price, as GMT.
                //'date_on_sale_to'       => '',                                      // date-time	End date of sale price, in the site’s timezone.
                //'date_on_sale_to_gmt'   => '',                                      // date-time	End date of sale price, in the site’s timezone.
                //'virtual'               => $object->type == Product::TYPE_SERVICE,  // boolean		If the product is virtual. Default is false.
                //'downloadable'          => false,                                   // boolean		If the product is downloadable. Default is false.
                //'downloads'             => $downloads,                              // array		List of downloadable files. See Product - Downloads properties
                //'download_limit'        => -1,                                      // integer		Number of times downloadable files can be downloaded after purchase. Default is -1.
                //'download_expiry'       => -1,                                      // integer		Number of days until access to downloadable files expires. Default is -1.
                //'external_url'          => '',                                      // string		Product external URL. Only for external products.
                //'button_text'           => '',                                      // string		Product external button text. Only for external products.
                'tax_status' => 'none',                                  // string		Tax status. Options: taxable, shipping and none. Default is taxable.
                //'tax_class'             => '',                                      // string		Tax class.
                //'manage_stock'          => false,                                   // boolean		Stock management at product level. Default is false.
                //'stock_quantity'        => $object->stock_reel,                     // integer		Stock quantity.
                //'in_stock'              => $object->stock_reel > 0,                 // boolean		Controls whether or not the product is listed as “in stock” or “out of stock” on the frontend. Default is true.
                //'backorders'            => '',                                      // string		If managing stock, this controls if backorders are allowed. Options: no, notify and yes. Default is no.
                //'sold_individually'     => false,                                   // boolean		Allow one item to be bought in a single order. Default is false.
                'weight' => (!empty($totalWeight) ? $totalWeight : ''),                            // string		Product weight (kg).
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

            // Set tax
            if (!empty($object->array_options["options_ecommerceng_tax_class_{$this->site->id}_{$conf->entity}"])) {
                $productData['tax_status'] = 'taxable';
                $productData['tax_class'] = $object->array_options["options_ecommerceng_tax_class_{$this->site->id}_{$conf->entity}"];
            }

            try {
                $res = $this->client->post("products", $productData);
                $remoteId = $res->id;
            } catch (HttpClientException $fault) {
                $this->errors[] = $langs->trans('ECommerceWoocommerceCreateRemoteProduct', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
                dol_syslog(__METHOD__ .
                    ': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceCreateRemoteProduct', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
                    ' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
                return false;
            }
        }

        dol_syslog(__METHOD__ . ": end", LOG_DEBUG);
        return $remoteId;
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
        try {
            $result = $this->client->put("orders/$order_remote_id", $commandeData);
        } catch (HttpClientException $fault) {
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
     * Get tax rate from tax class name
     *
     * @param  string   $tax_class      Tax class name
     * @param  string   $tax_status     Tax status
     *
     * @return float                    Tax rate
     */
    private function getTaxRate($tax_class, $tax_status = 'taxable')
    {
        //dol_syslog(__METHOD__ . ": Get tax rate, tax_classe: $tax_class, tax_status: $tax_status", LOG_DEBUG);
        global $conf, $mysoc;

        $tax_rate = 0;

        // $tax_status => Tax status. Options: taxable, shipping and none. Default is taxable
        if ($tax_status != 'none') {
            $tax_class = !empty($tax_class) ? $tax_class : 'standard';
            $tax_rate = '';

            // Retrieve all woocommerce tax classes
            if (!isset($this->woocommerceTaxes) || !isset($this->woocommerceTaxes['classes'][$tax_class])) {
                $this->setWoocommerceTaxes();
            }

            // Get woocommerce tax if one only
            if (isset($this->woocommerceTaxes['classes'][$tax_class]) && count($this->woocommerceTaxes['classes'][$tax_class]) == 1) {
                $tax_rate = $this->woocommerceTaxes['classes'][$tax_class];
                $tax_rate = array_values($tax_rate);
                $tax_rate = doubleval($tax_rate[0]->rate);

                // Get near dolibarr tax for woocommerce tax rate
                $tax = $this->_getClosestDolibarrTaxRate($tax_rate);
                if (isset($tax)) {
                    $tax_rate = $tax;
                }
            }

            if ($tax_rate == '') {
                $tax_rate = $conf->global->ECOMMERCE_WOOCOMMERCE_DEFAULT_TVA;
            }
        }

        //dol_syslog(__METHOD__ . ": end, return $tax_rate", LOG_DEBUG);
        return $tax_rate;
    }

    /**
     * Get tax class for show in extrafields
     *
     * @param  string   $tax_class      Tax class name
     * @param  string   $tax_status     Tax status
     *
     * @return string                   Tax class name
     */
    private function getTaxClass($tax_class, $tax_status = 'taxable')
    {
        //dol_syslog(__METHOD__ . ": Get tax class name, tax_class: $tax_class, tax_status: $tax_status", LOG_DEBUG);

        // $tax_status => Tax status. Options: taxable, shipping and none. Default is taxable
        if ($tax_status != 'none') {
            $tax_class = !empty($tax_class) ? $tax_class : 'standard';
        } else {
            $tax_class = '';
        }

        //dol_syslog(__METHOD__ . ": end, return $tax_class", LOG_DEBUG);
        return $tax_class;
    }

    /**
     * Calcul tax rate and return the closest dolibarr tax rate.
     *
     * @param   float   $priceHT        Price HT
     * @param   float   $taxAmount      Tax amount
     *
     * @return  float                   Tax rate
     */
    private function getClosestDolibarrTaxRate($priceHT, $taxAmount)
    {
        //dol_syslog(__METHOD__ . ": Get closest dolibarr tax rate, priceHT: $priceHT, priceHT: $taxAmount", LOG_DEBUG);
        $tax_rate = 0;
        if ($taxAmount != 0) {
            //calcul tax rate from remote site
            $shipping_tax_rate = ($taxAmount / $priceHT) * 100;

            // Get near dolibarr tax for woocommerce tax rate
            $tax = $this->_getClosestDolibarrTaxRate($shipping_tax_rate);
            if (isset($tax)) {
                $tax_rate = $tax;
            }
        }

        //dol_syslog(__METHOD__ . ": end, return $tax_rate", LOG_DEBUG);
        return $tax_rate;
    }

    /**
     * Retrieve all Dolibarr tax rates
     *
     * @return  void
     */
    private function setDolibarrTaxes()
    {
        //dol_syslog(__METHOD__ . ": Retrieve all Dolibarr tax rates", LOG_DEBUG);

   		$resql = $this->db->query("SELECT DISTINCT taux FROM ".MAIN_DB_PREFIX."c_tva ORDER BY taux DESC");
   		if ($resql) {
            $taxesTable = [];

            while ($tax = $this->db->fetch_object($resql)) {
                $taxesTable[] = $tax->taux;
            }

            $this->dolibarrTaxes = $taxesTable;
        }

        //dol_syslog(__METHOD__ . ": end", LOG_DEBUG);
    }

    /**
     * Retrieve all Dolibarr tax rates
     *
     * @return  void
     */
    /*    private function setDolibarrTaxes()
    {
        dol_syslog(__METHOD__ . ": Retrieve all Dolibarr tax rates", LOG_DEBUG);

   		$resql = $this->db->query("SELECT t.*, c.code AS country FROM ".MAIN_DB_PREFIX."c_tva AS t LEFT JOIN ".MAIN_DB_PREFIX."c_country AS c ON t.fk_pays = c.rowid");
   		if ($resql) {
            $taxesTable = [ 'taxes' => [], 'countries' => [] ];

            while ($tax = $this->db->fetch_array($resql)) {
                $taxesTable['taxes'][] = $tax;
                if (!empty($tax['country'])) {
                    $taxesTable['countries'][$tax['country']][] = $tax;
                }
            }

            $this->dolibarrTaxes = $taxesTable;
        }

        dol_syslog(__METHOD__ . ": end", LOG_DEBUG);
    }*/

    /**
     * Update all Woocommerce tax classes in dict
     *
     * @return array|false    List of woocommerce tax class or false if error
     */
    public function getAllWoocommerceTaxClass()
    {
        dol_syslog(__METHOD__ . ": Retrieve all Woocommerce tax classes", LOG_DEBUG);
        global $langs;

        try {
            $tax_classes = $this->client->get('taxes/classes');
        } catch (HttpClientException $fault) {
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
     * Retrieve all Woocommerce tax rates
     *
     * @return boolean
     */
    private function setWoocommerceTaxes()
    {
        dol_syslog(__METHOD__ . ": Retrieve all Woocommerce tax rates", LOG_DEBUG);
        global $conf, $langs;

        $nb_max_by_request = empty($conf->global->ECOMMERCENG_MAXSIZE_MULTICALL) ? 100 : min($conf->global->ECOMMERCENG_MAXSIZE_MULTICALL, 100);

        $taxesTable = [ 'taxes' => [], 'classes' => [], 'countries' => [], 'states' => [], 'postcodes' => [], 'cities' => []];
        $idxPage = 0;
        do {
            $idxPage++;
            try {
                $taxes = $this->client->get('taxes',
                    [
                        'page' => $idxPage,
                        'per_page' => $nb_max_by_request,
                    ]
                );
            } catch (HttpClientException $fault) {
                $this->errors[] = $langs->trans('ECommerceWoocommerceGetWoocommerceTaxes', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage());
                dol_syslog(__METHOD__ .
                    ': Error:' . $langs->transnoentitiesnoconv('ECommerceWoocommerceGetWoocommerceTaxes', $this->site->name, $fault->getCode() . ': ' . $fault->getMessage()) .
                    ' - Request:' . json_encode($fault->getRequest()) . ' - Response:' . json_encode($fault->getResponse()), LOG_ERR);
                return false;
            }

            foreach ($taxes as $tax) {
                $id = $tax->id;
                unset($tax->_links);

                $taxesTable['taxes'][$id] = $tax;
                if (!empty($tax->class)) {
                    $taxesTable['classes'][$tax->class][$id] = $tax;
                }
                if (!empty($tax->country)) {
                    $taxesTable['countries'][$tax->country][$id] = $tax;
                }
                if (!empty($tax->state)) {
                    $taxesTable['states'][$tax->state][$id] = $tax;
                }
                if (!empty($tax->postcode)) {
                    $taxesTable['postcodes'][$tax->postcode][$id] = $tax;
                }
                if (!empty($tax->city)) {
                    $taxesTable['cities'][$tax->city][$id] = $tax;
                }
            }
        } while (!empty($taxes));

        $this->woocommerceTaxes = $taxesTable;

        dol_syslog(__METHOD__ . ": end", LOG_DEBUG);
        return true;
    }

    /**
     * Get closest dolibarr tax rate
     *
     * @param  string   $tax_rate       Tax rate
     *
     * @return float                    Closest dolibarr tax rate
     */
    private function _getClosestDolibarrTaxRate($tax_rate)
    {
        //dol_syslog(__METHOD__ . ": Get closest dolibarr tax rate, tax_rate: $tax_rate", LOG_DEBUG);

        $tax = null;

        // Retrieve all dolibarr tax
        if (!isset($this->dolibarrTaxes)) {
            $this->setDolibarrTaxes();
        }

        // Get closest dolibarr tax for woocommerce tax
        if (is_array($this->dolibarrTaxes) && count($this->dolibarrTaxes) > 0) {
            $closestTax = 0;
            foreach ($this->dolibarrTaxes as $tax) {
                if (abs($tax - $tax_rate) < abs($tax_rate - $closestTax)) {
                    $closestTax = $tax;
                }
            }
            $tax = $closestTax;
        }

        //dol_syslog(__METHOD__ . ": end, return ".(isset($tax)?json_encode($tax):'null'), LOG_DEBUG);
        return $tax;
    }

    /**
     * Get closest dolibarr tax
     *
     * @param  string   $country_code   Country code
     * @param  string   $tax_rate       Tax rate
     *
     * @return float                    Near dolibarr tax
     */
    /*private function getClosestDolibarCountryTax($country_code, $tax_rate)
    {
        dol_syslog(__METHOD__ . ": Get closest dolibarr tax rate, country_code: $country_code, tax_rate: $tax_rate", LOG_DEBUG);
        global $langs;

        $tax = null;

        // Get country code from default language if empty
        if (empty($country_code)) $country_code = substr($langs->defaultlang, -2);

        // Retrieve all dolibarr tax
        if (!isset($this->dolibarrTaxes) || !isset($this->dolibarrTaxes['countries'][$country_code])) {
            $this->setDolibarrTaxes();
        }

        // Get closest dolibarr tax for woocommerce tax
        $dolibarrTaxes = $this->dolibarrTaxes['countries'][$country_code];
        if (is_array($dolibarrTaxes)) {
            $closestTaxes = [];
            foreach ($dolibarrTaxes as $tax) {
                $near = $tax['taux'] - $tax_rate;
                if (!isset($closestTaxes[$near]) || $closestTaxes[$near]['taux'] < $tax['taux']) {
                    $nearTaxes[$near] = $tax;
                }
            }
            ksort($closestTaxes);
            reset($closestTaxes);
            $tax = $closestTaxes[0];
        }

        dol_syslog(__METHOD__ . ": end, return ".(isset($tax)?json_encode($tax):'null'), LOG_DEBUG);
        return $tax;
    }*/

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

    public function __destruct()
    {
        ini_set("memory_limit", "528M");
    }
}
