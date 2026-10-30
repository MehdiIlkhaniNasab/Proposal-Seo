<?php
/**
 * REST endpoints for the Screaming Frog agent and the shareable web view.
 *
 *   GET  /wp-json/fanoos/v1/sf/{id}/targets   domains to crawl (client + competitors)
 *   GET  /wp-json/fanoos/v1/sf/{id}/urls      URL list of a domain (from its sitemaps)
 *   POST /wp-json/fanoos/v1/sf/{id}?domain=   upload an export (Internal All / All Inlinks / Redirects, plain or .gz)
 *
 * The agent authenticates with the proposal's token in the X-Fanoos-Key header.
 * The web view of a proposal is /?fanoos_deck={id}&t={token}.
 *
 * @package Fanoos
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', function () {
	$auth = function ( WP_REST_Request $req ) {
		$p = fns_proposal( (int) $req['id'] );
		return $p && hash_equals( $p['token'], (string) $req->get_header( 'x_fanoos_key' ) );
	};
	register_rest_route( 'fanoos/v1', '/sf/(?P<id>\d+)/targets', array(
		'methods'             => 'GET',
		'permission_callback' => $auth,
		'callback'            => function ( WP_REST_Request $req ) {
			$p = fns_proposal( (int) $req['id'] );
			$t = array();
			foreach ( fns_domains( $p ) as $d => $role ) {
				$t[] = array( 'domain' => $d, 'role' => $role );
			}
			if ( 'text' === $req->get_param( 'format' ) ) {
				header( 'Content-Type: text/plain; charset=utf-8' );
				echo implode( "\n", array_column( $t, 'domain' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				exit;
			}
			return array( 'targets' => $t );
		},
	) );
	register_rest_route( 'fanoos/v1', '/sf/(?P<id>\d+)/urls', array(
		'methods'             => 'GET',
		'permission_callback' => $auth,
		'callback'            => function ( WP_REST_Request $req ) {
			$p      = fns_proposal( (int) $req['id'] );
			$domain = fns_domain( (string) $req->get_param( 'domain' ) );
			$audit  = fns_data( $p['id'], 'audit' )[ $domain ] ?? null;
			if ( ! $audit ) {
				$audit = fns_site_audit( $domain, 3, (int) $p['inputs']['crawl_limit'] );
				if ( ! is_wp_error( $audit ) ) {
					fns_put_data( $p['id'], 'audit', $domain, $audit );
				}
			}
			$urls = is_wp_error( $audit ) ? array() : (array) $audit['urls'];
			array_unshift( $urls, fns_home_url( $domain ) );
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo implode( "\n", array_slice( array_values( array_unique( $urls ) ), 0, (int) $p['inputs']['crawl_limit'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			exit;
		},
	) );
	register_rest_route( 'fanoos/v1', '/sf/(?P<id>\d+)', array(
		'methods'             => 'POST',
		'permission_callback' => $auth,
		'callback'            => function ( WP_REST_Request $req ) {
			$files = $req->get_file_params();
			if ( empty( $files['file']['tmp_name'] ) ) {
				return new WP_Error( 'fns_sf_nofile', 'فایلی ارسال نشد.', array( 'status' => 400 ) );
			}
			$domain = fns_domain( (string) $req->get_param( 'domain' ) );
			$p      = fns_proposal( (int) $req['id'] );
			if ( ! isset( fns_domains( $p )[ $domain ] ) ) {
				return new WP_Error( 'fns_sf_domain', 'این دامنه در پروپوزال نیست.', array( 'status' => 400 ) );
			}
			$name = (string) ( $req->get_param( 'name' ) ?: $files['file']['name'] );
			$r    = fns_sf_import( $p['id'], $domain, $files['file']['tmp_name'], $name );
			if ( is_wp_error( $r ) ) {
				return new WP_Error( $r->get_error_code(), $r->get_error_message(), array( 'status' => 422 ) );
			}
			fns_compute( $p['id'] );
			return array( 'ok' => true, 'domain' => $domain, 'kind' => $r['kind'] );
		},
	) );
} );

/**
 * Web view of a proposal (shareable link with the token).
 */
add_action( 'template_redirect', function () {
	if ( empty( $_GET['fanoos_deck'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	$id = (int) $_GET['fanoos_deck']; // phpcs:ignore WordPress.Security.NonceVerification
	$p  = fns_proposal( $id );
	$t  = sanitize_text_field( wp_unslash( $_GET['t'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! $p || ( ! hash_equals( $p['token'], $t ) && ! current_user_can( FNS_CAP ) ) || empty( get_post_meta( $id, '_fns_shared', true ) ) && ! current_user_can( FNS_CAP ) ) {
		status_header( 404 );
		exit;
	}
	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow' );
	echo fns_deck_html( $id, array( 'web' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	exit;
} );
