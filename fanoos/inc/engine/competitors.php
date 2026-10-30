<?php
/**
 * Competitor (and client) profile from SEO Signal's site analyzer + the quick
 * audit: ranking keywords and pages, estimated monthly impressions, clicks,
 * buyer clicks, revenue range, their best pages and their share of the client's
 * market.
 *
 * SEO Signal gives each keyword's page and each page's average position, so a
 * keyword's position is taken from its page. Estimates use a standard CTR curve
 * by position and are shown as ranges, never as exact numbers.
 *
 * @package Fanoos
 */

defined( 'ABSPATH' ) || exit;

/**
 * Average organic CTR by position (desktop + mobile, global studies).
 */
function fns_ctr( $pos ) {
	$pos   = (float) $pos;
	$curve = array( 1 => 0.28, 0.155, 0.11, 0.08, 0.06, 0.045, 0.035, 0.03, 0.025, 0.02 );
	if ( $pos <= 0 ) {
		return 0.01;
	}
	if ( $pos <= 10 ) {
		$lo = (int) floor( $pos );
		$hi = min( 10, $lo + 1 );
		$lo = max( 1, $lo );
		return $curve[ $lo ] + ( $curve[ $hi ] - $curve[ $lo ] ) * ( $pos - $lo );
	}
	return $pos <= 20 ? 0.01 : 0.002;
}

/**
 * Share of searches in which a result at this position is actually seen.
 */
function fns_visibility( $pos ) {
	return $pos <= 10 ? 1.0 : ( $pos <= 20 ? 0.35 : 0.05 );
}

/**
 * Profile of one domain.
 *
 * @param array $site      fns_ss_site() result.
 * @param array $audit     fns_site_audit() result (may be empty).
 * @param array $market    fns_build_market() result.
 * @param array $in        Proposal inputs (AOV, conversion rate, business type).
 */
function fns_domain_profile( $site, $audit, $market, $in ) {
	$pos_of = array();
	foreach ( (array) ( $site['pages'] ?? array() ) as $pg ) {
		$pos_of[ $pg['p'] ] = $pg['avg'] > 0 ? $pg['avg'] : 15;
	}
	$market_words = array_flip( (array) ( $market['all_words'] ?? array() ) );
	$impr         = 0;
	$clicks       = 0;
	$buyer_clicks = 0;
	$market_clicks = 0;
	$top3         = 0;
	$top10        = 0;
	$pages        = array();
	foreach ( (array) ( $site['keywords'] ?? array() ) as $k ) {
		$pos    = $pos_of[ $k['p'] ] ?? 15;
		$i      = $k['v'] * fns_visibility( $pos );
		$c      = $k['v'] * fns_ctr( $pos );
		$intent = fns_intent( $k['w'] );
		$buyer  = in_array( $intent, array( 'buy', 'compare', 'local' ), true ) ? 1 : ( 'general' === $intent ? 0.5 : 0 );
		$impr         += $i;
		$clicks       += $c;
		$buyer_clicks += $c * $buyer;
		if ( isset( $market_words[ fns_norm( $k['w'] ) ] ) ) {
			$market_clicks += $c;
		}
		if ( $pos <= 3 ) {
			$top3++;
		}
		if ( $pos <= 10 ) {
			$top10++;
		}
		$pg = $k['p'] ?: '/';
		if ( ! isset( $pages[ $pg ] ) ) {
			$pages[ $pg ] = array( 'p' => $pg, 'clicks' => 0, 'buyer' => 0, 'kw' => 0, 'pos' => $pos, 'top' => '' , 'top_v' => 0 );
		}
		$pages[ $pg ]['clicks'] += $c;
		$pages[ $pg ]['buyer']  += $c * $buyer;
		$pages[ $pg ]['kw']++;
		if ( $k['v'] > $pages[ $pg ]['top_v'] ) {
			$pages[ $pg ]['top']   = $k['w'];
			$pages[ $pg ]['top_v'] = $k['v'];
		}
	}
	usort( $pages, function ( $a, $b ) {
		return $b['clicks'] <=> $a['clicks'];
	} );
	foreach ( $pages as &$pg ) {
		$pg['money'] = $pg['clicks'] > 0 && $pg['buyer'] / $pg['clicks'] >= 0.5;
	}
	unset( $pg );
	$money_pages = array_values( array_filter( $pages, function ( $pg ) {
		return $pg['money'];
	} ) );
	$rev = fns_revenue_range( $buyer_clicks, $in );
	return array(
		'keywords'      => count( (array) ( $site['keywords'] ?? array() ) ),
		'capped'        => ! empty( $site['capped'] ),
		'ranking_pages' => count( (array) ( $site['pages'] ?? array() ) ),
		'top3'          => $top3,
		'top10'         => $top10,
		'impressions'   => (int) round( $impr ),
		'clicks'        => (int) round( $clicks ),
		'buyer_clicks'  => (int) round( $buyer_clicks ),
		'market_clicks' => (int) round( $market_clicks ),
		'revenue'       => $rev,
		'top_pages'     => array_slice( $pages, 0, 8 ),
		'money_pages'   => array_slice( $money_pages, 0, 5 ),
		'site_pages'    => (int) ( $audit['sitemap']['total'] ?? 0 ),
		'page_types'    => (array) ( $audit['sitemap']['types'] ?? array() ),
		'cms'           => (string) ( $audit['cms'] ?? '' ),
		'related'       => array_slice( (array) ( $site['related'] ?? array() ), 0, 8 ),
	);
}

