<?php
/**
 * Form handlers (admin-post.php) and AJAX endpoints of the app. Every form
 * redirects back into the app (/seo/proposal/…) with a flash message.
 *
 * @package Ostorlab
 */

defined( 'ABSPATH' ) || exit;

/**
 * Verify nonce (POST or signed GET link) + capability, or die.
 */
function ost_guard( $action ) {
	$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_ost_nonce'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! ost_can() || ! wp_verify_nonce( $nonce, $action ) ) {
		wp_die( 'دسترسی غیرمجاز یا صفحه منقضی شده؛ صفحه را تازه کنید.', 403 );
	}
}

/**
 * Redirect back into the app with a flash message.
 */
function ost_back( $url, $msg = '', $ok = true ) {
	if ( '' !== $msg ) {
		$url = add_query_arg( array( 'ost_msg' => rawurlencode( $msg ), 'ost_ok' => $ok ? 1 : 0 ), $url );
	}
	wp_safe_redirect( $url );
	exit;
}

/**
 * Posted value (unslashed).
 */
function ost_post( $key, $default = '' ) {
	return isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : $default; // phpcs:ignore WordPress.Security
}

/**
 * Return URL posted by the form (same site only).
 */
function ost_return_url( $fallback ) {
	$r = (string) ost_post( '_return' );
	return $r ? wp_validate_redirect( $r, $fallback ) : $fallback;
}

/**
 * Id from POST or a signed GET link.
 */
