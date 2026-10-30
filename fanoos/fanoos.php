<?php
/**
 * Plugin Name:       فانوس — Fanoos by Ronagh
 * Description:       فانوس، پروپوزال‌ساز هوشمند سئو: تحلیل بازار جستجو، رقبا، آف‌پیج (رپورتاژ و بک‌لینک باکیفیت)، وضعیت تکنیکال و سرچ کنسول، محاسبه هزینه و نقشه راه ۸ تا ۱۲ ماهه، و خروجی PDF اسلایدی با برند آژانس.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Mehdi Ilkhani Nasab
 * License:           GPL-2.0-or-later
 * Text Domain:       fanoos
 *
 * @package Fanoos
 */

defined( 'ABSPATH' ) || exit;

const FNS_VERSION = '1.0.0';
const FNS_CAP     = 'manage_options';
const FNS_CPT     = 'fanoos_proposal';

define( 'FNS_FILE', __FILE__ );
define( 'FNS_DIR', __DIR__ );
define( 'FNS_URL', plugin_dir_url( __FILE__ ) );

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
	'engine/competitors',
	'engine/offpage',
	'engine/technical',
	'engine/forecast',
	'engine/budget',
	'engine/roadmap',
	'engine/writer',
	'engine/runner',
	'engine/charts',
	'engine/deck',
	'engine/pdf',
	'rest',
	'admin/menu',
	'admin/actions',
) as $fns_file ) {
	require_once FNS_DIR . '/inc/' . $fns_file . '.php';
}
unset( $fns_file );

add_action( 'init', 'fns_register_cpt' );

register_activation_hook( __FILE__, function () {
	fns_register_cpt();
	flush_rewrite_rules();
} );
