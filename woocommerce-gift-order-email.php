<?php
/**
 * Plugin Name: WooCommerce Custom Gift Order Email
 * Plugin URI: http://www.skyverge.com/blog/how-to-add-a-custom-woocommerce-email/
 * Description: Plugin for adding a custom WooCommerce email that sends gift recipients an email when an order is received with a gift recipient email.
 * Author: Aidan Graf
 * Version: 0.1
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 *  Add a custom email to the list of emails WooCommerce should load
 *
 * @since 0.1
 * @param array $email_classes available email classes
 * @return array filtered available email classes
 */
function add_gift_order_woocommerce_email( $email_classes ) {
	
	// Defines a path for the templates to be used
  	define( 'GIFT_TEMPLATE_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) . '/templates/' );

	// include our custom email class
	require_once( 'includes/class-wc-gift-order-email.php' );

	// add the email class to the list of email classes that WooCommerce loads
	$email_classes['WC_Gift_Order_Email'] = new WC_Gift_Order_Email();

	return $email_classes;

}
add_filter( 'woocommerce_email_classes', 'add_gift_order_woocommerce_email' );
