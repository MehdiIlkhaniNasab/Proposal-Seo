<?php
/**
 * Cost calculator.
 *
 * Quantities come from the analysis (so every number can be explained to the
 * client): new landing pages from buyer keyword groups the client has no page
 * for, articles from the content gap with competitors, updates from pages that
 * are already close to page one, reportages from the competitors' pace of quality
 * reportages, backlinks from the quality referring-domain gap. Three packages
 * scale the quantities; every number can be overridden in the costs tab.
 *
 * Costs are split into the agency fee and the budgets the client pays directly
 * (content writers, reportage platforms), which is the trust-building model.
 *
 * @package Ostorlab
 */

defined( 'ABSPATH' ) || exit;

/**
 * Packages: key => [label, quantity factor, fee factor, note].
 */
function ost_packages() {
	return array(
		'basic'  => array( 'label' => 'پایه', 'q' => 0.6, 'fee' => 0.75, 'note' => 'حفظ و رشد آرام؛ مناسب بازارهای کم‌رقابت' ),
		'pro'    => array( 'label' => 'پیشنهادی', 'q' => 1.0, 'fee' => 1.0, 'note' => 'رسیدن به رقبا در زمان معقول؛ بهترین نسبت هزینه به نتیجه' ),
		'growth' => array( 'label' => 'رشد سریع', 'q' => 1.5, 'fee' => 1.4, 'note' => 'سبقت از رقبا؛ برای بازارهای پررقابت' ),
	);
}

/**
 * Suggested quantities for the recommended package, with the reason of each.
 */
