<?php
/**
 * Proposal workspace with tabs.
 *
 * @var array  $p
 * @var string $tab
 * @package Fanoos
 */

defined( 'ABSPATH' ) || exit;

$id   = $p['id'];
$in   = $p['inputs'];
$a    = fns_data( $id, 'analysis' );
$st   = fns_status_label( $p['status'] );
$job  = (array) $p['job'];
$tabs = array(
	'summary'     => array( 'خلاصه و اجرا', 'play' ),
	'market'      => array( 'بازار و کلمات', 'search' ),
	'competitors' => array( 'رقبا', 'users' ),
	'offpage'     => array( 'آف‌پیج', 'link' ),
	'technical'   => array( 'تکنیکال', 'tool' ),
	'costs'       => array( 'هزینه و نقشه راه', 'wallet' ),
	'texts'       => array( 'متن‌ها', 'text' ),
	'export'      => array( 'خروجی PDF', 'download' ),
);
if ( ! isset( $tabs[ $tab ] ) ) {
	$tab = 'summary';
}
$url  = function ( $t ) use ( $id ) {
	return fns_admin_url( 'proposal', array( 'id' => $id, 'tab' => $t ) );
};
$need = function () use ( $a, $url ) {
	if ( ! $a ) {
		echo '<div class="fns-empty sm"><p>هنوز تحلیلی انجام نشده است.</p><a class="fns-btn primary" href="' . esc_url( $url( 'summary' ) ) . '">شروع تحلیل</a></div>';
		return true;
	}
	return false;
};
$cd = $a['client_domain'] ?? '';
?>
<div class="fns-bar">
	<div>
		<h1><?php echo esc_html( $p['title'] ); ?> <span class="fns-pill <?php echo esc_attr( $st[1] ); ?>"><?php echo esc_html( $st[0] ); ?></span></h1>
		<p class="fns-muted"><?php echo esc_html( $in['domain'] ?: 'بدون سایت' ); ?> · <?php echo esc_html( fns_business_types()[ $in['business_type'] ] ); ?> · دوره <?php echo esc_html( fns_num( $in['months'] ) ); ?> ماه</p>
	</div>
	<div class="fns-bar-act">
		<a class="fns-btn" href="<?php echo esc_url( fns_admin_url( 'edit', array( 'id' => $id ) ) ); ?>">ویرایش ورودی‌ها</a>
		<?php if ( $a ) : ?>
			<a class="fns-btn" target="_blank" href="<?php echo esc_url( fns_action_url( 'fns_pdf', array( 'id' => $id, 'preview' => 1 ) ) ); ?>"><?php echo fns_icon( 'eye', 16 ); // phpcs:ignore ?> پیش‌نمایش</a>
			<a class="fns-btn primary" target="_blank" href="<?php echo esc_url( fns_action_url( 'fns_pdf', array( 'id' => $id ) ) ); ?>"><?php echo fns_icon( 'download', 16 ); // phpcs:ignore ?> دانلود PDF</a>
		<?php endif; ?>
	</div>
</div>

<nav class="fns-tabs">
	<?php foreach ( $tabs as $k => $t ) : ?>
		<a href="<?php echo esc_url( $url( $k ) ); ?>" class="<?php echo $tab === $k ? 'on' : ''; ?>"><?php echo fns_icon( $t[1], 16 ); // phpcs:ignore ?><?php echo esc_html( $t[0] ); ?></a>
	<?php endforeach; ?>
</nav>

