<?php
/**
 * Entry strategy ("سرپل"): where to start so the first results come fastest.
 *
 * Keywords are grouped into topics that one page can win (the keyword without
 * buy / price / best … modifiers: «خرید مبل ال کوچک», «قیمت مبل ال کوچک» and
 * «مبل ال کوچک» are one topic). Each topic is scored on:
 *   - demand: monthly searches (diminishing returns),
 *   - money: share of buy / compare / local searches,
 *   - ease: SEO Signal competition label,
 *   - rivals: how many competitors already sit in the top 3 / top 10 for it,
 *   - proof: competitors rank for it and the client does not (keyword gap),
 *   - foothold: the client already ranks 4–20 (an update can reach page one).
 * The best topics get a time-to-result estimate, the expected clicks / orders,
 * plain-language reasons and a 90-day plan. The SEO manager can pin or drop
 * topics in the strategy tab.
 *
 * @package Ostorlab
 */

defined( 'ABSPATH' ) || exit;

/**
 * Words that do not change the topic of a keyword.
 */
function ost_topic_stopwords() {
	static $stop = null;
	if ( null === $stop ) {
		$words = array( 'از', 'به', 'در', 'با', 'و', 'برای', 'که', 'را', 'های', 'ها', 'یک', 'تا', 'بر', 'هم', 'ی', 'انواع', 'لیست', 'مدل', 'مدلهای', 'مدل‌های', 'جدید', 'جدیدترین', 'ارزان', 'ارزانترین', 'ارزان‌ترین', 'بهترین', 'خرید', 'قیمت', 'فروش', 'سفارش', 'آنلاین', 'اینترنتی', 'تخفیف', 'عمده', 'چیست', 'چیه', 'چگونه', 'چطور', 'نحوه', 'راهنمای', 'راهنما', 'انتخاب', 'مشخصات', 'بررسی', 'معرفی', 'مقایسه', 'هزینه', 'تعرفه', 'اقساطی', 'قسطی', 'لیست قیمت', 'عکس', 'تصاویر', 'شیک', 'جدید ۱۴۰۵', '1405', '1404', '۱۴۰۵', '۱۴۰۴' );
		$stop  = array_flip( array_map( 'ost_norm', $words ) );
	}
	return $stop;
}

/**
 * Topic key of a keyword.
 */
function ost_topic_key( $word ) {
	$stop   = ost_topic_stopwords();
	$tokens = array_filter( explode( ' ', ost_norm( $word ) ), function ( $t ) use ( $stop ) {
		return '' !== $t && ! isset( $stop[ $t ] );
	} );
	return implode( ' ', $tokens );
}

/**
 * Ease of a competition label (SEO Signal: آسان / معمولی / سخت).
 */
function ost_ease( $label ) {
	$l = (string) $label;
	if ( false !== mb_strpos( $l, 'سخت' ) || false !== stripos( $l, 'high' ) ) {
		return 0.3;
	}
	if ( false !== mb_strpos( $l, 'آسان' ) || false !== mb_strpos( $l, 'کم' ) || false !== stripos( $l, 'low' ) ) {
		return 1.0;
	}
	return 0.65;
}

/**
 * Position of each keyword per domain (from SEO Signal site analyzer: the page's average position).
 *
 * @return array norm word => [domain => position]
 */
function ost_positions( $ss ) {
	$out = array();
	foreach ( (array) $ss as $domain => $site ) {
		$pos_of = array();
		foreach ( (array) ( $site['pages'] ?? array() ) as $pg ) {
			$pos_of[ $pg['p'] ] = $pg['avg'] > 0 ? (float) $pg['avg'] : 15;
		}
		foreach ( (array) ( $site['keywords'] ?? array() ) as $k ) {
			$w = ost_norm( $k['w'] );
			$p = $pos_of[ $k['p'] ] ?? 15;
			if ( ! isset( $out[ $w ][ $domain ] ) || $p < $out[ $w ][ $domain ] ) {
				$out[ $w ][ $domain ] = $p;
			}
		}
	}
	return $out;
}

/**
 * All scored topics.
 *
 * @param array  $market Market (with rows).
 * @param array  $ss     SEO Signal site data per domain.
 * @param array  $gap    ost_keyword_gap() result.
 * @param string $client Client domain ('' = no site).
 * @param array  $gsc    Search Console data (strike queries).
 * @param array  $in     Proposal inputs.
 * @return array topic key => topic, best first
 */
