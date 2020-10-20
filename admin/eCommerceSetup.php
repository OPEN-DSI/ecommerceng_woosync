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
dol_include_once('/ecommerceng/class/data/eCommercePaymentGateways.class.php');

use OAuth\Common\Storage\DoliStorage;

$langs->load('admin');
$langs->load('companies');
$langs->load('bills');
$langs->load('banks');
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
$pay_gateways = new eCommercePaymentGateways($db);

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
$siteId = GETPOST('site_form_select_site', 'int');
if (empty($siteId)) $siteId = GETPOST('ecommerce_id', 'int');
if (empty($siteId)) $siteId = $site_form_select_site;

if ($siteId > 0)
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

            $ecommerceOrderActions = array();
            if ($conf->commande->enabled) {
                $ecommerceOrderActions['create_order'] = GETPOST('ecommerce_create_order', 'int') ? 1 : 0;
            }
            if ($conf->facture->enabled) {
                $ecommerceOrderActions['create_invoice'] = GETPOST('ecommerce_create_invoice', 'int') ? 1 : 0;
                $ecommerceOrderActions['send_invoice_by_mail'] = GETPOST('ecommerce_send_invoice_by_mail', 'int') ? 1 : 0;
                if (empty($ecommerceOrderActions['create_invoice'])) $ecommerceOrderActions['send_invoice_by_mail'] = 0;
            }
            if ($conf->supplier_invoice->enabled && !empty($ecommerceOrderActions['create_invoice'])) {
                $ecommerceOrderActions['create_supplier_invoice'] = GETPOST('ecommerce_create_supplier_invoice', 'int') ? 1 : 0;
            }

            $efields = new ExtraFields($db);
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
                $orderExtrafields = $efields->fetch_name_optionals_label($order_table_element, true);

                if (!isset($siteDb->parameters['order_status_etod'])) {
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
                    $ecommerceOrderStatusForECommerceToDolibarr = array();
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

                if (!empty($ecommerceOrderActions['create_order'])) {
                    if (!isset($siteDb->parameters['order_status_dtoe'])) {
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
                }

                $ecommerceExtrafieldsCorrespondence[$order_table_element] = array();
                foreach ($orderExtrafields as $key => $label) {
                    if (preg_match('/^ecommerceng_/', $key)) continue;
                    $options_saved = $siteDb->parameters['ef_crp'][$order_table_element][$key];
                    $activated = GETPOST('act_ef_crp_' . $order_table_element . '_' . $key, 'int');
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
                    $activated = GETPOST('act_ef_crp_' . $order_line_table_element . '_' . $key, 'int');
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
                );
            } else {
                $ecommerceRealtimeDolibarrToECommerce = array(
                    'thridparty' => !empty($_POST['ecommerce_realtime_dtoe_thridparty']) ? 1 : 0,
                    'contact' => !empty($_POST['ecommerce_realtime_dtoe_contact']) ? 1 : 0,
                    'product' => !empty($_POST['ecommerce_realtime_dtoe_product']) ? 1 : 0,
                );
            }
            if ($conf->commande->enabled && !empty($ecommerceOrderActions['create_order'])) {
                if (!isset($_POST['ecommerce_realtime_dtoe_order']) && !isset($siteDb->parameters['realtime_dtoe']['order'])) {
                    $ecommerceRealtimeDolibarrToECommerce['order'] = 1;
                } else {
                    $ecommerceRealtimeDolibarrToECommerce['order'] = !empty($_POST['ecommerce_realtime_dtoe_order']) ? 1 : 0;
                }
            }

            $ecommerceProductSynchPrice = GETPOST('ecommerce_product_synch_price', 'alpha');
            $ecommerceWoocommerceCustomerRoles = GETPOST('ecommerce_woocommerce_customer_roles', 'alpha');

            $ecommerceProductSynchDirection = array(
                'image' => isset($_POST['ecommerce_product_image_synch_direction']) ? GETPOST('ecommerce_product_image_synch_direction', 'alpha') : 'etod',
                'ref' => isset($_POST['ecommerce_product_ref_synch_direction']) ? GETPOST('ecommerce_product_ref_synch_direction', 'alpha') : 'etod',
                'description' => isset($_POST['ecommerce_product_description_synch_direction']) ? GETPOST('ecommerce_product_description_synch_direction', 'alpha') : 'etod',
                'short_description' => isset($_POST['ecommerce_product_short_description_synch_direction']) ? GETPOST('ecommerce_product_short_description_synch_direction', 'alpha') : 'etod',
                'weight' => isset($_POST['ecommerce_product_weight_synch_direction']) ? GETPOST('ecommerce_product_weight_synch_direction', 'alpha') : 'etod',
                'tax' => isset($_POST['ecommerce_product_tax_synch_direction']) ? GETPOST('ecommerce_product_tax_synch_direction', 'alpha') : 'etod',
                'status' => isset($_POST['ecommerce_product_status_synch_direction']) ? GETPOST('ecommerce_product_status_synch_direction', 'alpha') : 'etod',
            );

            $siteDb->parameters = array(
				'shipping_service' => $_POST['ecommerce_fk_shipping_service'],
				'discount_code_service' => $_POST['ecommerce_fk_discount_code_service'],
				'pw_gift_cards_service' => $_POST['ecommerce_fk_pw_gift_cards_service'],
                'web_hooks_secret' => $_POST['ecommerce_web_hooks_secret'],
				'order_status_etod' => $ecommerceOrderStatusForECommerceToDolibarr,
                'order_status_dtoe' => $ecommerceOrderStatusForDolibarrToECommerce,
                'ef_crp' => $ecommerceExtrafieldsCorrespondence,
                'payment_cond' => $_POST['ecommerce_payment_cond'],
                'realtime_dtoe' => $ecommerceRealtimeDolibarrToECommerce,
                'product_synch_direction' => $ecommerceProductSynchDirection,
                'product_synch_price' => $ecommerceProductSynchPrice,
                'customer_roles' => $ecommerceWoocommerceCustomerRoles,
            );
            if ($conf->commande->enabled || $conf->facture->enabled || $conf->supplier_invoice->enabled) {
                $siteDb->parameters['order_actions'] = $ecommerceOrderActions;
            }
            if ($ecommerceOrderActions['create_order'] || $ecommerceOrderActions['create_invoice'] || $ecommerceOrderActions['create_supplier_invoice']) {
                $siteDb->parameters['default_sales_representative_follow'] = isset($_POST['default_sales_representative_follow']) ? GETPOST('default_sales_representative_follow', 'int') : 0;
            }
        }

        $result = 0;
        if (intval($_POST['ecommerce_id']) > 0)
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
                    ], [
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
                    ], [
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
                    ], [
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
                    ], [
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
                    ], [
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
                    ], [
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
                    ], [
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
                    ], [
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
							"3_trash" => $langs->trans('ECommercengWoocommerceOrderStatusTrash'),
                        )),
                        'alwayseditable' => 0,
                        'perms' => '',
                        'list' => 1,
                    ], [
						'attrname' => "ecommerceng_wc_link_{$siteDb->id}_{$conf->entity}",
						'label' => $langs->trans('ECommercengWoocommerceOrderLink', $siteDb->name),
						'type' => 'url',
						'pos' => 3,
						'size' => '',
						'elementtype' => 'commande',
						'unique' => 0,
						'required' => 0,
						'default_value' => '',
						'param' => null,
						'alwayseditable' => 0,
						'perms' => '',
						'list' => 1,
					], [
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

            if ($result > 0 && (!empty($ecommerceOrderActions['create_order']) || !empty($ecommerceOrderActions['create_invoice']) || !empty($ecommerceOrderActions['create_supplier_invoice']))) {
                // Payment gateways correspondence
                $ecommercePaymentGateways = $pay_gateways->get_all($siteDb->id);
                if (is_array($ecommercePaymentGateways)) {
                    foreach ($ecommercePaymentGateways as $payment_gateway_id => $infos) {
                        $ecommercePaymentGateways[$payment_gateway_id]['payment_mode_id'] = isset($_POST['payment_mode_id_' . $payment_gateway_id]) && (!empty($ecommerceOrderActions['create_order']) || !empty($ecommerceOrderActions['create_invoice']) || !empty($ecommerceOrderActions['create_supplier_invoice'])) ? GETPOST('payment_mode_id_' . $payment_gateway_id, 'int') : 0;
                        $ecommercePaymentGateways[$payment_gateway_id]['bank_account_id'] = isset($_POST['bank_account_id_' . $payment_gateway_id]) && $conf->banque->enabled && (!empty($ecommerceOrderActions['create_invoice']) || !empty($ecommerceOrderActions['create_supplier_invoice'])) ? GETPOST('bank_account_id_' . $payment_gateway_id, 'int') : 0;
                        $ecommercePaymentGateways[$payment_gateway_id]['create_invoice_payment'] = isset($_POST['create_invoice_payment_' . $payment_gateway_id]) && !empty($ecommerceOrderActions['create_invoice']) ? GETPOST('create_invoice_payment_' . $payment_gateway_id, 'int') : 0;
                        $ecommercePaymentGateways[$payment_gateway_id]['mail_model_for_send_invoice'] = isset($_POST['mail_model_for_send_invoice_' . $payment_gateway_id]) && !empty($ecommerceOrderActions['send_invoice_by_mail']) ? GETPOST('mail_model_for_send_invoice_' . $payment_gateway_id, 'int') : 0;
                        $ecommercePaymentGateways[$payment_gateway_id]['supplier_id'] = isset($_POST['supplier_id_' . $payment_gateway_id]) && !empty($ecommerceOrderActions['create_supplier_invoice']) ? GETPOST('supplier_id_' . $payment_gateway_id, 'int') : 0;
                        $ecommercePaymentGateways[$payment_gateway_id]['product_id_for_fee'] = isset($_POST['product_id_for_fee_' . $payment_gateway_id]) && !empty($ecommerceOrderActions['create_supplier_invoice']) ? GETPOST('product_id_for_fee_' . $payment_gateway_id, 'int') : 0;
                        $ecommercePaymentGateways[$payment_gateway_id]['create_supplier_invoice_payment'] = isset($_POST['create_supplier_invoice_payment_' . $payment_gateway_id]) && !empty($ecommerceOrderActions['create_supplier_invoice']) ? GETPOST('create_supplier_invoice_payment_' . $payment_gateway_id, 'int') : 0;
                    }

                    $result = $pay_gateways->set($siteDb->id, $ecommercePaymentGateways);
                    if ($result < 0) {
                        setEventMessages($pay_gateways->error, $pay_gateways->errors, 'errors');
                    }
                } else {
                    setEventMessages($pay_gateways->error, $pay_gateways->errors, 'errors');
                    $result = -1;
                }
            }
        }

        if ($result > 0)
        {
            $eCommerceMenu = new eCommerceMenu($db, $siteDb);
            $eCommerceMenu->updateMenu();
            $db->commit();

//            if ($siteDb->type == 2) { // Woocommerce
//                ecommerceng_update_woocommerce_dict_tax_class($db, $siteDb);
//            }
            if (!empty($conf->global->PRODUIT_MULTIPRICES) && $siteDb->price_level != $last_price_level) {
                updatePriceLevel($siteDb);
            }

            setEventMessages($langs->trans('ECommerceSetupSaved'), null);
            Header("Location: " . $_SERVER["PHP_SELF"] . "?ecommerce_id=".$siteDb->id);
            exit;
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
    $db->begin();
    $siteDb->id = $_POST['ecommerce_id'];
    $result = $siteDb->delete($user);
    if ($result < 0)
    {
        setEventMessages($langs->trans('ECommerceDeleteErrorDb'), null, 'errors');
    }
    else
    {
        $efields = new ExtraFields($db);
        $efields->delete("ecommerceng_description_{$conf->entity}");
        $efields->delete("ecommerceng_short_description_{$conf->entity}");
        $efields->delete("ecommerceng_wc_status_{$siteDb->id}_{$conf->entity}");
        $efields->delete("ecommerceng_tax_class_{$siteDb->id}_{$conf->entity}");
//        $efields->delete("ecommerceng_wc_regular_price_{$siteDb->id}_{$conf->entity}");
        $efields->delete("ecommerceng_wc_sale_price_{$siteDb->id}_{$conf->entity}");
        $efields->delete("ecommerceng_wc_date_on_sale_from_{$siteDb->id}_{$conf->entity}");
        $efields->delete("ecommerceng_wc_date_on_sale_to_{$siteDb->id}_{$conf->entity}");
        $efields->delete("ecommerceng_online_payment_{$conf->entity}");
        $efields->delete("ecommerceng_wc_status_{$siteDb->id}_{$conf->entity}");
        $efields->delete("ecommerceng_wc_role_{$siteDb->id}_{$conf->entity}");

        // Delete all VAT for the site
        $resql = $this->db->query('DELETE FROM ' . MAIN_DB_PREFIX . 'c_ecommerceng_tax_class WHERE site_id = ' . $siteDb->id . ' AND entity = ' . $conf->entity);

        $pay_gateways->delete_all($siteDb->id);

        $eCommerceMenu = new eCommerceMenu($db, $siteDb);
        $eCommerceMenu->updateMenu();
        $success[] = $langs->trans('ECommerceDeleteOk');
        $siteDb->id = null;
        unset($_POST);
        Header("Location: " . $_SERVER["PHP_SELF"]);
        exit;
    }
}
// Update dictionary for tax class of woocommerce
elseif ($_POST['site_form_detail_action'] == 'update_woocommerce_tax_class') {
    if (ecommerceng_update_woocommerce_dict_tax($db, $siteDb)) {
        setEventMessage($langs->trans('ECommercengWoocommerceDictTaxClassUpdated'));
    }
}
// Update payment gateways
elseif ($_POST['site_form_detail_action'] == 'update_payment_gateways') {
    if (ecommerceng_update_payment_gateways($db, $siteDb)) {
        setEventMessage($langs->trans('ECommercePaymentGatewaysUpdated'));
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

if ($siteId > 0)
    $siteDb->fetch($siteId);

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
$ecommerceId = $siteId;
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
$ecommerceOrderActions = (isset($siteDb->parameters['order_actions']) ? $siteDb->parameters['order_actions'] : array());
$ecommerceDefaultSalesRepresentativeFollow = (isset($siteDb->parameters['default_sales_representative_follow']) ? $siteDb->parameters['default_sales_representative_follow'] : 0);
/*$ecommerceTimeout = 300;
if (isset($_POST['ecommerce_timeout']))
    $ecommerceTimeout = $_POST['ecommerce_timeout'];
elseif (isset($siteDb->timeout))
    $ecommerceTimeout = $siteDb->timeout;*/
$ecommerceOAuth = false;
$ecommerceOAuthGenerateToken = false;
$ecommerceOrderStatus = false;
if ($ecommerceId > 0) {
    if ($ecommerceType == 2) {
		$ecommerceOAuth = true;
		$ecommerceOAuthWordpressOAuthSetupUri = $ecommerceWebserviceAddress . (substr($ecommerceWebserviceAddress, -1, 1) != '/' ? '/' : '') . 'wp-admin/admin.php?page=wo_settings#clients';
		$ecommerceOrderStatus = $conf->commande->enabled;

		$uriFactory = new \OAuth\Common\Http\Uri\UriFactory();
		$currentUri = $uriFactory->createFromAbsolute(dol_buildpath('/ecommerceng/webhooks.php', 2) . '?ecommerce_id=' . $siteId);
		$eCommerceSiteWebHooksUrl = $currentUri->getAbsoluteUri();
		$eCommerceSiteWebHooksSecret = (!empty($_POST['ecommerce_web_hooks_secret']) ? $_POST['ecommerce_web_hooks_secret'] : (!empty($siteDb->parameters['web_hooks_secret']) ? $siteDb->parameters['web_hooks_secret'] : ''));

		$ecommerceFkShippingService = (!empty($_POST['ecommerce_fk_shipping_service']) ? $_POST['ecommerce_fk_shipping_service'] : (!empty($siteDb->parameters['shipping_service']) ? $siteDb->parameters['shipping_service'] : ''));
		$ecommerceFkDiscountCodeService = (!empty($_POST['ecommerce_fk_discount_code_service']) ? $_POST['ecommerce_fk_discount_code_service'] : (!empty($siteDb->parameters['discount_code_service']) ? $siteDb->parameters['discount_code_service'] : ''));
		$ecommerceFkPwGiftCardsService = (!empty($_POST['ecommerce_fk_pw_gift_cards_service']) ? $_POST['ecommerce_fk_pw_gift_cards_service'] : (!empty($siteDb->parameters['pw_gift_cards_service']) ? $siteDb->parameters['pw_gift_cards_service'] : ''));
	}

    if ($ecommerceOAuth) {
        // Create callback URL
        //$urlwithouturlroot=preg_replace('/'.preg_quote(DOL_URL_ROOT,'/').'$/i','',trim($dolibarr_main_url_root));
        //$urlwithroot=$urlwithouturlroot.DOL_URL_ROOT;		// This is to use external domain name found into config file
        $uriFactory = new \OAuth\Common\Http\Uri\UriFactory();
        //$currentUri = $uriFactory->createFromAbsolute($urlwithroot.'/ecommerceng/core/modules/oauth/wordpress_oauthcallback.php?ecommerce_id='.$siteId);
        $currentUri = $uriFactory->createFromAbsolute(dol_buildpath('/ecommerceng/core/modules/oauth/wordpress_oauthcallback.php', 2).'?ecommerce_id='.$siteId);
        $ecommerceOAuthRedirectUri = $currentUri->getAbsoluteUri();
//        $ecommerceOAuthRedirectUri = dol_buildpath('/ecommerceng/core/modules/oauth/wordpress_oauthcallback.php', 2).'?ecommerce_id='.$ecommerceId;
        $ecommerceOAuthId = ($_POST['ecommerce_oauth_id'] ? $_POST['ecommerce_oauth_id'] : $siteDb->oauth_id);
        $ecommerceOAuthSecret = ($_POST['ecommerce_oauth_secret'] ? $_POST['ecommerce_oauth_secret'] : $siteDb->oauth_secret);

        // Token
        $ecommerceOAuthTokenObj = null;
        $storage = new DoliStorage($db, $conf);
        try {
            $ecommerceOAuthTokenObj = $storage->retrieveAccessToken('ECommerce_'.$ecommerceId);
        } catch(Exception $e) {}
        $ecommerceOAuthGenerateToken = (!empty($ecommerceOAuthId) && !empty($ecommerceOAuthSecret) || is_object($ecommerceOAuthTokenObj));

        $ecommerceOAuthBackToUri = urlencode(dol_buildpath('/ecommerceng/admin/eCommerceSetup.php', 2).'?ecommerce_id='.$ecommerceId);

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
        $defaultOrderStatusForECommerceToDolibarr = array(
            "pending" => array('selected' => 's' . Commande::STATUS_DRAFT, 'billed' => 0),
            "processing" => array('selected' => 's' . Commande::STATUS_VALIDATED, 'billed' => 0),
            "on-hold" => array('selected' => 's' . Commande::STATUS_DRAFT, 'billed' => 0),
            "completed" => array('selected' => 's' . Commande::STATUS_CLOSED, 'billed' => 1),
            "cancelled" => array('selected' => 's' . Commande::STATUS_CANCELED, 'billed' => 0),
            "refunded" => array('selected' => 's' . Commande::STATUS_CANCELED, 'billed' => 1),
            "failed" => array('selected' => 's' . Commande::STATUS_CANCELED, 'billed' => 0),
        );

        if (isset($efields->attribute_param["ecommerceng_wc_status_{$siteDb->id}_{$conf->entity}"]['options']) &&
            is_array($efields->attribute_param["ecommerceng_wc_status_{$siteDb->id}_{$conf->entity}"]['options'])) {
            foreach ($efields->attribute_param["ecommerceng_wc_status_{$siteDb->id}_{$conf->entity}"]['options'] as $key => $value) {
                if (($pos = strpos($key , '_')) > 0) $key = substr($key, $pos + 1);
                $selected = GETPOST('order_status_etod_' . $key, 'alpha');
                $selected = $selected ? $selected : (isset($siteDb->parameters['order_status_etod'][$key]['selected']) ? $siteDb->parameters['order_status_etod'][$key]['selected'] : $defaultOrderStatusForECommerceToDolibarr[$key]['selected']);
                $billed = isset($_POST['order_status_etod_billed_' . $key]) ? GETPOST('order_status_etod_billed_' . $key, 'alpha') :
                    (isset($siteDb->parameters['order_status_etod'][$key]['billed']) ? $siteDb->parameters['order_status_etod'][$key]['billed'] : $defaultOrderStatusForECommerceToDolibarr[$key]['billed']);
                $ecommerceOrderStatusForECommerceToDolibarr[$key] = array('label' => $value, 'selected' => $selected, 'billed' => $billed);
            }
        }

        $commande = new Commande($db);
        $langs->load('orders');
        $langs->load('bills');
        $ecommerceOrderStatusForDolibarrToECommerce = array();
        $defaultOrderStatusForDolibarrToECommerce = array(
            Commande::STATUS_CANCELED => 'cancelled',
            Commande::STATUS_DRAFT => 'on-hold',
            Commande::STATUS_VALIDATED => 'processing',
            Commande::STATUS_ACCEPTED => 'processing',
            Commande::STATUS_CLOSED => 'completed',
        );
        $selected = GETPOST('order_status_dtoe_' . Commande::STATUS_CANCELED, 'alpha');
        $selected = $selected ? $selected : (isset($siteDb->parameters['order_status_dtoe'][Commande::STATUS_CANCELED]) ? $siteDb->parameters['order_status_dtoe'][Commande::STATUS_CANCELED] : $defaultOrderStatusForDolibarrToECommerce[Commande::STATUS_CANCELED]);
        $ecommerceOrderStatusForDolibarrToECommerce['s'.Commande::STATUS_CANCELED] = array(
            'label' => $commande->LibStatut(Commande::STATUS_CANCELED, 0, 0, 1),
            'selected' => $selected
        );
        $selected = GETPOST('order_status_dtoe_' . Commande::STATUS_DRAFT, 'alpha');
        $selected = $selected ? $selected : (isset($siteDb->parameters['order_status_dtoe'][Commande::STATUS_DRAFT]) ? $siteDb->parameters['order_status_dtoe'][Commande::STATUS_DRAFT] : $defaultOrderStatusForDolibarrToECommerce[Commande::STATUS_DRAFT]);
        $ecommerceOrderStatusForDolibarrToECommerce['s'.Commande::STATUS_DRAFT] = array(
            'label' => $commande->LibStatut(Commande::STATUS_DRAFT, 0, 0, 1),
            'selected' => $selected
        );
        $selected = GETPOST('order_status_dtoe_' . Commande::STATUS_VALIDATED, 'alpha');
        $selected = $selected ? $selected : (isset($siteDb->parameters['order_status_dtoe'][Commande::STATUS_VALIDATED]) ? $siteDb->parameters['order_status_dtoe'][Commande::STATUS_VALIDATED] : $defaultOrderStatusForDolibarrToECommerce[Commande::STATUS_VALIDATED]);
        $ecommerceOrderStatusForDolibarrToECommerce['s'.Commande::STATUS_VALIDATED] = array(
            'label' => $commande->LibStatut(Commande::STATUS_VALIDATED, 0, 0, 1),
            'selected' => $selected
        );
        $selected = GETPOST('order_status_dtoe_' . Commande::STATUS_ACCEPTED, 'alpha');
        $selected = $selected ? $selected : (isset($siteDb->parameters['order_status_dtoe'][Commande::STATUS_ACCEPTED]) ? $siteDb->parameters['order_status_dtoe'][Commande::STATUS_ACCEPTED] : $defaultOrderStatusForDolibarrToECommerce[Commande::STATUS_ACCEPTED]);
        $ecommerceOrderStatusForDolibarrToECommerce['s'.Commande::STATUS_ACCEPTED] = array(
            'label' => $commande->LibStatut(Commande::STATUS_ACCEPTED, 0, 0, 1),
            'selected' => $selected
        );
        $selected = GETPOST('order_status_dtoe_' . Commande::STATUS_CLOSED, 'alpha');
        $selected = $selected ? $selected : (isset($siteDb->parameters['order_status_dtoe'][Commande::STATUS_CLOSED]) ? $siteDb->parameters['order_status_dtoe'][Commande::STATUS_CLOSED] : $defaultOrderStatusForDolibarrToECommerce[Commande::STATUS_CLOSED]);
        $ecommerceOrderStatusForDolibarrToECommerce['s'.Commande::STATUS_CLOSED] = array(
            'label' => $commande->LibStatut(Commande::STATUS_CLOSED, 0, 0, 1),
            'selected' => $selected
        );
    }

    // Payment gateways correspondence
    if (!empty($ecommerceOrderActions['create_order']) || !empty($ecommerceOrderActions['create_invoice']) || !empty($ecommerceOrderActions['create_supplier_invoice'])) {
        $ecommercePaymentGateways = $pay_gateways->get_all($siteDb->id);
        if (!is_array($ecommercePaymentGateways) && $ecommercePaymentGateways < 0) {
            setEventMessages($pay_gateways->error, $pay_gateways->errors, 'errors');
        }
    }

    if ($ecommerceType == 2) {
        // Extrafields correspondence
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
        $ecommerceProductImageSynchDirection = isset($siteDb->parameters['product_synch_direction']['image']) ? $siteDb->parameters['product_synch_direction']['image'] : 'etod';
        $ecommerceProductRefSynchDirection = isset($siteDb->parameters['product_synch_direction']['ref']) ? $siteDb->parameters['product_synch_direction']['ref'] : 'etod';
        $ecommerceProductDescriptionSynchDirection = isset($siteDb->parameters['product_synch_direction']['description']) ? $siteDb->parameters['product_synch_direction']['description'] : 'etod';
        $ecommerceProductShortDescriptionSynchDirection = isset($siteDb->parameters['product_synch_direction']['short_description']) ? $siteDb->parameters['product_synch_direction']['short_description'] : 'etod';
        $ecommerceProductWeightSynchDirection = isset($siteDb->parameters['product_synch_direction']['weight']) ? $siteDb->parameters['product_synch_direction']['weight'] : 'etod';
        $ecommerceProductTaxSynchDirection = isset($siteDb->parameters['product_synch_direction']['tax']) ? $siteDb->parameters['product_synch_direction']['tax'] : 'etod';
        $ecommerceProductStatusSynchDirection = isset($siteDb->parameters['product_synch_direction']['status']) ? $siteDb->parameters['product_synch_direction']['status'] : 'etod';
        $ecommerceWoocommerceCustomerRoles = isset($siteDb->parameters['customer_roles']) ? $siteDb->parameters['customer_roles'] : 'customer';
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
