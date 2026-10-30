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
 * @package Ostorlab
 */

defined( 'ABSPATH' ) || exit;

$in      = $p['inputs'];
$assets  = ost_deck_assets( $args['inline'] );
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
$items   = ost_budget_items();
$agency  = ost_setting( 'agency_name' );
$page    = 0;
$colors  = array( 'primary' => ost_setting( 'theme_primary', '#0B4F9C' ), 'accent' => ost_setting( 'theme_accent', '#0D94E4' ), 'dark' => ost_setting( 'theme_dark', '#0A2540' ) );
$section = array( 'market' => '۰۱', 'audit' => '۰۲', 'plan' => '۰۳' );
$who     = ost_presenter( $p );
$sg      = (array) ( $a['strategy'] ?? array() );
$initial = function ( $name ) {
	return mb_substr( trim( (string) $name ), 0, 1 );
};
$on      = function ( $k ) use ( $slides ) {
	return isset( $slides[ $k ] );
};
$logo    = $assets['logo'];
$GLOBALS['ost_deck_no'] = ost_presenter( $p )['number'];
$open    = function ( $kicker, $title, $lead = '', $class = '' ) use ( &$page, $logo, $agency, $client ) {
	$page++;
	echo '<section class="slide s-content ' . esc_attr( $class ) . '"><div class="beam"></div><header class="sh"><span class="kicker">' . esc_html( $kicker ) . '</span><h2>' . esc_html( $title ) . '</h2>' . ( $lead ? '<p class="lead">' . esc_html( $lead ) . '</p>' : '' ) . '</header><div class="sb">';
	return function () use ( $page, $logo, $agency, $client ) {
		echo '</div><footer class="sf"><span class="sf-brand"><img src="' . esc_attr( $logo ) . '" alt=""><b>' . esc_html( $agency ) . '</b></span><span class="sf-client">پروپوزال سئو ' . esc_html( $client ) . ( $GLOBALS['ost_deck_no'] ? ' · ' . esc_html( $GLOBALS['ost_deck_no'] ) : '' ) . '</span><span class="pg">' . esc_html( ost_fa( $page ) ) . '</span></footer></section>';
	};
};
$divider = function ( $num, $title, $sub ) use ( &$page ) {
	$page++;
	echo '<section class="slide s-divider"><div class="beam big"></div><div class="dv"><span class="dv-num">' . esc_html( $num ) . '</span><h2>' . esc_html( $title ) . '</h2><p>' . esc_html( $sub ) . '</p></div><span class="pg light">' . esc_html( ost_fa( $page ) ) . '</span></section>';
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
	foreach ( ost_lines( $text ) as $li ) {
		echo '<li>' . esc_html( $li ) . '</li>';
	}
	echo '</ul>';
};
$range = function ( $rev ) {
	if ( $rev['high'] > 0 ) {
		return ost_compact( $rev['low'] ) . ' تا ' . ost_compact( $rev['high'] );
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
<body class="<?php echo $args['web'] ? 'web' : 'print'; ?><?php echo $args['download'] ? ' is-download' : ''; ?>" data-file="<?php echo esc_attr( ost_pdf_filename( $p ) ); ?>">
<?php if ( $args['web'] ) : ?>
<div class="toolbar"><span><?php echo esc_html( 'پروپوزال سئو ' . $client ); ?></span><button type="button" data-make-pdf>دانلود PDF</button></div>
<?php endif; ?>
<main class="deck">

<?php if ( $on( 'cover' ) ) : $page++; ?>
<section class="slide s-cover">
	<div class="cover-art"><svg class="astro" viewBox="0 0 600 600" aria-hidden="true"><g fill="none" stroke="rgba(255,255,255,.22)" stroke-width="1.5"><circle cx="300" cy="300" r="270"/><circle cx="300" cy="300" r="230"/><circle cx="300" cy="300" r="150"/><circle cx="300" cy="300" r="70"/><ellipse cx="300" cy="360" rx="190" ry="120"/><ellipse cx="300" cy="400" rx="130" ry="70"/><line x1="30" y1="300" x2="570" y2="300"/><line x1="300" y1="30" x2="300" y2="570"/><line x1="562.0" y1="300.0" x2="536.0" y2="300.0"/><line x1="560.6" y1="327.4" x2="546.6" y2="325.9"/><line x1="556.3" y1="354.5" x2="542.6" y2="351.6"/><line x1="549.2" y1="381.0" x2="535.9" y2="376.6"/><line x1="539.3" y1="406.6" x2="526.6" y2="400.9"/><line x1="526.9" y1="431.0" x2="504.4" y2="418.0"/><line x1="512.0" y1="454.0" x2="500.6" y2="445.8"/><line x1="494.7" y1="475.3" x2="484.3" y2="465.9"/><line x1="475.3" y1="494.7" x2="465.9" y2="484.3"/><line x1="454.0" y1="512.0" x2="445.8" y2="500.6"/><line x1="431.0" y1="526.9" x2="418.0" y2="504.4"/><line x1="406.6" y1="539.3" x2="400.9" y2="526.6"/><line x1="381.0" y1="549.2" x2="376.6" y2="535.9"/><line x1="354.5" y1="556.3" x2="351.6" y2="542.6"/><line x1="327.4" y1="560.6" x2="325.9" y2="546.6"/><line x1="300.0" y1="562.0" x2="300.0" y2="536.0"/><line x1="272.6" y1="560.6" x2="274.1" y2="546.6"/><line x1="245.5" y1="556.3" x2="248.4" y2="542.6"/><line x1="219.0" y1="549.2" x2="223.4" y2="535.9"/><line x1="193.4" y1="539.3" x2="199.1" y2="526.6"/><line x1="169.0" y1="526.9" x2="182.0" y2="504.4"/><line x1="146.0" y1="512.0" x2="154.2" y2="500.6"/><line x1="124.7" y1="494.7" x2="134.1" y2="484.3"/><line x1="105.3" y1="475.3" x2="115.7" y2="465.9"/><line x1="88.0" y1="454.0" x2="99.4" y2="445.8"/><line x1="73.1" y1="431.0" x2="95.6" y2="418.0"/><line x1="60.7" y1="406.6" x2="73.4" y2="400.9"/><line x1="50.8" y1="381.0" x2="64.1" y2="376.6"/><line x1="43.7" y1="354.5" x2="57.4" y2="351.6"/><line x1="39.4" y1="327.4" x2="53.4" y2="325.9"/><line x1="38.0" y1="300.0" x2="64.0" y2="300.0"/><line x1="39.4" y1="272.6" x2="53.4" y2="274.1"/><line x1="43.7" y1="245.5" x2="57.4" y2="248.4"/><line x1="50.8" y1="219.0" x2="64.1" y2="223.4"/><line x1="60.7" y1="193.4" x2="73.4" y2="199.1"/><line x1="73.1" y1="169.0" x2="95.6" y2="182.0"/><line x1="88.0" y1="146.0" x2="99.4" y2="154.2"/><line x1="105.3" y1="124.7" x2="115.7" y2="134.1"/><line x1="124.7" y1="105.3" x2="134.1" y2="115.7"/><line x1="146.0" y1="88.0" x2="154.2" y2="99.4"/><line x1="169.0" y1="73.1" x2="182.0" y2="95.6"/><line x1="193.4" y1="60.7" x2="199.1" y2="73.4"/><line x1="219.0" y1="50.8" x2="223.4" y2="64.1"/><line x1="245.5" y1="43.7" x2="248.4" y2="57.4"/><line x1="272.6" y1="39.4" x2="274.1" y2="53.4"/><line x1="300.0" y1="38.0" x2="300.0" y2="64.0"/><line x1="327.4" y1="39.4" x2="325.9" y2="53.4"/><line x1="354.5" y1="43.7" x2="351.6" y2="57.4"/><line x1="381.0" y1="50.8" x2="376.6" y2="64.1"/><line x1="406.6" y1="60.7" x2="400.9" y2="73.4"/><line x1="431.0" y1="73.1" x2="418.0" y2="95.6"/><line x1="454.0" y1="88.0" x2="445.8" y2="99.4"/><line x1="475.3" y1="105.3" x2="465.9" y2="115.7"/><line x1="494.7" y1="124.7" x2="484.3" y2="134.1"/><line x1="512.0" y1="146.0" x2="500.6" y2="154.2"/><line x1="526.9" y1="169.0" x2="504.4" y2="182.0"/><line x1="539.3" y1="193.4" x2="526.6" y2="199.1"/><line x1="549.2" y1="219.0" x2="535.9" y2="223.4"/><line x1="556.3" y1="245.5" x2="542.6" y2="248.4"/><line x1="560.6" y1="272.6" x2="546.6" y2="274.1"/></g><g stroke="rgba(186,230,253,.7)" stroke-width="3" stroke-linecap="round"><line x1="110" y1="480" x2="490" y2="120"/></g><circle cx="300" cy="300" r="9" fill="rgba(253,230,138,.9)"/><circle cx="490" cy="120" r="6" fill="rgba(186,230,253,.9)"/></svg></div>
	<div class="cover-logo"><img src="<?php echo esc_attr( $assets['logo'] ); ?>" alt="<?php echo esc_attr( $agency ); ?>"></div>
	<div class="cover-body">
		<span class="cover-kicker">پروپوزال سئو و رشد ارگانیک<?php echo $who['prepared_for'] ? ' · تقدیم به ' . esc_html( $who['prepared_for'] ) : ''; ?></span>
		<h1><?php echo esc_html( $client ); ?></h1>
		<p class="cover-head"><?php echo esc_html( $t['headline'] ?? '' ); ?></p>
		<div class="cover-meta">
			<?php if ( $cd ) : ?><span><?php echo esc_html( $cd ); ?></span><?php endif; ?>
			<span><?php echo esc_html( $who['date'] ); ?></span>
			<span>دوره <?php echo esc_html( ost_num( $months ) ); ?> ماهه</span>
			<?php if ( $who['number'] ) : ?><span dir="ltr"><?php echo esc_html( $who['number'] ); ?></span><?php endif; ?>
			<?php if ( $who['valid_until'] ) : ?><span>معتبر تا <?php echo esc_html( $who['valid_until'] ); ?></span><?php endif; ?>
		</div>
	</div>
	<div class="cover-foot">
		<span>تهیه‌شده توسط <b><?php echo esc_html( $who['name'] ); ?></b> — <?php echo esc_html( $who['title'] ); ?>، <?php echo esc_html( $agency ); ?><?php echo $who['phone'] ? ' · <bdi>' . esc_html( $who['phone'] ) . '</bdi>' : ''; ?></span>
		<span><?php echo esc_html( $who['site'] ); ?></span>
	</div>
</section>
<?php endif; ?>

<?php if ( $on( 'summary' ) ) : $close = $open( 'خلاصه اجرایی', 'در یک نگاه', '' ); ?>
	<div class="grid g-sum">
		<div>
			<div class="prose big"><?php $para( $t['executive_summary'] ?? '' ); ?></div>
			<?php if ( ! empty( $sg['picks'] ) ) : ?>
				<div class="sum-strat"><span>نقطه شروع</span><b>«<?php echo esc_html( $sg['picks'][0]['label'] ); ?>»</b><small><?php echo esc_html( ost_num( $sg['picks'][0]['volume'] ) . ' جستجوی ماهانه · نتیجه در ' . ost_num( $sg['picks'][0]['months'][0] ) . ' تا ' . ost_num( $sg['picks'][0]['months'][1] ) . ' ماه' ); ?></small></div>
			<?php endif; ?>
		</div>
		<div class="tiles t2">
			<?php
			$tile( ost_compact( $m['total'] ), 'جستجوی ماهانه بازار شما', ost_num( count( $m['clusters'] ) ) . ' خوشه، ' . ost_num( $m['keywords'] ) . ' کلمه' );
			$tile( ost_compact( $m['buyer'] ), 'جستجوی آماده خرید', ost_pct( $m['buyer'] / max( 1, $m['total'] ) ) . ' از کل تقاضا', 'hl' );
			$tile( ost_num( $f['scenarios']['low'] ) . ' تا ' . ost_num( $f['scenarios']['high'] ), 'کلیک غیربرندی ماهانه در پایان دوره', $f['baseline'] ? 'امروز حدود ' . ost_num( $f['baseline'] ) : 'شروع از صفر' );
			$tile( ost_num( $months ) . ' ماه', 'دوره پیشنهادی', 'پکیج ' . $pkg['label'] . '، ماهانه حدود ' . ost_toman( $pkg['monthly'], true ) );
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
			echo ost_chart_hbar( $rows, 560 ); // phpcs:ignore
			?>
		</div>
		<div class="card">
			<h3>نیت جستجوکنندگان</h3>
			<?php echo ost_chart_split( $m['intents'], 560 ); // phpcs:ignore ?>
			<div class="tiles t2 mt">
				<?php $tile( ost_compact( $m['buyer'] ), 'جستجوی خرید، مقایسه و محلی', 'مستقیم مشتری می‌آورد', 'hl' ); ?>
				<?php
				if ( ! empty( $m['intents']['info'] ) ) {
					$tile( ost_compact( $m['intents']['info'] ), 'جستجوی آموزشی', 'برای اعتماد و جذب مشتری آینده' );
				} else {
					$tile( ost_num( $m['keywords'] ), 'کلمه مرتبط با جستجوی واقعی', 'کلمات بی‌ربط و بدون جستجو حذف شده‌اند' );
				}
				?>
			</div>
		</div>
	</div>
<?php $close(); endif; ?>

<?php
if ( $on( 'channels' ) ) :
	$ch    = $a['channels'];
	$close = $open( $section['market'] . ' · کانال‌های تقاضا', 'مشتریان شما کجا دنبال این محصول/خدمت می‌گردند؟', 'گوگل فقط یکی از کانال‌هاست؛ این برآورد نشان می‌دهد سئو چه سهمی از کل تقاضای بازار شما را پوشش می‌دهد.' );
	?>
	<div class="grid g-chan">
		<div class="card">
			<h3>سهم هر کانال از تقاضای بازار (برآورد)</h3>
			<div class="stack100" dir="rtl">
				<?php foreach ( $ch['channels'] as $k => $c ) : ?>
					<i style="flex:<?php echo esc_attr( round( 1000 * $c['share'] ) ); ?>;background:<?php echo esc_attr( $c['color'] ); ?>"><?php echo $c['share'] >= .08 ? esc_html( ost_num( round( 100 * $c['share'] ) ) . '٪' ) : ''; ?></i>
				<?php endforeach; ?>
			</div>
			<ul class="chan-list">
				<?php foreach ( $ch['channels'] as $k => $c ) : ?>
					<li class="<?php echo 'google' === $k ? 'is-google' : ''; ?>">
						<i class="dot" style="background:<?php echo esc_attr( $c['color'] ); ?>"></i>
						<div><b><?php echo esc_html( $c['label'] ); ?></b><?php echo $c['why'] ? '<small>' . esc_html( $c['why'] ) . '</small>' : ''; ?></div>
						<em><?php echo esc_html( ost_num( round( 100 * $c['share'] ) ) . '٪' ); ?><span>حدود <?php echo esc_html( ost_compact( $c['demand'] ) ); ?> در ماه</span></em>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<div class="tiles t1">
			<?php
			$tile( ost_compact( $ch['total'] ), 'تقاضای ماهانه در همه کانال‌ها', 'برآورد، با پایه جستجوی واقعی گوگل' );
			$tile( ost_compact( $ch['google'] ), 'جستجوی ماهانه در گوگل', 'اندازه‌گیری‌شده (سئو سیگنال) — سهم سئو', 'hl' );
			?>
			<p class="note"><?php echo esc_html( $ch['note'] ?: ( 'ai' === $ch['source'] ? 'سهم کانال‌ها برآورد هوش مصنوعی از رفتار خرید این صنعت در ایران است.' : 'سهم کانال‌ها برآورد ما از الگوی معمول این نوع کسب‌وکار در ایران است.' ) ); ?> عدد گوگل داده واقعی است و بقیه کانال‌ها نسبت به آن تخمین زده شده‌اند.</p>
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
				<td class="n"><?php echo esc_html( ost_num( $c['total'] ) ); ?></td>
				<td><span class="bar-mini"><i style="width:<?php echo (int) round( 100 * $c['buyer_share'] ); ?>%"></i></span><?php echo esc_html( ost_pct( $c['buyer_share'] ) ); ?></td>
				<td><?php echo ost_chart_spark( $c['chart']['data'] ?? array(), 110, 30 ); // phpcs:ignore ?> <small dir="ltr" class="<?php echo $c['change'] >= 0 ? 'up' : 'down'; ?>"><?php echo esc_html( ( $c['change'] >= 0 ? '+' : '' ) . ost_num( $c['change'], 1 ) . '٪' ); ?></small></td>
				<td class="kw"><?php echo esc_html( implode( '، ', array_slice( array_column( $c['top'], 'w' ), 0, 4 ) ) ); ?></td>
				<td class="kw"><?php echo esc_html( implode( '، ', array_slice( array_column( $c['leaders'], 'd' ), 0, 2 ) ) ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
<?php $close(); endif; ?>

<?php if ( $on( 'keykw' ) ) : $close = $open( $section['market'] . ' · کلمات کلیدی اصلی', 'کلماتی که واقعاً برای فروش شما مهم‌اند', $m['ai_note'] ?: 'از میان همه کلمات بازار، این‌ها مستقیم‌ترین مسیر به مشتری‌اند؛ کلمات بی‌ربط و بدون جستجو کنار گذاشته شده‌اند.' ); ?>
	<?php $prio = array( 1 => array( 'حیاتی', 'p1' ), 2 => array( 'مهم', 'p2' ), 3 => array( 'مفید', 'p3' ) ); $kk = array_slice( $m['key'], 0, 12 ); ?>
	<div class="key-grid">
		<?php foreach ( $kk as $i => $k ) : $pr = $prio[ $k['p'] ] ?? $prio[2]; ?>
			<div class="key-kw">
				<span class="pr <?php echo esc_attr( $pr[1] ); ?>"><?php echo esc_html( $pr[0] ); ?></span>
				<b><?php echo esc_html( $k['w'] ); ?></b>
				<em><?php echo esc_html( ost_num( $k['v'] ) ); ?><i>جستجو در ماه</i></em>
				<?php if ( $k['why'] ) : ?><small><?php echo esc_html( $k['why'] ); ?></small><?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
	<?php if ( ! empty( $m['filtered'] ) && ( $m['filtered']['zero'] + $m['filtered']['irrelevant'] + $m['filtered']['ai'] ) > 0 ) : ?>
		<p class="note">پالایش داده: <?php echo esc_html( ost_num( $m['filtered']['zero'] + $m['filtered']['irrelevant'] + $m['filtered']['ai'] ) ); ?> کلمه بدون جستجو یا بی‌ربط با کسب‌وکار شما حذف شد و تحلیل روی <?php echo esc_html( ost_num( $m['filtered']['kept'] ) ); ?> کلمه واقعی انجام شده است. منبع حجم جستجو: سئو سیگنال.</p>
	<?php endif; ?>
<?php $close(); endif; ?>

<?php if ( $on( 'money' ) ) : $close = $open( $section['market'] . ' · کلمات پول‌ساز', 'جستجوهایی که مشتری می‌آورند', 'تمرکز سئو روی این کلمات است، نه بازدید بی‌خاصیت. هر کدام صفحه اختصاصی و قوی لازم دارد.' ); ?>
	<div class="chips-grid">
		<?php foreach ( array_slice( $m['money'], 0, 24 ) as $k ) : ?>
			<div class="chip-kw i-<?php echo esc_attr( $k['i'] ); ?>"><span><?php echo esc_html( $k['w'] ); ?></span><b><?php echo esc_html( ost_num( $k['v'] ) ); ?></b></div>
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
						list( , $jm ) = ost_jalali( (int) gmdate( 'Y', $tm ), (int) gmdate( 'n', $tm ), 15 );
						$series[ ost_jalali_months()[ $jm ] ] = $v['clicks'];
					}
					echo ost_chart_columns( $series, 560, 220 ); // phpcs:ignore
					?>
				<?php else : ?>
					<h3>صفحات پربازدید شما (تخمین)</h3>
					<table class="tbl sm"><thead><tr><th>صفحه</th><th>کلمه اصلی</th><th>کلیک ماهانه</th></tr></thead><tbody>
					<?php foreach ( array_slice( $cp['top_pages'], 0, 6 ) as $pg ) : ?>
						<tr><td class="url"><?php echo esc_html( ost_short_url( $pg['p'], 34 ) ); ?></td><td class="kw"><?php echo esc_html( $pg['top'] ); ?></td><td class="n"><?php echo esc_html( ost_num( $pg['clicks'] ) ); ?></td></tr>
					<?php endforeach; ?>
					</tbody></table>
				<?php endif; ?>
			</div>
			<div class="tiles t2">
				<?php
				if ( ! empty( $gsc['totals'] ) ) {
					$tile( ost_num( $gsc['totals']['clicks'] ), 'کلیک در ۳ ماه اخیر', 'منبع: سرچ کنسول' );
					$tile( ost_compact( $gsc['totals']['impressions'] ), 'نمایش در ۳ ماه اخیر' );
					$tile( ost_pct( ( $gsc['brand']['nonbrand'] ?? 0 ) / max( 1, $gsc['totals']['clicks'] ) ), 'سهم کلیک غیربرندی', 'مشتریانی که هنوز شما را نمی‌شناسند', 'hl' );
					$tile( ost_num( count( $gsc['strike'] ?? array() ) ), 'کلمه نزدیک صفحه اول', 'رتبه ۴ تا ۲۰؛ سریع‌ترین فرصت' );
				} else {
					$tile( ost_num( $cp['keywords'] ) . ( $cp['capped'] ? '+' : '' ), 'کلمه با رتبه در گوگل' );
					$tile( ost_num( $cp['clicks'] ), 'کلیک ماهانه (تخمین)', '', 'hl' );
					$tile( ost_num( $cp['top10'] ), 'کلمه در صفحه اول' );
					$tile( ost_num( $cp['site_pages'] ), 'صفحه در نقشه سایت' );
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
	<?php
	$crows = ost_deck_competitor_rows( $a );
	$ccol  = function ( $fn ) use ( $crows ) {
		foreach ( $crows as $pr ) {
			if ( $fn( $pr ) ) {
				return true;
			}
		}
		return false;
	};
	$c_pages = $ccol( function ( $pr ) {
		return ! empty( $pr['site_pages'] );
	} );
	$c_ord   = $ccol( function ( $pr ) {
		return ! empty( $pr['revenue']['orders'] );
	} );
	$c_rev   = $ccol( function ( $pr ) {
		return ! empty( $pr['revenue']['high'] );
	} );
	?>
	<table class="tbl cmp">
		<thead><tr><th>سایت</th><?php echo $c_pages ? '<th>صفحات سایت</th>' : ''; ?><th>کلمات رتبه‌دار</th><th>صفحات رتبه‌دار</th><th>نمایش ماهانه*</th><th>کلیک ماهانه*</th><th>کلیک خرید*</th><?php echo $c_ord ? '<th>' . ( in_array( $in['business_type'], array( 'service', 'local', 'saas' ), true ) ? 'سرنخ ماهانه*' : 'سفارش ماهانه*' ) . '</th>' : ''; ?><?php echo $c_rev ? '<th>درآمد ماهانه از گوگل*</th>' : ''; ?></tr></thead>
		<tbody>
		<?php foreach ( $crows as $d => $pr ) : ?>
			<tr class="<?php echo 'client' === $pr['role'] ? 'me' : ''; ?>">
				<td><b><?php echo esc_html( $d ); ?></b><?php echo 'client' === $pr['role'] ? ' <em>شما</em>' : ''; ?></td>
				<?php if ( $c_pages ) : ?><td class="n"><?php echo esc_html( $pr['site_pages'] ? ost_num( $pr['site_pages'] ) : '—' ); ?></td><?php endif; ?>
				<td class="n"><?php echo esc_html( ost_num( $pr['keywords'] ) . ( $pr['capped'] ? '+' : '' ) ); ?></td>
				<td class="n"><?php echo esc_html( ost_num( $pr['ranking_pages'] ) ); ?></td>
				<td class="n"><?php echo esc_html( ost_compact( $pr['impressions'] ) ); ?></td>
				<td class="n"><?php echo esc_html( ost_compact( $pr['clicks'] ) ); ?></td>
				<td class="n"><?php echo esc_html( ost_compact( $pr['buyer_clicks'] ) ); ?></td>
				<?php if ( $c_ord ) : ?><td class="n"><?php echo esc_html( ost_compact( $pr['revenue']['orders'] ) ); ?></td><?php endif; ?>
				<?php if ( $c_rev ) : ?><td class="n"><?php echo esc_html( $range( $pr['revenue'] ) ); ?></td><?php endif; ?>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<p class="note">* تخمین: نمایش و کلیک از حجم جستجوی کلمات و میانگین رتبه هر صفحه با نرخ کلیک استاندارد جهانی؛ سفارش با نرخ تبدیل <?php echo esc_html( ost_num( $in['conversion_rate'], 1 ) ); ?>٪<?php echo $in['aov'] ? ' و میانگین سبد ' . esc_html( ost_toman( $in['aov'], true ) ) : ''; ?>. عددها برای مقایسه‌اند، نه گزارش دقیق.</p>
<?php $close(); endif; ?>

<?php if ( $on( 'sov' ) ) : $close = $open( $section['market'] . ' · سهم بازار', 'چه کسی کلیک‌های بازار شما را می‌گیرد؟', 'سهم هر سایت از کلیک‌های همین خوشه‌های کلمات (تخمینی).' ); ?>
	<div class="grid g-2">
		<div class="card">
			<h3>سهم از کلیک‌های بازار</h3>
			<?php
			$rows = array();
			foreach ( array_filter( $a['sov'] ) as $d => $s ) {
				$rows[] = array( 'label' => $d, 'value' => round( 100 * $s ), 'hl' => $d === $cd );
			}
			usort( $rows, function ( $x, $y ) {
				return $y['value'] <=> $x['value'];
			} );
			echo ost_chart_hbar( $rows, 560, function ( $v ) {
				return ost_num( $v ) . '٪';
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
				<tr><td><?php echo esc_html( $r[0] ); ?></td><td class="url"><?php echo esc_html( ost_short_url( $r[1]['p'], 26 ) ); ?></td><td class="kw"><?php echo esc_html( $r[1]['top'] ); ?></td><td class="n"><?php echo esc_html( ost_compact( $r[1]['clicks'] ) ); ?></td></tr>
			<?php endforeach; ?>
			</tbody></table>
		</div>
	</div>
<?php $close(); endif; ?>

<?php if ( $on( 'gap' ) ) : $close = $open( $section['market'] . ' · شکاف کلمات', 'کلماتی که رقبا دارند و شما ندارید', sprintf( '%s کلمه با حدود %s جستجوی ماهانه؛ %s از آن جستجوی خرید است.', ost_num( $a['gap']['count'] ), ost_compact( $a['gap']['volume'] ), ost_compact( $a['gap']['buyer'] ) ) ); ?>
	<div class="chips-grid three">
		<?php foreach ( array_slice( $a['gap']['top'], 0, 18 ) as $g ) : ?>
			<div class="chip-kw i-<?php echo esc_attr( $g['i'] ); ?>"><span><?php echo esc_html( $g['w'] ); ?></span><b><?php echo esc_html( ost_num( $g['v'] ) ); ?></b><small><?php echo esc_html( implode( '، ', array_slice( $g['by'], 0, 2 ) ) ); ?></small></div>
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
			$offs = array_filter( $a['offpage'], function ( $o ) {
				return ! empty( $o['reportage']['available'] ) || ! empty( $o['backlinks']['available'] );
			} );
			foreach ( $offs as $d => $o ) {
				$rows[] = array( 'label' => $d, 'value' => $o['quality_reportages'], 'hl' => $d === $cd );
			}
			$o_bl    = (bool) array_filter( array_map( function ( $o ) {
				return $o['backlinks']['refdomains'] ?? 0;
			}, $offs ) );
			$o_spend = (bool) array_filter( array_column( array_column( $offs, 'reportage' ), 'spend12' ) );
			$o_media = (bool) array_filter( array_column( array_column( $offs, 'reportage' ), 'media' ) );
			echo ost_chart_hbar( $rows, 520, 'ost_num' ); // phpcs:ignore
			?>
		</div>
		<div class="card">
			<table class="tbl sm">
				<thead><tr><th>سایت</th><th>کل رپورتاژ</th><th>۱۲ ماه اخیر</th><?php echo $o_media ? '<th>رسانه</th>' : ''; ?><th>باکیفیت</th><?php echo $o_bl ? '<th>دامنه ارجاع‌دهنده باکیفیت</th>' : ''; ?><?php echo $o_spend ? '<th>هزینه تخمینی ۱۲ ماه</th>' : ''; ?></tr></thead>
				<tbody>
				<?php foreach ( $offs as $d => $o ) : $r = $o['reportage']; $b = $o['backlinks']; ?>
					<tr class="<?php echo $d === $cd ? 'me' : ''; ?>">
						<td><b><?php echo esc_html( $d ); ?></b></td>
						<td class="n"><?php echo esc_html( $r['available'] ? ost_num( $r['total'] ) : '—' ); ?></td>
						<td class="n"><?php echo esc_html( $r['available'] ? ost_num( $r['last12'] ) : '—' ); ?></td>
						<?php if ( $o_media ) : ?><td class="n"><?php echo esc_html( $r['available'] ? ost_num( $r['media'] ) : '—' ); ?></td><?php endif; ?>
						<td class="n"><b><?php echo esc_html( ost_num( $o['quality_reportages'] ) ); ?></b></td>
						<?php if ( $o_bl ) : ?><td class="n"><?php echo esc_html( ! empty( $b['available'] ) ? ost_num( $b['quality_refdomains'] ) . ' از ' . ost_num( $b['refdomains'] ) : '—' ); ?></td><?php endif; ?>
						<?php if ( $o_spend ) : ?><td class="n"><?php echo esc_html( $r['spend12'] ? ost_toman( $r['spend12'], true ) : '—' ); ?></td><?php endif; ?>
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
						echo '<tr><td class="url">' . esc_html( ost_short_url( ost_path( $pg['url'] ), 30 ) ) . '</td><td class="n">' . esc_html( ost_num( $pg['reportage'] ) ) . '</td><td class="n">' . esc_html( ost_num( $pg['domains'] ) ) . '</td></tr>';
					}
				} else {
					foreach ( array_slice( $o['reportage']['pages'], 0, 5 ) as $pg ) {
						echo '<tr><td class="url">' . esc_html( ost_short_url( ost_path( $pg['url'] ), 30 ) ) . '</td><td class="n">' . esc_html( ost_num( $pg['n'] ) ) . '</td><td class="n">—</td></tr>';
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
			<?php echo ost_chart_ring( $te['score'] ?? 0, 170, $te['grade'] ?? '' ); // phpcs:ignore ?>
			<p class="muted"><?php echo esc_html( $focus ); ?><br><?php echo esc_html( ! empty( $te['has_sf'] ) ? 'بر اساس خزش ' . ost_num( $te['metrics']['crawled'] ) . ' آدرس با اسکریمینگ فراگ' : 'بر اساس بررسی سریع نمونه صفحات' ); ?></p>
			<?php if ( ! empty( $te['good'] ) ) : ?><ul class="good"><?php foreach ( array_slice( $te['good'], 0, 3 ) as $g ) { echo '<li>' . esc_html( $g ) . '</li>'; } ?></ul><?php endif; ?>
		</div>
		<div class="card">
			<h3>مهم‌ترین موارد</h3>
			<table class="tbl sm issues"><thead><tr><th>مورد</th><th>اهمیت</th><th>تعداد</th></tr></thead><tbody>
			<?php foreach ( array_slice( (array) ( $te['issues'] ?? array() ), 0, 9 ) as $i ) : ?>
				<tr><td><?php echo esc_html( $i['label'] ); ?></td><td><span class="sev sev-<?php echo esc_attr( $i['sev'] ); ?>"><?php echo esc_html( ost_sev_label( $i['sev'] ) ); ?></span></td><td class="n"><?php echo esc_html( ost_num( $i['count'] ) . ( $i['total'] > 1 ? ' از ' . ost_num( $i['total'] ) : '' ) ); ?></td></tr>
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
	<?php
	$techs  = array_filter( $a['technical'], function ( $te ) {
		return null !== ( $te['score'] ?? null );
	} );
	$has_mt = function ( $fn ) use ( $techs ) {
		return (bool) array_filter( $techs, $fn );
	};
	$t_sf    = $has_mt( function ( $te ) {
		return ! empty( $te['has_sf'] );
	} );
	$t_cms   = $has_mt( function ( $te ) {
		return ! empty( $te['metrics']['cms'] ) && false === mb_strpos( $te['metrics']['cms'], 'نامشخص' );
	} );
	$t_speed = $has_mt( function ( $te ) {
		return ! empty( $te['metrics']['home_ms'] ) && $te['metrics']['home_ms'] >= 50;
	} );
	$t_schema = $has_mt( function ( $te ) {
		return ! empty( $te['metrics']['sample'] );
	} );
	$t_depth = $has_mt( function ( $te ) {
		return ! empty( $te['metrics']['avg_depth'] );
	} );
	?>
	<table class="tbl cmp">
		<thead><tr><th>سایت</th><th>امتیاز فنی</th><?php echo $t_cms ? '<th>پلتفرم</th>' : ''; ?><th>HTTPS</th><th>نقشه سایت</th><?php echo $t_schema ? '<th>اسکیما در نمونه‌ها</th>' : ''; ?><?php echo $t_speed ? '<th>سرعت صفحه اصلی</th>' : ''; ?><?php echo $t_sf ? '<th>خطای ۴xx</th><th>لینک شکسته</th>' : ''; ?><?php echo $t_depth ? '<th>عمق میانگین</th>' : ''; ?></tr></thead>
		<tbody>
		<?php foreach ( $techs as $d => $te ) : $mt = $te['metrics']; ?>
			<tr class="<?php echo $d === $cd ? 'me' : ''; ?>">
				<td><b><?php echo esc_html( $d ); ?></b></td>
				<td class="n"><b><?php echo esc_html( ost_num( $te['score'] ) ); ?></b></td>
				<?php if ( $t_cms ) : ?><td><?php echo esc_html( $mt['cms'] ?? '—' ); ?></td><?php endif; ?>
				<td><?php echo ! empty( $mt['https'] ) ? '✓' : '✗'; ?></td>
				<td><?php echo ! empty( $mt['sitemap'] ) ? '✓ ' . esc_html( ! empty( $mt['pages'] ) ? ost_num( $mt['pages'] ) : '' ) : '✗'; ?></td>
				<?php if ( $t_schema ) : ?><td class="n"><?php echo esc_html( ! empty( $mt['sample'] ) ? ost_num( $mt['schema'] ) . ' از ' . ost_num( $mt['sample'] ) : '—' ); ?></td><?php endif; ?>
				<?php if ( $t_speed ) : ?><td class="n"><?php echo esc_html( ! empty( $mt['home_ms'] ) && $mt['home_ms'] >= 50 ? ost_num( $mt['home_ms'] / 1000, 1 ) . ' ث' : '—' ); ?></td><?php endif; ?>
				<?php if ( $t_sf ) : ?><td class="n"><?php echo esc_html( ! empty( $te['has_sf'] ) ? ost_num( $mt['e4xx'] ) : '—' ); ?></td><td class="n"><?php echo esc_html( ! empty( $te['has_sf'] ) ? ost_num( $mt['broken'] ) : '—' ); ?></td><?php endif; ?>
				<?php if ( $t_depth ) : ?><td class="n"><?php echo esc_html( ! empty( $mt['avg_depth'] ) ? ost_num( $mt['avg_depth'], 1 ) : '—' ); ?></td><?php endif; ?>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php if ( $t_sf ) : ?><p class="note">ستون‌های ۴xx و لینک شکسته از خزش اسکریمینگ فراگ صفحات مهم هر سایت است (— یعنی آن سایت خزیده نشده).</p><?php endif; ?>
<?php $close(); endif; ?>

<?php if ( $on( 'content' ) ) : $close = $open( $section['audit'] . ' · محتوا', 'محتوایی که مشتری دنبالش است', $t['content'] ?? '' ); ?>
	<?php
	$qs = array_filter( (array) $m['questions'], function ( $q ) {
		return $q['v'] > 0;
	} );
	$qs = count( $qs ) >= 3 ? $qs : array(); // One or two questions are not worth a card.
	?>
	<div class="grid <?php echo $qs ? 'g-2' : 'g-1'; ?>">
		<?php if ( $qs ) : ?>
		<div class="card">
			<h3>سؤال‌های پرجستجوی مشتریان</h3>
			<ol class="qs">
				<?php foreach ( array_slice( $qs, 0, 10 ) as $q ) : ?>
					<li><span><?php echo esc_html( $q['w'] ); ?></span><b><?php echo esc_html( ost_num( $q['v'] ) ); ?></b></li>
				<?php endforeach; ?>
			</ol>
		</div>
		<?php endif; ?>
		<div class="tiles <?php echo $qs ? 't2' : 't4'; ?>">
			<?php
			$tile( ost_num( $pkg['qty']['landings'] ), 'صفحه فرود / دسته', 'برای کلمات خرید' );
			$tile( ost_num( $pkg['qty']['articles'] ), 'مقاله تخصصی', 'در ' . ost_num( $months - 1 ) . ' ماه', 'hl' );
			$tile( ost_num( $pkg['qty']['updates'] ), 'به‌روزرسانی صفحه', 'صفحات نزدیک صفحه اول' );
			$tile( ost_num( count( $m['clusters'] ) ), 'خوشه موضوعی', 'هر خوشه یک صفحه اصلی + مقالات پشتیبان' );
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

<?php if ( $on( 'div_plan' ) ) { $divider( $section['plan'], 'استراتژی و نقشه راه', 'از کجا شروع می‌کنیم، چقدر طول می‌کشد و چه چیزی به دست می‌آید' ); } ?>

<?php
if ( $on( 'opps' ) ) :
	$op     = $a['opps_ai'];
	$main   = (array) $op['main'];
	$angles = array( 'کم‌رقابت' => 'a-easy', 'زودبازده' => 'a-fast', 'سودآور' => 'a-profit', 'شکاف رقبا' => 'a-gap', 'جایگاه فعلی شما' => 'a-foot' );
	$cards  = array_slice( (array) $op['items'], 0, 4 );
	$close  = $open( $section['plan'] . ' · فرصت‌های بازار', 'پیشنهاد اصلی ما و فرصت‌های بازار شما', '', 's-opps' );
	?>
	<?php if ( ! empty( $main['title'] ) ) : ?>
		<div class="main-rec">
			<span class="tag">پیشنهاد اصلی</span>
			<b><?php echo esc_html( $main['title'] ); ?></b>
			<?php if ( ! empty( $main['text'] ) ) : ?><p><?php echo esc_html( $main['text'] ); ?></p><?php endif; ?>
		</div>
	<?php endif; ?>
	<div class="opp-cards n<?php echo (int) count( $cards ); ?>">
		<?php foreach ( $cards as $c ) : $cls = $angles[ trim( (string) $c['angle'] ) ] ?? 'a-fast'; ?>
			<div class="opp-card <?php echo esc_attr( $cls ); ?>">
				<span class="ang"><?php echo esc_html( $c['angle'] ); ?></span>
				<b><?php echo esc_html( $c['title'] ); ?></b>
				<?php if ( ! empty( $c['keywords'] ) ) : ?><div class="kws"><?php foreach ( array_slice( (array) $c['keywords'], 0, 4 ) as $w ) { echo '<i>' . esc_html( $w ) . '</i>'; } ?></div><?php endif; ?>
				<p><?php echo esc_html( $c['why'] ); ?></p>
				<?php if ( ! empty( $c['potential'] ) ) : ?><em><?php echo esc_html( $c['potential'] ); ?></em><?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
<?php $close(); endif; ?>

<?php if ( $on( 'strategy' ) && ! empty( $sg['picks'] ) ) : $close = $open( $section['plan'] . ' · استراتژی شروع', 'استراتژی شروع: ' . $sg['name'], $sg['idea'], 's-strategy' ); ?>
	<div class="grid g-strat">
		<div class="card">
			<h3>نقشه فرصت‌های بازار شما <small>هر دایره یک صفحه قابل‌برد؛ اندازه = جستجوی خرید</small></h3>
			<?php echo ost_chart_matrix( $sg['all'], $sg['picks'], 640, 400 ); // phpcs:ignore ?>
		</div>
		<ol class="opps">
			<?php foreach ( $sg['picks'] as $i => $o ) : ?>
				<li class="<?php echo 0 === $i ? 'first' : ''; ?>">
					<span class="on"><?php echo esc_html( ost_fa( $i + 1 ) ); ?></span>
					<div>
						<b><?php echo esc_html( $o['label'] ); ?></b>
						<small><?php echo esc_html( ost_opp_type_label( $o['type'] ) . ' · ' . ost_num( $o['volume'] ) . ' جستجو · ' . ost_pct( $o['buyer_share'] ) . ' خرید' ); ?></small>
					</div>
					<em><?php echo esc_html( ost_num( $o['months'][0] ) . ' تا ' . ost_num( $o['months'][1] ) ); ?><i>ماه</i></em>
				</li>
			<?php endforeach; ?>
		</ol>
	</div>
<?php $close(); endif; ?>

<?php if ( $on( 'strategy_plan' ) && ! empty( $sg['picks'] ) ) : $o1 = $sg['picks'][0]; $close = $open( $section['plan'] . ' · ۹۰ روز اول', 'نقطه شروع: «' . $o1['label'] . '»', $t['strategy'] ?? '' ); ?>
	<div class="grid g-hero">
		<div class="hero-opp">
			<span class="tag"><?php echo esc_html( ost_opp_type_label( $o1['type'] ) ); ?></span>
			<ul class="why"><?php foreach ( array_slice( $o1['reasons'], 0, 4 ) as $r ) { echo '<li>' . esc_html( $r ) . '</li>'; } ?></ul>
			<div class="hero-kpi">
				<div><b><?php echo esc_html( ost_compact( $o1['volume'] ) ); ?></b><span>جستجوی ماهانه</span></div>
				<div><b><?php echo esc_html( ost_num( $o1['months'][0] ) . ' تا ' . ost_num( $o1['months'][1] ) ); ?></b><span>ماه تا نتیجه</span></div>
				<div><b><?php echo esc_html( ost_num( $o1['clicks'] ) ); ?></b><span>کلیک ماهانه پس از برد</span></div>
			</div>
		</div>
		<div class="days90">
			<?php foreach ( $sg['plan'] as $st ) : ?>
				<div class="d"><small><?php echo esc_html( $st['when'] ); ?></small><b><?php echo esc_html( $st['title'] ); ?></b><ul><?php foreach ( $st['items'] as $it ) { echo '<li>' . esc_html( $it ) . '</li>'; } ?></ul></div>
			<?php endforeach; ?>
			<div class="d90-res"><span>پایان ۹۰ روز</span><b><?php echo esc_html( ost_num( $sg['day90'][0] ) . ' تا ' . ost_num( $sg['day90'][1] ) ); ?></b><small>کلیک ماهانه تازه از همین فرصت‌ها<?php echo $sg['revenue']['high'] > 0 ? ' · پس از تثبیت حدود ' . esc_html( ost_compact( $sg['revenue']['low'] ) . ' تا ' . ost_compact( $sg['revenue']['high'] ) ) . ' تومان فروش ماهانه' : ''; ?></small></div>
		</div>
	</div>
<?php $close(); endif; ?>

<?php if ( $on( 'roadmap' ) ) : $close = $open( $section['plan'] . ' · نقشه راه', 'نقشه راه ' . ost_num( $months ) . ' ماهه', 'سئو یک مسیر مرحله‌ای است؛ هر مرحله پایه مرحله بعد است.' ); ?>
	<div class="timeline">
		<?php foreach ( $a['roadmap'] as $i => $ph ) : ?>
			<div class="phase">
				<div class="ph-head"><span class="ph-n"><?php echo esc_html( ost_fa( $i + 1 ) ); ?></span><div><b><?php echo esc_html( $ph['label'] ); ?></b><small><?php echo esc_html( $ph['when'] ); ?></small></div></div>
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
		<thead><tr><th>اقدام</th><?php for ( $mm = 1; $mm <= $months; $mm++ ) { echo '<th>' . esc_html( ost_fa( $mm ) ) . '</th>'; } ?><th>جمع</th></tr></thead>
		<tbody>
			<tr><td>رفع مشکلات فنی و پایه‌گذاری</td><?php for ( $mm = 1; $mm <= $months; $mm++ ) { echo '<td>' . ( 1 === $mm ? '<i class="on"></i>' : ( $mm <= 3 ? '<i class="half"></i>' : '' ) ) . '</td>'; } ?><td class="n">ماه ۱</td></tr>
			<?php foreach ( $items as $k => $lab ) : if ( empty( $pkg['qty'][ $k ] ) ) { continue; } ?>
				<tr><td><?php echo esc_html( $lab[0] ); ?></td>
				<?php for ( $mm = 1; $mm <= $months; $mm++ ) { $v = (int) ( $pkg['plan'][ $mm ][ $k ] ?? 0 ); echo '<td class="n">' . ( $v ? esc_html( ost_fa( $v ) ) : '<span class="z">·</span>' ) . '</td>'; } ?>
				<td class="n"><b><?php echo esc_html( ost_num( $pkg['qty'][ $k ] ) ); ?></b></td></tr>
			<?php endforeach; ?>
			<tr><td>گزارش ماهانه و جلسه مرور</td><?php for ( $mm = 1; $mm <= $months; $mm++ ) { echo '<td><i class="on"></i></td>'; } ?><td class="n"><?php echo esc_html( ost_num( $months ) ); ?></td></tr>
		</tbody>
	</table>
<?php $close(); endif; ?>

<?php if ( $on( 'forecast' ) ) : $close = $open( $section['plan'] . ' · نتیجه', 'نتیجه واقع‌بینانه در ' . ost_num( $months ) . ' ماه', 'پیش‌بینی بر اساس سهمی است که رقبا همین امروز از این بازار دارند؛ یعنی دست‌یافتنی است.' ); ?>
	<div class="grid g-fc">
		<div class="card">
			<h3>کلیک غیربرندی ماهانه از گوگل</h3>
			<?php echo ost_chart_forecast( $f['curve'], $f['baseline'], 760, 300 ); // phpcs:ignore ?>
			<p class="legend"><i class="ln"></i> پیش‌بینی میانی <i class="bd"></i> بازه محتاطانه تا خوش‌بینانه <i class="dash"></i> وضعیت امروز</p>
		</div>
		<div class="tiles t1">
			<?php
			$tile( ost_num( $f['scenarios']['low'] ) . ' تا ' . ost_num( $f['scenarios']['high'] ), 'کلیک ماهانه در پایان دوره', 'سناریوی محتاطانه تا خوش‌بینانه', 'hl' );
			$tile( ost_num( $f['keywords']['low'] ) . ' تا ' . ost_num( $f['keywords']['high'] ), 'کلمه پول‌ساز در صفحه اول', 'از ' . ost_num( $f['keywords']['of'] ) . ' کلمه هدف' );
			$rv = $f['revenue'];
			$tile( $rv['high'] > 0 ? $range( $rv ) . ' تومان' : ost_num( $rv['orders'] ) . ( 'leads' === $rv['basis'] ? ' سرنخ' : ' سفارش' ), $rv['high'] > 0 ? 'فروش ماهانه از گوگل (تخمین)' : ( 'leads' === $rv['basis'] ? 'سرنخ ماهانه از گوگل' : 'سفارش ماهانه از گوگل' ), 'ارزش تبلیغاتی معادل: ' . ost_toman( $rv['value'], true ) . ' در ماه' );
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
				<div class="price"><b><?php echo esc_html( ost_compact( $pk['monthly'] ) ); ?></b><span>تومان در ماه (میانگین، همه هزینه‌ها)</span></div>
				<ul>
					<?php foreach ( $items as $ik => $lab ) : if ( empty( $pk['qty'][ $ik ] ) ) { continue; } ?>
						<li><b><?php echo esc_html( ost_num( $pk['qty'][ $ik ] ) ); ?></b> <?php echo esc_html( $lab[0] ); ?></li>
					<?php endforeach; ?>
				</ul>
				<p class="pk-res">پیش‌بینی: <b><?php echo esc_html( ost_num( $fc['low'] ?? 0 ) . ' تا ' . ost_num( $fc['high'] ?? 0 ) ); ?></b> کلیک ماهانه</p>
				<p class="pk-total">جمع <?php echo esc_html( ost_num( $months ) ); ?> ماه: <?php echo esc_html( ost_toman( $pk['total'], true ) ); ?></p>
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
					<tr><td><?php echo esc_html( $items[ $k ][0] ); ?></td><td class="n"><?php echo esc_html( ost_num( $pkg['qty'][ $k ] ) ); ?></td><td class="n"><?php echo esc_html( ost_compact( $bud['prices'][ $k ] ) ); ?></td><td class="n"><?php echo esc_html( ost_compact( $pkg['lines'][ $k ] ) ); ?></td><td class="why"><?php echo esc_html( $bud['why'][ $k ] ?? '' ); ?></td></tr>
				<?php endforeach; ?>
				<tr class="grp"><td colspan="5">۲. بودجه اعتبار (آف‌پیج) — پرداخت مستقیم شما در پنل رپورتاژ (مثل تریبون، تسمینو)</td></tr>
				<?php foreach ( array( 'reportages', 'backlinks' ) as $k ) : if ( empty( $pkg['qty'][ $k ] ) ) { continue; } ?>
					<tr><td><?php echo esc_html( $items[ $k ][0] ); ?></td><td class="n"><?php echo esc_html( ost_num( $pkg['qty'][ $k ] ) ); ?></td><td class="n"><?php echo esc_html( ost_compact( $bud['prices'][ $k ] ) ); ?></td><td class="n"><?php echo esc_html( ost_compact( $pkg['lines'][ $k ] ) ); ?></td><td class="why"><?php echo esc_html( $bud['why'][ $k ] ?? '' ); ?></td></tr>
				<?php endforeach; ?>
				<tr class="grp"><td colspan="5">۳. دستمزد تیم سئو — استراتژی، اجرای فنی، مدیریت محتوا و لینک، گزارش</td></tr>
				<tr><td>دستمزد ماهانه</td><td class="n"><?php echo esc_html( ost_num( $months ) ); ?> ماه</td><td class="n"><?php echo esc_html( ost_compact( $pkg['fee'] ) ); ?></td><td class="n"><?php echo esc_html( ost_compact( $pkg['fees'] ) ); ?></td><td class="why">ثابت در طول دوره</td></tr>
			</tbody>
		</table>
		<div class="tiles t1">
			<?php
			$tile( ost_toman( $pkg['total'], true ), 'جمع کل دوره', 'میانگین ماهانه ' . ost_toman( $pkg['monthly'], true ), 'hl' );
			$tile( ost_toman( $pkg['direct'], true ), 'بودجه محتوا و آف‌پیج', ost_pct( $pkg['direct'] / max( 1, $pkg['total'] ) ) . ' — مستقیم توسط شما پرداخت می‌شود' );
			$tile( ost_toman( $pkg['fees'], true ), 'دستمزد تیم سئو', ost_pct( $pkg['fees'] / max( 1, $pkg['total'] ) ) . ' از کل' );
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

<?php if ( $on( 'team' ) ) : $close = $open( 'تیم شما', 'چه کسانی روی پروژه شما کار می‌کنند', $who['tagline'] ); ?>
	<div class="grid g-team">
		<div class="card person">
			<?php if ( $who['photo'] ) : ?><img class="ph" src="<?php echo esc_attr( $args['inline'] ? ost_deck_image( $who['photo'] ) : $who['photo'] ); ?>" alt=""><?php else : ?><span class="ph ini"><?php echo esc_html( $initial( $who['name'] ) ); ?></span><?php endif; ?>
			<b><?php echo esc_html( $who['name'] ); ?></b>
			<span><?php echo esc_html( $who['title'] . '، ' . $agency ); ?></span>
			<p><?php echo esc_html( $who['bio'] ); ?></p>
			<div class="ct">
				<?php foreach ( array( $who['phone'], $who['email'] ) as $ct ) { echo $ct ? '<bdi>' . esc_html( $ct ) . '</bdi>' : ''; } ?>
			</div>
		</div>
		<div>
			<?php if ( $who['stats'] ) : ?>
				<div class="tiles t3">
					<?php foreach ( array_slice( $who['stats'], 0, 3 ) as $i => $st ) { $tile( $st[1], $st[0], '', 0 === $i ? 'hl' : '' ); } ?>
				</div>
			<?php endif; ?>
			<div class="team-list">
				<?php foreach ( array_slice( $who['team'], 0, 6 ) as $m2 ) : ?>
					<div><span class="ini sm"><?php echo esc_html( $initial( $m2[0] ) ); ?></span><b><?php echo esc_html( $m2[0] ); ?></b><small><?php echo esc_html( $m2[1] ); ?></small></div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
<?php $close(); endif; ?>

<?php if ( $on( 'portfolio' ) ) : $close = $open( 'نمونه‌کار', 'نتایجی که پیش‌تر ساخته‌ایم', ost_setting( 'agency_about' ) ); ?>
	<div class="grid g-3">
		<?php foreach ( array_slice( ost_portfolio(), 0, 6 ) as $pf ) : ?>
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
			<?php foreach ( ost_lines( $t['next_steps'] ?? '' ) as $s ) { echo '<li>' . esc_html( $s ) . '</li>'; } ?>
		</ol>
	</div>
	<div class="cl-contact">
		<div class="cover-logo sm"><img src="<?php echo esc_attr( $assets['logo'] ); ?>" alt=""></div>
		<b><?php echo esc_html( $who['name'] ); ?></b>
		<span><?php echo esc_html( $who['title'] . '، ' . $agency ); ?></span>
		<?php foreach ( array_unique( array_filter( array( $who['phone'], $who['email'], $who['agency_phone'], $who['site'], $who['instagram'], $who['address'] ) ) ) as $ct ) : ?>
			<span class="ct"><?php echo esc_html( $ct ); ?></span>
		<?php endforeach; ?>
		<?php if ( $who['number'] ) : ?><span class="num">پروپوزال <bdi><?php echo esc_html( $who['number'] ); ?></bdi><?php echo $who['valid_until'] ? ' · معتبر تا ' . esc_html( $who['valid_until'] ) : ''; ?></span><?php endif; ?>
	</div>
	<span class="pg light"><?php echo esc_html( ost_fa( $page ) ); ?></span>
</section>
<?php endif; ?>

</main>
<?php if ( $args['print'] ) : ?>
<script>document.fonts.ready.then(function(){setTimeout(function(){window.print();},400);});</script>
<?php endif; ?>
<?php if ( $args['web'] || $args['download'] ) : ?>
<div class="pdf-box" id="pdf-box" hidden>
	<div class="pdf-card">
		<b data-pdf-title>در حال ساخت PDF…</b>
		<div class="pdf-bar"><i data-pdf-bar></i></div>
		<small data-pdf-note>اسلایدها یکی‌یکی آماده می‌شوند؛ این صفحه را نبندید.</small>
		<div class="pdf-act" data-pdf-act hidden>
			<button type="button" data-make-pdf>دانلود دوباره</button>
			<button type="button" class="ghost" data-pdf-close>بستن</button>
		</div>
	</div>
</div>
<script src="<?php echo esc_url( OST_URL . 'assets/vendor/html-to-image.min.js?ver=1.11.11' ); ?>"></script>
<script src="<?php echo esc_url( OST_URL . 'assets/vendor/jspdf.umd.min.js?ver=2.5.1' ); ?>"></script>
<script>
/* Build the PDF in the browser: every slide is rendered by the browser itself (exact Persian
   shaping and fonts), turned into an image and placed on a 16:9 page; the file then downloads. */
( function () {
	var box = document.getElementById( 'pdf-box' ), busy = false;
	var q = function ( s ) { return box.querySelector( s ); };
	var fa = function ( n ) { return String( n ).replace( /\d/g, function ( d ) { return '۰۱۲۳۴۵۶۷۸۹'[ d ]; } ); };
	function make() {
		if ( busy ) { return; }
		if ( ! window.htmlToImage || ! window.jspdf ) { window.print(); return; }
		busy = true;
		box.hidden = false;
		q( '[data-pdf-act]' ).hidden = true;
		q( '[data-pdf-title]' ).textContent = 'در حال ساخت PDF…';
		q( '[data-pdf-note]' ).textContent = 'اسلایدها یکی‌یکی آماده می‌شوند؛ این صفحه را نبندید.';
		document.body.classList.add( 'is-rendering' );
		var slides = Array.prototype.slice.call( document.querySelectorAll( '.slide' ) );
		var pdf = new window.jspdf.jsPDF( { orientation: 'landscape', unit: 'px', format: [ 1280, 720 ], hotfixes: [ 'px_scaling' ], compress: true } );
		var opts = { width: 1280, height: 720, pixelRatio: 1.75, quality: 0.9, backgroundColor: '#ffffff', cacheBust: false, style: { margin: '0', zoom: '1', boxShadow: 'none', borderRadius: '0' } };
		document.fonts.ready.then( function () {
			return window.htmlToImage.getFontEmbedCSS( document.body );
		} ).then( function ( css ) {
			opts.fontEmbedCSS = css;
			var i = 0;
			function next() {
				if ( i >= slides.length ) { return Promise.resolve(); }
				q( '[data-pdf-bar]' ).style.width = Math.round( 100 * i / slides.length ) + '%';
				q( '[data-pdf-title]' ).textContent = 'در حال ساخت PDF… اسلاید ' + fa( i + 1 ) + ' از ' + fa( slides.length );
				return window.htmlToImage.toJpeg( slides[ i ], opts ).then( function ( img ) {
					if ( i > 0 ) { pdf.addPage( [ 1280, 720 ], 'landscape' ); }
					pdf.addImage( img, 'JPEG', 0, 0, 1280, 720, undefined, 'FAST' );
					i++;
					return next();
				} );
			}
			return next();
		} ).then( function () {
			pdf.save( document.body.dataset.file || 'seo-proposal.pdf' );
			q( '[data-pdf-bar]' ).style.width = '100%';
			q( '[data-pdf-title]' ).textContent = '✓ PDF دانلود شد';
			q( '[data-pdf-note]' ).textContent = 'فایل در پوشه دانلودهای مرورگر ذخیره شد.';
			q( '[data-pdf-act]' ).hidden = false;
		} ).catch( function ( e ) {
			q( '[data-pdf-title]' ).textContent = 'ساخت PDF در این مرورگر ممکن نشد';
			q( '[data-pdf-note]' ).textContent = 'از «دانلود دوباره» یا چاپ مرورگر (Ctrl+P ← Save as PDF) استفاده کنید. ' + ( e && e.message ? e.message : '' );
			q( '[data-pdf-act]' ).hidden = false;
		} ).then( function () {
			busy = false;
			document.body.classList.remove( 'is-rendering' );
		} );
	}
	document.addEventListener( 'click', function ( e ) {
		if ( e.target.closest( '[data-make-pdf]' ) ) { make(); }
		if ( e.target.closest( '[data-pdf-close]' ) ) { box.hidden = true; }
	} );
	if ( document.body.classList.contains( 'is-download' ) ) {
		window.addEventListener( 'load', function () { setTimeout( make, 300 ); } );
	}
}() );
</script>
<?php endif; ?>
</body>
</html>
