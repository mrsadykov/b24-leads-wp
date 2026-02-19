<?php
/**
 * Интеграция с Gravity Forms: отправка заявок в Битрикс24.
 *
 * @package B24_Leads_WP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class B24_Leads_Gravity
 */
class B24_Leads_Gravity {

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
		add_action( 'gform_after_submission', array( $this, 'on_form_submit' ), 10, 2 );
	}

	/**
	 * После отправки формы Gravity Forms — отправить данные в B24.
	 *
	 * @param array $entry Entry array (field_id => value).
	 * @param array $form  Form object with 'fields' array.
	 */
	public function on_form_submit( $entry, $form ) {
		if ( ! is_array( $entry ) || ! is_array( $form ) || empty( $form['fields'] ) ) {
			return;
		}

		$data = $this->map_entry_to_b24_data( $entry, $form['fields'] );
		if ( empty( $data ) ) {
			return;
		}

		do_action( 'b24_leads_wp_send_lead', $data );
	}

	/**
	 * Преобразует entry + fields в универсальный массив для B24.
	 * Маппинг по типу поля GF: name, email, phone, text, textarea.
	 *
	 * @param array $entry  Entry (field id => value).
	 * @param array $fields Form fields (id, type, label).
	 * @return array
	 */
	private function map_entry_to_b24_data( $entry, $fields ) {
		$data = array();

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) || empty( $field['id'] ) ) {
				continue;
			}
			$id    = (string) $field['id'];
			$value = isset( $entry[ $id ] ) ? $entry[ $id ] : '';
			$value = is_array( $value ) ? implode( ', ', $value ) : trim( (string) $value );
			if ( $value === '' ) {
				continue;
			}

			$type  = isset( $field['type'] ) ? strtolower( $field['type'] ) : '';
			$label = isset( $field['label'] ) ? strtolower( trim( $field['label'] ) ) : '';

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
					if ( strpos( $label, 'message' ) !== false || strpos( $label, 'comment' ) !== false || strpos( $label, 'сообщение' ) !== false ) {
						$data['message'] = $value;
					} elseif ( empty( $data['name'] ) ) {
						$data['name'] = $value;
					} else {
						$data[ 'field_' . $id ] = $value;
					}
					break;
				default:
					if ( empty( $data['name'] ) && in_array( $type, array( 'text', '' ), true ) ) {
						$data['name'] = $value;
					} else {
						$data[ 'field_' . $id ] = $value;
					}
					break;
			}
		}

		if ( empty( $data['title'] ) && ! empty( $data['name'] ) ) {
			$data['title'] = $data['name'];
		}

		return apply_filters( 'b24_leads_wp_gravity_data', $data, $entry, $fields );
	}
}
