#!/usr/bin/env php
<?php
/* Copyright (C) 2019      Open-Dsi <support@open-dsi.fr>
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
 *  \file       scripts/fix_invoices_for_bundles_prices_with_orders_if_exist.php
 *  \ingroup    cron
 *  \brief      Execute pendings jobs
 */
if (! defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL','1'); // Disables token renewal
if (! defined('NOREQUIREMENU'))  define('NOREQUIREMENU','1');
if (! defined('NOREQUIREHTML'))  define('NOREQUIREHTML','1');
if (! defined('NOREQUIREAJAX'))  define('NOREQUIREAJAX','1');
if (! defined('NOLOGIN'))        define('NOLOGIN','1');
//if (! defined('NOREQUIRETRAN'))  define('NOREQUIRETRAN','1');


$sapi_type = php_sapi_name();
$script_file = basename(__FILE__);
$path=dirname(__FILE__).'/';

// Test if batch mode
if (substr($sapi_type, 0, 3) == 'cgi') {
	echo "Error: You are using PHP for CGI. To execute ".$script_file." from command line, you must use PHP for CLI mode.\n";
	exit(-1);
}

// Check parameters
if (! isset($argv[1]) || ! $argv[1]) {
    print 'Usage: ' . $script_file . ' user_login_in_dolibarr [also_invoice_with_free_lines(0|1) [begin_date(YYYY-MM-DD)]]' . "\n";
    exit(-1);
}
$userlogin=$argv[1];
$also_invoice_with_free_lines=!empty($argv[2]);
if (isset($argv[3])) {
	$begin_date = strtotime($argv[3]);
	if ($begin_date === false) {
		print 'Wrong date format' . "\n";
		print 'Usage: ' . $script_file . ' user_login_in_dolibarr [also_invoice_with_free_lines(0|1) begin_date(YYYY-MM-DD)]' . "\n";
		exit(-1);
	}
}

// Change this following line to use the correct relative path (../, ../../, etc)
$res=0;
if (! $res && file_exists("../../master.inc.php")) $res=@include '../../master.inc.php';			// to work if your module directory is into a subdir of root htdocs directory
if (! $res && file_exists("../../../master.inc.php")) $res=@include '../../../master.inc.php';		// to work if your module directory is into a subdir of root htdocs directory
if (! $res) die("Include of main fails");
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
dol_include_once('/ecommerceng/class/data/eCommerceSite.class.php');
dol_include_once('/ecommerceng/class/business/eCommerceSynchro.class.php');
dol_include_once('/ecommerceng/class/data/eCommerceProduct.class.php');
dol_include_once('/ecommerceng/lib/eCommerce.lib.php');

// Global variables
$version=DOL_VERSION;

/*
 * Main
 */

// current date
$now=dol_now();

@set_time_limit(0);
print "***** ".$script_file." (".$version.") pid=".dol_getmypid()." ***** userlogin=" . $userlogin . " ***** " . $now . " *****\n";
print "\n";

$user = new User($db);
$res = $user->fetch('', $userlogin);
if ($res == 0) {
    print "Error user (Login: $userlogin) not found\n";
    $db->close();
    exit(0);
} elseif ($res < 0) {
    print "Error fetch user (Login: $userlogin) : " . $user->errorsToString() . "\n";
    $db->close();
    exit(0);
}

$siteDb = new eCommerceSite($db);
$sites = $siteDb->listSites('object', true);

$max_sites = count($sites);
$num_sites = 0;

if ($max_sites > 0) {
	$langs->loadLangs(array('ecommerce@ecommerceng', 'woocommerce@ecommerceng'));
	$startTime = microtime(true);

	foreach ($sites as $site) {
		print "Processing the site '{$site->name}'.\n";
		$num_sites++;
		$max_jobs = 0;
		$num_jobs = 0;

		$result = $site->setEntityValues($site->entity);
		if ($result < 0) {
			print "Error set entity to {$site->entity} : " . $db->lasterror() . "\n";
			continue;
		}
		$user->getrights();

		if (!empty($conf->stock->enabled) && !empty($conf->global->STOCK_CALCULATE_ON_BILL)) {
			print "Error decrement stock on validate invoice but don't support rollback stock before delete all lines of the invoice and re-create all lines.\n";
			continue;
		}

		print "Connect to the site.\n";
		$eCommerceSynchro = new eCommerceSynchro($db, $site);
		$result = $eCommerceSynchro->connect();
		if ($result < 0) {
			print "Warning: Connect to site fails: {$eCommerceSynchro->errorsToString()}.\n";
			continue;
		}
		$eCommerceWooCommerce = $eCommerceSynchro->eCommerceRemoteAccess->getClass();

		print "Get all Invoice who have TTC != HT + TVA.\n";
		if ($also_invoice_with_free_lines) {
			print "Or all Invoice who have free lines.\n";
		}

		$sql = "SELECT DISTINCT f.rowid, f.ref_ext";
		$sql .= " FROM " . MAIN_DB_PREFIX . "facture AS f";
        if ($also_invoice_with_free_lines) {
			$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "facturedet AS fd ON fd.fk_facture = f.rowid";
		}
		$sql .= " WHERE f.entity IN (" . getEntity('facture') . ")";
		$sql .= " AND f.ref_ext LIKE 'eCommerce-" . $site->id . "-%'";
		if (isset($begin_date)) {
			$sql .= " AND f.datef >= '" . $db->idate($begin_date) . "'";
		}
		$sql .= " AND (f.total_ttc != (f.total_ht + f.total_tva)";
        if ($also_invoice_with_free_lines) {
			$sql .= " OR fd.fk_product IS NULL OR fd.fk_product = 0";
		}
		$sql .= ")";

		$resql = $db->query($sql);
		if (!$resql) {
			print "\nError: SQL : $sql; Error: " . $db->lasterror() . "\n";
			continue;
		}

		$infV14 = (version_compare(DOL_VERSION, "14.0.0") < 0);
		$max_jobs = $db->num_rows($resql);

		while ($obj = $db->fetch_object($resql)) {
			$num_jobs++;
			printStatus($num_sites, $max_sites, $num_jobs, $max_jobs);

			if (preg_match('/^' . preg_quote('eCommerce-' . $site->id . '-') . '(\d+)' . preg_quote('-refund-') . '(\d+)/', $obj->ref_ext, $matches)) {
				$remote_id = $matches[1];
				$refund_remote_id = $matches[2];
			} elseif (preg_match('/^' . preg_quote('eCommerce-' . $site->id . '-') . '(\d+)/', $obj->ref_ext, $matches)) {
				$remote_id = $matches[1];
				$refund_remote_id = 0;
			} else {
				print "Warning: Invoice ID {$obj->rowid} bypassed because don't have valid ref ext.\n";
				continue;
			}

			// Fetch invoice
			$invoice = new Facture($db);
			$result = $invoice->fetch($obj->rowid);
			if ($result < 0) {
				print "\nError: Fetch invoice ID {$obj->rowid} for remote order {$remote_id} : " . $invoice->errorsToString() . "\n";
				continue;
			} elseif ($result == 0) {
				print "\nError: Fetch invoice ID {$obj->rowid} for remote order {$remote_id} : Not found.\n";
				continue;
			}

			//-------------------------------------------------
			// Fix invoice, deposit invoice and refund invoice
			//-------------------------------------------------

			print "\nFix invoice : {$invoice->ref}.\n";
			$isDepositType = isset($site->parameters['create_invoice_type']) && $site->parameters['create_invoice_type'] == Facture::TYPE_DEPOSIT;
			$typeAmount = isset($site->parameters['order_actions']['create_invoice_deposit_type']) ? $site->parameters['order_actions']['create_invoice_deposit_type'] : '';
			$valueDeposit = isset($site->parameters['order_actions']['create_invoice_deposit_value']) ? $site->parameters['order_actions']['create_invoice_deposit_value'] : 0;
			if ($invoice->type == Facture::TYPE_DEPOSIT && !$isDepositType) {
				print "\nError: Invoice {$invoice->ref} is of deposit type but the configuration has changed\n";
				continue;
			}
			if (!empty($conf->accounting->enabled)) {
				if (method_exists($invoice, 'getVentilExportCompta')) {
					$accounted = $invoice->getVentilExportCompta();
					if ($accounted > 0) {
						$langs->load("accountancy");
						print "\nError:  " . $langs->trans("Accounted") . "\n";
						continue;
					}
				}
			}

			// Get remote data
			$order_remote_data = $eCommerceWooCommerce->client->sendToApi(eCommerceClientApi::METHOD_GET, 'orders/' . $remote_id);
			if (!isset($order_remote_data)) {
				print "\nError: Get order data {$remote_id} : " . $eCommerceWooCommerce->client->errorsToString() . "\n";
				continue;
			}
			$order_data = $eCommerceWooCommerce->convertOrderDataIntoProcessedData($order_remote_data);
			if ($order_data === false) {
				print "\nError: Convert order remote data {$remote_id} : " . $eCommerceWooCommerce->errorsToString() . "\n";
				continue;
			}

			// Check refund data
			if ($invoice->type == Facture::TYPE_CREDIT_NOTE) {
				if (empty($refund_remote_id) && is_array($order_data['refunds'])) {
					$nb_refunds = count($order_data['refunds']);
					if ($nb_refunds == 1) {
						$refund_remote_id = array_values($order_data['refunds'])[0]['remote_id'];
					} elseif ($nb_refunds > 1) {
						$total_ttc = abs($invoice->total_ttc);

						$error = 0;
						foreach ($order_data['refunds'] as $refund) {
							if ($total_ttc == $refund['total_ttc']) {
								if (empty($refund_remote_id)) {
									$refund_remote_id = $refund['remote_id'];
								} else {
									$error++;
									break;
								}
							}
						}
						if ($error || empty($refund_remote_id)) {
							print "\nError: Invoice {$invoice->ref} is of credit note type with empty remote refund ID, but found multiple refunds with a amount equal to {$total_ttc}\n";
							continue;
						}
					}

					$invoice->ref_ext = 'eCommerce-' . $site->id . '-' . $remote_id . '-refund-' . $refund_remote_id;
					$result = $invoice->update($user);
					if ($result < 0) {
						print "\nError: Update invoice {$invoice->ref} for the ref ext {$invoice->ref_ext} : " . $invoice->errorsToString() . "\n";
						continue;
					}
				}
				if (empty($refund_remote_id)) {
					print "\nError: Invoice {$invoice->ref} is of credit note type but the refund remote ID is not found ({$obj->ref_ext})\n";
					continue;
				} elseif (empty($order_data['refunds'][$refund_remote_id])) {
					print "\nError: Invoice {$invoice->ref} is of credit note type but the refund data is not found ({$refund_remote_id})\n";
					continue;
				}
			}

			$db->begin();
			$error = 0;

			// Force the invoice virtuality at draft
			$invoice->statut = $invoice->status = Facture::STATUS_DRAFT;
			$invoice->brouillon = 1;

			// Credit note invoices
			if ($invoice->type == Facture::TYPE_CREDIT_NOTE) {
				$lines = $order_data['refunds'][$refund_remote_id]['items'];
			} // Standard and deposit invoices
			else {
				$lines = $order_data['items'];
			}

			// Delete all lines of the credit note invoice
			if (is_array($invoice->lines)) {
				foreach ($invoice->lines as $line) {
					// Delete discount
					$sql2 = "DELETE FROM " . MAIN_DB_PREFIX . "societe_remise_except WHERE fk_facture_line = " . $line->id;
					$resql2 = $db->query($sql2);
					if (!$resql2) {
						print "\nError: SQL : $sql2; Error: " . $db->lasterror() . "\n";
						$error++;
						break;  // break on items
					}

					// Delete line
					$result = $invoice->deleteline($line->id);
					if ($result < 0) {
						$error++;
						print "\nError: Delete line {$line->id} of invoice {$invoice->ref} : " . $invoice->errorsToString() . "\n";
						break;  // break on items
					}
				}
			}

			if (!$error && is_array($lines)) {
				$warehouseByLine = array();
				$result = $eCommerceSynchro->addProductLines($invoice, $lines, $order_data, $warehouseByLine, true);
				if ($result < 0) {
					print "\nError: Add lines into the invoice {$invoice->ref} for remote order {$remote_id} : " . $eCommerceSynchro->errorsToString() . "\n";
					$error++;
				}
			}

			// Force set amount if another amount paid
			if (!$error) {
				$result = $eCommerceSynchro->fixTotalAmounts($invoice,
					$invoice->type == Facture::TYPE_CREDIT_NOTE ? -$order_data['refunds'][$refund_remote_id]['total_ttc'] : $order_data['payment_amount_ttc']);
				if ($result < 0) {
					print "\nError: Set correct amount of the invoice {$invoice->ref} for remote order {$remote_id} : " . $eCommerceSynchro->errorsToString() . "\n";
					$error++;
				}
			}

			//----------------------------------------
			// Fix order
			//----------------------------------------
			if (!$error) {
				$eCommerceCommande = new eCommerceCommande($db);
				$result = $eCommerceCommande->fetchByRemoteId($remote_id, $site->id);
				if ($result < 0 && !empty($eCommerceCommande->error)) {
					$error++;
					print "\nError: Fetch order link for remote ID {$remote_id} of invoice {$invoice->ref} : " . errorsToString($eCommerceCommande) . "\n";
				} elseif ($result > 0) {
					$order = new Commande($db);
					$result = $order->fetch($eCommerceCommande->fk_commande);
					if ($result < 0) {
						$error++;
						print "\nError: Fetch order of the invoice {$invoice->ref} for remote order {$remote_id} : " . $order->errorsToString() . "\n";
					} elseif ($result > 0) {
						print "\nFix order : {$order->ref}.\n";

						// Force the order virtuality at draft
						$order->statut = $order->status = Commande::STATUS_DRAFT;
						$order->brouillon = 1;

                        $services_id = array();
						if ($site->parameters['shipping_service'] > 0) $services_id[] = $site->parameters['shipping_service'];
						if ($site->parameters['discount_code_service'] > 0) $services_id[] = $site->parameters['discount_code_service'];
						if ($site->parameters['pw_gift_cards_service'] > 0) $services_id[] = $site->parameters['pw_gift_cards_service'];
						if ($site->parameters['acfw_store_credits_service'] > 0) $services_id[] = $site->parameters['acfw_store_credits_service'];
						if ($site->parameters['fee_service'] > 0) $services_id[] = $site->parameters['fee_service'];

						$lines = $order_data['items'];
						$rang = 1;
						foreach ($lines as $item) {
							// Get product ID
							$fk_product = 0;
							if (!empty($item['id_remote_product'])) {
								if (empty($conf->global->ECOMMERCENG_DISABLED_PRODUCT_SYNCHRO_STOD)) {
									$eCommerceProduct = new eCommerceProduct($db);
									$result = $eCommerceProduct->fetchByRemoteId($item['id_remote_product'], $site->id); // load info of table ecommerce_product
									if ($result < 0 && !empty($eCommerceProduct->error)) {
										$error++;
										print "\nError: Fetch product link for remote ID {$item['id_remote_product']} of order {$order->ref} for remote order {$remote_id} : " . $eCommerceProduct->errorsToString() . "\n";
										break;  // break on items
									} elseif ($result > 0) {
										$fk_product = $eCommerceProduct->fk_product;
									}
									unset($eCommerceProduct);
								} else {
									$product_ref = trim($item['ref']);
									if (!empty($product_ref)) {
										$product = new Product($db);
										$result = $product->fetch(0, $product_ref);
										if ($result < 0) {
											print "\nError: Fetch product by reference {$product_ref} of order {$order->ref} for remote order {$remote_id} : " . $product->errorsToString() . "\n";
											$error++;
											break;  // break on items
										} elseif ($result == 0) {
											print "\nError: Product not found by reference {$product_ref} of order {$order->ref} for remote order {$remote_id}\n";
											$error++;
											break;  // break on items
										}
										$fk_product = $product->id;
									} elseif (!empty($conf->global->ECOMMERCENG_PRODUCT_REF_MANDATORY)) {
										print "\nError: Product reference mandatory for {$item['label']} of order {$order->ref} for remote order {$remote_id}\n";
										$error++;
										break;  // break on items
									}
								}
							} elseif (!empty($item['id_product'])) {
								$fk_product = $item['id_product'];
							}

							if ($fk_product > 0) {
								// Fetch line
								if (!$error) {
									$sql2 = "SELECT rowid FROM " . MAIN_DB_PREFIX . "commandedet WHERE fk_commande = " . $order->id . " AND rang = " . $rang .
                                        " AND (" . (in_array($fk_product, $services_id) ? "fk_product IS NULL OR fk_product = 0 OR " : "") . "fk_product = " . $fk_product . ")";
									$resql2 = $db->query($sql2);
									if (!$resql2) {
										print "\nError: SQL : $sql2; Error: " . $db->lasterror() . "\n";
										$error++;
										break;  // break on items
									}

									$line_id = 0;
									if ($obj2 = $db->fetch_object($resql2)) {
										$line_id = $obj2->rowid;
									}
									$db->free($resql2);

									if (empty($line_id)) {
										print "\nWarning: Line ID not found for product {$fk_product} rang {$rang} of order {$order->ref} for remote order {$remote_id}\n";
										continue;
									}

									$line = new OrderLine($db);
									$result = $line->fetch($line_id);
									if ($result < 0) {
										print "\nError: Fetch line ID {$line_id} of order {$order->ref} for remote order {$remote_id} : " . $line->errorsToString() . "\n";
										$error++;
										break;
									} elseif ($result == 0) {
										print "\nError: Fetch line ID {$line_id} of order {$order->ref} for remote order {$remote_id} : Not found.\n";
										$error++;
										break;
									}

									$rang++;
								}

								// Update price line
								if (!$error) {
									$price = $item['price'];

									if ($price != $line->subprice) {
										$result = $order->updateline(
											$line->id, $line->desc, $price, $line->qty, $line->remise_percent,
											$line->tva_tx, $line->localtax1_tx, $line->localtax2_tx, 'HT',
											$line->info_bits, $line->date_start, $line->date_end,
											$line->product_type, $line->fk_parent_line, 0,
											$line->fk_fournprice, $line->pa_ht, $line->label, $line->special_code,
											$line->array_options, $line->fk_unit, $line->multicurrency_subprice,
											0, $line->ref_ext);
										if ($result <= 0) {
											print "\nError: Update price line of product {$fk_product} rang {$rang} of order {$order->ref} for remote order {$remote_id} : " . errorsToString($order) . "\n";
											$error++;
											break;  // break on items
										}
									}
								}
							}
						}

						// Force set amount if another amount paid
						if (!$error) {
							$result = $eCommerceSynchro->fixTotalAmounts($order, $order_data['payment_amount_ttc']);
							if ($result < 0) {
								print "\nError: Set correct amount of the order {$order->ref} for remote order {$remote_id} : " . $eCommerceSynchro->errorsToString() . "\n";
								$error++;
							}
						}
					}
				}
			}

			if ($error) {
				$db->rollback();
			} else {
				$db->commit();
			}
		}

		$db->free($resql);
	}
}

print "\nEnd.\n";

$db->close();

exit(0);

function getProduct($product_id)
{
	global $db, $product_cached;

	if (!isset($product_cached[$product_id])) {
		$dbProduct = new Product($db);
		$dbProduct->fetch($product_id);
		$dbProduct->load_stock();

		$product_cached[$product_id] = $dbProduct;
	}

	return $product_cached[$product_id];
}

function errorsToString($object, $separator = ', ')
{
	return $object->error . (is_array($object->errors) ? (!empty($object->error) ? $separator : '') . join($separator, $object->errors) : '');
}

function printStatus($num_sites, $max_sites, $num_jobs, $max_jobs)
{
	global $startTime;

	$sub_percent = $num_sites * 100 / $max_sites;
	$percent = $num_jobs * $sub_percent / $max_jobs;
	$elapsedTime = microtime(true) - $startTime;
	$remainingTime = $percent > 0 ? $elapsedTime * (100 - $percent) / $percent : 0;
	print sprintf("\rStatus: Site: %2d / %2d - Product: %6d / %6d - %3d%% - Elapsed: " . microTimeToTime($elapsedTime) . " - Remaining: " . microTimeToTime($remainingTime), $num_sites, $max_sites, $num_jobs, $max_jobs, $percent);
}

function microTimeToTime($microtime)
{
    $hours = (int)($microtime / 60 / 60);
    $minutes = (int)($microtime / 60) - $hours * 60;
    $seconds = (int)$microtime - $hours * 60 * 60 - $minutes * 60;
    return sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
}