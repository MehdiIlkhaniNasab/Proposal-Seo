<?php
/**
 * Plugin Name:       اسطرلاب — Ostorlab by Ronagh
 * Description:       اسطرلاب — پیش از حرکت، مسیر را بسنج. پروپوزال‌ساز هوشمند سئو: تحلیل بازار جستجو، رقبا، آف‌پیج (رپورتاژ و بک‌لینک باکیفیت)، وضعیت تکنیکال و سرچ کنسول، محاسبه هزینه و نقشه راه ۸ تا ۱۲ ماهه، و خروجی PDF اسلایدی با برند آژانس.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Mehdi Ilkhani Nasab
 * License:           GPL-2.0-or-later
 * Text Domain:       ostorlab
 *
 * @package Ostorlab
 */

defined( 'ABSPATH' ) || exit;

const OST_VERSION = '1.0.0';
const OST_CAP     = 'manage_options';
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
	'engine/competitors',
	'engine/strategy',
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
) as $ost_file ) {
	require_once OST_DIR . '/inc/' . $ost_file . '.php';
}
unset( $ost_file );

add_action( 'init', 'ost_register_cpt' );

register_activation_hook( __FILE__, function () {
	ost_register_cpt();
	flush_rewrite_rules();
} );
