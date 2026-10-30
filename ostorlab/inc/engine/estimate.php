<?php
/**
 * Demand and sales estimate, transparent and adjustable by the SEO manager.
 *
 * Every market number comes from the reviewed keyword list. That list is a starting
 * point, not the whole market: after the work starts, Search Console reveals many
 * long-tail and new searches that were not in it. So two figures are kept side by side:
 * "only the reviewed keywords" (measured) and "with undiscovered keywords" (the measured
 * demand × a long-tail factor). The manager sets that factor, scales the forecast,
 * sets conversion rate / order value / lead close rate, or types the end-of-period
 * clicks directly; the whole proposal (forecast, astrolabe, packages, revenue) follows.
 *
 * @package Ostorlab
 */

defined( 'ABSPATH' ) || exit;

/**
 * Long-tail factors offered in the form: factor => label.
 */
function ost_longtail_options() {
	return array(
		'1'   => 'فقط کلمات بررسی‌شده (بدون برآورد اضافه)',
		'1.3' => '۱٫۳ برابر — بازار خیلی محدود و تخصصی',
		'1.5' => '۱٫۵ برابر — محتاطانه',
		'1.8' => '۱٫۸ برابر — پیشنهادی (معمول برای ۵ تا ۷ کلمه اصلی)',
		'2.2' => '۲٫۲ برابر — بازار متنوع با محصولات زیاد',
		'2.5' => '۲٫۵ برابر — فروشگاه بزرگ / خدمات گسترده',
		'3'   => '۳ برابر — بازار خیلی گسترده',
	);
}

/**
 * Estimate settings of a proposal.
 */
function ost_estimate_settings( $id ) {
	$e = get_post_meta( $id, '_ost_estimate', true );
	return array_replace( array(
		'longtail'   => 1.8,  // Real demand ÷ demand of the reviewed keywords.
		'adjust'     => 100,  // Forecast scale, percent.
		'headline'   => 'all', // all = headline numbers include undiscovered keywords; list = reviewed keywords only.
		'close_rate' => 30,   // Leads that become customers (service businesses), percent.
		'lead_rate'  => 0,    // Visitors who become leads, percent (0 = default setting).
		'low'        => 0,    // Manual end-of-period clicks (0 = automatic).
		'high'       => 0,
	), is_array( $e ) ? $e : array() );
}

/**
 * Scale a forecast: the growth above today's baseline is multiplied by $factor
 * (the baseline itself is real and stays). Manual end values replace the scenarios.
 */
function ost_forecast_scale( $f, $factor, $low = 0, $high = 0 ) {
	$base = (int) $f['baseline'];
	$want = array();
	foreach ( $f['scenarios'] as $k => $v ) {
		$want[ $k ] = $base + max( 0, $v - $base ) * $factor;
	}
	if ( $low > 0 && $high >= $low ) {
		$want = array( 'low' => $low, 'mid' => ( $low + $high ) / 2, 'high' => $high );
	}
	foreach ( $want as $k => $target ) {
		$old = max( 1, $f['scenarios'][ $k ] - $base );
		foreach ( (array) ( $f['curve'][ $k ] ?? array() ) as $m => $v ) {
			$f['curve'][ $k ][ $m ] = (int) round( $base + ( $v - $base ) * ( $target - $base ) / $old );
		}
		$f['scenarios'][ $k ] = (int) round( $target );
	}
	$f['buyer_mid'] = (int) round( $f['scenarios']['mid'] * $f['buyer_share'] );
	return $f;
}

/**
 * Money from clicks: orders × order value (shop), or leads × close rate × deal value (services).
 *
 * @return array {per_click: toman per buyer click, rate: orders or leads per buyer click, basis}
 */
function ost_click_value( $in, $est ) {
	$aov = (float) ( $in['aov'] ?? 0 );
	if ( in_array( $in['business_type'] ?? 'shop', array( 'service', 'local', 'saas' ), true ) ) {
		$lr = ( (float) $est['lead_rate'] ?: (float) ost_setting( 'lead_rate', 3 ) ) / 100;
		return array( 'per_click' => $lr * (float) $est['close_rate'] / 100 * $aov, 'rate' => $lr, 'basis' => 'leads' );
	}
	$cr = max( 0.1, (float) ( $in['conversion_rate'] ?? 1.5 ) ) / 100;
	return array( 'per_click' => $cr * $aov, 'rate' => $cr, 'basis' => 'orders' );
}

