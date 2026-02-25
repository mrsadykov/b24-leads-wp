<?php
/**
 * Интеграция с Elementor Forms: отправка заявок в Битрикс24.
 *
 * @package B24_Leads_WP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class B24_Leads_Elementor
 */
class B24_Leads_Elementor {

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
		add_action( 'elementor_pro/forms/new_record', array( $this, 'on_form_submit' ), 10, 2 );
	}

	/**
	 * После отправки формы Elementor — отправить данные в B24.
	 *
	 * @param \ElementorPro\Modules\Forms\Classes\Form_Record $record
	 * @param \ElementorPro\Modules\Forms\Classes\Handler     $handler
	 */
	public function on_form_submit( $record, $handler ) {
		$raw = $record->get( 'fields' );
		if ( ! is_array( $raw ) || empty( $raw ) ) {
			return;
		}

		$data = $this->map_fields_to_b24_data( $raw );
		if ( empty( $data ) ) {
			return;
		}
		$data['_form_type'] = 'elementor';
		$form_meta = $record->get( 'form' );
		$data['_form_id']   = isset( $form_meta['id'] ) ? sanitize_key( $form_meta['id'] ) : '';
		$data['_form_name'] = isset( $form_meta['name'] ) ? $form_meta['name'] : __( 'Elementor Form', 'b24-leads-wp' );

		do_action( 'b24_leads_wp_send_lead', $data );
	}

	/**
	 * Преобразует поля Elementor в универсальный массив для B24.
	 * Маппинг по типу поля (email, tel, textarea) и по label/title.
	 *
	 * @param array $fields Элементы с ключами value, type, title/label.
	 * @return array
	 */
	private function map_fields_to_b24_data( $fields ) {
		$data = array();
		$name_labels  = array( 'name', 'имя', 'fio', 'your name', 'ваше имя' );
		$phone_labels = array( 'phone', 'tel', 'телефон', 'телефон number' );
		$email_labels = array( 'email', 'e-mail', 'mail', 'почта' );
		$msg_labels   = array( 'message', 'comment', 'сообщение', 'comments', 'ваше сообщение' );

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) || empty( $field['value'] ) ) {
				continue;
			}
			$value = is_string( $field['value'] ) ? trim( $field['value'] ) : '';
			if ( $value === '' ) {
				continue;
			}
			$type  = isset( $field['type'] ) ? strtolower( $field['type'] ) : '';
			$title = isset( $field['title'] ) ? strtolower( trim( $field['title'] ) ) : ( isset( $field['label'] ) ? strtolower( trim( $field['label'] ) ) : '' );

			if ( $type === 'email' || $this->label_matches( $title, $email_labels ) ) {
				$data['email'] = $value;
			} elseif ( $type === 'tel' || $this->label_matches( $title, $phone_labels ) ) {
				$data['phone'] = $value;
			} elseif ( $type === 'textarea' || $this->label_matches( $title, $msg_labels ) ) {
				$data['message'] = $value;
			} elseif ( $type === 'text' && ( $this->label_matches( $title, $name_labels ) || empty( $data['name'] ) ) ) {
				if ( empty( $data['name'] ) ) {
					$data['name'] = $value;
				}
			} else {
				$key = sanitize_key( $title ?: $type );
				if ( $key !== '' && ! isset( $data[ $key ] ) ) {
					$data[ $key ] = $value;
				}
			}
		}

		// Заголовок заявки: имя или «Заявка с сайта»
		if ( empty( $data['title'] ) && ! empty( $data['name'] ) ) {
			$data['title'] = $data['name'];
		}

		return apply_filters( 'b24_leads_wp_elementor_data', $data, $fields );
	}

	/**
	 * @param string $label
	 * @param array  $keywords
	 * @return bool
	 */
	private function label_matches( $label, $keywords ) {
		if ( $label === '' ) {
			return false;
		}
		foreach ( $keywords as $kw ) {
			if ( strpos( $label, $kw ) !== false ) {
				return true;
			}
		}
		return false;
	}
}
