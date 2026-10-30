<?php
/**
 * Semrush Backlinks API (api.semrush.com/analytics/v1/).
 *
 * The structure is complete and can be switched on in settings once the account
 * is active. Answers are CSV (";" separated) with a header row; errors are plain
 * text such as "ERROR 50 :: NOTHING FOUND". Each backlink row costs API units, so
 * the number of rows read per domain is capped in settings and everything is
 * cached for 20 days.
 *
 * Reports used:
 *   backlinks_overview   — totals of a domain or one page (target_type=url)
 *   backlinks_refdomains — referring domains with Authority Score and country
 *   backlinks            — individual links (source, anchor, nofollow, sitewide …)
 *   backlinks_anchors    — anchor texts with domain counts
 *   backlinks_pages      — pages of the target that receive the most links
 *
 * @package Fanoos
 */

defined( 'ABSPATH' ) || exit;

const FNS_SEMRUSH_BASE = 'https://api.semrush.com/analytics/v1/';

/**
 * Is Semrush switched on and does it have a key?
 */
function fns_semrush_connected() {
	return ! empty( fns_setting( 'semrush_enabled' ) ) && '' !== trim( (string) fns_setting( 'semrush_key' ) );
}

/**
 * Columns requested per report.
 */
function fns_semrush_columns() {
	return array(
		'backlinks_overview'   => 'ascore,total,domains_num,urls_num,ips_num,follows_num,nofollows_num,texts_num,images_num',
		'backlinks_refdomains' => 'domain_ascore,domain,backlinks_num,ip,country,first_seen,last_seen',
		'backlinks'            => 'page_ascore,source_title,source_url,target_url,anchor,external_num,internal_num,first_seen,last_seen,nofollow,sitewide,newlink,lostlink,form,frame,image',
		'backlinks_anchors'    => 'anchor,domains_num,backlinks_num,first_seen,last_seen',
		'backlinks_pages'      => 'source_url,source_title,response_code,backlinks_num,domains_num,last_seen',
	);
}

/**
 * One report.
 *
 * @param string $type        Report type (see fns_semrush_columns()).
 * @param string $target      Domain or URL.
 * @param string $target_type root_domain|domain|url.
 * @param int    $limit       Rows.
 * @return array|WP_Error Rows (assoc arrays by column name).
 */
function fns_semrush_report( $type, $target, $target_type = 'root_domain', $limit = 100, $extra = array() ) {
	if ( ! fns_semrush_connected() ) {
		return new WP_Error( 'fns_semrush_off', 'سمراش هنوز متصل نشده است (فانوس › تنظیمات › اتصال‌ها › سمراش).' );
	}
	$params = array_merge( array(
		'key'            => trim( (string) fns_setting( 'semrush_key' ) ),
		'type'           => $type,
		'target'         => 'url' === $target_type ? $target : fns_domain( $target ),
		'target_type'    => $target_type,
		'export_columns' => fns_semrush_columns()[ $type ] ?? '',
		'display_limit'  => (int) $limit,
	), $extra );
	$cache_key = 'fns_sr_' . md5( wp_json_encode( array_diff_key( $params, array( 'key' => 1 ) ) ) );
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$res = fns_http( add_query_arg( array_map( 'rawurlencode', $params ), FNS_SEMRUSH_BASE ), array( 'method' => 'GET', 'timeout' => 90 ) );
	if ( is_wp_error( $res ) ) {
		return new WP_Error( 'fns_semrush_http', 'اتصال به سمراش برقرار نشد: ' . fns_net_error( $res ) );
	}
	$body = trim( (string) wp_remote_retrieve_body( $res ) );
	if ( 0 === strpos( $body, 'ERROR' ) ) {
		if ( false !== strpos( $body, 'NOTHING FOUND' ) ) {
			set_transient( $cache_key, array(), 20 * DAY_IN_SECONDS );
			return array();
		}
		$msgs = array(
			'120' => 'کلید API سمراش نامعتبر است.',
			'132' => 'واحدهای API حساب سمراش تمام شده است.',
			'134' => 'واحدهای API حساب سمراش تمام شده است.',
			'135' => 'حساب سمراش به Backlinks API دسترسی ندارد (پلن Business لازم است).',
		);
		$code = preg_match( '/ERROR\s+(\d+)/', $body, $m ) ? $m[1] : '';
		return new WP_Error( 'fns_semrush_' . $code, $msgs[ $code ] ?? 'خطای سمراش: ' . mb_substr( $body, 0, 160 ) );
	}
	$rows = fns_semrush_parse_csv( $body );
	set_transient( $cache_key, $rows, 20 * DAY_IN_SECONDS );
	return $rows;
}

