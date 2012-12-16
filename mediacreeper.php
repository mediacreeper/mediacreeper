<?php
/*
Plugin Name: MediaCreeper
Author URI: http://mediacreeper.github.com/mediacreeper/
Plugin URI: http://wordpress.org/extend/plugins/mediacreeper/
Description: MediaCreeper.com statistics inside Wordpress admin
Version: 1.0
Author: Martin Alice <mediacreeper.wordpress [at] gmail.com>

Requires Wordpress 2.8 or later

*/

/* Load implementation class */
require_once(dirname(__FILE__) .'/Mediacreeper.class.php');


if(!function_exists('add_action'))
	exit;

add_action(Mediacreeper::CRON_ACTION_NAME, Mediacreeper::CRON_ACTION_FUNC);
/**
 * Wrapper function (Mediacreeper::CRON_ACTION_FUNC) with the sole
 * purpose of running Mediacreeper::cronjob()
 */
function mediacreeper_cron_wrapper() {
	$MediacreeperPlugin = new Mediacreeper();
	$MediacreeperPlugin->cronjob();
}


if(is_admin()) {
	$MediacreeperPlugin = new Mediacreeper();
	register_activation_hook(__FILE__, array(&$MediacreeperPlugin, 'activate'));
	register_deactivation_hook(__FILE__, array(&$MediacreeperPlugin, 'deactivate'));
}