/**
 * Monthly revenue range from buyer clicks.
 *
 * Shops: clicks × conversion rate × average order value. Services: leads (lead
 * rate). Without an order value, the advertising value of the clicks is used.
 *
 * @return array{low: float, high: float, orders: int, basis: string}
 */
function fns_revenue_range( $buyer_clicks, $in ) {
	$cr  = max( 0.1, (float) ( $in['conversion_rate'] ?? 1.5 ) ) / 100;
	$aov = (float) ( $in['aov'] ?? 0 );
	if ( in_array( $in['business_type'] ?? 'shop', array( 'service', 'local', 'saas' ), true ) ) {
		$leads = $buyer_clicks * max( 0.5, (float) fns_setting( 'lead_rate', 3 ) ) / 100;
		return array(
			'low'    => $aov > 0 ? $leads * 0.7 * $aov * 0.3 : 0,
			'high'   => $aov > 0 ? $leads * 1.3 * $aov * 0.3 : 0,
			'orders' => (int) round( $leads ),
			'basis'  => 'leads',
			'value'  => $buyer_clicks * (float) fns_setting( 'click_value', 3000 ),
		);
	}
	$orders = $buyer_clicks * $cr;
	return array(
		'low'    => $aov > 0 ? $orders * 0.7 * $aov : 0,
		'high'   => $aov > 0 ? $orders * 1.3 * $aov : 0,
		'orders' => (int) round( $orders ),
		'basis'  => 'orders',
		'value'  => $buyer_clicks * (float) fns_setting( 'click_value', 3000 ),
	);
}

/**
 * Share of voice in the client's market (from the clicks each domain gets from it).
 *
 * @param array $profiles domain => profile.
 * @return array domain => share (0–1)
 */
function fns_share_of_voice( $profiles ) {
	$sum = array_sum( array_column( $profiles, 'market_clicks' ) );
	$out = array();
	foreach ( $profiles as $d => $p ) {
		$out[ $d ] = $sum ? $p['market_clicks'] / $sum : 0;
	}
	return $out;
}

/**
 * Keyword gap: words competitors rank for and the client does not, summed over
 * competitors, best opportunities first.
 *
 * @param array $gaps competitor => fns_ss_compare( client, competitor, 'gap' ).
 */
function fns_keyword_gap( $gaps ) {
	$words = array();
	foreach ( $gaps as $comp => $rows ) {
		foreach ( (array) $rows as $r ) {
			$k = fns_norm( $r['w'] );
			if ( '' === $k ) {
				continue;
			}
			if ( ! isset( $words[ $k ] ) ) {
				$words[ $k ] = array( 'w' => $r['w'], 'v' => $r['v'], 'by' => array(), 'i' => fns_intent( $r['w'] ) );
			}
			$words[ $k ]['by'][] = $comp;
		}
	}
	foreach ( $words as &$w ) {
		$buyer      = in_array( $w['i'], array( 'buy', 'compare', 'local' ), true ) ? 1.6 : ( 'general' === $w['i'] ? 1.2 : 0.8 );
		$w['score'] = $w['v'] * $buyer * ( 1 + 0.4 * ( count( $w['by'] ) - 1 ) );
	}
	unset( $w );
	uasort( $words, function ( $a, $b ) {
		return $b['score'] <=> $a['score'];
	} );
	return array(
		'count'  => count( $words ),
		'volume' => array_sum( array_column( $words, 'v' ) ),
		'buyer'  => array_sum( array_map( function ( $w ) {
			return in_array( $w['i'], array( 'buy', 'compare', 'local' ), true ) ? $w['v'] : 0;
		}, $words ) ),
		'top'    => array_slice( array_values( $words ), 0, 30 ),
	);
}
