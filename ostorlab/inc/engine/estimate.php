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
		'target_share' => 0,  // Ronagh's traffic target, % of the field's demand (0 = suggested).
		'target'     => 0,    // Or the monthly visits directly.
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
	if ( 'leads' === ost_biz( $in['business_type'] ?? 'shop' )['basis'] ) {
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

/**
 * Share of the measured search demand each site captures today (its estimated clicks from
 * the market's keywords ÷ the market's monthly searches).
 *
 * @return array domain => share (0..1)
 */
function ost_demand_share( $a ) {
	$total = max( 1, (int) ( $a['market']['total'] ?? 0 ) );
	$out   = array();
	foreach ( (array) ( $a['profiles'] ?? array() ) as $d => $pr ) {
		$out[ $d ] = min( 1, (int) ( $pr['market_clicks'] ?? 0 ) / $total );
	}
	arsort( $out );
	return $out;
}

/**
 * Ronagh's traffic target: monthly organic visits from Google for the whole site (not only
 * money keywords), as a share of the total demand of the field. Suggested = the level of the
 * average competitor today; the manager can set the share or the visits directly.
 *
 * @return array {share, visits, suggested, basis, months}
 */
function ost_target( $a, $in, $est ) {
	$e     = (array) $a['estimate'];
	$total = max( 1, (int) $e['total'] );
	$comp  = array();
	foreach ( (array) $a['profiles'] as $d => $pr ) {
		if ( 'competitor' === $pr['role'] && ! empty( $pr['market_clicks'] ) ) {
			$comp[] = (int) $pr['market_clicks'];
		}
	}
	$lt        = max( 1, (float) $e['longtail'] );
	$suggested = $comp ? 100 * ost_avg( $comp ) * $lt / $total : 3;
	$suggested = max( 1, min( 25, round( $suggested * 2 ) / 2 ) );
	$basis     = $comp ? 'رسیدن به سطح میانگین رقبای امروز' : 'سهم معمول یک سایت فعال در این بازار';
	$share     = (float) $est['target_share'] > 0 ? (float) $est['target_share'] : $suggested;
	$visits    = (int) $est['target'] > 0 ? (int) $est['target'] : (int) ( round( $total * $share / 100 / 10 ) * 10 );
	if ( (int) $est['target'] > 0 ) {
		$share = round( 100 * $visits / $total, 1 );
		$basis = 'هدف تعیین‌شده توسط تیم رونق';
	} elseif ( (float) $est['target_share'] > 0 ) {
		$basis = 'سهم هدف تعیین‌شده توسط تیم رونق';
	}
	// Never below the forecast itself.
	$floor = (int) ( $a['forecast']['scenarios']['high'] ?? 0 );
	if ( $floor > $visits && ! (float) $est['target_share'] && ! (int) $est['target'] ) { // The manager's own target is kept as set.
		$visits = (int) ( ceil( $floor / 10 ) * 10 );
		$share  = round( 100 * $visits / $total, 1 );
	}
	return array(
		'share'     => $share,
		'visits'    => $visits,
		'suggested' => $suggested,
		'basis'     => $basis,
		'months'    => (int) $in['months'],
		'today'     => (int) ( $a['forecast']['baseline'] ?? 0 ),
	);
}

/**
 * Gaps found in the analysis, each with its number and source: the "why SEO, why now"
 * facts used in the summary, the weaknesses and the AI texts.
 *
 * @return array [{k, text, n, src}]
 */
function ost_gap_facts( $a, $in ) {
	$cd    = (string) ( $a['client_domain'] ?? '' );
	$out   = array();
	$share = (array) ( $a['demand_share'] ?? array() );
	if ( $cd && isset( $share[ $cd ] ) && count( $share ) > 1 ) {
		$lead = array_key_first( $share );
		if ( $lead !== $cd && $share[ $lead ] > $share[ $cd ] ) {
			$out[] = array( 'k' => 'share', 'n' => $share[ $cd ], 'src' => 'سئو سیگنال', 'text' => sprintf( 'سهم شما از تقاضای این بازار %s است؛ %s حدود %s را می‌گیرد.', ost_share_text( $share[ $cd ] ), $lead, ost_pct( $share[ $lead ] ) ) );
		}
	}
	$noq = array_filter( (array) ( $a['strategy']['all'] ?? array() ), function ( $o ) {
		return null === $o['client_pos'] && $o['buyer_share'] >= 0.5;
	} );
	if ( $noq ) {
		$out[] = array( 'k' => 'money', 'n' => count( $noq ), 'src' => 'سئو سیگنال', 'text' => sprintf( '%s موضوع خرید با حدود %s جستجوی ماهانه که سایت شما در آن‌ها هیچ رتبه‌ای ندارد.', ost_num( count( $noq ) ), ost_compact( array_sum( array_column( $noq, 'volume' ) ) ) ) );
	}
	if ( ! empty( $a['gap']['count'] ) ) {
		$out[] = array( 'k' => 'gap', 'n' => (int) $a['gap']['count'], 'src' => 'سئو سیگنال', 'text' => sprintf( '%s کلمه که رقبا در آن‌ها رتبه دارند و شما ندارید (حدود %s جستجوی ماهانه).', ost_num( $a['gap']['count'] ), ost_compact( $a['gap']['volume'] ?? 0 ) ) );
	}
	$gsc = (array) ( $a['gsc'] ?? array() );
	if ( ! empty( $gsc['strike'] ) ) {
		$out[] = array( 'k' => 'strike', 'n' => count( $gsc['strike'] ), 'src' => 'سرچ کنسول', 'text' => sprintf( '%s کلمه در رتبه ۴ تا ۲۰ با حدود %s نمایش در ۳ ماه؛ نزدیک‌ترین بردها.', ost_num( count( $gsc['strike'] ) ), ost_compact( array_sum( array_column( $gsc['strike'], 'impressions' ) ) ) ) );
	}
	if ( ! empty( $gsc['queries'] ) ) {
		$low = array_filter( $gsc['queries'], function ( $q ) {
			return $q['impressions'] >= 300 && $q['clicks'] / max( 1, $q['impressions'] ) < 0.01;
		} );
		if ( count( $low ) >= 3 ) {
			$out[] = array( 'k' => 'ctr', 'n' => count( $low ), 'src' => 'سرچ کنسول', 'text' => sprintf( '%s جستجو با حدود %s نمایش ولی نرخ کلیک زیر ۱٪؛ دیده می‌شوید ولی انتخاب نمی‌شوید.', ost_num( count( $low ) ), ost_compact( array_sum( array_column( $low, 'impressions' ) ) ) ) );
		}
		if ( ! empty( $gsc['brand']['brand'] ) ) {
			$all = max( 1, $gsc['brand']['brand'] + $gsc['brand']['nonbrand'] );
			if ( $gsc['brand']['brand'] / $all > 0.5 ) {
				$out[] = array( 'k' => 'brand', 'n' => $gsc['brand']['brand'] / $all, 'src' => 'سرچ کنسول', 'text' => sprintf( '%s از کلیک‌های گوگل برندی است؛ یعنی مشتری تازه‌ای که هنوز شما را نمی‌شناسد، کمتر به سایت می‌رسد.', ost_pct( $gsc['brand']['brand'] / $all ) ) );
			}
		}
	}
	$te = (array) ( $a['technical'][ $cd ] ?? array() );
	if ( $cd && isset( $te['score'] ) && null !== $te['score'] ) {
		$high = array_filter( (array) $te['issues'], function ( $i ) {
			return 'high' === $i['sev'];
		} );
		if ( $high || $te['score'] < 70 ) {
			$out[] = array( 'k' => 'tech', 'n' => count( $high ), 'src' => ! empty( $te['has_sf'] ) ? 'اسکریمینگ فراگ' : 'بررسی فنی', 'text' => sprintf( 'امتیاز فنی %s از ۱۰۰%s.', ost_num( $te['score'] ), $high ? '؛ ' . ost_num( count( $high ) ) . ' مشکل مهم: ' . implode( '، ', array_slice( array_column( $high, 'label' ), 0, 2 ) ) : '' ) );
		}
	}
	if ( $cd && ost_deck_has_offpage( $a ) ) {
		$mine = (int) ( $a['offpage'][ $cd ]['quality_reportages'] ?? 0 );
		$cmp  = array();
		foreach ( (array) $a['offpage'] as $d => $o ) {
			if ( $d !== $cd ) {
				$cmp[] = (int) $o['quality_reportages'];
			}
		}
		$avg = $cmp ? (int) round( ost_avg( $cmp ) ) : 0;
		if ( $avg > $mine ) {
			$out[] = array( 'k' => 'offpage', 'n' => $avg - $mine, 'src' => 'سئو سیگنال', 'text' => sprintf( 'رقبا به‌طور میانگین %s رپورتاژ باکیفیت دارند و شما %s.', ost_num( $avg ), ost_num( $mine ) ) );
		}
	}
	return $out;
}

/**
 * A small share in words: "کمتر از ۰٫۱٪", "حدود ۰٫۴٪", "حدود ۸٪".
 */
function ost_share_text( $share ) {
	if ( $share < 0.001 ) {
		return 'کمتر از ۰٫۱٪';
	}
	return 'حدود ' . ost_pct( $share, $share < 0.01 ? 1 : 0 );
}
