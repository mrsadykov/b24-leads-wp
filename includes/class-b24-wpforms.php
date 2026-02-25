<?php
/**
 * Интеграция с WPForms: отправка заявок в Битрикс24.
 *
 * @package B24_Leads_WP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class B24_Leads_WPForms
 */
class B24_Leads_WPForms {

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

	private function __construct() {
		add_action( 'wpforms_process_complete', array( $this, 'on_form_submit' ), 10, 4 );
	}

	/**
	 * После успешной отправки формы WPForms — отправить данные в B24.
	 *
	 * @param array $fields    Sanitized field data (id, type, value, ...).
	 * @param array $entry     Original $_POST.
	 * @param array $form_data Form settings.
	 * @param int   $entry_id  Entry ID.
	 */
	public function on_form_submit( $fields, $entry, $form_data, $entry_id ) {
		if ( ! is_array( $fields ) || empty( $fields ) ) {
			return;
		}

		$data = $this->map_fields_to_b24_data( $fields );
		if ( empty( $data ) ) {
			return;
		}
		$data['_form_type'] = 'wpforms';
		$data['_form_id']   = isset( $form_data['id'] ) ? (int) $form_data['id'] : 0;
		$data['_form_name'] = isset( $form_data['settings']['form_title'] ) ? $form_data['settings']['form_title'] : '';

		do_action( 'b24_leads_wp_send_lead', $data );
	}

	/**
	 * Преобразует поля WPForms в универсальный массив для B24.
	 * Маппинг по типу поля: name, email, phone, textarea, text.
	 *
	 * @param array $fields Элементы с type, value, name и др.
	 * @return array
	 */
	private function map_fields_to_b24_data( $fields ) {
		$data = array();

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$value = isset( $field['value'] ) ? $field['value'] : '';
			if ( is_array( $value ) ) {
				$value = implode( ', ', $value );
			}
			$value = trim( (string) $value );
			if ( $value === '' ) {
				continue;
			}

			$type = isset( $field['type'] ) ? strtolower( $field['type'] ) : '';
			$name = isset( $field['name'] ) ? strtolower( sanitize_key( $field['name'] ) ) : '';

			switch ( $type ) {
				case 'name':
					$data['name'] = $value;
					break;
				case 'email':
					$data['email'] = $value;
					break;
				case 'phone':
					$data['phone'] = $value;
					break;
				case 'textarea':
					$data['message'] = $value;
					break;
				case 'text':
					if ( strpos( $name, 'message' ) !== false || strpos( $name, 'comment' ) !== false ) {
						$data['message'] = $value;
					} elseif ( empty( $data['name'] ) ) {
						$data['name'] = $value;
					}
					break;
				default:
					if ( empty( $data['name'] ) && ( $type === 'text' || $type === '' ) ) {
						$data['name'] = $value;
					} elseif ( $name !== '' && ! isset( $data[ $name ] ) ) {
						$data[ $name ] = $value;
					}
					break;
			}
		}

		if ( empty( $data['title'] ) && ! empty( $data['name'] ) ) {
			$data['title'] = $data['name'];
		}

		return apply_filters( 'b24_leads_wp_wpforms_data', $data, $fields );
	}
}