<?php if ( 'summary' === $tab ) : $cost = fns_ss_cost_estimate( $p ); ?>
	<div class="fns-grid g-side">
		<section class="fns-panel">
			<h2>اجرای تحلیل</h2>
			<p class="fns-muted">هر مرحله جداگانه اجرا می‌شود تا هیچ درخواستی به محدودیت زمان سرور نخورد. پاسخ‌های سئو سیگنال ۲۰ روز کش می‌شوند؛ اجرای دوباره هزینه سهمیه ندارد.</p>
			<div class="fns-run" id="fns-run" data-id="<?php echo (int) $id; ?>" data-status="<?php echo esc_attr( $job['status'] ?? '' ); ?>" data-autorun="<?php echo ! empty( $_GET['autorun'] ) ? 1 : 0; // phpcs:ignore ?>">
				<div class="fns-run-act">
					<button class="fns-btn primary" data-run="1"><?php echo fns_icon( 'play', 16 ); // phpcs:ignore ?> <?php echo $a ? 'به‌روزرسانی تحلیل' : 'شروع تحلیل'; ?></button>
					<?php if ( $a ) : ?><button class="fns-btn" data-run="fresh">تحلیل کامل از نو</button><?php endif; ?>
					<?php if ( 'paused' === ( $job['status'] ?? '' ) ) : ?><button class="fns-btn amber" data-resume="1">ادامه تحلیل</button><?php endif; ?>
					<span class="fns-muted">سهمیه امروز سئو سیگنال: <?php echo esc_html( fns_num( fns_ss_remaining() ) ); ?> · این پروپوزال حداکثر <?php echo esc_html( fns_num( $cost['total'] ) ); ?></span>
				</div>
				<div class="fns-progress"><i style="width:0"></i></div>
				<ol class="fns-steps">
					<?php foreach ( (array) ( $job['steps'] ?? array() ) as $k => $s ) : ?>
						<li class="<?php echo esc_attr( $s['status'] ); ?>"><b><?php echo esc_html( $s['label'] ); ?></b><small><?php echo esc_html( $s['msg'] ); ?></small></li>
					<?php endforeach; ?>
				</ol>
				<?php if ( ! empty( $job['msg'] ) ) : ?><p class="fns-notice amber"><?php echo esc_html( $job['msg'] ); ?></p><?php endif; ?>
			</div>
		</section>
		<aside class="fns-panel">
			<h2>منابع داده</h2>
			<ul class="fns-sources">
				<li class="<?php echo fns_ss_connected() ? 'ok' : 'off'; ?>">سئو سیگنال — کلمات، رقبا، شکاف کلمات، رپورتاژها</li>
				<li class="<?php echo fns_semrush_connected() ? 'ok' : 'off'; ?>">سمراش — بک‌لینک و دامنه‌های ارجاع‌دهنده <?php echo fns_semrush_connected() ? '' : '<small>(متصل نیست؛ آف‌پیج از رپورتاژهای سئو سیگنال)</small>'; ?></li>
				<li class="<?php echo $in['gsc_access'] && fns_google_connected() ? 'ok' : 'off'; ?>">سرچ کنسول کارفرما <?php echo $in['gsc_access'] ? '' : '<small>(دسترسی داده نشده)</small>'; ?></li>
				<li class="ok">بررسی فنی سریع (نقشه سایت، robots، نمونه صفحات)</li>
				<li class="<?php echo fns_data( $id, 'sf' ) ? 'ok' : 'off'; ?>">اسکریمینگ فراگ <small>(از تب تکنیکال)</small></li>
				<li class="<?php echo fns_ai_connected() ? 'ok' : 'off'; ?>">هوش مصنوعی — نوشتن متن‌ها <?php echo fns_ai_via_sokan() ? '<small>(تنظیمات سکان)</small>' : ''; ?></li>
			</ul>
		</aside>
	</div>
	<?php if ( $a ) : $pk = $a['budget']['packages'][ $a['budget']['selected'] ]; $f = $a['forecast']; ?>
		<section class="fns-kpis">
			<div><b><?php echo esc_html( fns_compact( $a['market']['total'] ) ); ?></b><span>جستجوی ماهانه بازار</span></div>
			<div class="hl"><b><?php echo esc_html( fns_compact( $a['market']['buyer'] ) ); ?></b><span>جستجوی آماده خرید</span></div>
			<div><b><?php echo esc_html( fns_num( $f['baseline'] ) ); ?></b><span>کلیک غیربرندی امروز <?php echo 'gsc' === $f['basis'] ? '(سرچ کنسول)' : '(تخمین)'; ?></span></div>
			<div><b><?php echo esc_html( fns_num( $f['scenarios']['low'] ) . ' تا ' . fns_num( $f['scenarios']['high'] ) ); ?></b><span>کلیک ماهانه پایان دوره</span></div>
			<div><b><?php echo esc_html( fns_compact( $pk['monthly'] ) ); ?></b><span>تومان در ماه (پکیج <?php echo esc_html( $pk['label'] ); ?>)</span></div>
		</section>
		<?php
		$errs = array_filter( (array) ( $job['steps'] ?? array() ), function ( $s ) {
			return 'error' === $s['status'];
		} );
		if ( $errs ) :
			?>
			<section class="fns-panel warn"><h2>مراحلی که داده نگرفتند</h2><ul><?php foreach ( $errs as $s ) { echo '<li><b>' . esc_html( $s['label'] ) . ':</b> ' . esc_html( $s['msg'] ) . '</li>'; } ?></ul><p class="fns-muted">پروپوزال با داده‌های موجود ساخته شده؛ اسلایدهای بدون داده خودکار ساده‌تر می‌شوند.</p></section>
		<?php endif; ?>
	<?php endif; ?>

