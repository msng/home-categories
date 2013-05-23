<?php
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

include 'config.php';
delete_option($config['option_name']);

