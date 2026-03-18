<?php
/**
 * Админ-страница настроек: вебхук B24, тип сущности, маппинг полей.
 *
 * @package B24_Leads_WP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class B24_Leads_Admin
 */
class B24_Leads_Admin {

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
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'handle_clear_log' ) );
		add_action( 'admin_init', array( $this, 'handle_reset_mapping' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Обработка запроса на очистку журнала.
	 */
	public function handle_clear_log() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( empty( $_POST['b24_leads_wp_clear_log'] ) || empty( $_POST['_wpnonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'b24_leads_wp_clear_log' ) ) {
			return;
		}
		B24_Leads_Logger::clear();
		$url = add_query_arg(
			array(
				'b24_leads_wp_log_cleared' => '1',
				'_wpnonce'                 => wp_create_nonce( 'b24_log_cleared' ),
			),
			menu_page_url( 'b24-leads', false )
		);
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Сброс маппинга полей: стандартные — по умолчанию, дополнительные — пусто.
	 */
	public function handle_reset_mapping() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( empty( $_POST['b24_leads_wp_reset_mapping'] ) || empty( $_POST['_wpnonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'b24_leads_wp_reset_mapping' ) ) {
			return;
		}
		$defaults = array(
			'name'    => 'NAME',
			'phone'   => 'PHONE',
			'email'   => 'EMAIL',
			'message' => 'COMMENTS',
			'title'   => 'TITLE',
		);
		update_option( 'b24_leads_wp_field_mapping', $defaults );
		update_option( 'b24_leads_wp_field_mapping_extra', array() );
		$url = add_query_arg(
			array(
				'b24_leads_wp_mapping_reset' => '1',
				'_wpnonce'                   => wp_create_nonce( 'b24_mapping_reset' ),
			),
			menu_page_url( 'b24-leads', false )
		);
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Пункт меню в настройках.
	 */
	public function add_menu() {
		add_options_page(
			__( 'Заявки в Битрикс24', 'b24-leads' ),
			__( 'B24 Заявки', 'b24-leads' ),
			'manage_options',
			'b24-leads',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Регистрация настроек.
	 */
	public function register_settings() {
		register_setting(
			'b24_leads_wp_settings',
			'b24_leads_wp_webhook_url',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_webhook_url' ),
			)
		);

		register_setting(
			'b24_leads_wp_settings',
			'b24_leads_wp_entity_type',
			array(
				'type'              => 'string',
				'sanitize_callback' => function ( $v ) {
					return in_array( $v, array( 'lead', 'deal' ), true ) ? $v : 'lead';
				},
			)
		);

		register_setting(
			'b24_leads_wp_settings',
			'b24_leads_wp_field_mapping',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_field_mapping' ),
			)
		);

		register_setting(
			'b24_leads_wp_settings',
			'b24_leads_wp_deal_category_id',
			array(
				'type'              => 'integer',
				'sanitize_callback' => function ( $v ) {
					return absint( $v );
				},
			)
		);

		register_setting(
			'b24_leads_wp_settings',
			'b24_leads_wp_deal_stage_id',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_setting(
			'b24_leads_wp_settings',
			'b24_leads_wp_field_mapping_extra',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_extra_mapping' ),
			)
		);

		register_setting(
			'b24_leads_wp_settings',
			'b24_leads_wp_create_contact',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'wp_validate_boolean',
			)
		);
	}

	/**
	 * Санитизация дополнительных полей маппинга (пользовательские пары форма → B24).
	 * Берём данные из $_POST напрямую: options.php иногда передаёт в callback не тот массив.
	 *
	 * @param array $mapping Значение, переданное WordPress (может быть пустым или некорректным).
	 * @return array
	 */
	public function sanitize_extra_mapping( $mapping ) {
		$existing = get_option( 'b24_leads_wp_field_mapping_extra', array() );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return $existing;
		}
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'b24_leads_wp_settings-options' ) ) {
			return $existing;
		}

		if ( isset( $_POST['b24_leads_wp_field_mapping_extra'] ) && is_array( $_POST['b24_leads_wp_field_mapping_extra'] ) ) {
			$mapping = map_deep( wp_unslash( $_POST['b24_leads_wp_field_mapping_extra'] ), 'sanitize_text_field' );
		} else {
			$mapping = array();
		}

		if ( ! is_array( $mapping ) ) {
			return $existing;
		}
		if ( empty( $mapping ) && ! empty( $existing ) ) {
			return $existing;
		}

		$out = array();
		foreach ( $mapping as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			// Ключ формы: разрешаем кириллицу, пробелы, латиницу, цифры (как в конструкторах CF7/WPForms).
			$form_key = isset( $row['form'] ) ? $this->sanitize_extra_mapping_form_key( trim( (string) $row['form'] ) ) : '';
			// Поле B24: буквы, цифры, подчёркивание (в т.ч. UF_CRM_*).
			$b24_key  = isset( $row['b24'] ) ? preg_replace( '/[^A-Z0-9_]/', '', strtoupper( trim( (string) $row['b24'] ) ) ) : '';
			if ( $form_key !== '' && $b24_key !== '' ) {
				$out[] = array( 'form' => $form_key, 'b24' => $b24_key );
			}
		}

		// Не затирать сохранённые данные пустым результатом (потеря данных из формы и т.п.).
		if ( empty( $out ) && ! empty( $existing ) ) {
			return $existing;
		}
		return $out;
	}

	/**
	 * Санитизация ключа поля формы для доп. маппинга: кириллица, пробелы, латиница, цифры.
	 *
	 * @param string $key
	 * @return string
	 */
	private function sanitize_extra_mapping_form_key( $key ) {
		$key = trim( (string) $key );
		if ( $key === '' ) {
			return '';
		}
		// Буквы (в т.ч. кириллица), цифры, пробел, подчёркивание, дефис.
		$key = preg_replace( '/[^\p{L}\p{N}\s_\-]/u', '', $key );
		return substr( $key, 0, 200 );
	}

	/**
	 * @param string $url
	 * @return string
	 */
	public function sanitize_webhook_url( $url ) {
		$url = trim( (string) $url );
		$url = esc_url_raw( $url, array( 'https' ) );
		return $url;
	}

	/**
	 * @param array $mapping
	 * @return array
	 */
	public function sanitize_field_mapping( $mapping ) {
		if ( ! is_array( $mapping ) ) {
			return array();
		}
		$allowed_b24 = array( 'TITLE', 'NAME', 'LAST_NAME', 'PHONE', 'EMAIL', 'COMMENTS', 'SOURCE_ID', 'SOURCE_DESCRIPTION' );
		$out = array();
		foreach ( $mapping as $form_key => $b24_key ) {
			$form_key = sanitize_text_field( $form_key );
			$b24_key  = strtoupper( sanitize_text_field( $b24_key ) );
			if ( $form_key !== '' && ( $b24_key === '' || in_array( $b24_key, $allowed_b24, true ) ) ) {
				$out[ $form_key ] = $b24_key;
			}
		}
		return $out;
	}

	/**
	 * Подключение стилей только на странице плагина.
	 *
	 * @param string $hook
	 */
	public function enqueue_assets( $hook ) {
		if ( $hook !== 'settings_page_b24-leads' ) {
			return;
		}
		wp_add_inline_style( 'wp-admin', '
			.b24-leads-wp-table input[type="text"] { width: 100%; max-width: 220px; }
			.b24-leads-wp-table th { width: 140px; }
			.b24-leads-wp-webhook { max-width: 480px; }
		' );
		wp_add_inline_script( 'jquery', "
			jQuery(function($) {
				var row = $('.b24-leads-wp-deal-option');
				var radios = $('input[name=b24_leads_wp_entity_type]');
				function updateRow() {
					row.css('opacity', radios.filter(':checked').val() === 'deal' ? '1' : '0.6');
				}
				radios.on('change', updateRow);
				updateRow();
				var mappingTable = $('.b24-leads-wp-mapping-table tbody');
				var templateRow = mappingTable.find('tr.b24-extra-row-template');
				function reindexExtraRows() {
					mappingTable.find('tr.b24-extra-row:not(.b24-extra-row-template)').each(function(i) {
						$(this).find('.b24-extra-input-form').attr('name', 'b24_leads_wp_field_mapping_extra[' + i + '][form]');
						$(this).find('.b24-extra-input-b24').attr('name', 'b24_leads_wp_field_mapping_extra[' + i + '][b24]');
					});
				}
				$('.b24-add-extra-row').on('click', function() {
					var clone = templateRow.clone().removeClass('b24-extra-row-template').show();
					var n = mappingTable.find('tr.b24-extra-row:not(.b24-extra-row-template)').length;
					clone.find('.b24-extra-input-form').attr('name', 'b24_leads_wp_field_mapping_extra[' + n + '][form]');
					clone.find('.b24-extra-input-b24').attr('name', 'b24_leads_wp_field_mapping_extra[' + n + '][b24]');
					templateRow.before(clone);
				});
				mappingTable.on('click', '.b24-remove-extra-row', function() {
					$(this).closest('tr.b24-extra-row').not('.b24-extra-row-template').remove();
					reindexExtraRows();
				});
			});
		" );
	}

	/**
	 * Вывод страницы настроек.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$webhook_url = get_option( 'b24_leads_wp_webhook_url', '' );
		$entity_type = get_option( 'b24_leads_wp_entity_type', 'lead' );
		$mapping     = get_option( 'b24_leads_wp_field_mapping', array() );
		$defaults    = array(
			'name'     => 'NAME',
			'phone'    => 'PHONE',
			'email'    => 'EMAIL',
			'message'  => 'COMMENTS',
			'title'    => 'TITLE',
		);
		$mapping = wp_parse_args( is_array( $mapping ) ? $mapping : array(), $defaults );

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php
			$log_cleared = isset( $_GET['b24_leads_wp_log_cleared'] ) && isset( $_GET['_wpnonce'] )
				&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'b24_log_cleared' )
				&& sanitize_text_field( wp_unslash( $_GET['b24_leads_wp_log_cleared'] ) ) === '1';
			$mapping_reset = isset( $_GET['b24_leads_wp_mapping_reset'] ) && isset( $_GET['_wpnonce'] )
				&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'b24_mapping_reset' )
				&& sanitize_text_field( wp_unslash( $_GET['b24_leads_wp_mapping_reset'] ) ) === '1';
			?>
			<?php if ( $log_cleared ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Журнал отправок очищен.', 'b24-leads' ); ?></p></div>
			<?php endif; ?>
			<?php if ( $mapping_reset ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Маппинг полей сброшен: стандартные поля — по умолчанию, дополнительные — удалены.', 'b24-leads' ); ?></p></div>
			<?php endif; ?>

			<p><?php esc_html_e( 'Заявки с форм сайта будут отправляться в Битрикс24 как лиды или сделки. Настройте входящий вебхук в B24 и укажите его URL ниже.', 'b24-leads' ); ?></p>

			<form action="options.php" method="post">
				<?php settings_fields( 'b24_leads_wp_settings' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="b24_leads_wp_webhook_url"><?php esc_html_e( 'URL входящего вебхука B24', 'b24-leads' ); ?></label>
						</th>
						<td>
							<input type="url"
								   id="b24_leads_wp_webhook_url"
								   name="b24_leads_wp_webhook_url"
								   value="<?php echo esc_attr( $webhook_url ); ?>"
								   class="regular-text b24-leads-wp-webhook"
								   placeholder="https://ваш-портал.bitrix24.ru/rest/1/xxxxxxxxxx/"
							/>
							<p class="description">
								<?php esc_html_e( 'Создайте вебхук в Битрикс24: Настройки → Инструменты → Разработчикам → Входящий вебхук. Выберите права CRM. Скопируйте URL (без имени метода в конце).', 'b24-leads' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Куда отправлять', 'b24-leads' ); ?></th>
						<td>
							<label>
								<input type="radio" name="b24_leads_wp_entity_type" value="lead" <?php checked( $entity_type, 'lead' ); ?> />
								<?php esc_html_e( 'Лиды (crm.lead.add)', 'b24-leads' ); ?>
							</label>
							&nbsp;&nbsp;
							<label>
								<input type="radio" name="b24_leads_wp_entity_type" value="deal" <?php checked( $entity_type, 'deal' ); ?> />
								<?php esc_html_e( 'Сделки (crm.deal.add)', 'b24-leads' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Создавать контакт в B24', 'b24-leads' ); ?></th>
						<td>
							<input type="hidden" name="b24_leads_wp_create_contact" value="0" />
							<label>
								<input type="checkbox" name="b24_leads_wp_create_contact" value="1" <?php checked( get_option( 'b24_leads_wp_create_contact', false ) ); ?> />
								<?php esc_html_e( 'Сначала создавать контакт (crm.contact.add), затем привязывать его к лиду или сделке', 'b24-leads' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'В B24 появится контакт с именем, телефоном и email из заявки; лид/сделка будет связан с этим контактом.', 'b24-leads' ); ?></p>
						</td>
					</tr>
					<tr class="b24-leads-wp-deal-option" style="<?php echo $entity_type !== 'deal' ? 'opacity: 0.6;' : ''; ?>">
						<th scope="row">
							<label for="b24_leads_wp_deal_category_id"><?php esc_html_e( 'ID воронки (CATEGORY_ID)', 'b24-leads' ); ?></label>
						</th>
						<td>
							<input type="number"
								   id="b24_leads_wp_deal_category_id"
								   name="b24_leads_wp_deal_category_id"
								   value="<?php echo esc_attr( (string) get_option( 'b24_leads_wp_deal_category_id', 0 ) ); ?>"
								   min="0"
								   step="1"
								   class="small-text"
							/>
							<p class="description">
								<?php esc_html_e( 'Только для сделок. 0 = воронка по умолчанию. Чтобы заявки попадали в нужную воронку, укажите её ID (1, 2, 3…). Как узнать ID и код этапа — см. раздел 1.3 в документации (файл docs/VERIFY-FREE-PLUGIN.md).', 'b24-leads' ); ?>
							</p>
						</td>
					</tr>
					<tr class="b24-leads-wp-deal-option" style="<?php echo $entity_type !== 'deal' ? 'opacity: 0.6;' : ''; ?>">
						<th scope="row">
							<label for="b24_leads_wp_deal_stage_id"><?php esc_html_e( 'Этап сделки (STAGE_ID)', 'b24-leads' ); ?></label>
						</th>
						<td>
							<input type="text"
								   id="b24_leads_wp_deal_stage_id"
								   name="b24_leads_wp_deal_stage_id"
								   value="<?php echo esc_attr( get_option( 'b24_leads_wp_deal_stage_id', '' ) ); ?>"
								   class="regular-text"
								   placeholder="NEW или C1:NEW"
							/>
							<p class="description">
								<?php esc_html_e( 'Только для сделок. Код этапа воронки. Пусто = первый этап по умолчанию. Для воронки по умолчанию: NEW, PREPARATION; для своей воронки: C1:NEW, C2:PREPARATION (цифра = ID воронки). Как узнать коды — см. раздел 1.3 в docs/VERIFY-FREE-PLUGIN.md.', 'b24-leads' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Маппинг полей формы → Битрикс24', 'b24-leads' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Укажите, как имена полей из формы соответствуют полям CRM. Ниже — стандартные поля и уже добавленные дополнительные; пустые пары при сохранении не сохраняются. После добавления или удаления строк нажмите «Сохранить изменения». Поля в формах создаются в конструкторе (CF7, WPForms и т.д.); здесь задаётся только маппинг.', 'b24-leads' ); ?></p>
				<table class="form-table b24-leads-wp-table b24-leads-wp-mapping-table" role="presentation">
					<thead>
						<tr>
							<th style="width: 180px;"><?php esc_html_e( 'Ключ в форме / поле', 'b24-leads' ); ?></th>
							<th><?php esc_html_e( 'Поле в Битрикс24', 'b24-leads' ); ?></th>
							<th style="width: 80px;"><?php esc_html_e( 'Действие', 'b24-leads' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$form_labels = array(
							'name'     => __( 'Имя (форма)', 'b24-leads' ),
							'phone'    => __( 'Телефон', 'b24-leads' ),
							'email'    => __( 'Email', 'b24-leads' ),
							'message'  => __( 'Сообщение', 'b24-leads' ),
							'title'    => __( 'Заголовок заявки', 'b24-leads' ),
						);
						foreach ( $form_labels as $form_key => $label ) :
							$b24_val = isset( $mapping[ $form_key ] ) ? $mapping[ $form_key ] : '';
							?>
							<tr class="b24-mapping-standard-row">
								<td><?php echo esc_html( $label ); ?></td>
								<td>
									<input type="text"
										   name="b24_leads_wp_field_mapping[<?php echo esc_attr( $form_key ); ?>]"
										   value="<?php echo esc_attr( $b24_val ); ?>"
										   placeholder="<?php echo esc_attr( $form_key === 'name' ? 'NAME' : ( $form_key === 'phone' ? 'PHONE' : ( $form_key === 'email' ? 'EMAIL' : ( $form_key === 'message' ? 'COMMENTS' : 'TITLE' ) ) ) ); ?>"
									/>
								</td>
								<td></td>
							</tr>
						<?php endforeach; ?>
						<?php
						$extra = get_option( 'b24_leads_wp_field_mapping_extra', array() );
						if ( ! is_array( $extra ) ) {
							$extra = array();
						}
						// Всегда выводим сохранённые строки; затем одну пустую для добавления новой пары (без кнопки «Добавить поле»).
						if ( ! empty( $extra ) ) {
							$extra[] = array( 'form' => '', 'b24' => '' );
						} else {
							$extra = array( array( 'form' => '', 'b24' => '' ) );
						}
						foreach ( $extra as $i => $row ) :
							$f = isset( $row['form'] ) ? $row['form'] : '';
							$b = isset( $row['b24'] ) ? $row['b24'] : '';
							?>
							<tr class="b24-extra-row">
								<td><input type="text" class="b24-extra-input-form" name="b24_leads_wp_field_mapping_extra[<?php echo (int) $i; ?>][form]" value="<?php echo esc_attr( $f ); ?>" placeholder="company" /></td>
								<td><input type="text" class="b24-extra-input-b24" name="b24_leads_wp_field_mapping_extra[<?php echo (int) $i; ?>][b24]" value="<?php echo esc_attr( $b ); ?>" placeholder="COMPANY_TITLE" /></td>
								<td><button type="button" class="button button-small b24-remove-extra-row"><?php esc_html_e( 'Удалить', 'b24-leads' ); ?></button></td>
							</tr>
						<?php endforeach; ?>
						<tr class="b24-extra-row b24-extra-row-template" style="display:none;">
							<td><input type="text" name="" value="" placeholder="company" class="b24-extra-input-form" /></td>
							<td><input type="text" name="" value="" placeholder="COMPANY_TITLE" class="b24-extra-input-b24" /></td>
							<td><button type="button" class="button button-small b24-remove-extra-row"><?php esc_html_e( 'Удалить', 'b24-leads' ); ?></button></td>
						</tr>
					</tbody>
				</table>
				<p><button type="button" class="button b24-add-extra-row"><?php esc_html_e( 'Добавить поле', 'b24-leads' ); ?></button></p>

				<?php submit_button(); ?>
			</form>
			<p class="description">
				<?php esc_html_e( 'Сбросить весь маппинг (стандартные поля — по умолчанию, дополнительные — удалить):', 'b24-leads' ); ?>
				<form method="post" style="display:inline; margin-left: 6px;" onsubmit="return confirm('<?php echo esc_js( __( 'Сбросить весь маппинг (стандартные — по умолчанию, дополнительные — удалить)?', 'b24-leads' ) ); ?>');">
					<?php wp_nonce_field( 'b24_leads_wp_reset_mapping', '_wpnonce' ); ?>
					<input type="hidden" name="b24_leads_wp_reset_mapping" value="1" />
					<?php submit_button( __( 'Сбросить маппинг', 'b24-leads' ), 'secondary', 'submit', false ); ?>
				</form>
			</p>

			<?php
			$last = get_option( 'b24_leads_wp_last_response', null );
			if ( $last && ! empty( $last['time'] ) ) :
				$is_ok = isset( $last['code'] ) && (int) $last['code'] === 200;
				?>
				<hr />
				<h2><?php esc_html_e( 'Последний ответ Битрикс24 (диагностика)', 'b24-leads' ); ?></h2>
				<p><strong><?php esc_html_e( 'Время запроса:', 'b24-leads' ); ?></strong> <?php echo esc_html( $last['time'] ); ?>
					| <strong><?php esc_html_e( 'Метод:', 'b24-leads' ); ?></strong> <?php echo esc_html( isset( $last['method'] ) ? $last['method'] : '-' ); ?>
					| <strong><?php esc_html_e( 'HTTP-код:', 'b24-leads' ); ?></strong>
					<span style="color: <?php echo $is_ok ? 'green' : 'red'; ?>;"><?php echo esc_html( isset( $last['code'] ) ? $last['code'] : '-' ); ?></span>
				</p>
				<?php if ( ! empty( $last['body'] ) ) : ?>
					<details>
						<summary><?php esc_html_e( 'Тело ответа', 'b24-leads' ); ?></summary>
						<pre style="background: #f5f5f5; padding: 10px; overflow: auto; max-height: 200px;"><?php echo esc_html( $last['body'] ); ?></pre>
					</details>
				<?php endif; ?>
				<p class="description"><?php esc_html_e( 'Отправьте форму на сайте и обновите эту страницу — здесь появится ответ от B24. Код 200 и result с id лида означают успех.', 'b24-leads' ); ?></p>
			<?php endif; ?>

			<hr />
			<h2><?php esc_html_e( 'Журнал отправок', 'b24-leads' ); ?></h2>
			<?php
			$log_entries = B24_Leads_Logger::get_entries( 50 );
			if ( ! empty( $log_entries ) ) :
				?>
				<form method="post" style="margin-bottom: 12px;">
					<?php wp_nonce_field( 'b24_leads_wp_clear_log', '_wpnonce' ); ?>
					<input type="hidden" name="b24_leads_wp_clear_log" value="1" />
					<?php submit_button( __( 'Очистить журнал', 'b24-leads' ), 'secondary', 'submit', false ); ?>
				</form>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th style="width: 140px;"><?php esc_html_e( 'Время', 'b24-leads' ); ?></th>
							<th style="width: 80px;"><?php esc_html_e( 'Тип', 'b24-leads' ); ?></th>
							<th><?php esc_html_e( 'Сообщение', 'b24-leads' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $log_entries as $entry ) : ?>
							<tr>
								<td><?php echo esc_html( $entry['time'] ); ?></td>
								<td>
									<?php
									$type_labels = array(
										'success' => __( 'Успех', 'b24-leads' ),
										'error'   => __( 'Ошибка', 'b24-leads' ),
										'skip'    => __( 'Пропуск', 'b24-leads' ),
									);
									$label = isset( $type_labels[ $entry['type'] ] ) ? $type_labels[ $entry['type'] ] : $entry['type'];
									$color = $entry['type'] === 'success' ? 'green' : ( $entry['type'] === 'error' ? 'red' : 'gray' );
									?>
									<span style="color: <?php echo esc_attr( $color ); ?>;"><?php echo esc_html( $label ); ?></span>
								</td>
								<td>
									<?php echo esc_html( $entry['message'] ); ?>
									<?php if ( ! empty( $entry['detail']['id'] ) ) : ?>
										<code style="margin-left: 6px;">ID: <?php echo esc_html( (string) $entry['detail']['id'] ); ?></code>
									<?php endif; ?>
									<?php if ( ! empty( $entry['detail']['error'] ) ) : ?>
										<div class="description" style="margin-top: 4px;"><?php echo esc_html( $entry['detail']['error'] ); ?></div>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'Записей пока нет. Отправьте форму на сайте — здесь появятся успешные отправки и ошибки.', 'b24-leads' ); ?></p>
			<?php endif; ?>

			<hr />
			<h2><?php esc_html_e( 'Как подключить формы', 'b24-leads' ); ?></h2>
			<ul style="list-style: disc; margin-left: 1.5em;">
				<li><?php esc_html_e( 'Contact Form 7: заявки отправляются в B24 автоматически после отправки формы.', 'b24-leads' ); ?></li>
				<li><?php esc_html_e( 'Elementor Forms (Elementor Pro): заявки с виджета «Форма» уходят в B24 автоматически.', 'b24-leads' ); ?></li>
				<li><?php esc_html_e( 'WPForms: заявки отправляются в B24 после успешной отправки формы.', 'b24-leads' ); ?></li>
				<li><?php esc_html_e( 'Gravity Forms: заявки отправляются в B24 после отправки формы.', 'b24-leads' ); ?></li>
				<li><?php esc_html_e( 'Любая форма/тема: вызовите в коде do_action( \'b24_leads_wp_send_lead\', $data ); где $data — массив с ключами name, phone, email, message (и при необходимости title, utm_source, utm_medium, utm_campaign).', 'b24-leads' ); ?></li>
			</ul>
		</div>
		<?php
	}
}
