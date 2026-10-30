<?php
/**
 * App helpers: tabs, statuses and sales pipeline, activity log, client-view
 * tracking, follow-ups, flash messages and form helpers.
 *
 * @package Ostorlab
 */

defined( 'ABSPATH' ) || exit;

/**
 * Proposal tabs: key => [label, icon].
 */
function ost_proposal_tabs() {
	return array(
		'summary'     => array( 'خلاصه و اجرا', 'play' ),
		'discovery'   => array( 'شناخت کسب‌وکار', 'user' ),
		'keywords'    => array( 'بازبینی کلمات', 'grid' ),
		'market'      => array( 'بازار و کلمات', 'search' ),
		'competitors' => array( 'رقبا', 'users' ),
		'strategy'    => array( 'استراتژی شروع', 'target' ),
		'estimate'    => array( 'تخمین ترافیک و فروش', 'trophy' ),
		'offpage'     => array( 'آف‌پیج', 'link' ),
		'technical'   => array( 'تکنیکال', 'tool' ),
		'costs'       => array( 'هزینه و نقشه راه', 'wallet' ),
		'texts'       => array( 'متن‌ها', 'text' ),
		'export'      => array( 'خروجی و ارسال', 'download' ),
		'activity'    => array( 'پیگیری', 'clock' ),
	);
}

/**
 * Statuses: key => [label, color, stage of the sales pipeline].
 */
function ost_statuses() {
	return array(
		'draft'       => array( 'پیش‌نویس', 'gray' ),
		'running'     => array( 'در حال تحلیل', 'blue' ),
		'paused'      => array( 'متوقف (سقف روزانه)', 'amber' ),
		'waiting'     => array( 'منتظر تأیید شما', 'amber' ),
		'ready'       => array( 'آماده ارسال', 'sky' ),
		'sent'        => array( 'ارسال‌شده', 'navy' ),
		'negotiating' => array( 'در حال مذاکره', 'violet' ),
		'won'         => array( 'قرارداد بسته شد', 'green' ),
		'lost'        => array( 'رد شد', 'red' ),
	);
}

function ost_status_label( $status ) {
	return ost_statuses()[ $status ] ?? array( $status, 'gray' );
}

/**
 * Statuses the SEO manager sets by hand (the others come from the analysis).
 */
function ost_sales_statuses() {
	return array( 'ready', 'sent', 'negotiating', 'won', 'lost' );
}

/**
 * Reasons a proposal was not accepted (for the win/loss report).
 */
function ost_lost_reasons() {
	return array( 'price' => 'قیمت بالا', 'competitor' => 'آژانس / فریلنسر دیگر', 'timing' => 'زمان مناسب نیست', 'inhouse' => 'تیم داخلی', 'no_answer' => 'پاسخ نداد', 'other' => 'سایر' );
}

/**
 * Set a proposal's sales status (with history).
 */
function ost_set_status( $id, $status, $note = '' ) {
	$old = (string) get_post_meta( $id, '_ost_status', true );
	if ( $old === $status ) {
		return;
	}
	update_post_meta( $id, '_ost_status', $status );
	$times            = (array) get_post_meta( $id, '_ost_status_times', true );
	$times[ $status ] = time();
	update_post_meta( $id, '_ost_status_times', $times );
	ost_log( $id, 'وضعیت: ' . ost_status_label( $status )[0] . ( '' !== $note ? ' — ' . $note : '' ) );
}

/**
 * Activity log of a proposal.
 */
function ost_log( $id, $text, $type = 'event' ) {
	$log   = (array) get_post_meta( $id, '_ost_log', true );
	$log[] = array( 't' => time(), 'u' => get_current_user_id(), 'text' => mb_substr( (string) $text, 0, 600 ), 'type' => $type );
	update_post_meta( $id, '_ost_log', array_slice( $log, -150 ) );
}

function ost_log_entries( $id ) {
	return array_reverse( array_filter( (array) get_post_meta( $id, '_ost_log', true ), 'is_array' ) );
}

/**
 * Count a view of the shared web link by the client.
 */
function ost_track_view( $id ) {
	$v          = wp_parse_args( (array) get_post_meta( $id, '_ost_views', true ), array( 'count' => 0, 'first' => 0, 'last' => 0 ) );
	$fresh      = time() - (int) $v['last'] > 30 * MINUTE_IN_SECONDS;
	$v['count'] = (int) $v['count'] + 1;
	$v['first'] = $v['first'] ?: time();
	$v['last']  = time();
	update_post_meta( $id, '_ost_views', $v );
	if ( $fresh ) {
		ost_log( $id, 'کارفرما لینک پروپوزال را باز کرد', 'view' );
	}
}

function ost_views( $id ) {
	return wp_parse_args( (array) get_post_meta( $id, '_ost_views', true ), array( 'count' => 0, 'first' => 0, 'last' => 0 ) );
}

