<?php
/**
 * Plugin Name:       اسطرلاب — Ostorlab by Ronagh
 * Description:       اسطرلاب — پیش از حرکت، مسیر را بسنج. پروپوزال‌ساز هوشمند سئو: تحلیل بازار جستجو، رقبا، آف‌پیج (رپورتاژ و بک‌لینک باکیفیت)، وضعیت تکنیکال و سرچ کنسول، محاسبه هزینه و نقشه راه ۸ تا ۱۲ ماهه، و خروجی PDF اسلایدی با برند آژانس.
 * Version:           1.5.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Mehdi Ilkhani Nasab
 * License:           GPL-2.0-or-later
 * Text Domain:       ostorlab
 *
 * @package Ostorlab
 */

defined( 'ABSPATH' ) || exit;

const OST_VERSION = '1.5.0';
const OST_CAP     = 'ost_manage'; // Administrators and Sokan's SEO managers get it (inc/app/router.php).
const OST_CPT     = 'ostorlab_proposal';

define( 'OST_FILE', __FILE__ );
define( 'OST_DIR', __DIR__ );
define( 'OST_URL', plugin_dir_url( __FILE__ ) );

foreach ( array(
	'core/helpers',
	'core/settings',
	'core/network',
	'core/proposals',
	'connectors/seosignal',
	'connectors/semrush',
	'connectors/google',
	'connectors/llm',
	'connectors/site',
	'connectors/screaming-frog',
	'engine/keywords',
	'engine/discovery',
	'engine/ai-market',
	'engine/competitors',
	'engine/strategy',
	'engine/offpage',
	'engine/technical',
	'engine/forecast',
	'engine/estimate',
	'engine/budget',
	'engine/roadmap',
	'engine/writer',
	'engine/runner',
	'engine/charts',
	'engine/deck',
	'engine/pdf',
	'rest',
	'app/ui',
	'app/router',
	'app/actions',
) as $ost_file ) {
	require_once OST_DIR . '/inc/' . $ost_file . '.php';
}
unset( $ost_file );

add_action( 'init', 'ost_register_cpt' );

/**
 * Upgrades. 1.3.0: the Screaming Frog wizard state of 1.2.x could grow on every save (a
 * numeric key doubled each time) until the host slowed down mid-crawl; oversized states are
 * removed (only the wizard's progress, not the crawl data, which is stored separately).
 * The crawl now takes the 30 most important pages of each site instead of 500.
 */
add_action( 'admin_init', 'ost_upgrade' );
add_action( 'template_redirect', 'ost_upgrade', 1 );
add_action( 'rest_api_init', 'ost_upgrade', 1 );
function ost_upgrade() {
	if ( version_compare( (string) get_option( 'ost_db_version', '0' ), '1.3.0', '>=' ) ) {
		return;
	}
	global $wpdb;
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s AND LENGTH(meta_value) > %d", '_ost_sf_wizard', 20000 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	wp_cache_flush();
	// The old default of 500 pages per site becomes the new default of 30 important pages.
	foreach ( get_posts( array( 'post_type' => OST_CPT, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $pid ) {
		$in = get_post_meta( $pid, '_ost_inputs', true );
		if ( is_array( $in ) && 500 === (int) ( $in['crawl_limit'] ?? 0 ) ) {
			$in['crawl_limit'] = 30;
			update_post_meta( $pid, '_ost_inputs', $in );
		}
	}
	update_option( 'ost_db_version', OST_VERSION, false );
}

register_activation_hook( __FILE__, function () {
	ost_register_cpt();
	flush_rewrite_rules();
} );