function ost_opportunities( $market, $ss, $gap, $client, $gsc, $in ) {
	$pos    = ost_positions( $ss );
	$gapset = array();
	foreach ( (array) ( $gap['top'] ?? array() ) as $g ) {
		$gapset[ ost_norm( $g['w'] ) ] = $g['by'];
	}
	$strike = array();
	foreach ( (array) ( $gsc['strike'] ?? array() ) as $q ) {
		$strike[ ost_norm( $q['query'] ) ] = (float) $q['position'];
	}
	foreach ( (array) ( $gsc['queries'] ?? array() ) as $q ) {
		$strike[ ost_norm( $q['query'] ) ] = $strike[ ost_norm( $q['query'] ) ] ?? (float) $q['position'];
	}
	$comps  = array_values( array_diff( array_keys( (array) $ss ), array( $client ) ) );
	$topics = array();
	foreach ( (array) ( $market['rows'] ?? array() ) as $r ) {
		$key = ost_topic_key( $r['w'] );
		if ( '' === $key ) {
			continue;
		}
		if ( ! isset( $topics[ $key ] ) ) {
			$topics[ $key ] = array( 'key' => $key, 'seed' => $r['seed'], 'words' => array(), 'volume' => 0, 'buyer' => 0, 'ease_sum' => 0, 'comp' => array(), 'client_pos' => null, 'gap_by' => array(), 'info' => 0 );
		}
		$t = &$topics[ $key ];
		$w = ost_norm( $r['w'] );
		$t['words'][]  = array( 'w' => $r['w'], 'v' => $r['v'], 'i' => $r['i'] );
		$t['volume']  += $r['v'];
		$t['buyer']   += $r['v'] * ( in_array( $r['i'], array( 'buy', 'compare', 'local' ), true ) ? 1 : ( 'general' === $r['i'] ? 0.5 : 0.15 ) );
		$t['info']    += 'info' === $r['i'] ? 1 : 0;
		$t['ease_sum'] += $r['v'] * ost_ease( $r['c'] );
		foreach ( $comps as $d ) {
			if ( isset( $pos[ $w ][ $d ] ) && ( ! isset( $t['comp'][ $d ] ) || $pos[ $w ][ $d ] < $t['comp'][ $d ] ) ) {
				$t['comp'][ $d ] = $pos[ $w ][ $d ];
			}
		}
		$cp = $client ? ( $strike[ $w ] ?? ( $pos[ $w ][ $client ] ?? null ) ) : null;
		if ( null !== $cp && ( null === $t['client_pos'] || $cp < $t['client_pos'] ) ) {
			$t['client_pos'] = $cp;
		}
		if ( isset( $gapset[ $w ] ) ) {
			$t['gap_by'] = array_values( array_unique( array_merge( $t['gap_by'], $gapset[ $w ] ) ) );
		}
		unset( $t );
	}
	foreach ( $topics as $k => &$t ) {
		if ( $t['volume'] < 40 ) {
			unset( $topics[ $k ] );
			continue;
		}
		usort( $t['words'], function ( $a, $b ) {
			return $b['v'] <=> $a['v'];
		} );
		$t['label']       = $t['words'][0]['w'];
		$t['buyer_share'] = $t['buyer'] / $t['volume'];
		$t['ease']        = $t['ease_sum'] / $t['volume'];
		$t['strong']      = count( array_filter( $t['comp'], function ( $p ) {
			return $p <= 3;
		} ) );
		$t['in_top10']    = count( array_filter( $t['comp'], function ( $p ) {
			return $p <= 10;
		} ) );
		$t['best_comp']   = $t['comp'] ? min( $t['comp'] ) : null;
		$foothold         = null !== $t['client_pos'] && $t['client_pos'] > 3 && $t['client_pos'] <= 20;
		$owned            = null !== $t['client_pos'] && $t['client_pos'] <= 3;
		$t['foothold']    = $foothold;
		$rival            = 1 / ( 1 + 0.45 * $t['strong'] + 0.12 * max( 0, $t['in_top10'] - $t['strong'] ) );
		$proof            = $t['gap_by'] ? 1.12 : ( 0 === $t['in_top10'] ? 1.08 : 1.0 );
		// A head term (the seed itself) is the category page: valuable but rarely the fastest win.
		$seed_tokens      = count( array_filter( explode( ' ', ost_topic_key( $t['seed'] ) ) ) );
		$own_tokens       = count( array_filter( explode( ' ', $t['key'] ) ) );
		$t['head']        = $own_tokens <= $seed_tokens;
		$specific         = $t['head'] ? 0.45 : ( $own_tokens > $seed_tokens ? 1.15 : 1 );
		$t['entry']       = ( 0.35 + 0.65 * $t['ease'] ) * $rival * ( $foothold ? 1.3 : 1 ) * ( $t['head'] ? 0.6 : 1 );
		$t['score']       = pow( $t['volume'], 0.72 ) * ( 0.25 + 0.75 * $t['buyer_share'] ) * ( 0.35 + 0.65 * $t['ease'] ) * $rival * $proof * ( $foothold ? 1.55 : 1 ) * ( $owned ? 0.25 : 1 ) * $specific;
		// Time to the first page-one results.
		$months      = $t['ease'] >= 0.85 ? 2 : ( $t['ease'] >= 0.55 ? 3 : 5 );
		$months     += min( 3, $t['strong'] );
		$months     -= $foothold ? 1 : 0;
		$months     += $t['head'] ? 2 : 0;
		$months      = (int) ost_clamp( $months, 1, 9 );
		$t['months'] = array( $months, $months + ( $months >= 4 ? 2 : 1 ) );
		// Expected position and clicks once won.
		$target       = $foothold ? 3 : ( $t['ease'] >= 0.85 && $t['strong'] < 2 ? 3 : 5 );
		$t['target']  = $target;
		$t['clicks']  = (int) round( $t['volume'] * ost_ctr( $target ) );
		$t['buyer_clicks'] = (int) round( $t['buyer'] * ost_ctr( $target ) );
		$t['revenue'] = ost_revenue_range( $t['buyer_clicks'], $in );
		$t['pages']   = ( $t['buyer_share'] >= 0.35 ? 1 : 0 ) + min( 3, max( $t['buyer_share'] >= 0.35 ? 0 : 1, (int) ceil( $t['info'] / 2 ) ) );
		$t['reportages'] = $t['strong'] >= 2 ? 3 : ( $t['strong'] ? 2 : 1 );
		$t['type']    = $foothold ? 'harvest' : ( 0 === $t['in_top10'] ? 'empty' : ( $t['gap_by'] ? 'gap' : 'easy' ) );
		$t['reasons'] = ost_opportunity_reasons( $t );
		$t['words']   = array_slice( $t['words'], 0, 6 );
		unset( $t['ease_sum'], $t['info'] );
	}
	unset( $t );
	uasort( $topics, function ( $a, $b ) {
		return $b['score'] <=> $a['score'];
	} );
	return array_slice( $topics, 0, 30, true );
}

