<?php
/**
 * WordPress REST API Client
 *
 */

require_once DOL_DOCUMENT_ROOT.'/includes/OAuth/bootstrap.php';
dol_include_once('/ecommerceng/includes/CurlClientEx.php');

use OAuth\Common\Storage\DoliStorage;
use OAuth\Common\Consumer\Credentials;
use OAuth\Common\Http\Client\CurlClientEx;
use OAuth\Common\Http\Uri\Uri;

/**
 * REST API WordPress Client class.
 *
 */
class WordPressClient
{
    /**
     * WordPress REST API Client version.
     */
    const VERSION = '1.0.0';

    /**
     * WordPress OAuth Service name.
     */
    const OAUTH_SERVICENAME_WORDPRESS = 'WordPress';

    /**
     * Base Api Uri.
     *
     * @var string
     */
    public $baseApiUri;

    /**
     * Token storage.
     *
     * @var DoliStorage
     */
    public $storage;

    /**
     * The credentials.
     *
     * @var Credentials
     */
    public $credentials;

    /**
     * Http client.
     *
     * @var CurlClientEx
     */
    public $httpClient;

    /**
     * API service.
     *
     * @var \OAuth\OAuth2\Service\WordPress
     */
    public $apiService;

    /**
     * Errors.
     *
     * @var array
     */
    public $errors;

    /**
     * Initialize client.
     *
     * @param string     $baseApiUri
     */
    public function __construct($baseApiUri, $oauth_id, $oauth_secret, $callbackUrl)
    {
        global $db, $conf;

        $this->errors = array();

        // Token storage
        $this->storage = new DoliStorage($db, $conf);

        // Setup the credentials for the requests
        $this->credentials = new Credentials(
            $oauth_id,
            $oauth_secret,
            $callbackUrl
        );

        // Setup the api service
        $this->baseApiUri = $baseApiUri.(substr($baseApiUri, -1, 1)!='/'?'/':'').'wp-json/wp/v2';
        $serviceFactory = new \OAuth\ServiceFactory();
        $this->httpClient = new CurlClientEx();
        $serviceFactory->setHttpClient($this->httpClient);
        $this->apiService = $serviceFactory->createService(self::OAUTH_SERVICENAME_WORDPRESS, $this->credentials, $this->storage, array(), new Uri($baseApiUri));
    }

    /**
     * POST method.
     *
     * @param string $endpoint API endpoint.
     * @param array  $data     Request data.
     *
     * @return array
     */
    public function post($endpoint, $data)
    {
        $this->errors = array();
        return $this->request($endpoint, 'POST', $data);
    }

    /**
     * POST media method.
     *
     * @param string $endpoint   API endpoint.
     * @param string $filepath   File path.
     * @param array  $data       Request data.
     *
     * @return array
     */
    public function postmedia($endpoint, $filepath, $data)
    {
        $responseData = null;
        $this->errors = array();

        // Set File
        if (file_exists($filepath)) {
            if (function_exists('curl_file_create')) { // php 5.5+
                $cFile = curl_file_create($filepath);
            } else {
                $cFile = '@' . realpath($filepath);
            }
            $data['file'] = $cFile;
        } else {
            $this->errors[] = array('File not found ("'.$filepath.'").');
            return $responseData;
        }

        $extraHeaders = array("Content-Type" => "multipart/form-data");

        return $this->request($endpoint, 'POST', $data, $extraHeaders);
    }

    /**
     * PUT method.
     *
     * @param string $endpoint API endpoint.
     * @param array  $data     Request data.
     *
     * @return array
     */
    public function put($endpoint, $data)
    {
        $this->errors = array();
        return $this->request($endpoint, 'PUT', $data);
    }

    /**
     * GET method.
     *
     * @param string $endpoint   API endpoint.
     * @param array  $parameters Request parameters.
     *
     * @return array
     */
    public function get($endpoint, $parameters = [])
    {
        $this->errors = array();
        return $this->request($endpoint, 'GET', $parameters);
    }

    /**
     * DELETE method.
     *
     * @param string $endpoint   API endpoint.
     * @param array  $parameters Request parameters.
     *
     * @return array
     */
    public function delete($endpoint, $parameters = [])
    {
        $this->errors = array();
        return $this->request($endpoint, 'DELETE', $parameters);
    }

    /**
     * OPTIONS method.
     *
     * @param string $endpoint API endpoint.
     *
     * @return array
     */
    public function options($endpoint)
    {
        $this->errors = array();
        return $this->request($endpoint, 'OPTIONS');
    }

    /**
     * Request.
     *
     * @param string   $endpoint     API endpoint.
     * @param string   $method       HTTP method
     * @param array    $body         Request body if applicable (an associative array will
     *                               automatically be converted into a urlencoded body)
     * @param array    $extraHeaders Extra headers if applicable. These will override service-specific
     *                               any defaults.
     * @return array
     */
    public function request($endpoint, $method, $body = [], $extraHeaders = [])
    {
        $responseData = null;

        // Check if we have auth token
        try {
            $token = $this->storage->retrieveAccessToken(self::OAUTH_SERVICENAME_WORDPRESS);
        } catch (Exception $e) {
            $this->errors[] = $e->getMessage();
            return $responseData;
        }

        // Is token expired or will token expire in the next 30 seconds
        $expire = ($token->getEndOfLife() !== -9002 && $token->getEndOfLife() !== -9001 && time() > ($token->getEndOfLife() - 30));

        // Token expired so we refresh it
        if ($expire) {
            try {
                $this->httpClient->setHttpPostBuildQuery(true);
                $token = $this->apiService->refreshAccessToken($token);
            } catch (Exception $e) {
                $this->errors[] = $e->getMessage();
                return $responseData;
            }
        }

        // Send a request with api
        try {
            $this->httpClient->setHttpPostBuildQuery(false);
            $response = $this->apiService->request($this->baseApiUri.(substr($this->baseApiUri, -1, 1)!='/'?'/':'').$endpoint, $method, $body, $extraHeaders);
            $responseData = json_decode($response, true);
        } catch (Exception $e) {
            $this->errors[] = $e->getMessage();
        }

        return $responseData;
    }
}
