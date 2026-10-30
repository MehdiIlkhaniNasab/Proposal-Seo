<?php
/**
 * Search market: clusters from the client's seed keywords, demand per cluster and
 * how much of it is buyer demand (people ready to buy / book / call).
 *
 * Each seed becomes a cluster; its related keywords from SEO Signal are assigned
 * to the cluster they fit best (no keyword is counted twice), then split by
 * intent with Persian modifiers.
 *
 * @package Ostorlab
 */

defined( 'ABSPATH' ) || exit;

/**
 * Intent groups with their Persian / English modifiers (matched on whole words).
 */
function ost_intents() {
	return array(
		'buy'     => array( 'label' => 'خرید و سفارش', 'buyer' => true, 'words' => array( 'خرید', 'قیمت', 'فروش', 'سفارش', 'ارزان', 'عمده', 'تخفیف', 'فروشگاه', 'نمایندگی', 'هزینه', 'تعرفه', 'اجاره', 'رزرو', 'ثبت نام', 'ثبتنام', 'استخدام', 'مشاوره', 'وقت', 'نوبت', 'پرداخت', 'اقساط', 'قسطی', 'آنلاین', 'اینترنتی', 'ارسال', 'تعمیر', 'نصب', 'خدمات', 'شرکت', 'تولید', 'تولیدی', 'پخش', 'بلیط', 'بلیت', 'رزو', 'کرایه', 'خریداری', 'price', 'buy', 'shop' ) ),
		'compare' => array( 'label' => 'مقایسه و انتخاب', 'buyer' => true, 'words' => array( 'بهترین', 'مقایسه', 'مدل', 'مدلهای', 'مدل‌های', 'انواع', 'برند', 'لیست', 'بررسی', 'نقد', 'مشخصات', 'تفاوت', 'یا', 'معرفی', 'راهنمای خرید', 'کدام', 'چه', 'ارزانترین', 'ارزان‌ترین', 'جدید', 'جدیدترین', 'اصل', 'اورجینال', 'ایرانی', 'خارجی', 'vs', 'best', 'review' ) ),
		'local'   => array( 'label' => 'محلی و نزدیک من', 'buyer' => true, 'words' => array( 'تهران', 'مشهد', 'اصفهان', 'شیراز', 'تبریز', 'کرج', 'قم', 'اهواز', 'رشت', 'کرمان', 'یزد', 'ساری', 'ارومیه', 'همدان', 'قزوین', 'زنجان', 'اراک', 'بندرعباس', 'کیش', 'نزدیک', 'آدرس', 'شعبه', 'غرب', 'شرق', 'شمال', 'جنوب', 'منطقه', 'محله' ) ),
		'info'    => array( 'label' => 'آموزشی و اطلاعاتی', 'buyer' => false, 'words' => array( 'چیست', 'چیه', 'چگونه', 'چطور', 'چطوری', 'آموزش', 'روش', 'علت', 'دلیل', 'فواید', 'معایب', 'مزایا', 'عوارض', 'کاربرد', 'تاریخچه', 'دانلود', 'pdf', 'یعنی', 'معنی', 'نحوه', 'طرز', 'ترفند', 'نکات', 'راهنما', 'چند', 'کی', 'چرا', 'what', 'how' ) ),
	);
}

/**
 * Intent of a keyword (first match wins in the order buy → local → compare → info).
 */
function ost_intent( $word ) {
	$w     = ' ' . ost_norm( $word ) . ' ';
	$order = array( 'buy', 'local', 'compare', 'info' );
	$all   = ost_intents();
	foreach ( $order as $key ) {
		foreach ( $all[ $key ]['words'] as $mod ) {
			if ( false !== mb_strpos( $w, ' ' . ost_norm( $mod ) . ' ' ) ) {
				return $key;
			}
		}
	}
	return 'general';
}

/**
 * Word overlap between a keyword and a seed (to assign it to the best cluster).
 */
function ost_kw_overlap( $word, $seed ) {
	$a = array_filter( explode( ' ', ost_norm( $word ) ) );
	$b = array_filter( explode( ' ', ost_norm( $seed ) ) );
	return $b ? count( array_intersect( $a, $b ) ) / count( $b ) : 0;
}

/**
 * Build the market from keyword research results.
 *
 * @param array $raw  seed => ost_ss_keyword() result.
 * @return array{clusters: array, total: int, buyer: int, keywords: int, money: array, questions: array}
 */
