<?php
/**
 * SEO Signal OpenAPI (panel.seosignal.net/openapi/v1).
 *
 * POST + JSON with the header "X-Api-Key: email:token"; answers {ok, data} or
 * {error: {code, message}}. Keyword research, site analyzer, site compare and the
 * reportage assistant share one daily quota (50 by default, reset 00:00 Tehran).
 * Every answer is cached (search volumes are monthly data), so re-running a
 * proposal or building another one in the same industry costs no quota.
 *
 * @package Ostorlab
 */

defined( 'ABSPATH' ) || exit;

const OST_SS_BASE = 'https://panel.seosignal.net/openapi/v1/';

function ost_ss_today() {
	$d = new DateTime( 'now', new DateTimeZone( 'Asia/Tehran' ) );
	return $d->format( 'Y-m-d' );
}

/**
 * Today's quota counted by Ostorlab.
 */
function ost_ss_usage() {
	$u = get_option( 'ost_ss_usage', array() );
	if ( ! is_array( $u ) || ( $u['date'] ?? '' ) !== ost_ss_today() ) {
		$u = array( 'date' => ost_ss_today(), 'used' => 0, 'last_error' => '' );
	}
	$u['cap']   = max( 1, (int) ost_setting( 'seosignal_daily_cap', 40 ) );
	$u['sokan'] = function_exists( 'sap_ss_usage' ) ? (int) ( sap_ss_usage()['used'] ?? 0 ) : 0;
	return $u;
}

function ost_ss_remaining() {
	$u = ost_ss_usage();
	return max( 0, $u['cap'] - $u['used'] - $u['sokan'] );
}

function ost_ss_key() {
	$key = trim( (string) ost_setting( 'seosignal_key' ) );
	$key = preg_replace( '/^\s*x-api-key\s*:\s*/i', '', $key );
	return trim( $key, " \t\n\r\0\x0B\"'" );
}

function ost_ss_connected() {
	return '' !== ost_ss_key();
}

/**
 * Call an endpoint (cached).
 *
 * @param string $endpoint e.g. "keyword-research".
 * @param array  $body     JSON body.
 * @param bool   $metered  Counts against the daily quota.
 * @param bool   $fresh    Skip the cache.
 * @return array|WP_Error Decoded answer (ok, data, …).
 */
function ost_ss_request( $endpoint, $body = array(), $metered = true, $fresh = false ) {
	$cache_key = 'ost_ss_' . md5( $endpoint . wp_json_encode( $body ) );
	if ( ! $fresh ) {
		$cached = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			$cached['_cached'] = true;
			return $cached;
		}
	}
	$key = ost_ss_key();
	if ( '' === $key ) {
		return new WP_Error( 'ost_ss_key', 'کلید API سئو سیگنال وارد نشده است (اسطرلاب › تنظیمات › اتصال‌ها).' );
	}
	if ( $metered && ost_ss_remaining() < 1 ) {
		return new WP_Error( 'ost_ss_cap', 'سقف روزانه درخواست سئو سیگنال که برای اسطرلاب تعیین کرده‌اید پر شده است. تحلیل از همین‌جا فردا (۰۰:۰۰ تهران) ادامه پیدا می‌کند، یا سقف را در تنظیمات بیشتر کنید.' );
	}
	$res = ost_http( OST_SS_BASE . $endpoint, array(
		'method'  => 'POST',
		'timeout' => 90,
		'headers' => array(
			'X-Api-Key'    => $key,
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
		),
		'body'    => wp_json_encode( (object) $body ),
	) );
	if ( is_wp_error( $res ) ) {
		return new WP_Error( 'ost_ss_http', 'اتصال به سئو سیگنال برقرار نشد: ' . ost_net_error( $res ) );
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	$data = json_decode( ost_body( $res ), true );
	if ( ! is_array( $data ) ) {
		return new WP_Error( 'ost_ss_json', sprintf( 'پاسخ سئو سیگنال JSON نبود (HTTP %d). معمولاً یعنی VPN/پروکسی سرور درخواست را جای دیگری برده است.', $code ) );
	}
	if ( $metered && in_array( $code, array( 200, 422 ), true ) ) {
		$u = ost_ss_usage();
		update_option( 'ost_ss_usage', array( 'date' => $u['date'], 'used' => $u['used'] + 1, 'last_error' => '' ), false );
	}
	if ( empty( $data['ok'] ) ) {
		$err  = (array) ( $data['error'] ?? array() );
		$ecode = (string) ( $err['code'] ?? 'HTTP_' . $code );
		$msgs = array(
			'INVALID_API_KEY'     => 'کلید API سئو سیگنال نامعتبر است.',
			'PLAN_NOT_ALLOWED'    => 'پلن حساب سئو سیگنال اجازه این بخش را نمی‌دهد (برای دستیار رپورتاژ، سرویس رپورتاژ باید روی پلن فعال باشد).',
			'RATE_LIMIT_EXCEEDED' => 'سقف روزانه حساب سئو سیگنال تمام شده است.',
			'REQUEST_FAILED'      => 'سئو سیگنال برای این مورد داده‌ای نداشت.',
			'ACCOUNT_DISABLED'    => 'حساب سئو سیگنال غیرفعال است.',
		);
		return new WP_Error( 'ost_ss_' . strtolower( $ecode ), $msgs[ $ecode ] ?? ( 'خطای سئو سیگنال: ' . ( $err['message'] ?? $ecode ) ), array( 'code' => $ecode ) );
	}
	set_transient( $cache_key, $data, max( 1, (int) ost_setting( 'seosignal_cache_days', 20 ) ) * DAY_IN_SECONDS );
	return $data;
}

