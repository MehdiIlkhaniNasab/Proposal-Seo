<?php
/**
 * Network routing (same rules as Sokan).
 *
 * Sites behind a local proxy (WP_PROXY_HOST, e.g. v2ray) only need it for Google
 * and foreign AI / Semrush APIs; Iranian services (SEO Signal, GapGPT) and the
 * sites being crawled connect directly. «smart» mode does exactly that. When Sokan
 * is active its identical filter is already in place, so ours is not added twice.
 *
 * @package Fanoos
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hosts that need the proxy in smart mode.
 */
function fns_proxy_hosts() {
	return array( 'googleapis.com', 'google.com', 'api.anthropic.com', 'api.openai.com', 'api.semrush.com', 'semrush.com' );
}

function fns_proxy_defined() {
	return defined( 'WP_PROXY_HOST' ) && '' !== (string) WP_PROXY_HOST;
}

function fns_host_in( $host, $patterns ) {
	$host = strtolower( (string) $host );
	foreach ( $patterns as $p ) {
		if ( $host === $p || substr( $host, -strlen( '.' . $p ) ) === '.' . $p ) {
			return true;
		}
	}
	return false;
}

add_action( 'plugins_loaded', function () {
	if ( function_exists( 'sap_proxy_hosts' ) ) {
		return; // Sokan routes requests already.
	}
	add_filter( 'pre_http_send_through_proxy', function ( $override, $uri, $check ) {
		if ( ! fns_proxy_defined() ) {
			return $override;
		}
		if ( ! empty( $GLOBALS['fns_force_direct'] ) ) {
			return false;
		}
		$mode = fns_setting( 'proxy_mode', 'smart' );
		if ( 'all' === $mode ) {
			return $override;
		}
		if ( 'none' === $mode ) {
			return false;
		}
		return fns_host_in( (string) ( $check['host'] ?? '' ), fns_proxy_hosts() );
	}, 10, 3 );
} );

/**
 * Is an HTTP error a dead proxy?
 */
function fns_is_proxy_down( $error ) {
	$msg = is_wp_error( $error ) ? $error->get_error_message() : (string) $error;
	return fns_proxy_defined() && ( false !== strpos( $msg, 'cURL error 7' ) || false !== strpos( $msg, 'cURL error 97' ) ) && false !== strpos( $msg, (string) WP_PROXY_HOST );
}

/**
 * Persian explanation of a network error.
 */
function fns_net_error( $error ) {
	$msg = is_wp_error( $error ) ? $error->get_error_message() : (string) $error;
	if ( fns_is_proxy_down( $msg ) ) {
		return 'پروکسی وردپرس خاموش است؛ نرم‌افزار پروکسی (مثلاً v2rayN) را روشن کنید و دوباره امتحان کنید.';
	}
	if ( false !== strpos( $msg, 'cURL error 28' ) ) {
		return 'مهلت اتصال تمام شد (شبکه کند یا مسدود). دوباره امتحان کنید.';
	}
	if ( false !== strpos( $msg, 'cURL error 6' ) ) {
		return 'نام دامنه پیدا نشد؛ اینترنت سرور یا آدرس را بررسی کنید.';
	}
	return $msg;
}

/**
 * HTTP request that retries once directly when the proxy is down.
 */
function fns_http( $url, $args ) {
	$res = wp_remote_request( $url, $args );
	if ( is_wp_error( $res ) && fns_is_proxy_down( $res ) ) {
		$GLOBALS['fns_force_direct'] = true;
		$res                         = wp_remote_request( $url, $args );
		unset( $GLOBALS['fns_force_direct'] );
	}
	return $res;
}

/**
 * Body of a JSON API answer without BOMs / leading whitespace.
 */
function fns_body( $res ) {
	return preg_replace( '/^(?:\xEF\xBB\xBF|\s)+/', '', (string) wp_remote_retrieve_body( $res ) );
}
