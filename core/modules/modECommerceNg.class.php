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


require_once(DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php');
require_once(DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php');
require_once(DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php');

dol_include_once('/ecommerceng/admin/class/gui/eCommerceMenu.class.php');
dol_include_once('/ecommerceng/admin/class/data/eCommerceDict.class.php');
dol_include_once('/ecommerceng/class/data/eCommerceSite.class.php');


/**
 *  Description and activation class for module ECommerce
 */
class modECommerceNg extends DolibarrModules
{
	/**
	 *   Constructor. Define names, constants, directories, boxes, permissions
	 *
	 *   @param    DoliDB      $db      Database handler
	 */
	function __construct($db)
	{
		global $conf, $langs;

		$this->db = $db;

		// Id for module (must be unique).
		// Use here a free id (See in Home -> System information -> Dolibarr for list of used modules id).
		$this->numero = 107100;
		// Key text used to identify module (for permissions, menus, etc...)
		$this->rights_class = 'ecommerceng';

		// Family can be 'crm','financial','hr','projects','products','ecm','technic','other'
		// It is used to group modules in module setup page
		$this->family = "other";
		// Module label (no space allowed), used if translation string 'ModuleXXXName' not found (where XXX is value of numeric property 'numero' of module)
		$this->name = 'EcommerceNg';        //  Must be same than value used for if $conf->ecommerceng->enabled
		// Module description, used if translation string 'ModuleXXXDesc' not found (where XXX is value of numeric property 'numero' of module)
		$this->description = "Module to synchronise Dolibarr with ECommerce platform (currently ecommerce supported: WooCommerce)";
		$this->descriptionlong = "See page https://wiki.dolibarr.org/index.php/Module_Magento_EN for more information";
		$this->editor_name = 'Open-Dsi';
		$this->editor_url = 'http://www.open-dsi.fr';

		// Possible values for version are: 'development', 'experimental', 'dolibarr' or version
		$this->version = '4.1.28';
		// Key used in llx_const table to save module status enabled/disabled (where MYMODULE is value of property name of module in uppercase)
		$this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);
		// Where to store the module in setup page (0=common,1=interface,2=others,3=very specific)
		$this->special = 1;
		// Name of image file used for this module.
		// If file is in theme/yourtheme/img directory under name object_pictovalue.png, use this->picto='pictovalue'
		// If file is in module/images directory, use this->picto=DOL_URL_ROOT.'/module/images/file.png'
		$this->picto = 'eCommerce.png@ecommerceng';

		// Defined all module parts (triggers, login, substitutions, menus, css, etc...)
		// for default path (eg: /mymodule/core/xxxxx) (0=disable, 1=enable)
		// for specific path of parts (eg: /mymodule/core/modules/barcode)
		// for specific css file (eg: /mymodule/css/mymodule.css.php)
		//$this->module_parts = array(
		//                        	'triggers' => 0,                                 // Set this to 1 if module has its own trigger directory
		//							'login' => 0,                                    // Set this to 1 if module has its own login method directory
		//							'substitutions' => 0,                            // Set this to 1 if module has its own substitution function file
		//							'menus' => 0,                                    // Set this to 1 if module has its own menus handler directory
		//							'barcode' => 0,                                  // Set this to 1 if module has its own barcode directory
		//							'models' => 0,                                   // Set this to 1 if module has its own models directory
		//							'css' => '/mymodule/css/mymodule.css.php',       // Set this to relative path of css if module has its own css file
		//							'hooks' => array('hookcontext1','hookcontext2')  // Set here all hooks context managed by module
		//							'workflow' => array('order' => array('WORKFLOW_ORDER_AUTOCREATE_INVOICE')) // Set here all workflow context managed by module
		//                        );
		$this->module_parts = array(
			'triggers' => 1,
			'hooks' => array('expeditioncard', 'invoicecard', 'productdocuments', 'productcard', 'thirdpartycard'),
		);

		// Data directories to create when module is enabled.
		// Example: this->dirs = array("/mymodule/temp");
		$this->dirs = array();
		$r = 0;

		// Relative path to module style sheet if exists. Example: '/mymodule/mycss.css'.
		$this->style_sheet = '';

		// Config pages. Put here list of php page names stored in admmin directory used to setup module.
		$this->config_page_url = array('eCommerceSetup.php@ecommerceng');

		// Dependencies
		$this->depends = array("modSociete", "modProduct", "modCategorie", "modWebServices");        // List of modules id that must be enabled if this module is enabled
		$this->requiredby = array();    // List of modules id to disable if this one is disabled
		$this->phpmin = array(5, 3);                    // Minimum version of PHP required by module
		$this->need_dolibarr_version = array(3, 9);    // Minimum version of Dolibarr required by module
		$this->langfiles = array("ecommerce@ecommerceng", "woocommerce@ecommerceng");

		// Constants
		// List of particular constants to add when module is enabled
		$this->const = array(
			0 => array('ECOMMERCENG_SHOW_DEBUG_TOOLS', 'chaine', '1', 'Enable button to clean database for debug purpose', 1, 'allentities', 1),
			1 => array('ECOMMERCENG_DEBUG', 'chaine', '0', 'This is to enable ECommerceng log of web services requests', 1, 'allentities', 0),
			2 => array('ECOMMERCENG_MAXSIZE_MULTICALL', 'chaine', '400', 'Max size for multicall', 1, 'allentities', 0),
			3 => array('ECOMMERCENG_MAXRECORD_PERSYNC', 'chaine', '2000', 'Max nb of record per synch', 1, 'allentities', 0),
			4 => array('ECOMMERCENG_ENABLE_LOG_IN_NOTE', 'chaine', '0', 'Store into private note the last full response returned by web service', 1, 'allentities', 0),
			5 => array('ECOMMERCENG_WOOCOMMERCE_ORDER_STATUS_LVL_CHECK', 'chaine', '1', '', 0, 'current', 0),
			6 => array('ECOMMERCENG_NO_COUNT_UPDATE', 'chaine', '1', '', 0, 'allentities', 0),
		);

		// Array to add new pages in new tabs
		//$this->tabs = array('entity:Title:@mymodule:/mymodule/mynewtab.php?id=__ID__');
		// where entity can be
		// 'thirdparty'       to add a tab in third party view
		// 'intervention'     to add a tab in intervention view
		// 'supplier_order'   to add a tab in supplier order view
		// 'supplier_invoice' to add a tab in supplier invoice view
		// 'invoice'          to add a tab in customer invoice view
		// 'order'            to add a tab in customer order view
		// 'product'          to add a tab in product view
		// 'propal'           to add a tab in propal view
		// 'member'           to add a tab in fundation member view
		// 'contract'         to add a tab in contract view

		if (!isset($conf->ecommerceng) || !isset($conf->ecommerceng->enabled)) {
			$conf->ecommerceng = new stdClass();
			$conf->ecommerceng->enabled = 0;
		}

		$eCommerceSite = new eCommerceSite($this->db);

		// Dictionaries
		$this->dictionaries = array(
			'langs' => 'woocommerce@ecommerceng',
			'tabname' => array(
				MAIN_DB_PREFIX . "c_ecommerceng_tax_class",
				MAIN_DB_PREFIX . "c_ecommerceng_tax_rate",
				MAIN_DB_PREFIX . "c_ecommerceng_attribute",
			),
			'tablib' => array(
				"ECommercengWoocommerceDictTaxClass",
				"ECommercengWoocommerceDictTaxRate",
				"ECommercengWoocommerceDictAttribute",
			),
			'tabsql' => array(
				'SELECT f.rowid as rowid, f.site_id, f.code, f.label, f.entity, f.active FROM ' . MAIN_DB_PREFIX . 'c_ecommerceng_tax_class as f WHERE f.entity=' . $conf->entity,
				'SELECT f.rowid as rowid, f.site_id, f.tax_id, f.tax_country, f.tax_state, f.tax_postcode, f.tax_city, f.tax_rate, f.tax_name, f.tax_priority, f.tax_compound, f.tax_shipping, f.tax_order, f.tax_class, f.entity, f.active FROM ' . MAIN_DB_PREFIX . 'c_ecommerceng_tax_rate as f WHERE f.entity=' . $conf->entity,
				'SELECT f.rowid as rowid, f.site_id, f.attribute_id, f.attribute_name, f.attribute_slug, f.attribute_type, f.attribute_order_by, f.attribute_has_archives, f.entity, f.active FROM ' . MAIN_DB_PREFIX . 'c_ecommerceng_attribute as f WHERE f.entity=' . $conf->entity,
			),
			'tabsqlsort' => array(
				"site_id ASC, label ASC",
				"site_id ASC, tax_id ASC",
				"site_id ASC, attribute_id ASC",
			),
			'tabfield' => array(
				"code,label,site_id",
				"tax_id,tax_country,tax_state,tax_postcode,tax_city,tax_rate,tax_name,tax_priority,tax_compound,tax_shipping,tax_order,tax_class,site_id",
				"attribute_id,attribute_name,attribute_slug,attribute_type,attribute_order_by,attribute_has_archives,site_id",
			),
			'tabfieldvalue' => array(
				"code,label,site_id",
				"tax_id,tax_country,tax_state,tax_postcode,tax_city,tax_rate,tax_name,tax_priority,tax_compound,tax_shipping,tax_order,tax_class,site_id",
				"attribute_id,attribute_name,attribute_slug,attribute_type,attribute_order_by,attribute_has_archives,site_id",
			),
			'tabfieldinsert' => array(
				"code,label,site_id",
				"tax_id,tax_country,tax_state,tax_postcode,tax_city,tax_rate,tax_name,tax_priority,tax_compound,tax_shipping,tax_order,tax_class,site_id",
				"attribute_id,attribute_name,attribute_slug,attribute_type,attribute_order_by,attribute_has_archives,site_id",
			),
			'tabrowid' => array(
				"rowid",
				"rowid",
				"rowid",
			),
			'tabcond' => array(
				$conf->ecommerceng->enabled && $eCommerceSite->hasTypeSite(2),
				$conf->ecommerceng->enabled && $eCommerceSite->hasTypeSite(2),
				$conf->ecommerceng->enabled && $eCommerceSite->hasTypeSite(2),
			),
		);

		/* Example:
		$this->dictionaries=array(
			'langs'=>'mylangfile@mymodule',
			'tabname'=>array(MAIN_DB_PREFIX."table1",MAIN_DB_PREFIX."table2",MAIN_DB_PREFIX."table3"),		// List of tables we want to see into dictonnary editor
			'tablib'=>array("Table1","Table2","Table3"),													// Label of tables
			'tabsql'=>array('SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.MAIN_DB_PREFIX.'table1 as f','SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.MAIN_DB_PREFIX.'table2 as f','SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.MAIN_DB_PREFIX.'table3 as f'),	// Request to select fields
			'tabsqlsort'=>array("label ASC","label ASC","label ASC"),																					// Sort order
			'tabfield'=>array("code,label","code,label","code,label"),																					// List of fields (result of select to show dictionary)
			'tabfieldvalue'=>array("code,label","code,label","code,label"),																				// List of fields (list of fields to edit a record)
			'tabfieldinsert'=>array("code,label","code,label","code,label"),																			// List of fields (list of fields for insert)
			'tabrowid'=>array("rowid","rowid","rowid"),																									// Name of columns with primary key (try to always name it 'rowid')
			'tabcond'=>array($conf->mymodule->enabled,$conf->mymodule->enabled,$conf->mymodule->enabled)												// Condition to show each dictionary
		);
		*/

		// Boxes
		$this->boxes = array(
			0 => array('file' => 'box_ecommerce_webhooks@ecommerceng', 'note' => $langs->trans('ECommerceBoxWebHooks')),
		);            // List of boxes
		$r = 0;

		// Add here list of php file(s) stored in includes/boxes that contains class to show a box.
		// Example:
		//$this->boxes[$r][1] = "myboxa.php";
		//$r++;
		//$this->boxes[$r][1] = "myboxb.php";
		//$r++;

		// Cronjobs
		//------------
		$this->cronjobs = array(
			//	0=>array('label'=>'AutoSyncEcommerceNg', 'jobtype'=>'method', 'class'=>'ecommerceng/class/business/eCommerceUtils.class.php', 'objectname'=>'eCommerceUtils', 'method'=>'synchAll', 'parameters'=>'100', 'comment'=>'Synchronize all data from eCommerce to Dolibarr. Parameter is max nb of record to do per synchronization run.', 'frequency'=>1, 'unitfrequency'=>86400, 'priority'=>90, 'status'=>0, 'test'=>true),
			1 => array('label' => 'ECommerceProcessPendingWebHooks', 'jobtype' => 'method', 'class' => '/ecommerceng/class/business/eCommercePendingWebHook.class.php', 'objectname' => 'eCommercePendingWebHook', 'method' => 'cronProcessPendingWebHooks', 'parameters' => '', 'comment' => 'Process all pending WebHooks.', 'frequency' => 15, 'unitfrequency' => 60, 'priority' => 90, 'status' => 1, 'test' => '$conf->ecommerceng->enabled'),
			2 => array('label' => 'ECommerceCheckWebHooksStatus', 'jobtype' => 'method', 'class' => '/ecommerceng/class/business/eCommercePendingWebHook.class.php', 'objectname' => 'eCommercePendingWebHook', 'method' => 'cronCheckWebHooksStatus', 'parameters' => '', 'comment' => 'Check WebHooks status.', 'frequency' => 15, 'unitfrequency' => 60, 'priority' => 80, 'status' => 1, 'test' => '$conf->ecommerceng->enabled'),
			3 => array('label' => 'ECommerceCheckWebHooksVolumetry', 'jobtype' => 'method', 'class' => '/ecommerceng/class/business/eCommercePendingWebHook.class.php', 'objectname' => 'eCommercePendingWebHook', 'method' => 'cronCheckWebHooksVolumetry', 'parameters' => '', 'comment' => 'Check WebHooks volumetry.', 'frequency' => 15, 'unitfrequency' => 60, 'priority' => 70, 'status' => 0, 'test' => '$conf->ecommerceng->enabled'),
		);

		// Permissions
		$this->rights = array();        // Permission array used by this module
		$this->rights_class = 'ecommerceng';
		$r = 0;

		$r++;
		$this->rights[$r][0] = 107101;
		$this->rights[$r][1] = 'See synchronization status';
		$this->rights[$r][3] = 1;
		$this->rights[$r][4] = 'read';

		$r++;
		$this->rights[$r][0] = 107102;
		$this->rights[$r][1] = 'Synchronize';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'write';

		$r++;
		$this->rights[$r][0] = 107103;
		$this->rights[$r][1] = 'Configure websites';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'site';

		$r = 0;

		// Add here list of permission defined by an id, a label, a boolean and two constant strings.
		// Example:
		// $this->rights[$r][0] = 2000; 				// Permission id (must not be already used)
		// $this->rights[$r][1] = 'Permision label';	// Permission label
		// $this->rights[$r][3] = 1; 					// Permission by default for new user (0/1)
		// $this->rights[$r][4] = 'level1';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		// $this->rights[$r][5] = 'level2';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		// $r++;


		// Main menu entries
		$this->menu = array();            // List of menus to add
		$r = 0;

		//define main left menu
		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=tools',
			'type' => 'left',
			'titre' => 'ECommerceMenuMain',
			'leftmenu' => 'ecommerceng',
			'url' => '/ecommerceng/index.php',
			'langs' => 'ecommerce@ecommerceng',
			'position' => 100,
			'enabled' => '$conf->ecommerceng->enabled',
			'perms' => '$user->rights->ecommerceng->read',
			'target' => '',
			'user' => 2
		);
		$r++;

		//define left menu
		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=tools,fk_leftmenu=ecommerceng',
			'type' => 'left',
			'titre' => 'ECommerceMenuSites',
			'leftmenu' => 'ecommerceng_sites',
			'url' => '/ecommerceng/index.php',
			'langs' => 'ecommerce@ecommerceng',
			'position' => 110,
			'enabled' => '$conf->ecommerceng->enabled',
			'perms' => '$user->rights->ecommerceng->read',
			'target' => '',
			'user' => 2
		);
		$r++;

		//add link to configuration
		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=tools,fk_leftmenu=ecommerceng',
			'type' => 'left',
			'titre' => 'ECommerceMenuSetup',
			'leftmenu' => 'ecommerceng_setup',
			'url' => '/ecommerceng/admin/eCommerceSetup.php',
			'langs' => 'ecommerce@ecommerceng',
			'position' => 120,
			'enabled' => '$conf->ecommerceng->enabled',
			'perms' => '$user->rights->ecommerceng->site',
			'target' => '',
			'user' => 2
		);
		$r++;

		// Add links for webhooks
		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=tools,fk_leftmenu=ecommerceng',
			'type' => 'left',
			'titre' => 'ECommerceMenuWebHooks',
			'leftmenu' => 'ecommerceng_webhooks',
			'url' => '/ecommerceng/webhookslist.php',
			'langs' => 'ecommerce@ecommerceng',
			'position' => 130,
			'enabled' => '$conf->ecommerceng->enabled',
			'perms' => '$user->rights->ecommerceng->read',
			'target' => '',
			'user' => 2
		);
		$r++;

		// Add here entries to declare new menus
		//if (! empty($conf->modules['ecommerceng']))     // Do not run this code if module is not yet enabled (tables does not exists yet)
		//{
