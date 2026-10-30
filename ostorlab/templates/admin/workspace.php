<?php
/**
 * Proposal workspace with tabs.
 *
 * @var array  $p
 * @var string $tab
 * @package Ostorlab
 */

defined( 'ABSPATH' ) || exit;

$id   = $p['id'];
$in   = $p['inputs'];
$a    = ost_data( $id, 'analysis' );
$st   = ost_status_label( $p['status'] );
$job  = (array) $p['job'];
$tabs = array(
	'summary'     => array( 'خلاصه و اجرا', 'play' ),
	'market'      => array( 'بازار و کلمات', 'search' ),
	'competitors' => array( 'رقبا', 'users' ),
	'strategy'    => array( 'استراتژی شروع', 'target' ),
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
	return ost_admin_url( 'proposal', array( 'id' => $id, 'tab' => $t ) );
};
$need = function () use ( $a, $url ) {
	if ( ! $a ) {
		echo '<div class="ost-empty sm"><p>هنوز تحلیلی انجام نشده است.</p><a class="ost-btn primary" href="' . esc_url( $url( 'summary' ) ) . '">شروع تحلیل</a></div>';
		return true;
	}
	return false;
};
$cd = $a['client_domain'] ?? '';
?>
<div class="ost-bar">
	<div>
		<h1><?php echo esc_html( $p['title'] ); ?> <span class="ost-pill <?php echo esc_attr( $st[1] ); ?>"><?php echo esc_html( $st[0] ); ?></span></h1>
		<p class="ost-muted"><?php echo esc_html( $in['domain'] ?: 'بدون سایت' ); ?> · <?php echo esc_html( ost_business_types()[ $in['business_type'] ] ); ?> · دوره <?php echo esc_html( ost_num( $in['months'] ) ); ?> ماه</p>
	</div>
	<div class="ost-bar-act">
		<a class="ost-btn" href="<?php echo esc_url( ost_admin_url( 'edit', array( 'id' => $id ) ) ); ?>">ویرایش ورودی‌ها</a>
		<?php if ( $a ) : ?>
			<a class="ost-btn" target="_blank" href="<?php echo esc_url( ost_action_url( 'ost_pdf', array( 'id' => $id, 'preview' => 1 ) ) ); ?>"><?php echo ost_icon( 'eye', 16 ); // phpcs:ignore ?> پیش‌نمایش</a>
			<a class="ost-btn primary" target="_blank" href="<?php echo esc_url( ost_action_url( 'ost_pdf', array( 'id' => $id ) ) ); ?>"><?php echo ost_icon( 'download', 16 ); // phpcs:ignore ?> دانلود PDF</a>
		<?php endif; ?>
	</div>
</div>

<nav class="ost-tabs">
	<?php foreach ( $tabs as $k => $t ) : ?>
		<a href="<?php echo esc_url( $url( $k ) ); ?>" class="<?php echo $tab === $k ? 'on' : ''; ?>"><?php echo ost_icon( $t[1], 16 ); // phpcs:ignore ?><?php echo esc_html( $t[0] ); ?></a>
	<?php endforeach; ?>
</nav>

