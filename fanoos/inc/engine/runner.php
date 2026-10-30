<?php
/**
 * Analysis runner: the job is a list of small steps, one external call (or a
 * small group) each, run one per AJAX request so no request hits PHP or proxy
 * time limits. Failures are recorded and the job goes on; when the SEO Signal
 * quota is used up the job pauses and can be resumed the next day.
 *
 * After the data steps, fns_compute() turns the raw data into the analysis
 * (market, profiles, off-page, technical, forecast, budget, roadmap); it needs no
 * API and runs again whenever costs or texts are edited.
 *
 * @package Fanoos
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plan the steps of a proposal.
 *
 * @param array $p     Proposal.
 * @param bool  $fresh Re-collect everything (else completed steps are kept).
 */
function fns_job_plan( $p, $fresh = false ) {
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
	foreach ( fns_domains( $p ) as $d => $role ) {
		$add( 'site:' . $d, 'کلمات و صفحات ' . $d );
		$add( 'audit:' . $d, 'بررسی فنی سریع ' . $d );
		$add( 'rep:' . $d, 'رپورتاژهای ' . $d );
		if ( fns_semrush_connected() ) {
			$add( 'semrush:' . $d, 'بک‌لینک‌های ' . $d . ' (سمراش)' );
		}
	}
	if ( $in['has_site'] ) {
		foreach ( $in['competitors'] as $c ) {
			$add( 'gap:' . $c, 'شکاف کلمات با ' . $c );
		}
		if ( $in['gsc_access'] && fns_google_connected() ) {
			$add( 'gsc', 'داده سرچ کنسول' );
		}
	}
	$add( 'compute', 'محاسبه بازار، رقبا، هزینه و نقشه راه' );
	if ( fns_ai_connected() ) {
		$add( 'ai', 'نوشتن متن‌ها با هوش مصنوعی' );
	}
	$steps['compute']['status'] = 'pending';
	if ( isset( $steps['ai'] ) && $fresh ) {
		$steps['ai']['status'] = 'pending';
	}
	$job = array( 'status' => 'running', 'steps' => $steps, 'started' => time(), 'msg' => '' );
	update_post_meta( $p['id'], '_fns_job', $job );
	update_post_meta( $p['id'], '_fns_status', 'running' );
	return $job;
}

/**
 * Run the next pending step.
 *
 * @return array Job state.
 */
function fns_job_tick( $id ) {
	$p   = fns_proposal( $id );
	$job = (array) $p['job'];
	if ( empty( $job['steps'] ) ) {
		return $job;
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
		update_post_meta( $id, '_fns_job', $job );
		update_post_meta( $id, '_fns_status', 'ready' );
		return $job;
	}
	$res = fns_job_step( $p, $key );
	if ( is_wp_error( $res ) && 'fns_ss_cap' === $res->get_error_code() ) {
		$job['status'] = 'paused';
		$job['msg']    = $res->get_error_message();
		update_post_meta( $id, '_fns_job', $job );
		update_post_meta( $id, '_fns_status', 'paused' );
		return $job;
	}
	$job['steps'][ $key ]['status'] = is_wp_error( $res ) ? 'error' : 'done';
	$job['steps'][ $key ]['msg']    = is_wp_error( $res ) ? $res->get_error_message() : ( is_string( $res ) ? $res : '' );
	$left = count( array_filter( $job['steps'], function ( $s ) {
		return 'pending' === $s['status'];
	} ) );
	if ( ! $left ) {
		$job['status'] = 'done';
		update_post_meta( $id, '_fns_status', 'ready' );
	}
	update_post_meta( $id, '_fns_job', $job );
	return $job;
}

/**
 * One step.
 *
 * @return string|true|WP_Error Message on success.
 */
