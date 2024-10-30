<?php
/*
  Plugin Name: Canvasflow Export
  Description: This plugin provides a quick, simple and secure way to push Canvasflow articles directly to WordPress.
  Version: 1.2.1
    Developer:  Canvasflow
    Developer URI: https://canvasflow.io
  License: GNU General Public License v3.0
  Text Domain: wp-canvasflow
*/

require_once(plugin_dir_path( __FILE__ ) . 'includes/canvasflow-export-db.php');
require_once(plugin_dir_path( __FILE__ ) . 'includes/canvasflow-export-controller.php');


register_activation_hook( __FILE__, 'canvasflow_export_rest_on_activate');
register_uninstall_hook( __FILE__, 'canvasflow_export_on_uninstall' );

add_action( 'admin_menu', 'canvasflow_export_add_menu');

add_action( 'rest_api_init',  function () {
	$controller = new Canvasflow_Export_Controller();
	$controller->register_routes();
});

add_action( 'admin_enqueue_scripts', 'canvasflow_export_register_rest_script' );

function canvasflow_export_register_rest_script($hook) {
	wp_enqueue_style( 'cf-style', plugins_url('assets/css/style.css', __FILE__));
	wp_enqueue_script('cf_rest_script', plugin_dir_url(__FILE__) . 'assets/js/cf-rest.js');
}

function canvasflow_export_rest_on_activate() {
	$canvasflow_db = new Canvasflow_Export_DB();
	$canvasflow_db->create_canvasflow_rest_credentials_if_not_exists();
}

function canvasflow_export_on_uninstall() {
	$canvasflow_db = new Canvasflow_Export_DB();
	$canvasflow_db->delete_canvasflow_rest_credentials();
}

function canvasflow_export_add_menu() {
	add_options_page( 
		__('Canvasflow Export'), // Page Title
		__('Canvasflow Export'), // Menu Title
		'manage_options', 
		'canvasflow-export-rest', 
		'canvasflow_export_page_settings', 
		plugins_url('assets/img/favicon.png', __FILE__),
		'2' // Position
	);
}

function canvasflow_export_page_settings() {
	include( plugin_dir_path( __FILE__ ) . 'includes/canvasflow-export-settings.php');
}

