<?php

llxHeader();

if (is_object($site))
{
    $linkback='<a href="index.php">'.$langs->trans("BackToListOfSites").'</a>';

    print_fiche_titre($langs->trans('ECommerceSiteSynchro').' '.$site->name, $linkback, 'eCommerceTitle@ecommerceng');

    print '<br>';

    $head = array();
    $head[1][0] = '?id='.$site->id;
    $head[1][1] = $langs->trans("Direction").' : Ecommerce -> Dolibarr';
    $head[1][2] = 'ecommerce2dolibarr';

    dol_fiche_head($head, 'ecommerce2dolibarr', '');

    print '<form name="form_count" id="form_count" action="'.$_SERVER['PHP_SELF'].'?id='.$site->id.'" method="post">';
    //print '<input type="hidden" name="id" value="'.$site->id.'">';
    if (GETPOST('test_with_no_categ_count','alpha')) print '<input type="hidden" name="test_with_no_categ_count" value="'.GETPOST('test_with_no_categ_count','alpha').'">';
    if (GETPOST('test_with_no_product_count','alpha')) print '<input type="hidden" name="test_with_no_product_count" value="'.GETPOST('test_with_no_product_count','alpha').'">';
    if (GETPOST('test_with_no_thirdparty_count','alpha')) print '<input type="hidden" name="test_with_no_thirdparty_count" value="'.GETPOST('test_with_no_thirdparty_count','alpha').'">';
    if (GETPOST('test_with_no_order_count','alpha')) print '<input type="hidden" name="test_with_no_order_count" value="'.GETPOST('test_with_no_order_count','alpha').'">';
    if (GETPOST('test_with_no_invoice_count','alpha')) print '<input type="hidden" name="test_with_no_invoice_count" value="'.GETPOST('test_with_no_invoice_count','alpha').'">';
    print '<input type="hidden" name="id" value="'.$site->id.'">';

    print '<table class="centpercent nobordernopadding">';

    print '<tr><td>';

    print $langs->trans("ECommerceLastCompleteSync", $site->name).' : ';
    if ($site->last_update) print '<strong>'.dol_print_date($site->last_update, 'dayhoursec').'</strong>';
    else print '<strong>'.$langs->trans("ECommerceNoUpdateSite").'</strong>';

    print '</td><td align="right"></td></tr>';

    print '<tr><td>';

    print $langs->trans("RestrictCountAndSynchForRecordBefore").' ';
    //print '(YYYYMMDDHHMMSS) ';
    print '<input type="text" name="to_date" value="'.dol_escape_htmltag($to_date).'" placeholder="YYYYMMDDHHMMSS">';

    print '</td><td>';
    $button.='<input type="submit" class="button" name="refresh" style="margin-right: 15px" href="'.$_SERVER["PHP_SELF"].'?id='.$site->id.'&action=refresh" value="'.$langs->trans('RefreshCount').'">';
    print $button;

    $disabled=true;
    if ($synchRights != true || ($nbCategoriesToUpdate>0 || $nbProductToUpdate>0 || $nbSocieteToUpdate>0 || $nbCommandeToUpdate>0 || $nbFactureToUpdate>0)) $disabled=false;

    $button2.='<input type="submit" class="button'.($disabled?' buttonRefused':'').'" name="submit_synchro_all" href="'.($disabled?'#':$_SERVER["PHP_SELF"].'?id='.$site->id.'&action=submit_synchro_all&submit_synchro_all=1').'" value="'.$langs->trans('SyncAll').'">';
    print $button2;

    print '</td></tr>';

    print '<tr><td>';

    print $langs->trans("RestrictNbInSync").' ';
    //print '(YYYYMMDDHHMMSS) ';
    print '<input type="text" name="to_nb" placeholder="0" value="'.dol_escape_htmltag($to_nb).'">';

    print '</td><td>';
    print '</td></tr>';

    if (! empty($site->fk_anonymous_thirdparty))
    {
        print '<tr><td>';

        print $langs->trans("SynchUnkownCustomersOnThirdParty").' : ';
        $nonloggedthirdparty=new Societe($db);
        $result = $nonloggedthirdparty->fetch($site->fk_anonymous_thirdparty);
        print $nonloggedthirdparty->getNomUrl(1);
        print '</td><td align="right"></td></tr>';
    }

    print '</table>';


    print '<br>'."\n";
    ?>
	<div class="div-table-responsive">
	<table class="noborder" width="100%">
		<tr class="liste_titre">
			<td><?php print $langs->trans('ECommerceObjectToUpdate') ?></td>
			<td><?php print $langs->trans('NbInDolibarr') ?></td>
			<td><?php print $langs->trans('NbInDolibarrLinkedToE') ?></td>
			<td><?php
			print $langs->trans('ECommerceCountToUpdate');
			?></td>
			<?php if ($synchRights==true):?>
			<td>&nbsp;</td>
			<?php endif; ?>
		</tr>

    <?php
    $var=!$var;
    ?>
		<tr <?php print $bc[$var] ?>>
			<td><?php print $langs->trans('ECommerceCategoriesProducts') ?></td>
			<td><?php print $nbCategoriesInDolibarr; ?> *</td>
			<td><?php print $nbCategoriesInDolibarrLinkedToE;
			?> *
			<?php
			     if (! empty($conf->global->ECOMMERCENG_SHOW_DEBUG_TOOLS))
			     {
					    print '<div class="debugtools inline-block">(<a class="submit_reset_data_links" style="color: #600" href="'.$_SERVER["PHP_SELF"].'?id='.$site->id.'&reset_data=categories_links">'.$langs->trans("ClearLinks").'</a>';
					    print ' - <a class="submit_reset_data_all" style="color: #600" href="'.$_SERVER["PHP_SELF"].'?id='.$site->id.'&reset_data=categories_all">'.$langs->trans("ClearData").'</a>';
					    print ')</div>';
			     }
            ?>
			</td>
			<td>
				<?php
					print $nbCategoriesToUpdate;
				?>
			</td>
			<?php if ($synchRights==true) { ?>
			<td>
				<?php if ($nbCategoriesToUpdate>0||!empty($conf->global->ECOMMERCENG_NO_COUNT_UPDATE)) { ?>
					<input type="submit" name="submit_synchro_category" id="submit_synchro_category" class="button" value="<?php print $langs->trans('ECommerceSynchronizeCategoryProduct') ?>">
				<?php } ?>
			</td>
			<?php } ?>
		</tr>
    <?php
    $var=!$var;
    ?>
		<tr <?php print $bc[$var] ?>>
			<td><?php print $langs->trans('ProductsOrServices') ?></td>
			<td><?php print $nbProductInDolibarr; ?> **</td>
			<td><?php print $nbProductInDolibarrLinkedToE; ?> **
			<?php
			     if (! empty($conf->global->ECOMMERCENG_SHOW_DEBUG_TOOLS))
			     {
				      print '<div class="debugtools inline-block"> (<a class="submit_reset_data_links" style="color: #600" href="'.$_SERVER["PHP_SELF"].'?id='.$site->id.'&reset_data=products_links">'.$langs->trans("ClearLinks").'</a>';
			          print ' - <a class="submit_reset_data_all" style="color: #600" href="'.$_SERVER["PHP_SELF"].'?id='.$site->id.'&reset_data=products_all">'.$langs->trans("ClearData").'</a>';
					  print ')</div>';
			     }
            ?>
			</td>
			<td><?php print $nbProductToUpdate;	?>
			</td>
			<?php if ($synchRights==true):?>
			<td>
				<?php
				if ($nbProductToUpdate>0 && $nbCategoriesToUpdate>0&&empty($conf->global->ECOMMERCENG_NO_COUNT_UPDATE)) {
				    ?>
					<input type="submit" name="submit_synchro_product" id="submit_synchro_product" class="button" disabled="disabled" value="<?php print $langs->trans('ECommerceSynchronizeProduct').' ('.$langs->trans("SyncCategFirst").")"; ?>">
				<?php
				}
				elseif ($nbProductToUpdate>0||!empty($conf->global->ECOMMERCENG_NO_COUNT_UPDATE)) { ?>
					<input type="submit" name="submit_synchro_product" id="submit_synchro_product" class="button" value="<?php print $langs->trans('ECommerceSynchronizeProduct') ?>">
				<?php } ?>
			</td>
			<?php endif; ?>
		</tr>
    <?php
    $var=!$var;
    ?>
		<tr <?php print $bc[$var] ?>>
			<td><?php print $langs->trans('ECommerceSociete') ?> (<?php print $langs->trans('ECommerceOnlyNew') ?>)</td>
			<td><?php print $nbSocieteInDolibarr; ?> ***</td>
			<td><?php print $nbSocieteInDolibarrLinkedToE; ?> ***
			<?php
			     if (! empty($conf->global->ECOMMERCENG_SHOW_DEBUG_TOOLS))
			     {
					    print '<div class="debugtools inline-block"> (<a class="submit_reset_data_links" style="color: #600" href="'.$_SERVER["PHP_SELF"].'?id='.$site->id.'&reset_data=thirdparties_links">'.$langs->trans("ClearLinks").'</a>';
					    print ' - <a class="submit_reset_data_all" style="color: #600" href="'.$_SERVER["PHP_SELF"].'?id='.$site->id.'&reset_data=thirdparties_all">'.$langs->trans("ClearData").'</a>';
					    print ')</div>';
			     }
            ?>
			</td>
			<td>
				<?php
					print $nbSocieteToUpdate;
				?> ****
			</td>
			<?php if ($synchRights==true):?>
			<td>
				<?php if ($nbSocieteToUpdate>0||!empty($conf->global->ECOMMERCENG_NO_COUNT_UPDATE)): ?>
					<input type="submit" name="submit_synchro_societe" id="submit_synchro_societe" class="button" value="<?php print $langs->trans('ECommerceSynchronizeSociete') ?>">
				<?php endif; ?>
			</td>
			<?php endif; ?>
		</tr>
    <?php
    $var=!$var;
    ?>
		<tr <?php print $bc[$var] ?>>
			<td><?php print $langs->trans('ECommerceCommande') ?></td>
			<?php
            if (empty($conf->commande->enabled))
            {
                ?>
				<td colspan="3"><?php print $langs->trans("ModuleCustomerOrderDisabled"); ?></td>
                <?php
            }
            else
            {
                ?>
    			<td><?php print $nbCommandeInDolibarr; ?></td>
    			<td><?php print $nbCommandeInDolibarrLinkedToE; ?>
    			<?php
    			     if (! empty($conf->global->ECOMMERCENG_SHOW_DEBUG_TOOLS))
    			     {
    					    print '<div class="debugtools inline-block"> (<a class="submit_reset_data_links" style="color: #600" href="'.$_SERVER["PHP_SELF"].'?id='.$site->id.'&reset_data=orders_links">'.$langs->trans("ClearLinks").'</a>';
    					    print ' - <a class="submit_reset_data_all" style="color: #600" href="'.$_SERVER["PHP_SELF"].'?id='.$site->id.'&reset_data=orders_all">'.$langs->trans("ClearData").'</a>';
    					    print ')</div>';
    			     }
                ?>
    			</td>
    			<td><?php print $nbCommandeToUpdate; ?>
    			</td>
    			<?php if ($synchRights==true):?>
    			<td>
                        <?php
    				if ($nbCommandeToUpdate>0 && $nbSocieteToUpdate>0&&empty($conf->global->ECOMMERCENG_NO_COUNT_UPDATE)) { ?>
    					<input type="submit" name="submit_synchro_commande" id="submit_synchro_commande" class="button" disabled="disabled" value="<?php print $langs->trans('ECommerceSynchronizeCommande').' ('.$langs->trans("SyncSocieteFirst").')'; ?>">
    				<?php } elseif ($nbCommandeToUpdate>0||!empty($conf->global->ECOMMERCENG_NO_COUNT_UPDATE)) { ?>
    					<input type="submit" name="submit_synchro_commande" id="submit_synchro_commande" class="button" value="<?php print $langs->trans('ECommerceSynchronizeCommande') ?>">
    				<?php } ?>
    			</td>
    			<?php endif;
            }
            ?>
		</tr>
    <?php
    $var=!$var;
    if (! empty($conf->facture->enabled))
    {
    ?>
		<tr <?php print $bc[$var] ?>>
			<td><?php print $langs->trans('ECommerceFacture') ?></td>
			<td><?php print $nbFactureInDolibarr; ?></td>
			<td><?php print $nbFactureInDolibarrLinkedToE; ?>
			<?php
			     if (! empty($conf->global->ECOMMERCENG_SHOW_DEBUG_TOOLS))
			     {
					    print '<div class="debugtools inline-block"> (<a class="submit_reset_data_links" style="color: #600" href="'.$_SERVER["PHP_SELF"].'?id='.$site->id.'&reset_data=invoices_links">'.$langs->trans("ClearLinks").'</a>';
					    print ' - <a class="submit_reset_data_all" style="color: #600" href="'.$_SERVER["PHP_SELF"].'?id='.$site->id.'&reset_data=invoices_all">'.$langs->trans("ClearData").'</a>';
					    print ')</div>';
			     }
            ?>
			</td>
			<td>
				<?php
					print $nbFactureToUpdate;
				?>
			</td>
			<?php if ($synchRights==true):?>
			<td>
				<?php if ($nbFactureToUpdate>0 && $nbSocieteToUpdate>0&&empty($conf->global->ECOMMERCENG_NO_COUNT_UPDATE)) { ?>
					<input type="submit" name="submit_synchro_facture" id="submit_synchro_facture" class="button" disabled="disabled" value="<?php print $langs->trans('ECommerceSynchronizeFacture').' ('.$langs->trans("SyncSocieteFirst").')'; ?>">
				<?php } elseif ($nbFactureToUpdate>0 && $nbCommandeToUpdate>0&&empty($conf->global->ECOMMERCENG_NO_COUNT_UPDATE)) { ?>
					<input type="submit" name="submit_synchro_facture" id="submit_synchro_facture" class="button" disabled="disabled" value="<?php print $langs->trans('ECommerceSynchronizeFacture').' ('.$langs->trans("SyncCommandeFirst").')'; ?>">
				<?php } elseif ($nbFactureToUpdate>0||!empty($conf->global->ECOMMERCENG_NO_COUNT_UPDATE)) { ?>
					<input type="submit" name="submit_synchro_facture" id="submit_synchro_facture" class="button" value="<?php print $langs->trans('ECommerceSynchronizeFacture') ?>">
				<?php } ?>
			</td>
			<?php endif; ?>
		</tr>
	<?php } ?>
	</table>
	</div>
	<?php
	$categorytmpprod=new Categorie($db);
	$categorytmpprod->fetch($site->fk_cat_product);
	$tagnameprod=$categorytmpprod->label;
	$categorytmp=new Categorie($db);
	$categorytmp->fetch($site->fk_cat_societe);
	$tagname=$categorytmp->label;
	print '<span class="opacitymedium">';
	print '* '.$langs->trans("OnlyProductCategIn", $tagnameprod).'<br>';
	print '** '.$langs->trans("OnlyProductsIn", $tagnameprod, $tagnameprod).'<br>';
	//print '*** '.$langs->trans("OnlyThirdPartyWithTags", $tagname).'<br>';
	print '*** '.$langs->trans("OnlyThirdPartyIn", $tagname).'<br>';
	print '**** '.$langs->trans("WithMagentoThirdIsModifiedIfAddressModified").'<br>';
	print '</span>';

	dol_fiche_end();

	print '<br>';

    $head = array();
    $head[1][0] = '?id='.$site->id;
    $head[1][1] = $langs->trans("Direction").' : Dolibarr -> Ecommerce';
    $head[1][2] = 'dolibarr2ecommerce';

    dol_fiche_head($head, 'dolibarr2ecommerce', '');

	print $langs->trans("SyncIsAutomaticInRealTime", $site->name)."\n";

    print '<form name="form_count" id="form_count" action="'.$_SERVER['PHP_SELF'].'?id='.$site->id.'" method="post">';
    //print '<input type="hidden" name="id" value="'.$site->id.'">';

    print '<table class="centpercent nobordernopadding">';

    print '<tr><td>';
    print $langs->trans("RestrictNbInSync").' ';
    print '<input type="text" name="dtoe_to_nb" placeholder="0" value="'.dol_escape_htmltag($dtoe_to_nb).'">';
    print '</td><td>';
    print '<input type="submit" class="button" name="refresh" style="margin-right: 15px" href="'.$_SERVER["PHP_SELF"].'?id='.$site->id.'&action=refresh" value="'.$langs->trans('RefreshCount').'">';
    $disabled=true;
    if ($synchRights != true || (($nbCategoriesToUpdateDToE>0 || $nbProductToUpdateDToE>0) && !($nbCategoriesToUpdate>0 || $nbProductToUpdate>0))) $disabled=false;
    print '<input type="submit" class="button'.($disabled?' buttonRefused':'').'" name="submit_dtoe_synchro_all" href="'.($disabled?'#':$_SERVER["PHP_SELF"].'?id='.$site->id.'&action=submit_dtoe_synchro_all').'" value="'.$langs->trans('SyncAll').'">';
    print '</td></tr>';

    print '</table>';


    print '<br>'."\n";
    ?>
	<div class="div-table-responsive">
	<table class="noborder" width="100%">
		<tr class="liste_titre">
			<td><?php print $langs->trans('ECommerceObjectToUpdate') ?></td>
			<td><?php print $langs->trans('NbInDolibarr') ?></td>
			<td><?php print $langs->trans('NbInDolibarrLinkedToE') ?></td>
			<td><?php print $langs->trans('ECommerceCountToUpdateDtoE'); ?></td>
			<?php if ($synchRights==true) { ?>
			<td>&nbsp;</td>
			<?php } ?>
		</tr>

    <?php
    $var=!$var;
    ?>
		<tr <?php print $bc[$var] ?>>
			<td><?php print $langs->trans('ECommerceCategoriesProducts') ?></td>
			<td><?php print $nbCategoriesInDolibarr ?> *</td>
			<td><?php print $nbCategoriesInDolibarrLinkedToE ?> *</td>
			<td><?php print $nbCategoriesToUpdateDToE ?></td>
			<?php if ($synchRights==true) { ?>
			<td>
				<?php if ($nbCategoriesToUpdateDToE>0) { ?>
					<input type="submit" name="submit_dtoe_synchro_category" id="submit_dtoe_synchro_category" class="button"<?php print ($nbCategoriesToUpdate>0 ? ' disabled="disabled"' : '') ?> value="<?php print ($nbCategoriesToUpdate>0 ? $langs->trans('ECommerceSynchronizeCategoryProduct').' ('.$langs->trans("SyncCategFirst").")" : $langs->trans('ECommerceSynchronizeCategoryProduct')) ?>">
				<?php } ?>
			</td>
			<?php } ?>
		</tr>

    <?php
    $var=!$var;
    ?>
		<tr <?php print $bc[$var] ?>>
			<td><?php print $langs->trans('ProductsOrServices') ?></td>
			<td><?php print $nbProductInDolibarr ?> **</td>
			<td><?php print $nbProductInDolibarrLinkedToE ?> **</td>
			<td><?php print $nbProductToUpdateDToE ?> ***</td>
			<?php if ($synchRights==true) { ?>
			<td>
				<?php if ($nbProductToUpdateDToE>0) { ?>
					<input type="submit" name="submit_dtoe_synchro_product" id="submit_dtoe_synchro_product" class="button"<?php print ($nbCategoriesToUpdateDToE>0 || $nbCategoriesToUpdate>0 || $nbProductToUpdate>0 ? ' disabled="disabled"' : '') ?> value="<?php print ($nbCategoriesToUpdate>0 || $nbProductToUpdate>0 || $nbCategoriesToUpdateDToE>0? $langs->trans('ECommerceSynchronizeProduct').' ('.($nbProductToUpdate>0?$langs->trans("SyncProductFirst"):$langs->trans("SyncCategFirst")).")" : $langs->trans('ECommerceSynchronizeProduct')) ?>">
				<?php } ?>
			</td>
			<?php } ?>
		</tr>
	</table>
	</div>
	<?php
	print '<span class="opacitymedium">';
	print '* '.$langs->trans("OnlyProductCategIn", $tagnameprod).'<br>';
	print '** '.$langs->trans("OnlyProductsIn", $tagnameprod, $tagnameprod).'<br>';
  print '*** '.$langs->trans("ECommerceNGUpdateElementDateMoreRecent").'<br>';
	print '</span>';

	dol_fiche_end();

	print '</form>';


	if (! empty($conf->global->ECOMMERCENG_SHOW_DEBUG_TOOLS))
	{
		print '<br><br>';

		$head = array();
        $head[1][0] = '?id='.$site->id;
		$head[1][1] = $langs->trans("DangerZone");
		$head[1][2] = 'dangerzone';

   		dol_fiche_head($head, 'dangerzone', '');

	    print '<div class="nodebugtools inline-block">';
		print '<a style="color: #600" id="showtools">'.$langs->trans("ShowDebugTools").'</a>';
		print '</div>';
   		print '<div class="debugtools">';
   		print '<a style="color: #600" class="submit_reset_data_links" href="'.$_SERVER['PHP_SELF'].'?id='.$site->id.'&to_date='.$to_date.'&reset_data=links">'.$langs->trans('ECommerceResetLink').'</a>';
   		print '<br><br>';
		print '<a style="color: #600" class="submit_reset_data_all" href="'.$_SERVER['PHP_SELF'].'?id='.$site->id.'&to_date='.$to_date.'&reset_data=all">'.$langs->trans('ECommerceReset').'</a>';
		dol_fiche_end();
		print '</div>';

		print "
		<!-- Include jQuery confirm -->
		<script type=\"text/javascript\">
        jQuery(document).ready(function() {
		$('.submit_reset_data_links').on('click', function () {
		    return confirm('".dol_escape_js($langs->trans("AreYouSureYouWantToDeleteLinks"))."');
		});
        $('.submit_reset_data_all').on('click', function () {
		    return confirm('".dol_escape_js($langs->trans("AreYouSureYouWantToDeleteAll"))."');
		});
		$('#showtools').on('click', function () {
		    jQuery('.nodebugtools').toggle();
		    jQuery('.debugtools').toggle();
        });
        ";
		if (empty($_SESSION["showdebugtool"]))
		{
		    print " jQuery('.nodebugtools').show();\n";
		    print " jQuery('.debugtools').hide();\n";
		}
		else
		{
		    print " jQuery('.nodebugtools').hide();\n";
		    print " jQuery('.debugtools').show();\n";
		}
        print "});";
		print "</script>";
	}


  if ($site->type == 1) {
      print '<br>';
      $soapwsdlcacheon = ini_get('soap.wsdl_cache_enabled');
      $soapwsdlcachedir = ini_get('soap.wsdl_cache_dir');
      if ($soapwsdlcacheon) {
          print '<div class="opacitymedium">' . $langs->trans("WarningSoapCacheIsOn", $soapwsdlcachedir) . '</div><br>';
      } else {
          print '<div class="opacitymedium">' . $langs->trans("SoapCacheIsOff", $soapwsdlcachedir) . '</div><br>';
      }
  }
	print '<br>';

}
else
{
	print_fiche_titre($langs->trans("ECommerceSiteSynchro"),$linkback,'eCommerceTitle@ecommerceng');
	$errors[] = $langs->trans('ECommerceSiteError');
}

setEventMessages(null, $errors, 'errors');
if (GETPOST('reset_data')) setEventMessages(null, $success, 'warnings');
else setEventMessages(null, $success);

llxFooter();
