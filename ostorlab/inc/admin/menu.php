<?php
/**
 * Admin menu, assets and view routing.
 *
 * @package Ostorlab
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', function () {
	$icon = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#a7aaad" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="13.5" r="7.5"/><circle cx="12" cy="13.5" r="4"/><path d="M12 3v2.5M10.5 3h3M5.5 19.5l13-12"/></svg>' );
	add_menu_page( 'اسطرلاب', 'اسطرلاب', OST_CAP, 'ostorlab', 'ost_admin_page', $icon, 27 );
	add_submenu_page( 'ostorlab', 'پروپوزال‌ها', 'پروپوزال‌ها', OST_CAP, 'ostorlab', 'ost_admin_page' );
	add_submenu_page( 'ostorlab', 'پروپوزال جدید', 'پروپوزال جدید', OST_CAP, 'ostorlab-new', 'ost_admin_page' );
	add_submenu_page( 'ostorlab', 'تنظیمات اسطرلاب', 'تنظیمات', OST_CAP, 'ostorlab-settings', 'ost_admin_page' );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( false === strpos( (string) $hook, 'ostorlab' ) ) {
		return;
	}
	wp_enqueue_style( 'ostorlab-admin', OST_URL . 'assets/css/admin.css', array(), OST_VERSION );
	wp_add_inline_style( 'ostorlab-admin', '@font-face{font-family:"Vazirmatn";src:url("' . esc_url( OST_URL . 'assets/fonts/Vazirmatn-Variable.woff2' ) . '") format("woff2");font-weight:100 900;font-display:swap}' );
	wp_enqueue_media();
	wp_enqueue_script( 'ostorlab-admin', OST_URL . 'assets/js/admin.js', array(), OST_VERSION, true );
	wp_localize_script( 'ostorlab-admin', 'OST', array(
		'ajax'  => admin_url( 'admin-ajax.php' ),
		'nonce' => wp_create_nonce( 'ost_ajax' ),
	) );
} );

/**
 * URL of an admin view.
 */
function ost_admin_url( $view = 'list', $args = array() ) {
	$page = array( 'new' => 'ostorlab-new', 'settings' => 'ostorlab-settings' )[ $view ] ?? 'ostorlab';
	$args = array_merge( array( 'page' => $page ), 'list' === $view || 'new' === $view || 'settings' === $view ? array() : array( 'view' => $view ), $args );
	return add_query_arg( $args, admin_url( 'admin.php' ) );
}

/**
 * Signed admin-post URL.
 */
function ost_action_url( $action, $args = array() ) {
	return wp_nonce_url( add_query_arg( array_merge( array( 'action' => $action ), $args ), admin_url( 'admin-post.php' ) ), $action );
}

/**
 * Page router.
 */
function ost_admin_page() {
	if ( ! current_user_can( OST_CAP ) ) {
		return;
	}
	$page = sanitize_key( $_GET['page'] ?? 'ostorlab' ); // phpcs:ignore WordPress.Security.NonceVerification
	$view = sanitize_key( $_GET['view'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	$id   = (int) ( $_GET['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
	$tab  = sanitize_key( $_GET['tab'] ?? 'summary' ); // phpcs:ignore WordPress.Security.NonceVerification
	echo '<div class="ost-wrap" dir="rtl">';
	ost_view( 'admin/header', array( 'page' => $page ) );
	$notice = get_transient( 'ost_notice_' . get_current_user_id() );
	if ( $notice ) {
		delete_transient( 'ost_notice_' . get_current_user_id() );
		echo '<div class="ost-notice ' . esc_attr( $notice['type'] ) . '">' . esc_html( $notice['msg'] ) . '</div>';
	}
	if ( 'ostorlab-settings' === $page ) {
		ost_view( 'admin/settings', array( 'tab' => sanitize_key( $_GET['tab'] ?? 'connections' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
	} elseif ( 'ostorlab-new' === $page || 'edit' === $view ) {
		ost_view( 'admin/form', array( 'p' => $id ? ost_proposal( $id ) : null ) );
	} elseif ( 'proposal' === $view && $id && ost_proposal( $id ) ) {
		ost_view( 'admin/workspace', array( 'p' => ost_proposal( $id ), 'tab' => $tab ) );
	} else {
		ost_view( 'admin/list', array( 'items' => ost_proposals() ) );
	}
	echo '</div>';
}

/**
 * One-shot notice after a redirect.
 */
function ost_notice( $msg, $type = 'ok' ) {
	set_transient( 'ost_notice_' . get_current_user_id(), array( 'msg' => $msg, 'type' => $type ), 60 );
}

/**
 * Status label + class.
 */
function ost_status_label( $status ) {
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
