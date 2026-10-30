<?php
/**
 * Analysis runner: the job is a list of small steps, one external call (or a
 * small group) each, run one per AJAX request so no request hits PHP or proxy
 * time limits. Failures are recorded and the job goes on; when the SEO Signal
 * quota is used up the job pauses and can be resumed the next day.
 *
 * After the data steps, ost_compute() turns the raw data into the analysis
 * (market, profiles, off-page, technical, forecast, budget, roadmap); it needs no
 * API and runs again whenever costs or texts are edited.
 *
 * @package Ostorlab
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plan the steps of a proposal.
 *
 * @param array $p     Proposal.
 * @param bool  $fresh Re-collect everything (else completed steps are kept).
 */
function ost_job_plan( $p, $fresh = false ) {
	$in      = $p['inputs'];
	$old     = $fresh ? array() : (array) ( $p['job']['steps'] ?? array() );
	$steps   = array();
	$add     = function ( $key, $label ) use ( &$steps, $old ) {
		$prev           = $old[ $key ] ?? array();
		$steps[ $key ]  = array( 'label' => $label, 'status' => 'done' === ( $prev['status'] ?? '' ) ? 'done' : 'pending', 'msg' => 'done' === ( $prev['status'] ?? '' ) ? ( $prev['msg'] ?? '' ) : '' );
	};
	foreach ( $in['keywords'] as $kw ) {
		$add( 'kw:' . $kw, 'تحقیق کلمه «' . $kw . '»' );
	}
	if ( ost_ai_connected() ) {
		$add( 'aikw', 'پالایش کلمات و انتخاب کلمات کلیدی اصلی با هوش مصنوعی' );
		foreach ( $steps as $k => $s ) {
			if ( 0 === strpos( $k, 'kw:' ) && 'pending' === $s['status'] ) {
				$steps['aikw']['status'] = 'pending'; // New keyword data: review again.
			}
		}
	}
	foreach ( ost_domains( $p ) as $d => $role ) {
		$add( 'site:' . $d, 'کلمات و صفحات ' . $d );
		$add( 'audit:' . $d, 'بررسی فنی سریع ' . $d );
		$add( 'rep:' . $d, 'رپورتاژهای ' . $d );
		if ( ost_semrush_connected() ) {
			$add( 'semrush:' . $d, 'بک‌لینک‌های ' . $d . ' (سمراش)' );
		}
	}
	if ( $in['has_site'] ) {
		foreach ( $in['competitors'] as $c ) {
			$add( 'gap:' . $c, 'شکاف کلمات با ' . $c );
		}
		if ( $in['gsc_access'] && ost_google_connected() ) {
			$add( 'gsc', 'داده سرچ کنسول' );
		}
	}
	$add( 'compute', 'محاسبه بازار، رقبا، هزینه و نقشه راه' );
	if ( ost_ai_connected() ) {
		$add( 'ai', 'نوشتن متن‌ها با هوش مصنوعی' );
	}
	$steps['compute']['status'] = 'pending';
	if ( isset( $steps['ai'] ) && $fresh ) {
		$steps['ai']['status'] = 'pending';
	}
	$job = array( 'status' => 'running', 'steps' => $steps, 'started' => time(), 'msg' => '' );
	update_post_meta( $p['id'], '_ost_job', $job );
	ost_job_status( $p['id'], 'running' );
	return $job;
}

/**
 * Status from the analysis job; a proposal already sent / in negotiation / closed keeps its sales status.
 */
function ost_job_status( $id, $status ) {
	$cur = (string) get_post_meta( $id, '_ost_status', true );
	if ( in_array( $cur, array( 'sent', 'negotiating', 'won', 'lost' ), true ) ) {
		return;
	}
	update_post_meta( $id, '_ost_status', $status );
}

/**
 * Run the next pending step.
 *
 * @return array Job state.
 */
