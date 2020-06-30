/**
 * Craft Commerce Multi Vendor plugin for Craft CMS
 *
 * Craft Commerce Multi Vendor JS
 *
 * @author    Josh Smith
 * @copyright Copyright (c) 2019 Josh Smith
 * @link      https://www.joshsmith.dev
 * @package   CraftCommerceMultiVendor
 * @since     1.0.0
 */
$('.js--vendor-order').on('submit', function(e) {
    var $transferBtn = $('.js--transfer');
    var vendorName = $transferBtn.data('vendor');
    var amount = $transferBtn.data('amount');

    if( !confirm('Please confirm you want to transfer ' + amount + ' to ' + vendorName + '.') ){
        e.preventDefault();
        return;
    }
});