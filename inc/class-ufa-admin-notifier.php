<?php

/**
 * Class UFAAdminNotifier
 * Singleton to manage admin notifications & errors
 */
class UFAAdminNotifier {

	/**
	 * Enqueue an error message
	 *
	 * @param string $message
	 */
	public static function error( $message ) {

		$instance = self::instance();
		if ( is_array( $message ) ) {
			$instance->admin_errors = array_merge( $instance->admin_errors, $message );
		}
		elseif ( is_scalar( $message ) ) {
			$instance->admin_errors[] = $message;
		}
		else {
			wp_die( 'Unknown error message format' );
		}

	}

	/**
	 * Enqueue a status message
	 *
	 * @param string $message
	 */
	public static function message( $message ) {

		$instance = self::instance();
		if ( is_array( $message ) ) {
			$instance->admin_messages = array_merge( $instance->admin_messages, $message );
		}
		elseif ( is_scalar( $message ) ) {
			$instance->admin_messages[] = $message;
		}
		else {
			wp_die( 'Unknown error message format' );
		}

	}

	/**
	 * Check if there is output queued
	 *
	 * @return bool
	 */
	public static function has_output() {
		$instance = self::instance();
		return self::has_errors() || self::has_messages();
	}

	/**
	 * Check if there are errors queued
	 * @return bool
	 */
	public static function has_errors() {
		$instance = self::instance();
		return ! empty( $instance->admin_errors );
	}

	/**
	 * Check if there are messages queued
	 * @return bool
	 */
	public static function has_messages() {
		$instance = self::instance();
		return ! empty( $instance->admin_messages );
	}

	/**
	 * Return and optionally instantiate the singleton instance
	 *
	 * @return UFAAdminNotifier
	 */
	protected static function instance() {

		static $instance;
		if ( ! isset( $instance ) ) {
			$instance = new self();
		}

		return $instance;

	}

	protected function __construct() {

		$this->admin_errors   = array();
		$this->admin_messages = array();
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'network_admin_notices', array( $this, 'admin_notices' ) );

	}

	/**
	 * Display admin notices/messages
	 */
	public function admin_notices() {

		foreach ( $this->admin_errors as $message ) {
			echo '<div class="error"><p>';
			echo wp_kses( $message, array() );
			echo '</p></div>';
		}

		foreach ( $this->admin_messages as $message ) {
			echo '<div class="updated"><p>';
			echo wp_kses( $message, array() );
			echo '</p></div>';
		}

	}

}
