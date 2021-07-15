'use strict';

(function($) {
  $(document).ready(function() {
    if ($('.woosw_color_picker').length > 0) {
      $('.woosw_color_picker').wpColorPicker();
    }

    $(document).on('click', '.woosw_action', function(e) {
      var pid = jQuery(this).attr('data-pid');
      var key = jQuery(this).attr('data-key');

      if (jQuery('#woosw_popup').length < 1) {
        jQuery('body').append('<div id=\'woosw_popup\'></div>');
      }

      jQuery('#woosw_popup').html('Loading...');

      if (key && key != '') {
        jQuery('#woosw_popup').
            dialog({
              minWidth: 460,
              title: 'Wishlist #' + key,
              dialogClass: 'wpc-dialog',
            });

        var data = {
          action: 'wishlist_quickview',
          nonce: woosw_vars.nonce,
          key: key,
        };

        jQuery.post(ajaxurl, data, function(response) {
          jQuery('#woosw_popup').html(response);
        });
      }

      if (pid && pid != '') {
        jQuery('#woosw_popup').
            dialog({
              minWidth: 460,
              title: 'Product ID #' + pid,
              dialogClass: 'wpc-dialog',
            });

        var data = {
          action: 'wishlist_quickview',
          nonce: woosw_vars.nonce,
          pid: pid,
        };

        jQuery.post(ajaxurl, data, function(response) {
          jQuery('#woosw_popup').html(response);
        });
      }

      e.preventDefault();
    });
  });
})(jQuery);