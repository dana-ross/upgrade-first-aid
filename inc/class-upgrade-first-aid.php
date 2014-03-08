<?php

add_action( 'admin_menu', array( 'UpgradeFirstAid', 'admin_menu' ) );
add_action( 'admin_init', array( 'UpgradeFirstAid', 'admin_init' ) );
add_action( 'admin_enqueue_scripts', array( 'UpgradeFirstAid', 'admin_enqueue_scripts' ) );

/**
 * Main functionality for the Upgrade First Aid plugin
 */
class UpgradeFirstAid {

	public static function admin_menu() {
		add_management_page( 'Upgrade First Aid', __( 'Upgrade First Aid', 'upgrade_first_aid' ), 'manage_options', __FILE__, array( 'UpgradeFirstAid', 'plugin_options' ) );
	}

	public static function admin_init() {
		load_plugin_textdomain( 'upgrade_first_aid', false, trailingslashit( dirname( plugin_basename( __FILE__ ) ) ) . '/lang/' );
		add_meta_box( 'upgrade_first_aid_resources', __( 'Resources', 'upgrade_first_aid' ), array( 'UpgradeFirstAid', 'meta_box_resources' ), 'upgrade_first_aid', 'normal', 'high' );

		// Display a warning on Windows servers
		if ( UpgradeFirstAidUtil::is_windows() ) {
			UFAAdminNotifier::error( sprintf( __( 'This plugin hasn\'t been tested on Windows servers. For helpful information on running WordPress on IIS, see <a href="%s">%s</a>', 'upgrade-first-aid' ), esc_url( 'http://codex.wordpress.org/Installing_on_Microsoft_IIS' ), 'http://codex.wordpress.org/Installing_on_Microsoft_IIS' ) );
		}
	}

	public static function admin_enqueue_scripts() {
		wp_enqueue_script( 'common' );
		wp_enqueue_script( 'postbox' );
		wp_enqueue_script( 'jquery-ui-draggable' );
	}

