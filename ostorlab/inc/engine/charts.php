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
 * @package Ostorlab
 */

defined( 'ABSPATH' ) || exit;

/**
 * Chart colors (follow the theme settings).
 */
function ost_chart_colors() {
	return array(
		'accent'  => ost_setting( 'theme_accent', '#0D94E4' ),
		'primary' => ost_setting( 'theme_primary', '#0B4F9C' ),
		'muted'   => '#A7B4C6',
		'grid'    => '#E3EAF3',
		'ink'     => '#0F1E33',
		'ink2'    => '#55657A',
		'band'    => '#CDEBFC',
		// Intent order: buy, compare, local, general, info (validated categorical set).
		'intents' => array( 'buy' => '#0B4F9C', 'compare' => '#0D94E4', 'local' => '#D97706', 'general' => '#0F9F8F', 'info' => '#8B5CF6' ),
	);
}

function ost_svg_text( $x, $y, $text, $attrs = '' ) {
	return sprintf( '<text x="%s" y="%s" %s>%s</text>', round( $x, 1 ), round( $y, 1 ), $attrs, esc_html( $text ) );
}

/**
 * Horizontal bars, right-to-left: labels on the right, bars grow to the left.
 *
 * @param array $rows [ ['label' => , 'value' => , 'hl' => bool, 'note' => ] ].
 */
function ost_chart_hbar( $rows, $w = 560, $fmt = 'ost_compact' ) {
	$c     = ost_chart_colors();
	$rows  = array_values( $rows );
	$bar   = 22;
	$gap   = 16;
	$lab   = 150;
	$h     = count( $rows ) * ( $bar + $gap ) + 6;
	$max   = max( 1, max( array_column( $rows, 'value' ) ?: array( 1 ) ) );
	$area  = $w - $lab - 70;
	$svg   = sprintf( '<svg class="ost-chart" viewBox="0 0 %d %d" width="100%%" role="img">', $w, $h );
	foreach ( $rows as $i => $r ) {
		$y    = 4 + $i * ( $bar + $gap );
		$len  = max( 3, $area * (float) $r['value'] / $max );
		$x1   = $w - $lab - 12;
		$fill = ! empty( $r['hl'] ) ? $c['accent'] : $c['muted'];
		$svg .= ost_svg_text( $w - 2, $y + $bar / 2 + 5, $r['label'], 'text-anchor="end" font-size="14" fill="' . $c['ink'] . '"' . ( ! empty( $r['hl'] ) ? ' font-weight="700"' : '' ) );
		$svg .= sprintf( '<path d="M%1$s %2$s h-%3$s a4 4 0 0 1 -4 -4 v-%4$s a4 4 0 0 1 4 -4 h%3$s z" fill="%5$s"><title>%6$s: %7$s</title></path>', round( $x1, 1 ), $y + $bar, round( max( 0, $len - 4 ), 1 ), $bar - 8, $fill, esc_attr( $r['label'] ), esc_attr( call_user_func( $fmt, $r['value'] ) ) );
		$svg .= ost_svg_text( $x1 - $len - 8, $y + $bar / 2 + 5, call_user_func( $fmt, $r['value'] ) . ( isset( $r['note'] ) ? ' ' . $r['note'] : '' ), 'text-anchor="end" font-size="13" fill="' . $c['ink2'] . '" font-weight="600"' );
	}
	return $svg . '</svg>';
}

/**
 * One 100% stacked bar (intent split) with a legend under it.
 *
 * @param array $parts key => value (keys of ost_chart_colors()['intents']).
 */
