<?php
/**
 * Form posts (admin-post.php) and AJAX endpoints of the admin UI.
 *
 * @package Ostorlab
 */

defined( 'ABSPATH' ) || exit;

/**
 * Check capability + nonce of an admin-post action.
 */
function ost_guard( $action ) {
	if ( ! current_user_can( OST_CAP ) ) {
		wp_die( 'دسترسی ندارید.' );
	}
	check_admin_referer( $action );
}

function ost_redirect( $url ) {
	wp_safe_redirect( $url );
	exit;
}

/**
 * Posted value (unslashed).
 */
function ost_post( $key, $default = '' ) {
	return isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : $default; // phpcs:ignore WordPress.Security
}

// Create / edit a proposal.
add_action( 'admin_post_ost_save_proposal', function () {
	ost_guard( 'ost_save_proposal' );
	$in = (array) ost_post( 'in', array() );
	if ( count( ost_list( $in['keywords'] ?? '' ) ) < 1 ) {
		ost_notice( 'دست‌کم یک کلمه کلیدی وارد کنید (پیشنهاد: ۵ تا ۷ کلمه).', 'err' );
		ost_redirect( wp_get_referer() );
	}
	$id = ost_save_proposal( (int) ost_post( 'id', 0 ), $in );
	ost_notice( 'ذخیره شد. حالا «شروع تحلیل» را بزنید.' );
	ost_redirect( ost_admin_url( 'proposal', array( 'id' => $id, 'tab' => 'summary', 'autorun' => ost_post( 'run' ) ? 1 : 0 ) ) );
} );

