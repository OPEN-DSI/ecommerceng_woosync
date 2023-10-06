jQuery(document).ready(function (){
  jQuery('.ef_state_all').click(function () {
    var _this = jQuery(this);
    var state = _this.is(':checked');
    var target = _this.attr('data-target');

    jQuery('.'+target).prop('checked', state).map(function (idx, item) {
      var _this = jQuery(item);
      ef_update(_this);
    });
  });

  jQuery('.ef_state').click(function () {
    var _this = jQuery(this);
    ef_update(_this);
  });

  function ef_update(_this) {
    var target = _this.attr('data-target');
    var state = _this.is(':checked');

    jQuery('.'+target).prop('disabled', !state);
  }
});