/**
 * Follow-up: [date (Y-m-d), note].
 */
function ost_followup( $id ) {
	return wp_parse_args( (array) get_post_meta( $id, '_ost_followup', true ), array( 'date' => '', 'note' => '' ) );
}

/**
 * Value of the selected package (stored on every compute, for the dashboard).
 */
function ost_value( $id ) {
	return wp_parse_args( (array) get_post_meta( $id, '_ost_value', true ), array( 'total' => 0, 'monthly' => 0, 'fees' => 0, 'package' => '' ) );
}

/**
 * Pipeline numbers for the dashboard.
 */
function ost_pipeline( $items ) {
	$s = array( 'count' => count( $items ), 'month' => 0, 'by' => array_fill_keys( array_keys( ost_statuses() ), 0 ), 'open_value' => 0, 'won_value' => 0, 'won_fees' => 0, 'due' => array(), 'lost_reasons' => array() );
	$month_start = strtotime( gmdate( 'Y-m-01' ) );
	$today       = gmdate( 'Y-m-d', current_time( 'timestamp' ) ); // phpcs:ignore
	foreach ( $items as $p ) {
		$s['by'][ $p['status'] ] = ( $s['by'][ $p['status'] ] ?? 0 ) + 1;
		if ( $p['created'] >= $month_start ) {
			$s['month']++;
		}
		$v = ost_value( $p['id'] );
		if ( in_array( $p['status'], array( 'sent', 'negotiating' ), true ) ) {
			$s['open_value'] += $v['total'];
		}
		if ( 'won' === $p['status'] ) {
			$s['won_value'] += $v['total'];
			$s['won_fees']  += $v['fees'];
		}
		if ( 'lost' === $p['status'] ) {
			$r                        = (string) get_post_meta( $p['id'], '_ost_lost_reason', true ) ?: 'other';
			$s['lost_reasons'][ $r ] = ( $s['lost_reasons'][ $r ] ?? 0 ) + 1;
		}
		$f = ost_followup( $p['id'] );
		if ( $f['date'] && $f['date'] <= $today && ! in_array( $p['status'], array( 'won', 'lost' ), true ) ) {
			$s['due'][] = array( 'p' => $p, 'f' => $f, 'late' => $f['date'] < $today );
		}
	}
	$closed       = $s['by']['won'] + $s['by']['lost'];
	$s['win_rate'] = $closed ? $s['by']['won'] / $closed : null;
	usort( $s['due'], function ( $a, $b ) {
		return strcmp( $a['f']['date'], $b['f']['date'] );
	} );
	return $s;
}

/**
 * Flash message from the redirect.
 */
function ost_flash() {
	if ( empty( $_GET['ost_msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	$ok = ! isset( $_GET['ost_ok'] ) || '1' === $_GET['ost_ok']; // phpcs:ignore
	printf( '<div class="ost-flash %s">%s</div>', $ok ? 'is-ok' : 'is-err', esc_html( sanitize_text_field( wp_unslash( $_GET['ost_msg'] ) ) ) ); // phpcs:ignore
}

/**
 * Hidden fields of an app form posting to admin-post.php.
 */
function ost_form_fields( $action, $return = '' ) {
	echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
	echo '<input type="hidden" name="_ost_nonce" value="' . esc_attr( wp_create_nonce( $action ) ) . '">';
	if ( $return ) {
		echo '<input type="hidden" name="_return" value="' . esc_url( $return ) . '">';
	}
}

/**
 * Signed GET link to an admin-post action (redirects back into the app).
 */
function ost_action_url( $action, $args = array() ) {
	return add_query_arg( array_merge( array( 'action' => $action, '_ost_nonce' => wp_create_nonce( $action ) ), $args ), admin_url( 'admin-post.php' ) );
}

/**
 * "۳ روز پیش" style relative time.
 */
function ost_ago( $ts ) {
	$d = time() - (int) $ts;
	if ( $d < 90 ) {
		return 'همین الان';
	}
	if ( $d < HOUR_IN_SECONDS ) {
		return ost_num( round( $d / 60 ) ) . ' دقیقه پیش';
	}
	if ( $d < DAY_IN_SECONDS ) {
		return ost_num( round( $d / HOUR_IN_SECONDS ) ) . ' ساعت پیش';
	}
	if ( $d < 30 * DAY_IN_SECONDS ) {
		return ost_num( round( $d / DAY_IN_SECONDS ) ) . ' روز پیش';
	}
	return ost_jdate( $ts );
}

/**
 * Jalali date of a Y-m-d string.
 */
function ost_jdate_ymd( $ymd ) {
	$ts = strtotime( $ymd . ' 12:00:00' );
	return $ts ? ost_jdate( $ts ) : '';
}