function ost_chart_split( $parts, $w = 560 ) {
	$c     = ost_chart_colors();
	$order = array( 'buy', 'compare', 'local', 'general', 'info' );
	$sum   = max( 1, array_sum( $parts ) );
	$svg   = sprintf( '<svg class="ost-chart" viewBox="0 0 %d 96" width="100%%" role="img">', $w );
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
		$svg .= sprintf( '<rect x="%s" y="4" width="%s" height="34" rx="4" fill="%s"><title>%s: %s</title></rect>', round( $x + 1, 1 ), round( max( 1, $seg - 2 ), 1 ), $c['intents'][ $k ], esc_attr( ost_intent_label( $k ) ), esc_attr( ost_pct( $v / $sum ) ) );
		if ( $seg > 44 ) {
			$svg .= ost_svg_text( $x + $seg / 2, 26, ost_pct( $v / $sum ), 'text-anchor="middle" font-size="13" font-weight="700" fill="#fff"' );
		}
		$label = ost_intent_label( $k );
		$leg  .= sprintf( '<rect x="%s" y="62" width="12" height="12" rx="3" fill="%s"/>', round( $lx - 12, 1 ), $c['intents'][ $k ] );
		$leg  .= ost_svg_text( $lx - 18, 73, $label, 'text-anchor="end" font-size="12.5" fill="' . $c['ink2'] . '"' );
		$lx   -= 30 + mb_strlen( $label ) * 7.2;
	}
	return $svg . $leg . '</svg>';
}

/**
 * Forecast: band between the low and high scenarios, the expected line and the baseline.
 *
 * @param array $curve {low: int[], mid: int[], high: int[]} indexed by month 0..N.
 */
function ost_chart_forecast( $curve, $baseline, $w = 760, $h = 300 ) {
	$c    = ost_chart_colors();
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
	$svg = sprintf( '<svg class="ost-chart" viewBox="0 0 %d %d" width="100%%" role="img">', $w, $h );
	for ( $g = 0; $g <= 4; $g++ ) {
		$v    = $max / 1.1 * $g / 4;
		$svg .= sprintf( '<line x1="%s" x2="%s" y1="%s" y2="%s" stroke="%s" stroke-width="1"/>', $pl, $w - $pr, $y( $v ), $y( $v ), $c['grid'] );
		$svg .= ost_svg_text( $w - $pr + 8, $y( $v ) + 4, ost_compact( round( $v ) ), 'text-anchor="start" font-size="12" fill="' . $c['ink2'] . '"' );
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
		$svg .= sprintf( '<circle cx="%s" cy="%s" r="9" fill="transparent"><title>ماه %s: %s تا %s کلیک (پیش‌بینی میانی %s)</title></circle>', $x( $m ), $y( $curve['mid'][ $m ] ), ost_num( $m ), ost_num( $curve['low'][ $m ] ), ost_num( $curve['high'][ $m ] ), ost_num( $curve['mid'][ $m ] ) );
		if ( 0 === $m % max( 1, (int) ceil( $n / 12 ) ) ) {
			$svg .= ost_svg_text( $x( $m ), $h - 12, 0 === $m ? 'امروز' : ost_num( $m ), 'text-anchor="middle" font-size="12" fill="' . $c['ink2'] . '"' );
		}
	}
	$svg .= sprintf( '<circle cx="%s" cy="%s" r="5.5" fill="%s" stroke="#fff" stroke-width="2"/>', $x( $n ), $y( $curve['mid'][ $n ] ), $c['primary'] );
	$svg .= ost_svg_text( $x( $n ) - 10, $y( $curve['mid'][ $n ] ) - 12, ost_num( $curve['mid'][ $n ] ), 'text-anchor="end" font-size="14" font-weight="800" fill="' . $c['ink'] . '"' );
	$svg .= ost_svg_text( $pl + 4, $y( $baseline ) - 7, 'امروز: ' . ost_num( $baseline ), 'text-anchor="start" font-size="12" fill="' . $c['ink2'] . '"' );
	return $svg . '</svg>';
}

/**
 * Score ring (0–100).
 */
function ost_chart_ring( $score, $size = 120, $label = '' ) {
	$c     = ost_chart_colors();
	$r     = $size / 2 - 10;
	$circ  = 2 * M_PI * $r;
	$score = null === $score ? 0 : (int) $score;
	$col   = $score >= 70 ? $c['accent'] : ( $score >= 50 ? '#D97706' : '#DC2626' );
	return sprintf(
		'<svg class="ost-ring" viewBox="0 0 %1$d %1$d" width="%1$d" height="%1$d" role="img"><circle cx="%2$s" cy="%2$s" r="%3$s" fill="none" stroke="%4$s" stroke-width="10"/><circle cx="%2$s" cy="%2$s" r="%3$s" fill="none" stroke="%5$s" stroke-width="10" stroke-linecap="round" stroke-dasharray="%6$s %7$s" transform="rotate(-90 %2$s %2$s)"/><text x="%2$s" y="%8$s" text-anchor="middle" font-size="%9$s" font-weight="800" fill="%10$s">%11$s</text>%12$s</svg>',
		$size, $size / 2, round( $r, 1 ), $c['grid'], $col, round( $circ * $score / 100, 1 ), round( $circ, 1 ), round( $size / 2 + $size * 0.1, 1 ), round( $size * 0.28 ), $c['ink'], ost_fa( $score ),
		$label ? ost_svg_text( $size / 2, $size / 2 + $size * 0.27, $label, 'text-anchor="middle" font-size="' . round( $size * 0.1 ) . '" fill="' . $c['ink2'] . '"' ) : ''
	);
}

