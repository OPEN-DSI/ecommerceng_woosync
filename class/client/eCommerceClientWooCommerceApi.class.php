<?php
/* Copyright (C) 2023       Open-Dsi		<support@open-dsi.fr>
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
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *      \file       htdocs/ecommerceng/class/business/eCommerceClientWooCommerceApi.class.php
 *      \ingroup    ecommerceng
 *      \brief      This file for managing Client WooCommerce API
 */

dol_include_once('/ecommerceng/class/client/eCommerceClientApi.class.php');
dol_include_once('/ecommerceng/includes/oauth-subscriber-woocommerce/src/Oauth1.php');
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Subscriber\Oauth\Oauth1;

/**
 * 	Class to manage Client WooCommerce API
 */
class eCommerceClientWooCommerceApi extends eCommerceClientApi
{
	/**
	 * @var string  API version
	 */
	public $api_version;
	/**
	 * @var string  Authentication type
	 */
	public $authentication_type;
	/**
	 * @var string  Authentication login
	 */
	public $authentication_login;
	/**
	 * @var string  Authentication password
	 */
	public $authentication_password;

	/**
	 *  Connect to the Wordpress
	 *
	 * @param   eCommerceSite   $site   eCommerceSite object
	 * @return	int		                <0 if KO, >0 if OK
	 */
	public function connection($site)
	{
		global $conf, $langs;
		dol_syslog(__METHOD__ . " - authentication_type={$site->authentication_type}, user_name={$site->user_name}, user_password={$site->user_password}, timeout={$site->timeout}", LOG_DEBUG);
		$this->errors = array();

		$this->api_version = !empty($site->api_version) ? $site->api_version : 'v3';
		$this->authentication_type = !empty($site->authentication_type) ? $site->authentication_type : 'oauth1_header';
		$this->authentication_login = $site->user_name;
		$this->authentication_password = $site->user_password;
		$timeout = $site->timeout > 0 ? $site->timeout : 30;

		$this->api_url = rtrim($site->webservice_address, '/');
		$this->api_url_prefix = '/wp-json/wc/' . $this->api_version;

		try {
			$options = [
				// Base URI is used with relative requests
				'base_uri' => $this->api_url,
				// You can set any number of default request options.
				'timeout' => $timeout,
			];
			if (!empty($conf->global->ECOMMERCENG_WOOCOMMERCE_NO_VERIFY_SSL)) $options['verify'] = false;

			if (in_array($this->authentication_type, [ 'oauth1_header', 'oauth1_query' ])) {
				$stack = HandlerStack::create();

				$middleware = new Oauth1([
					'consumer_key'    => $this->authentication_login,
					'consumer_secret' => $this->authentication_password,
					'request_method' => $this->authentication_type == 'oauth1_header' ? Oauth1::REQUEST_METHOD_HEADER : Oauth1::REQUEST_METHOD_QUERY,
					'signature_method' => Oauth1::SIGNATURE_METHOD_HMACSHA256,
					'api_version' => $this->api_version,
				]);
				$stack->push($middleware);

				$options['handler'] = $stack;
				$options['auth'] = 'oauth';
			}

			$this->client = new Client($options);
		} catch (Exception $e) {
			$this->errors[] = $langs->trans('ECommerceErrorConnectAPI', $site->name);
			$this->errors[] = $e->getMessage();
			dol_syslog(__METHOD__ . " Error: " . $this->errorsToString(), LOG_ERR);
			return -1;
		}

		return 1;
	}

	/**
	 *  Send to the Api
	 *
	 * @param   string  $method     						Method request
	 * @param   string  $url        						Url request
	 * @param   array   $options    						Options request
	 * @param   bool  	$without_prefix						Without api url prefix
	 * @param   int  	$status_code						Status code returned
	 * @param   int  	$error_info							Error info returned
	 * @return	array                 						null if KO otherwise result data
	 */
	public function sendToApi($method, $url, $options = [], $without_prefix = false, &$status_code = null, &$error_info = null)
	{
		dol_syslog(__METHOD__ . " method=" . $method . " url=" . $url . " options=" . json_encode($options), LOG_DEBUG);

		if ($this->authentication_type == 'basic') {
			$options['headers']['Authorization'] = 'Basic ' . base64_encode($this->authentication_login . ':' . $this->authentication_password);
		} elseif ($this->authentication_type == 'query') {
			$options[GuzzleHttp\RequestOptions::QUERY]['consumer_key'] = $this->authentication_login;
			$options[GuzzleHttp\RequestOptions::QUERY]['consumer_secret'] = $this->authentication_password;
		}

		return parent::sendToApi($method, $url, $options, $without_prefix, $status_code, $error_info);
	}
}
