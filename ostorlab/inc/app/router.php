<?php
/**
 * Front-end app: Ostorlab lives at {site}/seo/proposal/ as a standalone,
 * full-screen panel (own login screen, no theme, no admin bar, no wp-admin
 * chrome), next to Sokan at /seo/plan/.
 *
 * Routes (relative to the base):
 *   /                              dashboard (pipeline, follow-ups, all proposals)
 *   /new/                          new proposal
 *   /proposal/{id}/{tab}/          a proposal (summary, market, competitors, strategy, offpage,
 *                                  technical, costs, texts, export, activity)
 *   /proposal/{id}/edit/           edit inputs
 *   /proposal/{id}/deck/           slide preview      /proposal/{id}/pdf/  PDF
 *   /settings/{tab}/               connections, agency, pricing, quality
 *   /guide/                        guide
 *   /view/{id}/{token}/            the client's shareable web view (no login)
 *
 * Without pretty permalinks the same routes work as /?ost_app=1&ost_route=…
 *
 * @package Ostorlab
 */

defined( 'ABSPATH' ) || exit;

const OST_REWRITE_VERSION = '1';

/**
 * Base path of the app, e.g. "seo/proposal". Filterable.
 */
function ost_app_base() {
	return trim( (string) apply_filters( 'ost_app_base', 'seo/proposal' ), '/' );
}

/**
 * App URL of a route.
 */
function ost_url( $route = '', $args = array() ) {
	$route = trim( (string) $route, '/' );
	if ( get_option( 'permalink_structure' ) ) {
		$url = home_url( '/' . ost_app_base() . '/' . ( '' !== $route ? $route . '/' : '' ) );
	} else {
		$url = add_query_arg( array( 'ost_app' => 1, 'ost_route' => $route ), home_url( '/' ) );
	}
	return $args ? add_query_arg( $args, $url ) : $url;
}

/**
 * Proposal screen URL.
 */
function ost_proposal_url( $id, $tab = 'summary', $args = array() ) {
	return ost_url( 'proposal/' . (int) $id . ( 'summary' !== $tab ? '/' . $tab : '' ), $args );
}

/**
 * The client's web view of a proposal.
 */
function ost_share_url( $p ) {
	return ost_url( 'view/' . (int) $p['id'] . '/' . $p['token'] );
}

add_action( 'init', function () {
	add_rewrite_rule( '^' . preg_quote( ost_app_base(), '#' ) . '/?(.*)$', 'index.php?ost_app=1&ost_route=$matches[1]', 'top' );
	// Without Sokan, the bare parent (/seo/) opens Ostorlab.
	$parent = dirname( ost_app_base() );
	if ( '.' !== $parent && '' !== $parent && ! function_exists( 'sap_app_base' ) ) {
		add_rewrite_rule( '^' . preg_quote( $parent, '#' ) . '/?$', 'index.php?ost_app=1&ost_route=', 'top' );
	}
	if ( get_option( 'ost_rewrite_version' ) !== OST_REWRITE_VERSION . '|' . ost_app_base() ) {
		flush_rewrite_rules( false );
		update_option( 'ost_rewrite_version', OST_REWRITE_VERSION . '|' . ost_app_base(), false );
	}
} );

add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'ost_app';
	$vars[] = 'ost_route';
	return $vars;
} );

/**
 * Administrators, and Sokan's SEO managers, have Ostorlab's capability.
 */
add_filter( 'user_has_cap', function ( $all ) {
	if ( ! empty( $all['manage_options'] ) || ! empty( $all['sap_manage'] ) ) {
		$all[ OST_CAP ] = true;
	}
	return $all;
} );

function ost_can() {
	return current_user_can( OST_CAP );
}

/**
 * wp-admin keeps one menu item (and an admin-bar link) that opens the app.
 */
add_action( 'admin_menu', function () {
	add_menu_page( 'اسطرلاب', 'اسطرلاب (پروپوزال)', OST_CAP, 'ostorlab', '__return_null', 'dashicons-chart-pie', 4 );
} );
add_action( 'load-toplevel_page_ostorlab', function () {
	wp_safe_redirect( ost_url() );
	exit;
} );
add_action( 'admin_bar_menu', function ( $bar ) {
	if ( ost_can() ) {
		$bar->add_node( array( 'id' => 'ostorlab', 'title' => 'اسطرلاب', 'href' => ost_url() ) );
	}
}, 81 );

/**
 * Render the app before the theme (and before canonical redirects).
 */