/**
 * Plain-language reasons of a topic.
 */
function ost_opportunity_reasons( $t ) {
	$r   = array();
	$r[] = sprintf( '%s جستجوی ماهانه که %s آن نیت خرید یا مقایسه دارد', ost_num( $t['volume'] ), ost_pct( $t['buyer_share'] ) );
	if ( $t['foothold'] ) {
		$r[] = sprintf( 'سایت شما همین الان رتبه حدود %s دارد؛ با تقویت همان صفحه به صفحه اول می‌رسد', ost_num( $t['client_pos'] ) );
	}
	if ( 0 === $t['in_top10'] ) {
		$r[] = 'هیچ‌کدام از رقبای اصلی در ۱۰ نتیجه اول گوگل برای آن نیستند؛ میدان خالی است';
	} elseif ( 0 === $t['strong'] ) {
		$r[] = sprintf( 'رقبا هستند ولی ضعیف: بهترین‌شان رتبه %s است، نه ۱ تا ۳', ost_num( $t['best_comp'] ) );
	} else {
		$r[] = sprintf( '%s رقیب در ۳ نتیجه اول؛ با صفحه کامل‌تر و لینک هدفمند قابل رقابت', ost_num( $t['strong'] ) );
	}
	if ( $t['gap_by'] ) {
		$r[] = sprintf( '%s برای آن رتبه و ترافیک دارد و شما صفحه‌ای ندارید؛ تقاضا ثابت‌شده است', implode( ' و ', array_slice( $t['gap_by'], 0, 2 ) ) );
	}
	$r[] = $t['ease'] >= 0.85 ? 'سختی رقابت کلمات: کم' : ( $t['ease'] >= 0.55 ? 'سختی رقابت کلمات: متوسط' : 'سختی رقابت کلمات: بالا، ولی ارزشش را دارد' );
	return $r;
}

/**
 * Strategy: the chosen topics (pinned by the SEO manager or the best five), totals and the 90-day plan.
 *
 * @param array $opps  ost_opportunities() result.
 * @param array $saved {picks: string[], off: string[]}.
 */
