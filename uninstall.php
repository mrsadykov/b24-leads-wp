<?php
/**
 * Удаление опций плагина при полном удалении из админки.
 *
 * @package Sadykov_Form_Submissions
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$option_names = array(
	'b24_leads_wp_webhook_url',
	'b24_leads_wp_entity_type',
	'b24_leads_wp_field_mapping',
	'b24_leads_wp_field_mapping_extra',
	'b24_leads_wp_deal_category_id',
	'b24_leads_wp_deal_stage_id',
	'b24_leads_wp_create_contact',
	'b24_leads_wp_last_response',
	'b24_leads_wp_log',
);

if ( is_multisite() ) {
	global $wpdb;
	$blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );
	foreach ( $blog_ids as $blog_id ) {
		switch_to_blog( (int) $blog_id );
		foreach ( $option_names as $name ) {
			delete_option( $name );
		}
		restore_current_blog();
	}
} else {
	foreach ( $option_names as $name ) {
		delete_option( $name );
	}
}