/**
 * Small trend line (12 months of search demand).
 */
function ost_chart_spark( $data, $w = 160, $h = 40 ) {
	$data = array_values( array_map( 'floatval', (array) $data ) );
	if ( count( $data ) < 2 ) {
		return '';
	}
	$c   = ost_chart_colors();
	$min = min( $data );
	$max = max( $data );
	$rng = max( 1, $max - $min );
	$n   = count( $data ) - 1;
	$d   = '';
	foreach ( $data as $i => $v ) {
		$d .= ( $i ? 'L' : 'M' ) . round( 2 + ( $w - 4 ) * $i / $n, 1 ) . ' ' . round( $h - 3 - ( $h - 6 ) * ( $v - $min ) / $rng, 1 ) . ' ';
	}
	return sprintf( '<svg class="ost-spark" viewBox="0 0 %d %d" width="%d" height="%d"><path d="%s" fill="none" stroke="%s" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/></svg>', $w, $h, $w, $h, $d, $c['accent'] );
}

/**
 * Vertical bars (monthly Search Console clicks), oldest on the left.
 *
 * @param array $series label => value.
 */
function ost_chart_columns( $series, $w = 560, $h = 200 ) {
	$c    = ost_chart_colors();
	$vals = array_values( $series );
	$labs = array_keys( $series );
	$n    = count( $vals );
	if ( ! $n ) {
		return '';
	}
	$max = max( 1, max( $vals ) );
	$bw  = ( $w - 20 ) / $n;
	$svg = sprintf( '<svg class="ost-chart" viewBox="0 0 %d %d" width="100%%" role="img">', $w, $h );
	foreach ( $vals as $i => $v ) {
		$bh   = max( 2, ( $h - 40 ) * $v / $max );
		$x    = 10 + $i * $bw + 3;
		$svg .= sprintf( '<rect x="%s" y="%s" width="%s" height="%s" rx="4" fill="%s"><title>%s: %s</title></rect>', round( $x, 1 ), round( $h - 24 - $bh, 1 ), round( $bw - 6, 1 ), round( $bh, 1 ), $i === $n - 1 ? $c['accent'] : $c['muted'], esc_attr( $labs[ $i ] ), ost_num( $v ) );
		if ( 0 === $i % 3 || $i === $n - 1 ) {
			$svg .= ost_svg_text( $x + ( $bw - 6 ) / 2, $h - 6, $labs[ $i ], 'text-anchor="middle" font-size="11" fill="' . $c['ink2'] . '"' );
		}
	}
	return $svg . '</svg>';
}

/**
 * Opportunity map: x = ease of entry (rivals + competition), y = monthly searches
 * (log scale), bubble = buyer searches. The chosen topics are numbered and
 * highlighted; the quick-win corner (easy + big) is shaded.
 *
 * @param array $all   All topics.
 * @param array $picks Chosen topics (in order).
 */