function ost_budget_suggest( $a, $months ) {
	$market  = (array) ( $a['market'] ?? array() );
	$client  = $a['profiles'][ $a['client_domain'] ?? '' ] ?? array();
	$comps   = array_filter( (array) ( $a['profiles'] ?? array() ), function ( $p ) {
		return 'competitor' === ( $p['role'] ?? '' );
	} );
	$offc    = $a['offpage'][ $a['client_domain'] ?? '' ] ?? array();
	$offs    = array_intersect_key( (array) ( $a['offpage'] ?? array() ), $comps );
	$work    = max( 1, $months - 1 ); // Content and links start in month 2.
	$has_site = ! empty( $a['client_domain'] );

	// Landing / category pages for buyer keyword groups.
	$money_n  = count( (array) ( $market['money'] ?? array() ) );
	$have     = count( (array) ( $client['money_pages'] ?? array() ) );
	$landings = (int) ost_clamp( round( $money_n / 4 ) - $have, 3, 30 );

	// Articles: gap of ranking pages with competitors, or the question keywords.
	$comp_pages = ost_avg( array_column( $comps, 'ranking_pages' ) );
	$gap_pages  = max( 0, $comp_pages - (int) ( $client['ranking_pages'] ?? 0 ) );
	$questions  = count( (array) ( $market['questions'] ?? array() ) );
	$articles   = (int) ost_clamp( round( 0.45 * max( $gap_pages, $questions * 1.5 ) ), 3 * $work, 12 * $work );

	// Updates: pages already ranking 4–20 (fastest wins).
	$strike = 0;
	if ( ! empty( $a['gsc']['strike'] ) ) {
		$strike = count( $a['gsc']['strike'] );
	} elseif ( $client ) {
		$strike = count( array_filter( (array) ( $client['top_pages'] ?? array() ), function ( $p ) {
			return $p['pos'] > 3 && $p['pos'] <= 20;
		} ) ) * 3;
	}
	$updates = $has_site ? (int) ost_clamp( $strike, $work, 6 * $work ) : 0;

	// Reportages: the competitors' pace plus part of the gap.
	$q_comp = array();
	$pace   = array();
	foreach ( $offs as $o ) {
		$q_comp[] = (int) ( $o['quality_reportages'] ?? 0 );
		$r        = (array) ( $o['reportage'] ?? array() );
		$pace[]   = ! empty( $r['last12'] ) ? $r['last12'] / 12 : ( (int) ( $o['quality_reportages'] ?? 0 ) ) / 24;
	}
	$q_client   = (int) ( $offc['quality_reportages'] ?? 0 );
	$avg_q      = $q_comp ? ost_avg( $q_comp ) : 0;
	$avg_pace   = $pace ? ost_avg( $pace ) : 1;
	$reportages = (int) ost_clamp( round( 0.4 * max( 0, $avg_q - $q_client ) + $avg_pace * $work ), $work, 10 * $work );

	// Other quality backlinks (niche / guest posts), from the referring-domain gap.
	$ref_comp  = array_filter( array_map( function ( $o ) {
		return (int) ( $o['backlinks']['quality_refdomains'] ?? 0 );
	}, $offs ) );
	$ref_gap   = $ref_comp ? max( 0, ost_avg( $ref_comp ) - (int) ( $offc['backlinks']['quality_refdomains'] ?? 0 ) ) : 0;
	$backlinks = (int) ost_clamp( $ref_comp ? round( 0.25 * $ref_gap ) : 2 * $work, 0, 8 * $work );

	// Reportage price: median price competitors paid (SEO Signal), else the default.
	$prices = array_filter( array_map( function ( $o ) {
		return (float) ( $o['reportage']['price_avg'] ?? 0 );
	}, $offs ) );
	$price  = $prices ? round( ost_median( $prices ) / 100000 ) * 100000 : (float) ost_setting( 'price_reportage', 4500000 );

	return array(
		'qty'     => array( 'landings' => $landings, 'articles' => $articles, 'updates' => $updates, 'reportages' => $reportages, 'backlinks' => $backlinks ),
		'reportage_price' => $price,
		'why'     => array(
			'landings'   => sprintf( '%s کلمه خرید و مقایسه پرجستجو پیدا شد%s؛ هر گروه کلمه خرید یک صفحه فرود/دسته اختصاصی لازم دارد.', ost_num( $money_n ), $have ? sprintf( ' و %s صفحه فعلی شما برای آن‌ها رتبه دارد', ost_num( $have ) ) : '' ),
			'articles'   => $comp_pages ? sprintf( 'رقبا به‌طور میانگین با %s صفحه در گوگل رتبه دارند و شما با %s صفحه؛ به‌علاوه %s سؤال پرجستجوی مشتریان.', ost_num( $comp_pages ), ost_num( (int) ( $client['ranking_pages'] ?? 0 ) ), ost_num( $questions ) ) : sprintf( '%s سؤال پرجستجوی مشتریان در این حوزه پیدا شد.', ost_num( $questions ) ),
			'updates'    => $has_site ? sprintf( '%s صفحه/کلمه شما نزدیک صفحه اول است (رتبه ۴ تا ۲۰)؛ به‌روزرسانی آن‌ها سریع‌ترین نتیجه را می‌دهد.', ost_num( $strike ) ) : 'سایت جدید است؛ به‌روزرسانی از ماه‌های بعد و بعد از انتشار محتوا شروع می‌شود.',
			'reportages' => $q_comp ? sprintf( 'رقبا به‌طور میانگین %s رپورتاژ باکیفیت دارند (ماهانه حدود %s رپورتاژ تازه) و شما %s؛ این عدد فاصله را در طول دوره کم می‌کند.', ost_num( $avg_q ), ost_num( $avg_pace, 1 ), ost_num( $q_client ) ) : 'بر اساس میانگین بازار؛ با اتصال دستیار رپورتاژ دقیق‌تر می‌شود.',
			'backlinks'  => $ref_comp ? sprintf( 'رقبا به‌طور میانگین از %s دامنه باکیفیت لینک گرفته‌اند و شما از %s دامنه.', ost_num( ost_avg( $ref_comp ) ), ost_num( (int) ( $offc['backlinks']['quality_refdomains'] ?? 0 ) ) ) : 'لینک‌های مکمل تخصصی (غیر رپورتاژ) برای تنوع پروفایل لینک.',
		),
	);
}

/**
 * Full budget: three packages with monthly plan and cost lines.
 *
 * @param array $suggest   ost_budget_suggest() result.
 * @param array $overrides Saved edits {fee: {pkg: n}, qty: {pkg: {item: n}}, price: {item: n}, selected}.
 */
