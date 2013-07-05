<?php

/**
 * Utility functions used by UpgradeConcierge
 */
class UpgradeFirstAidUtil {

	/**
	 * Get the user currently running this PHP code.
	 * @return string
	 */
	public static function current_php_user() {
		if ( function_exists( 'posix_getpwuid' ) && function_exists( 'posix_getuid' ) ) {
			$user = posix_getpwuid( posix_getuid() );
			if ( isset( $user['name'] ) ) {
				return $user['name'];
			}
		}

		$username = getenv( 'USERNAME' );
		if ( !empty( $username ) ) {
			return $username;
		}

		// Fallthrough
		return WP_Error( "Can't get current user" );
	}

	/**
	 * Render an icon for display next to a note about WordPress's chosen upgrade method (direct, ftpext, etc.)
	 * @param string $type upgrade method
	 * @return string img tag
	 */
	public static function type_icon( $type ) {
		if ( $type === 'direct' ) {
			return self::img_tag( admin_url( 'images/yes.png' ) );
		}

		// Fallthrough generic icon
		return self::img_tag( admin_url( 'images/comment-grey-bubble.png' ) );

	}

	/**
	 * Render an HTML img tag
	 * @param string $url
	 * @param string alt
	 * @return string
	 */
	public static function img_tag( $url, $alt = "" ) {
		return '<img src="' . $url . '" alt="' . esc_attr( $alt ) . '" /> ';
	}

	/**
	 * Test if the user running PHP can write to a given directory
	 * @param string $directory
	 * @return boolean
	 */
	public static function can_write_to_directory($directory) {
		$can_write = false;
		$temp_file_name = trailingslashit($directory) . 'temp-write-test-' . time();
		$temp_handle = @fopen($temp_file_name, 'w');
		if ( $temp_handle ) {
			$can_write = true;
			@fclose($temp_handle);
			@unlink($temp_file_name);
		}

		return $can_write;
	}

	public static function upgrade_method_description( $type ) {
		switch ( $type ) {
			case 'direct':
				return __( 'automatically', 'upgrade_first_aid' );
			case 'ftpext':
				return __( "using PHP's FTP extension", 'upgrade_first_aid' );
			case 'ssh':
				return __( 'using SSH', 'upgrade_first_aid' );
			case 'ftpsockets':
				return __( 'using FTP over sockets', 'upgrade_first_aid' );
		}

		// Fallthrough
		return WP_Error( "Unrecognized filesystem method $type" );
	}

}
