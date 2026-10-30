<?php
/**
 * Realistic growth forecast (three scenarios) over the proposal period.
 *
 * Baseline: Search Console clicks (when the client gave access) or the estimate
 * from SEO Signal. Target: a share of what the competitors already earn from the
 * same market (proof that it is achievable), capped by the market size, and
 * scaled by the period and the chosen package. Growth follows an S-curve: the
 * first two months build the foundation, visible growth starts in months 3–5.
 *
 * @package Ostorlab
 */

defined( 'ABSPATH' ) || exit;

/**
 * Forecast.
 *
 * @param array $a      Analysis (market, profiles, client, gsc).
 * @param int   $months Period.
 * @param float $effort Package intensity (1 = recommended).
 * @return array{baseline: int, scenarios: array, curve: array, basis: string, keywords: array}
 */
function ost_forecast( $a, $months, $effort = 1.0 ) {
	$market  = (array) ( $a['market'] ?? array() );
	$demand  = (int) ( $market['total'] ?? 0 );
	$comp    = array_filter( (array) ( $a['profiles'] ?? array() ), function ( $p ) {
		return 'competitor' === ( $p['role'] ?? '' );
	} );
	$client  = $a['profiles'][ $a['client_domain'] ?? '' ] ?? null;
	$gsc     = (array) ( $a['gsc'] ?? array() );
	$baseline = 0;
	$basis    = 'none';
	if ( ! empty( $gsc['totals']['clicks'] ) ) {
		$nonbrand = (int) ( $gsc['brand']['nonbrand'] ?? 0 );
		$all      = max( 1, (int) ( $gsc['brand']['brand'] ?? 0 ) + $nonbrand );
		$baseline = (int) round( $gsc['totals']['clicks'] / 3 * ( $nonbrand / $all ) );
		$basis    = 'gsc';
	} elseif ( $client ) {
		$baseline = (int) ( $client['market_clicks'] ?: $client['clicks'] );
		$basis    = 'estimate';
	}
	$comp_market = array_column( $comp, 'market_clicks' );
	$avg_comp    = $comp_market ? ost_avg( $comp_market ) : 0;
	$top_comp    = $comp_market ? max( $comp_market ) : 0;
	// What is realistically reachable in 12 months with the recommended package.
	$reach = $avg_comp > 0 ? min( 0.85 * $avg_comp + 0.15 * $top_comp, 0.12 * $demand ) : 0.05 * $demand;
	$reach = max( $reach, $baseline * 1.4, 30 );
	$time  = min( 1.15, pow( $months / 12, 1.15 ) );
	$diff  = 1 - 0.3 * ost_avg( array_column( (array) ( $market['clusters'] ?? array() ), 'difficulty' ) );
	$exp   = $baseline + max( 0, $reach - $baseline ) * $time * $effort * $diff;
	$scen  = array(
		'low'  => (int) round( $baseline + ( $exp - $baseline ) * 0.55 ),
		'mid'  => (int) round( $exp ),
		'high' => (int) round( $baseline + ( $exp - $baseline ) * 1.4 ),
	);
	$curve = array();
	for ( $m = 0; $m <= $months; $m++ ) {
		$p = ost_s_curve( $m, $months );
		foreach ( $scen as $k => $target ) {
			$curve[ $k ][] = (int) round( $baseline + ( $target - $baseline ) * $p );
		}
	}
	$buyer_share = $demand ? ( $market['buyer'] ?? 0 ) / $demand : 0.5;
	$money       = count( (array) ( $market['money'] ?? array() ) );
	return array(
		'baseline'    => $baseline,
		'basis'       => $basis,
		'demand'      => $demand,
		'avg_comp'    => (int) round( $avg_comp ),
		'top_comp'    => (int) round( $top_comp ),
		'scenarios'   => $scen,
		'curve'       => $curve,
		'buyer_share' => $buyer_share,
		'buyer_mid'   => (int) round( $scen['mid'] * $buyer_share ),
		'growth'      => $baseline > 20 ? $scen['mid'] / $baseline - 1 : null,
		'keywords'    => array(
			'low'  => (int) round( $money * 0.15 * $effort ),
			'high' => (int) round( $money * 0.35 * $effort ),
			'of'   => $money,
		),
	);
}

/**
 * Normalised S-curve progress (0 at month 0, 1 at the last month).
 */
function ost_s_curve( $m, $months ) {
	$k   = 7.5 / max( 6, $months );
	$mid = $months * 0.55;
	$f   = function ( $x ) use ( $k, $mid ) {
		return 1 / ( 1 + exp( -$k * ( $x - $mid ) ) );
	};
	return ( $f( $m ) - $f( 0 ) ) / max( 0.0001, $f( $months ) - $f( 0 ) );
}
