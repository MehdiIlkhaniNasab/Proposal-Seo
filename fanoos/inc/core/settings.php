<?php
/**
 * Global settings: connections, agency brand, default prices, quality rules.
 *
 * When Sokan (the SEO action-plan plugin) runs on the same WordPress, empty
 * connection fields fall back to Sokan's: the same SEO Signal key, AI gateway
 * and Google connection are used without entering them twice.
 *
 * @package Fanoos
 */

defined( 'ABSPATH' ) || exit;

const FNS_SECRET_KEYS = array( 'seosignal_key', 'ai_gateway_key', 'anthropic_key', 'google_service_account', 'semrush_key', 'pdf_service_token' );

/**
 * Settings that Sokan also has (same names), used when ours are empty.
 */
const FNS_SOKAN_SHARED = array( 'seosignal_key', 'ai_provider', 'ai_base_url', 'ai_gateway_key', 'anthropic_key', 'ai_model', 'ai_model_light' );

/**
 * Default settings.
 */
function fns_settings_defaults() {
	return array(
		// Connections.
		'reuse_sokan'            => 1,
		'seosignal_key'          => '',
		'seosignal_daily_cap'    => 40,
		'seosignal_cache_days'   => 20,
		'ai_provider'            => 'gateway', // gateway (OpenAI-compatible, e.g. GapGPT) | anthropic.
		'ai_base_url'            => 'https://api.gapgpt.app/v1',
		'ai_gateway_key'         => '',
		'anthropic_key'          => '',
		'ai_model'               => 'claude-sonnet-5-5',
		'ai_model_light'         => 'gpt-4.1-mini',
		'ai_budget'              => 10,
		'google_service_account' => '',
		'semrush_key'            => '',
		'semrush_enabled'        => 0,
		'semrush_limit'          => 300,  // Backlink rows read per domain (each row costs API units).
		'semrush_min_ascore'     => 10,   // Authority Score of a source below this = low quality.
		'proxy_mode'             => 'smart',
		'pdf_service'            => 'browser', // browser (print dialog) | gotenberg.
		'pdf_service_url'        => '',
		'pdf_service_token'      => '',
		// Agency.
		'agency_name'            => 'آژانس رونق',
		'agency_tagline'         => 'آژانس معماری سود کسب‌وکار',
		'agency_logo'            => '',
		'agency_site'            => 'ronagh.com',
		'agency_phone'           => '',
		'agency_email'           => '',
		'agency_address'         => '',
		'consultant_name'        => 'مهدی ایلخانی نسب',
		'consultant_title'       => 'مدیر سئو',
		'agency_about'           => 'ما سئو را از کلمه‌هایی شروع می‌کنیم که برای شما مشتری و فروش می‌آورند، نه فقط بازدید. هر ماه دقیقاً می‌دانید چه کاری انجام شده و نتیجه‌اش چه بوده است.',
		'portfolio'              => "فروشگاه لوازم خانگی | رشد ۳۴۰٪ کلیک ارگانیک در ۱۰ ماه | ۷۲ کلمه خرید در صفحه اول\nکلینیک زیبایی | ۳ برابر شدن تماس از گوگل در ۸ ماه | رتبه ۱ تا ۳ در ۱۸ خدمت محلی\nآموزشگاه آنلاین | ۲۱۰٪ رشد ثبت‌نام ارگانیک | کاهش ۴۵٪ هزینه تبلیغات",
		'theme_primary'          => '#0B4F9C',
		'theme_accent'           => '#0D94E4',
		'theme_dark'             => '#0A2540',
		// Prices (Toman) and defaults of a new proposal.
		'price_article'          => 1500000,
		'price_update'           => 700000,
		'price_landing'          => 2500000,
		'price_reportage'        => 4500000,
		'price_backlink'         => 1200000,
		'fee_monthly'            => 25000000,
		'default_months'         => 12,
		'conversion_rate'        => 1.5,   // % of organic visits that buy (global e-commerce average is 1.5–3%).
		'lead_rate'              => 3,     // % of visits that become a lead (services).
		'click_value'            => 3000,  // Toman per visit equivalent (paid ads value).
		// Off-page quality rules.
		'generic_anchors'        => "اینجا\nکلیک کنید\nاینجا کلیک کنید\nسایت\nوبسایت\nلینک\nمنبع\nادامه مطلب\nبیشتر بخوانید\nمشاهده\nclick here\nhere\nwebsite\nread more\nsource\nlink",
		'spam_patterns'          => "/forum\n/forums\n/comment\n/profile\n/member\n/members\n/user/\n/users/\n/author/\n/tag/\n/directory\nguestbook\nreplytocom\n/wp-admin\n/feed\n?p=\nblogfa.com\nblogsky.com\nmihanblog.com\npersianblog.ir\nblog.ir\nrozblog.com\nloxblog.com",
		'iran_tlds'              => ".ir\n.co.ir\n.ac.ir\n.org.ir\n.net.ir\n.id.ir\n.gov.ir",
	);
}