function ost_chart_matrix( $all, $picks, $w = 640, $h = 470 ) {
	$c     = ost_chart_colors();
	$all   = array_values( (array) $all );
	if ( ! $all ) {
		return '';
	}
	$pl    = 70;
	$pr    = 16;
	$pt    = 16;
	$pb    = 44;
	$cw    = $w - $pl - $pr;
	$ch    = $h - $pt - $pb;
	$emax  = max( 0.01, max( array_column( $all, 'entry' ) ) );
	$vmin  = max( 10, min( array_column( $all, 'volume' ) ) );
	$vmax  = max( $vmin * 10, max( array_column( $all, 'volume' ) ) );
	$bmax  = max( 1, max( array_column( $all, 'buyer' ) ) );
	$x     = function ( $o ) use ( $pl, $cw, $emax ) {
		return round( $pl + 14 + ( $cw - 28 ) * ( $o['entry'] / $emax ), 1 );
	};
	$y     = function ( $v ) use ( $pt, $ch, $vmin, $vmax ) {
		return round( $pt + 14 + ( $ch - 28 ) * ( 1 - ( log( max( $v, $vmin ) ) - log( $vmin ) ) / max( 0.01, log( $vmax ) - log( $vmin ) ) ), 1 );
	};
	$rank  = array();
	foreach ( array_values( $picks ) as $i => $o ) {
		$rank[ $o['key'] ] = $i + 1;
	}
	$svg  = sprintf( '<svg class="ost-chart" viewBox="0 0 %d %d" width="100%%" role="img">', $w, $h );
	// Quick-win zone: the easier half of the map.
	$svg .= sprintf( '<rect x="%s" y="%s" width="%s" height="%s" rx="14" fill="%s" opacity=".5"/>', $pl + $cw / 2, $pt, $cw / 2, $ch, $c['band'] );
	$svg .= ost_svg_text( $w - $pr - 12, $pt + 24, 'منطقه برد سریع', 'text-anchor="end" font-size="14" font-weight="800" fill="' . $c['primary'] . '"' );
	$svg .= sprintf( '<line x1="%1$s" x2="%1$s" y1="%2$s" y2="%3$s" stroke="%4$s"/><line x1="%5$s" x2="%6$s" y1="%3$s" y2="%3$s" stroke="%4$s"/>', $pl, $pt, $pt + $ch, $c['grid'], $pl, $w - $pr );
	foreach ( array( $vmin, sqrt( $vmin * $vmax ), $vmax ) as $v ) {
		$svg .= ost_svg_text( $pl - 8, $y( $v ) + 4, ost_compact( round( $v ) ), 'text-anchor="end" font-size="11.5" fill="' . $c['ink2'] . '"' );
	}
	$svg .= ost_svg_text( $pl, $h - 14, '← ورود سخت‌تر (رقبای قوی)', 'text-anchor="start" font-size="12" fill="' . $c['ink2'] . '"' );
	$svg .= ost_svg_text( $w - $pr, $h - 14, 'ورود آسان‌تر (رقیب ضعیف یا غایب) →', 'text-anchor="end" font-size="12" fill="' . $c['ink2'] . '"' );
	$svg .= ost_svg_text( 14, $pt + $ch / 2, 'جستجوی ماهانه', 'text-anchor="middle" font-size="12" fill="' . $c['ink2'] . '" transform="rotate(-90 14 ' . ( $pt + $ch / 2 ) . ')"' );
	// Others first (underneath), the chosen ones on top.
	usort( $all, function ( $a, $b ) use ( $rank ) {
		return isset( $rank[ $a['key'] ] ) <=> isset( $rank[ $b['key'] ] );
	} );
	foreach ( $all as $o ) {
		$r  = 5 + 15 * sqrt( $o['buyer'] / $bmax );
		$hl = isset( $rank[ $o['key'] ] );
		$r  = $hl ? max( 13, $r ) : $r;
		$svg .= sprintf( '<circle cx="%s" cy="%s" r="%s" fill="%s" fill-opacity="%s" stroke="#fff" stroke-width="2"><title>%s: %s جستجو، ورود %s ماهه</title></circle>', $x( $o ), $y( $o['volume'] ), round( $r, 1 ), $hl ? $c['accent'] : $c['muted'], $hl ? '1' : '.55', esc_attr( $o['label'] ), esc_attr( ost_num( $o['volume'] ) ), esc_attr( ost_num( $o['months'][0] ) ) );
		if ( $hl ) {
			$svg .= ost_svg_text( $x( $o ), $y( $o['volume'] ) + 5, ost_fa( $rank[ $o['key'] ] ), 'text-anchor="middle" font-size="13" font-weight="900" fill="#fff"' );
		}
	}
	return $svg . '</svg>';
}