function ost_job_tick( $id ) {
	$p   = ost_proposal( $id );
	$job = (array) $p['job'];
	if ( empty( $job['steps'] ) ) {
		return $job;
	}
	// A step left "running" means the server stopped in the middle of it last time.
	foreach ( $job['steps'] as $k => $s ) {
		if ( 'running' === $s['status'] ) {
			$job['steps'][ $k ]['status'] = 'error';
			$job['steps'][ $k ]['msg']    = $s['msg'] ?: 'سرور وسط این مرحله متوقف شد (معمولاً محدودیت زمان اجرای PHP یا حافظه هاست). مراحل بعدی ادامه پیدا کرد.';
		}
	}
	$key = null;
	foreach ( $job['steps'] as $k => $s ) {
		if ( 'pending' === $s['status'] ) {
			$key = $k;
			break;
		}
	}
	if ( null === $key ) {
		$job['status'] = 'done';
		update_post_meta( $id, '_ost_job', $job );
		ost_job_status( $id, 'ready' );
		return $job;
	}
	// Mark the step as running: if the server dies inside it (time / memory limit,
	// fatal error), the next tick and the shutdown handler record it as failed and
	// the job moves on instead of retrying the same step forever.
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
	wp_raise_memory_limit( 'admin' );
	$job['steps'][ $key ]['status']  = 'running';
	$job['steps'][ $key ]['started'] = time();
	update_post_meta( $id, '_ost_job', $job );
	$GLOBALS['ost_running_step'] = array( $id, $key );
	register_shutdown_function( 'ost_job_shutdown' );
	try {
		$res = ost_job_step( $p, $key );
	} catch ( Throwable $e ) {
		$res = new WP_Error( 'ost_step_fatal', 'خطای داخلی در این مرحله: ' . mb_substr( $e->getMessage(), 0, 220 ) );
	}
	unset( $GLOBALS['ost_running_step'] );
	if ( is_wp_error( $res ) && 'ost_ss_cap' === $res->get_error_code() ) {
		$job['status'] = 'paused';
		$job['msg']    = $res->get_error_message();
		update_post_meta( $id, '_ost_job', $job );
		ost_job_status( $id, 'paused' );
		return $job;
	}
	$job['steps'][ $key ]['status'] = is_wp_error( $res ) ? 'error' : 'done';
	$job['steps'][ $key ]['msg']    = is_wp_error( $res ) ? $res->get_error_message() : ( is_string( $res ) ? $res : '' );
	$left = count( array_filter( $job['steps'], function ( $s ) {
		return 'pending' === $s['status'];
	} ) );
	if ( ! $left ) {
		$job['status'] = 'done';
		ost_job_status( $id, 'ready' );
		ost_log( $id, 'تحلیل کامل شد' );
	}
	update_post_meta( $id, '_ost_job', $job );
	return $job;
}

/**
 * The request died inside a step (fatal error, time or memory limit): record it.
 */
function ost_job_shutdown() {
	if ( empty( $GLOBALS['ost_running_step'] ) ) {
		return;
	}
	list( $id, $key ) = $GLOBALS['ost_running_step'];
	$err = error_get_last();
	$job = (array) get_post_meta( $id, '_ost_job', true );
	if ( isset( $job['steps'][ $key ] ) && 'running' === $job['steps'][ $key ]['status'] ) {
		$msg = $err ? preg_replace( '/ in \/.*$/s', '', (string) $err['message'] ) : '';
		if ( false !== stripos( $msg, 'Maximum execution time' ) ) {
			$msg = 'محدودیت زمان اجرای PHP هاست تمام شد';
		} elseif ( false !== stripos( $msg, 'memory size' ) ) {
			$msg = 'حافظه PHP هاست کافی نبود';
		}
		$job['steps'][ $key ]['status'] = 'error';
		$job['steps'][ $key ]['msg']    = 'سرور وسط این مرحله متوقف شد' . ( $msg ? ' (' . mb_substr( $msg, 0, 160 ) . ')' : '' ) . '؛ تحلیل از مرحله بعد ادامه پیدا می‌کند.';
		update_post_meta( $id, '_ost_job', $job );
	}
}

/**
 * One step.
 *
 * @return string|true|WP_Error Message on success.
 */
