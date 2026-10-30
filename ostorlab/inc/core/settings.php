<?php
/**
 * Global settings: connections, agency brand, default prices, quality rules.
 *
 * When Sokan (the SEO action-plan plugin) runs on the same WordPress, empty
 * connection fields fall back to Sokan's: the same SEO Signal key, AI gateway
 * and Google connection are used without entering them twice.
 *
 * @package Ostorlab
 */

defined( 'ABSPATH' ) || exit;

const OST_SECRET_KEYS = array( 'seosignal_key', 'ai_gateway_key', 'anthropic_key', 'google_service_account', 'semrush_key', 'pdf_service_token' );

/**
 * Settings that Sokan also has (same names), used when ours are empty.
 */
const OST_SOKAN_SHARED = array( 'seosignal_key', 'ai_provider', 'ai_base_url', 'ai_gateway_key', 'anthropic_key', 'ai_model', 'ai_model_light' );

/**
 * Default settings.
 */
function ost_settings_defaults() {
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
		'agency_instagram'       => '',
		'agency_linkedin'        => '',
		'consultant_name'        => 'مهدی ایلخانی نسب',
		'consultant_title'       => 'مدیر سئو',
		'consultant_phone'       => '',
		'consultant_email'       => '',
		'consultant_photo'       => '',
		'consultant_bio'         => 'مسئول استراتژی و نتیجه پروژه؛ همه گزارش‌ها و جلسات ماهانه با من است.',
		'agency_stats'           => "پروژه سئو اجراشده | ۱۲۰+\nسال تجربه تیم | ۸\nتمدید قرارداد مشتریان | ۹۰٪",
		'team'                   => "مهدی ایلخانی نسب | مدیر سئو و استراتژی\nکارشناس سئو تکنیکال | رفع فنی و سرعت\nسردبیر محتوا | تولید و به‌روزرسانی محتوا\nکارشناس آف‌پیج | رپورتاژ و لینک‌سازی",
		'proposal_prefix'        => 'RN',
		'proposal_validity'      => 14,
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
function ost_settings() {
	if ( isset( $GLOBALS['ost_settings_cache'] ) ) {
		return $GLOBALS['ost_settings_cache'];
	}
	$stored = get_option( 'ost_settings', array() );
	$s      = wp_parse_args( is_array( $stored ) ? $stored : array(), ost_settings_defaults() );
	foreach ( OST_SECRET_KEYS as $k ) {
		$s[ $k ] = ost_decrypt( $s[ $k ] );
	}
	$GLOBALS['ost_settings_cache'] = $s;
	return $s;
}

/**
 * Is Sokan active on this site?
 */
function ost_sokan_active() {
	return function_exists( 'sap_setting' );
}

/**
 * One setting; shared connection settings fall back to Sokan's.
 */
function ost_setting( $key, $default = '' ) {
	$s   = ost_settings();
	$val = $s[ $key ] ?? '';
	if ( ( '' === $val || null === $val ) && in_array( $key, OST_SOKAN_SHARED, true ) && ! empty( $s['reuse_sokan'] ) && ost_sokan_active() ) {
		$val = sap_setting( $key, '' );
	}
	return ( '' === $val || null === $val ) ? $default : $val;
}

/**
 * Is a setting inherited from Sokan (ours empty, Sokan's set)?
 */
function ost_setting_from_sokan( $key ) {
	$s = ost_settings();
	return '' === (string) ( $s[ $key ] ?? '' ) && ! empty( $s['reuse_sokan'] ) && ost_sokan_active() && in_array( $key, OST_SOKAN_SHARED, true ) && '' !== (string) sap_setting( $key, '' );
}

/**
 * Partial update. Secrets are encrypted at rest.
 */
function ost_update_settings( $changes ) {
	$stored = get_option( 'ost_settings', array() );
	$stored = is_array( $stored ) ? $stored : array();
	foreach ( $changes as $k => $v ) {
		$stored[ $k ] = in_array( $k, OST_SECRET_KEYS, true ) ? ost_encrypt( $v ) : $v;
	}
	update_option( 'ost_settings', $stored, false );
	unset( $GLOBALS['ost_settings_cache'] );
}

/**
 * Agency logo URL (setting or the bundled Ronagh logo).
 */
function ost_logo_url() {
	$logo = (string) ost_setting( 'agency_logo' );
	return '' !== $logo ? $logo : OST_URL . 'assets/img/ronagh-logo.png';
}

/**
 * "label | value" lines → [[label, value]].
 */
function ost_pairs( $text ) {
	$out = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
		$p = array_map( 'trim', explode( '|', $line ) );
		if ( '' !== $p[0] ) {
			$out[] = array( $p[0], $p[1] ?? '' );
		}
	}
	return $out;
}

