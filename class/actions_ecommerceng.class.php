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

/**
 *  \file       htdocs/ecommerceng/class/actions_ecommerceng.class.php
 *  \ingroup    ecommerceng
 *  \brief      File for hooks
 */

class ActionsECommerceNg
{
    /**
     * Overloading the doActions function : replacing the parent's function with the one below
     *
     * @param   array()         $parameters     Hook metadatas (context, etc...)
     * @param   CommonObject    &$object        The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param   string          &$action        Current action (if set). Generally create or edit or null
     * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
     * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
     */
    function doActions($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $user, $langs;

        if (in_array('productdocuments', explode(':', $parameters['context'])) && $action == 'synchronize_images') {
            if (!empty($conf->global->ECOMMERCENG_ENABLE_SYNCHRO_IMAGES)) {
                $result = $object->call_trigger('PRODUCT_MODIFY', $user);
                if ($result < 0) {
                    if (count($object->errors)) setEventMessages($object->error, $object->errors, 'errors');
                    else setEventMessages($langs->trans($object->error), null, 'errors');
                } else {
                    setEventMessage($langs->trans('ECommerceProductImagesSynchronized'));
                }
            }
        }

        return 0; // or return 1 to replace standard code
    }

    /**
     * Overloading the formObjectOptions function : replacing the parent's function with the one below
     *
     * @param   array()         $parameters     Hook metadatas (context, etc...)
     * @param   CommonObject    &$object        The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param   string          &$action        Current action (if set). Generally create or edit or null
     * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
     * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
     */
    function formObjectOptions($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $langs;

        if (in_array('productdocuments', explode(':', $parameters['context']))) {
            if (!empty($conf->global->ECOMMERCENG_ENABLE_SYNCHRO_IMAGES)) {
                $buttons = '<div class="tabsAction">';
                $buttons .= '<div class="inline-block divButAction"><a class="butAction" href="' . $_SERVER["PHP_SELF"] . '?action=synchronize_images&amp;id=' . $object->id . '">' . $langs->trans("ECommerceSynchronizeProductImages") . '</a></div>';
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
     * @param   array()         $parameters     Hook metadatas (context, etc...)
     * @param   CommonObject    &$object        The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param   string          &$action        Current action (if set). Generally create or edit or null
     * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
     * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
     */
    function afterODTCreation($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $user, $langs, $db;

        if ((in_array('expeditioncard', explode(':', $parameters['context'])) && ($action == 'confirm_valid' || $action == 'builddoc')) ||
            (in_array('invoicecard', explode(':', $parameters['context'])) && ($action == 'confirm_valid' || $action == 'builddoc'))) {
            if (!empty($conf->global->ECOMMERCENG_ENABLE_SEND_FILE_TO_ORDER)) {
                $commande_id = 0;
                $societe_id = 0;
                $object_src = $parameters['object'];
                $object_src->fetchObjectLinked('', 'commande', $object_src->id, $object_src->element);
                if (!empty($object_src->linkedObjects)) {
                    foreach ($object_src->linkedObjects['commande'] as $element) {
                        $commande_id = $element->id;
                        $societe_id = $element->socid;
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
                            $eCommerceCommande->fetchByCommandeId($commande_id, $site->id);

                            $eCommerceSociete = new eCommerceSociete($db);
                            $eCommerceSociete->fetchByFkSociete($societe_id, $site->id);

                            if ($eCommerceCommande->remote_id > 0 && $eCommerceSociete->remote_id > 0) {
                                $eCommerceSynchro = new eCommerceSynchro($db, $site);
                                dol_syslog("Hook ActionsECommerceNg::afterPDFCreation try to connect to eCommerce site " . $site->name);
                                $eCommerceSynchro->connect();
                                if (count($eCommerceSynchro->errors)) {
                                    $error++;
                                    setEventMessages($eCommerceSynchro->error, $eCommerceSynchro->errors, 'errors');
                                }

                                if (!$error) {
                                    $result = $eCommerceSynchro->eCommerceRemoteAccess->sendFileForCommande($eCommerceCommande->remote_id, $eCommerceSociete->remote_id, $object_src, $parameters['file'], $parameters['outputlangs']);
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
     * @param   array()         $parameters     Hook metadatas (context, etc...)
     * @param   CommonObject    &$object        The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param   string          &$action        Current action (if set). Generally create or edit or null
     * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
     * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
     */
    function afterPDFCreation($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $user, $langs, $db;

        if ((in_array('expeditioncard', explode(':', $parameters['context'])) && ($action == 'confirm_valid' || $action == 'builddoc')) ||
            (in_array('invoicecard', explode(':', $parameters['context'])) && ($action == 'confirm_valid' || $action == 'builddoc'))) {
            if (!empty($conf->global->ECOMMERCENG_ENABLE_SEND_FILE_TO_ORDER)) {
                $commande_id = 0;
                $societe_id = 0;
                $object_src = $parameters['object'];
                $object_src->fetchObjectLinked('', 'commande', $object_src->id, $object_src->element);
                if (!empty($object_src->linkedObjects)) {
                    foreach ($object_src->linkedObjects['commande'] as $element) {
                        $commande_id = $element->id;
                        $societe_id = $element->socid;
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
                            $eCommerceCommande->fetchByCommandeId($commande_id, $site->id);

                            $eCommerceSociete = new eCommerceSociete($db);
                            $eCommerceSociete->fetchByFkSociete($societe_id, $site->id);

                            if ($eCommerceCommande->remote_id > 0 && $eCommerceSociete->remote_id > 0) {
                                $eCommerceSynchro = new eCommerceSynchro($db, $site);
                                dol_syslog("Hook ActionsECommerceNg::afterPDFCreation try to connect to eCommerce site " . $site->name);
                                $eCommerceSynchro->connect();
                                if (count($eCommerceSynchro->errors)) {
                                    $error++;
                                    setEventMessages($eCommerceSynchro->error, $eCommerceSynchro->errors, 'errors');
                                }

                                if (!$error) {
                                    $result = $eCommerceSynchro->eCommerceRemoteAccess->sendFileForCommande($eCommerceCommande->remote_id, $eCommerceSociete->remote_id, $object_src, $parameters['file'], $parameters['outputlangs']);
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
}