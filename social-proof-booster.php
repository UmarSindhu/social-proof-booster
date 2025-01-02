<?php
/*
 Plugin Name: Social Proof Booster
 Plugin URI: https://github.com/UmarSindhu/social-proof-booster
 Description: Displays real-time social proof popups to boost conversions.
 Version: 1.0.0
 Author: Umar Sindhu
 email: umarsindhu3@gmail.com
 Author URI: https://github.com/UmarSindhu
 License: GPLv2 or later
 License URI: https://www.gnu.org/licenses/gpl-2.0.html
 Text Domain: social-proof-booster
*/

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

// Define constants
define( 'SPBP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SPBP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// Ensure the Heartbeat API is enabled
add_filter( 'heartbeat_send', '__return_true' );

// Include main plugin file
require_once SPBP_PLUGIN_DIR . 'includes/class-spbp-social-proof-booster.php';
