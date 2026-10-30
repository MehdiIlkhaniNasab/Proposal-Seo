<?php
/**
 * Google Search Console (only when the client gave access).
 *
 * Two ways to connect:
 *   1. A service account JSON in Fanoos settings; the client adds its e-mail as a
 *      user of the property in Search Console (read access is enough).
 *   2. Sokan's Google connection, when Sokan runs on the same site.
 *
 * @package Fanoos
 */

defined( 'ABSPATH' ) || exit;

const FNS_GOOGLE_SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';

/**
 * Which connection is used: own | sokan | ''.
 */
function fns_google_mode() {
	if ( '' !== (string) fns_setting( 'google_service_account' ) ) {
		return 'own';
	}
	if ( fns_setting( 'reuse_sokan' ) && function_exists( 'sap_google_connected' ) && sap_google_connected() ) {
		return 'sokan';
	}
	return '';
}

function fns_google_connected() {
	return '' !== fns_google_mode();
}

/**
 * Service account e-mail (the client adds it in Search Console › Users).
 */
function fns_google_service_email() {
	$json = json_decode( (string) fns_setting( 'google_service_account' ), true );
	if ( ! empty( $json['client_email'] ) ) {
		return (string) $json['client_email'];
	}
	return function_exists( 'sap_google_service_email' ) ? sap_google_service_email() : '';
}

/**
 * Access token from the service account (signed JWT).
 */
function fns_google_token() {
	$cached = get_transient( 'fns_google_token' );
	if ( $cached ) {
		return $cached;
	}
	$json = json_decode( (string) fns_setting( 'google_service_account' ), true );
	if ( empty( $json['client_email'] ) || empty( $json['private_key'] ) ) {
		return new WP_Error( 'fns_sa', 'فایل JSON سرویس اکانت گوگل معتبر نیست.' );
	}
	$b64    = function ( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	};
	$now    = time();
	$header = $b64( wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) );
	$claims = $b64( wp_json_encode( array(
		'iss'   => $json['client_email'],
		'scope' => FNS_GOOGLE_SCOPE,
		'aud'   => 'https://oauth2.googleapis.com/token',
		'iat'   => $now,
		'exp'   => $now + 3600,
	) ) );
	$sig = '';
	if ( ! openssl_sign( $header . '.' . $claims, $sig, $json['private_key'], 'sha256WithRSAEncryption' ) ) {
		return new WP_Error( 'fns_sa_sign', 'امضای JWT ناموفق بود (کلید خصوصی نامعتبر).' );
	}
	$res = fns_http( 'https://oauth2.googleapis.com/token', array(
		'method'  => 'POST',
		'timeout' => 20,
		'body'    => array(
			'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
			'assertion'  => $header . '.' . $claims . '.' . $b64( $sig ),
		),
	) );
	if ( is_wp_error( $res ) ) {
		return new WP_Error( 'fns_google_net', 'اتصال به گوگل ناموفق بود: ' . fns_net_error( $res ) );
	}
	$token = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( empty( $token['access_token'] ) ) {
		return new WP_Error( 'fns_google_token', 'دریافت توکن گوگل ناموفق بود: ' . ( $token['error_description'] ?? $token['error'] ?? 'unknown' ) );
	}
	set_transient( 'fns_google_token', $token['access_token'], 3300 );
	return $token['access_token'];
}

/**
 * Authorized JSON request to a Google API.
 */