function ost_strategy( $opps, $saved, $in, $client ) {
	$off   = array_flip( (array) ( $saved['off'] ?? array() ) );
	$picks = array();
	foreach ( (array) ( $saved['picks'] ?? array() ) as $k ) {
		if ( isset( $opps[ $k ] ) ) {
			$picks[ $k ] = $opps[ $k ];
		}
	}
	// Fill up with the best topics: fast ones (first results within 4 months) first.
	foreach ( array( true, false ) as $fast_only ) {
		foreach ( $opps as $k => $o ) {
			if ( count( $picks ) >= 5 ) {
				break 2;
			}
			if ( ! isset( $picks[ $k ] ) && ! isset( $off[ $k ] ) && ( ! $fast_only || $o['months'][0] <= 4 ) ) {
				$picks[ $k ] = $o;
			}
		}
	}
	$picks = array_values( $picks );
	if ( ! $picks ) {
		return array( 'picks' => array(), 'all' => $opps );
	}
	$fast   = array_filter( $picks, function ( $o ) {
		return $o['months'][0] <= 3;
	} );
	$types  = array_count_values( array_column( $picks, 'type' ) );
	arsort( $types );
	$main   = array_key_first( $types );
	$names  = array(
		'harvest' => array( 'برداشت سریع', 'اول صفحاتی را به صفحه اول می‌رسانیم که همین حالا پشت در هستند؛ کمترین هزینه، سریع‌ترین نتیجه.' ),
		'empty'   => array( 'سرپل در میدان خالی', 'اول جایی را می‌گیریم که رقبا خالی گذاشته‌اند؛ تقاضا هست، رقیب جدی نیست.' ),
		'gap'     => array( 'ورود از شکاف رقبا', 'اول همان کلماتی را می‌گیریم که برای رقبا فروش می‌آورد و شما هنوز صفحه‌ای برایش ندارید.' ),
		'easy'    => array( 'ورود از ضلع کم‌رقابت', 'اول از کلمات پرجستجو با رقابت کم شروع می‌کنیم تا رشد زود دیده شود و اعتبار سایت بالا برود.' ),
	);
	$clicks = array_sum( array_column( $picks, 'clicks' ) );
	$buyer  = array_sum( array_column( $picks, 'buyer_clicks' ) );
	$fast_c = array_sum( array_column( $fast, 'clicks' ) );
	$labels = array_column( $picks, 'label' );
	$plan   = array(
		array( 'when' => 'هفته ۱ و ۲', 'title' => 'آماده‌سازی میدان', 'items' => array_values( array_filter( array( 'نقشه کلمات: هر فرصت به یک صفحه مشخص', $client ? 'رفع موانع فنی همین صفحات (سرعت، ایندکس، لینک داخلی)' : 'ساخت ساختار صفحات فرصت‌ها در سایت جدید', 'تحلیل ۱۰ نتیجه اول گوگل برای هر فرصت' ) ) ) ),
		array( 'when' => 'هفته ۳ تا ۶', 'title' => 'ساخت صفحات برنده', 'items' => array( 'صفحه اختصاصی و کامل برای «' . $labels[0] . '»' . ( isset( $labels[1] ) ? ' و «' . $labels[1] . '»' : '' ), 'عنوان و توضیحات جذاب برای کلیک بیشتر، اسکیما و سؤالات متداول', 'به‌روزرسانی صفحاتی که نزدیک صفحه اول هستند' ) ),
		array( 'when' => 'هفته ۷ تا ۱۰', 'title' => 'تقویت و لینک', 'items' => array( 'مقاله‌های پشتیبان که به صفحات فرصت لینک می‌دهند', 'اولین رپورتاژها با انکر دقیق همین فرصت‌ها', 'لینک از صفحات پربازدید خود سایت' ) ),
		array( 'when' => 'هفته ۱۱ تا ۱۳', 'title' => 'سنجش و گسترش', 'items' => array( 'گزارش تغییر رتبه و کلیک همین فرصت‌ها', isset( $labels[2] ) ? 'شروع فرصت‌های بعدی: «' . implode( '»، «', array_slice( $labels, 2, 3 ) ) . '»' : 'گسترش به کلمات نزدیک', 'تصمیم برای فاز بعد بر اساس داده' ) ),
	);
	return array(
		'picks'      => $picks,
		'all'        => $opps,
		'type'       => $main,
		'name'       => $names[ $main ][0],
		'idea'       => $names[ $main ][1],
		'volume'     => array_sum( array_column( $picks, 'volume' ) ),
		'clicks'     => $clicks,
		'buyer'      => $buyer,
		'fast_clicks' => $fast_c,
		'fast_count' => count( $fast ),
		'revenue'    => ost_revenue_range( $buyer, $in ),
		'pages'      => array_sum( array_column( $picks, 'pages' ) ),
		'reportages' => array_sum( array_column( array_slice( $picks, 0, 3 ), 'reportages' ) ),
		'plan'       => $plan,
		'day90'      => array( (int) round( $fast_c * 0.35 ), (int) round( $fast_c * 0.7 ) ),
	);
}

/**
 * Persian label of a topic type.
 */
function ost_opp_type_label( $type ) {
	return array( 'harvest' => 'برداشت سریع', 'empty' => 'میدان خالی', 'gap' => 'شکاف رقبا', 'easy' => 'کم‌رقابت' )[ $type ] ?? $type;
}