add_action( 'admin_post_ost_delete', function () {
	ost_guard( 'ost_delete' );
	ost_delete_proposal( (int) ( $_GET['id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification
	ost_notice( 'پروپوزال حذف شد.' );
	ost_redirect( ost_admin_url() );
} );

add_action( 'admin_post_ost_duplicate', function () {
	ost_guard( 'ost_duplicate' );
	$id = ost_duplicate_proposal( (int) ( $_GET['id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification
	ost_notice( 'کپی ساخته شد؛ نام کارفرما، دامنه و رقبا را اصلاح کنید.' );
	ost_redirect( ost_admin_url( 'edit', array( 'id' => $id ) ) );
} );

add_action( 'admin_post_ost_status', function () {
	ost_guard( 'ost_status' );
	$id = (int) ( $_GET['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
	$st = sanitize_key( $_GET['status'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( ost_proposal( $id ) && in_array( $st, array( 'ready', 'sent', 'won', 'lost' ), true ) ) {
		update_post_meta( $id, '_ost_status', $st );
	}
	ost_redirect( wp_get_referer() ?: ost_admin_url() );
} );

// Costs tab: overrides of quantities, fees, prices and the selected package.
add_action( 'admin_post_ost_save_budget', function () {
	ost_guard( 'ost_save_budget' );
	$id  = (int) ost_post( 'id' );
	$raw = (array) ost_post( 'b', array() );
	$out = array( 'selected' => array_key_exists( $raw['selected'] ?? '', ost_packages() ) ? $raw['selected'] : 'pro' );
	foreach ( ost_packages() as $k => $pk ) {
		if ( isset( $raw['fee'][ $k ] ) && '' !== trim( $raw['fee'][ $k ] ) ) {
			$out['fee'][ $k ] = ost_to_number( $raw['fee'][ $k ] );
		}
		foreach ( ost_budget_items() as $item => $lab ) {
			if ( isset( $raw['qty'][ $k ][ $item ] ) && '' !== trim( $raw['qty'][ $k ][ $item ] ) ) {
				$out['qty'][ $k ][ $item ] = (int) ost_to_number( $raw['qty'][ $k ][ $item ] );
			}
		}
	}
	foreach ( ost_budget_items() as $item => $lab ) {
		if ( isset( $raw['price'][ $item ] ) && '' !== trim( $raw['price'][ $item ] ) ) {
			$out['price'][ $item ] = ost_to_number( $raw['price'][ $item ] );
		}
	}
	if ( ost_post( 'reset' ) ) {
		$out = array( 'selected' => $out['selected'] );
	}
	update_post_meta( $id, '_ost_budget', $out );
	$months = (int) ost_to_number( ost_post( 'months' ) );
	if ( $months ) {
		$p                     = ost_proposal( $id );
		$p['inputs']['months'] = $months;
		ost_save_proposal( $id, $p['inputs'] );
	}
	ost_compute( $id );
	ost_notice( 'هزینه‌ها و نقشه راه به‌روز شد.' );
	ost_redirect( ost_admin_url( 'proposal', array( 'id' => $id, 'tab' => 'costs' ) ) );
} );

// Strategy tab: pinned and excluded opportunities.
add_action( 'admin_post_ost_save_strategy', function () {
	ost_guard( 'ost_save_strategy' );
	$id    = (int) ost_post( 'id' );
	$picks = array_slice( array_map( 'sanitize_text_field', (array) ost_post( 'pick', array() ) ), 0, 5 );
	$off   = array_map( 'sanitize_text_field', (array) ost_post( 'off', array() ) );
	update_post_meta( $id, '_ost_strategy', ost_post( 'reset' ) ? array() : array( 'picks' => $picks, 'off' => array_values( array_diff( $off, $picks ) ) ) );
	ost_compute( $id );
	ost_notice( 'استراتژی شروع به‌روز شد.' );
	ost_redirect( ost_admin_url( 'proposal', array( 'id' => $id, 'tab' => 'strategy' ) ) );
} );

// Texts tab.
add_action( 'admin_post_ost_save_texts', function () {
	ost_guard( 'ost_save_texts' );
	$id    = (int) ost_post( 'id' );
	$texts = array();
	foreach ( ost_text_fields() as $k => $f ) {
		$texts[ $k ] = sanitize_textarea_field( (string) ost_post( 'text_' . $k ) );
	}
	if ( ost_post( 'reset' ) ) {
		$texts = array();
	}
	update_post_meta( $id, '_ost_texts', $texts );
	ost_notice( ost_post( 'reset' ) ? 'متن‌ها به نسخه خودکار برگشت.' : 'متن‌ها ذخیره شد.' );
	ost_redirect( ost_admin_url( 'proposal', array( 'id' => $id, 'tab' => 'texts' ) ) );
} );

// Export tab: slides on/off and web sharing.
add_action( 'admin_post_ost_save_slides', function () {
	ost_guard( 'ost_save_slides' );
	$id  = (int) ost_post( 'id' );
	$p   = ost_proposal( $id );
	$on  = array_map( 'sanitize_key', (array) ost_post( 'slides', array() ) );
	$all = array_keys( ost_deck_slides( $p, ost_data( $id, 'analysis' ) ) );
	update_post_meta( $id, '_ost_slides', array( 'off' => array_values( array_diff( $all, $on ) ) ) );
	update_post_meta( $id, '_ost_shared', ost_post( 'shared' ) ? 1 : 0 );
	ost_notice( 'تنظیمات خروجی ذخیره شد.' );
	ost_redirect( ost_admin_url( 'proposal', array( 'id' => $id, 'tab' => 'export' ) ) );
} );

// Manual Screaming Frog upload (several files at once).
add_action( 'admin_post_ost_sf_upload', function () {
	ost_guard( 'ost_sf_upload' );
	$id     = (int) ost_post( 'id' );
	$domain = ost_domain( (string) ost_post( 'domain' ) );
	$files  = $_FILES['files'] ?? array(); // phpcs:ignore WordPress.Security
	$done   = array();
	$errors = array();
	foreach ( (array) ( $files['tmp_name'] ?? array() ) as $i => $tmp ) {
		if ( ! $tmp || ! is_uploaded_file( $tmp ) ) {
			continue;
		}
		$r = ost_sf_import( $id, $domain, $tmp, (string) $files['name'][ $i ] );
		if ( is_wp_error( $r ) ) {
			$errors[] = $files['name'][ $i ] . ': ' . $r->get_error_message();
		} else {
			$done[] = $r['kind'];
		}
	}
	ost_compute( $id );
	ost_notice( $errors ? implode( ' | ', $errors ) : sprintf( '%s فایل برای %s وارد شد (%s).', ost_num( count( $done ) ), $domain, implode( '، ', $done ) ), $errors ? 'err' : 'ok' );
	ost_redirect( ost_admin_url( 'proposal', array( 'id' => $id, 'tab' => 'technical' ) ) );
} );

// Screaming Frog agent script download.
add_action( 'admin_post_ost_agent', function () {
	ost_guard( 'ost_agent' );
	$p  = ost_proposal( (int) ( $_GET['id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$os = 'win' === ( $_GET['os'] ?? '' ) ? 'win' : 'sh'; // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! $p ) {
		wp_die( 'پروپوزال پیدا نشد.' );
	}
	$name = 'ostorlab-sf-' . preg_replace( '/[^\w.-]/', '', $p['inputs']['domain'] ?: 'proposal-' . $p['id'] ) . ( 'win' === $os ? '.ps1' : '.sh' );
	nocache_headers();
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $name . '"' );
	echo ost_sf_agent_script( $p, $os ); // phpcs:ignore WordPress.Security.EscapeOutput
	exit;
} );

// PDF export.
add_action( 'admin_post_ost_pdf', function () {
	ost_guard( 'ost_pdf' );
	$id = (int) ( $_GET['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
	$p  = ost_proposal( $id );
	if ( ! $p || ! ost_data( $id, 'analysis' ) ) {
		wp_die( 'اول تحلیل را اجرا کنید.' );
	}
	if ( 'gotenberg' === ost_setting( 'pdf_service' ) && empty( $_GET['browser'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$pdf = ost_pdf_gotenberg( $id );
		if ( ! is_wp_error( $pdf ) ) {
			nocache_headers();
			header( 'Content-Type: application/pdf' );
			header( 'Content-Disposition: attachment; filename="' . ost_pdf_filename( $p ) . '"' );
			echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput
			exit;
		}
		ost_notice( $pdf->get_error_message() . ' — از خروجی مرورگر استفاده شد.', 'err' );
	}
	nocache_headers();
	echo ost_deck_html( $id, array( 'print' => empty( $_GET['preview'] ), 'web' => ! empty( $_GET['preview'] ) ) ); // phpcs:ignore
	exit;
} );

// Settings.
add_action( 'admin_post_ost_save_settings', function () {
	ost_guard( 'ost_save_settings' );
	$raw     = (array) ost_post( 's', array() );
	$changes = array();
	$defs    = ost_settings_defaults();
	foreach ( $raw as $k => $v ) {
		if ( ! array_key_exists( $k, $defs ) ) {
			continue;
		}
		if ( in_array( $k, OST_SECRET_KEYS, true ) && ( '' === trim( (string) $v ) || false !== strpos( (string) $v, '••••' ) ) ) {
			continue; // Empty or masked: keep the stored secret.
		}
		if ( is_int( $defs[ $k ] ) || is_float( $defs[ $k ] ) ) {
			$v = ost_to_number( $v );
		} elseif ( in_array( $k, array( 'portfolio', 'agency_about', 'generic_anchors', 'spam_patterns', 'iran_tlds', 'google_service_account', 'agency_stats', 'team', 'consultant_bio' ), true ) ) {
			$v = 'google_service_account' === $k ? trim( (string) $v ) : sanitize_textarea_field( (string) $v );
		} else {
			$v = sanitize_text_field( (string) $v );
		}
		$changes[ $k ] = $v;
	}
	foreach ( (array) ost_post( 'clear', array() ) as $k ) {
		if ( in_array( $k, OST_SECRET_KEYS, true ) ) {
			$changes[ $k ] = '';
		}
	}
	foreach ( array( 'reuse_sokan', 'semrush_enabled' ) as $flag ) {
		if ( ost_post( 'has_' . $flag ) ) {
			$changes[ $flag ] = empty( $raw[ $flag ] ) ? 0 : 1;
		}
	}
	ost_update_settings( $changes );
	delete_transient( 'ost_google_token' );
	ost_notice( 'تنظیمات ذخیره شد.' );
	ost_redirect( add_query_arg( 'tab', sanitize_key( ost_post( 'tab', 'connections' ) ), ost_admin_url( 'settings' ) ) );
} );

/**
 * AJAX guard.
 */
function ost_ajax_guard() {
	if ( ! current_user_can( OST_CAP ) || ! check_ajax_referer( 'ost_ajax', 'nonce', false ) ) {
		wp_send_json_error( array( 'msg' => 'دسترسی ندارید یا صفحه منقضی شده؛ صفحه را تازه کنید.' ), 403 );
	}
}

// Start (or restart) the analysis job.
add_action( 'wp_ajax_ost_job_start', function () {
	ost_ajax_guard();
	$p = ost_proposal( (int) ost_post( 'id' ) );
	if ( ! $p ) {
		wp_send_json_error( array( 'msg' => 'پروپوزال پیدا نشد.' ) );
	}
	if ( ! ost_ss_connected() ) {
		wp_send_json_error( array( 'msg' => 'اول کلید سئو سیگنال را در تنظیمات وارد کنید.' ) );
	}
	wp_send_json_success( ost_job_plan( $p, (bool) ost_post( 'fresh' ) ) );
} );

// Run the next step.
add_action( 'wp_ajax_ost_job_tick', function () {
	ost_ajax_guard();
	$id = (int) ost_post( 'id' );
	if ( ! ost_proposal( $id ) ) {
		wp_send_json_error( array( 'msg' => 'پروپوزال پیدا نشد.' ) );
	}
	if ( ost_post( 'resume' ) ) {
		$job = (array) get_post_meta( $id, '_ost_job', true );
		$job['status'] = 'running';
		update_post_meta( $id, '_ost_job', $job );
	}
	wp_send_json_success( ost_job_tick( $id ) );
} );

// Rewrite texts with AI only.
add_action( 'wp_ajax_ost_ai_texts', function () {
	ost_ajax_guard();
	$id = (int) ost_post( 'id' );
	$p  = ost_proposal( $id );
	$t  = ost_writer_ai( $p, ost_data( $id, 'analysis' ) );
	if ( is_wp_error( $t ) ) {
		wp_send_json_error( array( 'msg' => $t->get_error_message() ) );
	}
	update_post_meta( $id, '_ost_ai_texts', $t );
	ost_compute( $id );
	wp_send_json_success( array( 'msg' => 'متن‌ها با هوش مصنوعی بازنویسی شد.' ) );
} );

// Connection tests.
add_action( 'wp_ajax_ost_test', function () {
	ost_ajax_guard();
	$svc = sanitize_key( ost_post( 'svc' ) );
	if ( 'seosignal' === $svc ) {
		$r = ost_ss_test();
	} elseif ( 'ai' === $svc ) {
		$r = ost_ai_test();
	} elseif ( 'semrush' === $svc ) {
		$r = ost_semrush_test();
	} elseif ( 'google' === $svc ) {
		$s = ost_gsc_sites();
		$r = is_wp_error( $s ) ? array( 'ok' => false, 'msg' => $s->get_error_message() ) : array( 'ok' => true, 'msg' => sprintf( 'اتصال موفق؛ %s پراپرتی در دسترس: %s', ost_num( count( $s ) ), implode( '، ', array_slice( $s, 0, 6 ) ) ) );
	} elseif ( 'pdf' === $svc ) {
		$res = wp_remote_get( untrailingslashit( (string) ost_setting( 'pdf_service_url' ) ) . '/health', array( 'timeout' => 15 ) );
		$r   = is_wp_error( $res ) ? array( 'ok' => false, 'msg' => ost_net_error( $res ) ) : array( 'ok' => 200 === (int) wp_remote_retrieve_response_code( $res ), 'msg' => 'HTTP ' . wp_remote_retrieve_response_code( $res ) );
	} else {
		$r = array( 'ok' => false, 'msg' => 'سرویس نامعتبر' );
	}
	wp_send_json_success( $r );
} );
