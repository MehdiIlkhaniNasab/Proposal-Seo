<?php
/**
 * Technical health of a domain: quick audit (always) + Screaming Frog summary
 * (when crawled). Each check has a plain-language label, a severity and a count;
 * the score is 100 minus weighted penalties.
 *
 * @package Ostorlab
 */

defined( 'ABSPATH' ) || exit;

/**
 * Evaluate a domain.
 *
 * @param array $audit ost_site_audit() result.
 * @param array $sf    Screaming Frog summary of the domain {internal, inlinks, redirects}.
 * @return array{score: int, grade: string, issues: array, good: array, metrics: array}
 */
function ost_technical( $audit, $sf = array() ) {
	$issues = array();
	$good   = array();
	$add    = function ( $key, $label, $sev, $count, $total = 0, $hint = '', $src = 'audit' ) use ( &$issues ) {
		$issues[] = array( 'key' => $key, 'label' => $label, 'sev' => $sev, 'count' => (int) $count, 'total' => (int) $total, 'hint' => $hint, 'src' => $src );
	};
	if ( ! $audit && ! $sf ) {
		return array( 'score' => null, 'grade' => '—', 'issues' => array(), 'good' => array(), 'metrics' => array() );
	}
	$pages = array_filter( (array) ( $audit['pages'] ?? array() ), function ( $p ) {
		return 200 === (int) ( $p['code'] ?? 0 ) && isset( $p['title'] );
	} );
	$n     = max( 1, count( $pages ) );
	$sum   = function ( $fn ) use ( $pages ) {
		return count( array_filter( $pages, $fn ) );
	};
	if ( $audit ) {
		if ( empty( $audit['https'] ) ) {
			$add( 'https', 'سایت روی HTTPS نیست', 'high', 1, 1, 'گوگل و کاربران به سایت بدون قفل اعتماد نمی‌کنند.' );
		} else {
			$good[] = 'HTTPS فعال است';
		}
		if ( ! empty( $audit['robots']['blocks_all'] ) ) {
			$add( 'robots_block', 'فایل robots.txt کل سایت را برای گوگل بسته است', 'high', 1, 1, 'تا رفع نشود، هیچ صفحه‌ای رشد نمی‌کند.' );
		} elseif ( empty( $audit['robots']['exists'] ) ) {
			$add( 'robots', 'فایل robots.txt وجود ندارد', 'low', 1, 1, 'راهنمای خزش ربات گوگل نیست.' );
		} else {
			$good[] = 'robots.txt درست تنظیم شده';
		}
		if ( empty( $audit['sitemap']['found'] ) ) {
			$add( 'sitemap', 'نقشه سایت (Sitemap) پیدا نشد', 'med', 1, 1, 'گوگل صفحات جدید را دیرتر پیدا می‌کند.' );
		} else {
			$good[] = sprintf( 'نقشه سایت با %s آدرس', ost_num( $audit['sitemap']['total'] ) );
			if ( empty( $audit['robots']['sitemaps'] ) ) {
				$add( 'sitemap_robots', 'آدرس نقشه سایت در robots.txt معرفی نشده', 'low', 1, 1, '' );
			}
		}
		if ( (int) ( $audit['home_ms'] ?? 0 ) > 1800 ) {
			$add( 'speed', sprintf( 'صفحه اصلی دیر باز می‌شود (%s ثانیه)', ost_num( $audit['home_ms'] / 1000, 1 ) ), 'med', 1, 1, 'هر ثانیه تأخیر، بخشی از مشتریان را فراری می‌دهد.' );
		}
		$bad_code = count( array_filter( (array) ( $audit['pages'] ?? array() ), function ( $p ) {
			return (int) ( $p['code'] ?? 0 ) >= 400; // Timeouts of the parallel read are not page errors.
		} ) );
		$deep = ! empty( $sf['internal'] ); // A crawl covers the page-level checks; the sample is not counted twice.
		if ( $bad_code && ! $deep ) {
			$add( 'sample_errors', 'صفحات خطا در نمونه بررسی‌شده', 'high', $bad_code, count( (array) $audit['pages'] ), 'صفحه‌ای که باز نمی‌شود، مشتری و اعتبار را از دست می‌دهد.' );
		}
		$checks = array(
			array( 'title_missing', 'صفحات بدون عنوان (Title)', 'high', function ( $p ) {
				return '' === $p['title'];
			} ),
			array( 'title_len', 'عنوان خیلی بلند یا خیلی کوتاه', 'low', function ( $p ) {
				return '' !== $p['title'] && ( $p['title_len'] > 65 || $p['title_len'] < 20 );
			} ),
			array( 'meta_missing', 'صفحات بدون توضیحات متا', 'med', function ( $p ) {
				return '' === $p['meta'];
			} ),
			array( 'h1_missing', 'صفحات بدون تیتر اصلی (H1)', 'med', function ( $p ) {
				return 0 === (int) $p['h1'];
			} ),
			array( 'h1_multi', 'صفحات با چند تیتر اصلی (H1)', 'low', function ( $p ) {
				return (int) $p['h1'] > 1;
			} ),
			array( 'canonical', 'صفحات بدون تگ کنونیکال', 'low', function ( $p ) {
				return '' === $p['canonical'];
			} ),
			array( 'schema', 'صفحات بدون اسکیما (داده ساخت‌یافته)', 'med', function ( $p ) {
				return empty( $p['schema'] );
			} ),
			array( 'thin', 'صفحات کم‌محتوا (کمتر از ۲۵۰ کلمه)', 'med', function ( $p ) {
				return (int) $p['words'] < 250;
			} ),
			array( 'alt', 'صفحاتی که بیشتر تصاویرشان متن جایگزین (alt) ندارند', 'low', function ( $p ) {
				return $p['images'] > 2 && $p['no_alt'] / max( 1, $p['images'] ) > 0.5;
			} ),
			array( 'viewport', 'صفحات بدون تنظیم موبایل (viewport)', 'high', function ( $p ) {
				return empty( $p['viewport'] );
			} ),
			array( 'noindex', 'صفحات مهم با noindex (از گوگل حذف شده)', 'high', function ( $p ) {
				return ! empty( $p['noindex'] );
			} ),
		);
		foreach ( $deep ? array() : $checks as $c ) {
			$cnt = $sum( $c[3] );
			if ( $cnt ) {
				$add( $c[0], $c[1], $c[2], $cnt, $n, '', 'audit' );
			}
		}
		$types = array();
		foreach ( $pages as $p ) {
			$types = array_merge( $types, (array) $p['schema'] );
		}
		if ( $types ) {
			$good[] = 'اسکیما: ' . implode( '، ', array_slice( array_unique( $types ), 0, 5 ) );
		}
	}
	$in  = (array) ( $sf['internal'] ?? array() );
	$lk  = (array) ( $sf['inlinks'] ?? array() );
	$rd  = (array) ( $sf['redirects'] ?? array() );
	if ( $in ) {
		$t = max( 1, (int) $in['indexable'] );
		$sfc = array(
			array( 'sf_4xx', 'آدرس‌های خطای ۴۰۴ / ۴xx', 'high', $in['codes']['4xx'] ?? 0, $in['urls'] ),
			array( 'sf_5xx', 'خطای سرور (۵xx)', 'high', $in['codes']['5xx'] ?? 0, $in['urls'] ),
			array( 'sf_3xx', 'آدرس‌های ریدایرکت‌شده در مسیر خزش', 'low', $in['codes']['3xx'] ?? 0, $in['urls'] ),
			array( 'sf_title_dup', 'عنوان تکراری', 'med', $in['title_dup'], $t ),
			array( 'sf_title_missing', 'بدون عنوان', 'high', $in['title_missing'], $t ),
			array( 'sf_meta_missing', 'بدون توضیحات متا', 'med', $in['meta_missing'], $t ),
			array( 'sf_meta_dup', 'توضیحات متای تکراری', 'low', $in['meta_dup'], $t ),
			array( 'sf_h1_missing', 'بدون H1', 'med', $in['h1_missing'], $t ),
			array( 'sf_h1_dup', 'H1 تکراری', 'low', $in['h1_dup'], $t ),
			array( 'sf_thin', 'کم‌محتوا (کمتر از ۲۵۰ کلمه)', 'med', $in['thin'], $t ),
			array( 'sf_deep', 'صفحات خیلی عمیق (بیش از ۳ کلیک از صفحه اصلی)', 'med', $in['deep'], $t ),
			array( 'sf_weak', 'صفحات تقریباً بی‌لینک (یتیم)', 'med', $in['weak_inlinks'], $t ),
			array( 'sf_slow', 'صفحات کند (بیش از ۱٫۲ ثانیه)', 'med', $in['slow'], $t ),
			array( 'sf_dupes', 'صفحات تقریباً تکراری', 'med', $in['near_dupes'], $t ),
			array( 'sf_noindex', 'صفحات non-indexable', 'low', $in['non_indexable'], $in['html'] ),
			array( 'sf_params', 'آدرس‌های پارامتردار (?…) در خزش', 'low', $in['params'], $t ),
		);
		foreach ( $sfc as $c ) {
			if ( (int) $c[3] > 0 ) {
				$add( $c[0], $c[1], $c[2], $c[3], $c[4], '', 'sf' );
			}
		}
	}
	if ( $lk ) {
		if ( $lk['broken'] ) {
			$add( 'sf_broken', 'لینک داخلی شکسته', 'high', $lk['broken'], $lk['body'] + $lk['nav'], 'کاربر و ربات گوگل به بن‌بست می‌رسند.', 'sf' );
		}
		if ( $lk['to_redirect'] ) {
			$add( 'sf_link_redirect', 'لینک داخلی به آدرس ریدایرکت‌شده', 'low', $lk['to_redirect'], $lk['body'] + $lk['nav'], '', 'sf' );
		}
		if ( $lk['generic_anchor'] + $lk['empty_anchor'] ) {
			$add( 'sf_anchor', 'لینک داخلی با انکر بی‌معنی یا خالی', 'low', $lk['generic_anchor'] + $lk['empty_anchor'], $lk['body'], '', 'sf' );
		}
	}
	if ( $rd ) {
		if ( $rd['chains'] ) {
			$add( 'sf_chains', 'زنجیره ریدایرکت', 'med', $rd['chains'], $rd['total'], '', 'sf' );
		}
		if ( $rd['loops'] ) {
			$add( 'sf_loops', 'حلقه ریدایرکت', 'high', $rd['loops'], $rd['total'], '', 'sf' );
		}
		if ( $rd['temporary'] ) {
			$add( 'sf_temp', 'ریدایرکت موقت (۳۰۲) به جای دائمی', 'low', $rd['temporary'], $rd['total'], '', 'sf' );
		}
	}
	// Score: a weighted penalty per issue, scaled by how much of the site it affects.
	$weights = array( 'high' => 14, 'med' => 7, 'low' => 3 );
	$penalty = 0;
	foreach ( $issues as $i ) {
		$ratio    = $i['total'] ? min( 1, $i['count'] / $i['total'] ) : 1;
		$penalty += $weights[ $i['sev'] ] * ( 0.35 + 0.65 * $ratio );
	}
	$score = (int) round( ost_clamp( 100 - $penalty, 5, 100 ) );
	usort( $issues, function ( $a, $b ) {
		$o = array( 'high' => 0, 'med' => 1, 'low' => 2 );
		return array( $o[ $a['sev'] ], -$a['count'] ) <=> array( $o[ $b['sev'] ], -$b['count'] );
	} );
	return array(
		'score'   => $score,
		'grade'   => $score >= 85 ? 'عالی' : ( $score >= 70 ? 'خوب' : ( $score >= 50 ? 'متوسط' : 'ضعیف' ) ),
		'issues'  => $issues,
		'good'    => $good,
		'has_sf'  => (bool) $in,
		'metrics' => array(
			'pages'    => (int) ( $audit['sitemap']['total'] ?? 0 ),
			'crawled'  => (int) ( $in['urls'] ?? 0 ),
			'home_ms'  => (int) ( $audit['home_ms'] ?? 0 ),
			'avg_ms'   => (int) ( $in['avg_ms'] ?? 0 ),
			'schema'   => $audit ? $n - $sum( function ( $p ) {
				return empty( $p['schema'] );
			} ) : 0,
			'sample'   => $audit ? $n : 0,
			'e4xx'     => (int) ( $in['codes']['4xx'] ?? 0 ),
			'broken'   => (int) ( $lk['broken'] ?? 0 ),
			'chains'   => (int) ( $rd['chains'] ?? 0 ),
			'avg_depth' => (float) ( $in['avg_depth'] ?? 0 ),
			'avg_inlinks' => (float) ( $in['avg_inlinks'] ?? 0 ),
			'cms'      => (string) ( $audit['cms'] ?? '' ),
			'https'    => ! empty( $audit['https'] ),
			'sitemap'  => ! empty( $audit['sitemap']['found'] ),
		),
	);
}

/**
 * Severity label.
 */
function ost_sev_label( $sev ) {
	return array( 'high' => 'مهم', 'med' => 'متوسط', 'low' => 'کم' )[ $sev ] ?? $sev;
}
