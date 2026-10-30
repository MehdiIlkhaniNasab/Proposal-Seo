<?php
/**
 * Roadmap: five phases spread over the period (8–12+ months), each with its goal,
 * actions taken from the analysis, the deliverables of the chosen package in
 * those months, and a realistic expected result from the forecast.
 *
 * @package Ostorlab
 */

defined( 'ABSPATH' ) || exit;

/**
 * Month ranges of the five phases for a period.
 *
 * @return array[] [from, to]
 */
function ost_phase_ranges( $months ) {
	$cuts = array( 1, max( 2, (int) round( $months * 0.25 ) ), max( 3, (int) round( $months * 0.5 ) ), max( 4, (int) round( $months * 0.75 ) ), $months );
	$out  = array( array( 1, 1 ) );
	for ( $i = 1; $i < 5; $i++ ) {
		$from = $out[ $i - 1 ][1] + 1;
		$to   = max( $from, $cuts[ $i ] );
		if ( 4 === $i ) {
			$to = $months;
		}
		$out[] = array( $from, min( $to, $months ) );
	}
	return $out;
}

/**
 * Build the roadmap.
 *
 * @param array $a        Analysis.
 * @param array $pkg      Selected package (ost_budget()['packages'][key]).
 * @param array $forecast ost_forecast() for that package.
 */
function ost_roadmap( $a, $pkg, $forecast, $months, $in ) {
	$tech    = $a['technical'][ $a['client_domain'] ?? '' ] ?? array();
	$top_iss = array_slice( array_column( array_filter( (array) ( $tech['issues'] ?? array() ), function ( $i ) {
		return 'low' !== $i['sev'];
	} ), 'label' ), 0, 3 );
	$local   = 'local' === ( $in['business_type'] ?? '' ) || ( $a['market']['intents']['local'] ?? 0 ) > 0.08 * max( 1, $a['market']['total'] ?? 0 );
	$shop    = 'shop' === ( $in['business_type'] ?? '' );
	$has     = ! empty( $a['client_domain'] );
	$clusters = array_keys( (array) ( $a['market']['clusters'] ?? array() ) );
	$opps     = array_column( (array) ( $a['strategy']['picks'] ?? array() ), 'label' );
	$defs = array(
		array(
			'title'   => 'پایه‌گذاری و رفع موانع',
			'goal'    => 'سایت آماده رشد شود و همه‌چیز قابل اندازه‌گیری باشد.',
			'actions' => array_values( array_filter( array(
				$has ? 'رفع مشکلات فنی مهم' . ( $top_iss ? ': ' . implode( '، ', $top_iss ) : '' ) : 'معماری سایت جدید و ساختار آدرس‌ها بر اساس خوشه‌های کلمات',
				'نقشه کلمات کلیدی: هر کلمه خرید به یک صفحه مشخص',
				'اتصال سرچ کنسول و تعریف هدف فروش/تماس در گوگل آنالیتیکس',
				$has ? 'هرس محتوا: ادغام یا حذف صفحات ضعیف و تکراری' : '',
			) ) ),
		),
		array(
			'title'   => 'صفحات پول‌ساز',
			'goal'    => 'صفحاتی که مستقیم فروش می‌آورند در رقابت قرار بگیرند.',
			'actions' => array_values( array_filter( array(
				$opps ? 'استراتژی شروع: صفحات برنده برای «' . implode( '»، «', array_slice( $opps, 0, 2 ) ) . '»' : '',
				$shop ? 'بهینه‌سازی دسته‌بندی‌ها و صفحات محصول پرفروش' : 'ساخت و بهینه‌سازی صفحات خدمات اصلی',
				'لینک‌سازی داخلی از صفحات پربازدید به صفحات فروش',
				'اسکیما (محصول، سؤالات متداول، مسیر صفحه) برای نمایش بهتر در گوگل',
				'شروع رپورتاژ در رسانه‌های معتبر با انکرهای هدفمند',
				$local ? 'ساخت و بهینه‌سازی پروفایل گوگل مپ و صفحات شهر/شعبه' : '',
			) ) ),
		),
		array(
			'title'   => 'رشد محتوا و اعتبار',
			'goal'    => 'پوشش کامل سؤال‌های مشتری و افزایش اعتبار دامنه.',
			'actions' => array_values( array_filter( array(
				count( $opps ) > 2 ? 'گسترش استراتژی شروع به «' . implode( '»، «', array_slice( $opps, 2, 3 ) ) . '»' : '',
				'انتشار منظم مقاله تخصصی برای خوشه‌های ' . implode( '، ', array_slice( $clusters, 0, 3 ) ),
				'به‌روزرسانی صفحاتی که نزدیک صفحه اول هستند',
				'ادامه رپورتاژ و لینک‌سازی به صفحات فروش',
			) ) ),
		),
		array(
			'title'   => 'تثبیت و گسترش',
			'goal'    => 'تثبیت رتبه‌های به‌دست‌آمده و ورود به کلمات رقابتی‌تر.',
			'actions' => array(
				'گسترش به خوشه‌ها و کلمات رقابتی‌تر',
				'تحلیل کلماتی که رقبا دارند و ما نداریم و پوشش آن‌ها',
				'بهبود نرخ کلیک (عنوان و توضیحات) صفحات پرنمایش',
			),
		),
		array(
			'title'   => 'بهره‌برداری و مقیاس',
			'goal'    => 'تبدیل ترافیک به فروش بیشتر و برنامه سال بعد.',
			'actions' => array(
				'بهینه‌سازی نرخ تبدیل صفحات پرترافیک',
				'دفاع از رتبه‌ها در برابر رقبا و به‌روزرسانی محتوای قدیمی',
				'گزارش نهایی دوره و نقشه راه سال دوم',
			),
		),
	);
	$items  = ost_budget_items();
	$phases = array();
	foreach ( ost_phase_ranges( $months ) as $i => $range ) {
		list( $from, $to ) = $range;
		if ( $from > $months ) {
			continue;
		}
		$deliver = array();
		for ( $m = $from; $m <= $to; $m++ ) {
			foreach ( (array) ( $pkg['plan'][ $m ] ?? array() ) as $item => $n ) {
				$deliver[ $item ] = ( $deliver[ $item ] ?? 0 ) + $n;
			}
		}
		$deliver = array_filter( $deliver );
		$labels  = array();
		foreach ( $deliver as $item => $n ) {
			$labels[] = ost_num( $n ) . ' ' . $items[ $item ][1];
		}
		$low  = (int) ( $forecast['curve']['low'][ $to ] ?? 0 );
		$high = (int) ( $forecast['curve']['high'][ $to ] ?? 0 );
		$phases[] = array_merge( $defs[ $i ], array(
			'from'     => $from,
			'to'       => $to,
			'label'    => $from === $to ? 'ماه ' . ost_num( $from ) : 'ماه ' . ost_num( $from ) . ' تا ' . ost_num( $to ),
			'when'     => ost_jmonth_from_now( $from - 1 ) . ( $from === $to ? '' : ' — ' . ost_jmonth_from_now( $to - 1 ) ),
			'deliver'  => $deliver,
			'deliver_text' => $labels,
			'result'   => 0 === $i
				? 'گزارش وضعیت پایه و رفع موانع؛ هنوز رشد ترافیک انتظار نمی‌رود.'
				: sprintf( 'حدود %s تا %s کلیک غیربرندی در ماه', ost_num( $low ), ost_num( $high ) ),
		) );
	}
	return $phases;
}