/**
 * Keyword research for one seed.
 *
 * @return array|WP_Error {seed, volume, total, change, list: [{w, v, c}], chart, ctr}
 */
function ost_ss_keyword( $seed, $type = 'similar', $count = 300 ) {
	$r = ost_ss_request( 'keyword-research', array( 'keyword' => $seed, 'search_type' => $type, 'count' => $count ) );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$d    = (array) ( $r['data'] ?? array() );
	$list = array();
	foreach ( (array) ( $d['keywordList'] ?? array() ) as $k ) {
		$w = trim( (string) ( $k['Word'] ?? '' ) );
		if ( '' !== $w ) {
			$list[] = array( 'w' => $w, 'v' => (int) ( $k['SearchVolume'] ?? 0 ), 'c' => (string) ( $k['Competition'] ?? '' ) );
		}
	}
	return array(
		'seed'   => $seed,
		'volume' => (int) ( $d['wordSearchVolume'] ?? 0 ),
		'total'  => (int) ( $d['allSearchVolume'] ?? 0 ),
		'change' => (float) ( $d['changeSearchVolume'] ?? 0 ),
		'list'   => $list,
		'chart'  => array( 'labels' => (array) ( $d['chart']['lable'] ?? $d['chart']['label'] ?? array() ), 'data' => array_map( 'intval', (array) ( $d['chart']['data'] ?? array() ) ) ),
		'ctr'    => ost_ss_domain_ctr( $d['domainCTR'] ?? array() ),
	);
}

/**
 * Volumes of a list of keywords (one quota unit for up to 1000 words).
 *
 * @return array|WP_Error word => [v, c]
 */
function ost_ss_bulk( $words ) {
	$words = array_values( array_unique( array_filter( array_map( 'trim', (array) $words ) ) ) );
	if ( ! $words ) {
		return array();
	}
	$r = ost_ss_request( 'keyword-research-bulk', array( 'keywords' => array_slice( $words, 0, 1000 ) ) );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$out = array();
	foreach ( (array) ( $r['data']['keywordList'] ?? array() ) as $k ) {
		$out[ (string) $k['Word'] ] = array( 'v' => (int) ( $k['SearchVolume'] ?? 0 ), 'c' => (string) ( $k['Competition'] ?? '' ) );
	}
	return $out;
}

/**
 * Keywords and top pages of a domain.
 *
 * @return array|WP_Error {domain, keywords: [{w, v, p}], pages: [{p, avg, n}], related: [], ctr}
 */
function ost_ss_site( $domain, $count = 2000 ) {
	$r = ost_ss_request( 'site-analyzer', array( 'url' => ost_domain( $domain ), 'search_type' => 'domain', 'count' => $count ) );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$d  = (array) ( $r['data'] ?? array() );
	$kw = array();
	foreach ( (array) ( $d['Keywords'] ?? array() ) as $k ) {
		$kw[] = array( 'w' => (string) ( $k['Word'] ?? '' ), 'v' => (int) ( $k['SearchVolume'] ?? 0 ), 'p' => (string) ( $k['Page'] ?? '' ) );
	}
	$pages = array();
	foreach ( (array) ( $d['topPages'] ?? array() ) as $p ) {
		$pages[] = array( 'p' => (string) ( $p['Page'] ?? '' ), 'avg' => (float) ( $p['Avg'] ?? 0 ), 'n' => (int) ( $p['Count'] ?? 0 ) );
	}
	return array(
		'domain'   => (string) ( $d['domain'] ?? ost_domain( $domain ) ),
		'keywords' => $kw,
		'pages'    => $pages,
		'related'  => array_values( array_map( 'strval', (array) ( $d['RelDomains'] ?? array() ) ) ),
		'ctr'      => ost_ss_domain_ctr( $d['domainCTR'] ?? array() ),
		'capped'   => count( $kw ) >= $count,
	);
}