add_action( 'template_redirect', function () {
	if ( ! get_query_var( 'ost_app' ) ) {
		return;
	}
	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow', true );
	status_header( 200 );
	show_admin_bar( false );
	$route = trim( (string) get_query_var( 'ost_route' ), '/' );
	$parts = array_values( array_filter( explode( '/', $route ), 'strlen' ) );

	// The client's web view: token only, no login.
	if ( 'view' === ( $parts[0] ?? '' ) ) {
		$p = ost_proposal( absint( $parts[1] ?? 0 ) );
		if ( ! $p || ! hash_equals( $p['token'], (string) ( $parts[2] ?? '' ) ) || ( ! get_post_meta( $p['id'], '_ost_shared', true ) && ! ost_can() ) || ! ost_data( $p['id'], 'analysis' ) ) {
			status_header( 404 );
			ost_render( 'not-found-public' );
		}
		if ( ! ost_can() ) {
			ost_track_view( $p['id'] );
		}
		echo ost_deck_html( $p['id'], array( 'web' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}
	if ( ! is_user_logged_in() ) {
		ost_render( 'login', array( 'redirect' => ost_url( $route ) ) );
	}
	if ( ! ost_can() ) {
		status_header( 403 );
		ost_render( 'denied' );
	}
	wp_raise_memory_limit( 'admin' );

	// Deck preview / PDF.
	if ( 'proposal' === ( $parts[0] ?? '' ) && in_array( $parts[2] ?? '', array( 'deck', 'pdf' ), true ) ) {
		ost_output_deck( absint( $parts[1] ?? 0 ), 'pdf' === $parts[2] );
	}
	ost_render( 'shell', ost_resolve_route( $parts ) );
}, 1 );

/**
 * Route → view + variables.
 */
function ost_resolve_route( $parts ) {
	$first = $parts[0] ?? '';
	$out   = array( 'nav' => $first ?: 'dashboard', 'proposal' => null );
	switch ( $first ) {
		case 'new':
			return array_merge( $out, array( 'view' => 'form', 'title' => 'پروپوزال جدید', 'vars' => array( 'p' => null ) ) );
		case 'settings':
			$tab = sanitize_key( $parts[1] ?? 'connections' );
			return array_merge( $out, array( 'view' => 'settings', 'title' => 'تنظیمات', 'vars' => array( 'tab' => $tab ) ) );
		case 'guide':
			return array_merge( $out, array( 'view' => 'guide', 'title' => 'راهنما', 'vars' => array() ) );
		case 'proposal':
			$p = ost_proposal( absint( $parts[1] ?? 0 ) );
			if ( $p ) {
				$tab = sanitize_key( $parts[2] ?? 'summary' );
				if ( 'edit' === $tab ) {
					return array_merge( $out, array( 'view' => 'form', 'title' => 'ویرایش ' . $p['title'], 'nav' => 'proposal', 'proposal' => $p, 'vars' => array( 'p' => $p ) ) );
				}
				$tabs = ost_proposal_tabs();
				$tab  = isset( $tabs[ $tab ] ) ? $tab : 'summary';
				return array_merge( $out, array( 'view' => 'proposal', 'title' => $p['title'] . ' — ' . $tabs[ $tab ][0], 'nav' => 'proposal', 'proposal' => $p, 'vars' => array( 'p' => $p, 'tab' => $tab, 'tabs' => $tabs ) ) );
			}
			status_header( 404 );
			return array_merge( $out, array( 'view' => 'not-found', 'title' => 'یافت نشد', 'vars' => array() ) );
		default:
			return array_merge( $out, array( 'view' => 'dashboard', 'title' => 'داشبورد پروپوزال‌ها', 'nav' => 'dashboard', 'vars' => array() ) );
	}
}

/**
 * Deck preview (web) or PDF (Gotenberg, else the print dialog).
 */
function ost_output_deck( $id, $pdf ) {
	$p = ost_proposal( $id );
	if ( ! $p || ! ost_data( $id, 'analysis' ) ) {
		wp_safe_redirect( $p ? ost_proposal_url( $id, 'summary', array( 'ost_msg' => rawurlencode( 'اول تحلیل را اجرا کنید.' ), 'ost_ok' => 0 ) ) : ost_url() );
		exit;
	}
	if ( $pdf ) {
		ost_log( $id, 'PDF پروپوزال گرفته شد' );
		if ( 'gotenberg' === ost_setting( 'pdf_service' ) && empty( $_GET['browser'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$file = ost_pdf_gotenberg( $id );
			if ( ! is_wp_error( $file ) ) {
				header( 'Content-Type: application/pdf' );
				header( 'Content-Disposition: attachment; filename="' . ost_pdf_filename( $p ) . '"' );
				echo $file; // phpcs:ignore WordPress.Security.EscapeOutput
				exit;
			}
		}
	}
	echo ost_deck_html( $id, array( 'print' => $pdf, 'web' => ! $pdf ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	exit;
}

/**
 * Output a full-page app template and stop.
 */
function ost_render( $ost_template, $ost_args = array() ) {
	extract( $ost_args, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract
	include OST_DIR . '/templates/app/' . $ost_template . '.php';
	exit;
}

/**
 * Asset tags for the app document (no theme, no wp_head).
 */
function ost_app_assets() {
	printf( '<link rel="stylesheet" href="%s">' . "\n", esc_url( OST_URL . 'assets/css/app.css?ver=' . OST_VERSION ) );
	printf(
		'<script>window.OST = %s;</script>' . "\n",
		wp_json_encode( array(
			'ajax'  => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'ost_ajax' ),
		) )
	);
	printf( '<script src="%s" defer></script>' . "\n", esc_url( OST_URL . 'assets/js/app.js?ver=' . OST_VERSION ) );
}

/**
 * Login form failures come back to the app's own login screen.
 */
add_action( 'wp_login_failed', function () {
	$to = isset( $_POST['ost_login'], $_POST['redirect_to'] ) ? wp_unslash( $_POST['redirect_to'] ) : ''; // phpcs:ignore
	if ( $to ) {
		wp_safe_redirect( add_query_arg( 'ost_login', 'failed', $to ) );
		exit;
	}
} );
add_filter( 'authenticate', function ( $user, $username, $password ) {
	if ( isset( $_POST['ost_login'], $_POST['redirect_to'] ) && ( '' === (string) $username || '' === (string) $password ) ) { // phpcs:ignore
		wp_safe_redirect( add_query_arg( 'ost_login', 'empty', wp_unslash( $_POST['redirect_to'] ) ) ); // phpcs:ignore
		exit;
	}
	return $user;
}, 30, 3 );
