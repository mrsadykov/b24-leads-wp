<?php
/**
 * Логирование отправок в Битрикс24.
 *
 * @package B24_Leads_WP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class B24_Leads_Logger
 */
class B24_Leads_Logger {

	const OPTION_KEY = 'b24_leads_wp_log';
	const MAX_ENTRIES = 200;

	/**
	 * @var self
	 */
	private static $instance = null;

	/**
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Записать запись в лог.
	 *
	 * @param string $type    success | error | skip
	 * @param string $message Краткое сообщение.
	 * @param array  $detail  Доп. данные (method, id, code, response и т.д.).
	 */
	public static function log( $type, $message, $detail = array() ) {
		$entry = array(
			'time'    => current_time( 'mysql' ),
			'type'    => in_array( $type, array( 'success', 'error', 'skip' ), true ) ? $type : 'skip',
			'message' => is_string( $message ) ? $message : '',
			'detail'  => is_array( $detail ) ? $detail : array(),
		);

		$log = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		array_unshift( $log, $entry );
		$log = array_slice( $log, 0, self::MAX_ENTRIES );
		update_option( self::OPTION_KEY, $log, false );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			$line = sprintf( '[B24 Leads WP] %s %s — %s', strtoupper( $type ), $entry['time'], $message );
			if ( ! empty( $detail ) ) {
				$line .= ' ' . wp_json_encode( $detail );
			}
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( $line );
		}
	}

	/**
	 * Получить последние записи лога.
	 *
	 * @param int $limit
	 * @return array
	 */
	public static function get_entries( $limit = 50 ) {
		$log = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $log ) ) {
			return array();
		}
		return array_slice( $log, 0, $limit );
	}

	/**
	 * Очистить журнал.
	 */
	public static function clear() {
		update_option( self::OPTION_KEY, array(), false );
	}
}