function ost_build_market( $raw ) {
	$best = array(); // word key => [seed, overlap, row].
	foreach ( $raw as $seed => $r ) {
		if ( empty( $r['list'] ) && empty( $r['volume'] ) ) {
			continue;
		}
		$rows = $r['list'];
		if ( ! empty( $r['volume'] ) ) {
			array_unshift( $rows, array( 'w' => $seed, 'v' => (int) $r['volume'], 'c' => '' ) );
		}
		foreach ( $rows as $row ) {
			$k  = ost_norm( $row['w'] );
			$ov = ost_kw_overlap( $row['w'], $seed ) + ( $k === ost_norm( $seed ) ? 2 : 0 );
			if ( ! isset( $best[ $k ] ) || $ov > $best[ $k ][1] ) {
				$best[ $k ] = array( $seed, $ov, $row );
			}
		}
	}
	$clusters = array();
	foreach ( $raw as $seed => $r ) {
		$clusters[ $seed ] = array(
			'seed'     => $seed,
			'volume'   => (int) ( $r['volume'] ?? 0 ),
			'change'   => (float) ( $r['change'] ?? 0 ),
			'chart'    => $r['chart'] ?? array(),
			'leaders'  => array_slice( (array) ( $r['ctr'] ?? array() ), 0, 5 ),
			'total'    => 0,
			'buyer'    => 0,
			'count'    => 0,
			'intents'  => array(),
			'top'      => array(),
			'hard'     => 0,
			'error'    => $r['error'] ?? '',
		);
	}
	$money     = array();
	$questions = array();
	$all_rows  = array();
	foreach ( $best as $k => $b ) {
		list( $seed, , $row ) = $b;
		$intent = ost_intent( $row['w'] );
		$c      = &$clusters[ $seed ];
		$c['total']                   += $row['v'];
		$c['count']++;
		$c['intents'][ $intent ]       = ( $c['intents'][ $intent ] ?? 0 ) + $row['v'];
		$is_buyer                      = 'general' === $intent ? false : ( ost_intents()[ $intent ]['buyer'] ?? false );
		if ( $is_buyer ) {
			$c['buyer'] += $row['v'];
			$money[]     = array( 'w' => $row['w'], 'v' => $row['v'], 'i' => $intent, 'seed' => $seed, 'c' => $row['c'] );
		}
		if ( 'info' === $intent ) {
			$questions[] = array( 'w' => $row['w'], 'v' => $row['v'], 'seed' => $seed );
		}
		if ( false !== mb_strpos( (string) $row['c'], 'سخت' ) ) {
			$c['hard'] += $row['v'];
		}
		$c['top'][] = array( 'w' => $row['w'], 'v' => $row['v'], 'i' => $intent, 'c' => $row['c'] );
		$all_rows[] = array( 'w' => $row['w'], 'v' => $row['v'], 'i' => $intent, 'c' => $row['c'], 'seed' => $seed );
		unset( $c );
	}
	// The general (unmodified) head terms are mixed demand; half of them is counted as buyer demand.
	foreach ( $clusters as &$c ) {
		$c['buyer'] += (int) round( 0.5 * ( $c['intents']['general'] ?? 0 ) );
		usort( $c['top'], function ( $a, $b ) {
			return $b['v'] <=> $a['v'];
		} );
		$c['top']        = array_slice( $c['top'], 0, 12 );
		$c['buyer_share'] = $c['total'] ? $c['buyer'] / $c['total'] : 0;
		$c['difficulty'] = $c['total'] ? $c['hard'] / $c['total'] : 0;
	}
	unset( $c );
	uasort( $clusters, function ( $a, $b ) {
		return $b['total'] <=> $a['total'];
	} );
	usort( $money, function ( $a, $b ) {
		return $b['v'] <=> $a['v'];
	} );
	usort( $questions, function ( $a, $b ) {
		return $b['v'] <=> $a['v'];
	} );
	$total = array_sum( array_column( $clusters, 'total' ) );
	$buyer = array_sum( array_column( $clusters, 'buyer' ) );
	$intents = array();
	foreach ( $clusters as $c ) {
		foreach ( $c['intents'] as $i => $v ) {
			$intents[ $i ] = ( $intents[ $i ] ?? 0 ) + $v;
		}
	}
	return array(
		'clusters'  => $clusters,
		'total'     => $total,
		'buyer'     => $buyer,
		'keywords'  => count( $best ),
		'intents'   => $intents,
		'money'     => array_slice( $money, 0, 40 ),
		'questions' => array_slice( $questions, 0, 40 ),
		'all_words' => array_keys( $best ),
		'rows'      => $all_rows,
	);
}

/**
 * Persian label of an intent key.
 */
function ost_intent_label( $key ) {
	return 'general' === $key ? 'عمومی' : ( ost_intents()[ $key ]['label'] ?? $key );
}
