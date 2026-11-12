<?php
/**
 * Tab: keyword review — every topic that stays in the analysis and every keyword that was
 * removed (with the reason), confirmed by the SEO manager before the proposal is written.
 *
 * @var array $p
 * @var array $a
 * @package Ostorlab
 */

defined( 'ABSPATH' ) || exit;

$id  = $p['id'];
$man = ost_manual_kw( $id );
?>
<details class="ost-panel ost-import" <?php echo $man['rows'] ? '' : 'open'; ?>>
	<summary><b><?php echo ost_icon( 'download', 16 ); // phpcs:ignore ?> ورود کلمات دستی (اکسل، CSV یا کپی)</b>
		<small class="ost-muted"><?php echo $man['rows'] ? esc_html( sprintf( '%s کلمه ثبت شده · %s', ost_num( count( $man['rows'] ) ), 'only' === $man['mode'] ? 'تحلیل فقط با همین کلمات' : 'همراه کلمات سئو سیگنال' ) ) : 'اختیاری'; ?></small>
	</summary>
	<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php ost_form_fields( 'ost_kw_import' ); ?><input type="hidden" name="id" value="<?php echo (int) $id; ?>">
		<p class="ost-muted">ستون‌ها به ترتیب: <b>کلمه</b>، <b>جستجوی ماهانه</b> (اختیاری؛ خالی باشد از سئو سیگنال گرفته می‌شود)، <b>گروه</b> (اختیاری؛ هر گروه یک خوشه در پروپوزال می‌شود). سطر عنوان خودکار رد می‌شود. کلمات شما معتبر حساب می‌شوند و فقط حذف‌های خودتان رویشان اعمال می‌شود.</p>
		<div class="ost-grid g2">
			<label class="ost-field">ستون‌ها را از اکسل کپی کنید و اینجا بچسبانید
				<textarea name="kw_text" rows="6" dir="auto" placeholder="خرید گیتار آکوستیک	2400	گیتار&#10;آموزش گیتار مقدماتی	1900	آموزش"></textarea>
			</label>
			<div>
				<label class="ost-field">یا فایل (xlsx، csv، txt)
					<input type="file" name="kw_file" accept=".xlsx,.csv,.txt">
				</label>
				<div class="ost-field">نقش این کلمات
					<label class="ost-check"><input type="radio" name="mode" value="add" <?php checked( 'add', $man['mode'] ); ?>> همراه کلمات سئو سیگنال و عمق بازار</label>
					<label class="ost-check"><input type="radio" name="mode" value="only" <?php checked( 'only', $man['mode'] ); ?>> تحلیل فقط با همین کلمات</label>
				</div>
				<label class="ost-check"><input type="checkbox" name="append" value="1"> به فهرست قبلی اضافه شود (وگرنه جایگزین می‌شود)</label>
			</div>
		</div>
		<div class="ost-row">
			<button class="ost-btn primary" data-busy="در حال ثبت و گرفتن حجم‌ها…">ثبت کلمات</button>
			<?php if ( $man['rows'] ) : ?><button class="ost-btn ghost" name="clear" value="1" data-confirm="فهرست کلمات دستی حذف شود؟">حذف فهرست دستی</button><?php endif; ?>
		</div>
	</form>
</details>
<?php
if ( ! $a || empty( $a['market'] ) ) {
	echo '<div class="ost-empty sm"><p>بعد از جمع‌آوری کلمات (مرحله تحقیق کلمه در اجرای تحلیل) این صفحه پر می‌شود.</p><a class="ost-btn primary" href="' . esc_url( ost_proposal_url( $id ) ) . '">اجرای تحلیل</a></div>';
	return;
}
$m      = $a['market'];
$fl     = array_replace( array( 'zero' => 0, 'user' => 0, 'ai' => 0, 'irrelevant' => 0, 'evidence' => 0, 'kept' => 0 ), (array) ( $m['filtered'] ?? array() ) );
$ok     = ost_kw_review_confirmed( $p );
$wait   = 'waiting' === ( $p['job']['status'] ?? '' );
$topics = (array) ( $m['topics'] ?? array() );
$by_label = array( 'user' => 'حوزه حذف‌شده', 'ai' => 'هوش مصنوعی', 'rule' => 'بی‌ربط با کلمه اصلی', 'evidence' => 'بدون شواهد' );
$dropped  = (array) ( $m['dropped'] ?? array() );
?>
<?php if ( $wait && ! $ok ) : ?>
	<div class="ost-notice amber"><b>تحلیل منتظر تأیید کلمات است.</b> موضوع‌هایی را که کار نمی‌کنید بردارید، اگر کلمه درستی حذف شده برگردانید و «تأیید کلمات و نوشتن پروپوزال» را بزنید.</div>
<?php endif; ?>
<section class="ost-kpis">
	<div class="hl"><b><?php echo esc_html( ost_num( $fl['kept'] ) ); ?></b><span>کلمه واقعی و مرتبط در تحلیل</span></div>
	<div><b><?php echo esc_html( ost_compact( $m['total'] ) ); ?></b><span>جستجوی ماهانه همین کلمات</span></div>
	<div><b><?php echo esc_html( ost_num( $fl['user'] + $fl['ai'] + $fl['irrelevant'] + $fl['evidence'] ) ); ?></b><span>کلمه بی‌ربط کنار گذاشته شد</span></div>
	<div><b><?php echo esc_html( ost_num( $fl['zero'] ) ); ?></b><span>کلمه بدون جستجو حذف شد</span></div>
