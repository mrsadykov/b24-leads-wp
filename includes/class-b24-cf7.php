<?php
/**
 * Интеграция с Contact Form 7: отправка заявок в Битрикс24.
 *
 * @package B24_Leads_WP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class B24_Leads_CF7
 */
class B24_Leads_CF7 {

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
		add_action( 'wpcf7_mail_sent', array( $this, 'on_cf7_mail_sent' ), 10, 1 );
	}

	/**
	 * После успешной отправки формы CF7 — отправить данные в B24.
	 *
	 * @param WPCF7_ContactForm $contact_form
	 */
	public function on_cf7_mail_sent( $contact_form ) {
		$submission = WPCF7_Submission::get_instance();
		if ( ! $submission ) {
			return;
		}

		$posted = $submission->get_posted_data();
		if ( empty( $posted ) ) {
			return;
		}

		$data = $this->map_cf7_to_b24_data( $posted );
		if ( empty( $data ) ) {
			return;
		}
		$data['_form_type'] = 'cf7';
		$data['_form_id']   = $contact_form->id();
		$data['_form_name'] = $contact_form->title();

		do_action( 'b24_leads_wp_send_lead', $data );
	}

	/**
	 * Преобразует данные CF7 в универсальный массив для B24.
	 * Поддерживаем типичные имена полей CF7: your-name, your-email, your-phone, your-message и др.
	 *
	 * @param array $posted
	 * @return array
	 */
	private function map_cf7_to_b24_data( $posted ) {
		$data = array();

		// Типичные имена полей CF7 (можно расширить через фильтр).
		$name_keys    = array( 'your-name', 'name', 'имя', 'fio' );
		$phone_keys   = array( 'your-phone', 'phone', 'tel', 'телефон' );
		$email_keys   = array( 'your-email', 'email', 'e-mail', 'mail' );
		$message_keys = array( 'your-message', 'message', 'comment', 'сообщение', 'comments' );
		$title_keys   = array( 'your-subject', 'subject', 'тема', 'title' );

		$data['name']    = $this->first_non_empty( $posted, $name_keys );
		$data['phone']   = $this->first_non_empty( $posted, $phone_keys );
		$data['email']   = $this->first_non_empty( $posted, $email_keys );
		$data['message'] = $this->first_non_empty( $posted, $message_keys );
		$data['title']   = $this->first_non_empty( $posted, $title_keys );

		// UTM из полей или из реферера/куки (если форма передаёт)
		$utm_keys = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term' );
		foreach ( $utm_keys as $key ) {
			if ( ! empty( $posted[ $key ] ) ) {
				$data[ $key ] = $posted[ $key ];
			}
		}

		// Все остальные поля добавить как есть (нижний регистр ключа) для гибкого маппинга
		foreach ( $posted as $key => $value ) {
			if ( is_string( $value ) && $value !== '' && ! isset( $data[ $key ] ) ) {
				$data[ $key ] = $value;
			}
		}

		return apply_filters( 'b24_leads_wp_cf7_data', $data, $posted );
	}

	/**
	 * @param array $posted
	 * @param array $keys
	 * @return string
	 */
	private function first_non_empty( $posted, $keys ) {
		foreach ( $keys as $key ) {
			if ( ! empty( $posted[ $key ] ) && is_string( $posted[ $key ] ) ) {
				return trim( $posted[ $key ] );
			}
		}
		return '';
	}
}