/**
 * The signature roadmap: an astrolabe. Outer scale = the months of the period (Jalali
 * names), phase ring = the five phases (one blue hue, light → dark as the work matures,
 * each numbered), milestone ring = when each entry opportunity is expected to give its
 * first result, inner ring = expected monthly clicks (one teal hue, darker = more), and
 * the rule (alidade) points at the first quick win. Centre: clicks at the end of the period.
 *
 * @param array $phases     ost_roadmap() phases.
 * @param array $milestones [ ['n' => 1, 'month' => int, 'label' => string] ].
 * @param array $curve      Monthly clicks (index 0..months).
 * @param array $center     ['value' => string, 'label' => string, 'sub' => string].
 */
function ost_chart_astrolabe( $months, $phases, $milestones, $curve, $center, $size = 500 ) {
	$c     = ost_chart_colors();
	$cx    = $size / 2;
	$cy    = $size / 2;
	$ramp  = array( '#5DB3EC', '#2E8FDB', '#1570C2', '#0B529A', '#0A3566' );
	$teal  = '#0F9F8F';
	$amber = '#D97706';
	$months = max( 1, (int) $months );
	$ang   = function ( $m ) use ( $months ) { // Month boundary (0..months) → radians, clockwise from the top.
		return -M_PI / 2 + 2 * M_PI * $m / $months;
	};
	$pt    = function ( $r, $a ) use ( $cx, $cy ) {
		return array( round( $cx + $r * cos( $a ), 2 ), round( $cy + $r * sin( $a ), 2 ) );
	};
	$arc   = function ( $r1, $r2, $a1, $a2 ) use ( $pt ) {
		$large = ( $a2 - $a1 ) > M_PI ? 1 : 0;
		list( $x1, $y1 ) = $pt( $r2, $a1 );
		list( $x2, $y2 ) = $pt( $r2, $a2 );
		list( $x3, $y3 ) = $pt( $r1, $a2 );
		list( $x4, $y4 ) = $pt( $r1, $a1 );
		return sprintf( 'M%s %s A%s %s 0 %d 1 %s %s L%s %s A%s %s 0 %d 0 %s %s Z', $x1, $y1, $r2, $r2, $large, $x2, $y2, $x3, $y3, $r1, $r1, $large, $x4, $y4 );
	};
	$s  = sprintf( '<svg class="ost-astro" viewBox="0 0 %1$d %1$d" width="%1$d" height="%1$d" role="img" aria-label="نقشه راه %2$s ماهه">', $size, esc_attr( ost_fa( $months ) ) );
	$s .= sprintf( '<defs><radialGradient id="astro-bg" cx="50%%" cy="50%%" r="50%%"><stop offset="0" stop-color="#F3F9FF"/><stop offset="1" stop-color="#E6F4FD"/></radialGradient></defs><circle cx="%1$s" cy="%1$s" r="%2$s" fill="url(#astro-bg)" stroke="%3$s" stroke-width="1.5"/>', $cx, $size / 2 - 4, $c['grid'] );
	// Outer scale: one tick per month, every month named.
	$r_out = $size / 2 - 8;
	for ( $m = 0; $m < $months; $m++ ) {
		list( $x1, $y1 ) = $pt( $r_out, $ang( $m ) );
		list( $x2, $y2 ) = $pt( $r_out - 12, $ang( $m ) );
		$s .= sprintf( '<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="%s" stroke-width="1.5"/>', $x1, $y1, $x2, $y2, $c['muted'] );
		$mid             = $ang( $m + 0.5 );
		list( $tx, $ty ) = $pt( $r_out - 24, $mid );
		$name            = explode( ' ', ost_jmonth_from_now( $m ) )[0];
		$s .= ost_svg_text( $tx, $ty + 4, $name, 'text-anchor="middle" font-size="11" font-weight="700" fill="' . $c['ink2'] . '"' );
	}
	// Phase ring.
	$r1 = $size / 2 - 104;
	$r2 = $size / 2 - 52;
	foreach ( array_values( $phases ) as $i => $ph ) {
		$a1   = $ang( $ph['from'] - 1 ) + 0.012;
		$a2   = $ang( $ph['to'] ) - 0.012;
		$fill = $ramp[ min( $i, 4 ) ];
		$s   .= sprintf( '<path d="%s" fill="%s"/>', $arc( $r1, $r2, $a1, $a2 ), $fill );
		list( $tx, $ty ) = $pt( ( $r1 + $r2 ) / 2, ( $a1 + $a2 ) / 2 );
		$s .= sprintf( '<circle cx="%s" cy="%s" r="13" fill="#fff" fill-opacity="%s"/>', $tx, $ty, 0 === $i ? '0.9' : '0.18' );
		$s .= ost_svg_text( $tx, $ty + 5, ost_fa( $i + 1 ), 'text-anchor="middle" font-size="15" font-weight="900" fill="' . ( 0 === $i ? $c['ink'] : '#fff' ) . '"' );
	}
	// Inner ring: expected monthly clicks (darker = more).
	$r3  = $r1 - 30;
	$r4  = $r1 - 54;
	$max = max( 1, max( array_map( 'intval', (array) $curve ) ) );
	for ( $m = 1; $m <= $months; $m++ ) {
		$v   = (int) ( $curve[ $m ] ?? 0 );
		$s  .= sprintf( '<path d="%s" fill="%s" fill-opacity="%s"/>', $arc( $r4, $r3, $ang( $m - 1 ) + 0.01, $ang( $m ) - 0.01 ), $teal, round( 0.12 + 0.88 * $v / $max, 2 ) );
	}
	// Milestones: first result of each entry opportunity; several in one month share its
	// span, alternating between two radii so they never overlap.
	$month_of = function ( $ms ) use ( $months ) {
		return max( 1, min( $months, (int) $ms['month'] ) );
	};
	$count = array_count_values( array_map( $month_of, $milestones ) );
	$s    .= '<!--rule-->';
	$seen  = array();
	$first = null;
	foreach ( $milestones as $ms ) {
		$mm          = $month_of( $ms );
		$k           = $seen[ $mm ] ?? 0;
		$seen[ $mm ] = $k + 1;
		$a           = $ang( $mm - 1 + ( $k + 0.5 ) / $count[ $mm ] );
		$r           = ( $r3 + $r1 ) / 2 + ( $count[ $mm ] > 1 ? ( 0 === $k % 2 ? 7 : -7 ) : 0 );
		list( $x, $y ) = $pt( $r, $a );
		if ( null === $first ) { // The first quick win gets a halo.
			$first = array( $x, $y, $a );
			$s    .= sprintf( '<circle cx="%s" cy="%s" r="15" fill="%s" fill-opacity=".22"/>', $x, $y, $amber );
		}
		$s .= sprintf( '<circle cx="%s" cy="%s" r="8.5" fill="%s" stroke="#fff" stroke-width="2"/>', $x, $y, $amber );
		$s .= ost_svg_text( $x, $y + 4, ost_fa( $ms['n'] ), 'text-anchor="middle" font-size="10.5" font-weight="900" fill="#fff"' );
	}
	// The rule (alidade) through the centre, pointing at the first quick win.
	if ( $first ) {
		list( $x2, $y2 ) = $pt( $r4 - 12, $first[2] + M_PI );
		$s = str_replace( '<!--rule-->', sprintf( '<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="%s" stroke-width="2" stroke-opacity=".45" stroke-dasharray="2 5" stroke-linecap="round"/>', $first[0], $first[1], $x2, $y2, $amber ), $s );
	}
	// Centre.
	$rc  = $r4 - 12;
	$s  .= sprintf( '<circle cx="%1$s" cy="%2$s" r="%3$s" fill="%4$s"/><circle cx="%1$s" cy="%2$s" r="%5$s" fill="none" stroke="#7DD3FC" stroke-opacity=".5" stroke-width="1"/>', $cx, $cy, $rc, $c['ink'], $rc - 7 );
	$rtl = 'direction="rtl" unicode-bidi="embed" ';
	$s  .= ost_svg_text( $cx, $cy - 22, $center['label'], $rtl . 'text-anchor="middle" font-size="12" font-weight="700" fill="#BFDBFE"' );
	$s  .= ost_svg_text( $cx, $cy + 10, $center['value'], $rtl . 'text-anchor="middle" font-size="' . ( mb_strlen( $center['value'] ) > 12 ? 20 : 26 ) . '" font-weight="900" fill="#fff"' );
	$s  .= ost_svg_text( $cx, $cy + 34, $center['sub'], $rtl . 'text-anchor="middle" font-size="11.5" fill="#BFDBFE"' );
	return $s . '</svg>';
}
