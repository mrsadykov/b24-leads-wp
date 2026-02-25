<?php
/**
 * Plugin Name: Заявки в Битрикс24
 * Plugin URI: https://github.com/your-repo/b24-leads-wp
 * Description: Отправка заявок с форм WordPress в Битрикс24 (лиды/сделки) по входящему вебхуку.
 * Version: 1.0.0
 * Author: B24 Leads
 * Author URI: https://github.com/your-repo
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: b24-leads-wp
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

const B24_LEADS_WP_VERSION = '1.0.0';
const B24_LEADS_WP_PLUGIN_FILE = __FILE__;
const B24_LEADS_WP_PLUGIN_DIR = __DIR__;

require_once B24_LEADS_WP_PLUGIN_DIR . '/includes/class-b24-logger.php';
require_once B24_LEADS_WP_PLUGIN_DIR . '/includes/class-b24-sender.php';
require_once B24_LEADS_WP_PLUGIN_DIR . '/includes/class-b24-admin.php';
require_once B24_LEADS_WP_PLUGIN_DIR . '/includes/class-b24-cf7.php';
require_once B24_LEADS_WP_PLUGIN_DIR . '/includes/class-b24-elementor.php';
require_once B24_LEADS_WP_PLUGIN_DIR . '/includes/class-b24-wpforms.php';
require_once B24_LEADS_WP_PLUGIN_DIR . '/includes/class-b24-gravity.php';

/**
 * Инициализация плагина.
 */
function b24_leads_wp_init() {
	B24_Leads_Admin::instance();
	B24_Leads_Sender::instance();
	if ( class_exists( 'WPCF7_ContactForm' ) ) {
		B24_Leads_CF7::instance();
	}
	if ( defined( 'ELEMENTOR_PRO_VERSION' ) ) {
		B24_Leads_Elementor::instance();
	}
	if ( function_exists( 'wpforms' ) ) {
		B24_Leads_WPForms::instance();
	}
	if ( class_exists( 'GFForms' ) ) {
		B24_Leads_Gravity::instance();
	}
	// Хук для расширений (например Pro-add-on): подключаться после загрузки бесплатного плагина.
	do_action( 'b24_leads_wp_loaded' );
}
add_action( 'plugins_loaded', 'b24_leads_wp_init', 20 );

/**
 * Универсальная отправка заявки в B24.
 * Любая форма/плагин может вызвать: do_action( 'b24_leads_wp_send_lead', $data );
 *
 * @param array $data Ассоциативный массив: name, phone, email, message, title, utm_source, utm_medium, utm_campaign и др.
 */
function b24_leads_wp_send_lead( $data ) {
	do_action( 'b24_leads_wp_send_lead', $data );
}
