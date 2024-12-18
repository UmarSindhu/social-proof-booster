<?php
/*
 Plugin Name: Social Proof Booster
 Plugin URI: https://yourwebsite.com
 Description: Displays real-time social proof popups to boost conversions.
 Version: 1.0
 Author: Umar Sindhu
 email: umarsindhu3@gmail.com
 Author URI: https://yourwebsite.com
 License: GPL2
*/
// ini_set('display_errors', '1');
// ini_set('display_startup_errors', '1');
// error_reporting(E_ALL);

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

// Define constants
define( 'SPB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SPB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// Ensure the Heartbeat API is enabled
add_filter( 'heartbeat_send', '__return_true' );

// Include main plugin file
require_once SPB_PLUGIN_DIR . 'includes/class-social-proof-booster.php';