function ost_req_id() {
	return absint( $_REQUEST['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
}

// Create / edit a proposal.
add_action( 'admin_post_ost_save_proposal', function () {
	ost_guard( 'ost_save_proposal' );
	$in = (array) ost_post( 'in', array() );
	$old = (int) ost_post( 'id', 0 );
	if ( count( ost_list( $in['keywords'] ?? '' ) ) < 1 ) {
		ost_back( $old ? ost_proposal_url( $old, 'edit' ) : ost_url( 'new' ), 'دست‌کم یک کلمه کلیدی وارد کنید (پیشنهاد: ۵ تا ۷ کلمه).', false );
	}
	$id = ost_save_proposal( $old, $in );
	if ( ! $old ) {
		ost_log( $id, 'پروپوزال ساخته شد' );
	}
	ost_back( ost_proposal_url( $id, 'summary', ost_post( 'run' ) ? array( 'autorun' => 1 ) : array() ), ost_post( 'run' ) ? '' : 'ذخیره شد. حالا «شروع تحلیل» را بزنید.' );
} );

add_action( 'admin_post_ost_delete', function () {
	ost_guard( 'ost_delete' );
	ost_delete_proposal( ost_req_id() );
	ost_back( ost_url(), 'پروپوزال حذف شد.' );
} );

add_action( 'admin_post_ost_duplicate', function () {
	ost_guard( 'ost_duplicate' );
	$id = ost_duplicate_proposal( ost_req_id() );
	ost_log( $id, 'ساخته‌شده از روی کپی یک پروپوزال دیگر' );
	ost_back( ost_proposal_url( $id, 'edit' ), 'کپی ساخته شد؛ نام کارفرما، دامنه و رقبا را اصلاح کنید.' );
} );

add_action( 'admin_post_ost_status', function () {
	ost_guard( 'ost_status' );
	$id = ost_req_id();
	$st = sanitize_key( $_REQUEST['status'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( ost_proposal( $id ) && in_array( $st, ost_sales_statuses(), true ) ) {
		$reason = 'lost' === $st ? sanitize_key( ost_post( 'reason' ) ) : '';
		if ( $reason ) {
			update_post_meta( $id, '_ost_lost_reason', $reason );
		}
		ost_set_status( $id, $st, trim( ( $reason ? ost_lost_reasons()[ $reason ] ?? '' : '' ) . ' ' . sanitize_text_field( (string) ost_post( 'note' ) ) ) );
		if ( in_array( $st, array( 'won', 'lost' ), true ) ) {
			delete_post_meta( $id, '_ost_followup' );
		}
	}
	ost_back( ost_return_url( ost_proposal_url( $id ) ), 'وضعیت به‌روز شد.' );
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
	ost_log( $id, 'هزینه‌ها ویرایش شد' );
	$months = (int) ost_to_number( ost_post( 'months' ) );
	if ( $months ) {
		$p                     = ost_proposal( $id );
		$p['inputs']['months'] = $months;
		ost_save_proposal( $id, $p['inputs'] );
	}
	ost_compute( $id );
	ost_back( ost_proposal_url( $id, 'costs' ), 'هزینه‌ها و نقشه راه به‌روز شد.' );
} );

// Follow-up date and note.
add_action( 'admin_post_ost_followup', function () {
	ost_guard( 'ost_followup' );
	$id   = ost_req_id();
	$days = (int) ost_post( 'days' );
	$date = $days ? gmdate( 'Y-m-d', current_time( 'timestamp' ) + $days * DAY_IN_SECONDS ) : sanitize_text_field( (string) ost_post( 'date' ) ); // phpcs:ignore
	if ( ost_post( 'clear' ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
		delete_post_meta( $id, '_ost_followup' );
		ost_back( ost_return_url( ost_proposal_url( $id, 'activity' ) ), 'پیگیری حذف شد.' );
	}
	$note = sanitize_text_field( (string) ost_post( 'note' ) );
	update_post_meta( $id, '_ost_followup', array( 'date' => $date, 'note' => $note ) );
	ost_log( $id, 'پیگیری برای ' . ost_jdate_ymd( $date ) . ( $note ? ': ' . $note : '' ) );
	ost_back( ost_return_url( ost_proposal_url( $id, 'activity' ) ), 'یادآور پیگیری ثبت شد.' );
} );

// Note on the activity log (calls, meetings, client feedback).
add_action( 'admin_post_ost_note', function () {
	ost_guard( 'ost_note' );
	$id   = ost_req_id();
	$text = sanitize_textarea_field( (string) ost_post( 'text' ) );
	if ( '' !== trim( $text ) ) {
		ost_log( $id, $text, sanitize_key( ost_post( 'type', 'note' ) ) ?: 'note' );
	}
	ost_back( ost_proposal_url( $id, 'activity' ), 'یادداشت ثبت شد.' );
} );

// Screaming Frog wizard: state (polled), step confirmations, URL check, final analysis.
add_action( 'wp_ajax_ost_sf', function () {
	ost_ajax_guard();
	$id = (int) ost_post( 'id' );
	$p  = ost_proposal( $id );
	if ( ! $p ) {
		wp_send_json_error( array( 'msg' => 'پروپوزال پیدا نشد.' ) );
	}
	$do = sanitize_key( ost_post( 'do' ) );
	if ( 'installed' === $do ) {
		ost_sf_wizard_save( $id, array( 'installed' => 1 ) );
	} elseif ( 'os' === $do ) {
		ost_sf_wizard_save( $id, array( 'os' => in_array( ost_post( 'os' ), array( 'win', 'sh' ), true ) ? ost_post( 'os' ) : 'win' ) );
	} elseif ( 'urls' === $do ) {
		// Check (and build when missing) the URL list of one domain from its sitemaps.
		$domain = ost_domain( (string) ost_post( 'domain' ) );
		if ( isset( ost_domains( $p )[ $domain ] ) ) {
			$limit = (int) ost_to_number( ost_post( 'limit' ) );
			if ( $limit ) {
				$p['inputs']['crawl_limit'] = $limit;
				ost_save_proposal( $id, $p['inputs'] );
				$p = ost_proposal( $id );
			}
			$audit = ost_data( $id, 'audit' )[ $domain ] ?? null;
			if ( ! $audit || count( (array) $audit['urls'] ) < min( (int) $p['inputs']['crawl_limit'], (int) ( $audit['sitemap']['total'] ?? 0 ) ) - 1 ) {
				// If the server dies while reading the site (time limit, fatal error), record it so the
				// wizard can go on with the home page instead of getting stuck on this domain.
				register_shutdown_function( function () use ( $id, $domain ) {
					if ( ! empty( $GLOBALS['ost_sf_urlcheck'] ) ) {
						$errs            = (array) ost_sf_wizard( $id )['url_errors'];
						$errs[ $domain ] = 'سرور هنگام خواندن نقشه سایت متوقف شد (محدودیت زمان یا خطای هاست).';
						ost_sf_wizard_save( $id, array( 'url_errors' => $errs ) );
					}
				} );
				$GLOBALS['ost_sf_urlcheck'] = true;
				try {
					$r = ost_site_audit( $domain, 3, (int) $p['inputs']['crawl_limit'] );
				} catch ( Throwable $e ) {
					$r = new WP_Error( 'ost_audit', $e->getMessage() );
				}
				unset( $GLOBALS['ost_sf_urlcheck'] );
				$errs = (array) ost_sf_wizard( $id )['url_errors'];
				if ( is_wp_error( $r ) ) {
					// The site cannot be read from this server (firewall, Cloudflare…): SEO Spider still
					// crawls it from your computer, starting from the home page.
					$errs[ $domain ] = $r->get_error_message();
				} else {
					unset( $errs[ $domain ] );
					ost_put_data( $id, 'audit', $domain, $r );
				}
				ost_sf_wizard_save( $id, array( 'url_errors' => $errs ) );
			}
		}
	} elseif ( 'urls_ok' === $do ) {
		ost_sf_wizard_save( $id, array( 'urls_ok' => 1 ) );
	} elseif ( 'analyze' === $do ) {
		ost_compute( $id );
		ost_log( $id, 'تحلیل تکنیکال با داده اسکریمینگ فراگ به‌روز شد' );
	} elseif ( 'reset' === $do ) {
		delete_post_meta( $id, '_ost_sf_wizard' );
	}
	wp_send_json_success( ost_sf_status( $id ) );
} );

// Strategy tab: pinned and excluded opportunities.
add_action( 'admin_post_ost_save_strategy', function () {
	ost_guard( 'ost_save_strategy' );
	$id    = (int) ost_post( 'id' );
	$picks = array_slice( array_map( 'sanitize_text_field', (array) ost_post( 'pick', array() ) ), 0, 5 );
	$off   = array_map( 'sanitize_text_field', (array) ost_post( 'off', array() ) );
	update_post_meta( $id, '_ost_strategy', ost_post( 'reset' ) ? array() : array( 'picks' => $picks, 'off' => array_values( array_diff( $off, $picks ) ) ) );
	ost_compute( $id );
	ost_back( ost_proposal_url( $id, 'strategy' ), 'استراتژی شروع به‌روز شد.' );
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
	ost_back( ost_proposal_url( $id, 'texts' ), ost_post( 'reset' ) ? 'متن‌ها به نسخه خودکار برگشت.' : 'متن‌ها ذخیره شد.' );
} );

// Export tab: slides on/off and web sharing.
add_action( 'admin_post_ost_save_slides', function () {
	ost_guard( 'ost_save_slides' );
	$id  = (int) ost_post( 'id' );
	$p   = ost_proposal( $id );
	$on  = array_map( 'sanitize_key', (array) ost_post( 'slides', array() ) );
	$all = array_keys( ost_deck_slides( $p, ost_data( $id, 'analysis' ) ) );
	update_post_meta( $id, '_ost_slides', array( 'off' => array_values( array_diff( $all, $on ) ) ) );
	$was = (bool) get_post_meta( $id, '_ost_shared', true );
	update_post_meta( $id, '_ost_shared', ost_post( 'shared' ) ? 1 : 0 );
	if ( ! $was && ost_post( 'shared' ) ) {
		ost_log( $id, 'لینک وب پروپوزال برای کارفرما فعال شد' );
	}
	ost_back( ost_proposal_url( $id, 'export' ), 'تنظیمات خروجی ذخیره شد.' );
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
	ost_back( ost_proposal_url( $id, 'technical' ), $errors ? implode( ' | ', $errors ) : sprintf( '%s فایل برای %s وارد شد (%s).', ost_num( count( $done ) ), $domain, implode( '، ', $done ) ), ! $errors );
} );

// Screaming Frog agent script download.
add_action( 'admin_post_ost_agent', function () {
	ost_guard( 'ost_agent' );
	$p  = ost_proposal( ost_req_id() );
	$os = 'win' === ( $_GET['os'] ?? '' ) ? 'win' : 'sh'; // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! $p ) {
		wp_die( 'پروپوزال پیدا نشد.' );
	}
	$name = ost_sf_script_name( $p, 'win' === $os ? '.ps1' : '.sh' );
	ost_sf_wizard_save( $p['id'], array( 'downloaded' => 1, 'os' => $os ) );
	nocache_headers();
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $name . '"' );
	echo ost_sf_agent_script( $p, $os ); // phpcs:ignore WordPress.Security.EscapeOutput
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
	// Logo / presenter photo uploaded as files.
	foreach ( array( 'agency_logo' => 'logo_file', 'consultant_photo' => 'photo_file' ) as $key => $field ) {
		if ( ! empty( $_FILES[ $field ]['tmp_name'] ) ) { // phpcs:ignore WordPress.Security
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$att = media_handle_upload( $field, 0 );
			if ( ! is_wp_error( $att ) ) {
				$changes[ $key ] = wp_get_attachment_url( $att );
			}
		}
	}
	ost_update_settings( $changes );
	delete_transient( 'ost_google_token' );
	ost_back( ost_url( 'settings/' . sanitize_key( ost_post( 'tab', 'connections' ) ) ), 'تنظیمات ذخیره شد.' );
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