function ost_budget( $suggest, $months, $overrides = array() ) {
	$prices = array(
		'landings'   => (float) ( $overrides['price']['landings'] ?? ost_setting( 'price_landing', 2500000 ) ),
		'articles'   => (float) ( $overrides['price']['articles'] ?? ost_setting( 'price_article', 1500000 ) ),
		'updates'    => (float) ( $overrides['price']['updates'] ?? ost_setting( 'price_update', 700000 ) ),
		'reportages' => (float) ( $overrides['price']['reportages'] ?? $suggest['reportage_price'] ),
		'backlinks'  => (float) ( $overrides['price']['backlinks'] ?? ost_setting( 'price_backlink', 1200000 ) ),
	);
	$fee_base = (float) ost_setting( 'fee_monthly', 25000000 );
	$out      = array( 'months' => $months, 'prices' => $prices, 'packages' => array(), 'selected' => $overrides['selected'] ?? 'pro', 'why' => $suggest['why'] );
	foreach ( ost_packages() as $key => $pk ) {
		$qty = array();
		foreach ( $suggest['qty'] as $item => $n ) {
			$qty[ $item ] = isset( $overrides['qty'][ $key ][ $item ] ) && '' !== $overrides['qty'][ $key ][ $item ]
				? (int) $overrides['qty'][ $key ][ $item ]
				: (int) round( $n * $pk['q'] );
		}
		$fee   = isset( $overrides['fee'][ $key ] ) && '' !== $overrides['fee'][ $key ] ? (float) $overrides['fee'][ $key ] : round( $fee_base * $pk['fee'] / 500000 ) * 500000;
		$lines = array();
		foreach ( $qty as $item => $n ) {
			$lines[ $item ] = $n * $prices[ $item ];
		}
		$direct = array_sum( $lines );
		$fees   = $fee * $months;
		$out['packages'][ $key ] = array(
			'label'   => $pk['label'],
			'note'    => $pk['note'],
			'qty'     => $qty,
			'fee'     => $fee,
			'lines'   => $lines,
			'content' => $lines['landings'] + $lines['articles'] + $lines['updates'],
			'offpage' => $lines['reportages'] + $lines['backlinks'],
			'direct'  => $direct,
			'fees'    => $fees,
			'total'   => $direct + $fees,
			'monthly' => ( $direct + $fees ) / $months,
			'plan'    => ost_budget_schedule( $qty, $months ),
			'effort'  => $pk['q'],
		);
	}
	return $out;
}

/**
 * Spread quantities over the months: month 1 = foundation (technical, strategy,
 * keyword map), content / links from month 2 with a short ramp.
 *
 * @return array month (1..N) => item => n
 */
function ost_budget_schedule( $qty, $months ) {
	$plan = array();
	for ( $m = 1; $m <= $months; $m++ ) {
		$plan[ $m ] = array_fill_keys( array_keys( $qty ), 0 );
	}
	$weights = array();
	for ( $m = 2; $m <= $months; $m++ ) {
		$weights[ $m ] = 2 === $m ? 0.6 : 1.0;
	}
	$wsum = array_sum( $weights );
	foreach ( $qty as $item => $n ) {
		$w = $weights;
		if ( 'landings' === $item ) {
			// Money pages first: most of them in months 2–4.
			foreach ( $w as $m => $v ) {
				$w[ $m ] = $m <= 4 ? 3 : 0.5;
			}
		}
		$ws   = array_sum( $w );
		$left = $n;
		$acc  = 0.0;
		foreach ( $w as $m => $v ) {
			$acc     += $n * $v / $ws;
			$give     = (int) round( $acc ) - ( $n - $left );
			$give     = max( 0, min( $left, $give ) );
			$plan[ $m ][ $item ] = $give;
			$left    -= $give;
		}
		if ( $left > 0 ) {
			$plan[ $months ][ $item ] += $left;
		}
	}
	unset( $wsum );
	return $plan;
}

/**
 * Labels of budget items.
 */
function ost_budget_items() {
	return array(
		'landings'   => array( 'صفحات فرود و دسته‌بندی', 'صفحه' ),
		'articles'   => array( 'مقاله تخصصی جدید', 'مقاله' ),
		'updates'    => array( 'به‌روزرسانی صفحات نزدیک صفحه اول', 'صفحه' ),
		'reportages' => array( 'رپورتاژ باکیفیت در رسانه‌های ایرانی', 'رپورتاژ' ),
		'backlinks'  => array( 'بک‌لینک تخصصی مکمل', 'لینک' ),
	);
}