function fns_job_step( $p, $key ) {
	$id = $p['id'];
	list( $type, $arg ) = array_pad( explode( ':', $key, 2 ), 2, '' );
	switch ( $type ) {
		case 'kw':
			$r = fns_ss_keyword( $arg, 'similar', 300 );
			fns_put_data( $id, 'keywords', $arg, is_wp_error( $r ) ? array( 'seed' => $arg, 'list' => array(), 'error' => $r->get_error_message() ) : $r );
			return is_wp_error( $r ) ? $r : sprintf( '%s کلمه مرتبط، %s جستجوی ماهانه', fns_num( count( $r['list'] ) ), fns_num( $r['total'] ) );
		case 'site':
			$r = fns_ss_site( $arg );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			fns_put_data( $id, 'seosignal', $arg, $r );
			return sprintf( '%s کلمه، %s صفحه رتبه‌دار', fns_num( count( $r['keywords'] ) ), fns_num( count( $r['pages'] ) ) );
		case 'audit':
			$r = fns_site_audit( $arg, 14, (int) $p['inputs']['crawl_limit'] );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			fns_put_data( $id, 'audit', $arg, $r );
			return sprintf( '%s آدرس در نقشه سایت، %s صفحه نمونه بررسی شد', fns_num( $r['sitemap']['total'] ), fns_num( count( $r['pages'] ) ) );
		case 'rep':
			$raw = array( 'reportages' => fns_ss_reportage_buyer( $arg, 'reportages', 'all' ) );
			if ( is_wp_error( $raw['reportages'] ) ) {
				$err = $raw['reportages'];
				if ( 'fns_ss_cap' === $err->get_error_code() ) {
					return $err;
				}
				fns_put_data( $id, 'reportage', $arg, array( 'error' => $err->get_error_message() ) );
				return $err;
			}
			foreach ( array( 'anchors' => 'inbound_anchors', 'pages' => 'linked_pages' ) as $k => $report ) {
				$r         = fns_ss_reportage_buyer( $arg, $report, 'all' );
				$raw[ $k ] = is_wp_error( $r ) ? array() : $r;
			}
			fns_put_data( $id, 'reportage', $arg, $raw );
			return sprintf( '%s رپورتاژ پیدا شد', fns_num( count( fns_find_rows( $raw['reportages'] ) ) ) );
		case 'semrush':
			$top = array();
			$ss  = fns_data( $id, 'seosignal' )[ $arg ] ?? array();
			$pr  = $ss ? fns_domain_profile( $ss, array(), array(), $p['inputs'] ) : array();
			foreach ( array_slice( (array) ( $pr['top_pages'] ?? array() ), 0, 3 ) as $pg ) {
				$top[] = 'https://' . $arg . ( '/' === substr( $pg['p'], 0, 1 ) ? '' : '/' ) . ltrim( $pg['p'], '/' );
			}
			$r = fns_semrush_domain( $arg, $top );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			fns_put_data( $id, 'semrush', $arg, $r );
			return sprintf( '%s بک‌لینک خوانده شد', fns_num( count( $r['links'] ) ) );
		case 'gap':
			$r = fns_ss_compare( $p['inputs']['domain'], $arg, 'gap' );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			fns_put_data( $id, 'gap', $arg, $r );
			return sprintf( '%s کلمه فقط در %s', fns_num( count( $r ) ), $arg );
		case 'gsc':
			$r = fns_gsc_collect( $p['inputs']['domain'], $p['inputs']['gsc_property'], $p['inputs']['brand_terms'] );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			fns_set_data( $id, 'gsc', $r );
			return sprintf( '%s کلیک در ۳ ماه', fns_num( $r['totals']['clicks'] ) );
		case 'compute':
			fns_compute( $id );
			return 'محاسبه شد';
		case 'ai':
			$a = fns_data( $id, 'analysis' );
			$t = fns_writer_ai( $p, $a );
			if ( is_wp_error( $t ) ) {
				return $t;
			}
			update_post_meta( $id, '_fns_ai_texts', $t );
			fns_compute( $id );
			return 'متن‌ها نوشته شد';
	}
	return new WP_Error( 'fns_step', 'مرحله نامعتبر' );
}

/**
 * Build the analysis from the collected data (no API calls).
 *
 * @return array Analysis.
 */
function fns_compute( $id ) {
	$p       = fns_proposal( $id );
	$in      = $p['inputs'];
	$domains = fns_domains( $p );
	$cd      = $in['has_site'] ? $in['domain'] : '';
	$kw      = fns_data( $id, 'keywords' );
	$ss      = fns_data( $id, 'seosignal' );
	$audit   = fns_data( $id, 'audit' );
	$rep     = fns_data( $id, 'reportage' );
	$sem     = fns_data( $id, 'semrush' );
	$sf      = fns_data( $id, 'sf' );
	$gaps    = fns_data( $id, 'gap' );
	$gsc     = $in['gsc_access'] ? fns_data( $id, 'gsc' ) : array();
	$raw     = array();
	foreach ( $in['keywords'] as $s ) {
		if ( isset( $kw[ $s ] ) ) {
			$raw[ $s ] = $kw[ $s ];
		}
	}
	$market   = fns_build_market( $raw );
	$tokens   = fns_market_tokens( $market, $in['keywords'] );
	$profiles = array();
	$offpage  = array();
	$tech     = array();
	foreach ( $domains as $d => $role ) {
		$profiles[ $d ]         = fns_domain_profile( $ss[ $d ] ?? array(), $audit[ $d ] ?? array(), $market, $in );
		$profiles[ $d ]['role'] = $role;
		$profiles[ $d ]['has_data'] = isset( $ss[ $d ] );
		$brand                  = 'client' === $role ? $in['brand_terms'] : array( preg_replace( '/\..*$/', '', $d ) );
		$offpage[ $d ]          = fns_offpage_profile( $rep[ $d ] ?? array(), $sem[ $d ] ?? array(), $brand, $tokens );
		$tech[ $d ]             = fns_technical( $audit[ $d ] ?? array(), $sf[ $d ] ?? array() );
	}
	$a = array(
		'client_domain' => $cd,
		'market'        => $market,
		'profiles'      => $profiles,
		'sov'           => fns_share_of_voice( $profiles ),
		'gap'           => fns_keyword_gap( $gaps ),
		'offpage'       => $offpage,
		'technical'     => $tech,
		'gsc'           => $gsc,
		'semrush'       => fns_semrush_connected(),
		'ai_texts'      => (array) get_post_meta( $id, '_fns_ai_texts', true ),
		'computed'      => time(),
	);
	$months      = (int) $in['months'];
	$suggest     = fns_budget_suggest( $a, $months );
	$a['budget'] = fns_budget( $suggest, $months, (array) $p['budget'] );
	$sel         = $a['budget']['packages'][ $a['budget']['selected'] ] ?? reset( $a['budget']['packages'] );
	$a['forecast'] = fns_forecast( $a, $months, (float) $sel['effort'] );
	$a['forecasts'] = array();
	foreach ( $a['budget']['packages'] as $k => $pk ) {
		$f                    = fns_forecast( $a, $months, (float) $pk['effort'] );
		$a['forecasts'][ $k ] = array( 'low' => $f['scenarios']['low'], 'mid' => $f['scenarios']['mid'], 'high' => $f['scenarios']['high'] );
	}
	$a['roadmap'] = fns_roadmap( $a, $sel, $a['forecast'], $months, $in );
	$rev          = fns_revenue_range( $a['forecast']['buyer_mid'], $in );
	$a['forecast']['revenue'] = $rev;
	fns_set_data( $id, 'analysis', $a );
	return $a;
}