</section>
<?php if ( ! empty( $m['empty_seeds'] ) ) : ?>
	<div class="ost-notice amber">این کلمه‌های اصلی کاملاً کنار رفتند (همه کلماتشان بی‌ربط، بدون جستجو یا در حوزه‌هایی بود که کار نمی‌کنید): <b><?php echo esc_html( implode( '، ', $m['empty_seeds'] ) ); ?></b>. اگر اشتباه است، کلماتشان را از جدول «کنار گذاشته‌شده» برگردانید یا پاسخ «کار نمی‌کنیم» را در شناخت کسب‌وکار اصلاح کنید.</div>
<?php endif; ?>
<?php if ( ! empty( $m['ai_note'] ) ) : ?>
	<div class="ost-notice"><b>دیدگاه هوش مصنوعی:</b> <?php echo esc_html( $m['ai_note'] ); ?></div>
<?php endif; ?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php ost_form_fields( 'ost_kw_review' ); ?><input type="hidden" name="id" value="<?php echo (int) $id; ?>">
	<div class="ost-grid g2 ost-kwrev">
		<section class="ost-panel">
			<h2>موضوع‌هایی که در پروپوزال تحلیل می‌شوند <small class="ost-muted"><?php echo esc_html( ost_num( count( $topics ) ) ); ?> موضوع؛ تیک را بردارید تا کنار برود</small></h2>
			<div class="ost-scroll">
				<table class="ost-table sm">
					<thead><tr><th></th><th>موضوع</th><th>جستجو</th><th>خرید</th><th>نمونه کلمات</th><th title="کارفرما یا رقبا برایش رتبه دارند">رقبا</th></tr></thead>
					<tbody>
					<?php foreach ( array_slice( $topics, 0, 250, true ) as $t => $x ) : ?>
						<tr class="<?php echo $x['key'] ? 'is-key' : ''; ?>">
							<td><input type="hidden" name="topic_all[]" value="<?php echo esc_attr( $t ); ?>"><input type="checkbox" name="topic_on[]" value="<?php echo esc_attr( $t ); ?>" checked></td>
							<td><b><?php echo esc_html( $t ); ?></b><?php echo $x['key'] ? ' <span class="ost-pill navy" title="کلمه کلیدی اصلی">اصلی</span>' : ''; ?></td>
							<td><?php echo esc_html( ost_num( $x['v'] ) ); ?></td>
							<td><?php echo esc_html( ost_pct( $x['buy'] / max( 1, $x['v'] ) ) ); ?></td>
							<td class="kw"><?php echo esc_html( implode( '، ', array_slice( $x['words'], 0, 3 ) ) ); ?></td>
							<td><?php echo $x['ev'] ? '✓' : '<span class="ost-muted">—</span>'; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</section>
		<section class="ost-panel">
			<h2>کلمات کنار گذاشته‌شده <small class="ost-muted">اگر کلمه درستی حذف شده، تیک «برگردان» را بزنید</small></h2>
			<div class="ost-scroll">
				<table class="ost-table sm">
					<thead><tr><th>برگردان</th><th>کلمه</th><th>جستجو</th><th>دلیل</th></tr></thead>
					<tbody>
					<?php foreach ( array_slice( $dropped, 0, 250 ) as $d ) : $k = ost_norm( $d['w'] ); ?>
						<tr>
							<td><input type="hidden" name="restore_all[]" value="<?php echo esc_attr( $k ); ?>"><input type="checkbox" name="restore[]" value="<?php echo esc_attr( $k ); ?>"></td>
							<td><?php echo esc_html( $d['w'] ); ?></td>
							<td><?php echo esc_html( ost_num( $d['v'] ) ); ?></td>
							<td class="kw"><span class="ost-pill <?php echo 'ai' === $d['by'] ? 'navy' : 'gray'; ?>"><?php echo esc_html( $by_label[ $d['by'] ] ?? $d['by'] ); ?></span> <?php echo esc_html( $d['why'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					<?php if ( ! $dropped ) : ?><tr><td colspan="4">کلمه بی‌ربطی پیدا نشد.</td></tr><?php endif; ?>
					</tbody>
				</table>
			</div>
			<label class="ost-field">کلمات یا عبارت‌هایی که باید حذف شوند (هر خط یکی)
				<textarea name="add_words" rows="2" placeholder="مثلاً: دستبند پلیسی"></textarea>
			</label>
		</section>
	</div>
	<section class="ost-panel ost-row">
		<button class="ost-btn primary" name="confirm" value="1"><?php echo ost_icon( 'play', 16 ); // phpcs:ignore ?> تأیید کلمات و نوشتن پروپوزال</button>
		<button class="ost-btn">اعمال تغییرات و محاسبه دوباره</button>
		<label class="ost-check"><input type="checkbox" name="remember" value="1" checked> این انتخاب‌ها برای پروپوزال‌های بعدی همین سایت هم اعمال شود</label>
		<?php if ( $ok ) : ?><span class="ost-pill green">تأیید شده</span><?php endif; ?>
	</section>
</form>