/**
 * Parse the ";" separated answer.
 */
function fns_semrush_parse_csv( $body ) {
	$lines = preg_split( '/\r\n|\n/', trim( $body ) );
	if ( count( $lines ) < 1 ) {
		return array();
	}
	$head = array_map( function ( $h ) {
		return strtolower( str_replace( ' ', '_', trim( $h ) ) );
	}, str_getcsv( array_shift( $lines ), ';', '"', '\\' ) );
	// Semrush answers with display names ("Page ascore"); map them back to the requested keys.
	$alias = array( 'page_ascore' => 'page_ascore', 'domain_ascore' => 'domain_ascore', 'authority_score' => 'ascore' );
	$rows  = array();
	foreach ( $lines as $line ) {
		if ( '' === trim( $line ) ) {
			continue;
		}
		$vals = str_getcsv( $line, ';', '"', '\\' );
		$row  = array();
		foreach ( $head as $i => $h ) {
			$row[ $alias[ $h ] ?? $h ] = $vals[ $i ] ?? '';
		}
		$rows[] = $row;
	}
	return $rows;
}

/**
 * Everything the off-page analysis needs for one domain.
 *
 * @param string   $domain    Domain.
 * @param string[] $top_pages Full URLs of its most important pages (traffic / money pages).
 * @return array|WP_Error
 */
function fns_semrush_domain( $domain, $top_pages = array() ) {
	$limit    = max( 50, (int) fns_setting( 'semrush_limit', 300 ) );
	$overview = fns_semrush_report( 'backlinks_overview', $domain, 'root_domain', 1 );
	if ( is_wp_error( $overview ) ) {
		return $overview;
	}
	$out = array(
		'overview'   => $overview[0] ?? array(),
		'refdomains' => fns_semrush_report( 'backlinks_refdomains', $domain, 'root_domain', $limit, array( 'display_sort' => 'domain_ascore_desc' ) ),
		'links'      => fns_semrush_report( 'backlinks', $domain, 'root_domain', $limit, array( 'display_sort' => 'page_ascore_desc' ) ),
		'anchors'    => fns_semrush_report( 'backlinks_anchors', $domain, 'root_domain', 100 ),
		'pages'      => fns_semrush_report( 'backlinks_pages', $domain, 'root_domain', 30 ),
		'top_pages'  => array(),
	);
	foreach ( array_slice( $top_pages, 0, 3 ) as $url ) {
		$ov    = fns_semrush_report( 'backlinks_overview', $url, 'url', 1 );
		$links = fns_semrush_report( 'backlinks', $url, 'url', 100 );
		$out['top_pages'][] = array(
			'url'      => $url,
			'overview' => is_wp_error( $ov ) ? array() : ( $ov[0] ?? array() ),
			'links'    => is_wp_error( $links ) ? array() : $links,
		);
	}
	foreach ( array( 'refdomains', 'links', 'anchors', 'pages' ) as $k ) {
		if ( is_wp_error( $out[ $k ] ) ) {
			$out['errors'][ $k ] = $out[ $k ]->get_error_message();
			$out[ $k ]           = array();
		}
	}
	return $out;
}

/**
 * Approximate API units of one domain (for the settings / run screen).
 */
function fns_semrush_units_estimate() {
	$limit = max( 50, (int) fns_setting( 'semrush_limit', 300 ) );
	// Backlinks reports cost ~40 units per row; overview ~40 per request.
	return 40 * ( 1 + $limit * 2 + 100 + 30 + 3 * ( 1 + 100 ) );
}

/**
 * Connection test with a small overview request.
 */
function fns_semrush_test() {
	if ( '' === trim( (string) fns_setting( 'semrush_key' ) ) ) {
		return array( 'ok' => false, 'msg' => 'کلید API سمراش وارد نشده است.' );
	}
	$was = fns_setting( 'semrush_enabled' );
	fns_update_settings( array( 'semrush_enabled' => 1 ) );
	$r = fns_semrush_report( 'backlinks_overview', 'semrush.com', 'root_domain', 1 );
	if ( ! $was ) {
		fns_update_settings( array( 'semrush_enabled' => 0 ) );
	}
	return is_wp_error( $r ) ? array( 'ok' => false, 'msg' => $r->get_error_message() ) : array( 'ok' => true, 'msg' => 'اتصال موفق؛ Backlinks API در دسترس است.' );
}
