<?php
/*
Plugin Name: Guetzli
Plugin URI: http://www.ohayoweb.com/guetzli
Description: 
Author: ohayoweb
Version: 1.0
Author URI: http://www.ohayoweb.com/?utm_source=wordpress&utm_medium=plugin_uri&utm_campaign=wordpress_plugins&utm_term=guetzli
*/

if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'GUETZLI__PLUGIN_VER', '1.0' );
define( 'GUETZLI__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// Si existe la constante, no la definiremos
if ( ! defined( 'GUETZLI__PLUGIN_DEBUG' ) ) {
	define( 'GUETZLI__PLUGIN_DEBUG', false );
}

require_once( GUETZLI__PLUGIN_DIR . 'includes/class.utils.guetzli.php' );
require_once( GUETZLI__PLUGIN_DIR . 'includes/class.wp.guetzli.php' );


if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once( GUETZLI__PLUGIN_DIR . 'includes/wp-cli.php' );
}

function run_Guetzli_Plugin() {
	$guetzli = WP_Guetzli::init();
}
run_Guetzli_Plugin();
