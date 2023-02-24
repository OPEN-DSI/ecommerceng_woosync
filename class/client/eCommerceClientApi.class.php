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
 *      \file       htdocs/ecommerceng/class/business/eCommerceClientApi.class.php
 *      \ingroup    ecommerceng
 *      \brief      This file for managing Client API
 */

dol_include_once('/ecommerceng/class/business/eCommerceUtils.class.php');
if (!class_exists('ComposerAutoloaderInite5f8183b6b110d1bbf5388358e7ebc94', false)) dol_include_once('/ecommerceng/vendor/autoload.php');
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * 	Class to manage Client API
 */
class eCommerceClientApi
{
	/**
	 * @var DoliDB Database handler.
	 */
	public $db;
	/**
	 * @var string Error
	 */
	public $error = '';
	/**
	 * @var array Errors
	 */
	public $errors = array();

	/**
	 * @var bool  Debug mode
	 */
	public $debug_mode = false;

	/**
	 * @var Client  Client REST handler
	 */
	public $client;
	/**
	 * @var string  API URL
	 */
	public $api_url;
	/**
	 * @var string  API URL prefix for requesting the API
	 */
	public $api_url_prefix;

	const METHOD_GET = 'GET';
	const METHOD_HEAD = 'HEAD';
	const METHOD_DELETE = 'DELETE';
	const METHOD_PUT = 'PUT';
	const METHOD_PATCH = 'PATCH';
	const METHOD_POST = 'POST';


	/**
	 * Constructor
	 *
	 * @param	DoliDB		$_db			Database handler
	 * @param	bool		$debug_mode		Debug mode
	 */
	public function __construct($_db, $debug_mode = false)
	{
		global $db;
		$this->db = is_object($_db) ? $_db : $db;
		$this->debug_mode = !empty($debug_mode);
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
		dol_syslog(__METHOD__ . " - method=" . $method . " url=" . $url . " options=" . json_encode($options) . " without_prefix=" . $without_prefix, LOG_NOTICE);
		global $conf, $langs;

		$stopwatch_id = -1;
		try {
			if (isset($status_code)) $status_code = 0;
			$request_url = rtrim($this->api_url, '/') . '/' . ($without_prefix ? '' : trim($this->api_url_prefix, '/') . '/') . ltrim($url, '/');

			// Disabled send data to site
			if (in_array($method, [self::METHOD_POST, self::METHOD_PUT, self::METHOD_DELETE, self::METHOD_PATCH]) && !empty($conf->global->ECOMMERCE_DISABLED_SEND_DATA_TO_API)) {
				return [];
			}

			$stopwatch_id = eCommerceUtils::startStopwatch(__METHOD__ . " - {$method} {$request_url}");
			switch ($method) {
				case self::METHOD_HEAD:
					$response = $this->client->head($request_url, $options);
					break;
				case self::METHOD_GET:
					$response = $this->client->get($request_url, $options);
					break;
				case self::METHOD_POST:
					$response = $this->client->post($request_url, $options);
					break;
				case self::METHOD_PUT:
					$response = $this->client->put($request_url, $options);
					break;
				case self::METHOD_DELETE:
					$response = $this->client->delete($request_url, $options);
					break;
				case self::METHOD_PATCH:
					$response = $this->client->patch($request_url, $options);
					break;
				default:
					$this->errors[] = 'Bad REST Method';
					dol_syslog(__METHOD__ . " Errors: " . $this->errorsToString(), LOG_ERR);
					return null;
			}
			eCommerceUtils::stopAndLogStopwatch($stopwatch_id);

			if (isset($status_code)) $status_code = $response->getStatusCode();
			$request_data = trim($response->getBody()->getContents());
			$msg_error =  "Method: " . $method . " Url: " . $request_url . " - Options: " . json_encode($options) . " - Data: " . $request_data;
			dol_syslog(__METHOD__ . " - " . $msg_error, LOG_DEBUG);

			$data =  json_decode($request_data, true);
			if (!is_array($data)) {
				if ($this->debug_mode) {
					$this->errors[] = $langs->trans('ECommerceErrorBadConvertResult') . ' - ' . $msg_error . ' - Converted data: ' . $data . " - Error: " . json_last_error_msg();
				} else {
					$this->errors[] = $langs->trans('ECommerceErrorBadConvertResult') . " - Error: " . json_last_error_msg();
				}
			}
			return $data;
		} catch (RequestException $e) {
			eCommerceUtils::stopAndLogStopwatch($stopwatch_id);

			$request = $e->getRequest();
			$response = $e->getResponse();

			if (isset($response) && isset($status_code)) $status_code = $response->getStatusCode();
            if (isset($response)) $error_info = json_decode(trim($response->getBody()->getContents()), true);
			if (empty($error_info)) $error_info = [];

			$errors_details = array();
			if (isset($request)) $errors_details[] = $this->_requestToString($request);
			if (isset($response)) $errors_details[] = $this->_responseToString($response);
			else $errors_details[] = '<pre>' . dol_nl2br((string)$e) . '</pre>';

			if ($this->debug_mode) {
				$this->errors = array_merge($this->errors, $errors_details);
			} else {
				if (isset($response)) {
                    $boby = strip_tags($response->getBody());
					$this->errors[] = '<b>' . $langs->trans('ECommerceResponseCode') . ': </b>' . $response->getStatusCode() . '<br>' .
						'<b>' . $langs->trans('ECommerceResponseReasonPhrase') . ': </b>' . $response->getReasonPhrase() .
						(!empty($boby) ? '<br><em>' . $boby . '</em>' : '');
				} else $this->errors[] = $e->getMessage();
			}

			dol_syslog(__METHOD__ . " Error: " . dol_htmlentitiesbr_decode(implode(', ', $errors_details)), LOG_ERR);
			return null;
		} catch (Exception $e) {
			eCommerceUtils::stopAndLogStopwatch($stopwatch_id);
			if ($this->debug_mode) {
				$this->errors[] = (string)$e;
			} else {
				$this->errors[] = $e->getMessage();
			}

			dol_syslog(__METHOD__ . " Error: " . $e, LOG_ERR);
			return null;
		}
	}