/**
 * All settings (secrets decrypted).
 */
function fns_settings() {
	if ( isset( $GLOBALS['fns_settings_cache'] ) ) {
		return $GLOBALS['fns_settings_cache'];
	}
	$stored = get_option( 'fns_settings', array() );
	$s      = wp_parse_args( is_array( $stored ) ? $stored : array(), fns_settings_defaults() );
	foreach ( FNS_SECRET_KEYS as $k ) {
		$s[ $k ] = fns_decrypt( $s[ $k ] );
	}
	$GLOBALS['fns_settings_cache'] = $s;
	return $s;
}

/**
 * Is Sokan active on this site?
 */
function fns_sokan_active() {
	return function_exists( 'sap_setting' );
}

/**
 * One setting; shared connection settings fall back to Sokan's.
 */
function fns_setting( $key, $default = '' ) {
	$s   = fns_settings();
	$val = $s[ $key ] ?? '';
	if ( ( '' === $val || null === $val ) && in_array( $key, FNS_SOKAN_SHARED, true ) && ! empty( $s['reuse_sokan'] ) && fns_sokan_active() ) {
		$val = sap_setting( $key, '' );
	}
	return ( '' === $val || null === $val ) ? $default : $val;
}

/**
 * Is a setting inherited from Sokan (ours empty, Sokan's set)?
 */
function fns_setting_from_sokan( $key ) {
	$s = fns_settings();
	return '' === (string) ( $s[ $key ] ?? '' ) && ! empty( $s['reuse_sokan'] ) && fns_sokan_active() && in_array( $key, FNS_SOKAN_SHARED, true ) && '' !== (string) sap_setting( $key, '' );
}

/**
 * Partial update. Secrets are encrypted at rest.
 */
function fns_update_settings( $changes ) {
	$stored = get_option( 'fns_settings', array() );
	$stored = is_array( $stored ) ? $stored : array();
	foreach ( $changes as $k => $v ) {
		$stored[ $k ] = in_array( $k, FNS_SECRET_KEYS, true ) ? fns_encrypt( $v ) : $v;
	}
	update_option( 'fns_settings', $stored, false );
	unset( $GLOBALS['fns_settings_cache'] );
}

/**
 * Agency logo URL (setting or the bundled Ronagh logo).
 */
function fns_logo_url() {
	$logo = (string) fns_setting( 'agency_logo' );
	return '' !== $logo ? $logo : FNS_URL . 'assets/img/ronagh-logo.png';
}

/**
 * Portfolio items from settings: [title, result, detail].
 */
function fns_portfolio() {
	$out = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) fns_setting( 'portfolio' ) ) as $line ) {
		$p = array_map( 'trim', explode( '|', $line ) );
		if ( '' !== $p[0] ) {
			$out[] = array( 'title' => $p[0], 'result' => $p[1] ?? '', 'detail' => $p[2] ?? '' );
		}
	}
	return $out;
}
