<?php
/**
 * Slide deck: which slides a proposal has (some depend on the data: a client
 * without a site gets "starting right from zero" instead of the current-state
 * slides) and the HTML of the whole deck — the same page is the web view, the
 * print-to-PDF source and the input of the PDF service.
 *
 * @package Fanoos
 */

defined( 'ABSPATH' ) || exit;

/**
 * Slides: key => label. Conditional slides are left out when they have no data.
 */
function fns_deck_slides( $p, $a ) {
	$has  = ! empty( $a['client_domain'] );
	$all  = array(
		'cover'       => 'جلد',
		'summary'     => 'خلاصه اجرایی',
		'div_market'  => 'بخش ۱: بازار و رقبا',
		'market'      => 'بازار جستجو',
		'clusters'    => 'خوشه‌های کلمات',
		'money'       => 'کلمات پول‌ساز',
		'current'     => $has ? 'وضعیت فعلی سایت' : 'شروع از صفر',
		'competitors' => 'مقایسه با رقبا',
		'sov'         => 'سهم بازار و صفحات برتر رقبا',
		'gap'         => 'شکاف کلمات',
		'div_audit'   => 'بخش ۲: فنی، محتوا و اعتبار',
		'offpage'     => 'رپورتاژ و بک‌لینک',
		'offpage_pages' => 'لینک صفحات پول‌ساز',
		'technical'   => 'سلامت فنی',
		'tech_compare' => 'مقایسه فنی',
		'content'     => 'محتوا',
		'swot'        => 'نقاط قوت، ضعف، فرصت و تهدید',
		'div_plan'    => 'بخش ۳: نقشه راه و سرمایه‌گذاری',
		'roadmap'     => 'نقشه راه',
		'plan'        => 'برنامه ماه‌به‌ماه',
		'forecast'    => 'نتیجه قابل انتظار',
		'packages'    => 'پکیج‌ها',
		'costs'       => 'جزئیات هزینه',
		'commitments' => 'تعهدات و همکاری',
		'portfolio'   => 'نمونه‌کارها',
		'closing'     => 'قدم بعدی',
	);
	if ( ! $has || empty( $a['gap']['count'] ) ) {
		unset( $all['gap'] );
	}
	$has_pages = false;
	foreach ( (array) ( $a['offpage'] ?? array() ) as $o ) {
		if ( ! empty( $o['reportage']['pages'] ) || ! empty( $o['backlinks']['pages'] ) ) {
			$has_pages = true;
		}
	}
	if ( ! $has_pages ) {
		unset( $all['offpage_pages'] );
	}
	if ( ! fns_portfolio() ) {
		unset( $all['portfolio'] );
	}
	if ( count( (array) ( $a['technical'] ?? array() ) ) < 2 ) {
		unset( $all['tech_compare'] );
	}
	return $all;
}

/**
 * Slides switched on for export (all by default).
 */
function fns_deck_enabled( $p, $a ) {
	$off = array_flip( (array) ( $p['slides']['off'] ?? array() ) );
	return array_diff_key( fns_deck_slides( $p, $a ), $off );
}

/**
 * Full HTML of the deck.
 *
 * @param array $args inline (embed font + logo as data URIs, for the PDF service), print (open print dialog).
 */
function fns_deck_html( $id, $args = array() ) {
	$p = fns_proposal( $id );
	$a = fns_data( $id, 'analysis' );
	if ( ! $p || ! $a ) {
		return '';
	}
	$args = wp_parse_args( $args, array( 'inline' => false, 'print' => false, 'web' => false ) );
	ob_start();
	fns_view( 'deck/deck', array(
		'p'      => $p,
		'a'      => $a,
		't'      => fns_texts( $p, $a ),
		'slides' => fns_deck_enabled( $p, $a ),
		'args'   => $args,
	) );
	return (string) ob_get_clean();
}

/**
 * Font and logo URLs (or data URIs when the page must be self-contained).
 */
function fns_deck_assets( $inline ) {
	$font = FNS_DIR . '/assets/fonts/Vazirmatn-Variable.woff2';
	$logo = fns_logo_url();
	if ( ! $inline ) {
		return array( 'font' => FNS_URL . 'assets/fonts/Vazirmatn-Variable.woff2', 'logo' => $logo, 'css' => FNS_DIR . '/assets/css/deck.css' );
	}
	$logo_data = $logo;
	$local     = 0 === strpos( $logo, FNS_URL ) ? FNS_DIR . '/' . substr( $logo, strlen( FNS_URL ) ) : '';
	if ( $local && file_exists( $local ) ) {
		$logo_data = 'data:image/png;base64,' . base64_encode( (string) file_get_contents( $local ) ); // phpcs:ignore
	} else {
		$r = wp_remote_get( $logo, array( 'timeout' => 15 ) );
		if ( ! is_wp_error( $r ) && 200 === (int) wp_remote_retrieve_response_code( $r ) ) {
			$type      = wp_remote_retrieve_header( $r, 'content-type' ) ?: 'image/png';
			$logo_data = 'data:' . $type . ';base64,' . base64_encode( wp_remote_retrieve_body( $r ) );
		}
	}
	return array(
		'font' => 'data:font/woff2;base64,' . base64_encode( (string) file_get_contents( $font ) ), // phpcs:ignore
		'logo' => $logo_data,
		'css'  => FNS_DIR . '/assets/css/deck.css',
	);
}