<?php elseif ( 'market' === $tab ) : if ( ! $need() ) : $m = $a['market']; ?>
	<section class="fns-kpis">
		<div><b><?php echo esc_html( fns_num( $m['total'] ) ); ?></b><span>جستجوی ماهانه</span></div>
		<div class="hl"><b><?php echo esc_html( fns_num( $m['buyer'] ) ); ?></b><span>جستجوی خرید/مقایسه/محلی</span></div>
		<div><b><?php echo esc_html( fns_num( $m['keywords'] ) ); ?></b><span>کلمه یکتا</span></div>
		<div><b><?php echo esc_html( fns_num( count( $m['clusters'] ) ) ); ?></b><span>خوشه</span></div>
	</section>
	<section class="fns-panel">
		<h2>خوشه‌ها</h2>
		<table class="fns-table">
			<thead><tr><th>خوشه</th><th>جستجوی ماهانه</th><th>خرید</th><th>مقایسه</th><th>محلی</th><th>عمومی</th><th>آموزشی</th><th>روند</th><th>کلمات برتر</th></tr></thead>
			<tbody>
			<?php foreach ( $m['clusters'] as $c ) : ?>
				<tr>
					<td><b><?php echo esc_html( $c['seed'] ); ?></b><?php echo $c['error'] ? '<br><small class="err">' . esc_html( $c['error'] ) . '</small>' : ''; ?></td>
					<td><?php echo esc_html( fns_num( $c['total'] ) ); ?></td>
					<?php foreach ( array( 'buy', 'compare', 'local', 'general', 'info' ) as $i ) : ?>
						<td><?php echo esc_html( fns_num( $c['intents'][ $i ] ?? 0 ) ); ?></td>
					<?php endforeach; ?>
					<td dir="ltr"><?php echo esc_html( fns_num( $c['change'], 1 ) ); ?>٪</td>
					<td class="kw"><?php echo esc_html( implode( '، ', array_slice( array_column( $c['top'], 'w' ), 0, 6 ) ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</section>
	<div class="fns-grid g2">
		<section class="fns-panel"><h2>کلمات پول‌ساز</h2><table class="fns-table sm"><thead><tr><th>کلمه</th><th>جستجو</th><th>نیت</th><th>خوشه</th></tr></thead><tbody>
			<?php foreach ( array_slice( $m['money'], 0, 40 ) as $k ) { echo '<tr><td>' . esc_html( $k['w'] ) . '</td><td>' . esc_html( fns_num( $k['v'] ) ) . '</td><td>' . esc_html( fns_intent_label( $k['i'] ) ) . '</td><td>' . esc_html( $k['seed'] ) . '</td></tr>'; } ?>
		</tbody></table></section>
		<section class="fns-panel"><h2>سؤال‌های مشتریان</h2><table class="fns-table sm"><thead><tr><th>کلمه</th><th>جستجو</th><th>خوشه</th></tr></thead><tbody>
			<?php foreach ( array_slice( $m['questions'], 0, 40 ) as $k ) { echo '<tr><td>' . esc_html( $k['w'] ) . '</td><td>' . esc_html( fns_num( $k['v'] ) ) . '</td><td>' . esc_html( $k['seed'] ) . '</td></tr>'; } ?>
		</tbody></table></section>
	</div>
<?php endif; ?>

<?php elseif ( 'competitors' === $tab ) : if ( ! $need() ) : ?>
	<section class="fns-panel">
		<h2>مقایسه با رقبا <small class="fns-muted">(نمایش، کلیک و درآمد تخمینی‌اند)</small></h2>
		<table class="fns-table">
			<thead><tr><th>دامنه</th><th>صفحات نقشه سایت</th><th>کلمات</th><th>صفحه اول</th><th>صفحات رتبه‌دار</th><th>نمایش ماهانه</th><th>کلیک ماهانه</th><th>کلیک خرید</th><th>سهم بازار</th><th>سفارش/سرنخ</th><th>درآمد ماهانه</th></tr></thead>
			<tbody>
			<?php foreach ( $a['profiles'] as $d => $pr ) : ?>
				<tr class="<?php echo 'client' === $pr['role'] ? 'me' : ''; ?>">
					<td><b><?php echo esc_html( $d ); ?></b><?php echo $pr['has_data'] ? '' : ' <small class="err">بدون داده سئو سیگنال</small>'; ?></td>
					<td><?php echo esc_html( fns_num( $pr['site_pages'] ) ); ?></td>
					<td><?php echo esc_html( fns_num( $pr['keywords'] ) . ( $pr['capped'] ? '+' : '' ) ); ?></td>
					<td><?php echo esc_html( fns_num( $pr['top10'] ) ); ?></td>
					<td><?php echo esc_html( fns_num( $pr['ranking_pages'] ) ); ?></td>
					<td><?php echo esc_html( fns_num( $pr['impressions'] ) ); ?></td>
					<td><?php echo esc_html( fns_num( $pr['clicks'] ) ); ?></td>
					<td><?php echo esc_html( fns_num( $pr['buyer_clicks'] ) ); ?></td>
					<td><?php echo esc_html( fns_pct( $a['sov'][ $d ] ?? 0 ) ); ?></td>
					<td><?php echo esc_html( fns_num( $pr['revenue']['orders'] ) ); ?></td>
					<td><?php echo esc_html( $pr['revenue']['high'] ? fns_compact( $pr['revenue']['low'] ) . ' تا ' . fns_compact( $pr['revenue']['high'] ) : '—' ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p class="fns-muted">روش: هر کلمه رتبه میانگین صفحه‌اش را می‌گیرد؛ نمایش = حجم × احتمال دیده‌شدن، کلیک = حجم × نرخ کلیک استاندارد آن رتبه، سفارش = کلیک خرید × نرخ تبدیل (<?php echo esc_html( fns_num( $in['conversion_rate'], 1 ) ); ?>٪)، درآمد = سفارش × میانگین سبد (±۳۰٪).</p>
	</section>
	<div class="fns-grid g2">
		<?php foreach ( $a['profiles'] as $d => $pr ) : ?>
			<section class="fns-panel"><h2><?php echo esc_html( $d ); ?> — صفحات برتر</h2><table class="fns-table sm"><thead><tr><th>صفحه</th><th>کلمه اصلی</th><th>کلمات</th><th>کلیک</th><th>پول‌ساز</th></tr></thead><tbody>
				<?php foreach ( $pr['top_pages'] as $pg ) { echo '<tr><td dir="ltr" class="url">' . esc_html( fns_short_url( $pg['p'], 40 ) ) . '</td><td>' . esc_html( $pg['top'] ) . '</td><td>' . esc_html( fns_num( $pg['kw'] ) ) . '</td><td>' . esc_html( fns_num( $pg['clicks'] ) ) . '</td><td>' . ( $pg['money'] ? '✓' : '' ) . '</td></tr>'; } ?>
			</tbody></table></section>
		<?php endforeach; ?>
	</div>
	<?php if ( ! empty( $a['gap']['count'] ) ) : ?>
		<section class="fns-panel"><h2>شکاف کلمات (<?php echo esc_html( fns_num( $a['gap']['count'] ) ); ?> کلمه، <?php echo esc_html( fns_num( $a['gap']['volume'] ) ); ?> جستجو)</h2><table class="fns-table sm"><thead><tr><th>کلمه</th><th>جستجو</th><th>نیت</th><th>رقبای دارای رتبه</th></tr></thead><tbody>
			<?php foreach ( $a['gap']['top'] as $g ) { echo '<tr><td>' . esc_html( $g['w'] ) . '</td><td>' . esc_html( fns_num( $g['v'] ) ) . '</td><td>' . esc_html( fns_intent_label( $g['i'] ) ) . '</td><td dir="ltr">' . esc_html( implode( ', ', $g['by'] ) ) . '</td></tr>'; } ?>
		</tbody></table></section>
	<?php endif; ?>
<?php endif; ?>

<?php elseif ( 'offpage' === $tab ) : if ( ! $need() ) : ?>
	<section class="fns-panel">
		<h2>رپورتاژ و بک‌لینک باکیفیت</h2>
		<p class="fns-muted">فقط رپورتاژ رسانه‌های ایرانی با لینک فالو و انکر هدفمند شمرده می‌شود؛ دایرکتوری، کامنت، پروفایل، انجمن، سایت‌واید، وبلاگ‌های رایگان و انکرهای «اینجا/کلیک کنید/آدرس سایت» کنار گذاشته می‌شوند (قواعد در تنظیمات قابل ویرایش است).</p>
		<table class="fns-table">
			<thead><tr><th>دامنه</th><th>کل رپورتاژ</th><th>فالو</th><th>۱۲ ماه اخیر</th><th>رسانه</th><th>انکر هدفمند</th><th>رپورتاژ باکیفیت</th><th>میانه قیمت</th><th>هزینه ۱۲ ماه</th><th>بک‌لینک (سمراش)</th><th>دامنه ارجاع‌دهنده</th><th>دامنه باکیفیت</th><th>اسپم</th></tr></thead>
			<tbody>
			<?php foreach ( $a['offpage'] as $d => $o ) : $r = $o['reportage']; $b = $o['backlinks']; ?>
				<tr class="<?php echo $d === $cd ? 'me' : ''; ?>">
					<td><b><?php echo esc_html( $d ); ?></b><?php echo $r['error'] ? '<br><small class="err">' . esc_html( $r['error'] ) . '</small>' : ''; ?></td>
					<td><?php echo esc_html( fns_num( $r['total'] ) ); ?></td>
					<td><?php echo esc_html( fns_num( $r['follow'] ) ); ?></td>
					<td><?php echo esc_html( fns_num( $r['last12'] ) ); ?></td>
					<td><?php echo esc_html( fns_num( $r['media'] ) ); ?></td>
					<td><?php echo esc_html( null === $r['targeted_ratio'] ? '—' : fns_pct( $r['targeted_ratio'] ) ); ?></td>
					<td><b><?php echo esc_html( fns_num( $o['quality_reportages'] ) ); ?></b></td>
					<td><?php echo esc_html( $r['price_avg'] ? fns_compact( $r['price_avg'] ) : '—' ); ?></td>
					<td><?php echo esc_html( $r['spend12'] ? fns_compact( $r['spend12'] ) : '—' ); ?></td>
					<td><?php echo esc_html( ! empty( $b['available'] ) ? fns_num( $b['backlinks'] ) : '—' ); ?></td>
					<td><?php echo esc_html( ! empty( $b['available'] ) ? fns_num( $b['refdomains'] ) : '—' ); ?></td>
					<td><?php echo esc_html( ! empty( $b['available'] ) ? fns_num( $b['quality_refdomains'] ) : '—' ); ?></td>
					<td><?php echo esc_html( ! empty( $b['available'] ) ? fns_pct( $b['spam_share'] ) : '—' ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</section>
	<div class="fns-grid g2">
		<?php foreach ( $a['offpage'] as $d => $o ) : $r = $o['reportage']; if ( ! $r['top_media'] && empty( $o['backlinks']['examples'] ) ) { continue; } ?>
			<section class="fns-panel">
				<h2><?php echo esc_html( $d ); ?></h2>
				<?php if ( $r['top_media'] ) : ?><p><b>رسانه‌های پرتکرار:</b> <span dir="ltr"><?php echo esc_html( implode( ', ', array_keys( $r['top_media'] ) ) ); ?></span></p><?php endif; ?>
				<?php if ( $r['anchors'] ) : ?><p><b>انکرها:</b> <?php foreach ( $r['anchors'] as $t => $n ) { echo '<span class="fns-chip">' . esc_html( fns_anchor_labels()[ $t ] ?? $t ) . ': ' . esc_html( fns_num( $n ) ) . '</span>'; } ?></p><?php endif; ?>
				<?php if ( $r['pages'] ) : ?><table class="fns-table sm"><thead><tr><th>صفحه لینک‌گرفته</th><th>تعداد</th></tr></thead><tbody><?php foreach ( $r['pages'] as $pg ) { echo '<tr><td dir="ltr" class="url">' . esc_html( fns_short_url( $pg['url'], 50 ) ) . '</td><td>' . esc_html( fns_num( $pg['n'] ) ) . '</td></tr>'; } ?></tbody></table><?php endif; ?>
				<?php if ( ! empty( $o['backlinks']['examples'] ) ) : ?><table class="fns-table sm"><thead><tr><th>نمونه رپورتاژ باکیفیت</th><th>انکر</th></tr></thead><tbody><?php foreach ( $o['backlinks']['examples'] as $ex ) { echo '<tr><td dir="ltr" class="url">' . esc_html( fns_short_url( $ex['from'], 45 ) ) . '</td><td>' . esc_html( $ex['anchor'] ) . '</td></tr>'; } ?></tbody></table><?php endif; ?>
			</section>
		<?php endforeach; ?>
	</div>
	<?php if ( ! fns_semrush_connected() ) : ?><div class="fns-notice">سمراش متصل نیست؛ وقتی اکانت فعال شد، کلید را در تنظیمات وارد و «فعال» کنید و تحلیل را به‌روز کنید تا بک‌لینک‌ها، دامنه‌های ارجاع‌دهنده و لینک صفحات پرفروش هم اضافه شوند.</div><?php endif; ?>
<?php endif; ?>

<?php elseif ( 'technical' === $tab ) : ?>
	<section class="fns-panel">
		<h2>اتصال اسکریمینگ فراگ</h2>
		<p>اسکریمینگ فراگ روی کامپیوتر خودتان اجرا می‌شود. اسکریپت آماده این پروپوزال را دانلود و اجرا کنید؛ برای سایت کارفرما و همه رقبا، تا <?php echo esc_html( fns_num( $in['crawl_limit'] ) ); ?> صفحه مهم از نقشه سایت را می‌خزد و نتیجه را خودکار به فانوس می‌فرستد.</p>
		<div class="fns-row">
			<a class="fns-btn primary" href="<?php echo esc_url( fns_action_url( 'fns_agent', array( 'id' => $id, 'os' => 'win' ) ) ); ?>"><?php echo fns_icon( 'download', 16 ); // phpcs:ignore ?> اسکریپت ویندوز (.ps1)</a>
			<a class="fns-btn" href="<?php echo esc_url( fns_action_url( 'fns_agent', array( 'id' => $id, 'os' => 'sh' ) ) ); ?>"><?php echo fns_icon( 'download', 16 ); // phpcs:ignore ?> اسکریپت مک / لینوکس (.sh)</a>
		</div>
		<pre class="fns-code" dir="ltr">powershell -ExecutionPolicy Bypass -File fanoos-sf-<?php echo esc_html( $in['domain'] ?: 'proposal-' . $id ); ?>.ps1</pre>
		<details><summary>آپلود دستی خروجی‌ها</summary>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fns-row">
				<?php wp_nonce_field( 'fns_sf_upload' ); ?>
				<input type="hidden" name="action" value="fns_sf_upload"><input type="hidden" name="id" value="<?php echo (int) $id; ?>">
				<select name="domain"><?php foreach ( fns_domains( $p ) as $d => $r ) { echo '<option>' . esc_html( $d ) . '</option>'; } ?></select>
				<input type="file" name="files[]" multiple accept=".csv,.gz">
				<button class="fns-btn">آپلود</button>
				<small class="fns-muted">internal_all.csv، all_inlinks.csv و redirects (خام یا gz)؛ نوع فایل خودکار تشخیص داده می‌شود.</small>
			</form>
		</details>
	</section>
	<?php if ( ! $need() ) : ?>
		<div class="fns-grid g2">
			<?php foreach ( $a['technical'] as $d => $te ) : ?>
				<section class="fns-panel">
					<h2><?php echo esc_html( $d ); ?> <span class="fns-score s<?php echo (int) floor( ( $te['score'] ?? 0 ) / 25 ); ?>"><?php echo esc_html( null === $te['score'] ? '—' : fns_num( $te['score'] ) ); ?></span> <small class="fns-muted"><?php echo ! empty( $te['has_sf'] ) ? 'با خزش اسکریمینگ فراگ (' . esc_html( fns_num( $te['metrics']['crawled'] ) ) . ' آدرس)' : 'بررسی سریع'; ?></small></h2>
					<table class="fns-table sm"><thead><tr><th>مورد</th><th>اهمیت</th><th>تعداد</th><th>منبع</th></tr></thead><tbody>
						<?php foreach ( $te['issues'] as $i ) { echo '<tr><td>' . esc_html( $i['label'] ) . '</td><td><span class="fns-sev ' . esc_attr( $i['sev'] ) . '">' . esc_html( fns_sev_label( $i['sev'] ) ) . '</span></td><td>' . esc_html( fns_num( $i['count'] ) . ( $i['total'] > 1 ? ' از ' . fns_num( $i['total'] ) : '' ) ) . '</td><td>' . ( 'sf' === $i['src'] ? 'SF' : 'سریع' ) . '</td></tr>'; } ?>
						<?php if ( ! $te['issues'] ) { echo '<tr><td colspan="4">مورد مهمی دیده نشد.</td></tr>'; } ?>
					</tbody></table>
					<?php if ( $te['good'] ) : ?><p class="fns-muted">✓ <?php echo esc_html( implode( ' · ', $te['good'] ) ); ?></p><?php endif; ?>
				</section>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

<?php elseif ( 'costs' === $tab ) : if ( ! $need() ) : $b = $a['budget']; $ov = (array) $p['budget']; $items = fns_budget_items(); ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fns-panel">
		<?php wp_nonce_field( 'fns_save_budget' ); ?>
		<input type="hidden" name="action" value="fns_save_budget"><input type="hidden" name="id" value="<?php echo (int) $id; ?>">
		<h2>محاسبه هزینه‌ها</h2>
		<p class="fns-muted">تعدادها از تحلیل رقبا پیشنهاد شده‌اند (دلیل هرکدام زیر جدول). هر خانه را می‌توانید تغییر دهید؛ خانه خالی = عدد پیشنهادی. بودجه محتوا و آف‌پیج مستقیم توسط کارفرما پرداخت می‌شود.</p>
		<div class="fns-row">
			<label>طول دوره <select name="months"><?php foreach ( array( 8, 9, 10, 12 ) as $mm ) { echo '<option value="' . (int) $mm . '" ' . selected( (int) $in['months'], $mm, false ) . '>' . esc_html( fns_num( $mm ) ) . ' ماه</option>'; } ?></select></label>
			<label>پکیج پیشنهادی در پروپوزال <select name="b[selected]"><?php foreach ( fns_packages() as $k => $pk ) { echo '<option value="' . esc_attr( $k ) . '" ' . selected( $b['selected'], $k, false ) . '>' . esc_html( $pk['label'] ) . '</option>'; } ?></select></label>
		</div>
		<table class="fns-table cost-edit">
			<thead><tr><th>بخش</th><th>قیمت واحد (تومان)</th><?php foreach ( $b['packages'] as $k => $pk ) { echo '<th>' . esc_html( $pk['label'] ) . '</th>'; } ?></tr></thead>
			<tbody>
				<?php foreach ( $items as $item => $lab ) : ?>
					<tr>
						<td><b><?php echo esc_html( $lab[0] ); ?></b></td>
						<td><input type="text" class="fns-money" name="b[price][<?php echo esc_attr( $item ); ?>]" value="<?php echo esc_attr( isset( $ov['price'][ $item ] ) ? fns_num( $ov['price'][ $item ] ) : '' ); ?>" placeholder="<?php echo esc_attr( fns_num( $b['prices'][ $item ] ) ); ?>"></td>
						<?php foreach ( $b['packages'] as $k => $pk ) : ?>
							<td><input type="text" class="qty" name="b[qty][<?php echo esc_attr( $k ); ?>][<?php echo esc_attr( $item ); ?>]" value="<?php echo esc_attr( $ov['qty'][ $k ][ $item ] ?? '' ); ?>" placeholder="<?php echo esc_attr( fns_num( $pk['qty'][ $item ] ) ); ?>"> <small><?php echo esc_html( fns_compact( $pk['lines'][ $item ] ) ); ?></small></td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
				<tr>
					<td><b>دستمزد ماهانه تیم سئو</b></td><td class="fns-muted">پیش‌فرض در تنظیمات</td>
					<?php foreach ( $b['packages'] as $k => $pk ) : ?>
						<td><input type="text" class="fns-money" name="b[fee][<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( isset( $ov['fee'][ $k ] ) ? fns_num( $ov['fee'][ $k ] ) : '' ); ?>" placeholder="<?php echo esc_attr( fns_num( $pk['fee'] ) ); ?>"></td>
					<?php endforeach; ?>
				</tr>
			</tbody>
			<tfoot>
				<tr><td>بودجه محتوا</td><td></td><?php foreach ( $b['packages'] as $pk ) { echo '<td>' . esc_html( fns_toman( $pk['content'] ) ) . '</td>'; } ?></tr>
				<tr><td>بودجه آف‌پیج</td><td></td><?php foreach ( $b['packages'] as $pk ) { echo '<td>' . esc_html( fns_toman( $pk['offpage'] ) ) . '</td>'; } ?></tr>
				<tr><td>دستمزد کل دوره</td><td></td><?php foreach ( $b['packages'] as $pk ) { echo '<td>' . esc_html( fns_toman( $pk['fees'] ) ) . '</td>'; } ?></tr>
				<tr class="total"><td>جمع کل دوره</td><td></td><?php foreach ( $b['packages'] as $pk ) { echo '<td>' . esc_html( fns_toman( $pk['total'] ) ) . '<br><small>ماهانه ' . esc_html( fns_toman( $pk['monthly'] ) ) . '</small></td>'; } ?></tr>
				<tr><td>پیش‌بینی کلیک پایان دوره</td><td></td><?php foreach ( $b['packages'] as $k => $pk ) { echo '<td>' . esc_html( fns_num( $a['forecasts'][ $k ]['low'] ) . ' تا ' . fns_num( $a['forecasts'][ $k ]['high'] ) ) . '</td>'; } ?></tr>
			</tfoot>
		</table>
		<ul class="fns-why"><?php foreach ( $b['why'] as $item => $why ) { echo '<li><b>' . esc_html( $items[ $item ][0] ) . ':</b> ' . esc_html( $why ) . '</li>'; } ?></ul>
		<div class="fns-actions"><button class="fns-btn primary">ذخیره و محاسبه دوباره</button><button class="fns-btn ghost" name="reset" value="1">برگشت به اعداد پیشنهادی</button></div>
	</form>
	<section class="fns-panel">
		<h2>نقشه راه (پکیج <?php echo esc_html( $b['packages'][ $b['selected'] ]['label'] ); ?>)</h2>
		<div class="fns-road">
			<?php foreach ( $a['roadmap'] as $i => $ph ) : ?>
				<div><span class="n"><?php echo esc_html( fns_fa( $i + 1 ) ); ?></span><b><?php echo esc_html( $ph['title'] ); ?></b><small><?php echo esc_html( $ph['label'] . ' · ' . $ph['when'] ); ?></small><ul><?php foreach ( $ph['actions'] as $ac ) { echo '<li>' . esc_html( $ac ) . '</li>'; } ?></ul><p class="deliv"><?php echo esc_html( implode( ' · ', $ph['deliver_text'] ) ); ?></p><p class="res"><?php echo esc_html( $ph['result'] ); ?></p></div>
			<?php endforeach; ?>
		</div>
	</section>
<?php endif; ?>

<?php elseif ( 'texts' === $tab ) : if ( ! $need() ) : $t = fns_texts( $p, $a ); ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fns-panel">
		<?php wp_nonce_field( 'fns_save_texts' ); ?>
		<input type="hidden" name="action" value="fns_save_texts"><input type="hidden" name="id" value="<?php echo (int) $id; ?>">
		<div class="fns-row between">
			<h2>متن‌های پروپوزال</h2>
			<?php if ( fns_ai_connected() ) : ?><button type="button" class="fns-btn" id="fns-ai-texts" data-id="<?php echo (int) $id; ?>"><?php echo fns_icon( 'spark', 16 ); // phpcs:ignore ?> بازنویسی با هوش مصنوعی</button><?php endif; ?>
		</div>
		<p class="fns-muted"><?php echo ! empty( $a['ai_texts'] ) ? 'متن‌ها را هوش مصنوعی از روی عددهای تحلیل نوشته است.' : 'متن‌ها از قالب آماده با عددهای تحلیل ساخته شده‌اند' . ( fns_ai_connected() ? '؛ با دکمه بالا با هوش مصنوعی بازنویسی کنید.' : '؛ با اتصال هوش مصنوعی روان‌تر و اختصاصی‌تر می‌شوند.' ); ?> هر تغییری اینجا بدهید در PDF همان می‌آید.</p>
		<?php foreach ( fns_text_fields() as $k => $f ) : ?>
			<label class="fns-text"><?php echo esc_html( $f[0] ); ?><textarea name="text_<?php echo esc_attr( $k ); ?>" rows="<?php echo (int) $f[1]; ?>"><?php echo esc_textarea( $t[ $k ] ?? '' ); ?></textarea></label>
		<?php endforeach; ?>
		<div class="fns-actions sticky"><button class="fns-btn primary">ذخیره متن‌ها</button><button class="fns-btn ghost" name="reset" value="1" data-confirm="ویرایش‌های دستی پاک شود؟">برگشت به متن خودکار</button></div>
	</form>
<?php endif; ?>

<?php elseif ( 'export' === $tab ) : if ( ! $need() ) : $all = fns_deck_slides( $p, $a ); $off = array_flip( (array) ( $p['slides']['off'] ?? array() ) ); $shared = (bool) get_post_meta( $id, '_fns_shared', true ); $link = add_query_arg( array( 'fanoos_deck' => $id, 't' => $p['token'] ), home_url( '/' ) ); ?>
	<div class="fns-grid g-side">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fns-panel">
			<?php wp_nonce_field( 'fns_save_slides' ); ?>
			<input type="hidden" name="action" value="fns_save_slides"><input type="hidden" name="id" value="<?php echo (int) $id; ?>">
			<h2>اسلایدهای پروپوزال</h2>
			<div class="fns-slides">
				<?php $n = 0; foreach ( $all as $k => $label ) : $n++; ?>
					<label class="fns-switch"><input type="checkbox" name="slides[]" value="<?php echo esc_attr( $k ); ?>" <?php checked( ! isset( $off[ $k ] ) ); ?>><span></span> <?php echo esc_html( fns_fa( $n ) . '. ' . $label ); ?></label>
				<?php endforeach; ?>
			</div>
			<label class="fns-switch"><input type="checkbox" name="shared" value="1" <?php checked( $shared ); ?>><span></span> نسخه وب قابل اشتراک با لینک (بدون نیاز به ورود)</label>
			<div class="fns-actions"><button class="fns-btn primary">ذخیره</button></div>
		</form>
		<aside class="fns-panel">
			<h2>خروجی</h2>
			<p><a class="fns-btn primary lg block" target="_blank" href="<?php echo esc_url( fns_action_url( 'fns_pdf', array( 'id' => $id ) ) ); ?>"><?php echo fns_icon( 'download' ); // phpcs:ignore ?> دانلود PDF اسلایدی</a></p>
			<p class="fns-muted"><?php echo 'gotenberg' === fns_setting( 'pdf_service' ) ? 'PDF روی سرور ساخته و مستقیم دانلود می‌شود.' : 'پنجره چاپ مرورگر باز می‌شود؛ مقصد را «Save as PDF» بگذارید (اندازه و حاشیه‌ها خودکار تنظیم است؛ تیک Background graphics روشن باشد).'; ?></p>
			<p><a class="fns-btn block" target="_blank" href="<?php echo esc_url( fns_action_url( 'fns_pdf', array( 'id' => $id, 'preview' => 1 ) ) ); ?>"><?php echo fns_icon( 'eye', 16 ); // phpcs:ignore ?> پیش‌نمایش اسلایدها</a></p>
			<?php if ( $shared ) : ?>
				<label>لینک نسخه وب برای کارفرما<input type="text" dir="ltr" readonly value="<?php echo esc_url( $link ); ?>" onclick="this.select()"></label>
			<?php endif; ?>
			<h3>وضعیت پروپوزال</h3>
			<div class="fns-row">
				<?php foreach ( array( 'sent' => 'ارسال شد', 'won' => 'قرارداد بسته شد', 'lost' => 'رد شد' ) as $k => $l ) { echo '<a class="fns-btn ghost sm" href="' . esc_url( fns_action_url( 'fns_status', array( 'id' => $id, 'status' => $k ) ) ) . '">' . esc_html( $l ) . '</a>'; } ?>
			</div>
		</aside>
	</div>
<?php endif; ?>
<?php endif; ?>
