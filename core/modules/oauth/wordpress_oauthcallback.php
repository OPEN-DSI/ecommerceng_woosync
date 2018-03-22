<?php
/*
 * Copyright (C) 2015       Frederic France      <frederic.france@free.fr>
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
 */

/**
 *      \file       htdocs/core/modules/oauth/wordpress_oauthcallback.php
 *      \ingroup    oauth
 *      \brief      Page to get oauth callback
 */

$res=0;
if (! $res && ! empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res=@include($_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php");
if (! $res && file_exists("../main.inc.php")) $res=@include("../main.inc.php");
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");
if (! $res && file_exists("../../../../main.inc.php")) $res=@include("../../../../main.inc.php");
if (! $res && file_exists("../../../../../main.inc.php")) $res=@include("../../../../../main.inc.php");
if (! $res && preg_match('/\/nltechno([^\/]*)\//',$_SERVER["PHP_SELF"],$reg)) $res=@include("../../../dolibarr".$reg[1]."/htdocs/main.inc.php"); // Used on dev env only
if (! $res && preg_match('/\/teclib([^\/]*)\//',$_SERVER["PHP_SELF"],$reg)) $res=@include("../../../dolibarr".$reg[1]."/htdocs/main.inc.php"); // Used on dev env only
if (! $res) die("Include of main fails");
require_once DOL_DOCUMENT_ROOT.'/includes/OAuth/bootstrap.php';
dol_include_once('/ecommerceng/includes/CurlClientEx.php');
dol_include_once('/ecommerceng/class/data/eCommerceSite.class.php');
use OAuth\Common\Storage\DoliStorage;
use OAuth\Common\Consumer\Credentials;
use OAuth\Common\Http\Uri\Uri;
use OAuth\Common\Http\Client\CurlClient;
use OAuth\OAuth2\Service\WordPress;

// Define $urlwithroot
$urlwithouturlroot=preg_replace('/'.preg_quote(DOL_URL_ROOT,'/').'$/i','',trim($dolibarr_main_url_root));
$urlwithroot=$urlwithouturlroot.DOL_URL_ROOT;		// This is to use external domain name found into config file
//$urlwithroot=DOL_MAIN_URL_ROOT;					// This is to use same domain name than current



$action = GETPOST('action', 'alpha');
$backtourl = GETPOST('backtourl', 'alpha');
$siteId = GETPOST('ecommerce_id', 'int');

//LOAD SELECTED SITE
$siteDb = new eCommerceSite($db);
$res = $siteDb->fetch($siteId);
if ($res < 0) {
    setEventMessage("Error: site ecommerce not found", 'errors');
    header('Location: ' . $backtourl);
    exit();
}

/**
 * Create a new instance of the URI class with the current URI, stripping the query string
 */
$uriFactory = new \OAuth\Common\Http\Uri\UriFactory();
//$currentUri = $uriFactory->createFromSuperGlobalArray($_SERVER);
//$currentUri->setQuery('');
//$currentUri = $uriFactory->createFromAbsolute($urlwithroot.'/custom/ecommerceng/core/modules/oauth/wordpress_oauthcallback.php?ecommerce_id='.$siteId);
$currentUri = $uriFactory->createFromAbsolute(dol_buildpath('/custom/ecommerceng/core/modules/oauth/wordpress_oauthcallback.php', 2).'?ecommerce_id='.$siteId);


/**
 * Load the credential for the service
 */

/** @var $serviceFactory \OAuth\ServiceFactory An OAuth service factory. */
$serviceFactory = new \OAuth\ServiceFactory();
$httpClient = new CurlClient();
// TODO Set options for proxy and timeout
// $params=array('CURLXXX'=>value, ...)
//$httpClient->setCurlParameters($params);
$serviceFactory->setHttpClient($httpClient);

// Dolibarr storage
$storage = new DoliStorage($db, $conf);

// Setup the credentials for the requests
$credentials = new Credentials(
    $siteDb->oauth_id,
    $siteDb->oauth_secret,
    $currentUri->getAbsoluteUri()
);

$requestedpermissionsarray=array();
if (GETPOST('state')) $requestedpermissionsarray=explode(',', GETPOST('state'));       // Example: 'userinfo_email,userinfo_profile,cloud_print'. 'state' parameter is standard to retrieve some parameters back
/*if ($action != 'delete' && empty($requestedpermissionsarray))
{
    print 'Error, parameter state is not defined';
    exit;
}*/
//var_dump($requestedpermissionsarray);exit;

// Instantiate the Api service using the credentials, http client and storage mechanism for the token
/** @var $apiService Service */
$apiService = $serviceFactory->createService('WordPress', $credentials, $storage, array(), new Uri($siteDb->webservice_address));

// access type needed to have oauth provider refreshing token
// alos note that a refresh token is sent only after a prompt
//$apiService->setAccessType('offline');

//$apiService->setApprouvalPrompt('force');

$langs->load("oauth");


/*
 * Actions
 */


if ($action == 'delete') 
{
    $storage->clearToken('ECommerce_'.$siteId);
    
    setEventMessages($langs->trans('TokenDeleted'), null, 'mesgs');
    
    header('Location: ' . $backtourl);
    exit();
} 

if (! empty($_GET['code']))     // We are coming from oauth provider page
{
    $backtourl = $_SESSION["backtourlsavedbeforeoauthjump"];
    unset($_SESSION["backtourlsavedbeforeoauthjump"]);

    //llxHeader('',$langs->trans("OAuthSetup"));

    //$linkback='<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';
    //print load_fiche_titre($langs->trans("OAuthSetup"),$linkback,'title_setup');

    //dol_fiche_head();
    // retrieve the CSRF state parameter
    $state = isset($_GET['state']) ? $_GET['state'] : null;
    //print '<table>';

    // This was a callback request from service, get the token
    try {
        //var_dump($_GET['code']);
        //var_dump($state);
        //var_dump($apiService);      // OAuth\OAuth2\Service\WordPress
        
        $token = $apiService->requestAccessToken($_GET['code'], $state);
        $storage->storeAccessToken('ECommerce_'.$siteId, $token);

//        $token = $apiService->refreshAccessToken($token);

        setEventMessages($langs->trans('NewTokenStored'), null, 'mesgs');   // Stored into object managed by class DoliStorage so into table oauth_token
    } catch (Exception $e) {
        setEventMessage($e->getMessage(), 'errors');
    }

    header('Location: ' . $backtourl);
    exit();
}
else // If entry on page with no parameter, we arrive here
{
    $_SESSION["backtourlsavedbeforeoauthjump"]=$backtourl;
    
    // This may create record into oauth_state before the header redirect.
    // Creation of record with state in this tables depend on the Provider used (see its constructor).
    if (GETPOST('state'))
    {
        $url = $apiService->getAuthorizationUri(array('state'=>GETPOST('state')));
    }
    else
    {
        $url = $apiService->getAuthorizationUri();      // Parameter state will be randomly generated
    }
    
    // we go on oauth provider authorization page
    header('Location: ' . $url);
    exit();
}


/*
 * View
 */

// No view at all, just actions

$db->close();

