<?php
/**
 * Admin menu, assets and view routing.
 *
 * @package Fanoos
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', function () {
	$icon = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#a7aaad" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21h6M10 17h4l1-4H9z"/><path d="M8 13 7 6h10l-1 7"/><path d="M12 6V3M4 4l2 2M20 4l-2 2"/></svg>' );
	add_menu_page( 'فانوس', 'فانوس', FNS_CAP, 'fanoos', 'fns_admin_page', $icon, 27 );
	add_submenu_page( 'fanoos', 'پروپوزال‌ها', 'پروپوزال‌ها', FNS_CAP, 'fanoos', 'fns_admin_page' );
	add_submenu_page( 'fanoos', 'پروپوزال جدید', 'پروپوزال جدید', FNS_CAP, 'fanoos-new', 'fns_admin_page' );
	add_submenu_page( 'fanoos', 'تنظیمات فانوس', 'تنظیمات', FNS_CAP, 'fanoos-settings', 'fns_admin_page' );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( false === strpos( (string) $hook, 'fanoos' ) ) {
		return;
	}
	wp_enqueue_style( 'fanoos-admin', FNS_URL . 'assets/css/admin.css', array(), FNS_VERSION );
	wp_add_inline_style( 'fanoos-admin', '@font-face{font-family:"Vazirmatn";src:url("' . esc_url( FNS_URL . 'assets/fonts/Vazirmatn-Variable.woff2' ) . '") format("woff2");font-weight:100 900;font-display:swap}' );
	wp_enqueue_media();
	wp_enqueue_script( 'fanoos-admin', FNS_URL . 'assets/js/admin.js', array(), FNS_VERSION, true );
	wp_localize_script( 'fanoos-admin', 'FNS', array(
		'ajax'  => admin_url( 'admin-ajax.php' ),
		'nonce' => wp_create_nonce( 'fns_ajax' ),
	) );
} );

/**
 * URL of an admin view.
 */
function fns_admin_url( $view = 'list', $args = array() ) {
	$page = array( 'new' => 'fanoos-new', 'settings' => 'fanoos-settings' )[ $view ] ?? 'fanoos';
	$args = array_merge( array( 'page' => $page ), 'list' === $view || 'new' === $view || 'settings' === $view ? array() : array( 'view' => $view ), $args );
	return add_query_arg( $args, admin_url( 'admin.php' ) );
}

/**
 * Signed admin-post URL.
 */
function fns_action_url( $action, $args = array() ) {
	return wp_nonce_url( add_query_arg( array_merge( array( 'action' => $action ), $args ), admin_url( 'admin-post.php' ) ), $action );
}

/**
 * Page router.
 */
function fns_admin_page() {
	if ( ! current_user_can( FNS_CAP ) ) {
		return;
	}
	$page = sanitize_key( $_GET['page'] ?? 'fanoos' ); // phpcs:ignore WordPress.Security.NonceVerification
	$view = sanitize_key( $_GET['view'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	$id   = (int) ( $_GET['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
	$tab  = sanitize_key( $_GET['tab'] ?? 'summary' ); // phpcs:ignore WordPress.Security.NonceVerification
	echo '<div class="fns-wrap" dir="rtl">';
	fns_view( 'admin/header', array( 'page' => $page ) );
	$notice = get_transient( 'fns_notice_' . get_current_user_id() );
	if ( $notice ) {
		delete_transient( 'fns_notice_' . get_current_user_id() );
		echo '<div class="fns-notice ' . esc_attr( $notice['type'] ) . '">' . esc_html( $notice['msg'] ) . '</div>';
	}
	if ( 'fanoos-settings' === $page ) {
		fns_view( 'admin/settings', array( 'tab' => sanitize_key( $_GET['tab'] ?? 'connections' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
	} elseif ( 'fanoos-new' === $page || 'edit' === $view ) {
		fns_view( 'admin/form', array( 'p' => $id ? fns_proposal( $id ) : null ) );
	} elseif ( 'proposal' === $view && $id && fns_proposal( $id ) ) {
		fns_view( 'admin/workspace', array( 'p' => fns_proposal( $id ), 'tab' => $tab ) );
	} else {
		fns_view( 'admin/list', array( 'items' => fns_proposals() ) );
	}
	echo '</div>';
}

/**
 * One-shot notice after a redirect.
 */
function fns_notice( $msg, $type = 'ok' ) {
	set_transient( 'fns_notice_' . get_current_user_id(), array( 'msg' => $msg, 'type' => $type ), 60 );
}

/**
 * Status label + class.
 */
function fns_status_label( $status ) {
	return array(
		'draft'   => array( 'پیش‌نویس', 'gray' ),
		'running' => array( 'در حال تحلیل', 'blue' ),
		'paused'  => array( 'متوقف (سقف روزانه)', 'amber' ),
		'ready'   => array( 'آماده', 'green' ),
		'sent'    => array( 'ارسال‌شده', 'navy' ),
		'won'     => array( 'قرارداد بسته شد', 'green' ),
		'lost'    => array( 'رد شد', 'red' ),
	)[ $status ] ?? array( $status, 'gray' );
}
