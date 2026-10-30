<?php
/**
 * Inline SVG charts for the slides (print-safe: no scripts, fixed colors).
 *
 * Color by job: the client is the one highlighted entity (brand accent), the
 * competitors are de-emphasised neutral bars with direct labels; the intent split
 * uses a fixed categorical order validated for color-vision deficiency; the
 * forecast is one hue (band + line). Every mark carries a <title> for hover in
 * the web view.
 *
 * @package Fanoos
 */

defined( 'ABSPATH' ) || exit;

/**
 * Chart colors (follow the theme settings).
 */
function fns_chart_colors() {
	return array(
		'accent'  => fns_setting( 'theme_accent', '#0D94E4' ),
		'primary' => fns_setting( 'theme_primary', '#0B4F9C' ),
		'muted'   => '#A7B4C6',
		'grid'    => '#E3EAF3',
		'ink'     => '#0F1E33',
		'ink2'    => '#55657A',
		'band'    => '#CDEBFC',
		// Intent order: buy, compare, local, general, info (validated categorical set).
		'intents' => array( 'buy' => '#0B4F9C', 'compare' => '#0D94E4', 'local' => '#D97706', 'general' => '#0F9F8F', 'info' => '#8B5CF6' ),
	);
}

function fns_svg_text( $x, $y, $text, $attrs = '' ) {
	return sprintf( '<text x="%s" y="%s" %s>%s</text>', round( $x, 1 ), round( $y, 1 ), $attrs, esc_html( $text ) );
}

/**
 * Horizontal bars, right-to-left: labels on the right, bars grow to the left.
 *
 * @param array $rows [ ['label' => , 'value' => , 'hl' => bool, 'note' => ] ].
 */
function fns_chart_hbar( $rows, $w = 560, $fmt = 'fns_compact' ) {
	$c     = fns_chart_colors();
	$rows  = array_values( $rows );
	$bar   = 22;
	$gap   = 16;
	$lab   = 150;
	$h     = count( $rows ) * ( $bar + $gap ) + 6;
	$max   = max( 1, max( array_column( $rows, 'value' ) ?: array( 1 ) ) );
	$area  = $w - $lab - 70;
	$svg   = sprintf( '<svg class="fns-chart" viewBox="0 0 %d %d" width="100%%" role="img">', $w, $h );
	foreach ( $rows as $i => $r ) {
		$y    = 4 + $i * ( $bar + $gap );
		$len  = max( 3, $area * (float) $r['value'] / $max );
		$x1   = $w - $lab - 12;
		$fill = ! empty( $r['hl'] ) ? $c['accent'] : $c['muted'];
		$svg .= fns_svg_text( $w - 2, $y + $bar / 2 + 5, $r['label'], 'text-anchor="end" font-size="14" fill="' . $c['ink'] . '"' . ( ! empty( $r['hl'] ) ? ' font-weight="700"' : '' ) );
		$svg .= sprintf( '<path d="M%1$s %2$s h-%3$s a4 4 0 0 1 -4 -4 v-%4$s a4 4 0 0 1 4 -4 h%3$s z" fill="%5$s"><title>%6$s: %7$s</title></path>', round( $x1, 1 ), $y + $bar, round( max( 0, $len - 4 ), 1 ), $bar - 8, $fill, esc_attr( $r['label'] ), esc_attr( call_user_func( $fmt, $r['value'] ) ) );
		$svg .= fns_svg_text( $x1 - $len - 8, $y + $bar / 2 + 5, call_user_func( $fmt, $r['value'] ) . ( isset( $r['note'] ) ? ' ' . $r['note'] : '' ), 'text-anchor="end" font-size="13" fill="' . $c['ink2'] . '" font-weight="600"' );
	}
	return $svg . '</svg>';
}

/**
 * One 100% stacked bar (intent split) with a legend under it.
 *
 * @param array $parts key => value (keys of fns_chart_colors()['intents']).
 */
