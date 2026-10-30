<?php
/**
 * Proposal slide deck (16:9, 1280×720 px = 13.33×7.5 in, PowerPoint size).
 *
 * @var array $p      Proposal.
 * @var array $a      Analysis.
 * @var array $t      Texts.
 * @var array $slides Enabled slides (key => label).
 * @var array $args   inline / print / web.
 *
 * @package Fanoos
 */

defined( 'ABSPATH' ) || exit;

$in      = $p['inputs'];
$assets  = fns_deck_assets( $args['inline'] );
$cd      = $a['client_domain'];
$client  = $in['client_name'] ?: $cd;
$m       = $a['market'];
$months  = (int) $in['months'];
$bud     = $a['budget'];
$sel     = $bud['selected'];
$pkg     = $bud['packages'][ $sel ];
$f       = $a['forecast'];
$comps   = array_filter( $a['profiles'], function ( $x ) {
	return 'competitor' === $x['role'];
} );
$items   = fns_budget_items();
$agency  = fns_setting( 'agency_name' );
$page    = 0;
$colors  = array( 'primary' => fns_setting( 'theme_primary', '#0B4F9C' ), 'accent' => fns_setting( 'theme_accent', '#0D94E4' ), 'dark' => fns_setting( 'theme_dark', '#0A2540' ) );
$section = array( 'market' => '۰۱', 'audit' => '۰۲', 'plan' => '۰۳' );
$on      = function ( $k ) use ( $slides ) {
	return isset( $slides[ $k ] );
};
$logo    = $assets['logo'];
$open    = function ( $kicker, $title, $lead = '', $class = '' ) use ( &$page, $logo, $agency, $client ) {
	$page++;
	echo '<section class="slide s-content ' . esc_attr( $class ) . '"><div class="beam"></div><header class="sh"><span class="kicker">' . esc_html( $kicker ) . '</span><h2>' . esc_html( $title ) . '</h2>' . ( $lead ? '<p class="lead">' . esc_html( $lead ) . '</p>' : '' ) . '</header><div class="sb">';
	return function () use ( $page, $logo, $agency, $client ) {
		echo '</div><footer class="sf"><span class="sf-brand"><img src="' . esc_attr( $logo ) . '" alt=""><b>' . esc_html( $agency ) . '</b></span><span class="sf-client">پروپوزال سئو ' . esc_html( $client ) . '</span><span class="pg">' . esc_html( fns_fa( $page ) ) . '</span></footer></section>';
	};
};
$divider = function ( $num, $title, $sub ) use ( &$page ) {
	$page++;
	echo '<section class="slide s-divider"><div class="beam big"></div><div class="dv"><span class="dv-num">' . esc_html( $num ) . '</span><h2>' . esc_html( $title ) . '</h2><p>' . esc_html( $sub ) . '</p></div><span class="pg light">' . esc_html( fns_fa( $page ) ) . '</span></section>';
};
$tile = function ( $value, $label, $note = '', $class = '' ) {
	echo '<div class="tile ' . esc_attr( $class ) . '"><b>' . esc_html( $value ) . '</b><span>' . esc_html( $label ) . '</span>' . ( $note ? '<small>' . esc_html( $note ) . '</small>' : '' ) . '</div>';
};
$para = function ( $text ) {
	foreach ( preg_split( '/\n\s*\n/', trim( (string) $text ) ) as $pp ) {
		if ( '' !== trim( $pp ) ) {
			echo '<p>' . nl2br( esc_html( trim( $pp ) ) ) . '</p>';
		}
	}
};
$ul = function ( $text, $class = '' ) {
	echo '<ul class="' . esc_attr( $class ) . '">';
	foreach ( fns_lines( $text ) as $li ) {
		echo '<li>' . esc_html( $li ) . '</li>';
	}
	echo '</ul>';
};
$range = function ( $rev ) {
	if ( $rev['high'] > 0 ) {
		return fns_compact( $rev['low'] ) . ' تا ' . fns_compact( $rev['high'] );
	}
	return '—';
};
$css = (string) file_get_contents( $assets['css'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( 'پروپوزال سئو ' . $client . ' — ' . $agency ); ?></title>
<style>
@font-face { font-family: "Vazirmatn"; src: url("<?php echo esc_attr( $assets['font'] ); ?>") format("woff2"); font-weight: 100 900; font-display: block; }
:root { --primary: <?php echo esc_html( $colors['primary'] ); ?>; --accent: <?php echo esc_html( $colors['accent'] ); ?>; --dark: <?php echo esc_html( $colors['dark'] ); ?>; }
<?php echo $css; // phpcs:ignore WordPress.Security.EscapeOutput ?>
</style>
</head>
<body class="<?php echo $args['web'] ? 'web' : 'print'; ?>">
<?php if ( $args['web'] ) : ?>
<div class="toolbar"><span><?php echo esc_html( 'پروپوزال سئو ' . $client ); ?></span><button onclick="window.print()">دانلود PDF</button></div>
<?php endif; ?>
<main class="deck">

<?php if ( $on( 'cover' ) ) : $page++; ?>
<section class="slide s-cover">
	<div class="cover-art"><div class="ring r1"></div><div class="ring r2"></div><div class="ring r3"></div><div class="beam cover-beam"></div></div>
	<div class="cover-logo"><img src="<?php echo esc_attr( $assets['logo'] ); ?>" alt="<?php echo esc_attr( $agency ); ?>"></div>
	<div class="cover-body">
		<span class="cover-kicker">پروپوزال سئو و رشد ارگانیک</span>
		<h1><?php echo esc_html( $client ); ?></h1>
		<p class="cover-head"><?php echo esc_html( $t['headline'] ?? '' ); ?></p>
		<div class="cover-meta">
			<?php if ( $cd ) : ?><span><?php echo esc_html( $cd ); ?></span><?php endif; ?>
			<span><?php echo esc_html( fns_jdate() ); ?></span>
			<span>دوره <?php echo esc_html( fns_num( $months ) ); ?> ماهه</span>
		</div>
	</div>
	<div class="cover-foot">
		<span>تهیه‌شده توسط <b><?php echo esc_html( fns_setting( 'consultant_name' ) ); ?></b> — <?php echo esc_html( fns_setting( 'consultant_title' ) ); ?>، <?php echo esc_html( $agency ); ?></span>
		<span><?php echo esc_html( fns_setting( 'agency_site' ) ); ?></span>
	</div>
</section>
<?php endif; ?>

<?php if ( $on( 'summary' ) ) : $close = $open( 'خلاصه اجرایی', 'در یک نگاه', '' ); ?>
	<div class="grid g-sum">
		<div class="prose big"><?php $para( $t['executive_summary'] ?? '' ); ?></div>
		<div class="tiles t2">
			<?php
			$tile( fns_compact( $m['total'] ), 'جستجوی ماهانه بازار شما', fns_num( count( $m['clusters'] ) ) . ' خوشه، ' . fns_num( $m['keywords'] ) . ' کلمه' );
			$tile( fns_compact( $m['buyer'] ), 'جستجوی آماده خرید', fns_pct( $m['buyer'] / max( 1, $m['total'] ) ) . ' از کل تقاضا', 'hl' );
			$tile( fns_num( $f['scenarios']['low'] ) . ' تا ' . fns_num( $f['scenarios']['high'] ), 'کلیک غیربرندی ماهانه در پایان دوره', $f['baseline'] ? 'امروز حدود ' . fns_num( $f['baseline'] ) : 'شروع از صفر' );
			$tile( fns_num( $months ) . ' ماه', 'دوره پیشنهادی', 'پکیج ' . $pkg['label'] . '، ماهانه حدود ' . fns_toman( $pkg['monthly'], true ) );
			?>
		</div>
	</div>
<?php $close(); endif; ?>

<?php if ( $on( 'div_market' ) ) { $divider( $section['market'], 'بازار و رقبا', 'مشتریان شما در گوگل چه می‌جویند و امروز چه کسی آن‌ها را می‌گیرد' ); } ?>

<?php if ( $on( 'market' ) ) : $close = $open( $section['market'] . ' · بازار جستجو', 'تقاضای آنلاین حوزه شما', $t['market'] ?? '' ); ?>
	<div class="grid g-2">
		<div class="card">
			<h3>جستجوی ماهانه هر خوشه</h3>
			<?php
			$rows = array();
			foreach ( array_slice( $m['clusters'], 0, 7 ) as $c ) {
				$rows[] = array( 'label' => $c['seed'], 'value' => $c['total'], 'hl' => true );
			}
			echo fns_chart_hbar( $rows, 560 ); // phpcs:ignore
			?>
		</div>
		<div class="card">
			<h3>نیت جستجوکنندگان</h3>
			<?php echo fns_chart_split( $m['intents'], 560 ); // phpcs:ignore ?>
			<div class="tiles t2 mt">
				<?php $tile( fns_compact( $m['buyer'] ), 'جستجوی خرید، مقایسه و محلی', 'مستقیم مشتری می‌آورد', 'hl' ); ?>
				<?php $tile( fns_compact( $m['intents']['info'] ?? 0 ), 'جستجوی آموزشی', 'برای اعتماد و جذب مشتری آینده' ); ?>
			</div>
		</div>
	</div>
<?php $close(); endif; ?>

<?php if ( $on( 'clusters' ) ) : $close = $open( $section['market'] . ' · خوشه‌ها', 'خوشه‌های اصلی کلمات و ارزش هرکدام', 'هر خوشه گروهی از جستجوهای هم‌موضوع است که با یک مجموعه صفحه هدف‌گیری می‌شود.' ); ?>
	<table class="tbl">
		<thead><tr><th>خوشه</th><th>جستجوی ماهانه</th><th>سهم خرید</th><th>روند ۱۲ ماه</th><th>پرجستجوترین کلمات</th><th>پیشتاز فعلی</th></tr></thead>
		<tbody>
		<?php foreach ( array_slice( $m['clusters'], 0, 7 ) as $c ) : ?>
			<tr>
				<td><b><?php echo esc_html( $c['seed'] ); ?></b></td>
				<td class="n"><?php echo esc_html( fns_num( $c['total'] ) ); ?></td>
				<td><span class="bar-mini"><i style="width:<?php echo (int) round( 100 * $c['buyer_share'] ); ?>%"></i></span><?php echo esc_html( fns_pct( $c['buyer_share'] ) ); ?></td>
				<td><?php echo fns_chart_spark( $c['chart']['data'] ?? array(), 110, 30 ); // phpcs:ignore ?> <small dir="ltr" class="<?php echo $c['change'] >= 0 ? 'up' : 'down'; ?>"><?php echo esc_html( ( $c['change'] >= 0 ? '+' : '' ) . fns_num( $c['change'], 1 ) . '٪' ); ?></small></td>
				<td class="kw"><?php echo esc_html( implode( '، ', array_slice( array_column( $c['top'], 'w' ), 0, 4 ) ) ); ?></td>
				<td class="kw"><?php echo esc_html( implode( '، ', array_slice( array_column( $c['leaders'], 'd' ), 0, 2 ) ) ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
<?php $close(); endif; ?>

<?php if ( $on( 'money' ) ) : $close = $open( $section['market'] . ' · کلمات پول‌ساز', 'جستجوهایی که مشتری می‌آورند', 'تمرکز سئو روی این کلمات است، نه بازدید بی‌خاصیت. هر کدام صفحه اختصاصی و قوی لازم دارد.' ); ?>
	<div class="chips-grid">
		<?php foreach ( array_slice( $m['money'], 0, 24 ) as $k ) : ?>
			<div class="chip-kw i-<?php echo esc_attr( $k['i'] ); ?>"><span><?php echo esc_html( $k['w'] ); ?></span><b><?php echo esc_html( fns_num( $k['v'] ) ); ?></b></div>
		<?php endforeach; ?>
	</div>
	<p class="note">عدد کنار هر کلمه، میانگین جستجوی ماهانه در گوگل است (منبع: سئو سیگنال). رنگ: <i class="dot i-buy"></i> خرید <i class="dot i-compare"></i> مقایسه <i class="dot i-local"></i> محلی</p>
<?php $close(); endif; ?>

<?php
if ( $on( 'current' ) ) :
	if ( $cd ) :
		$cp   = $a['profiles'][ $cd ];
		$gsc  = $a['gsc'];
		$close = $open( $section['market'] . ' · وضعیت فعلی', 'جایگاه امروز ' . $client . ' در گوگل', $t['current_state'] ?? '' );
		?>
		<div class="grid g-2">
			<div class="card">
				<?php if ( ! empty( $gsc['months'] ) ) : ?>
					<h3>کلیک ماهانه از گوگل (سرچ کنسول)</h3>
					<?php
					$series = array();
					foreach ( array_slice( $gsc['months'], -13, 13, true ) as $ym => $v ) {
						$tm = strtotime( $ym . '-15' );
						list( , $jm ) = fns_jalali( (int) gmdate( 'Y', $tm ), (int) gmdate( 'n', $tm ), 15 );
						$series[ fns_jalali_months()[ $jm ] ] = $v['clicks'];
					}
					echo fns_chart_columns( $series, 560, 220 ); // phpcs:ignore
					?>
				<?php else : ?>
					<h3>صفحات پربازدید شما (تخمین)</h3>
					<table class="tbl sm"><thead><tr><th>صفحه</th><th>کلمه اصلی</th><th>کلیک ماهانه</th></tr></thead><tbody>
					<?php foreach ( array_slice( $cp['top_pages'], 0, 6 ) as $pg ) : ?>
						<tr><td class="url"><?php echo esc_html( fns_short_url( $pg['p'], 34 ) ); ?></td><td class="kw"><?php echo esc_html( $pg['top'] ); ?></td><td class="n"><?php echo esc_html( fns_num( $pg['clicks'] ) ); ?></td></tr>
					<?php endforeach; ?>
					</tbody></table>
				<?php endif; ?>
			</div>
			<div class="tiles t2">
				<?php
				if ( ! empty( $gsc['totals'] ) ) {
					$tile( fns_num( $gsc['totals']['clicks'] ), 'کلیک در ۳ ماه اخیر', 'منبع: سرچ کنسول' );
					$tile( fns_compact( $gsc['totals']['impressions'] ), 'نمایش در ۳ ماه اخیر' );
					$tile( fns_pct( ( $gsc['brand']['nonbrand'] ?? 0 ) / max( 1, $gsc['totals']['clicks'] ) ), 'سهم کلیک غیربرندی', 'مشتریانی که هنوز شما را نمی‌شناسند', 'hl' );
					$tile( fns_num( count( $gsc['strike'] ?? array() ) ), 'کلمه نزدیک صفحه اول', 'رتبه ۴ تا ۲۰؛ سریع‌ترین فرصت' );
				} else {
					$tile( fns_num( $cp['keywords'] ) . ( $cp['capped'] ? '+' : '' ), 'کلمه با رتبه در گوگل' );
					$tile( fns_num( $cp['clicks'] ), 'کلیک ماهانه (تخمین)', '', 'hl' );
					$tile( fns_num( $cp['top10'] ), 'کلمه در صفحه اول' );
					$tile( fns_num( $cp['site_pages'] ), 'صفحه در نقشه سایت' );
				}
				?>
			</div>
		</div>
		<?php
		$close();
	else :
		$close = $open( $section['market'] . ' · شروع از صفر', 'مزیت شروع درست از روز اول', $t['current_state'] ?? '' );
		?>
		<div class="grid g-3">
			<div class="card step"><b>۱</b><h3>معماری بر اساس تقاضا</h3><p>ساختار منو، دسته‌ها و آدرس‌ها از روی خوشه‌های کلمات ساخته می‌شود، نه بعداً با هزینه بازسازی.</p></div>
			<div class="card step"><b>۲</b><h3>استاندارد فنی از ابتدا</h3><p>سرعت، نسخه موبایل، اسکیما، نقشه سایت و لینک‌سازی داخلی پیش از انتشار درست می‌شود.</p></div>
			<div class="card step"><b>۳</b><h3>صفحات پول‌ساز اول</h3><p>صفحات خرید و خدمات اصلی قبل از هر مقاله‌ای ساخته و تقویت می‌شوند.</p></div>
		</div>
		<?php
		$close();
	endif;
endif;
?>

<?php if ( $on( 'competitors' ) ) : $close = $open( $section['market'] . ' · رقبا', $cd ? 'شما و رقبا، کنار هم' : 'رقبای اصلی شما', $t['competitors'] ?? '' ); ?>
	<table class="tbl cmp">
		<thead><tr><th>سایت</th><th>صفحات سایت</th><th>کلمات رتبه‌دار</th><th>صفحات رتبه‌دار</th><th>نمایش ماهانه*</th><th>کلیک ماهانه*</th><th>کلیک خرید*</th><th><?php echo in_array( $in['business_type'], array( 'service', 'local', 'saas' ), true ) ? 'سرنخ ماهانه*' : 'سفارش ماهانه*'; ?></th><th>درآمد ماهانه از گوگل*</th></tr></thead>
		<tbody>
		<?php foreach ( $a['profiles'] as $d => $pr ) : ?>
			<tr class="<?php echo 'client' === $pr['role'] ? 'me' : ''; ?>">
				<td><b><?php echo esc_html( $d ); ?></b><?php echo 'client' === $pr['role'] ? ' <em>شما</em>' : ''; ?></td>
				<td class="n"><?php echo esc_html( $pr['site_pages'] ? fns_num( $pr['site_pages'] ) : '—' ); ?></td>
				<td class="n"><?php echo esc_html( fns_num( $pr['keywords'] ) . ( $pr['capped'] ? '+' : '' ) ); ?></td>
				<td class="n"><?php echo esc_html( fns_num( $pr['ranking_pages'] ) ); ?></td>
				<td class="n"><?php echo esc_html( fns_compact( $pr['impressions'] ) ); ?></td>
				<td class="n"><?php echo esc_html( fns_compact( $pr['clicks'] ) ); ?></td>
				<td class="n"><?php echo esc_html( fns_compact( $pr['buyer_clicks'] ) ); ?></td>
				<td class="n"><?php echo esc_html( fns_compact( $pr['revenue']['orders'] ) ); ?></td>
				<td class="n"><?php echo esc_html( $range( $pr['revenue'] ) ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<p class="note">* تخمین: نمایش و کلیک از حجم جستجوی کلمات و میانگین رتبه هر صفحه با نرخ کلیک استاندارد جهانی؛ سفارش با نرخ تبدیل <?php echo esc_html( fns_num( $in['conversion_rate'], 1 ) ); ?>٪<?php echo $in['aov'] ? ' و میانگین سبد ' . esc_html( fns_toman( $in['aov'], true ) ) : ''; ?>. عددها برای مقایسه‌اند، نه گزارش دقیق.</p>
<?php $close(); endif; ?>

<?php if ( $on( 'sov' ) ) : $close = $open( $section['market'] . ' · سهم بازار', 'چه کسی کلیک‌های بازار شما را می‌گیرد؟', 'سهم هر سایت از کلیک‌های همین خوشه‌های کلمات (تخمینی).' ); ?>
	<div class="grid g-2">
		<div class="card">
			<h3>سهم از کلیک‌های بازار</h3>
			<?php
			$rows = array();
			foreach ( $a['sov'] as $d => $s ) {
				$rows[] = array( 'label' => $d, 'value' => round( 100 * $s ), 'hl' => $d === $cd );
			}
			usort( $rows, function ( $x, $y ) {
				return $y['value'] <=> $x['value'];
			} );
			echo fns_chart_hbar( $rows, 560, function ( $v ) {
				return fns_num( $v ) . '٪';
			} ); // phpcs:ignore
			?>
		</div>
		<div class="card">
			<h3>صفحات پول‌ساز رقبا</h3>
			<table class="tbl sm"><thead><tr><th>سایت</th><th>صفحه</th><th>کلمه اصلی</th><th>کلیک*</th></tr></thead><tbody>
			<?php
			$rows = array();
			foreach ( $comps as $d => $pr ) {
				foreach ( array_slice( $pr['money_pages'] ?: $pr['top_pages'], 0, 2 ) as $pg ) {
					$rows[] = array( $d, $pg );
				}
			}
			usort( $rows, function ( $x, $y ) {
				return $y[1]['clicks'] <=> $x[1]['clicks'];
			} );
			foreach ( array_slice( $rows, 0, 8 ) as $r ) :
				?>
				<tr><td><?php echo esc_html( $r[0] ); ?></td><td class="url"><?php echo esc_html( fns_short_url( $r[1]['p'], 26 ) ); ?></td><td class="kw"><?php echo esc_html( $r[1]['top'] ); ?></td><td class="n"><?php echo esc_html( fns_compact( $r[1]['clicks'] ) ); ?></td></tr>
			<?php endforeach; ?>
			</tbody></table>
		</div>
	</div>
<?php $close(); endif; ?>

<?php if ( $on( 'gap' ) ) : $close = $open( $section['market'] . ' · شکاف کلمات', 'کلماتی که رقبا دارند و شما ندارید', sprintf( '%s کلمه با حدود %s جستجوی ماهانه؛ %s از آن جستجوی خرید است.', fns_num( $a['gap']['count'] ), fns_compact( $a['gap']['volume'] ), fns_compact( $a['gap']['buyer'] ) ) ); ?>
	<div class="chips-grid three">
		<?php foreach ( array_slice( $a['gap']['top'], 0, 18 ) as $g ) : ?>
			<div class="chip-kw i-<?php echo esc_attr( $g['i'] ); ?>"><span><?php echo esc_html( $g['w'] ); ?></span><b><?php echo esc_html( fns_num( $g['v'] ) ); ?></b><small><?php echo esc_html( implode( '، ', array_slice( $g['by'], 0, 2 ) ) ); ?></small></div>
		<?php endforeach; ?>
	</div>
<?php $close(); endif; ?>

<?php if ( $on( 'div_audit' ) ) { $divider( $section['audit'], 'فنی، محتوا و اعتبار', 'چرا رقبا بالاترند و ما از کجا شروع می‌کنیم' ); } ?>

<?php if ( $on( 'offpage' ) ) : $close = $open( $section['audit'] . ' · اعتبار', 'رپورتاژ و بک‌لینک باکیفیت', $t['offpage'] ?? '' ); ?>
	<div class="grid g-2w">
		<div class="card">
			<h3>رپورتاژ باکیفیت هر سایت</h3>
			<?php
			$rows = array();
			foreach ( $a['offpage'] as $d => $o ) {
				$rows[] = array( 'label' => $d, 'value' => $o['quality_reportages'], 'hl' => $d === $cd );
			}
			echo fns_chart_hbar( $rows, 520, 'fns_num' ); // phpcs:ignore
			?>
		</div>
		<div class="card">
			<table class="tbl sm">
				<thead><tr><th>سایت</th><th>کل رپورتاژ</th><th>۱۲ ماه اخیر</th><th>رسانه</th><th>باکیفیت</th><th>دامنه ارجاع‌دهنده باکیفیت</th><th>هزینه تخمینی ۱۲ ماه</th></tr></thead>
				<tbody>
				<?php foreach ( $a['offpage'] as $d => $o ) : $r = $o['reportage']; $b = $o['backlinks']; ?>
					<tr class="<?php echo $d === $cd ? 'me' : ''; ?>">
						<td><b><?php echo esc_html( $d ); ?></b></td>
						<td class="n"><?php echo esc_html( $r['available'] ? fns_num( $r['total'] ) : '—' ); ?></td>
						<td class="n"><?php echo esc_html( $r['available'] ? fns_num( $r['last12'] ) : '—' ); ?></td>
						<td class="n"><?php echo esc_html( $r['available'] ? fns_num( $r['media'] ) : '—' ); ?></td>
						<td class="n"><b><?php echo esc_html( fns_num( $o['quality_reportages'] ) ); ?></b></td>
						<td class="n"><?php echo esc_html( ! empty( $b['available'] ) ? fns_num( $b['quality_refdomains'] ) . ' از ' . fns_num( $b['refdomains'] ) : '—' ); ?></td>
						<td class="n"><?php echo esc_html( $r['spend12'] ? fns_toman( $r['spend12'], true ) : '—' ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p class="note">«باکیفیت» یعنی: رسانه ایرانی، لینک فالو، انکر هدفمند (کلمه کلیدی، نه «اینجا» یا آدرس سایت)، بدون لینک‌های دایرکتوری، کامنت، پروفایل، سایت‌واید و وبلاگ‌های رایگان. منبع: دستیار رپورتاژ سئو سیگنال<?php echo $a['semrush'] ? ' و سمراش' : ''; ?>.</p>
		</div>
	</div>
<?php $close(); endif; ?>

<?php if ( $on( 'offpage_pages' ) ) : $close = $open( $section['audit'] . ' · لینک صفحات', 'صفحات پرفروش رقبا چقدر لینک گرفته‌اند؟', 'رقبا لینک‌ها را به صفحات فروش می‌دهند، نه فقط صفحه اصلی؛ برنامه ما هم همین است.' ); ?>
	<div class="grid g-3">
		<?php foreach ( $a['offpage'] as $d => $o ) : if ( empty( $o['reportage']['pages'] ) && empty( $o['backlinks']['pages'] ) ) { continue; } ?>
			<div class="card">
				<h3><?php echo esc_html( $d ); ?></h3>
				<table class="tbl sm"><thead><tr><th>صفحه</th><th>رپورتاژ</th><th>دامنه</th></tr></thead><tbody>
				<?php
				$list = $o['backlinks']['pages'] ?? array();
				if ( $list ) {
					foreach ( array_slice( $list, 0, 5 ) as $pg ) {
						echo '<tr><td class="url">' . esc_html( fns_short_url( fns_path( $pg['url'] ), 30 ) ) . '</td><td class="n">' . esc_html( fns_num( $pg['reportage'] ) ) . '</td><td class="n">' . esc_html( fns_num( $pg['domains'] ) ) . '</td></tr>';
					}
				} else {
					foreach ( array_slice( $o['reportage']['pages'], 0, 5 ) as $pg ) {
						echo '<tr><td class="url">' . esc_html( fns_short_url( fns_path( $pg['url'] ), 30 ) ) . '</td><td class="n">' . esc_html( fns_num( $pg['n'] ) ) . '</td><td class="n">—</td></tr>';
					}
				}
				?>
				</tbody></table>
			</div>
		<?php endforeach; ?>
	</div>
<?php $close(); endif; ?>

<?php
if ( $on( 'technical' ) ) :
	$focus = $cd ?: array_key_first( $a['technical'] );
	$te    = $a['technical'][ $focus ] ?? array();
	$close = $open( $section['audit'] . ' · فنی', $cd ? 'سلامت فنی سایت شما' : 'استاندارد فنی رقبا', $t['technical'] ?? '' );
	?>
	<div class="grid g-tech">
		<div class="card center">
			<?php echo fns_chart_ring( $te['score'] ?? 0, 170, $te['grade'] ?? '' ); // phpcs:ignore ?>
			<p class="muted"><?php echo esc_html( $focus ); ?><br><?php echo esc_html( ! empty( $te['has_sf'] ) ? 'بر اساس خزش ' . fns_num( $te['metrics']['crawled'] ) . ' آدرس با اسکریمینگ فراگ' : 'بر اساس بررسی سریع نمونه صفحات' ); ?></p>
			<?php if ( ! empty( $te['good'] ) ) : ?><ul class="good"><?php foreach ( array_slice( $te['good'], 0, 3 ) as $g ) { echo '<li>' . esc_html( $g ) . '</li>'; } ?></ul><?php endif; ?>
		</div>
		<div class="card">
			<h3>مهم‌ترین موارد</h3>
			<table class="tbl sm issues"><thead><tr><th>مورد</th><th>اهمیت</th><th>تعداد</th></tr></thead><tbody>
			<?php foreach ( array_slice( (array) ( $te['issues'] ?? array() ), 0, 9 ) as $i ) : ?>
				<tr><td><?php echo esc_html( $i['label'] ); ?></td><td><span class="sev sev-<?php echo esc_attr( $i['sev'] ); ?>"><?php echo esc_html( fns_sev_label( $i['sev'] ) ); ?></span></td><td class="n"><?php echo esc_html( fns_num( $i['count'] ) . ( $i['total'] > 1 ? ' از ' . fns_num( $i['total'] ) : '' ) ); ?></td></tr>
			<?php endforeach; ?>
			<?php if ( empty( $te['issues'] ) ) : ?><tr><td colspan="3">مورد مهمی دیده نشد.</td></tr><?php endif; ?>
			</tbody></table>
		</div>
	</div>
	<?php
	$close();
endif;
?>

<?php if ( $on( 'tech_compare' ) ) : $close = $open( $section['audit'] . ' · مقایسه فنی', 'استاندارد فنی شما و رقبا', 'امتیاز ۱۰۰ یعنی مشکلی که جلوی رشد را بگیرد دیده نشد.' ); ?>
	<table class="tbl cmp">
		<thead><tr><th>سایت</th><th>امتیاز فنی</th><th>پلتفرم</th><th>HTTPS</th><th>نقشه سایت</th><th>اسکیما در نمونه‌ها</th><th>سرعت صفحه اصلی</th><th>خطای ۴xx</th><th>لینک شکسته</th><th>عمق میانگین</th></tr></thead>
		<tbody>
		<?php foreach ( $a['technical'] as $d => $te ) : $mt = $te['metrics']; ?>
			<tr class="<?php echo $d === $cd ? 'me' : ''; ?>">
				<td><b><?php echo esc_html( $d ); ?></b></td>
				<td class="n"><b><?php echo esc_html( null === $te['score'] ? '—' : fns_num( $te['score'] ) ); ?></b></td>
				<td><?php echo esc_html( $mt['cms'] ?? '—' ); ?></td>
				<td><?php echo ! empty( $mt['https'] ) ? '✓' : '✗'; ?></td>
				<td><?php echo ! empty( $mt['sitemap'] ) ? '✓ ' . esc_html( fns_num( $mt['pages'] ) ) : '✗'; ?></td>
				<td class="n"><?php echo esc_html( ! empty( $mt['sample'] ) ? fns_num( $mt['schema'] ) . ' از ' . fns_num( $mt['sample'] ) : '—' ); ?></td>
				<td class="n"><?php echo esc_html( ! empty( $mt['home_ms'] ) ? fns_num( $mt['home_ms'] / 1000, 1 ) . ' ث' : '—' ); ?></td>
				<td class="n"><?php echo esc_html( ! empty( $te['has_sf'] ) ? fns_num( $mt['e4xx'] ) : '—' ); ?></td>
				<td class="n"><?php echo esc_html( ! empty( $te['has_sf'] ) ? fns_num( $mt['broken'] ) : '—' ); ?></td>
				<td class="n"><?php echo esc_html( ! empty( $mt['avg_depth'] ) ? fns_num( $mt['avg_depth'], 1 ) : '—' ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<p class="note">ستون‌های ۴xx، لینک شکسته و عمق از خزش اسکریمینگ فراگ است (— یعنی آن سایت خزیده نشده).</p>
<?php $close(); endif; ?>

<?php if ( $on( 'content' ) ) : $close = $open( $section['audit'] . ' · محتوا', 'محتوایی که مشتری دنبالش است', $t['content'] ?? '' ); ?>
	<div class="grid g-2">
		<div class="card">
			<h3>سؤال‌های پرجستجوی مشتریان</h3>
			<ol class="qs">
				<?php foreach ( array_slice( $m['questions'], 0, 10 ) as $q ) : ?>
					<li><span><?php echo esc_html( $q['w'] ); ?></span><b><?php echo esc_html( fns_num( $q['v'] ) ); ?></b></li>
				<?php endforeach; ?>
			</ol>
		</div>
		<div class="tiles t2">
			<?php
			$tile( fns_num( $pkg['qty']['landings'] ), 'صفحه فرود / دسته', 'برای کلمات خرید' );
			$tile( fns_num( $pkg['qty']['articles'] ), 'مقاله تخصصی', 'در ' . fns_num( $months - 1 ) . ' ماه', 'hl' );
			$tile( fns_num( $pkg['qty']['updates'] ), 'به‌روزرسانی صفحه', 'صفحات نزدیک صفحه اول' );
			$tile( fns_num( count( $m['clusters'] ) ), 'خوشه موضوعی', 'هر خوشه یک صفحه اصلی + مقالات پشتیبان' );
			?>
		</div>
	</div>
<?php $close(); endif; ?>

<?php if ( $on( 'swot' ) ) : $close = $open( $section['audit'] . ' · جمع‌بندی', 'نقاط قوت، ضعف، فرصت و تهدید', '' ); ?>
	<div class="swot">
		<div class="sw s"><h3>نقاط قوت</h3><?php $ul( $t['strengths'] ?? '' ); ?></div>
		<div class="sw w"><h3>نقاط ضعف</h3><?php $ul( $t['weaknesses'] ?? '' ); ?></div>
		<div class="sw o"><h3>فرصت‌ها</h3><?php $ul( $t['opportunities'] ?? '' ); ?></div>
		<div class="sw t"><h3>تهدیدها</h3><?php $ul( $t['threats'] ?? '' ); ?></div>
	</div>
<?php $close(); endif; ?>

<?php if ( $on( 'div_plan' ) ) { $divider( $section['plan'], 'نقشه راه و سرمایه‌گذاری', 'از کجا شروع می‌کنیم، چقدر طول می‌کشد و چه چیزی به دست می‌آید' ); } ?>

<?php if ( $on( 'roadmap' ) ) : $close = $open( $section['plan'] . ' · نقشه راه', 'نقشه راه ' . fns_num( $months ) . ' ماهه', 'سئو یک مسیر مرحله‌ای است؛ هر مرحله پایه مرحله بعد است.' ); ?>
	<div class="timeline">
		<?php foreach ( $a['roadmap'] as $i => $ph ) : ?>
			<div class="phase">
				<div class="ph-head"><span class="ph-n"><?php echo esc_html( fns_fa( $i + 1 ) ); ?></span><div><b><?php echo esc_html( $ph['label'] ); ?></b><small><?php echo esc_html( $ph['when'] ); ?></small></div></div>
				<h3><?php echo esc_html( $ph['title'] ); ?></h3>
				<p class="goal"><?php echo esc_html( $ph['goal'] ); ?></p>
				<ul><?php foreach ( array_slice( $ph['actions'], 0, 4 ) as $ac ) { echo '<li>' . esc_html( $ac ) . '</li>'; } ?></ul>
				<?php if ( $ph['deliver_text'] ) : ?><p class="deliv"><?php echo esc_html( implode( ' · ', $ph['deliver_text'] ) ); ?></p><?php endif; ?>
				<p class="res"><?php echo esc_html( $ph['result'] ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>
<?php $close(); endif; ?>

<?php if ( $on( 'plan' ) ) : $close = $open( $section['plan'] . ' · برنامه اجرایی', 'تعهد اجرایی ماه‌به‌ماه (پکیج ' . $pkg['label'] . ')', 'تعهد ما انجام دقیق همین تعدادهاست؛ هر ماه با لینک و جزئیات گزارش می‌شود.' ); ?>
	<table class="tbl plan">
		<thead><tr><th>اقدام</th><?php for ( $mm = 1; $mm <= $months; $mm++ ) { echo '<th>' . esc_html( fns_fa( $mm ) ) . '</th>'; } ?><th>جمع</th></tr></thead>
		<tbody>
			<tr><td>رفع مشکلات فنی و پایه‌گذاری</td><?php for ( $mm = 1; $mm <= $months; $mm++ ) { echo '<td>' . ( 1 === $mm ? '<i class="on"></i>' : ( $mm <= 3 ? '<i class="half"></i>' : '' ) ) . '</td>'; } ?><td class="n">ماه ۱</td></tr>
			<?php foreach ( $items as $k => $lab ) : if ( empty( $pkg['qty'][ $k ] ) ) { continue; } ?>
				<tr><td><?php echo esc_html( $lab[0] ); ?></td>
				<?php for ( $mm = 1; $mm <= $months; $mm++ ) { $v = (int) ( $pkg['plan'][ $mm ][ $k ] ?? 0 ); echo '<td class="n">' . ( $v ? esc_html( fns_fa( $v ) ) : '<span class="z">·</span>' ) . '</td>'; } ?>
				<td class="n"><b><?php echo esc_html( fns_num( $pkg['qty'][ $k ] ) ); ?></b></td></tr>
			<?php endforeach; ?>
			<tr><td>گزارش ماهانه و جلسه مرور</td><?php for ( $mm = 1; $mm <= $months; $mm++ ) { echo '<td><i class="on"></i></td>'; } ?><td class="n"><?php echo esc_html( fns_num( $months ) ); ?></td></tr>
		</tbody>
	</table>
<?php $close(); endif; ?>

<?php if ( $on( 'forecast' ) ) : $close = $open( $section['plan'] . ' · نتیجه', 'نتیجه واقع‌بینانه در ' . fns_num( $months ) . ' ماه', 'پیش‌بینی بر اساس سهمی است که رقبا همین امروز از این بازار دارند؛ یعنی دست‌یافتنی است.' ); ?>
	<div class="grid g-fc">
		<div class="card">
			<h3>کلیک غیربرندی ماهانه از گوگل</h3>
			<?php echo fns_chart_forecast( $f['curve'], $f['baseline'], 760, 300 ); // phpcs:ignore ?>
			<p class="legend"><i class="ln"></i> پیش‌بینی میانی <i class="bd"></i> بازه محتاطانه تا خوش‌بینانه <i class="dash"></i> وضعیت امروز</p>
		</div>
		<div class="tiles t1">
			<?php
			$tile( fns_num( $f['scenarios']['low'] ) . ' تا ' . fns_num( $f['scenarios']['high'] ), 'کلیک ماهانه در پایان دوره', 'سناریوی محتاطانه تا خوش‌بینانه', 'hl' );
			$tile( fns_num( $f['keywords']['low'] ) . ' تا ' . fns_num( $f['keywords']['high'] ), 'کلمه پول‌ساز در صفحه اول', 'از ' . fns_num( $f['keywords']['of'] ) . ' کلمه هدف' );
			$rv = $f['revenue'];
			$tile( $rv['high'] > 0 ? $range( $rv ) . ' تومان' : fns_num( $rv['orders'] ) . ( 'leads' === $rv['basis'] ? ' سرنخ' : ' سفارش' ), $rv['high'] > 0 ? 'فروش ماهانه از گوگل (تخمین)' : ( 'leads' === $rv['basis'] ? 'سرنخ ماهانه از گوگل' : 'سفارش ماهانه از گوگل' ), 'ارزش تبلیغاتی معادل: ' . fns_toman( $rv['value'], true ) . ' در ماه' );
			?>
		</div>
	</div>
	<p class="note">این عددها پیش‌بینی‌اند، نه تعهد. تعهد ما انجام دقیق کارهای برنامه است؛ نتیجه به رقابت بازار، کیفیت سایت و سرعت همکاری هم بستگی دارد.</p>
<?php $close(); endif; ?>

<?php if ( $on( 'packages' ) ) : $close = $open( $section['plan'] . ' · سرمایه‌گذاری', 'سه مسیر برای همکاری', 'همه پکیج‌ها شامل استراتژی، رفع فنی، مدیریت پروژه و گزارش ماهانه است؛ تفاوت در سرعت رشد است.' ); ?>
	<div class="packs">
		<?php foreach ( $bud['packages'] as $k => $pk ) : $fc = $a['forecasts'][ $k ] ?? array(); ?>
			<div class="pack <?php echo $k === $sel ? 'rec' : ''; ?>">
				<?php if ( $k === $sel ) : ?><span class="badge">پیشنهاد ما</span><?php endif; ?>
				<h3><?php echo esc_html( $pk['label'] ); ?></h3>
				<p class="pk-note"><?php echo esc_html( $pk['note'] ); ?></p>
				<div class="price"><b><?php echo esc_html( fns_compact( $pk['monthly'] ) ); ?></b><span>تومان در ماه (میانگین، همه هزینه‌ها)</span></div>
				<ul>
					<?php foreach ( $items as $ik => $lab ) : if ( empty( $pk['qty'][ $ik ] ) ) { continue; } ?>
						<li><b><?php echo esc_html( fns_num( $pk['qty'][ $ik ] ) ); ?></b> <?php echo esc_html( $lab[0] ); ?></li>
					<?php endforeach; ?>
				</ul>
				<p class="pk-res">پیش‌بینی: <b><?php echo esc_html( fns_num( $fc['low'] ?? 0 ) . ' تا ' . fns_num( $fc['high'] ?? 0 ) ); ?></b> کلیک ماهانه</p>
				<p class="pk-total">جمع <?php echo esc_html( fns_num( $months ) ); ?> ماه: <?php echo esc_html( fns_toman( $pk['total'], true ) ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>
<?php $close(); endif; ?>

<?php if ( $on( 'costs' ) ) : $close = $open( $section['plan'] . ' · جزئیات هزینه', 'هزینه‌ها، شفاف و تفکیک‌شده', 'پکیج ' . $pkg['label'] . ' — هر عدد از تحلیل رقبا آمده و قابل توضیح است.' ); ?>
	<div class="grid g-cost">
		<table class="tbl cost">
			<thead><tr><th>بخش</th><th>تعداد</th><th>هزینه واحد</th><th>جمع دوره</th><th>چرا این تعداد؟</th></tr></thead>
			<tbody>
				<tr class="grp"><td colspan="5">۱. بودجه محتوا — پرداخت مستقیم شما به نویسنده/تیم محتوا</td></tr>
				<?php foreach ( array( 'landings', 'articles', 'updates' ) as $k ) : if ( empty( $pkg['qty'][ $k ] ) ) { continue; } ?>
					<tr><td><?php echo esc_html( $items[ $k ][0] ); ?></td><td class="n"><?php echo esc_html( fns_num( $pkg['qty'][ $k ] ) ); ?></td><td class="n"><?php echo esc_html( fns_compact( $bud['prices'][ $k ] ) ); ?></td><td class="n"><?php echo esc_html( fns_compact( $pkg['lines'][ $k ] ) ); ?></td><td class="why"><?php echo esc_html( $bud['why'][ $k ] ?? '' ); ?></td></tr>
				<?php endforeach; ?>
				<tr class="grp"><td colspan="5">۲. بودجه اعتبار (آف‌پیج) — پرداخت مستقیم شما در پنل رپورتاژ (مثل تریبون، تسمینو)</td></tr>
				<?php foreach ( array( 'reportages', 'backlinks' ) as $k ) : if ( empty( $pkg['qty'][ $k ] ) ) { continue; } ?>
					<tr><td><?php echo esc_html( $items[ $k ][0] ); ?></td><td class="n"><?php echo esc_html( fns_num( $pkg['qty'][ $k ] ) ); ?></td><td class="n"><?php echo esc_html( fns_compact( $bud['prices'][ $k ] ) ); ?></td><td class="n"><?php echo esc_html( fns_compact( $pkg['lines'][ $k ] ) ); ?></td><td class="why"><?php echo esc_html( $bud['why'][ $k ] ?? '' ); ?></td></tr>
				<?php endforeach; ?>
				<tr class="grp"><td colspan="5">۳. دستمزد تیم سئو — استراتژی، اجرای فنی، مدیریت محتوا و لینک، گزارش</td></tr>
				<tr><td>دستمزد ماهانه</td><td class="n"><?php echo esc_html( fns_num( $months ) ); ?> ماه</td><td class="n"><?php echo esc_html( fns_compact( $pkg['fee'] ) ); ?></td><td class="n"><?php echo esc_html( fns_compact( $pkg['fees'] ) ); ?></td><td class="why">ثابت در طول دوره</td></tr>
			</tbody>
		</table>
		<div class="tiles t1">
			<?php
			$tile( fns_toman( $pkg['total'], true ), 'جمع کل دوره', 'میانگین ماهانه ' . fns_toman( $pkg['monthly'], true ), 'hl' );
			$tile( fns_toman( $pkg['direct'], true ), 'بودجه محتوا و آف‌پیج', fns_pct( $pkg['direct'] / max( 1, $pkg['total'] ) ) . ' — مستقیم توسط شما پرداخت می‌شود' );
			$tile( fns_toman( $pkg['fees'], true ), 'دستمزد تیم سئو', fns_pct( $pkg['fees'] / max( 1, $pkg['total'] ) ) . ' از کل' );
			?>
			<div class="trust">بودجه محتوا و رپورتاژ را خودتان در اکانت اختصاصی پنل‌ها یا مستقیم به نویسنده پرداخت می‌کنید؛ ۱۰۰٪ شفاف و قابل پیگیری.</div>
		</div>
	</div>
<?php $close(); endif; ?>

<?php if ( $on( 'commitments' ) ) : $close = $open( $section['plan'] . ' · قرار همکاری', 'تعهد ما: انجام کار، نه وعده رتبه', 'رتبه و کلیک را هیچ‌کس نمی‌تواند تضمین کند؛ ما انجام دقیق و شفاف همین برنامه را تضمین می‌کنیم.' ); ?>
	<div class="grid g-2">
		<div class="card commit"><h3>تعهدات ما</h3><?php $ul( $t['commitments'] ?? '', 'checks' ); ?></div>
		<div class="card"><h3>آنچه از شما لازم داریم</h3><?php $ul( $t['client_needs'] ?? '', 'dots' ); ?></div>
	</div>
<?php $close(); endif; ?>

<?php if ( $on( 'portfolio' ) ) : $close = $open( 'نمونه‌کار', 'نتایجی که پیش‌تر ساخته‌ایم', fns_setting( 'agency_about' ) ); ?>
	<div class="grid g-3">
		<?php foreach ( array_slice( fns_portfolio(), 0, 6 ) as $pf ) : ?>
			<div class="card case"><h3><?php echo esc_html( $pf['title'] ); ?></h3><b><?php echo esc_html( $pf['result'] ); ?></b><p><?php echo esc_html( $pf['detail'] ); ?></p></div>
		<?php endforeach; ?>
	</div>
<?php $close(); endif; ?>

<?php if ( $on( 'closing' ) ) : $page++; ?>
<section class="slide s-closing">
	<div class="beam big"></div>
	<div class="cl-body">
		<span class="cover-kicker">قدم بعدی</span>
		<h2>از همین ماه شروع کنیم</h2>
		<ol class="steps">
			<?php foreach ( fns_lines( $t['next_steps'] ?? '' ) as $s ) { echo '<li>' . esc_html( $s ) . '</li>'; } ?>
		</ol>
	</div>
	<div class="cl-contact">
		<div class="cover-logo sm"><img src="<?php echo esc_attr( $assets['logo'] ); ?>" alt=""></div>
		<b><?php echo esc_html( fns_setting( 'consultant_name' ) ); ?></b>
		<span><?php echo esc_html( fns_setting( 'consultant_title' ) . '، ' . $agency ); ?></span>
		<?php foreach ( array( 'agency_phone', 'agency_email', 'agency_site', 'agency_address' ) as $k ) : if ( fns_setting( $k ) ) : ?>
			<span class="ct"><?php echo esc_html( fns_setting( $k ) ); ?></span>
		<?php endif; endforeach; ?>
	</div>
	<span class="pg light"><?php echo esc_html( fns_fa( $page ) ); ?></span>
</section>
<?php endif; ?>

</main>
<?php if ( $args['print'] ) : ?>
<script>document.fonts.ready.then(function(){setTimeout(function(){window.print();},400);});</script>
<?php endif; ?>
</body>
</html>
