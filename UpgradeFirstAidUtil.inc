<?php

/**
 * Utility functions used by UpgradeFirstAid
 */
class UpgradeFirstAidUtil {

	/**
	 * Get the user currently running this PHP code.
	 *
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
		if ( ! empty( $username ) ) {
			return $username;
		}

		// Fallthrough
		return WP_Error( "Can't get current user" );
	}

	/**
	 * Render an icon for display next to a note about WordPress's chosen upgrade method (direct, ftpext, etc.)
	 *
	 * @param string $type upgrade method
	 *
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
	 *
	 * @param string $url
	 * @param        string  alt
	 *
	 * @return string
	 */
	public static function img_tag( $url, $alt = "" ) {
		return '<img src="' . $url . '" alt="' . esc_attr( $alt ) . '" /> ';
	}

	/**
	 * Test if the user running PHP can write to a given directory
	 *
	 * @param string $directory
	 *
	 * @return boolean
	 */
	public static function can_write_to_directory( $directory ) {
		$can_write      = false;
		$temp_file_name = trailingslashit( $directory ) . 'temp-write-test-' . time();
		$temp_handle    = @fopen( $temp_file_name, 'w' );
		if ( $temp_handle ) {
			$can_write = true;
			@fclose( $temp_handle );
			@unlink( $temp_file_name );
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

	/**
	 * wp_get_themes from WordPress 3.8.1 with patch to prevent premature exit
	 *
	 * @param array $args
	 *
	 * @return array
	 * @see https://core.trac.wordpress.org/ticket/24639
	 * @see wp_get_themes()
	 */
	public static function alt_wp_get_themes( $args = array() ) {

		global $wp_theme_directories;

		$defaults = array( 'errors' => false, 'allowed' => null, 'blog_id' => 0 );
		$args     = wp_parse_args( $args, $defaults );

		$theme_directories = search_theme_directories();

		if ( count( $wp_theme_directories ) > 1 ) {
			// Make sure the current theme wins out, in case search_theme_directories() picks the wrong
			// one in the case of a conflict. (Normally, last registered theme root wins.)
			$current_theme = get_stylesheet();
			if ( isset( $theme_directories[$current_theme] ) ) {
				$root_of_current_theme = get_raw_theme_root( $current_theme );
				if ( ! in_array( $root_of_current_theme, $wp_theme_directories ) )
					$root_of_current_theme = WP_CONTENT_DIR . $root_of_current_theme;
				$theme_directories[$current_theme]['theme_root'] = $root_of_current_theme;
			}
		}

		if ( empty( $theme_directories ) )
			return array();

		if ( is_multisite() && null !== $args['allowed'] ) {
			$allowed = $args['allowed'];
			if ( 'network' === $allowed )
				$theme_directories = array_intersect_key( $theme_directories, WP_Theme::get_allowed_on_network() );
			elseif ( 'site' === $allowed )
				$theme_directories = array_intersect_key( $theme_directories, WP_Theme::get_allowed_on_site( $args['blog_id'] ) );
			elseif ( $allowed )
				$theme_directories = array_intersect_key( $theme_directories, WP_Theme::get_allowed( $args['blog_id'] ) );
			else
				$theme_directories = array_diff_key( $theme_directories, WP_Theme::get_allowed( $args['blog_id'] ) );
		}

		$themes = array();
		static $_themes = array();

		foreach ( $theme_directories as $theme => $theme_root ) {
			if ( isset( $_themes[$theme_root['theme_root'] . '/' . $theme] ) )
				$themes[$theme] = $_themes[$theme_root['theme_root'] . '/' . $theme];
			else
				$themes[$theme] = $_themes[$theme_root['theme_root'] . '/' . $theme] = new WP_Theme( $theme, $theme_root['theme_root'] );
		}

		if ( null !== $args['errors'] ) {
			foreach ( $themes as $theme => $wp_theme ) {
				if ( $wp_theme->errors() != $args['errors'] )
					unset( $themes[$theme] );
			}
		}

		return $themes;

	}

}