function fns_chart_split( $parts, $w = 560 ) {
	$c     = fns_chart_colors();
	$order = array( 'buy', 'compare', 'local', 'general', 'info' );
	$sum   = max( 1, array_sum( $parts ) );
	$svg   = sprintf( '<svg class="fns-chart" viewBox="0 0 %d 96" width="100%%" role="img">', $w );
	$x     = $w;
	$leg   = '';
	$lx    = $w;
	foreach ( $order as $k ) {
		$v = (float) ( $parts[ $k ] ?? 0 );
		if ( $v <= 0 ) {
			continue;
		}
		$seg  = $w * $v / $sum;
		$x   -= $seg;
		$svg .= sprintf( '<rect x="%s" y="4" width="%s" height="34" rx="4" fill="%s"><title>%s: %s</title></rect>', round( $x + 1, 1 ), round( max( 1, $seg - 2 ), 1 ), $c['intents'][ $k ], esc_attr( fns_intent_label( $k ) ), esc_attr( fns_pct( $v / $sum ) ) );
		if ( $seg > 44 ) {
			$svg .= fns_svg_text( $x + $seg / 2, 26, fns_pct( $v / $sum ), 'text-anchor="middle" font-size="13" font-weight="700" fill="#fff"' );
		}
		$label = fns_intent_label( $k );
		$leg  .= sprintf( '<rect x="%s" y="62" width="12" height="12" rx="3" fill="%s"/>', round( $lx - 12, 1 ), $c['intents'][ $k ] );
		$leg  .= fns_svg_text( $lx - 18, 73, $label, 'text-anchor="end" font-size="12.5" fill="' . $c['ink2'] . '"' );
		$lx   -= 30 + mb_strlen( $label ) * 7.2;
	}
	return $svg . $leg . '</svg>';
}

/**
 * Forecast: band between the low and high scenarios, the expected line and the baseline.
 *
 * @param array $curve {low: int[], mid: int[], high: int[]} indexed by month 0..N.
 */
function fns_chart_forecast( $curve, $baseline, $w = 760, $h = 300 ) {
	$c    = fns_chart_colors();
	$n    = max( 1, count( $curve['mid'] ) - 1 );
	$max  = max( 10, max( $curve['high'] ) ) * 1.1;
	$pl   = 20;
	$pr   = 64;
	$pt   = 16;
	$pb   = 36;
	$cw   = $w - $pl - $pr;
	$ch   = $h - $pt - $pb;
	// Time runs left to right (growth reads as "up and to the right", like every spreadsheet chart).
	$x = function ( $m ) use ( $pl, $cw, $n ) {
		return round( $pl + $cw * $m / $n, 1 );
	};
	$y = function ( $v ) use ( $pt, $ch, $max ) {
		return round( $pt + $ch * ( 1 - $v / $max ), 1 );
	};
	$svg = sprintf( '<svg class="fns-chart" viewBox="0 0 %d %d" width="100%%" role="img">', $w, $h );
	for ( $g = 0; $g <= 4; $g++ ) {
		$v    = $max / 1.1 * $g / 4;
		$svg .= sprintf( '<line x1="%s" x2="%s" y1="%s" y2="%s" stroke="%s" stroke-width="1"/>', $pl, $w - $pr, $y( $v ), $y( $v ), $c['grid'] );
		$svg .= fns_svg_text( $w - $pr + 8, $y( $v ) + 4, fns_compact( round( $v ) ), 'text-anchor="start" font-size="12" fill="' . $c['ink2'] . '"' );
	}
	$band = '';
	for ( $m = 0; $m <= $n; $m++ ) {
		$band .= ( $m ? 'L' : 'M' ) . $x( $m ) . ' ' . $y( $curve['high'][ $m ] ) . ' ';
	}
	for ( $m = $n; $m >= 0; $m-- ) {
		$band .= 'L' . $x( $m ) . ' ' . $y( $curve['low'][ $m ] ) . ' ';
	}
	$svg  .= '<path d="' . $band . 'Z" fill="' . $c['band'] . '" opacity="0.9"/>';
	$svg  .= sprintf( '<line x1="%s" x2="%s" y1="%s" y2="%s" stroke="%s" stroke-width="1.5" stroke-dasharray="5 5"/>', $pl, $w - $pr, $y( $baseline ), $y( $baseline ), $c['ink2'] );
	$line  = '';
	for ( $m = 0; $m <= $n; $m++ ) {
		$line .= ( $m ? 'L' : 'M' ) . $x( $m ) . ' ' . $y( $curve['mid'][ $m ] ) . ' ';
	}
	$svg .= '<path d="' . $line . '" fill="none" stroke="' . $c['primary'] . '" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"/>';
	for ( $m = 0; $m <= $n; $m++ ) {
		$svg .= sprintf( '<circle cx="%s" cy="%s" r="9" fill="transparent"><title>ماه %s: %s تا %s کلیک (پیش‌بینی میانی %s)</title></circle>', $x( $m ), $y( $curve['mid'][ $m ] ), fns_num( $m ), fns_num( $curve['low'][ $m ] ), fns_num( $curve['high'][ $m ] ), fns_num( $curve['mid'][ $m ] ) );
		if ( 0 === $m % max( 1, (int) ceil( $n / 12 ) ) ) {
			$svg .= fns_svg_text( $x( $m ), $h - 12, 0 === $m ? 'امروز' : fns_num( $m ), 'text-anchor="middle" font-size="12" fill="' . $c['ink2'] . '"' );
		}
	}
	$svg .= sprintf( '<circle cx="%s" cy="%s" r="5.5" fill="%s" stroke="#fff" stroke-width="2"/>', $x( $n ), $y( $curve['mid'][ $n ] ), $c['primary'] );
	$svg .= fns_svg_text( $x( $n ) - 10, $y( $curve['mid'][ $n ] ) - 12, fns_num( $curve['mid'][ $n ] ), 'text-anchor="end" font-size="14" font-weight="800" fill="' . $c['ink'] . '"' );
	$svg .= fns_svg_text( $pl + 4, $y( $baseline ) - 7, 'امروز: ' . fns_num( $baseline ), 'text-anchor="start" font-size="12" fill="' . $c['ink2'] . '"' );
	return $svg . '</svg>';
}