	/**
	 * Run tests and display status
	 *
	 * @author  Dave Ross <dave@davidmichaelross.com>
	 */
	public static function plugin_options() {

		if ( ! class_exists( 'WP_Filesystem_Direct' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		}

		$screen = get_current_screen();

		echo '<style>code {white-space: nowrap;}.details{width: 68%;min-width: 300px;float:left;margin-right:2%;}.sidebar{width:29%;min-width:200px;float:left;}</style>';
		echo '<div class=\"wrap\">';

		screen_icon();
		echo '<h2>' . __( 'Upgrade First Aid', 'upgrade_first_aid' ) . '</h2>';

		echo '<div class="details">';
		echo '<h3>' . __( 'Core Upgrades', 'upgrade_first_aid' ) . '</h3>';
		$type   = get_filesystem_method( array(), ABSPATH );
		$method = UpgradeFirstAidUtil::upgrade_method_description( $type );
		echo '<p>' . UpgradeFirstAidUtil::type_icon( $type ) . ' ' . sprintf( 'WordPress can install core upgrades %s.', $method ) . '</p>';
		self::maybe_disk_full( ABSPATH );
		self::maybe_ownership_mismatch( ABSPATH . 'index.php' );

		echo '<h3>' . __( 'Plugin Upgrades', 'upgrade_first_aid' ) . '</h3>';
		$type   = get_filesystem_method( array(), WP_PLUGIN_DIR );
		$method = UpgradeFirstAidUtil::upgrade_method_description( $type );
		echo '<p>' . UpgradeFirstAidUtil::type_icon( $type ) . sprintf( 'WordPress can install plugins %s.', $method ) . '</p>';
		self::maybe_disk_full( WP_PLUGIN_DIR );
		self::maybe_ownership_mismatch( WP_PLUGIN_DIR );

		$all_plugins = get_plugins();
		foreach ( $all_plugins as $plugin_path => $plugin_details ) {
			$plugin_full_path = dirname( WP_PLUGIN_DIR . "/{$plugin_path}" );
			$type             = get_filesystem_method( array(), WP_PLUGIN_DIR );
			$method           = UpgradeFirstAidUtil::upgrade_method_description( $type );
			echo '<p>' . UpgradeFirstAidUtil::type_icon( $type ) . sprintf( 'WordPress can upgrade the %s plugin %s.', $plugin_details['Name'], $method ) . '</p>';
			self::maybe_ownership_mismatch( $plugin_full_path, $plugin_details['Name'] );
		}

		echo '<h3>' . __( 'Theme Upgrades', 'upgrade_first_aid' ) . '</h3>';
		$type   = get_filesystem_method( array(), get_theme_root() );
		$method = UpgradeFirstAidUtil::upgrade_method_description( $type );
		echo '<p>' . UpgradeFirstAidUtil::type_icon( $type ) . sprintf( 'WordPress can install themes %s.', $method ) . '</p>';
		self::maybe_disk_full( get_theme_root() );
		self::maybe_ownership_mismatch( get_theme_root() );

		set_error_handler( array( __CLASS__, 'error_handler' ), E_ALL );
		if ( version_compare( '3.7', $GLOBALS['wp_version'], 'ge' ) ) {
			// wp_get_themes() fixed by nacin https://core.trac.wordpress.org/ticket/24639
			$all_themes = wp_get_themes();
		}
		else {
			// unpatched wp_get_themes() so a replacement is needed
			$all_themes = UpgradeFirstAidUtil::alt_wp_get_themes();
		}
		restore_error_handler();

		foreach ( $all_themes as $theme_path => $theme_details ) {
			$theme_full_path = $theme_details->get_stylesheet_directory();
			$type            = get_filesystem_method( array(), $theme_full_path );
			$method          = UpgradeFirstAidUtil::upgrade_method_description( $type );
			echo '<p>' . UpgradeFirstAidUtil::type_icon( $type ) . sprintf( 'WordPress can upgrade the %s theme %s.', $theme_details->Name, $method ) . '</p>';
			self::maybe_ownership_mismatch( get_theme_root() );
		}

		echo '</div>';

		echo '<div class="sidebar">';
		do_meta_boxes( 'upgrade_first_aid', 'normal', '' );
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Check for a possible file ownership mismatch and display an error
	 *
	 * @author Dave Ross <dave@davidmichaelross.com>
	 *
	 * @param string $context directory whose permissions should be checked
	 */
	private static function maybe_ownership_mismatch( $context, $item = 'WordPress' ) {
		$php_user             = UpgradeFirstAidUtil::current_php_user();
		$wp_filesystem_direct = new WP_Filesystem_Direct( null );
		$wp_owner             = $wp_filesystem_direct->owner( $context );

		if ( preg_match( '/index.php$/', $context ) ) {
			$directory = dirname( $context );
		}
		else {
			$directory = $context;
		}

		if ( $php_user !== $wp_owner ) {
			echo '<p>' . UpgradeFirstAidUtil::img_tag( admin_url( 'images/no.png' ) ) . sprintf( __( "PHP is currently running as the user <em>%s</em>, but the %s files are owned by the user <em>%s</em>. WordPress could install upgrades without FTP if you changed the files' owner to <em>%s</em>.", 'upgrade_first_aid' ), $php_user, $item, $wp_owner, $php_user ) . '</p>';
			if ( UpgradeFirstAidUtil::can_write_to_directory( $directory ) && ! defined( 'FS_METHOD' ) ) {
				echo '<p>' . UpgradeFirstAidUtil::img_tag( admin_url( 'images/comment-grey-bubble.png' ) ) . sprintf( __( '<em>%s</em> can write to the %s directory, so you can try adding <code>%s</code> to your wp-config.php file which might allow upgrades without FTP.', 'upgrade_first_aid' ), $php_user, $directory, "define('FS_METHOD', 'direct');" ) . '</p>';
			}
		}
	}

	/**
	 * Display an error if the disk is low on free space
	 *
	 * @author Dave Ross <dave@davidmichaelross.com>
	 *
	 * @param string $context directory whose partition should be checked
	 */
	private static function maybe_disk_full( $context ) {
		$free_space = disk_free_space( $context );
		if ( UPGRADE_FIRST_AID_DISK_FREE_THRESHOLD >= $free_space ) {
			echo '<p>' . UpgradeFirstAidUtil::img_tag( admin_url( 'images/no.png' ) ) . sprintf( __( 'The disk or partition where %s is located is dangerously low on free space.', 'upgrade_first_aid' ), $context ) . '</p>';
		}
	}

	public static function meta_box_resources() {
		echo '<ul>';
		_e( sprintf( 'The WordPress documentation has instructions on <a href="%s">how file permissions work</a>, as well as notes on <a href="%s">what permissions WordPress needs</a>.', esc_url( 'http://codex.wordpress.org/Changing_File_Permissions' ), esc_url( 'http://codex.wordpress.org/Changing_File_Permissions#Permission_Scheme_for_WordPress' ) ), 'upgrade_first_aid' );
		echo '</ul>';
	}

	public static function error_handler( $errno, $errstr, $errfile, $errline, $errcontext ) {

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			print wp_kses( "$errno $errstr $errfile $errline", array() );
			print '<hr>';
		}

	}
}
