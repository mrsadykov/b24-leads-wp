<?php
/**
 * Отправка данных в Битрикс24 по входящему вебхуку.
 *
 * @package B24_Leads_WP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class B24_Leads_Sender
 */
class B24_Leads_Sender {

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
		add_action( 'b24_leads_wp_send_lead', array( $this, 'handle_send_lead' ), 10, 1 );
	}

	/**
	 * Получить базовый URL вебхука (без метода).
	 *
	 * @return string
	 */
	public static function get_webhook_url() {
		$url = get_option( 'b24_leads_wp_webhook_url', '' );
		$url = trim( $url );
		$url = rtrim( $url, "/ \t\n\r" );
		// Убираем случайно попавший в конец метод или .json
		$url = preg_replace( '#(/\\.json|/crm\\.(lead|deal)\\.add)$#i', '', $url );
		return $url;
	}

	/**
	 * Тип сущности в CRM: lead или deal.
	 *
	 * @return string
	 */
	public static function get_entity_type() {
		$type = get_option( 'b24_leads_wp_entity_type', 'lead' );
		return in_array( $type, array( 'lead', 'deal' ), true ) ? $type : 'lead';
	}

	/**
	 * Этап сделки (STAGE_ID) для crm.deal.add. Пустая строка = B24 подставит этап по умолчанию.
	 *
	 * @return string
	 */
	public static function get_deal_stage_id() {
		return trim( (string) get_option( 'b24_leads_wp_deal_stage_id', '' ) );
	}

	/**
	 * Создавать контакт в B24 перед лидом/сделкой и привязывать к нему.
	 *
	 * @return bool
	 */
	public static function get_create_contact_option() {
		return (bool) get_option( 'b24_leads_wp_create_contact', false );
	}

	/**
	 * Маппинг полей формы → поля B24 (из настроек).
	 *
	 * @return array
	 */
	public static function get_field_mapping() {
		$defaults = array(
			'name'     => 'NAME',
			'phone'    => 'PHONE',
			'email'    => 'EMAIL',
			'message'  => 'COMMENTS',
			'title'    => 'TITLE',
		);
		$saved = get_option( 'b24_leads_wp_field_mapping', array() );
		$out   = wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
		$extra = get_option( 'b24_leads_wp_field_mapping_extra', array() );
		if ( is_array( $extra ) ) {
			foreach ( $extra as $row ) {
				if ( ! empty( $row['form'] ) && ! empty( $row['b24'] ) ) {
					$out[ $row['form'] ] = $row['b24'];
				}
			}
		}
		return $out;
	}

	/**
	 * Обработчик действия отправки лида.
	 * Расширения (Pro) могут менять webhook, entity, маппинг и поля через фильтры; в $data можно передать _form_type, _form_id для привязки к форме.
	 *
	 * @param array $data Данные формы: name, phone, email, message, title, utm_* ... Опционально _form_type, _form_id (не уходят в B24).
	 */
	public function handle_send_lead( $data ) {
		if ( ! is_array( $data ) ) {
			return;
		}

		$webhook = apply_filters( 'b24_leads_wp_webhook_url', self::get_webhook_url(), $data );
		if ( empty( $webhook ) ) {
			B24_Leads_Logger::log( 'skip', __( 'Вебхук не настроен. Заявка не отправлена.', 'b24-leads-wp' ), array() );
			return;
		}

		$entity = apply_filters( 'b24_leads_wp_entity_type', self::get_entity_type(), $data );
		$entity = in_array( $entity, array( 'lead', 'deal' ), true ) ? $entity : 'lead';
		$stage_id = apply_filters( 'b24_leads_wp_deal_stage_id', self::get_deal_stage_id(), $data );
		$method = $entity === 'deal' ? 'crm.deal.add' : 'crm.lead.add';
		$fields = $this->build_b24_fields( $data );

		if ( $entity === 'deal' && is_string( $stage_id ) && $stage_id !== '' ) {
			$fields['STAGE_ID'] = $stage_id;
		}

		if ( apply_filters( 'b24_leads_wp_create_contact', self::get_create_contact_option(), $data ) ) {
			$contact_id = $this->create_contact_in_b24( $webhook, $data );
			if ( $contact_id ) {
				if ( $entity === 'deal' ) {
					$fields['CONTACT_IDS'] = array( (int) $contact_id );
				} else {
					$fields['CONTACT_ID'] = (int) $contact_id;
				}
			}
		}

		$fields = apply_filters( 'b24_leads_wp_b24_fields', $fields, $data );
		if ( empty( $fields ) ) {
			B24_Leads_Logger::log( 'skip', __( 'Нет данных для отправки (пустые поля по маппингу).', 'b24-leads-wp' ), array( 'method' => $method ) );
			return;
		}

		$this->call_b24_rest( $webhook, $method, $fields );
	}

	/**
	 * Создать контакт в B24 (crm.contact.add). Возвращает ID контакта или null.
	 *
	 * @param string $webhook_base Базовый URL вебхука.
	 * @param array  $data         Данные формы (name, phone, email и т.д.).
	 * @return int|null
	 */
	private function create_contact_in_b24( $webhook_base, $data ) {
		$data = array_change_key_case( is_array( $data ) ? $data : array(), CASE_LOWER );
		$name  = trim( (string) ( isset( $data['name'] ) ? $data['name'] : '' ) );
		$phone = trim( (string) ( isset( $data['phone'] ) ? $data['phone'] : '' ) );
		$email = trim( (string) ( isset( $data['email'] ) ? $data['email'] : '' ) );

		if ( $name === '' ) {
			$name = $email !== '' ? $email : ( $phone !== '' ? $phone : __( 'Контакт с сайта', 'b24-leads-wp' ) );
		}

		$fields = array( 'NAME' => $name );
		if ( $phone !== '' ) {
			$fields['PHONE'] = array( array( 'VALUE' => $phone, 'VALUE_TYPE' => 'WORK' ) );
		}
		if ( $email !== '' ) {
			$fields['EMAIL'] = array( array( 'VALUE' => $email, 'VALUE_TYPE' => 'WORK' ) );
		}

		$url = trailingslashit( $webhook_base ) . 'crm.contact.add';
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array( 'fields' => $fields ) ),
			)
		);

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$decoded = json_decode( $body, true );
		$contact_id = isset( $decoded['result'] ) ? $decoded['result'] : null;

		if ( $code === 200 && $contact_id ) {
			return (int) $contact_id;
		}
		if ( $code !== 200 && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'B24 Leads WP: crm.contact.add failed ' . $code . ' — ' . $body );
		}
		return null;
	}

	/**
	 * Преобразует данные формы в поля B24 по маппингу.
	 * Ключи $data, начинающиеся с _, не попадают в B24. Маппинг можно изменить через фильтр (для Pro).
	 *
	 * @param array $data
	 * @return array
	 */
	private function build_b24_fields( $data ) {
		$mapping = apply_filters( 'b24_leads_wp_field_mapping', self::get_field_mapping(), $data );
		$data    = array_change_key_case( $data, CASE_LOWER );
		$out     = array();

		foreach ( $mapping as $form_key => $b24_key ) {
			if ( empty( $b24_key ) || strpos( (string) $form_key, '_' ) === 0 ) {
				continue;
			}
			if ( ! isset( $data[ $form_key ] ) ) {
				continue;
			}
			$value = trim( (string) $data[ $form_key ] );
			if ( $value === '' ) {
				continue;
			}

			// B24 ожидает PHONE/EMAIL как массив элементов [ ["VALUE" => "...", "VALUE_TYPE" => "WORK" ] ]
			if ( $b24_key === 'PHONE' ) {
				$out['PHONE'] = array( array( 'VALUE' => $value, 'VALUE_TYPE' => 'WORK' ) );
				continue;
			}
			if ( $b24_key === 'EMAIL' ) {
				$out['EMAIL'] = array( array( 'VALUE' => $value, 'VALUE_TYPE' => 'WORK' ) );
				continue;
			}

			$out[ $b24_key ] = $value;
		}

		// Заголовок лида/сделки: если не передан, формируем «Заявка с сайта — Имя» (в списке B24 сразу видно, что это заявка)
		if ( empty( $out['TITLE'] ) ) {
			$base = __( 'Заявка с сайта', 'b24-leads-wp' );
			if ( ! empty( $data['name'] ) ) {
				$base .= ' — ' . trim( (string) $data['name'] );
			}
			if ( ! empty( $data['utm_source'] ) ) {
				$base .= ' (' . trim( (string) $data['utm_source'] ) . ')';
			}
			$out['TITLE'] = $base;
		}

		// UTM в комментарий или в описание
		$utm = $this->collect_utm( $data );
		if ( $utm !== '' && ! empty( $out['COMMENTS'] ) ) {
			$out['COMMENTS'] .= "\n" . $utm;
		} elseif ( $utm !== '' ) {
			$out['COMMENTS'] = $utm;
		}

		// Поля формы с ключом UF_CRM_* автоматически уходят в B24 как пользовательские поля (без добавления в маппинг). Ключи с _ не отправляем.
		foreach ( $data as $form_key => $value ) {
			if ( strpos( (string) $form_key, '_' ) === 0 ) {
				continue;
			}
			if ( strpos( $form_key, 'uf_crm_' ) === 0 && is_string( $value ) && trim( $value ) !== '' ) {
				$out[ strtoupper( $form_key ) ] = trim( $value );
			}
		}

		return $out;
	}

	/**
	 * Собрать строку UTM из данных.
	 *
	 * @param array $data
	 * @return string
	 */
	private function collect_utm( $data ) {
		$keys = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term' );
		$pairs = array();
		foreach ( $keys as $key ) {
			if ( ! empty( $data[ $key ] ) ) {
				$pairs[] = $key . ': ' . trim( (string) $data[ $key ] );
			}
		}
		return implode( ', ', $pairs );
	}

	/**
	 * Вызов REST API Битрикс24.
	 *
	 * @param string $webhook_base Базовый URL вебхука (без метода).
	 * @param string $method       crm.lead.add или crm.deal.add
	 * @param array  $fields
	 */
	private function call_b24_rest( $webhook_base, $method, $fields ) {
		// B24: POST с JSON на URL без .json (документация apidocs.bitrix24.com)
		$url = trailingslashit( $webhook_base ) . $method;

		$body = array( 'fields' => $fields );
		$body_json = wp_json_encode( $body );

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => $body_json,
			)
		);

		$code         = wp_remote_retrieve_response_code( $response );
		$body_response = wp_remote_retrieve_body( $response );

		// Сохраняем последний ответ для отображения в настройках (диагностика)
		update_option( 'b24_leads_wp_last_response', array(
			'time'   => current_time( 'mysql' ),
			'code'   => $code,
			'body'   => $body_response,
			'method' => $method,
		), false );

		$decoded = json_decode( $body_response, true );
		$result_id = isset( $decoded['result'] ) ? $decoded['result'] : null;
		$error_msg = isset( $decoded['error_description'] ) ? $decoded['error_description'] : ( isset( $decoded['error'] ) ? $decoded['error'] : '' );

		if ( $code === 200 && $result_id !== null ) {
			B24_Leads_Logger::log( 'success', sprintf( __( 'Создан лид/сделка в B24 (ID: %s)', 'b24-leads-wp' ), $result_id ), array( 'method' => $method, 'id' => $result_id ) );
		} else {
			$log_message = sprintf( __( 'Ошибка B24: HTTP %s', 'b24-leads-wp' ), $code );
			if ( $code === 401 || ( $error_msg && stripos( $error_msg, 'credential' ) !== false ) ) {
				$log_message = __( 'Неверные учётные данные вебхука. Создайте новый вебхук в B24 и вставьте его URL в настройках плагина.', 'b24-leads-wp' );
			}
			B24_Leads_Logger::log( 'error', $log_message, array( 'method' => $method, 'code' => $code, 'response' => $body_response, 'error' => $error_msg ) );
		}
	}
}
