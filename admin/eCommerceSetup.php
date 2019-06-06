<?php
/* Copyright (C) 2010 Franck Charpentier - Auguria <franck.charpentier@auguria.net>
 * Copyright (C) 2016 Laurent Destailleur          <eldy@users.sourceforge.net>
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

/* PAGE setup ecommerce */

// Load Dolibarr environment
$res=0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (! $res && ! empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res=@include($_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php");
// Try main.inc.php into web root detected using web root caluclated from SCRIPT_FILENAME
$tmp=empty($_SERVER['SCRIPT_FILENAME'])?'':$_SERVER['SCRIPT_FILENAME'];$tmp2=realpath(__FILE__); $i=strlen($tmp)-1; $j=strlen($tmp2)-1;
while($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i]==$tmp2[$j]) { $i--; $j--; }
if (! $res && $i > 0 && file_exists(substr($tmp, 0, ($i+1))."/main.inc.php")) $res=@include(substr($tmp, 0, ($i+1))."/main.inc.php");
if (! $res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i+1)))."/main.inc.php")) $res=@include(dirname(substr($tmp, 0, ($i+1)))."/main.inc.php");
// Try main.inc.php using relative path
if (! $res && file_exists("../main.inc.php")) $res=@include("../main.inc.php");
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");
if (! $res) die("Include of main fails");

