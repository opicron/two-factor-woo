<?php

/**
 * Plugin Name: Two Factor Woo
 * Description: Add Two-Factor plugin support for Woocommerce
 * Author:      Robbert Langezaal
 * Author URI:  https://opicon.eu/
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Version:     0.1
 * Text Domain: two-factor-woo
 *
 * @package two-factor-woo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

define( 'TWO_FACTOR_WOO_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'TWO_FACTOR_WOO_URI', untrailingslashit( plugin_dir_url( __FILE__ ) ) );

require_once TWO_FACTOR_WOO_PATH . '/includes/class-plugin-loader.php';

use Two_Factor_Woo\Inc\Plugin_Loader;

Plugin_Loader::load();

?>