/**
 * Keyword comparison of two sites ("gap": words only site2 ranks for).
 *
 * @return array|WP_Error [{w, r1, r2, v}]
 */
function ost_ss_compare( $site1, $site2, $type = 'gap' ) {
	$r = ost_ss_request( 'site-compare', array( 'site1' => ost_domain( $site1 ), 'site2' => ost_domain( $site2 ), 'compare_type' => $type ) );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$out = array();
	foreach ( (array) ( $r['data'] ?? array() ) as $row ) {
		$out[] = array(
			'w'  => (string) ( $row['Word'] ?? '' ),
			'r1' => is_numeric( $row['Rank1'] ?? null ) ? (int) $row['Rank1'] : null,
			'r2' => is_numeric( $row['Rank2'] ?? null ) ? (int) $row['Rank2'] : null,
			'v'  => (int) ( $row['SearchVolume'] ?? 0 ),
		);
	}
	return $out;
}

/**
 * Reportages a domain has bought (Iranian sponsored articles), with price.
 *
 * @param string $report reportages|inbound_anchors|linked_pages|inbound_media|cost_analysis.
 * @return array|WP_Error Raw data of the report.
 */
function ost_ss_reportage_buyer( $domain, $report = 'reportages', $range = 'all' ) {
	$r = ost_ss_request( 'reportage-buyer', array( 'domain' => ost_domain( $domain ), 'report_type' => $report, 'date_range' => $range ) );
	return is_wp_error( $r ) ? $r : (array) ( $r['data'] ?? array() );
}

/**
 * Outgoing links of a media domain (to read the anchor / target of each reportage).
 *
 * @return array|WP_Error
 */
function ost_ss_reportage_seller( $domain, $report = 'out_links', $range = 'all' ) {
	$r = ost_ss_request( 'reportage-seller', array( 'domain' => ost_domain( $domain ), 'report_type' => $report, 'date_range' => $range ) );
	return is_wp_error( $r ) ? $r : (array) ( $r['data'] ?? array() );
}

/**
 * "safine.net (10%)" pairs → [[domain, pct]].
 */
function ost_ss_domain_ctr( $ctr ) {
	$out = array();
	foreach ( (array) ( $ctr['Domain'] ?? array() ) as $i => $label ) {
		$dom   = trim( preg_replace( '/\s*\(.*$/', '', (string) $label ) );
		$out[] = array( 'd' => $dom, 'pct' => (float) ( $ctr['CTR'][ $i ] ?? 0 ) );
	}
	return $out;
}

/**
 * Connection test (one quota unit; cached answers are not used).
 *
 * @return array{ok: bool, msg: string}
 */
function ost_ss_test() {
	$r = ost_ss_request( 'keyword-research-bulk', array( 'keywords' => array( 'سئو' ) ), true, true );
	if ( is_wp_error( $r ) ) {
		return array( 'ok' => false, 'msg' => $r->get_error_message() );
	}
	$u = $r['data']['Usage'] ?? null;
	$l = $r['data']['Limit'] ?? null;
	return array( 'ok' => true, 'msg' => 'اتصال موفق' . ( null !== $u ? sprintf( '؛ مصرف ماهانه پلن: %s از %s', ost_num( $u ), ost_num( $l ) ) : '' ) );
}

/**
 * Quota units a proposal's analysis needs (so the user sees it before running).
 */
function ost_ss_cost_estimate( $p ) {
	$in      = $p['inputs'];
	$domains = count( ost_domains( $p ) );
	$comp    = count( $in['competitors'] );
	$client  = $in['has_site'] ? 1 : 0;
	return array(
		'keywords'  => count( $in['keywords'] ),
		'sites'     => $domains,
		'gap'       => $client * $comp,
		'reportage' => 3 * $domains,
		'total'     => count( $in['keywords'] ) + $domains + $client * $comp + 3 * $domains,
	);
}
