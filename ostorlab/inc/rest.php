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
			$t = ost_sf_targets( $p, (bool) $req->get_param( 'force' ) );
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
			try {
				$urls = ost_sf_important_urls( $p, $domain )['urls'];
			} catch ( Throwable $e ) {
				$urls = array( ost_home_url( $domain ) ); // Whatever happens, the agent gets the home page.
			}
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo implode( "\n", $urls ); // phpcs:ignore WordPress.Security.EscapeOutput
			exit;
		},
	) );
	register_rest_route( 'ostorlab/v1', '/sf/(?P<id>\d+)', array(
		'methods'             => 'POST',
		'permission_callback' => $auth,
		'callback'            => function ( WP_REST_Request $req ) {
			if ( function_exists( 'set_time_limit' ) ) {
				@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}
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
			try {
				$r = ost_sf_import( $p['id'], $domain, $files['file']['tmp_name'], $name );
			} catch ( Throwable $e ) {
				$r = new WP_Error( 'ost_sf_import', 'خواندن فایل ناموفق بود: ' . $e->getMessage() );
			}
			if ( is_wp_error( $r ) ) {
				return new WP_Error( $r->get_error_code(), $r->get_error_message(), array( 'status' => 422 ) );
			}
			ost_compute( $p['id'] );
			return array( 'ok' => true, 'domain' => $domain, 'kind' => $r['kind'] );
		},
	) );
} );