function fns_google_request( $method, $url, $body = null ) {
	if ( 'sokan' === fns_google_mode() ) {
		return sap_google_request( $method, $url, $body, 90 );
	}
	$token = fns_google_token();
	if ( is_wp_error( $token ) ) {
		return $token;
	}
	$args = array(
		'method'  => $method,
		'timeout' => 90,
		'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ),
	);
	if ( null !== $body ) {
		$args['body'] = wp_json_encode( $body );
	}
	$res = fns_http( $url, $args );
	if ( is_wp_error( $res ) ) {
		return new WP_Error( 'fns_google_net', 'اتصال به گوگل ناموفق بود: ' . fns_net_error( $res ) );
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	$data = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( $code >= 400 ) {
		if ( 403 === $code ) {
			return new WP_Error( 'fns_google_403', sprintf( 'سرچ کنسول اجازه دسترسی نداد. از کارفرما بخواهید ایمیل %s را در Search Console › Settings › Users and permissions اضافه کند.', fns_google_service_email() ?: 'سرویس اکانت' ) );
		}
		return new WP_Error( 'fns_google_http', 'خطای API گوگل: ' . ( $data['error']['message'] ?? 'HTTP ' . $code ) );
	}
	return is_array( $data ) ? $data : array();
}

/**
 * Properties this connection can read.
 *
 * @return string[]|WP_Error
 */
function fns_gsc_sites() {
	$data = fns_google_request( 'GET', 'https://www.googleapis.com/webmasters/v3/sites' );
	if ( is_wp_error( $data ) ) {
		return $data;
	}
	$out = array();
	foreach ( (array) ( $data['siteEntry'] ?? array() ) as $s ) {
		if ( 'siteUnverifiedUser' !== ( $s['permissionLevel'] ?? '' ) ) {
			$out[] = (string) $s['siteUrl'];
		}
	}
	return $out;
}

/**
 * Property of a domain: the one given, or found among the accessible ones.
 *
 * @return string|WP_Error
 */
function fns_gsc_property( $domain, $given = '' ) {
	if ( '' !== trim( (string) $given ) ) {
		return trim( (string) $given );
	}
	$sites = fns_gsc_sites();
	if ( is_wp_error( $sites ) ) {
		return $sites;
	}
	$domain = fns_domain( $domain );
	foreach ( array( 'sc-domain:' . $domain, 'https://' . $domain . '/', 'https://www.' . $domain . '/', 'http://' . $domain . '/', 'http://www.' . $domain . '/' ) as $cand ) {
		if ( in_array( $cand, $sites, true ) ) {
			return $cand;
		}
	}
	return new WP_Error( 'fns_gsc_none', sprintf( 'پراپرتی سرچ کنسول %s در دسترس این اتصال نیست. از کارفرما بخواهید ایمیل %s را به کاربران پراپرتی اضافه کند.', $domain, fns_google_service_email() ?: 'سرویس اکانت' ) );
}

/**
 * Search Analytics query.
 */
function fns_gsc_query( $site, $start, $end, $dimensions, $limit = 1000 ) {
	$data = fns_google_request( 'POST', 'https://www.googleapis.com/webmasters/v3/sites/' . rawurlencode( $site ) . '/searchAnalytics/query', array(
		'startDate'  => $start,
		'endDate'    => $end,
		'dimensions' => $dimensions,
		'rowLimit'   => $limit,
		'type'       => 'web',
	) );
	if ( is_wp_error( $data ) ) {
		return $data;
	}
	$rows = array();
	foreach ( (array) ( $data['rows'] ?? array() ) as $r ) {
		$row = array();
		foreach ( $dimensions as $i => $dim ) {
			$row[ $dim ] = $r['keys'][ $i ] ?? '';
		}
		$row['clicks']      = (int) $r['clicks'];
		$row['impressions'] = (int) $r['impressions'];
		$row['position']    = round( (float) $r['position'], 1 );
		$rows[]             = $row;
	}
	return $rows;
}

/**
 * Everything the proposal shows from Search Console.
 *
 * @return array|WP_Error
 */
function fns_gsc_collect( $domain, $property, $brand_terms ) {
	$site = fns_gsc_property( $domain, $property );
	if ( is_wp_error( $site ) ) {
		return $site;
	}
	$end   = gmdate( 'Y-m-d', strtotime( '-3 days' ) );
	$start = gmdate( 'Y-m-d', strtotime( '-90 days', strtotime( $end ) ) );
	$long  = gmdate( 'Y-m-01', strtotime( '-15 months', strtotime( $end ) ) );
	$days  = fns_gsc_query( $site, $long, $end, array( 'date' ), 600 );
	if ( is_wp_error( $days ) ) {
		return $days;
	}
	$months = array();
	foreach ( $days as $d ) {
		$m = substr( $d['date'], 0, 7 );
		$months[ $m ]['clicks']      = ( $months[ $m ]['clicks'] ?? 0 ) + $d['clicks'];
		$months[ $m ]['impressions'] = ( $months[ $m ]['impressions'] ?? 0 ) + $d['impressions'];
	}
	ksort( $months );
	$queries = fns_gsc_query( $site, $start, $end, array( 'query' ), 2000 );
	$pages   = fns_gsc_query( $site, $start, $end, array( 'page' ), 500 );
	$queries = is_wp_error( $queries ) ? array() : $queries;
	$pages   = is_wp_error( $pages ) ? array() : $pages;
	$brand   = array_filter( array_map( 'fns_norm', (array) $brand_terms ) );
	$split   = array( 'brand' => 0, 'nonbrand' => 0 );
	$strike  = array();
	foreach ( $queries as $q ) {
		$is_brand = false;
		foreach ( $brand as $b ) {
			if ( '' !== $b && false !== mb_strpos( fns_norm( $q['query'] ), $b ) ) {
				$is_brand = true;
				break;
			}
		}
		$split[ $is_brand ? 'brand' : 'nonbrand' ] += $q['clicks'];
		if ( ! $is_brand && $q['position'] > 3 && $q['position'] <= 20 && $q['impressions'] >= 50 ) {
			$strike[] = $q;
		}
	}
	usort( $strike, function ( $a, $b ) {
		return $b['impressions'] <=> $a['impressions'];
	} );
	$sitemaps = fns_google_request( 'GET', 'https://www.googleapis.com/webmasters/v3/sites/' . rawurlencode( $site ) . '/sitemaps' );
	$tot      = array( 'clicks' => array_sum( array_column( $queries, 'clicks' ) ), 'impressions' => array_sum( array_column( $queries, 'impressions' ) ) );
	return array(
		'property' => $site,
		'range'    => array( $start, $end ),
		'months'   => $months,
		'totals'   => array(
			'clicks'      => array_sum( array_column( $days, 'clicks' ) ) ? array_sum( array_map( function ( $d ) use ( $start ) {
				return $d['date'] >= $start ? $d['clicks'] : 0;
			}, $days ) ) : $tot['clicks'],
			'impressions' => array_sum( array_map( function ( $d ) use ( $start ) {
				return $d['date'] >= $start ? $d['impressions'] : 0;
			}, $days ) ),
			'queries'     => count( $queries ),
			'pages'       => count( $pages ),
		),
		'brand'    => $split,
		'queries'  => array_slice( $queries, 0, 300 ),
		'pages'    => array_slice( $pages, 0, 100 ),
		'strike'   => array_slice( $strike, 0, 50 ),
		'sitemaps' => is_wp_error( $sitemaps ) ? array() : array_map( function ( $s ) {
			return array( 'path' => $s['path'] ?? '', 'errors' => (int) ( $s['errors'] ?? 0 ), 'warnings' => (int) ( $s['warnings'] ?? 0 ) );
		}, (array) ( $sitemaps['sitemap'] ?? array() ) ),
	);
}