function ost_job_step( $p, $key ) {
	$id = $p['id'];
	list( $type, $arg ) = array_pad( explode( ':', $key, 2 ), 2, '' );
	switch ( $type ) {
		case 'kw':
			$r = ost_ss_keyword( $arg, 'similar', 300 );
			ost_put_data( $id, 'keywords', $arg, is_wp_error( $r ) ? array( 'seed' => $arg, 'list' => array(), 'error' => $r->get_error_message() ) : $r );
			return is_wp_error( $r ) ? $r : sprintf( '%s کلمه دریافت شد؛ جستجوی ماهانه خود کلمه: %s (کلمات بی‌ربط و بدون جستجو بعداً حذف می‌شوند)', ost_num( count( $r['list'] ) ), ost_num( $r['volume'] ) );
		case 'site':
			$r = ost_ss_site( $arg );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			ost_put_data( $id, 'seosignal', $arg, $r );
			return sprintf( '%s کلمه، %s صفحه رتبه‌دار', ost_num( count( $r['keywords'] ) ), ost_num( count( $r['pages'] ) ) );
		case 'audit':
			$r = ost_site_audit( $arg, 14, (int) $p['inputs']['crawl_limit'] );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			ost_put_data( $id, 'audit', $arg, $r );
			return sprintf( '%s آدرس در نقشه سایت، %s صفحه نمونه بررسی شد', ost_num( $r['sitemap']['total'] ), ost_num( count( $r['pages'] ) ) );
		case 'rep':
			$raw = array( 'reportages' => ost_ss_reportage_buyer( $arg, 'reportages', 'all' ) );
			if ( is_wp_error( $raw['reportages'] ) ) {
				$err = $raw['reportages'];
				if ( 'ost_ss_cap' === $err->get_error_code() ) {
					return $err;
				}
				if ( 'ost_ss_request_failed' === $err->get_error_code() ) {
					// SEO Signal has no reportage recorded for this domain: that is a result (zero), not an error.
					ost_put_data( $id, 'reportage', $arg, array( 'reportages' => array( 'PRList' => array() ), 'anchors' => array(), 'pages' => array() ) );
					return 'رپورتاژی برای این دامنه در سئو سیگنال ثبت نشده (صفر)';
				}
				ost_put_data( $id, 'reportage', $arg, array( 'error' => $err->get_error_message() ) );
				return $err;
			}
			foreach ( array( 'anchors' => 'inbound_anchors', 'pages' => 'linked_pages' ) as $k => $report ) {
				$r         = ost_ss_reportage_buyer( $arg, $report, 'all' );
				$raw[ $k ] = is_wp_error( $r ) ? array() : $r;
			}
			ost_put_data( $id, 'reportage', $arg, $raw );
			return sprintf( '%s رپورتاژ پیدا شد', ost_num( count( ost_find_rows( $raw['reportages'] ) ) ) );
		case 'semrush':
			$top = array();
			$ss  = ost_data( $id, 'seosignal' )[ $arg ] ?? array();
			$pr  = $ss ? ost_domain_profile( $ss, array(), array(), $p['inputs'] ) : array();
			foreach ( array_slice( (array) ( $pr['top_pages'] ?? array() ), 0, 3 ) as $pg ) {
				$top[] = 'https://' . $arg . ( '/' === substr( $pg['p'], 0, 1 ) ? '' : '/' ) . ltrim( $pg['p'], '/' );
			}
			$r = ost_semrush_domain( $arg, $top );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			ost_put_data( $id, 'semrush', $arg, $r );
			return sprintf( '%s بک‌لینک خوانده شد', ost_num( count( $r['links'] ) ) );
		case 'gap':
			$r = ost_ss_compare( $p['inputs']['domain'], $arg, 'gap' );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			ost_put_data( $id, 'gap', $arg, $r );
			return sprintf( '%s کلمه فقط در %s', ost_num( count( $r ) ), $arg );
		case 'gsc':
			$r = ost_gsc_collect( $p['inputs']['domain'], $p['inputs']['gsc_property'], $p['inputs']['brand_terms'] );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			ost_set_data( $id, 'gsc', $r );
			return sprintf( '%s کلیک در ۳ ماه', ost_num( $r['totals']['clicks'] ) );
		case 'aikw':
			$raw = array();
			$kw  = ost_data( $id, 'keywords' );
			foreach ( $p['inputs']['keywords'] as $s ) {
				if ( isset( $kw[ $s ] ) ) {
					$raw[ $s ] = $kw[ $s ];
				}
			}
			$rev = ost_ai_keyword_review( $p, $raw );
			if ( is_wp_error( $rev ) ) {
				return $rev;
			}
			ost_set_data( $id, 'aikw', $rev );
			return sprintf( '%s کلمه بی‌ربط حذف شد، %s کلمه کلیدی اصلی انتخاب شد', ost_num( count( $rev['drop'] ) ), ost_num( count( $rev['important'] ) ) );
		case 'compute':
			ost_compute( $id );
			return 'محاسبه شد';
		case 'ai':
			$a = ost_data( $id, 'analysis' );
			$t = ost_writer_ai( $p, $a );
			if ( is_wp_error( $t ) ) {
				return $t;
			}
			update_post_meta( $id, '_ost_ai_texts', $t );
			ost_compute( $id );
			return 'متن‌ها نوشته شد';
	}
	return new WP_Error( 'ost_step', 'مرحله نامعتبر' );
}

