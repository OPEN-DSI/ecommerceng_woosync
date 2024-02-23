<?php
/* Copyright (C) 2017      Open-DSI             <support@open-dsi.fr>
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

dol_include_once('/ecommerceng/class/business/eCommerceSynchro.class.php');
dol_include_once("/ecommerceng/lib/eCommerce.lib.php");

/**
 *  \file       htdocs/ecommerceng/class/actions_ecommerceng.class.php
 *  \ingroup    ecommerceng
 *  \brief      File for hooks
 */

class ActionsECommerceNg
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
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

    /**
     * Overloading the doActions function : replacing the parent's function with the one below
     *
     * @param   array() $parameters Hook metadatas (context, etc...)
     * @param   CommonObject &$object The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param   string &$action Current action (if set). Generally create or edit or null
     * @param   HookManager $hookmanager Hook manager propagated to allow calling another hook
     * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
     */
    function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$contexts = explode(':', $parameters['context']);

		if (in_array('productdocuments', $contexts) && $action == 'synchronize_images') {
			if ($this->isImageSync($object) && $this->isProductLinkedToECommerce($object)) {
				$result = $object->call_trigger('PRODUCT_MODIFY', $user);
				if ($result < 0) {
					if (count($object->errors)) setEventMessages($object->error, $object->errors, 'errors');
					else setEventMessages($langs->trans($object->error), null, 'errors');
				} else {
					setEventMessage($langs->trans('ECommerceProductImagesSynchronized'));
				}
			}
		} elseif (in_array('productcard', $contexts)) {
			$confirm = GETPOST('confirm', 'alpha');

			if ($action == 'confirm_unlink_product_to_ecommerce' && $confirm == 'yes' && $this->isProductLinkedToECommerce($object)) {
				$site_id = GETPOST('siteid', 'int');
				$error = 0;
				$object->db->begin();

				// Delete link to ecommerce
				$eCommerceProduct = new eCommerceProduct($object->db);
				if ($eCommerceProduct->fetchByProductId($object->id, $site_id) > 0) {
					if ($eCommerceProduct->delete($user) < 0) {
						setEventMessages($eCommerceProduct->error, $eCommerceProduct->errors, 'errors');
						$error++;
					}
				}

				// Delete all categories of the ecommerce
				if (!$error && empty($conf->global->ECOMMERCE_DONT_UNSET_CATEGORIE_OF_PRODUCT_WHEN_DELINK)) {
					$eCommerceSite = new eCommerceSite($object->db);
					if ($eCommerceSite->fetch($site_id) > 0) {
						require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
						$cat = new Categorie($object->db);
						$cat_root = $eCommerceSite->fk_cat_product;
						$all_cat_full_arbo = $cat->get_full_arbo('product');
						$cats_full_arbo = array();
						foreach ($all_cat_full_arbo as $category) {
							$cats_full_arbo[$category['id']] = $category['fullpath'];
						}
						$categories = $cat->containing($object->id, 'product', 'id');
						foreach ($categories as $cat_id) {
							if (isset($cats_full_arbo[$cat_id]) &&
								(preg_match("/^{$cat_root}$/", $cats_full_arbo[$cat_id]) || preg_match("/^{$cat_root}_/", $cats_full_arbo[$cat_id]) ||
									preg_match("/_{$cat_root}_/", $cats_full_arbo[$cat_id]) || preg_match("/_{$cat_root}$/", $cats_full_arbo[$cat_id])
								)
							) {
								if ($cat->fetch($cat_id) > 0) {
									if ($cat->del_type($object, 'product') < 0) {
										setEventMessages($cat->error, $cat->errors, 'errors');
										$error++;
									}
								}
							}
						}
					}
				}

				$action = '';

				if ($error) {
					$object->db->rollback();
					return -1;
				} else {
					$object->db->commit();
				}
			}
		} elseif (in_array('thirdpartycard', $contexts)) {
			$confirm = GETPOST('confirm', 'alpha');

			if ($action == 'confirm_update_company_from_ecommerce' && $confirm == 'yes' && $this->isCompanyLinkedToECommerce($object)) {
				$site_id = GETPOST('siteid', 'int');
				$langs->load('ecommerceng@ecommerceng');
				$langs->load('woocommerce@ecommerceng');

				$error = 0;
				$object->db->begin();

				dol_include_once('/ecommerceng/class/data/eCommerceSite.class.php');
				$siteDb = new eCommerceSite($object->db);
				$result = $siteDb->fetch($site_id);
				if ($result == 0) {
					setEventMessage($langs->trans('EcommerceSiteNotFound', $site_id), 'errors');
					$error++;
				} elseif ($result < 0) {
					setEventMessage($langs->trans('EcommerceSiteNotFound', $site_id) . ' : ' . $siteDb->error, 'errors');
					$error++;
				}

				if (!$error) {
					dol_include_once('/ecommerceng/class/business/eCommerceSynchro.class.php');
					$synchro = new eCommerceSynchro($object->db, $siteDb, 0, 0);
					$synchro->connect();
					if (count($synchro->errors)) {
						setEventMessages($synchro->error, $synchro->errors, 'errors');
						$error++;
					}
				}

				if (!$error) {
					$eCommerceSociete = new eCommerceSociete($object->db);
					$links_list = $eCommerceSociete->getAllLinksByFkSociete($object->id, $siteDb->id);
					if (!is_array($links_list)) {
						setEventMessages($eCommerceSociete->error, $eCommerceSociete->errors, 'errors');
						$error++;
					}

					if (!$error) {
						foreach ($links_list as $link) {
							$result = $synchro->synchronizeCustomers(null, null, array($link->remote_id), 1, false, false, true);
							if ($result <= 0) {
								setEventMessages($synchro->error, $synchro->errors, 'errors');
								$error++;
								break;
							}
						}
					}
				}

				$action = '';

				if ($error) {
					$object->db->rollback();
					return -1;
				} else {
					setEventMessage($langs->trans('EcommerceUpdateCompanyFromECommerceSuccess'));
					$object->db->commit();
				}
			}
		} elseif (in_array('invoicelist', $contexts)) {
			// remove filters
			if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
				$_GET['search_ec_total_delta'] = '';
				$_POST['search_ec_total_delta'] = '';
			}
		}

		return 0; // or return 1 to replace standard code
	}

    /**
     * Overloading the addMoreActionsButtons function : replacing the parent's function with the one below
     *
     * @param   array() $parameters Hook metadatas (context, etc...)
     * @param   CommonObject &$object The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param   string &$action Current action (if set). Generally create or edit or null
     * @param   HookManager $hookmanager Hook manager propagated to allow calling another hook
     * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
     */
    function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $user, $langs;

		$contexts = explode(':', $parameters['context']);

        if (in_array('productcard', $contexts)) {
            if ($user->rights->ecommerceng->write) {
                // Site list
                $sites = $this->getProductLinkedSite($object);
                if (count($sites) > 0) {
                    if ($action == 'unlink_product_to_ecommerce') {
                        $sites_array = array();
                        foreach ($sites as $site) {
                            $sites_array[$site->id] = $site->name;
                        }

                        require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
                        $form = new Form($object->db);

                        // Define confirmation messages
                        $formquestionclone = array(
                            array('type' => 'select', 'name' => 'siteid', 'label' => $langs->trans("ECommerceSite"), 'values' => $sites_array),
                        );
                        print $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans('EcommerceNGUnlinkToECommerce'), $langs->trans('EcommerceNGConfirmUnlinkToECommerce', $object->ref), 'confirm_unlink_product_to_ecommerce', $formquestionclone, 'yes', 1, 250, 600);
                    }

                    $site_ids = array_keys($sites);
                    $params = count($sites) > 1 ? '&action=unlink_product_to_ecommerce' : '&action=confirm_unlink_product_to_ecommerce&confirm=yes&siteid=' . $site_ids[0];
                    print '<div class="inline-block divButAction"><a class="butAction" href="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . $params . '&token='.ecommercengNewToken().'">' . $langs->trans("EcommerceNGUnlinkToECommerce") . '</a></div>';
                }
            }
        } elseif (in_array('thirdpartycard', $contexts)) {
            if ($user->rights->ecommerceng->write) {
                // Site list
                $sites = $this->getCompanyLinkedSite($object);
                if (count($sites) > 0) {
                    if ($action == 'update_company_from_ecommerce') {
                        $sites_array = array();
                        foreach ($sites as $site) {
                            $sites_array[$site->id] = $site->name;
                        }

                        require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
                        $form = new Form($object->db);

                        // Define confirmation messages
                        $formquestionclone = array(
                            array('type' => 'select', 'name' => 'siteid', 'label' => $langs->trans("ECommerceSite"), 'values' => $sites_array),
                        );
                        print $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans('EcommerceUpdateCompanyFromECommerce'), $langs->trans('EcommerceConfirmUpdateCompanyFromECommerce', $object->ref), 'confirm_update_company_from_ecommerce', $formquestionclone, 'yes', 1, 250, 600);
                    }

                    $site_ids = array_keys($sites);
                    $params = count($sites) > 1 ? '&action=update_company_from_ecommerce' : '&action=confirm_update_company_from_ecommerce&confirm=yes&siteid=' . $site_ids[0];
                    print '<div class="inline-block divButAction"><a class="butAction" href="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . $params . '&token='.ecommercengNewToken().'">' . $langs->trans("EcommerceUpdateCompanyFromECommerce") . '</a></div>';
                }
            }
        }

        return 0; // or return 1 to replace standard code
    }

    /**
     * Overloading the formObjectOptions function : replacing the parent's function with the one below
     *
     * @param   array() $parameters Hook metadatas (context, etc...)
     * @param   CommonObject &$object The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param   string &$action Current action (if set). Generally create or edit or null
     * @param   HookManager $hookmanager Hook manager propagated to allow calling another hook
     * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
     */
    function formObjectOptions($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $langs;

		$contexts = explode(':', $parameters['context']);

        if (in_array('productdocuments', $contexts)) {
            if ($this->isImageSync($object) && $this->isProductLinkedToECommerce($object)) {
                $buttons = '<div class="tabsAction">';
                $buttons .= '<div class="inline-block divButAction"><a class="butAction" href="' . $_SERVER["PHP_SELF"] . '?action=synchronize_images&amp;id=' . $object->id . '&token='.ecommercengNewToken().'">' . $langs->trans("ECommerceSynchronizeProductImages") . '</a></div>';
                $buttons .= '</div>';

                print '<script type="text/javascript" language="javascript">';
                print '$(document).ready(function () {';
                print '$(\'div.fichecenter\').append("' . str_replace('"', '\\"', $buttons) . '");';
                print '});';
                print '</script>';
            }
        }

        return 0; // or return 1 to replace standard code
    }

    /**
     * Overloading the afterODTCreation function : replacing the parent's function with the one below
     *
     * @param   array() $parameters Hook metadatas (context, etc...)
     * @param   CommonObject &$object The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param   string &$action Current action (if set). Generally create or edit or null
     * @param   HookManager $hookmanager Hook manager propagated to allow calling another hook
     * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
     */
    function afterODTCreation($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $user, $langs, $db;

		$contexts = explode(':', $parameters['context']);

        if ((in_array('expeditioncard', $contexts) && ($action == 'confirm_valid' || $action == 'builddoc')) ||
            (in_array('invoicecard', $contexts) && ($action == 'confirm_valid' || $action == 'builddoc'))
        ) {
            if (!empty($conf->global->ECOMMERCENG_ENABLE_SEND_FILE_TO_ORDER)) {
                $commande_id = 0;
                $object_src = $parameters['object'];
                $object_src->fetchObjectLinked('', 'commande', $object_src->id, $object_src->element);
                if (!empty($object_src->linkedObjects)) {
                    foreach ($object_src->linkedObjects['commande'] as $element) {
                        $commande_id = $element->id;
                    }
                }

                if ($commande_id > 0) {
                    $db->begin();

                    $error = 0; // Error counter
                    $eCommerceSite = new eCommerceSite($db);
                    $sites = $eCommerceSite->listSites('object');

                    foreach ($sites as $site) {
                        if (!$error) {
                            $eCommerceCommande = new eCommerceCommande($db);
                            $eCommerceCommande->fetchByCommandeId($commande_id, $site->id); // TODO $eCommerceCommande->remote_societe_id a rajouter a la table

                            if ($eCommerceCommande->remote_id > 0) {
                                $eCommerceSynchro = new eCommerceSynchro($db, $site);
                                dol_syslog("Hook ActionsECommerceNg::afterPDFCreation try to connect to eCommerce site " . $site->name);
                                $eCommerceSynchro->connect();
                                if (count($eCommerceSynchro->errors)) {
                                    $error++;
                                    setEventMessages($eCommerceSynchro->error, $eCommerceSynchro->errors, 'errors');
                                }

                                if (!$error) {
                                    $result = $eCommerceSynchro->eCommerceRemoteAccess->sendFileForCommande($eCommerceCommande->remote_id, $object_src, $parameters['file'], $parameters['outputlangs']);
                                    if (!$result) {
                                        $error++;
                                        $this->errors[] = $eCommerceSynchro->eCommerceRemoteAccess->error;
                                    }
                                }
                            } else {
                                dol_syslog("Order with id " . $commande_id . " is not linked to an ecommerce record so we don't send file to it.");
                            }
                        }
                    }

                    if ($error) {
                        $db->rollback();
                        return -1;
                    } else {
                        $db->commit();
                        return 0;
                    }
                }
            }
        }

        return 0; // or return 1 to replace standard code
    }

    /**
     * Overloading the afterPDFCreation function : replacing the parent's function with the one below
     *
     * @param   array() $parameters Hook metadatas (context, etc...)
     * @param   CommonObject &$object The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param   string &$action Current action (if set). Generally create or edit or null
     * @param   HookManager $hookmanager Hook manager propagated to allow calling another hook
     * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
     */
    function afterPDFCreation($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $user, $langs, $db;

		$contexts = explode(':', $parameters['context']);

        if ((in_array('expeditioncard', $contexts) && ($action == 'confirm_valid' || $action == 'builddoc')) ||
            (in_array('invoicecard', $contexts) && ($action == 'confirm_valid' || $action == 'builddoc'))
        ) {
            if (!empty($conf->global->ECOMMERCENG_ENABLE_SEND_FILE_TO_ORDER)) {
                $commande_id = 0;
                $object_src = $parameters['object'];
                $object_src->fetchObjectLinked('', 'commande', $object_src->id, $object_src->element);
                if (!empty($object_src->linkedObjects)) {
                    foreach ($object_src->linkedObjects['commande'] as $element) {
                        $commande_id = $element->id;
                    }
                }

                if ($commande_id > 0) {
                    $db->begin();

                    $error = 0; // Error counter
                    $eCommerceSite = new eCommerceSite($db);
                    $sites = $eCommerceSite->listSites('object');

                    foreach ($sites as $site) {
                        if (!$error) {
                            $eCommerceCommande = new eCommerceCommande($db);
                            $eCommerceCommande->fetchByCommandeId($commande_id, $site->id); // TODO $eCommerceCommande->remote_societe_id a rajouter a la table

                            if ($eCommerceCommande->remote_id > 0) {
                                $eCommerceSynchro = new eCommerceSynchro($db, $site);
                                dol_syslog("Hook ActionsECommerceNg::afterPDFCreation try to connect to eCommerce site " . $site->name);
                                $eCommerceSynchro->connect();
                                if (count($eCommerceSynchro->errors)) {
                                    $error++;
                                    setEventMessages($eCommerceSynchro->error, $eCommerceSynchro->errors, 'errors');
                                }

                                if (!$error) {
                                    $result = $eCommerceSynchro->eCommerceRemoteAccess->sendFileForCommande($eCommerceCommande->remote_id, $object_src, $parameters['file'], $parameters['outputlangs']);
                                    if (!$result) {
                                        $error++;
                                        $this->errors[] = $eCommerceSynchro->eCommerceRemoteAccess->error;
                                    }
                                }
                            } else {
                                dol_syslog("Order with id " . $commande_id . " is not linked to an ecommerce record so we don't send file to it.");
                            }
                        }
                    }

                    if ($error) {
                        $db->rollback();
                        return -1;
                    } else {
                        $db->commit();
                        return 0;
                    }
                }
            }
        }

        return 0; // or return 1 to replace standard code
    }

    /**
     * Test if product is linked with a ECommerce
     *
     * @param   Product     &$object    Product object
     * @return  bool
     */
    private function isProductLinkedToECommerce(&$object)
    {
        dol_include_once('/ecommerceng/class/business/eCommerceSynchro.class.php');
        dol_include_once('/ecommerceng/class/data/eCommerceProduct.class.php');
        $isLinkedToECommerce = false;

        // Get current categories and subcategories
        require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
        $c = new Categorie($object->db);
        $categories = array();
        if (isset($_POST['categories'])) {
            $cats = GETPOST('categories');
        } else {
            $cats = $c->containing($object->id, Categorie::TYPE_PRODUCT, 'id');
        }
        foreach ($cats as $cat) {
            $c->id = $cat;
            $catslist = $c->get_all_ways();
            if (isset($catslist[0])) {
                foreach ($catslist[0] as $catinfos) {
                    $categories[$catinfos->id] = $catinfos->id;
                }
            }
        }

        $eCommerceSite = new eCommerceSite($object->db);
        $sites = $eCommerceSite->listSites('object');
        foreach ($sites as $site) {
            if (in_array($site->fk_cat_product, $categories)) {
                $eCommerceProduct = new eCommerceProduct($object->db);
                $eCommerceProduct->fetchByProductId($object->id, $site->id);

                if ($eCommerceProduct->remote_id > 0) {
                    $isLinkedToECommerce = true;
                    break;
                }
            }
        }

        return $isLinkedToECommerce;
    }

    /**
     * Test if company is linked with a ECommerce
     *
     * @param   Societe     &$object    Company object
     * @return  bool
     */
    private function isCompanyLinkedToECommerce(&$object)
    {
        dol_include_once('/ecommerceng/class/business/eCommerceSynchro.class.php');
        dol_include_once('/ecommerceng/class/data/eCommerceSociete.class.php');
        $isLinkedToECommerce = false;

        // Get current categories and subcategories
        require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
        $c = new Categorie($object->db);
        $categories = array();
        if (isset($_POST['categories'])) {
            $cats = GETPOST('categories');
        } else {
            $cats = $c->containing($object->id, Categorie::TYPE_CUSTOMER, 'id');
        }
        foreach ($cats as $cat) {
            $c->id = $cat;
            $catslist = $c->get_all_ways();
            if (isset($catslist[0])) {
                foreach ($catslist[0] as $catinfos) {
                    $categories[$catinfos->id] = $catinfos->id;
                }
            }
        }

        $eCommerceSite = new eCommerceSite($object->db);
        $sites = $eCommerceSite->listSites('object');
        foreach ($sites as $site) {
            if (in_array($site->fk_cat_societe, $categories)) {
                $eCommerceSociete = new eCommerceSociete($object->db);
				$links_list = $eCommerceSociete->getAllLinksByFkSociete($object->id, $site->id);
				if (is_array($links_list)) {
					foreach ($links_list as $link) {
						if (!empty($link->remote_id)) {
							$isLinkedToECommerce = true;
							break;
						}
					}
				}
            }
        }

        return $isLinkedToECommerce;
    }

    /**
     * Get ECommerce linked to the product
     *
     * @param   Product     &$object    Product object
     * @return  array
     */
    private function getProductLinkedSite(&$object)
    {
        dol_include_once('/ecommerceng/class/business/eCommerceSynchro.class.php');
        $linkedToECommerce = array();

        // Get current categories and subcategories
        require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
        $c = new Categorie($object->db);
        $categories = array();
        if (isset($_POST['categories'])) {
            $cats = GETPOST('categories');
        } else {
            $cats = $c->containing($object->id, Categorie::TYPE_PRODUCT, 'id');
        }
        foreach ($cats as $cat) {
            $c->id = $cat;
            $catslist = $c->get_all_ways();
            if (isset($catslist[0])) {
                foreach ($catslist[0] as $catinfos) {
                    $categories[$catinfos->id] = $catinfos->id;
                }
            }
        }

        $eCommerceSite = new eCommerceSite($object->db);
        $sites = $eCommerceSite->listSites('object');
        foreach ($sites as $site) {
            if (in_array($site->fk_cat_product, $categories)) {
                $eCommerceProduct = new eCommerceProduct($object->db);
                $eCommerceProduct->fetchByProductId($object->id, $site->id);

                if ($eCommerceProduct->remote_id > 0) {
                    $linkedToECommerce[$site->id] = $site;
                    break;
                }
            }
        }

        return $linkedToECommerce;
    }


    /**
     * Get ECommerce linked to the product
     *
     * @param   Product     &$object    Product object
     * @return  array
     */
    private function getCompanyLinkedSite(&$object)
    {
        dol_include_once('/ecommerceng/class/business/eCommerceSynchro.class.php');
        $linkedToECommerce = array();

        // Get current categories and subcategories
        require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
        $c = new Categorie($object->db);
        $categories = array();
        if (isset($_POST['categories'])) {
            $cats = GETPOST('categories');
        } else {
            $cats = $c->containing($object->id, Categorie::TYPE_CUSTOMER, 'id');
        }
        foreach ($cats as $cat) {
            $c->id = $cat;
            $catslist = $c->get_all_ways();
            if (isset($catslist[0])) {
                foreach ($catslist[0] as $catinfos) {
                    $categories[$catinfos->id] = $catinfos->id;
                }
            }
        }

        $eCommerceSite = new eCommerceSite($object->db);
        $sites = $eCommerceSite->listSites('object');
        foreach ($sites as $site) {
            if (in_array($site->fk_cat_societe, $categories)) {
                $eCommerceSociete = new eCommerceSociete($object->db);
				$links_list = $eCommerceSociete->getAllLinksByFkSociete($object->id, $site->id);
				if (is_array($links_list)) {
					foreach ($links_list as $link) {
						if (!empty($link->remote_id)) {
							$linkedToECommerce[$site->id] = $site;
							break;
						}
					}
				}
            }
        }

        return $linkedToECommerce;
    }

    /**
     * Test if product is linked with a ECommerce
     *
     * @param   Product     &$object    Product object
     * @return  bool
     */
    private function isImageSync(&$object)
    {
        dol_include_once('/ecommerceng/class/business/eCommerceSynchro.class.php');
        $isImageSync = false;

        $eCommerceSite = new eCommerceSite($object->db);
        $sites = $eCommerceSite->listSites('object');
        foreach ($sites as $site) {
            $productImageSynchDirection = isset($site->parameters['product_synch_direction']['image']) ? $site->parameters['product_synch_direction']['image'] : '';
            if ($productImageSynchDirection == 'dtoe' || $productImageSynchDirection == 'all') {
                $isImageSync = true;
                break;
            }
        }

        return $isImageSync;
    }

	/**
	 * Overloading the printFieldListSearchParam function : replacing the parent's function with the one below
	 *
	 * @param	array()			$parameters		Hook metadatas (context, etc...)
	 * @param	CommonObject	$object			The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string			$action			Current action (if set). Generally create or edit or null
	 * @param	HookManager		$hookmanager	Hook manager propagated to allow calling another hook
	 * @return	int								< 0 on error, 0 on success, 1 to replace standard code
	 */
	public function printFieldListSearchParam($parameters, &$object, &$action, $hookmanager)
	{
		$contexts = explode(':', $parameters['context']);

		if (in_array('invoicelist', $contexts)) {
			global $arrayfields, $langs;
			$langs->load('ecommerceng@ecommerceng');

			$arrayfields['ec_total_delta'] = array('label' => $langs->trans('ECommerceTotalDelta'), 'checked' => 0, 'position' => 400);

			$search_ec_total_delta = GETPOST('search_ec_total_delta', 'alphanohtml');

			$resPrints = '';
			if ($search_ec_total_delta != '') $resPrints .= '&search_ec_total_delta=' . urlencode($search_ec_total_delta);
			$this->resprints = $resPrints;
		}

		return 0;
	}

	/**
	 * Overloading the printFieldListSelect function : replacing the parent's function with the one below
	 *
	 * @param	array()			$parameters		Hook metadatas (context, etc...)
	 * @param	CommonObject	$object			The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string			$action			Current action (if set). Generally create or edit or null
	 * @param	HookManager		$hookmanager	Hook manager propagated to allow calling another hook
	 * @return	int								< 0 on error, 0 on success, 1 to replace standard code
	 */
	public function printFieldListSelect($parameters, &$object, &$action, $hookmanager)
	{
		$contexts = explode(':', $parameters['context']);

		if (in_array('invoicelist', $contexts)) {
			$resPrints = ', ABS(f.total_ttc - (f.total_ht + f.total_tva)) AS ec_total_delta';
			$this->resprints = $resPrints;
		}

		return 0;
	}

	/**
	 * Overloading the printFieldListWhere function : replacing the parent's function with the one below
	 *
	 * @param	array()			$parameters		Hook metadatas (context, etc...)
	 * @param	CommonObject	$object			The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string			$action			Current action (if set). Generally create or edit or null
	 * @param	HookManager		$hookmanager	Hook manager propagated to allow calling another hook
	 * @return	int								< 0 on error, 0 on success, 1 to replace standard code
	 */
	public function printFieldListWhere($parameters, &$object, &$action, $hookmanager)
	{
		$contexts = explode(':', $parameters['context']);

		if (in_array('invoicelist', $contexts)) {
			$search_ec_total_delta = GETPOST('search_ec_total_delta', 'alphanohtml');

			$out = '';
			if ($search_ec_total_delta) $out .= natural_search('ABS(f.total_ttc - (f.total_ht + f.total_tva))', $search_ec_total_delta, 1);

			$this->resprints = $out;
		}

		return 0;
	}

	/**
	 * Overloading the printFieldListOption function : replacing the parent's function with the one below
	 *
	 * @param	array()			$parameters		Hook metadatas (context, etc...)
	 * @param	CommonObject	$object			The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string			$action			Current action (if set). Generally create or edit or null
	 * @param	HookManager		$hookmanager	Hook manager propagated to allow calling another hook
	 * @return	int								< 0 on error, 0 on success, 1 to replace standard code
	 */
	public function printFieldListOption($parameters, &$object, &$action, $hookmanager)
	{
		$contexts = explode(':', $parameters['context']);

		if (in_array('invoicelist', $contexts)) {
			$arrayfields = $parameters['arrayfields'];

			if (!empty($arrayfields['ec_total_delta']['checked'])) {
				$search_ec_total_delta = GETPOST('search_ec_total_delta', 'alphanohtml');
				print '<td class="liste_titre right">';
				print '<input class="flat" type="text" name="search_ec_total_delta" size="8" value="' . dol_escape_htmltag($search_ec_total_delta) . '">';
				print '</td>';
			}
		}

		return 0;
	}

	/**
	 * Overloading the printFieldListTitle function : replacing the parent's function with the one below
	 *
	 * @param	array()			$parameters		Hook metadatas (context, etc...)
	 * @param	CommonObject	$object			The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string			$action			Current action (if set). Generally create or edit or null
	 * @param	HookManager		$hookmanager	Hook manager propagated to allow calling another hook
	 * @return	int								< 0 on error, 0 on success, 1 to replace standard code
	 */
	public function printFieldListTitle($parameters, &$object, &$action, $hookmanager)
	{
		global $langs;
		$contexts = explode(':', $parameters['context']);

		if (in_array('invoicelist', $contexts)) {
			$param = $parameters['param'];
			$sortfield = $parameters['sortfield'];
			$sortorder = $parameters['sortorder'];
			$arrayfields = $parameters['arrayfields'];

			if (!empty($arrayfields['ec_total_delta']['checked'])) {
				print_liste_field_titre($arrayfields['ec_total_delta']['label'], $_SERVER['PHP_SELF'], '', '', $param, '', $sortfield, $sortorder, 'right ');
			}
		}

		return 0;
	}

	/**
	 * Overloading the printFieldListValue function : replacing the parent's function with the one below
	 *
	 * @param	array()			$parameters		Hook metadatas (context, etc...)
	 * @param	CommonObject	$object			The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string			$action			Current action (if set). Generally create or edit or null
	 * @param	HookManager		$hookmanager	Hook manager propagated to allow calling another hook
	 * @return	int								< 0 on error, 0 on success, 1 to replace standard code
	 */
	public function printFieldListValue($parameters, &$object, &$action, $hookmanager)
	{
		$contexts = explode(':', $parameters['context']);

		if (in_array('invoicelist', $contexts)) {
			$obj = $parameters['obj'];
			$arrayfields = $parameters['arrayfields'];

			if (!empty($arrayfields['ec_total_delta']['checked'])) {
				print '<td class="right">';
				print price(price2num($obj->ec_total_delta));
				print '</td>';
			}
		}

		return 0;
	}

	/**
	 * Overloading the stockMovementCreate function : replacing the parent's function with the one below
	 *
	 * @param	array()			$parameters		Hook metadatas (context, etc...)
	 * @param	CommonObject	$object			The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string			$action			Current action (if set). Generally create or edit or null
	 * @param	HookManager		$hookmanager	Hook manager propagated to allow calling another hook
	 * @return	int								< 0 on error, 0 on success, 1 to replace standard code
	 */
	public function stockMovementCreate($parameters, &$object, &$action, $hookmanager)
	{
		$contexts = explode(':', $parameters['context']);

		if (in_array('mouvementstock', $contexts)) {
			$fk_product = $parameters['fk_product'];

			if ($fk_product > 0) {
				require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
				$product = new Product($this->db);

				$result = $product->fetch($fk_product);
				if ($result < 0) {
					$this->errors[] = $product->errorsToString();
					return -1;
				} elseif ($result == 0) {
					return 1;
				}

				if (isset($product->array_options['options_ecommerceng_stockable_product']) &&
					$product->array_options['options_ecommerceng_stockable_product'] == 0
				) {
					return 1;
				}
			}
		}

		return 0;
	}
}