	/**
	 *  Format the request to a string
	 *
	 * @param   RequestInterface    $request    Request handler
	 * @return	string		                    Formatted string of the request
	 */
	protected function _requestToString(RequestInterface $request)
	{
		global $langs;

		$out = '';
		$out .= '<b>' . $langs->trans('ECommerceRequestData') . ': </b><br><hr>';
		$out .= '<div style="max-width: 1024px;">';
		$out .= '<b>' . $langs->trans('ECommerceRequestProtocolVersion') . ': </b>' . $request->getProtocolVersion() . '<br>';
		$out .= '<b>' . $langs->trans('ECommerceRequestUri') . ': </b>' . $request->getUri() . '<br>';
		$out .= '<b>' . $langs->trans('ECommerceRequestTarget') . ': </b>' . $request->getRequestTarget() . '<br>';
		$out .= '<b>' . $langs->trans('ECommerceRequestMethod') . ': </b>' . $request->getMethod() . '<br>';
		$out .= '<b>' . $langs->trans('ECommerceRequestHeaders') . ':</b><ul>';
		foreach ($request->getHeaders() as $name => $values) {
			$out .= '<li><b>' . $name . ': </b>' . implode(', ', $values) . '</li>';
		}
		$out .= '</ul>';
		$out .= '<b>' . $langs->trans('ECommerceRequestBody') . ': </b>';
		$out .= '<br><em>' . $request->getBody() . '</em><br>';
		$out .= '</div>';
		return $out;
	}

	/**
	 *  Format the response to a string
	 *
	 * @param   ResponseInterface   $response   Response handler
	 * @return	string		                    Formatted string of the response
	 */
	protected function _responseToString(ResponseInterface $response)
	{
		global $langs;

		$out = '';
		$out .= '<b>' . $langs->trans('ECommerceResponseData') . ': </b><br><hr>';
		$out .= '<div style="max-width: 1024px;">';
		$out .= '<b>' . $langs->trans('ECommerceResponseProtocolVersion') . ': </b>' . $response->getProtocolVersion() . '<br>';
		$out .= '<b>' . $langs->trans('ECommerceResponseCode') . ': </b>' . $response->getStatusCode() . '<br>';
		$out .= '<b>' . $langs->trans('ECommerceResponseReasonPhrase') . ': </b>' . $response->getReasonPhrase() . '<br>';
		$out .= '<b>' . $langs->trans('ECommerceResponseHeaders') . ':</b><ul>';
		foreach ($response->getHeaders() as $name => $values) {
			$out .= '<li><b>' . $name . ': </b>' . implode(', ', $values) . '</li>';
		}
		$out .= '</ul>';
		$out .= '<b>' . $langs->trans('ECommerceResponseBody') . ': </b>';
		$body = json_decode($response->getBody(), true);
		if (is_array($body)) {
			$out .= '<ul>';
			foreach ($body as $name => $values) {
				$out .= '<li><b>' . $name . ': </b>' . (is_array($values) || is_object($values) ? json_encode($values) : $values) . '</li>';
			}
			$out .= '</ul>';
		} else {
			$out .= '<br><em>' . strip_tags($response->getBody()) . '</em><br>';
		}
		$out .= '</div>';
		return $out;
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