/**
 * Build the analysis from the collected data (no API calls).
 *
 * @return array Analysis.
 */
function ost_compute( $id ) {
	$p       = ost_proposal( $id );
	$in      = $p['inputs'];
	$domains = ost_domains( $p );
	$cd      = $in['has_site'] ? $in['domain'] : '';
	$kw      = ost_data( $id, 'keywords' );
	$ss      = ost_data( $id, 'seosignal' );
	$audit   = ost_data( $id, 'audit' );
	$rep     = ost_data( $id, 'reportage' );
	$sem     = ost_data( $id, 'semrush' );
	$sf      = ost_data( $id, 'sf' );
	$gaps    = ost_data( $id, 'gap' );
	$gsc     = $in['gsc_access'] ? ost_data( $id, 'gsc' ) : array();
	$raw     = array();
	foreach ( $in['keywords'] as $s ) {
		if ( isset( $kw[ $s ] ) ) {
			$raw[ $s ] = $kw[ $s ];
		}
	}
	$review   = ost_data( $id, 'aikw' );
	$market   = ost_build_market( $raw, $review );
	$market['ai_note'] = (string) ( $review['note'] ?? '' );
	$tokens   = ost_market_tokens( $market, $in['keywords'] );
	$profiles = array();
	$offpage  = array();
	$tech     = array();
	foreach ( $domains as $d => $role ) {
		$profiles[ $d ]         = ost_domain_profile( $ss[ $d ] ?? array(), $audit[ $d ] ?? array(), $market, $in );
		$profiles[ $d ]['role'] = $role;
		$profiles[ $d ]['has_data'] = isset( $ss[ $d ] );
		$brand                  = 'client' === $role ? $in['brand_terms'] : array( preg_replace( '/\..*$/', '', $d ) );
		$offpage[ $d ]          = ost_offpage_profile( $rep[ $d ] ?? array(), $sem[ $d ] ?? array(), $brand, $tokens );
		$tech[ $d ]             = ost_technical( $audit[ $d ] ?? array(), $sf[ $d ] ?? array() );
	}
	// With Search Console access, the client's own numbers come from it (SEO Signal only sees part of a small site).
	if ( $cd && ! empty( $gsc['totals']['clicks'] ) && isset( $profiles[ $cd ] ) ) {
		$profiles[ $cd ] = ost_profile_from_gsc( $profiles[ $cd ], $gsc, $market, $in );
	}
	$a = array(
		'client_domain' => $cd,
		'market'        => $market,
		'profiles'      => $profiles,
		'sov'           => ost_share_of_voice( $profiles ),
		'gap'           => ost_keyword_gap( $gaps ),
		'offpage'       => $offpage,
		'technical'     => $tech,
		'gsc'           => $gsc,
		'semrush'       => ost_semrush_connected(),
		'ai_texts'      => (array) get_post_meta( $id, '_ost_ai_texts', true ),
		'computed'      => time(),
	);
	$a['opportunities'] = ost_opportunities( $market, $ss, $a['gap'], $cd, $gsc, $in );
	$a['strategy']      = ost_strategy( $a['opportunities'], (array) $p['strategy'], $in, $cd );
	unset( $a['opportunities'] );
	$ai_market       = (array) get_post_meta( $id, '_ost_ai_market', true );
	$a['channels']   = ost_channels( $a, $in, $ai_market );
	$a['opps_ai']    = ost_market_opportunities( $a, $ai_market );
	$a['market']['rows'] = array(); // Only needed for the strategy; keeps the stored analysis small.
	$months      = (int) $in['months'];
	$suggest     = ost_budget_suggest( $a, $months );
	$a['budget'] = ost_budget( $suggest, $months, (array) $p['budget'] );
	$sel         = $a['budget']['packages'][ $a['budget']['selected'] ] ?? reset( $a['budget']['packages'] );
	$a['forecast'] = ost_forecast( $a, $months, (float) $sel['effort'] );
	$a['forecasts'] = array();
	foreach ( $a['budget']['packages'] as $k => $pk ) {
		$f                    = ost_forecast( $a, $months, (float) $pk['effort'] );
		$a['forecasts'][ $k ] = array( 'low' => $f['scenarios']['low'], 'mid' => $f['scenarios']['mid'], 'high' => $f['scenarios']['high'] );
	}
	$a['roadmap'] = ost_roadmap( $a, $sel, $a['forecast'], $months, $in );
	$rev          = ost_revenue_range( $a['forecast']['buyer_mid'], $in );
	$a['forecast']['revenue'] = $rev;
	ost_set_data( $id, 'analysis', $a );
	update_post_meta( $id, '_ost_value', array( 'total' => $sel['total'], 'monthly' => $sel['monthly'], 'fees' => $sel['fees'], 'package' => $sel['label'] ) );
	return $a;
}