//    		$eCommerceMenu = new eCommerceMenu($this->db,null,$this);
//	        $this->menu = $eCommerceMenu->getMenu();
		//}

		// Exports
		//--------
		$langs->load('products');
		$langs->load('bills');
		$r = 0;

		$r++;
		$this->export_code[$r] = $this->rights_class . '_' . $r;
		$this->export_label[$r] = "ECommerceExportProductsPrices";
		$this->export_permission[$r] = array(array("produit", "export"));
		$this->export_fields_array[$r] = array('p.ref' => "Ref", 'p.price_base_type' => "PriceBase", 'p.price_min' => "MinPriceHT", 'p.price' => "UnitPriceHT", 'p.price_min_ttc' => "MinPriceTTC", 'p.price_ttc' => "UnitPriceTTC", 'p.tva_tx' => 'VATRate');
		$this->export_TypeFields_array[$r] = array('p.ref' => "Text", 'p.price_base_type' => "Text", 'p.price_min' => "Numeric", 'p.price' => "Numeric", 'p.price_min_ttc' => "Numeric", 'p.price_ttc' => "Numeric", 'p.tva_tx' => 'Numeric');
		$this->export_entities_array[$r] = array();        // We define here only fields that use another icon that the one defined into import_icon
		$this->export_sql_start[$r] = 'SELECT DISTINCT ';
		$this->export_sql_end[$r] = ' FROM ' . MAIN_DB_PREFIX . 'product as p';
		$this->export_sql_end[$r] .= ' WHERE p.fk_product_type = 0 AND p.entity IN (' . getEntity('product') . ')';

		// Imports
		//--------
		$r = 0;

		$r++;
		$this->import_code[$r] = $this->rights_class . '_' . $r;
		$this->import_label[$r] = "ECommerceImportProductsPrices";    // Translation key
		$this->import_icon[$r] = $this->picto;
		$this->import_entities_array[$r] = array();        // We define here only fields that use another icon that the one defined into import_icon
		$this->import_tables_array[$r] = array('p' => MAIN_DB_PREFIX . 'product');
		$this->import_tables_creator_array[$r] = array('p' => 'fk_user_author');    // Fields to store import user id
		$this->import_fields_array[$r] = array('p.ref' => "Ref*", 'p.price_base_type' => "PriceBase*", 'p.price_min' => "MinPriceHT", 'p.price' => "UnitPriceHT", 'p.price_min_ttc' => "MinPriceTTC", 'p.price_ttc' => "UnitPriceTTC", 'p.tva_tx' => 'VATRate');
		$this->import_regex_array[$r] = array('p.ref' => '[^ ]', 'p.price_base_type' => '^HT|TTC$');
		$this->import_examplevalues_array[$r] = array('p.ref' => "PREF123456", 'p.price_base_type' => "HT or TTC", 'p.price_min' => "100", 'p.price' => "100", 'p.price_min_ttc' => "110", 'p.price_ttc' => "110", 'p.tva_tx' => '10');
		$this->import_updatekeys_array[$r] = array('p.ref' => 'Ref');
	}

	/**
	 *	Function called when module is enabled.
	 *	The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *	It also creates data directories.
	 *
	 *  @param     string  $options    Options
	 *  @return    int                 1 if OK, 0 if KO
	 */
	function init($options = '')
	{
		$sql = array();

		// Clean duplicate link and re-set the unique key on links tables
		// Categories
		$sql[] = [ 'sql' => "DELETE " . MAIN_DB_PREFIX . "ecommerce_category FROM " . MAIN_DB_PREFIX . "ecommerce_category
LEFT JOIN (
	SELECT MIN(t.rowid) AS rowid, t.fk_category, t.fk_site, t.remote_id
	FROM " . MAIN_DB_PREFIX . "ecommerce_category as t
	GROUP BY t.fk_category, t.fk_site, t.remote_id
	HAVING COUNT(*) > 1
) AS c ON c.rowid != " . MAIN_DB_PREFIX . "ecommerce_category.rowid AND c.fk_category = " . MAIN_DB_PREFIX . "ecommerce_category.fk_category AND c.fk_site = " . MAIN_DB_PREFIX . "ecommerce_category.fk_site AND c.remote_id = " . MAIN_DB_PREFIX . "ecommerce_category.remote_id
WHERE c.rowid IS NOT NULL" ];
		$sql[] = [ 'sql' => "ALTER TABLE " . MAIN_DB_PREFIX . "ecommerce_category  ADD UNIQUE INDEX uk_ecommerce_category_fk_site_fk_category ( fk_site, fk_category )", 'ignoreerror' => 1 ];
		$sql[] = [ 'sql' => "ALTER TABLE " . MAIN_DB_PREFIX . "ecommerce_category  ADD UNIQUE INDEX uk_ecommerce_category_fk_site_remote_id ( fk_site, remote_id )", 'ignoreerror' => 1 ];
		// Products
		$sql[] = [ 'sql' => "DELETE " . MAIN_DB_PREFIX . "ecommerce_product FROM " . MAIN_DB_PREFIX . "ecommerce_product
LEFT JOIN (
	SELECT MIN(t.rowid) AS rowid, t.fk_product, t.fk_site, t.remote_id
	FROM " . MAIN_DB_PREFIX . "ecommerce_product as t
	GROUP BY t.fk_product, t.fk_site, t.remote_id
	HAVING COUNT(*) > 1
) AS c ON c.rowid != " . MAIN_DB_PREFIX . "ecommerce_product.rowid AND c.fk_product = " . MAIN_DB_PREFIX . "ecommerce_product.fk_product AND c.fk_site = " . MAIN_DB_PREFIX . "ecommerce_product.fk_site AND c.remote_id = " . MAIN_DB_PREFIX . "ecommerce_product.remote_id
WHERE c.rowid IS NOT NULL" ];
		$sql[] = [ 'sql' => "ALTER TABLE " . MAIN_DB_PREFIX . "ecommerce_product  ADD UNIQUE KEY uk_ecommerce_product_fk_site_fk_product ( fk_site, fk_product )", 'ignoreerror' => 1 ];
		$sql[] = [ 'sql' => "ALTER TABLE " . MAIN_DB_PREFIX . "ecommerce_product  ADD UNIQUE KEY uk_ecommerce_product_fk_site_remote_id ( fk_site, remote_id )", 'ignoreerror' => 1 ];
		// Third parties
		$sql[] = [ 'sql' => "DELETE " . MAIN_DB_PREFIX . "ecommerce_societe FROM " . MAIN_DB_PREFIX . "ecommerce_societe
LEFT JOIN (
	SELECT MIN(t.rowid) AS rowid, t.fk_societe, t.fk_site, t.remote_id
	FROM " . MAIN_DB_PREFIX . "ecommerce_societe as t
	GROUP BY t.fk_societe, t.fk_site, t.remote_id
	HAVING COUNT(*) > 1
) AS c ON c.rowid != " . MAIN_DB_PREFIX . "ecommerce_societe.rowid AND c.fk_societe = " . MAIN_DB_PREFIX . "ecommerce_societe.fk_societe AND c.fk_site = " . MAIN_DB_PREFIX . "ecommerce_societe.fk_site AND c.remote_id = " . MAIN_DB_PREFIX . "ecommerce_societe.remote_id
WHERE c.rowid IS NOT NULL" ];
		$sql[] = [ 'sql' => "ALTER TABLE " . MAIN_DB_PREFIX . "ecommerce_societe  ADD UNIQUE KEY uk_ecommerce_societe_fk_site_fk_societe ( fk_site, fk_societe );", 'ignoreerror' => 1 ];
		$sql[] = [ 'sql' => "ALTER TABLE " . MAIN_DB_PREFIX . "ecommerce_societe  ADD UNIQUE KEY uk_ecommerce_societe_fk_site_remote_id ( fk_site, remote_id );", 'ignoreerror' => 1 ];
		// Contacts
		$sql[] = [ 'sql' => "DELETE " . MAIN_DB_PREFIX . "ecommerce_socpeople FROM " . MAIN_DB_PREFIX . "ecommerce_socpeople
LEFT JOIN (
	SELECT MIN(t.rowid) AS rowid, t.fk_socpeople, t.fk_site, t.remote_id, t.type
	FROM " . MAIN_DB_PREFIX . "ecommerce_socpeople as t
	GROUP BY t.fk_socpeople, t.fk_site, t.remote_id, t.type
	HAVING COUNT(*) > 1
) AS c ON c.rowid != " . MAIN_DB_PREFIX . "ecommerce_socpeople.rowid AND c.fk_socpeople = " . MAIN_DB_PREFIX . "ecommerce_socpeople.fk_socpeople AND c.fk_site = " . MAIN_DB_PREFIX . "ecommerce_socpeople.fk_site AND c.remote_id = " . MAIN_DB_PREFIX . "ecommerce_socpeople.remote_id AND c.type = " . MAIN_DB_PREFIX . "ecommerce_socpeople.type
WHERE c.rowid IS NOT NULL" ];
		$sql[] = [ 'sql' => "ALTER TABLE " . MAIN_DB_PREFIX . "ecommerce_socpeople  DROP INDEX uk_ecommerce_socpeople_fk_site_fk_socpeople", 'ignoreerror' => 1 ];
		$sql[] = [ 'sql' => "ALTER TABLE " . MAIN_DB_PREFIX . "ecommerce_socpeople  ADD UNIQUE KEY uk_ecommerce_socpeople_fk_site_fk_socpeople ( fk_site, fk_socpeople, type );", 'ignoreerror' => 1 ];
		$sql[] = [ 'sql' => "ALTER TABLE " . MAIN_DB_PREFIX . "ecommerce_socpeople  ADD UNIQUE KEY uk_ecommerce_socpeople_fk_site_remote_id ( fk_site, remote_id, type );", 'ignoreerror' => 1 ];
		// Orders
		$sql[] = [ 'sql' => "DELETE " . MAIN_DB_PREFIX . "ecommerce_commande FROM " . MAIN_DB_PREFIX . "ecommerce_commande
LEFT JOIN (
	SELECT MIN(t.rowid) AS rowid, t.fk_commande, t.fk_site, t.remote_id
	FROM " . MAIN_DB_PREFIX . "ecommerce_commande as t
	GROUP BY t.fk_commande, t.fk_site, t.remote_id
	HAVING COUNT(*) > 1
) AS c ON c.rowid != " . MAIN_DB_PREFIX . "ecommerce_commande.rowid AND c.fk_commande = " . MAIN_DB_PREFIX . "ecommerce_commande.fk_commande AND c.fk_site = " . MAIN_DB_PREFIX . "ecommerce_commande.fk_site AND c.remote_id = " . MAIN_DB_PREFIX . "ecommerce_commande.remote_id
WHERE c.rowid IS NOT NULL" ];
		$sql[] = [ 'sql' => "ALTER TABLE " . MAIN_DB_PREFIX . "ecommerce_commande  ADD UNIQUE KEY uk_ecommerce_commande_fk_site_fk_commande ( fk_site, fk_commande );", 'ignoreerror' => 1 ];
		$sql[] = [ 'sql' => "ALTER TABLE " . MAIN_DB_PREFIX . "ecommerce_commande  ADD UNIQUE KEY uk_ecommerce_commande_fk_site_remote_id ( fk_site, remote_id );", 'ignoreerror' => 1 ];

		// Clean corrupted data
		$sql[] = [ 'sql' => "DELETE FROM " . MAIN_DB_PREFIX . "ecommerce_societe WHERE fk_societe NOT IN (SELECT rowid FROM " . MAIN_DB_PREFIX . "societe);" ];
		$sql[] = [ 'sql' => "DELETE FROM " . MAIN_DB_PREFIX . "ecommerce_socpeople WHERE fk_socpeople NOT IN (SELECT rowid FROM " . MAIN_DB_PREFIX . "socpeople);" ];
		$sql[] = [ 'sql' => "DELETE FROM " . MAIN_DB_PREFIX . "ecommerce_product WHERE fk_product NOT IN (SELECT rowid FROM " . MAIN_DB_PREFIX . "product);" ];
		$sql[] = [ 'sql' => "DELETE FROM " . MAIN_DB_PREFIX . "ecommerce_commande WHERE fk_commande NOT IN (SELECT rowid FROM " . MAIN_DB_PREFIX . "commande);" ];
		$sql[] = [ 'sql' => "DELETE FROM " . MAIN_DB_PREFIX . "ecommerce_facture WHERE fk_facture NOT IN (SELECT rowid FROM " . MAIN_DB_PREFIX . "facture);" ];

		// Delete semaphore token for cron jobs
		require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
		dolibarr_del_const($this->db, 'ECOMMERCE_PROCESSING_WEBHOOK_SYNCHRONIZATION', 0);
		dolibarr_del_const($this->db, 'ECOMMERCE_CHECK_WEBHOOKS_STATUS', 0);

		$result=$this->load_tables($options);
		$this->addSettlementTerms();
		$this->addAnonymousCompany();
        $this->addFiles();
		return $this->_init($sql, $options);
	}

	/**
	 *	Function called when module is disabled.
	 *  Remove from database constants, boxes and permissions from Dolibarr database.
	 *	Data directories are not deleted.
	 *
	 *  @param     string  $options    Options
	 *  @return    int                 1 if OK, 0 if KO
	 */
	function remove($options = '')
	{
		$sql = array();

		return $this->_remove($sql, $options);
	}


	/**
	 *		\brief		Create tables, keys and data required by module
	 * 					Files llx_table1.sql, llx_table1.key.sql llx_data.sql with create table, create keys
	 * 					and create data commands must be stored in directory /mymodule/sql/
	 *					This function is called by this->init.
	 * 		\return		int		<=0 if KO, >0 if OK
	 */
	function load_tables()
	{
		return $this->_load_tables('/ecommerceng/sql/');
	}

	/**
	 * Add anonymous company for anonymous orders
	 */
	private function addAnonymousCompany()
	{
	    global $user;

		$idCompany = dolibarr_get_const($this->db, 'ECOMMERCE_COMPANY_ANONYMOUS');

		// Check for const existing but company deleted from dB
		if ($idCompany)
		{
			$dBSociete = new Societe($this->db);
			$idCompany = $dBSociete->fetch($idCompany) < 0 ? null:$idCompany ;
		}

		if ($idCompany == null)
		{
			$dBSociete = new Societe($this->db);
			$dBSociete->nom = 'Anonymous';
			$dBSociete->client = 3;//for client/prospect
			$dBSociete->create($user);

			if (dolibarr_set_const($this->db, 'ECOMMERCE_COMPANY_ANONYMOUS', $dBSociete->id) < 0)
			{
				dolibarr_print_error($this->db);
			}
		}
	}

	/**
	 * Add settlement terms if not exists
	 */
	private function AddSettlementTerms()
	{
		$table = MAIN_DB_PREFIX."c_payment_term";
		$eCommerceDict = new eCommerceDict($this->db, $table);
		$cashExists = $eCommerceDict->fetchByCode('CASH');
		if ($cashExists == array())
		{
			// Get free rowid to insert
			$newid = 0;
			$sql = "SELECT max(rowid) newid from ".$table;
			$maxId = $this->db->query($sql);
			if ($maxId)
			{
				$obj = $this->db->fetch_object($maxId);
				$newid = ($obj->newid + 1);
			}
			else
			{
				dol_print_error($this->db);
			}

			// Get free sortorder to insert
			$newSort = 0;
			$sql = "SELECT max(sortorder) newsortorder from ".$table;
			$maxSort = $this->db->query($sql);
			if ($maxSort)
			{
				$obj = $this->db->fetch_object($maxSort);
				$newSort = ($obj->newsortorder + 1);
			}
			else
			{
				dol_print_error($this->db);
			}

			if ($newid != 0 && $newSort != 0)
			{
			    if ((float) DOL_VERSION < 5.0)
			    {
    				$sql = "INSERT INTO ".$table."
    							(rowid, code, sortorder, active, libelle, libelle_facture, fdm, nbjour, decalage)
    						VALUES
    							(".$newid.", 'CASH', ".$newSort.", 1, 'Au comptant', 'A la commande', 0, 0, NULL)";
    				$insert = $this->db->query($sql);
			    }
			}
		}
	}

    /**
   	 * Add files need for dolibarr
   	 */
   	private function addFiles()
   	{
        $srcFile = dol_buildpath('/ecommerceng/patchs/dolibarr/includes/OAuth/OAuth2/Service/WordPress.php');
        $destFile = DOL_DOCUMENT_ROOT . '/includes/OAuth/OAuth2/Service/WordPress.php';

        if (!file_exists($destFile) && dol_copy($srcFile, $destFile) < 0) {
			setEventMessages("Error copy file '$srcFile' to '$destFile'", null, 'errors');
		}
   	}
}

