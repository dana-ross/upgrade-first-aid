<?php

/*
Plugin Name: Upgrade First Aid
Plugin URI: https://github.com/daveross/upgrade-first-aid
Description: Troubleshoot issues automatically upgrading WordPress, its themes, and its plugins.
Author: David Michael Ross
Version: 1.0
Author URI: http://davidmichaelross.com
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: upgrade_first_aid
*/

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once trailingslashit( dirname( __FILE__ ) ) . 'inc/class-ufa-admin-notifier.php';
require_once trailingslashit( dirname( __FILE__ ) ) . 'inc/class-upgrade-first-aid-util.inc';
require_once trailingslashit( dirname( __FILE__ ) ) . 'inc/class-upgrade-first-aid.php';
define( 'UPGRADE_FIRST_AID_DISK_FREE_THRESHOLD', 1024 * 10 );