/**
 * Score ring (0–100).
 */
function fns_chart_ring( $score, $size = 120, $label = '' ) {
	$c     = fns_chart_colors();
	$r     = $size / 2 - 10;
	$circ  = 2 * M_PI * $r;
	$score = null === $score ? 0 : (int) $score;
	$col   = $score >= 70 ? $c['accent'] : ( $score >= 50 ? '#D97706' : '#DC2626' );
	return sprintf(
		'<svg class="fns-ring" viewBox="0 0 %1$d %1$d" width="%1$d" height="%1$d" role="img"><circle cx="%2$s" cy="%2$s" r="%3$s" fill="none" stroke="%4$s" stroke-width="10"/><circle cx="%2$s" cy="%2$s" r="%3$s" fill="none" stroke="%5$s" stroke-width="10" stroke-linecap="round" stroke-dasharray="%6$s %7$s" transform="rotate(-90 %2$s %2$s)"/><text x="%2$s" y="%8$s" text-anchor="middle" font-size="%9$s" font-weight="800" fill="%10$s">%11$s</text>%12$s</svg>',
		$size, $size / 2, round( $r, 1 ), $c['grid'], $col, round( $circ * $score / 100, 1 ), round( $circ, 1 ), round( $size / 2 + $size * 0.1, 1 ), round( $size * 0.28 ), $c['ink'], fns_fa( $score ),
		$label ? fns_svg_text( $size / 2, $size / 2 + $size * 0.27, $label, 'text-anchor="middle" font-size="' . round( $size * 0.1 ) . '" fill="' . $c['ink2'] . '"' ) : ''
	);
}

/**
 * Small trend line (12 months of search demand).
 */
function fns_chart_spark( $data, $w = 160, $h = 40 ) {
	$data = array_values( array_map( 'floatval', (array) $data ) );
	if ( count( $data ) < 2 ) {
		return '';
	}
	$c   = fns_chart_colors();
	$min = min( $data );
	$max = max( $data );
	$rng = max( 1, $max - $min );
	$n   = count( $data ) - 1;
	$d   = '';
	foreach ( $data as $i => $v ) {
		$d .= ( $i ? 'L' : 'M' ) . round( 2 + ( $w - 4 ) * $i / $n, 1 ) . ' ' . round( $h - 3 - ( $h - 6 ) * ( $v - $min ) / $rng, 1 ) . ' ';
	}
	return sprintf( '<svg class="fns-spark" viewBox="0 0 %d %d" width="%d" height="%d"><path d="%s" fill="none" stroke="%s" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/></svg>', $w, $h, $w, $h, $d, $c['accent'] );
}

/**
 * Vertical bars (monthly Search Console clicks), oldest on the left.
 *
 * @param array $series label => value.
 */
function fns_chart_columns( $series, $w = 560, $h = 200 ) {
	$c    = fns_chart_colors();
	$vals = array_values( $series );
	$labs = array_keys( $series );
	$n    = count( $vals );
	if ( ! $n ) {
		return '';
	}
	$max = max( 1, max( $vals ) );
	$bw  = ( $w - 20 ) / $n;
	$svg = sprintf( '<svg class="fns-chart" viewBox="0 0 %d %d" width="100%%" role="img">', $w, $h );
	foreach ( $vals as $i => $v ) {
		$bh   = max( 2, ( $h - 40 ) * $v / $max );
		$x    = 10 + $i * $bw + 3;
		$svg .= sprintf( '<rect x="%s" y="%s" width="%s" height="%s" rx="4" fill="%s"><title>%s: %s</title></rect>', round( $x, 1 ), round( $h - 24 - $bh, 1 ), round( $bw - 6, 1 ), round( $bh, 1 ), $i === $n - 1 ? $c['accent'] : $c['muted'], esc_attr( $labs[ $i ] ), fns_num( $v ) );
		if ( 0 === $i % 3 || $i === $n - 1 ) {
			$svg .= fns_svg_text( $x + ( $bw - 6 ) / 2, $h - 6, $labs[ $i ], 'text-anchor="middle" font-size="11" fill="' . $c['ink2'] . '"' );
		}
	}
	return $svg . '</svg>';
}