require_once(DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php');
require_once(DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php');
require_once(DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php');
require_once DOL_DOCUMENT_ROOT . '/includes/OAuth/bootstrap.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php';
dol_include_once('/ecommerceng/class/data/eCommerceSite.class.php');
dol_include_once('/ecommerceng/admin/class/gui/eCommerceMenu.class.php');
dol_include_once('/ecommerceng/lib/eCommerce.lib.php');

use OAuth\Common\Storage\DoliStorage;

$langs->load('admin');
$langs->load('companies');
$langs->load("oauth");
$langs->load('ecommerce@ecommerceng');
$langs->load('woocommerce@ecommerceng');

$siteId = null;
$errors = array();
$success = array();
//CHECK ACCESS
if (!$user->admin && !$user->rights->ecommerceng->site)
    accessforbidden();

$error = GETPOST('error', 'alpha');
if (!empty($error)) {
    setEventMessage($error, 'errors');
}

//DATABASE ACCESS
$siteDb = new eCommerceSite($db);
$form = new Form($db);

$sites = $siteDb->listSites();
$siteTypes = $siteDb->getSiteTypes();
$site_form_select_site = 0;

// Set $site_form_select_site on first site.
if (count($sites))
{
    foreach ($sites as $option)
    {
        $site_form_select_site = $option['id'];
        break;
    }
}

//LOAD SELECTED SITE
if (isset($_POST['site_form_select_site']))
    $siteId = $_POST['site_form_select_site'];
elseif (isset($_POST['ecommerce_id']))
    $siteId = $_POST['ecommerce_id'];
elseif ($site_form_select_site)
    $siteId = $site_form_select_site;

if ($siteId != null)
    $siteDb->fetch($siteId);

/*
 * Actions
 */

if ($_POST['site_form_detail_action'] == 'save')
{
    if (trim($_POST['ecommerce_name']) == '')
        $errors[] = $langs->trans('ECommerceSetupNameEmpty');
    if ($_POST['ecommerce_fk_cat_product'] == 0)
        $errors[] = $langs->trans('ECommerceSetupCatProductEmpty');
    if ($_POST['ecommerce_fk_cat_societe'] == 0)
        $errors[] = $langs->trans('ECommerceSetupCatSocieteEmpty');
    if ($_POST['ecommerce_type'] == 0)
        $errors[] = $langs->trans('ECommerceSetupTypeEmpty');
    if (! ($_POST['ecommerce_fk_warehouse'] > 0) && $_POST['ecommerce_stock_sync_direction'] == 'ecommerce2dolibarr')
        setEventMessages($langs->trans('WarningStockProductNotFilled'), null, 'warnings');
    if (trim($_POST['ecommerce_webservice_address']) == '')
        $errors[] = $langs->trans('ECommerceSetupAddressEmpty');
    /*if (trim($_POST['ecommerce_timeout']) == '')
        $errors[] = $langs->trans('ECommerceSetupTimeoutEmpty');
    elseif (!ctype_digit($_POST['ecommerce_timeout']))
        $errors[] = $langs->trans('ECommerceSetupTimeoutMustBeInt');*/

    if ($errors == array())
    {
        $db->begin();
        $last_price_level = $siteDb->price_level;
        $siteDb->name = $_POST['ecommerce_name'];
        $siteDb->type = $_POST['ecommerce_type'];
        $siteDb->webservice_address = $_POST['ecommerce_webservice_address'];
        $siteDb->user_name = $_POST['ecommerce_user_name'];
        $siteDb->user_password = $_POST['ecommerce_user_password'];
        $siteDb->price_level = $_POST['ecommerce_price_level'];
        $siteDb->filter_label = $_POST['ecommerce_filter_label'];
        $siteDb->filter_value = $_POST['ecommerce_filter_value'];
        $siteDb->fk_cat_societe = $_POST['ecommerce_fk_cat_societe'];
        $siteDb->fk_cat_product = $_POST['ecommerce_fk_cat_product'];
        $siteDb->fk_anonymous_thirdparty = $_POST['ecommerce_fk_anonymous_thirdparty']>0?$_POST['ecommerce_fk_anonymous_thirdparty']:null;
        $siteDb->fk_warehouse = $_POST['ecommerce_fk_warehouse'];
        $siteDb->stock_sync_direction = $_POST['ecommerce_stock_sync_direction'];
        $siteDb->last_update = $_POST['ecommerce_last_update'];
        //$siteDb->timeout = $_POST['ecommerce_timeout'];
        $siteDb->magento_use_special_price = ($_POST['ecommerce_magento_use_special_price'] ? 1 : 0);
        $siteDb->ecommerce_price_type = $_POST['ecommerce_price_type'];

        if ($siteDb->type == 2) {
            $siteDb->oauth_id = $_POST['ecommerce_oauth_id'];
            $siteDb->oauth_secret = $_POST['ecommerce_oauth_secret'];

            dolibarr_set_const($db, 'ECOMMERCENG_WOOCOMMERCE_ORDER_STATUS_LVL_CHECK', GETPOST('order_status_dtoe_check_lvl_status', 'alpha') == 'yes' ? 1 : 0, 'chaine', 0, '', $conf->entity);

            $efields = new ExtraFields($db);
            $efields->fetch_name_optionals_label('commande', true);
            $ecommerceOrderStatusForECommerceToDolibarr = array();
            if (!isset($_POST['ecommerce_realtime_dtoe_thridparty']) && !isset($siteDb->parameters)) {
                $ecommerceOrderStatusForECommerceToDolibarr = array(
                    "pending" => array('selected' => 's' . Commande::STATUS_DRAFT, 'billed' => 0),
                    "processing" => array('selected' => 's' . Commande::STATUS_VALIDATED, 'billed' => 0),
                    "on-hold" => array('selected' => 's' . Commande::STATUS_DRAFT, 'billed' => 0),
                    "completed" => array('selected' => 's' . Commande::STATUS_CLOSED, 'billed' => 1),
                    "cancelled" => array('selected' => 's' . Commande::STATUS_CANCELED, 'billed' => 0),
                    "refunded" => array('selected' => 's' . Commande::STATUS_CANCELED, 'billed' => 1),
                    "failed" => array('selected' => 's' . Commande::STATUS_CANCELED, 'billed' => 0),
                );
            } else {
                if (isset($efields->attribute_param["ecommerceng_wc_status_{$siteDb->id}_{$conf->entity}"]['options']) &&
                    is_array($efields->attribute_param["ecommerceng_wc_status_{$siteDb->id}_{$conf->entity}"]['options'])
                ) {
                    foreach ($efields->attribute_param["ecommerceng_wc_status_{$siteDb->id}_{$conf->entity}"]['options'] as $key => $value) {
                        if (($pos = strpos($key, '_')) > 0) $key = substr($key, $pos + 1);
                        $billed = GETPOST('order_status_etod_billed_' . $key, 'alpha');
                        $ecommerceOrderStatusForECommerceToDolibarr[$key] = array(
                            'selected' => GETPOST('order_status_etod_' . $key, 'alpha'),
                            'billed' => empty($billed) ? 0 : 1,
                        );
                    }
                }
            }

            if (!isset($_POST['ecommerce_realtime_dtoe_thridparty']) && !isset($siteDb->parameters)) {
                $ecommerceOrderStatusForDolibarrToECommerce = array(
                    Commande::STATUS_CANCELED => 'cancelled',
                    Commande::STATUS_DRAFT => 'on-hold',
                    Commande::STATUS_VALIDATED => 'processing',
                    Commande::STATUS_ACCEPTED => 'processing',
                    Commande::STATUS_CLOSED => 'completed',
                );
            } else {
                $ecommerceOrderStatusForDolibarrToECommerce = array(
                    Commande::STATUS_CANCELED => GETPOST('order_status_dtoe_' . Commande::STATUS_CANCELED, 'alpha'),
                    Commande::STATUS_DRAFT => GETPOST('order_status_dtoe_' . Commande::STATUS_DRAFT, 'alpha'),
                    Commande::STATUS_VALIDATED => GETPOST('order_status_dtoe_' . Commande::STATUS_VALIDATED, 'alpha'),
                    Commande::STATUS_ACCEPTED => GETPOST('order_status_dtoe_' . Commande::STATUS_ACCEPTED, 'alpha'),
                    Commande::STATUS_CLOSED => GETPOST('order_status_dtoe_' . Commande::STATUS_CLOSED, 'alpha'),
                );
            }

            $ecommerceExtrafieldsCorrespondence = array();
            // fetch optionals attributes and labels
            if ($conf->product->enabled) {
                $product_table_element = 'product';
                $ecommerceExtrafieldsCorrespondence[$product_table_element] = array();

                $productExtrafields = $efields->fetch_name_optionals_label($product_table_element);
                foreach ($productExtrafields as $key => $label) {
                    if (preg_match('/^ecommerceng_/', $key)) continue;
                    $options_saved = $siteDb->parameters['ef_crp'][$product_table_element][$key];
                    $activated = GETPOST('act_ef_crp_' . $product_table_element . '_' . $key, 'alpha');
                    $correspondence = GETPOST('ef_crp_' . $product_table_element . '_' . $key, 'alpha');
                    $ecommerceExtrafieldsCorrespondence[$product_table_element][$key] = array(
                        'correspondences' => !empty($activated) ? $correspondence : (isset($options_saved['correspondences']) ? $options_saved['correspondences'] : $key),
                        'activated' => !empty($activated) ? 1 : 0,
                    );
                }
            }
            if ($conf->commande->enabled) {
                $order_table_element = 'commande';
                $ecommerceExtrafieldsCorrespondence[$order_table_element] = array();

                $orderExtrafields = $efields->fetch_name_optionals_label($order_table_element);
                foreach ($orderExtrafields as $key => $label) {
                    if (preg_match('/^ecommerceng_/', $key)) continue;
                    $options_saved = $siteDb->parameters['ef_crp'][$order_table_element][$key];
                    $activated = GETPOST('act_ef_crp_' . $order_table_element . '_' . $key, 'alpha');
                    $correspondence = GETPOST('ef_crp_' . $order_table_element . '_' . $key, 'alpha');
                    $ecommerceExtrafieldsCorrespondence[$order_table_element][$key] = array(
                        'correspondences' => !empty($activated) ? $correspondence : (isset($options_saved['correspondences']) ? $options_saved['correspondences'] : $key),
                        'activated' => !empty($activated) ? 1 : 0,
                    );
                }

                $order_line_table_element = 'commandedet';
                $ecommerceExtrafieldsCorrespondence[$order_line_table_element] = array();

                $orderLinesExtrafields = $efields->fetch_name_optionals_label($order_line_table_element);
                foreach ($orderLinesExtrafields as $key => $label) {
                    if (preg_match('/^ecommerceng_/', $key)) continue;
                    $options_saved = $siteDb->parameters['ef_crp'][$order_line_table_element][$key];
                    $activated = GETPOST('act_ef_crp_' . $order_line_table_element . '_' . $key, 'alpha');
                    $correspondence = GETPOST('ef_crp_' . $order_line_table_element . '_' . $key, 'alpha');
                    $ecommerceExtrafieldsCorrespondence[$order_line_table_element][$key] = array(
                        'correspondences' => !empty($activated) ? $correspondence : (isset($options_saved['correspondences']) ? $options_saved['correspondences'] : $key),
                        'activated' => !empty($activated) ? 1 : 0,
                    );
                }
            }

            if (!isset($_POST['ecommerce_realtime_dtoe_thridparty']) && !isset($siteDb->parameters)) {
                $ecommerceRealtimeDolibarrToECommerce = array(
                    'thridparty' => 1,
                    'contact' => 1,
                    'product' => 1,
                    'order' => 1,
                );
            } else {
                $ecommerceRealtimeDolibarrToECommerce = array(
                    'thridparty' => !empty($_POST['ecommerce_realtime_dtoe_thridparty']) ? 1 : 0,
                    'contact' => !empty($_POST['ecommerce_realtime_dtoe_contact']) ? 1 : 0,
                    'product' => !empty($_POST['ecommerce_realtime_dtoe_product']) ? 1 : 0,
                    'order' => !empty($_POST['ecommerce_realtime_dtoe_order']) ? 1 : 0,
                );
            }

            $ecommerceProductSynchPrice = GETPOST('ecommerce_product_synch_price', 'alpha');

            $ecommerceProductSynchDirection = array(
                'image' => GETPOST('ecommerce_product_image_synch_direction', 'alpha'),
                'ref' => GETPOST('ecommerce_product_ref_synch_direction', 'alpha'),
                'description' => GETPOST('ecommerce_product_description_synch_direction', 'alpha'),
                'short_description' => GETPOST('ecommerce_product_short_description_synch_direction', 'alpha'),
                'weight' => GETPOST('ecommerce_product_weight_synch_direction', 'alpha'),
                'tax' => GETPOST('ecommerce_product_tax_synch_direction', 'alpha'),
                'status' => GETPOST('ecommerce_product_status_synch_direction', 'alpha'),
            );

            $siteDb->parameters = array(
                'order_status_etod' => $ecommerceOrderStatusForECommerceToDolibarr,
                'order_status_dtoe' => $ecommerceOrderStatusForDolibarrToECommerce,
                'ef_crp' => $ecommerceExtrafieldsCorrespondence,
                'payment_cond' => $_POST['ecommerce_payment_cond'],
                'realtime_dtoe' => $ecommerceRealtimeDolibarrToECommerce,
                'product_synch_direction' => $ecommerceProductSynchDirection,
                'product_synch_price' => $ecommerceProductSynchPrice,
            );
        }

        $result = 0;
        if (intval($_POST['ecommerce_id']))
        {
            $siteDb->id = $_POST['ecommerce_id'];
            $result = $siteDb->update($user);
        } else
        {
            $result = $siteDb->create($user);
        }

        $error = '';
        if ($result > 0) {
            if ($siteDb->type == 2) { // Woocommerce
                $result = ecommerceng_add_extrafields($db, $langs, [
                    [
                        'attrname' => "ecommerceng_description_{$conf->entity}",
                        'label' => 'ECommercengWoocommerceDescription',
                        'type' => 'text',
                        'pos' => 1,
                        'size' => '',
                        'elementtype' => 'product',
                        'unique' => 0,
                        'required' => 0,
                        'default_value' => '',
                        'param' => '',
                        'alwayseditable' => 1,
                        'perms' => '',
                        'list' => 1,
                    ],[
                        'attrname' => "ecommerceng_short_description_{$conf->entity}",
                        'label' => 'ECommercengWoocommerceShortDescription',
                        'type' => 'text',
                        'pos' => 2,
                        'size' => '',
                        'elementtype' => 'product',
                        'unique' => 0,
                        'required' => 0,
                        'default_value' => '',
                        'param' => '',
                        'alwayseditable' => 1,
                        'perms' => '',
                        'list' => 1,
                    ],[
                        'attrname' => "ecommerceng_wc_status_{$siteDb->id}_{$conf->entity}",
                        'label' => $langs->trans('ECommercengWoocommerceStatus', $siteDb->name),
                        'type' => 'select',
                        'pos' => 3,
                        'size' => '',
                        'elementtype' => 'product',
                        'unique' => 0,
                        'required' => 0,
                        'default_value' => '',
                        'param' => array('options' => array(
                            "draft" => $langs->trans('ECommercengWoocommerceStatusDraft'),
                            "pending" => $langs->trans('ECommercengWoocommerceStatusPending'),
                            "private" => $langs->trans('ECommercengWoocommerceStatusPrivate'),
                            "publish" => $langs->trans('ECommercengWoocommerceStatusPublish'),
                        )),
                        'alwayseditable' => 1,
                        'perms' => '',
                        'list' => 1,
                    ],[
                        'attrname' => "ecommerceng_tax_class_{$siteDb->id}_{$conf->entity}",
                        'label' => $langs->trans('ECommercengWoocommerceTaxClass', $siteDb->name),
                        'type' => 'sellist',
                        'pos' => 4,
                        'size' => '',
                        'elementtype' => 'product',
                        'unique' => 0,
                        'required' => 0,
                        'default_value' => '',
                        'param' => array('options' => array("c_ecommerceng_tax_class:label:code::active=1 AND site_id={$siteDb->id} AND entity={$conf->entity}" => null)),
                        'alwayseditable' => 1,
                        'perms' => '',
                        'list' => 1,
                    /*],[
                        'attrname' => "ecommerceng_wc_regular_price_{$siteDb->id}_{$conf->entity}",
                        'label' => $langs->trans('ECommercengWoocommerceRegularPrice', $siteDb->name),
                        'type' => 'price',
                        'pos' => 5,
                        'size' => '',
                        'elementtype' => 'product',
                        'unique' => 0,
                        'required' => 0,
                        'default_value' => '',
                        'param' => '',
                        'alwayseditable' => 1,
                        'perms' => '',
                        'list' => 1,*/
                    ],[
                        'attrname' => "ecommerceng_wc_sale_price_{$siteDb->id}_{$conf->entity}",
                        'label' => $langs->trans('ECommercengWoocommerceSalePrice', $siteDb->name),
                        'type' => 'price',
                        'pos' => 6,
                        'size' => '',
                        'elementtype' => 'product',
                        'unique' => 0,
                        'required' => 0,
                        'default_value' => '',
                        'param' => '',
                        'alwayseditable' => 1,
                        'perms' => '',
                        'list' => 1,
                    ],[
                        'attrname' => "ecommerceng_wc_date_on_sale_from_{$siteDb->id}_{$conf->entity}",
                        'label' => $langs->trans('ECommercengWoocommerceDateOnSaleFrom', $siteDb->name),
                        'type' => 'date',
                        'pos' => 7,
                        'size' => '',
                        'elementtype' => 'product',
                        'unique' => 0,
                        'required' => 0,
                        'default_value' => '',
                        'param' => '',
                        'alwayseditable' => 1,
                        'perms' => '',
                        'list' => 1,
                    ],[
                        'attrname' => "ecommerceng_wc_date_on_sale_to_{$siteDb->id}_{$conf->entity}",
                        'label' => $langs->trans('ECommercengWoocommerceDateOnSaleTo', $siteDb->name),
                        'type' => 'date',
                        'pos' => 8,
                        'size' => '',
                        'elementtype' => 'product',
                        'unique' => 0,
                        'required' => 0,
                        'default_value' => '',
                        'param' => '',
                        'alwayseditable' => 1,
                        'perms' => '',
                        'list' => 1,
                    ],[
                        'attrname' => "ecommerceng_online_payment_{$conf->entity}",
                        'label' => 'ECommercengWoocommerceOnlinePayment',
                        'type' => 'boolean',
                        'pos' => 1,
                        'size' => '1',
                        'elementtype' => 'commande',
                        'unique' => 0,
                        'required' => 0,
                        'default_value' => '',
                        'param' => '',
                        'alwayseditable' => 0,
                        'perms' => '',
                        'list' => 1,
                    ],[
                        'attrname' => "ecommerceng_wc_status_{$siteDb->id}_{$conf->entity}",
                        'label' => $langs->trans('ECommercengWoocommerceOrderStatus', $siteDb->name),
                        'type' => 'select',
                        'pos' => 2,
                        'size' => '',
                        'elementtype' => 'commande',
                        'unique' => 0,
                        'required' => 0,
                        'default_value' => '',
                        'param' => array('options' => array(
                            "0_pending" => $langs->trans('ECommercengWoocommerceOrderStatusPending'),
                            "1_on-hold" => $langs->trans('ECommercengWoocommerceOrderStatusOnHold'),
                            "2_processing" => $langs->trans('ECommercengWoocommerceOrderStatusProcessing'),
                            "3_completed" => $langs->trans('ECommercengWoocommerceOrderStatusCompleted'),
                            "3_cancelled" => $langs->trans('ECommercengWoocommerceOrderStatusCancelled'),
                            "3_refunded" => $langs->trans('ECommercengWoocommerceOrderStatusRefunded'),
                            "3_failed" => $langs->trans('ECommercengWoocommerceOrderStatusFailed'),
                        )),
                        'alwayseditable' => 0,
                        'perms' => '',
                        'list' => 1,
                    ],[
                        'attrname' => "ecommerceng_wc_role_{$siteDb->id}_{$conf->entity}",
                        'label' => $langs->trans('ECommercengWoocommerceCompanyRole', $siteDb->name),
                        'type' => 'varchar',
                        'pos' => 0,
                        'size' => '255',
                        'elementtype' => 'societe',
                        'unique' => 0,
                        'required' => 0,
                        'default_value' => '',
                        'param' => '',
                        'alwayseditable' => 0,
                        'perms' => '',
                        'list' => 1,
                    ],
                ], $error);
            }
        }

        if ($result > 0)
        {
            $eCommerceMenu = new eCommerceMenu($db, $siteDb);
            $eCommerceMenu->updateMenu();
            $db->commit();

            if ($siteDb->type == 2) { // Woocommerce
                ecommerceng_update_woocommerce_dict_tax_class($db, $siteDb);
            }
            if (!empty($conf->global->PRODUIT_MULTIPRICES) && $siteDb->price_level != $last_price_level) {
                updatePriceLevel($siteDb);
            }

            setEventMessages($langs->trans('ECommerceSetupSaved'), null);
        } else
        {
            $db->rollback();
            if (!empty($error)) {
                setEventMessage($error, 'errors');
            } else {
                setEventMessages($siteDb->error, $siteDb->errors, 'errors');
            }
        }
    }
    else
    {
        setEventMessages('', $errors, 'errors');
    }
}
//DELETE
elseif ($_POST['site_form_detail_action'] == 'delete')
{
    $siteDb->id = $_POST['ecommerce_id'];
    $result = $siteDb->delete($user);
    if ($result < 0)
    {
        setEventMessages($langs->trans('ECommerceDeleteErrorDb'), null, 'errors');
    }
    else
    {
        $eCommerceMenu = new eCommerceMenu($db, $siteDb);
        $eCommerceMenu->updateMenu();
        $success[] = $langs->trans('ECommerceDeleteOk');
        $siteDb->id = null;
        unset($_POST);
    }
}
// Update dictionary for tax class of woocommerce
elseif ($_POST['site_form_detail_action'] == 'update_woocommerce_tax_class') {
    if (ecommerceng_update_woocommerce_dict_tax_class($db, $siteDb)) {
        setEventMessage($langs->trans('ECommercengWoocommerceDictTaxClassUpdated'));
    }
}



/*
 *  View
 */

/*if (! extension_loaded('soap'))
{
    llxHeader();

    print info_admin($langs->trans("ErrorModuleSoapRequired"));

    llxFooter();
    exit;
}*/

$classCategorie = new Categorie($db);
$productCategories = $classCategorie->get_full_arbo('product');
$societeCategories = $classCategorie->get_full_arbo('customer');

if (!empty($conf->global->PRODUIT_MULTIPRICES)) {
    $priceLevels = array();
	for ($i = 1; $i <= $conf->global->PRODUIT_MULTIPRICES_LIMIT; $i++) {
        $keyforlabel = 'PRODUIT_MULTIPRICES_LABEL'.$i;
        $priceLevels[$i] = !empty($conf->global->$keyforlabel) ? $langs->trans($conf->global->$keyforlabel) : $i;
    }
}

//SET VARIABLES
$ecommerceId = ($_POST['ecommerce_id'] ? $_POST['ecommerce_id'] : $siteDb->id);
$ecommerceName = ($_POST['ecommerce_name'] ? $_POST['ecommerce_name'] : $siteDb->name);
$ecommerceType = ($_POST['ecommerce_type'] ? $_POST['ecommerce_type'] : intval($siteDb->type));
$ecommerceWebserviceAddress = ($_POST['ecommerce_webservice_address'] ? $_POST['ecommerce_webservice_address'] : $siteDb->webservice_address);
$ecommerceWebserviceAddressTest = '';
if (!empty($ecommerceWebserviceAddress)) {
    switch ($ecommerceType) {
        case 1: // Magento
            $ecommerceWebserviceAddressTest = $ecommerceWebserviceAddress .(substr($ecommerceWebserviceAddress, -1, 1)!='/'?'/':''). 'api/?wsdl';
            break;
        case 2: // Woocommerce
            $ecommerceWebserviceAddressTest = $ecommerceWebserviceAddress .(substr($ecommerceWebserviceAddress, -1, 1)!='/'?'/':''). 'wp-json/';
            break;
    }
}
$ecommerceUserName = ($_POST['ecommerce_user_name'] ? $_POST['ecommerce_user_name'] : $siteDb->user_name);
$ecommerceUserPassword = ($_POST['ecommerce_user_password'] ? $_POST['ecommerce_user_password'] : $siteDb->user_password);
$ecommercePriceLevel = ($_POST['ecommerce_price_level'] ? $_POST['ecommerce_price_level'] : $siteDb->price_level);
$ecommerceFilterLabel = ($_POST['ecommerce_filter_label'] ? $_POST['ecommerce_filter_label'] : $siteDb->filter_label);
$ecommerceFilterValue = ($_POST['ecommerce_filter_value'] ? $_POST['ecommerce_filter_value'] : $siteDb->filter_value);
$ecommerceFkCatSociete = ($_POST['ecommerce_fk_cat_societe'] ? $_POST['ecommerce_fk_cat_societe'] : intval($siteDb->fk_cat_societe));
$ecommerceFkCatProduct = ($_POST['ecommerce_fk_cat_product'] ? $_POST['ecommerce_fk_cat_product'] : intval($siteDb->fk_cat_product));
$ecommerceFkAnonymousThirdparty = ($_POST['ecommerce_fk_anonymous_thirdparty'] ? $_POST['ecommerce_fk_anonymous_thirdparty'] : intval($siteDb->fk_anonymous_thirdparty));
$ecommerceFkWarehouse = ($_POST['ecommerce_fk_warehouse'] ? $_POST['ecommerce_fk_warehouse'] : intval($siteDb->fk_warehouse));
$ecommerceStockSyncDirection = ($_POST['ecommerce_stock_sync_direction'] ? $_POST['ecommerce_stock_sync_direction'] : $siteDb->stock_sync_direction);
$ecommerceMagentoUseSpecialPrice = ($_POST['ecommerce_magento_use_special_price'] ? $_POST['ecommerce_magento_use_special_price'] : intval($siteDb->magento_use_special_price));
$ecommercePriceType = ($_POST['ecommerce_price_type'] ? $_POST['ecommerce_price_type'] : $siteDb->ecommerce_price_type);
$ecommercePaymentCondition = ($_POST['ecommerce_payment_cond'] ? $_POST['ecommerce_payment_cond'] : (isset($siteDb->parameters['payment_cond']) ? $siteDb->parameters['payment_cond'] : ''));
$ecommerceRealtimeDtoe = (isset($siteDb->parameters['realtime_dtoe']) ? $siteDb->parameters['realtime_dtoe'] : array());
/*$ecommerceTimeout = 300;
if (isset($_POST['ecommerce_timeout']))
    $ecommerceTimeout = $_POST['ecommerce_timeout'];
elseif (isset($siteDb->timeout))
    $ecommerceTimeout = $siteDb->timeout;*/
$ecommerceOAuth = false;
$ecommerceOAuthGenerateToken = false;
$ecommerceOrderStatus = false;
if (!empty($ecommerceId)) {
    if ($ecommerceType == 2) {
        $ecommerceOAuth = true;
        $ecommerceOAuthWordpressOAuthSetupUri = $ecommerceWebserviceAddress . (substr($ecommerceWebserviceAddress, -1, 1) != '/' ? '/' : '') . 'wp-admin/admin.php?page=wo_settings#clients';
        $ecommerceOrderStatus = $conf->commande->enabled;
    }

    if ($ecommerceOAuth) {
        // Create callback URL
        //$urlwithouturlroot=preg_replace('/'.preg_quote(DOL_URL_ROOT,'/').'$/i','',trim($dolibarr_main_url_root));
        //$urlwithroot=$urlwithouturlroot.DOL_URL_ROOT;		// This is to use external domain name found into config file
        $uriFactory = new \OAuth\Common\Http\Uri\UriFactory();
        //$currentUri = $uriFactory->createFromAbsolute($urlwithroot.'/custom/ecommerceng/core/modules/oauth/wordpress_oauthcallback.php?ecommerce_id='.$siteId);
        $currentUri = $uriFactory->createFromAbsolute(dol_buildpath('/custom/ecommerceng/core/modules/oauth/wordpress_oauthcallback.php', 2).'?ecommerce_id='.$siteId);
        $ecommerceOAuthRedirectUri = $currentUri->getAbsoluteUri();
//        $ecommerceOAuthRedirectUri = dol_buildpath('/custom/ecommerceng/core/modules/oauth/wordpress_oauthcallback.php', 2).'?ecommerce_id='.$ecommerceId;
        $ecommerceOAuthId = ($_POST['ecommerce_oauth_id'] ? $_POST['ecommerce_oauth_id'] : $siteDb->oauth_id);
        $ecommerceOAuthSecret = ($_POST['ecommerce_oauth_secret'] ? $_POST['ecommerce_oauth_secret'] : $siteDb->oauth_secret);

        // Token
        $ecommerceOAuthTokenObj = null;
        $storage = new DoliStorage($db, $conf);
        try {
            $ecommerceOAuthTokenObj = $storage->retrieveAccessToken('ECommerce_'.$ecommerceId);
        } catch(Exception $e) {}
        $ecommerceOAuthGenerateToken = (!empty($ecommerceOAuthId) && !empty($ecommerceOAuthSecret) || is_object($ecommerceOAuthTokenObj));

        $ecommerceOAuthBackToUri = urlencode(dol_buildpath('/custom/ecommerceng/admin/eCommerceSetup.php', 2).'?ecommerce_id='.$ecommerceId);

        if (is_object($ecommerceOAuthTokenObj)) {
            $ecommerceOAuthTokenExpired = ($ecommerceOAuthTokenObj->getEndOfLife() !== $ecommerceOAuthTokenObj::EOL_NEVER_EXPIRES && $ecommerceOAuthTokenObj->getEndOfLife() !== $ecommerceOAuthTokenObj::EOL_UNKNOWN && time() > ($ecommerceOAuthTokenObj->getEndOfLife() - 30));

            $ecommerceOAuthHasRefreshToken = !empty($ecommerceOAuthTokenObj->getRefreshToken());

            $endoflife = $ecommerceOAuthTokenObj->getEndOfLife();
            if ($endoflife == $ecommerceOAuthTokenObj::EOL_NEVER_EXPIRES) {
                $ecommerceOAuthTokenExpireDate = $langs->trans("Never");
            } elseif ($endoflife == $ecommerceOAuthTokenObj::EOL_UNKNOWN) {
                $ecommerceOAuthTokenExpireDate = $langs->trans("Unknown");
            } else {
                $ecommerceOAuthTokenExpireDate = dol_print_date($endoflife, "dayhour");
            }
        }
    }

    if ($ecommerceOrderStatus) {
        $efields = new ExtraFields($db);
        $efields->fetch_name_optionals_label('commande', true);
        $ecommerceOrderStatusForECommerceToDolibarr = array();
        if (isset($efields->attribute_param["ecommerceng_wc_status_{$siteDb->id}_{$conf->entity}"]['options']) &&
            is_array($efields->attribute_param["ecommerceng_wc_status_{$siteDb->id}_{$conf->entity}"]['options'])) {
            foreach ($efields->attribute_param["ecommerceng_wc_status_{$siteDb->id}_{$conf->entity}"]['options'] as $key => $value) {
                if (($pos = strpos($key , '_')) > 0) $key = substr($key, $pos + 1);
                $selected = GETPOST('order_status_etod_' . $key, 'alpha');
                $selected = $selected ? $selected : (isset($siteDb->parameters['order_status_etod'][$key]['selected']) ? $siteDb->parameters['order_status_etod'][$key]['selected'] : '');
                $billed = isset($_POST['order_status_etod_billed_' . $key]) ? GETPOST('order_status_etod_billed_' . $key, 'alpha') :
                    (isset($siteDb->parameters['order_status_etod'][$key]['billed']) ? $siteDb->parameters['order_status_etod'][$key]['billed'] : '');
                $ecommerceOrderStatusForECommerceToDolibarr[$key] = array('label' => $value, 'selected' => $selected, 'billed' => $billed);
            }
        }

        $commande = new Commande($db);
        $langs->load('orders');
        $langs->load('bills');
        $ecommerceOrderStatusForDolibarrToECommerce = array();
        $selected = GETPOST('order_status_dtoe_' . Commande::STATUS_CANCELED, 'alpha');
        $selected = $selected ? $selected : (isset($siteDb->parameters['order_status_dtoe'][Commande::STATUS_CANCELED]) ? $siteDb->parameters['order_status_dtoe'][Commande::STATUS_CANCELED] : '');
        $ecommerceOrderStatusForDolibarrToECommerce['s'.Commande::STATUS_CANCELED] = array(
            'label' => $commande->LibStatut(Commande::STATUS_CANCELED, 0, 0, 1),
            'selected' => $selected
        );
        $selected = GETPOST('order_status_dtoe_' . Commande::STATUS_DRAFT, 'alpha');
        $selected = $selected ? $selected : (isset($siteDb->parameters['order_status_dtoe'][Commande::STATUS_DRAFT]) ? $siteDb->parameters['order_status_dtoe'][Commande::STATUS_DRAFT] : '');
        $ecommerceOrderStatusForDolibarrToECommerce['s'.Commande::STATUS_DRAFT] = array(
            'label' => $commande->LibStatut(Commande::STATUS_DRAFT, 0, 0, 1),
            'selected' => $selected
        );
        $selected = GETPOST('order_status_dtoe_' . Commande::STATUS_VALIDATED, 'alpha');
        $selected = $selected ? $selected : (isset($siteDb->parameters['order_status_dtoe'][Commande::STATUS_VALIDATED]) ? $siteDb->parameters['order_status_dtoe'][Commande::STATUS_VALIDATED] : '');
        $ecommerceOrderStatusForDolibarrToECommerce['s'.Commande::STATUS_VALIDATED] = array(
            'label' => $commande->LibStatut(Commande::STATUS_VALIDATED, 0, 0, 1),
            'selected' => $selected
        );
        $selected = GETPOST('order_status_dtoe_' . Commande::STATUS_ACCEPTED, 'alpha');
        $selected = $selected ? $selected : (isset($siteDb->parameters['order_status_dtoe'][Commande::STATUS_ACCEPTED]) ? $siteDb->parameters['order_status_dtoe'][Commande::STATUS_ACCEPTED] : '');
        $ecommerceOrderStatusForDolibarrToECommerce['s'.Commande::STATUS_ACCEPTED] = array(
            'label' => $commande->LibStatut(Commande::STATUS_ACCEPTED, 0, 0, 1),
            'selected' => $selected
        );
        $selected = GETPOST('order_status_dtoe_' . Commande::STATUS_CLOSED, 'alpha');
        $selected = $selected ? $selected : (isset($siteDb->parameters['order_status_dtoe'][Commande::STATUS_CLOSED]) ? $siteDb->parameters['order_status_dtoe'][Commande::STATUS_CLOSED] : '');
        $ecommerceOrderStatusForDolibarrToECommerce['s'.Commande::STATUS_CLOSED] = array(
            'label' => $commande->LibStatut(Commande::STATUS_CLOSED, 0, 0, 1),
            'selected' => $selected
        );
    }

    // Extrafields correspondence
    if ($ecommerceType == 2) {
        require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
        $extrafields = new ExtraFields($db);

        // fetch optionals attributes and labels
        if ($conf->product->enabled) {
            $product_table_element = 'product';
            $productExtrafields = array();
            $ecommerceExtrafieldsCorrespondence[$product_table_element] = array();

            $tempExtrafields = $extrafields->fetch_name_optionals_label($product_table_element);
            foreach ($tempExtrafields as $key => $label) {
                if (preg_match('/^ecommerceng_/', $key)) continue;
                $productExtrafields[$key] = $label;
                $options_saved = $siteDb->parameters['ef_crp'][$product_table_element][$key];
                $ecommerceExtrafieldsCorrespondence[$product_table_element][$key] = array(
                    'correspondences' => (isset($options_saved['correspondences']) ? $options_saved['correspondences'] : $key),
                    'activated' => (isset($options_saved['activated']) ? $options_saved['activated'] : $key),
                );
            }
        }
        if ($conf->commande->enabled) {
            $order_table_element = 'commande';
            $orderExtrafields = array();
            $ecommerceExtrafieldsCorrespondence[$order_table_element] = array();

            $tempExtrafields = $extrafields->fetch_name_optionals_label($order_table_element);
            foreach ($tempExtrafields as $key => $label) {
                if (preg_match('/^ecommerceng_/', $key)) continue;
                $orderExtrafields[$key] = $label;
                $options_saved = $siteDb->parameters['ef_crp'][$order_table_element][$key];
                $ecommerceExtrafieldsCorrespondence[$order_table_element][$key] = array(
                    'correspondences' => (isset($options_saved['correspondences']) ? $options_saved['correspondences'] : $key),
                    'activated' => (isset($options_saved['activated']) ? $options_saved['activated'] : $key),
                );
            }

            $order_line_table_element = 'commandedet';
            $orderLinesExtrafields = array();
            $ecommerceExtrafieldsCorrespondence[$order_line_table_element] = array();

            $tempExtrafields = $extrafields->fetch_name_optionals_label($order_line_table_element);
            foreach ($tempExtrafields as $key => $label) {
                if (preg_match('/^ecommerceng_/', $key)) continue;
                $orderLinesExtrafields[$key] = $label;
                $options_saved = $siteDb->parameters['ef_crp'][$order_line_table_element][$key];
                $ecommerceExtrafieldsCorrespondence[$order_line_table_element][$key] = array(
                    'correspondences' => (isset($options_saved['correspondences']) ? $options_saved['correspondences'] : $key),
                    'activated' => (isset($options_saved['activated']) ? $options_saved['activated'] : $key),
                );
            }
        }

        $ecommerceProductSynchPrice = isset($siteDb->parameters['product_synch_price']) ? $siteDb->parameters['product_synch_price'] : 'regular';
        $ecommerceProductImageSynchDirection = isset($siteDb->parameters['product_synch_direction']['image']) ? $siteDb->parameters['product_synch_direction']['image'] : '';
        $ecommerceProductRefSynchDirection = isset($siteDb->parameters['product_synch_direction']['ref']) ? $siteDb->parameters['product_synch_direction']['ref'] : '';
        $ecommerceProductDescriptionSynchDirection = isset($siteDb->parameters['product_synch_direction']['description']) ? $siteDb->parameters['product_synch_direction']['description'] : '';
        $ecommerceProductShortDescriptionSynchDirection = isset($siteDb->parameters['product_synch_direction']['short_description']) ? $siteDb->parameters['product_synch_direction']['short_description'] : '';
        $ecommerceProductWeightSynchDirection = isset($siteDb->parameters['product_synch_direction']['weight']) ? $siteDb->parameters['product_synch_direction']['weight'] : '';
        $ecommerceProductTaxSynchDirection = isset($siteDb->parameters['product_synch_direction']['tax']) ? $siteDb->parameters['product_synch_direction']['tax'] : '';
        $ecommerceProductStatusSynchDirection = isset($siteDb->parameters['product_synch_direction']['status']) ? $siteDb->parameters['product_synch_direction']['status'] : '';
    }
}

$ecommerceLastUpdate = $siteDb->last_update;
$var = true;
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">' . $langs->trans("BackToModuleList") . '</a>';
$title = '';
if ($siteDb->name)
    $title = $langs->trans('ECommerceSetupSite') . ' ' . $siteDb->name;
else
    $title = $langs->trans('ECommerceCreateSite');

//SHOW PAGE
$urltpl=dol_buildpath('/ecommerceng/admin/tpl/eCommerceSetup.tpl.php',0);
include($urltpl);

if ($siteDb->type == 1) {
    $soapwsdlcacheon = ini_get('soap.wsdl_cache_enabled');
    $soapwsdlcachedir = ini_get('soap.wsdl_cache_dir');
    if ($soapwsdlcacheon) {
        print img_warning('') . ' ' . $langs->trans("WarningSoapCacheIsOn", $soapwsdlcachedir) . ' ';
        print $langs->trans("WarningSoapCacheIsOn2", $langs->transnoentitiesnoconv("ECommerceSiteAddress")) . '<br>';
    } else {
        print $langs->trans("SoapCacheIsOff", $soapwsdlcachedir) . '<br>';
    }

}
llxFooter();


$db->close();
clearstatcache();