<?php if ( 'summary' === $tab ) : $cost = ost_ss_cost_estimate( $p ); ?>
	<div class="ost-grid g-side">
		<section class="ost-panel">
			<h2>اجرای تحلیل</h2>
			<p class="ost-muted">هر مرحله جداگانه اجرا می‌شود تا هیچ درخواستی به محدودیت زمان سرور نخورد. پاسخ‌های سئو سیگنال ۲۰ روز کش می‌شوند؛ اجرای دوباره هزینه سهمیه ندارد.</p>
			<div class="ost-run" id="ost-run" data-id="<?php echo (int) $id; ?>" data-status="<?php echo esc_attr( $job['status'] ?? '' ); ?>" data-autorun="<?php echo ! empty( $_GET['autorun'] ) ? 1 : 0; // phpcs:ignore ?>">
				<div class="ost-run-act">
					<button class="ost-btn primary" data-run="1"><?php echo ost_icon( 'play', 16 ); // phpcs:ignore ?> <?php echo $a ? 'به‌روزرسانی تحلیل' : 'شروع تحلیل'; ?></button>
					<?php if ( $a ) : ?><button class="ost-btn" data-run="fresh">تحلیل کامل از نو</button><?php endif; ?>
					<?php if ( 'paused' === ( $job['status'] ?? '' ) ) : ?><button class="ost-btn amber" data-resume="1">ادامه تحلیل</button><?php endif; ?>
					<span class="ost-muted">سهمیه امروز سئو سیگنال: <?php echo esc_html( ost_num( ost_ss_remaining() ) ); ?> · این پروپوزال حداکثر <?php echo esc_html( ost_num( $cost['total'] ) ); ?></span>
				</div>
				<div class="ost-progress"><i style="width:0"></i></div>
				<ol class="ost-steps">
					<?php foreach ( (array) ( $job['steps'] ?? array() ) as $k => $s ) : ?>
						<li class="<?php echo esc_attr( $s['status'] ); ?>"><b><?php echo esc_html( $s['label'] ); ?></b><small><?php echo esc_html( $s['msg'] ); ?></small></li>
					<?php endforeach; ?>
				</ol>
				<?php if ( ! empty( $job['msg'] ) ) : ?><p class="ost-notice amber"><?php echo esc_html( $job['msg'] ); ?></p><?php endif; ?>
			</div>
		</section>
		<aside class="ost-panel">
			<h2>منابع داده</h2>
			<ul class="ost-sources">
				<li class="<?php echo ost_ss_connected() ? 'ok' : 'off'; ?>">سئو سیگنال — کلمات، رقبا، شکاف کلمات، رپورتاژها</li>
				<li class="<?php echo ost_semrush_connected() ? 'ok' : 'off'; ?>">سمراش — بک‌لینک و دامنه‌های ارجاع‌دهنده <?php echo ost_semrush_connected() ? '' : '<small>(متصل نیست؛ آف‌پیج از رپورتاژهای سئو سیگنال)</small>'; ?></li>
				<li class="<?php echo $in['gsc_access'] && ost_google_connected() ? 'ok' : 'off'; ?>">سرچ کنسول کارفرما <?php echo $in['gsc_access'] ? '' : '<small>(دسترسی داده نشده)</small>'; ?></li>
				<li class="ok">بررسی فنی سریع (نقشه سایت، robots، نمونه صفحات)</li>
				<li class="<?php echo ost_data( $id, 'sf' ) ? 'ok' : 'off'; ?>">اسکریمینگ فراگ <small>(از تب تکنیکال)</small></li>
				<li class="<?php echo ost_ai_connected() ? 'ok' : 'off'; ?>">هوش مصنوعی — نوشتن متن‌ها <?php echo ost_ai_via_sokan() ? '<small>(تنظیمات سکان)</small>' : ''; ?></li>
			</ul>
		</aside>
	</div>
	<?php if ( $a ) : $pk = $a['budget']['packages'][ $a['budget']['selected'] ]; $f = $a['forecast']; ?>
		<section class="ost-kpis">
			<div><b><?php echo esc_html( ost_compact( $a['market']['total'] ) ); ?></b><span>جستجوی ماهانه بازار</span></div>
			<div class="hl"><b><?php echo esc_html( ost_compact( $a['market']['buyer'] ) ); ?></b><span>جستجوی آماده خرید</span></div>
			<div><b><?php echo esc_html( ost_num( $f['baseline'] ) ); ?></b><span>کلیک غیربرندی امروز <?php echo 'gsc' === $f['basis'] ? '(سرچ کنسول)' : '(تخمین)'; ?></span></div>
			<div><b><?php echo esc_html( ost_num( $f['scenarios']['low'] ) . ' تا ' . ost_num( $f['scenarios']['high'] ) ); ?></b><span>کلیک ماهانه پایان دوره</span></div>
			<div><b><?php echo esc_html( ost_compact( $pk['monthly'] ) ); ?></b><span>تومان در ماه (پکیج <?php echo esc_html( $pk['label'] ); ?>)</span></div>
		</section>
		<?php
		$errs = array_filter( (array) ( $job['steps'] ?? array() ), function ( $s ) {
			return 'error' === $s['status'];
		} );
		if ( $errs ) :
			?>
			<section class="ost-panel warn"><h2>مراحلی که داده نگرفتند</h2><ul><?php foreach ( $errs as $s ) { echo '<li><b>' . esc_html( $s['label'] ) . ':</b> ' . esc_html( $s['msg'] ) . '</li>'; } ?></ul><p class="ost-muted">پروپوزال با داده‌های موجود ساخته شده؛ اسلایدهای بدون داده خودکار ساده‌تر می‌شوند.</p></section>
		<?php endif; ?>
	<?php endif; ?>

