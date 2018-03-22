function eCommerceSubmitForm(id_form)
{
	document.getElementById(id_form).submit();
}

function eCommerceConfirmDelete(id_form, confirmation)
{
  if (confirm(confirmation))
  {
    document.getElementById(id_form+'_action').value = 'delete';
    eCommerceSubmitForm(id_form);
  }
}

function eCommerceConfirmWoocommerceUpdateDictTaxClass(id_form, confirmation)
{
  if (confirm(confirmation))
  {
    document.getElementById(id_form+'_action').value = 'update_woocommerce_tax_class';
    eCommerceSubmitForm(id_form);
  }
}

function eCommerceConfirmUpdatePriceLevel(id_form, confirmation, price_level)
{
  jQuery('#'+id_form).on('submit', function(e) {
    var current_price_level = jQuery('#'+id_form+' select[name="ecommerce_price_level"]').val();

    if (current_price_level != price_level && !confirm(confirmation)) {
      e.preventDefault();
    }
  });
}

jQuery(document).ready(function (){
  jQuery('#form_reset_data').submit(function() {
    return confirm(jQuery('#confirm').val());
  });
});