/**
 * Who presents a proposal: settings, overridden per proposal.
 *
 * @return array name, title, phone, email, photo, bio, team [[name, role]], stats [[label, value]],
 *               agency, tagline, logo, site, agency_phone, agency_email, address, instagram, linkedin,
 *               number, date, valid_until, prepared_for.
 */
function ost_presenter( $p = null ) {
	$in   = $p ? (array) $p['inputs'] : array();
	$pick = function ( $key, $setting ) use ( $in ) {
		$v = trim( (string) ( $in[ $key ] ?? '' ) );
		return '' !== $v ? $v : (string) ost_setting( $setting );
	};
	$days    = (int) ( ! empty( $in['validity_days'] ) ? $in['validity_days'] : ost_setting( 'proposal_validity', 14 ) );
	$created = $p ? (int) $p['created'] : time();
	$number  = trim( (string) ( $in['proposal_no'] ?? '' ) );
	if ( '' === $number && $p ) {
		list( $jy ) = ost_jalali( (int) gmdate( 'Y', $created ), (int) gmdate( 'n', $created ), (int) gmdate( 'j', $created ) );
		$number     = trim( (string) ost_setting( 'proposal_prefix', 'RN' ) ) . '-' . $jy . '-' . str_pad( (string) $p['id'], 3, '0', STR_PAD_LEFT );
	}
	$team = ost_pairs( '' !== trim( (string) ( $in['team'] ?? '' ) ) ? $in['team'] : ost_setting( 'team' ) );
	return array(
		'name'         => $pick( 'presenter_name', 'consultant_name' ),
		'title'        => $pick( 'presenter_title', 'consultant_title' ),
		'phone'        => $pick( 'presenter_phone', 'consultant_phone' ),
		'email'        => $pick( 'presenter_email', 'consultant_email' ),
		'photo'        => (string) ost_setting( 'consultant_photo' ),
		'bio'          => (string) ost_setting( 'consultant_bio' ),
		'team'         => $team,
		'stats'        => ost_pairs( ost_setting( 'agency_stats' ) ),
		'agency'       => (string) ost_setting( 'agency_name' ),
		'tagline'      => (string) ost_setting( 'agency_tagline' ),
		'logo'         => ost_logo_url(),
		'site'         => (string) ost_setting( 'agency_site' ),
		'agency_phone' => (string) ost_setting( 'agency_phone' ),
		'agency_email' => (string) ost_setting( 'agency_email' ),
		'address'      => (string) ost_setting( 'agency_address' ),
		'instagram'    => (string) ost_setting( 'agency_instagram' ),
		'linkedin'     => (string) ost_setting( 'agency_linkedin' ),
		'number'       => $number,
		'date'         => ost_jdate( $created ),
		'valid_until'  => $days > 0 ? ost_jdate( $created + $days * DAY_IN_SECONDS ) : '',
		'prepared_for' => trim( (string) ( $in['contact_person'] ?? '' ) ),
	);
}

/**
 * Portfolio items from settings: [title, result, detail].
 */
function ost_portfolio() {
	$out = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) ost_setting( 'portfolio' ) ) as $line ) {
		$p = array_map( 'trim', explode( '|', $line ) );
		if ( '' !== $p[0] ) {
			$out[] = array( 'title' => $p[0], 'result' => $p[1] ?? '', 'detail' => $p[2] ?? '' );
		}
	}
	return $out;
}