/**
 * The estimate shown in the demand / sales slides and the estimate tab.
 *
 * @param array $list Forecast from the reviewed keywords only (already adjusted).
 */
function ost_estimate( $a, $list, $in, $est ) {
	$m      = (array) $a['market'];
	$lt     = max( 1, (float) $est['longtail'] );
	$all    = ost_forecast_scale( $list, $lt );
	$money  = ost_click_value( $in, $est );
	$months = max( 1, (int) $in['months'] );
	$col    = function ( $f ) use ( $money, $months ) {
		$share = (float) $f['buyer_share'];
		$out   = array();
		foreach ( array( 'low', 'high' ) as $k ) {
			$clicks = (int) $f['scenarios'][ $k ];
			$buyer  = $clicks * $share;
			$extra  = 0; // Sales above today's level, summed over the period.
			for ( $i = 1; $i <= $months; $i++ ) {
				$extra += max( 0, (int) ( $f['curve'][ $k ][ $i ] ?? 0 ) - (int) $f['baseline'] ) * $share;
			}
			$out[ $k ] = array(
				'clicks'  => $clicks,
				'buyer'   => (int) round( $buyer ),
				'orders'  => (int) round( $buyer * $money['rate'] ),
				'revenue' => $buyer * $money['per_click'],
				'period'  => $extra * $money['per_click'],
			);
		}
		return $out;
	};
	return array(
		'keywords' => (int) ( $m['keywords'] ?? 0 ),
		'clusters' => count( (array) ( $m['clusters'] ?? array() ) ),
		'measured' => (int) ( $m['total'] ?? 0 ),
		'buyer'    => (int) ( $m['buyer'] ?? 0 ),
		'longtail' => $lt,
		'total'    => (int) round( ( $m['total'] ?? 0 ) * $lt ),
		'buyer_all' => (int) round( ( $m['buyer'] ?? 0 ) * $lt ),
		'list'     => $col( $list ),
		'all'      => $col( $all ),
		'basis'    => $money['basis'],
		'rate'     => $money['rate'],
		'has_money' => $money['per_click'] > 0,
		'baseline' => (int) $list['baseline'],
		'headline' => $lt > 1 ? $est['headline'] : 'list',
		'adjust'   => (int) $est['adjust'],
		'manual'   => $est['low'] > 0,
		'close_rate' => (float) $est['close_rate'],
	);
}

/**
 * Default transparency note (editable in the texts tab).
 */
function ost_scope_note( $e ) {
	if ( empty( $e['keywords'] ) ) {
		return '';
	}
	$t = sprintf( 'همه عددهای بازار این پروپوزال از %s کلمه کلیدی بررسی‌شده در %s خوشه به دست آمده؛ کلماتی که حجم جستجویشان با ابزار سئو سیگنال اندازه‌گیری و برای کسب‌وکار شما پالایش شده است. این فهرست نقطه شروع است، نه کل بازار.', ost_num( $e['keywords'] ), ost_num( $e['clusters'] ) );
	$t .= "\n\n" . 'بعد از شروع کار، با داده سرچ کنسول و تحقیق ادامه‌دار، معمولاً صدها جستجوی کم‌حجم، جدید و فصلی (دم بلند) پیدا می‌شود که اینجا شمرده نشده‌اند؛ مجموع همین جستجوهای کوچک اغلب از کلمات اصلی بیشتر است.';
	if ( $e['longtail'] > 1 ) {
		$t .= sprintf( ' برای همین تقاضای واقعی حوزه را حدود %s برابر عدد اندازه‌گیری‌شده برآورد کرده‌ایم و هر دو عدد را جدا نشان می‌دهیم: عدد اندازه‌گیری‌شده قطعی‌تر است و عدد کل، تصویر واقعی‌تری از بازار می‌دهد.', ost_num( $e['longtail'], 1 ) );
	}
	return $t;
}