/**
 * Client profile from Search Console (last 3 months, per month): keywords, pages,
 * impressions, clicks, buyer clicks and the clicks from the proposal's market.
 */
function ost_profile_from_gsc( $pr, $gsc, $market, $in ) {
	$words  = array_flip( (array) ( $market['all_words'] ?? array() ) );
	$clicks = 0;
	$buyer  = 0;
	$mkt    = 0;
	$top10  = 0;
	foreach ( (array) ( $gsc['queries'] ?? array() ) as $q ) {
		$c      = $q['clicks'] / 3;
		$intent = ost_intent( $q['query'] );
		$clicks += $c;
		$buyer  += $c * ( in_array( $intent, array( 'buy', 'compare', 'local' ), true ) ? 1 : ( 'general' === $intent ? 0.5 : 0 ) );
		if ( isset( $words[ ost_norm( $q['query'] ) ] ) ) {
			$mkt += $c;
		}
		if ( $q['position'] <= 10 ) {
			$top10++;
		}
	}
	$pr['keywords']      = max( (int) $pr['keywords'], (int) ( $gsc['totals']['queries'] ?? 0 ) );
	$pr['ranking_pages'] = max( (int) $pr['ranking_pages'], (int) ( $gsc['totals']['pages'] ?? 0 ) );
	$pr['impressions']   = (int) round( ( $gsc['totals']['impressions'] ?? 0 ) / 3 );
	$pr['clicks']        = (int) round( ( $gsc['totals']['clicks'] ?? $clicks * 3 ) / 3 );
	$pr['buyer_clicks']  = (int) round( $buyer );
	$pr['market_clicks'] = (int) round( max( $mkt, $pr['market_clicks'] ) );
	$pr['top10']         = max( (int) $pr['top10'], $top10 );
	$pr['capped']        = false;
	$pr['revenue']       = ost_revenue_range( $pr['buyer_clicks'], $in );
	$pr['source']        = 'gsc';
	$pr['has_data']      = true;
	return $pr;
}