<?php elseif ( 'market' === $tab ) : if ( ! $need() ) : $m = $a['market']; ?>
	<section class="ost-kpis">
		<div><b><?php echo esc_html( ost_num( $m['total'] ) ); ?></b><span>جستجوی ماهانه</span></div>
		<div class="hl"><b><?php echo esc_html( ost_num( $m['buyer'] ) ); ?></b><span>جستجوی خرید/مقایسه/محلی</span></div>
		<div><b><?php echo esc_html( ost_num( $m['keywords'] ) ); ?></b><span>کلمه یکتا</span></div>
		<div><b><?php echo esc_html( ost_num( count( $m['clusters'] ) ) ); ?></b><span>خوشه</span></div>
	</section>
	<section class="ost-panel">
		<h2>خوشه‌ها</h2>
		<table class="ost-table">
			<thead><tr><th>خوشه</th><th>جستجوی ماهانه</th><th>خرید</th><th>مقایسه</th><th>محلی</th><th>عمومی</th><th>آموزشی</th><th>روند</th><th>کلمات برتر</th></tr></thead>
			<tbody>
			<?php foreach ( $m['clusters'] as $c ) : ?>
				<tr>
					<td><b><?php echo esc_html( $c['seed'] ); ?></b><?php echo $c['error'] ? '<br><small class="err">' . esc_html( $c['error'] ) . '</small>' : ''; ?></td>
					<td><?php echo esc_html( ost_num( $c['total'] ) ); ?></td>
					<?php foreach ( array( 'buy', 'compare', 'local', 'general', 'info' ) as $i ) : ?>
						<td><?php echo esc_html( ost_num( $c['intents'][ $i ] ?? 0 ) ); ?></td>
					<?php endforeach; ?>
					<td dir="ltr"><?php echo esc_html( ost_num( $c['change'], 1 ) ); ?>٪</td>
					<td class="kw"><?php echo esc_html( implode( '، ', array_slice( array_column( $c['top'], 'w' ), 0, 6 ) ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</section>
	<div class="ost-grid g2">
		<section class="ost-panel"><h2>کلمات پول‌ساز</h2><table class="ost-table sm"><thead><tr><th>کلمه</th><th>جستجو</th><th>نیت</th><th>خوشه</th></tr></thead><tbody>
			<?php foreach ( array_slice( $m['money'], 0, 40 ) as $k ) { echo '<tr><td>' . esc_html( $k['w'] ) . '</td><td>' . esc_html( ost_num( $k['v'] ) ) . '</td><td>' . esc_html( ost_intent_label( $k['i'] ) ) . '</td><td>' . esc_html( $k['seed'] ) . '</td></tr>'; } ?>
		</tbody></table></section>
		<section class="ost-panel"><h2>سؤال‌های مشتریان</h2><table class="ost-table sm"><thead><tr><th>کلمه</th><th>جستجو</th><th>خوشه</th></tr></thead><tbody>
			<?php foreach ( array_slice( $m['questions'], 0, 40 ) as $k ) { echo '<tr><td>' . esc_html( $k['w'] ) . '</td><td>' . esc_html( ost_num( $k['v'] ) ) . '</td><td>' . esc_html( $k['seed'] ) . '</td></tr>'; } ?>
		</tbody></table></section>
	</div>
<?php endif; ?>

<?php elseif ( 'competitors' === $tab ) : if ( ! $need() ) : ?>
	<section class="ost-panel">
		<h2>مقایسه با رقبا <small class="ost-muted">(نمایش، کلیک و درآمد تخمینی‌اند)</small></h2>
		<table class="ost-table">
			<thead><tr><th>دامنه</th><th>صفحات نقشه سایت</th><th>کلمات</th><th>صفحه اول</th><th>صفحات رتبه‌دار</th><th>نمایش ماهانه</th><th>کلیک ماهانه</th><th>کلیک خرید</th><th>سهم بازار</th><th>سفارش/سرنخ</th><th>درآمد ماهانه</th></tr></thead>
			<tbody>
			<?php foreach ( $a['profiles'] as $d => $pr ) : ?>
				<tr class="<?php echo 'client' === $pr['role'] ? 'me' : ''; ?>">
					<td><b><?php echo esc_html( $d ); ?></b><?php echo $pr['has_data'] ? '' : ' <small class="err">بدون داده سئو سیگنال</small>'; ?></td>
					<td><?php echo esc_html( ost_num( $pr['site_pages'] ) ); ?></td>
					<td><?php echo esc_html( ost_num( $pr['keywords'] ) . ( $pr['capped'] ? '+' : '' ) ); ?></td>
					<td><?php echo esc_html( ost_num( $pr['top10'] ) ); ?></td>
					<td><?php echo esc_html( ost_num( $pr['ranking_pages'] ) ); ?></td>
					<td><?php echo esc_html( ost_num( $pr['impressions'] ) ); ?></td>
					<td><?php echo esc_html( ost_num( $pr['clicks'] ) ); ?></td>
					<td><?php echo esc_html( ost_num( $pr['buyer_clicks'] ) ); ?></td>
					<td><?php echo esc_html( ost_pct( $a['sov'][ $d ] ?? 0 ) ); ?></td>
					<td><?php echo esc_html( ost_num( $pr['revenue']['orders'] ) ); ?></td>
					<td><?php echo esc_html( $pr['revenue']['high'] ? ost_compact( $pr['revenue']['low'] ) . ' تا ' . ost_compact( $pr['revenue']['high'] ) : '—' ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p class="ost-muted">روش: هر کلمه رتبه میانگین صفحه‌اش را می‌گیرد؛ نمایش = حجم × احتمال دیده‌شدن، کلیک = حجم × نرخ کلیک استاندارد آن رتبه، سفارش = کلیک خرید × نرخ تبدیل (<?php echo esc_html( ost_num( $in['conversion_rate'], 1 ) ); ?>٪)، درآمد = سفارش × میانگین سبد (±۳۰٪).</p>
	</section>
	<div class="ost-grid g2">
		<?php foreach ( $a['profiles'] as $d => $pr ) : ?>
			<section class="ost-panel"><h2><?php echo esc_html( $d ); ?> — صفحات برتر</h2><table class="ost-table sm"><thead><tr><th>صفحه</th><th>کلمه اصلی</th><th>کلمات</th><th>کلیک</th><th>پول‌ساز</th></tr></thead><tbody>
				<?php foreach ( $pr['top_pages'] as $pg ) { echo '<tr><td dir="ltr" class="url">' . esc_html( ost_short_url( $pg['p'], 40 ) ) . '</td><td>' . esc_html( $pg['top'] ) . '</td><td>' . esc_html( ost_num( $pg['kw'] ) ) . '</td><td>' . esc_html( ost_num( $pg['clicks'] ) ) . '</td><td>' . ( $pg['money'] ? '✓' : '' ) . '</td></tr>'; } ?>
			</tbody></table></section>
		<?php endforeach; ?>
	</div>
	<?php if ( ! empty( $a['gap']['count'] ) ) : ?>
		<section class="ost-panel"><h2>شکاف کلمات (<?php echo esc_html( ost_num( $a['gap']['count'] ) ); ?> کلمه، <?php echo esc_html( ost_num( $a['gap']['volume'] ) ); ?> جستجو)</h2><table class="ost-table sm"><thead><tr><th>کلمه</th><th>جستجو</th><th>نیت</th><th>رقبای دارای رتبه</th></tr></thead><tbody>
			<?php foreach ( $a['gap']['top'] as $g ) { echo '<tr><td>' . esc_html( $g['w'] ) . '</td><td>' . esc_html( ost_num( $g['v'] ) ) . '</td><td>' . esc_html( ost_intent_label( $g['i'] ) ) . '</td><td dir="ltr">' . esc_html( implode( ', ', $g['by'] ) ) . '</td></tr>'; } ?>
		</tbody></table></section>
	<?php endif; ?>
<?php endif; ?>

<?php elseif ( 'strategy' === $tab ) : if ( ! $need() ) : $sg = (array) ( $a['strategy'] ?? array() ); $pinned = array_flip( (array) ( $p['strategy']['picks'] ?? array() ) ); $chosen = array_flip( array_column( (array) ( $sg['picks'] ?? array() ), 'key' ) ); ?>
	<?php if ( ! empty( $sg['picks'] ) ) : ?>
		<section class="ost-strat-hero">
			<span class="ost-pill navy"><?php echo esc_html( $sg['name'] ); ?></span>
			<h2><?php echo esc_html( $sg['idea'] ); ?></h2>
			<div class="ost-kpis">
				<div class="hl"><b>«<?php echo esc_html( $sg['picks'][0]['label'] ); ?>»</b><span>نقطه شروع</span></div>
				<div><b><?php echo esc_html( ost_compact( $sg['volume'] ) ); ?></b><span>جستجوی ماهانه ۵ فرصت</span></div>
				<div><b><?php echo esc_html( ost_num( $sg['day90'][0] ) . ' تا ' . ost_num( $sg['day90'][1] ) ); ?></b><span>کلیک ماهانه تازه در ۹۰ روز</span></div>
				<div><b><?php echo esc_html( ost_num( $sg['clicks'] ) ); ?></b><span>کلیک ماهانه پس از تثبیت</span></div>
			</div>
		</section>
	<?php endif; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ost-panel">
		<?php wp_nonce_field( 'ost_save_strategy' ); ?>
		<input type="hidden" name="action" value="ost_save_strategy"><input type="hidden" name="id" value="<?php echo (int) $id; ?>">
		<h2>فرصت‌های شروع <small class="ost-muted">مرتب‌شده بر اساس امتیاز (تقاضا × نیت خرید × سهولت × ضعف رقبا × جایگاه فعلی شما)</small></h2>
		<p class="ost-muted">۵ فرصت اول خودکار در پروپوزال می‌آید. با «سنجاق» ترتیب و انتخاب را خودتان تعیین کنید (حداکثر ۵)، با «حذف» فرصتی را کنار بگذارید.</p>
		<table class="ost-table">
			<thead><tr><th>سنجاق</th><th>حذف</th><th>فرصت (موضوع یک صفحه)</th><th>نوع</th><th>جستجو</th><th>نیت خرید</th><th>سهولت</th><th>رقبا در ۳ / ۱۰ اول</th><th>رتبه شما</th><th>زمان نتیجه</th><th>کلیک پس از برد</th><th>چرا</th></tr></thead>
			<tbody>
			<?php foreach ( (array) ( $sg['all'] ?? array() ) as $k => $o ) : ?>
				<tr class="<?php echo isset( $chosen[ $k ] ) ? 'me' : ''; ?>">
					<td><input type="checkbox" name="pick[]" value="<?php echo esc_attr( $k ); ?>" <?php checked( isset( $pinned[ $k ] ) ); ?>></td>
					<td><input type="checkbox" name="off[]" value="<?php echo esc_attr( $k ); ?>" <?php checked( in_array( $k, (array) ( $p['strategy']['off'] ?? array() ), true ) ); ?>></td>
					<td><b><?php echo esc_html( $o['label'] ); ?></b><br><small class="ost-muted"><?php echo esc_html( implode( '، ', array_slice( array_column( $o['words'], 'w' ), 1, 3 ) ) ); ?></small></td>
					<td><?php echo esc_html( ost_opp_type_label( $o['type'] ) ); ?></td>
					<td><?php echo esc_html( ost_num( $o['volume'] ) ); ?></td>
					<td><?php echo esc_html( ost_pct( $o['buyer_share'] ) ); ?></td>
					<td><?php echo esc_html( ost_pct( $o['ease'] ) ); ?></td>
					<td><?php echo esc_html( ost_num( $o['strong'] ) . ' / ' . ost_num( $o['in_top10'] ) ); ?></td>
					<td><?php echo esc_html( null === $o['client_pos'] ? '—' : ost_num( $o['client_pos'], 1 ) ); ?></td>
					<td><?php echo esc_html( ost_num( $o['months'][0] ) . ' تا ' . ost_num( $o['months'][1] ) . ' ماه' ); ?></td>
					<td><?php echo esc_html( ost_num( $o['clicks'] ) ); ?></td>
					<td class="kw"><?php echo esc_html( implode( ' · ', array_slice( $o['reasons'], 1, 2 ) ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<div class="ost-actions sticky"><button class="ost-btn primary">ذخیره و محاسبه دوباره</button><button class="ost-btn ghost" name="reset" value="1">انتخاب خودکار</button><span class="ost-muted">متن توضیح استراتژی در تب «متن‌ها» قابل ویرایش است.</span></div>
	</form>
<?php endif; ?>

<?php elseif ( 'offpage' === $tab ) : if ( ! $need() ) : ?>
	<section class="ost-panel">
		<h2>رپورتاژ و بک‌لینک باکیفیت</h2>
		<p class="ost-muted">فقط رپورتاژ رسانه‌های ایرانی با لینک فالو و انکر هدفمند شمرده می‌شود؛ دایرکتوری، کامنت، پروفایل، انجمن، سایت‌واید، وبلاگ‌های رایگان و انکرهای «اینجا/کلیک کنید/آدرس سایت» کنار گذاشته می‌شوند (قواعد در تنظیمات قابل ویرایش است).</p>
		<table class="ost-table">
			<thead><tr><th>دامنه</th><th>کل رپورتاژ</th><th>فالو</th><th>۱۲ ماه اخیر</th><th>رسانه</th><th>انکر هدفمند</th><th>رپورتاژ باکیفیت</th><th>میانه قیمت</th><th>هزینه ۱۲ ماه</th><th>بک‌لینک (سمراش)</th><th>دامنه ارجاع‌دهنده</th><th>دامنه باکیفیت</th><th>اسپم</th></tr></thead>
			<tbody>
			<?php foreach ( $a['offpage'] as $d => $o ) : $r = $o['reportage']; $b = $o['backlinks']; ?>
				<tr class="<?php echo $d === $cd ? 'me' : ''; ?>">
					<td><b><?php echo esc_html( $d ); ?></b><?php echo $r['error'] ? '<br><small class="err">' . esc_html( $r['error'] ) . '</small>' : ''; ?></td>
					<td><?php echo esc_html( ost_num( $r['total'] ) ); ?></td>
					<td><?php echo esc_html( ost_num( $r['follow'] ) ); ?></td>
					<td><?php echo esc_html( ost_num( $r['last12'] ) ); ?></td>
					<td><?php echo esc_html( ost_num( $r['media'] ) ); ?></td>
					<td><?php echo esc_html( null === $r['targeted_ratio'] ? '—' : ost_pct( $r['targeted_ratio'] ) ); ?></td>
					<td><b><?php echo esc_html( ost_num( $o['quality_reportages'] ) ); ?></b></td>
					<td><?php echo esc_html( $r['price_avg'] ? ost_compact( $r['price_avg'] ) : '—' ); ?></td>
					<td><?php echo esc_html( $r['spend12'] ? ost_compact( $r['spend12'] ) : '—' ); ?></td>
					<td><?php echo esc_html( ! empty( $b['available'] ) ? ost_num( $b['backlinks'] ) : '—' ); ?></td>
					<td><?php echo esc_html( ! empty( $b['available'] ) ? ost_num( $b['refdomains'] ) : '—' ); ?></td>
					<td><?php echo esc_html( ! empty( $b['available'] ) ? ost_num( $b['quality_refdomains'] ) : '—' ); ?></td>
					<td><?php echo esc_html( ! empty( $b['available'] ) ? ost_pct( $b['spam_share'] ) : '—' ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</section>
	<div class="ost-grid g2">
		<?php foreach ( $a['offpage'] as $d => $o ) : $r = $o['reportage']; if ( ! $r['top_media'] && empty( $o['backlinks']['examples'] ) ) { continue; } ?>
			<section class="ost-panel">
				<h2><?php echo esc_html( $d ); ?></h2>
				<?php if ( $r['top_media'] ) : ?><p><b>رسانه‌های پرتکرار:</b> <span dir="ltr"><?php echo esc_html( implode( ', ', array_keys( $r['top_media'] ) ) ); ?></span></p><?php endif; ?>
				<?php if ( $r['anchors'] ) : ?><p><b>انکرها:</b> <?php foreach ( $r['anchors'] as $t => $n ) { echo '<span class="ost-chip">' . esc_html( ost_anchor_labels()[ $t ] ?? $t ) . ': ' . esc_html( ost_num( $n ) ) . '</span>'; } ?></p><?php endif; ?>
				<?php if ( $r['pages'] ) : ?><table class="ost-table sm"><thead><tr><th>صفحه لینک‌گرفته</th><th>تعداد</th></tr></thead><tbody><?php foreach ( $r['pages'] as $pg ) { echo '<tr><td dir="ltr" class="url">' . esc_html( ost_short_url( $pg['url'], 50 ) ) . '</td><td>' . esc_html( ost_num( $pg['n'] ) ) . '</td></tr>'; } ?></tbody></table><?php endif; ?>
				<?php if ( ! empty( $o['backlinks']['examples'] ) ) : ?><table class="ost-table sm"><thead><tr><th>نمونه رپورتاژ باکیفیت</th><th>انکر</th></tr></thead><tbody><?php foreach ( $o['backlinks']['examples'] as $ex ) { echo '<tr><td dir="ltr" class="url">' . esc_html( ost_short_url( $ex['from'], 45 ) ) . '</td><td>' . esc_html( $ex['anchor'] ) . '</td></tr>'; } ?></tbody></table><?php endif; ?>
			</section>
		<?php endforeach; ?>
	</div>
	<?php if ( ! ost_semrush_connected() ) : ?><div class="ost-notice">سمراش متصل نیست؛ وقتی اکانت فعال شد، کلید را در تنظیمات وارد و «فعال» کنید و تحلیل را به‌روز کنید تا بک‌لینک‌ها، دامنه‌های ارجاع‌دهنده و لینک صفحات پرفروش هم اضافه شوند.</div><?php endif; ?>
<?php endif; ?>

<?php elseif ( 'technical' === $tab ) : ?>
	<section class="ost-panel">
		<h2>اتصال اسکریمینگ فراگ</h2>
		<p>اسکریمینگ فراگ روی کامپیوتر خودتان اجرا می‌شود. اسکریپت آماده این پروپوزال را دانلود و اجرا کنید؛ برای سایت کارفرما و همه رقبا، تا <?php echo esc_html( ost_num( $in['crawl_limit'] ) ); ?> صفحه مهم از نقشه سایت را می‌خزد و نتیجه را خودکار به اسطرلاب می‌فرستد.</p>
		<div class="ost-row">
			<a class="ost-btn primary" href="<?php echo esc_url( ost_action_url( 'ost_agent', array( 'id' => $id, 'os' => 'win' ) ) ); ?>"><?php echo ost_icon( 'download', 16 ); // phpcs:ignore ?> اسکریپت ویندوز (.ps1)</a>
			<a class="ost-btn" href="<?php echo esc_url( ost_action_url( 'ost_agent', array( 'id' => $id, 'os' => 'sh' ) ) ); ?>"><?php echo ost_icon( 'download', 16 ); // phpcs:ignore ?> اسکریپت مک / لینوکس (.sh)</a>
		</div>
		<pre class="ost-code" dir="ltr">powershell -ExecutionPolicy Bypass -File ostorlab-sf-<?php echo esc_html( $in['domain'] ?: 'proposal-' . $id ); ?>.ps1</pre>
		<details><summary>آپلود دستی خروجی‌ها</summary>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ost-row">
				<?php wp_nonce_field( 'ost_sf_upload' ); ?>
				<input type="hidden" name="action" value="ost_sf_upload"><input type="hidden" name="id" value="<?php echo (int) $id; ?>">
				<select name="domain"><?php foreach ( ost_domains( $p ) as $d => $r ) { echo '<option>' . esc_html( $d ) . '</option>'; } ?></select>
				<input type="file" name="files[]" multiple accept=".csv,.gz">
				<button class="ost-btn">آپلود</button>
				<small class="ost-muted">internal_all.csv، all_inlinks.csv و redirects (خام یا gz)؛ نوع فایل خودکار تشخیص داده می‌شود.</small>
			</form>
		</details>
	</section>
	<?php if ( ! $need() ) : ?>
		<div class="ost-grid g2">
			<?php foreach ( $a['technical'] as $d => $te ) : ?>
				<section class="ost-panel">
					<h2><?php echo esc_html( $d ); ?> <span class="ost-score s<?php echo (int) floor( ( $te['score'] ?? 0 ) / 25 ); ?>"><?php echo esc_html( null === $te['score'] ? '—' : ost_num( $te['score'] ) ); ?></span> <small class="ost-muted"><?php echo ! empty( $te['has_sf'] ) ? 'با خزش اسکریمینگ فراگ (' . esc_html( ost_num( $te['metrics']['crawled'] ) ) . ' آدرس)' : 'بررسی سریع'; ?></small></h2>
					<table class="ost-table sm"><thead><tr><th>مورد</th><th>اهمیت</th><th>تعداد</th><th>منبع</th></tr></thead><tbody>
						<?php foreach ( $te['issues'] as $i ) { echo '<tr><td>' . esc_html( $i['label'] ) . '</td><td><span class="ost-sev ' . esc_attr( $i['sev'] ) . '">' . esc_html( ost_sev_label( $i['sev'] ) ) . '</span></td><td>' . esc_html( ost_num( $i['count'] ) . ( $i['total'] > 1 ? ' از ' . ost_num( $i['total'] ) : '' ) ) . '</td><td>' . ( 'sf' === $i['src'] ? 'SF' : 'سریع' ) . '</td></tr>'; } ?>
						<?php if ( ! $te['issues'] ) { echo '<tr><td colspan="4">مورد مهمی دیده نشد.</td></tr>'; } ?>
					</tbody></table>
					<?php if ( $te['good'] ) : ?><p class="ost-muted">✓ <?php echo esc_html( implode( ' · ', $te['good'] ) ); ?></p><?php endif; ?>
				</section>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

<?php elseif ( 'costs' === $tab ) : if ( ! $need() ) : $b = $a['budget']; $ov = (array) $p['budget']; $items = ost_budget_items(); ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ost-panel">
		<?php wp_nonce_field( 'ost_save_budget' ); ?>
		<input type="hidden" name="action" value="ost_save_budget"><input type="hidden" name="id" value="<?php echo (int) $id; ?>">
		<h2>محاسبه هزینه‌ها</h2>
		<p class="ost-muted">تعدادها از تحلیل رقبا پیشنهاد شده‌اند (دلیل هرکدام زیر جدول). هر خانه را می‌توانید تغییر دهید؛ خانه خالی = عدد پیشنهادی. بودجه محتوا و آف‌پیج مستقیم توسط کارفرما پرداخت می‌شود.</p>
		<div class="ost-row">
			<label>طول دوره <select name="months"><?php foreach ( array( 8, 9, 10, 12 ) as $mm ) { echo '<option value="' . (int) $mm . '" ' . selected( (int) $in['months'], $mm, false ) . '>' . esc_html( ost_num( $mm ) ) . ' ماه</option>'; } ?></select></label>
			<label>پکیج پیشنهادی در پروپوزال <select name="b[selected]"><?php foreach ( ost_packages() as $k => $pk ) { echo '<option value="' . esc_attr( $k ) . '" ' . selected( $b['selected'], $k, false ) . '>' . esc_html( $pk['label'] ) . '</option>'; } ?></select></label>
		</div>
		<table class="ost-table cost-edit">
			<thead><tr><th>بخش</th><th>قیمت واحد (تومان)</th><?php foreach ( $b['packages'] as $k => $pk ) { echo '<th>' . esc_html( $pk['label'] ) . '</th>'; } ?></tr></thead>
			<tbody>
				<?php foreach ( $items as $item => $lab ) : ?>
					<tr>
						<td><b><?php echo esc_html( $lab[0] ); ?></b></td>
						<td><input type="text" class="ost-money" name="b[price][<?php echo esc_attr( $item ); ?>]" value="<?php echo esc_attr( isset( $ov['price'][ $item ] ) ? ost_num( $ov['price'][ $item ] ) : '' ); ?>" placeholder="<?php echo esc_attr( ost_num( $b['prices'][ $item ] ) ); ?>"></td>
						<?php foreach ( $b['packages'] as $k => $pk ) : ?>
							<td><input type="text" class="qty" name="b[qty][<?php echo esc_attr( $k ); ?>][<?php echo esc_attr( $item ); ?>]" value="<?php echo esc_attr( $ov['qty'][ $k ][ $item ] ?? '' ); ?>" placeholder="<?php echo esc_attr( ost_num( $pk['qty'][ $item ] ) ); ?>"> <small><?php echo esc_html( ost_compact( $pk['lines'][ $item ] ) ); ?></small></td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
				<tr>
					<td><b>دستمزد ماهانه تیم سئو</b></td><td class="ost-muted">پیش‌فرض در تنظیمات</td>
					<?php foreach ( $b['packages'] as $k => $pk ) : ?>
						<td><input type="text" class="ost-money" name="b[fee][<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( isset( $ov['fee'][ $k ] ) ? ost_num( $ov['fee'][ $k ] ) : '' ); ?>" placeholder="<?php echo esc_attr( ost_num( $pk['fee'] ) ); ?>"></td>
					<?php endforeach; ?>
				</tr>
			</tbody>
			<tfoot>
				<tr><td>بودجه محتوا</td><td></td><?php foreach ( $b['packages'] as $pk ) { echo '<td>' . esc_html( ost_toman( $pk['content'] ) ) . '</td>'; } ?></tr>
				<tr><td>بودجه آف‌پیج</td><td></td><?php foreach ( $b['packages'] as $pk ) { echo '<td>' . esc_html( ost_toman( $pk['offpage'] ) ) . '</td>'; } ?></tr>
				<tr><td>دستمزد کل دوره</td><td></td><?php foreach ( $b['packages'] as $pk ) { echo '<td>' . esc_html( ost_toman( $pk['fees'] ) ) . '</td>'; } ?></tr>
				<tr class="total"><td>جمع کل دوره</td><td></td><?php foreach ( $b['packages'] as $pk ) { echo '<td>' . esc_html( ost_toman( $pk['total'] ) ) . '<br><small>ماهانه ' . esc_html( ost_toman( $pk['monthly'] ) ) . '</small></td>'; } ?></tr>
				<tr><td>پیش‌بینی کلیک پایان دوره</td><td></td><?php foreach ( $b['packages'] as $k => $pk ) { echo '<td>' . esc_html( ost_num( $a['forecasts'][ $k ]['low'] ) . ' تا ' . ost_num( $a['forecasts'][ $k ]['high'] ) ) . '</td>'; } ?></tr>
			</tfoot>
		</table>
		<ul class="ost-why"><?php foreach ( $b['why'] as $item => $why ) { echo '<li><b>' . esc_html( $items[ $item ][0] ) . ':</b> ' . esc_html( $why ) . '</li>'; } ?></ul>
		<div class="ost-actions"><button class="ost-btn primary">ذخیره و محاسبه دوباره</button><button class="ost-btn ghost" name="reset" value="1">برگشت به اعداد پیشنهادی</button></div>
	</form>
	<section class="ost-panel">
		<h2>نقشه راه (پکیج <?php echo esc_html( $b['packages'][ $b['selected'] ]['label'] ); ?>)</h2>
		<div class="ost-road">
			<?php foreach ( $a['roadmap'] as $i => $ph ) : ?>
				<div><span class="n"><?php echo esc_html( ost_fa( $i + 1 ) ); ?></span><b><?php echo esc_html( $ph['title'] ); ?></b><small><?php echo esc_html( $ph['label'] . ' · ' . $ph['when'] ); ?></small><ul><?php foreach ( $ph['actions'] as $ac ) { echo '<li>' . esc_html( $ac ) . '</li>'; } ?></ul><p class="deliv"><?php echo esc_html( implode( ' · ', $ph['deliver_text'] ) ); ?></p><p class="res"><?php echo esc_html( $ph['result'] ); ?></p></div>
			<?php endforeach; ?>
		</div>
	</section>
<?php endif; ?>

<?php elseif ( 'texts' === $tab ) : if ( ! $need() ) : $t = ost_texts( $p, $a ); ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ost-panel">
		<?php wp_nonce_field( 'ost_save_texts' ); ?>
		<input type="hidden" name="action" value="ost_save_texts"><input type="hidden" name="id" value="<?php echo (int) $id; ?>">
		<div class="ost-row between">
			<h2>متن‌های پروپوزال</h2>
			<?php if ( ost_ai_connected() ) : ?><button type="button" class="ost-btn" id="ost-ai-texts" data-id="<?php echo (int) $id; ?>"><?php echo ost_icon( 'spark', 16 ); // phpcs:ignore ?> بازنویسی با هوش مصنوعی</button><?php endif; ?>
		</div>
		<p class="ost-muted"><?php echo ! empty( $a['ai_texts'] ) ? 'متن‌ها را هوش مصنوعی از روی عددهای تحلیل نوشته است.' : 'متن‌ها از قالب آماده با عددهای تحلیل ساخته شده‌اند' . ( ost_ai_connected() ? '؛ با دکمه بالا با هوش مصنوعی بازنویسی کنید.' : '؛ با اتصال هوش مصنوعی روان‌تر و اختصاصی‌تر می‌شوند.' ); ?> هر تغییری اینجا بدهید در PDF همان می‌آید.</p>
		<?php foreach ( ost_text_fields() as $k => $f ) : ?>
			<label class="ost-text"><?php echo esc_html( $f[0] ); ?><textarea name="text_<?php echo esc_attr( $k ); ?>" rows="<?php echo (int) $f[1]; ?>"><?php echo esc_textarea( $t[ $k ] ?? '' ); ?></textarea></label>
		<?php endforeach; ?>
		<div class="ost-actions sticky"><button class="ost-btn primary">ذخیره متن‌ها</button><button class="ost-btn ghost" name="reset" value="1" data-confirm="ویرایش‌های دستی پاک شود؟">برگشت به متن خودکار</button></div>
	</form>
<?php endif; ?>

<?php elseif ( 'export' === $tab ) : if ( ! $need() ) : $all = ost_deck_slides( $p, $a ); $off = array_flip( (array) ( $p['slides']['off'] ?? array() ) ); $shared = (bool) get_post_meta( $id, '_ost_shared', true ); $link = add_query_arg( array( 'ostorlab_deck' => $id, 't' => $p['token'] ), home_url( '/' ) ); ?>
	<div class="ost-grid g-side">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ost-panel">
			<?php wp_nonce_field( 'ost_save_slides' ); ?>
			<input type="hidden" name="action" value="ost_save_slides"><input type="hidden" name="id" value="<?php echo (int) $id; ?>">
			<h2>اسلایدهای پروپوزال</h2>
			<div class="ost-slides">
				<?php $n = 0; foreach ( $all as $k => $label ) : $n++; ?>
					<label class="ost-switch"><input type="checkbox" name="slides[]" value="<?php echo esc_attr( $k ); ?>" <?php checked( ! isset( $off[ $k ] ) ); ?>><span></span> <?php echo esc_html( ost_fa( $n ) . '. ' . $label ); ?></label>
				<?php endforeach; ?>
			</div>
			<label class="ost-switch"><input type="checkbox" name="shared" value="1" <?php checked( $shared ); ?>><span></span> نسخه وب قابل اشتراک با لینک (بدون نیاز به ورود)</label>
			<div class="ost-actions"><button class="ost-btn primary">ذخیره</button></div>
		</form>
		<aside class="ost-panel">
			<h2>خروجی</h2>
			<p><a class="ost-btn primary lg block" target="_blank" href="<?php echo esc_url( ost_action_url( 'ost_pdf', array( 'id' => $id ) ) ); ?>"><?php echo ost_icon( 'download' ); // phpcs:ignore ?> دانلود PDF اسلایدی</a></p>
			<p class="ost-muted"><?php echo 'gotenberg' === ost_setting( 'pdf_service' ) ? 'PDF روی سرور ساخته و مستقیم دانلود می‌شود.' : 'پنجره چاپ مرورگر باز می‌شود؛ مقصد را «Save as PDF» بگذارید (اندازه و حاشیه‌ها خودکار تنظیم است؛ تیک Background graphics روشن باشد).'; ?></p>
			<p><a class="ost-btn block" target="_blank" href="<?php echo esc_url( ost_action_url( 'ost_pdf', array( 'id' => $id, 'preview' => 1 ) ) ); ?>"><?php echo ost_icon( 'eye', 16 ); // phpcs:ignore ?> پیش‌نمایش اسلایدها</a></p>
			<?php if ( $shared ) : ?>
				<label>لینک نسخه وب برای کارفرما<input type="text" dir="ltr" readonly value="<?php echo esc_url( $link ); ?>" onclick="this.select()"></label>
			<?php endif; ?>
			<h3>وضعیت پروپوزال</h3>
			<div class="ost-row">
				<?php foreach ( array( 'sent' => 'ارسال شد', 'won' => 'قرارداد بسته شد', 'lost' => 'رد شد' ) as $k => $l ) { echo '<a class="ost-btn ghost sm" href="' . esc_url( ost_action_url( 'ost_status', array( 'id' => $id, 'status' => $k ) ) ) . '">' . esc_html( $l ) . '</a>'; } ?>
			</div>
		</aside>
	</div>
<?php endif; ?>
<?php endif; ?>
