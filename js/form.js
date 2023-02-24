jQuery(document).ready(function (){
  var ef_crp_all = $('.ef_crp_all');

  $.map(ef_crp_all, function (item) {
    var table = $(item).closest('table');

    act_ef_crp_update_all_checkbox(table);
  });

  $('.ef_crp_state').click(function () {
    var _this = $(this);
    var state = _this.is(':checked');
    var table = _this.closest('table');
    var tr_line = _this.closest('tr');

    tr_line.find('.ef_crp_value').prop('disabled', !state);
    act_ef_crp_update_all_checkbox(table);
  });

  ef_crp_all.click(function () {
    var _this = $(this);
    var table = _this.closest('table');
    var state = _this.is(':checked');

    table.find('.ef_crp_state').prop('checked', state);
    table.find('.ef_crp_value').prop('disabled', !state);
  });

  function act_ef_crp_update_all_checkbox(table) {
    var all_checkbox_checked = table.find('.ef_crp_state').length == table.find('.ef_crp_state:checked').length;

    table.find('.ef_crp_all').prop('checked', all_checkbox_checked);
  }
});