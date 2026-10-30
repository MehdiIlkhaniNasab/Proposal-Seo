<?php
/**
 * REST endpoints for the Screaming Frog agent and the shareable web view.
 *
 *   GET  /wp-json/ostorlab/v1/sf/{id}/targets   domains to crawl (client + competitors)
 *   GET  /wp-json/ostorlab/v1/sf/{id}/urls      URL list of a domain (from its sitemaps)
 *   POST /wp-json/ostorlab/v1/sf/{id}?domain=   upload an export (Internal All / All Inlinks / Redirects, plain or .gz)
 *
 * The agent authenticates with the proposal's token in the X-Ostorlab-Key header.
 * The client's web view of a proposal is /seo/proposal/view/{id}/{token}/ (inc/app/router.php).
 *
 * @package Ostorlab
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', function () {
	$auth = function ( WP_REST_Request $req ) {
		$p = ost_proposal( (int) $req['id'] );
		return $p && hash_equals( $p['token'], (string) $req->get_header( 'x_ostorlab_key' ) );
	};
	register_rest_route( 'ostorlab/v1', '/sf/(?P<id>\d+)/targets', array(
		'methods'             => 'GET',
		'permission_callback' => $auth,
		'callback'            => function ( WP_REST_Request $req ) {
			$p = ost_proposal( (int) $req['id'] );
			$t = array();
			foreach ( ost_domains( $p ) as $d => $role ) {
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
	register_rest_route( 'ostorlab/v1', '/sf/(?P<id>\d+)/ping', array(
		'methods'             => 'POST',
		'permission_callback' => $auth,
		'callback'            => function ( WP_REST_Request $req ) {
			$data = $req->get_json_params() ?: $req->get_body_params();
			$ping = ost_sf_record_ping( (int) $req['id'], (array) $data );
			if ( 'done' === $ping['stage'] ) {
				ost_compute( (int) $req['id'] );
				ost_log( (int) $req['id'], 'خزش اسکریمینگ فراگ کامل شد و تحلیل تکنیکال به‌روز شد' );
			}
			return array( 'ok' => true );
		},
	) );
	register_rest_route( 'ostorlab/v1', '/sf/(?P<id>\d+)/urls', array(
		'methods'             => 'GET',
		'permission_callback' => $auth,
		'callback'            => function ( WP_REST_Request $req ) {
			$p      = ost_proposal( (int) $req['id'] );
			$domain = ost_domain( (string) $req->get_param( 'domain' ) );
			$audit  = ost_data( $p['id'], 'audit' )[ $domain ] ?? null;
			if ( ! $audit ) {
				$audit = ost_site_audit( $domain, 3, (int) $p['inputs']['crawl_limit'] );
				if ( ! is_wp_error( $audit ) ) {
					ost_put_data( $p['id'], 'audit', $domain, $audit );
				}
			}
			$urls = is_wp_error( $audit ) ? array() : (array) $audit['urls'];
			array_unshift( $urls, ost_home_url( $domain ) );
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo implode( "\n", array_slice( array_values( array_unique( $urls ) ), 0, (int) $p['inputs']['crawl_limit'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			exit;
		},
	) );
	register_rest_route( 'ostorlab/v1', '/sf/(?P<id>\d+)', array(
		'methods'             => 'POST',
		'permission_callback' => $auth,
		'callback'            => function ( WP_REST_Request $req ) {
			$files = $req->get_file_params();
			if ( empty( $files['file']['tmp_name'] ) ) {
				return new WP_Error( 'ost_sf_nofile', 'فایلی ارسال نشد.', array( 'status' => 400 ) );
			}
			$domain = ost_domain( (string) $req->get_param( 'domain' ) );
			$p      = ost_proposal( (int) $req['id'] );
			if ( ! isset( ost_domains( $p )[ $domain ] ) ) {
				return new WP_Error( 'ost_sf_domain', 'این دامنه در پروپوزال نیست.', array( 'status' => 400 ) );
			}
			$name = (string) ( $req->get_param( 'name' ) ?: $files['file']['name'] );
			$r    = ost_sf_import( $p['id'], $domain, $files['file']['tmp_name'], $name );
			if ( is_wp_error( $r ) ) {
				return new WP_Error( $r->get_error_code(), $r->get_error_message(), array( 'status' => 422 ) );
			}
			ost_compute( $p['id'] );
			return array( 'ok' => true, 'domain' => $domain, 'kind' => $r['kind'] );
		},
	) );
} );
