<?php
/**
 * Tab: demand and sales estimate — what the numbers are based on, and the manager's controls.
 *
 * @var array $p
 * @var array $a
 * @package Ostorlab
 */

defined( 'ABSPATH' ) || exit;

$id   = $p['id'];
$in   = $p['inputs'];
$est  = ost_estimate_settings( $id );
$e    = (array) ( $a['estimate'] ?? array() );
if ( ! $e ) {
	echo '<div class="ost-empty sm"><p>برای دیدن تخمین‌ها یک بار «به‌روزرسانی تحلیل» را بزنید.</p></div>';
	return;
}
$svc  = in_array( $in['business_type'], array( 'service', 'local', 'saas' ), true );
$unit = 'leads' === $e['basis'] ? 'سرنخ (تماس / درخواست)' : 'سفارش';
$rng  = function ( $lo, $hi, $fmt = 'ost_num' ) {
	return $fmt( $lo ) . ' تا ' . $fmt( $hi );
};
$money = function ( $v ) {
	return ost_toman( $v, true );
};
$rows = array(
	array( 'جستجوی ماهانه حوزه', ost_num( $e['measured'] ), ost_num( $e['total'] ) ),
	array( 'جستجوی آماده خرید', ost_num( $e['buyer'] ), ost_num( $e['buyer_all'] ) ),
	array( 'کلیک ماهانه از گوگل در پایان دوره', $rng( $e['list']['low']['clicks'], $e['list']['high']['clicks'] ), $rng( $e['all']['low']['clicks'], $e['all']['high']['clicks'] ) ),
	array( 'کلیک با نیت خرید', $rng( $e['list']['low']['buyer'], $e['list']['high']['buyer'] ), $rng( $e['all']['low']['buyer'], $e['all']['high']['buyer'] ) ),
	array( $unit . ' ماهانه از گوگل', $rng( $e['list']['low']['orders'], $e['list']['high']['orders'] ), $rng( $e['all']['low']['orders'], $e['all']['high']['orders'] ) ),
);
if ( $e['has_money'] ) {
	$rows[] = array( 'فروش ماهانه از گوگل در پایان دوره', $rng( $e['list']['low']['revenue'], $e['list']['high']['revenue'], $money ), $rng( $e['all']['low']['revenue'], $e['all']['high']['revenue'], $money ) );
	$rows[] = array( 'فروش اضافه در کل ' . ost_num( $in['months'] ) . ' ماه (نسبت به امروز)', $rng( $e['list']['low']['period'], $e['list']['high']['period'], $money ), $rng( $e['all']['low']['period'], $e['all']['high']['period'], $money ) );
}
$head = 'all' === $e['headline'] ? 1 : 0;
?>
<div class="ost-notice">
	<b>این عددها از کجا آمده؟</b> همه تقاضا و پیش‌بینی‌ها از <b><?php echo esc_html( ost_num( $e['keywords'] ) ); ?> کلمه بررسی‌شده</b> در <?php echo esc_html( ost_num( $e['clusters'] ) ); ?> خوشه (تب بازبینی کلمات) ساخته شده است. بعد از شروع، کلمات کم‌حجم و جدید زیادی پیدا می‌شود که در این فهرست نیست؛ «ضریب کلمات کشف‌نشده» تقاضای واقعی را برآورد می‌کند. در پروپوزال هر دو عدد شفاف و جدا نمایش داده می‌شود.
</div>
<div class="ost-grid g2">
	<section class="ost-panel">
		<h2>نتیجه تخمین <small class="ost-muted">ستون پررنگ همان عددی است که در خلاصه، پیش‌بینی، اسطرلاب و پکیج‌ها می‌آید</small></h2>
		<table class="ost-table ost-est">
			<thead><tr><th></th><th class="<?php echo $head ? '' : 'on'; ?>">فقط کلمات بررسی‌شده</th><th class="<?php echo $head ? 'on' : ''; ?>">با کلمات کشف‌نشده (×<?php echo esc_html( ost_num( $e['longtail'], 1 ) ); ?>)</th></tr></thead>
			<tbody>
				<?php foreach ( $rows as $r ) : ?>
					<tr><td><?php echo esc_html( $r[0] ); ?></td><td class="<?php echo $head ? '' : 'on'; ?>"><?php echo esc_html( $r[1] ); ?></td><td class="<?php echo $head ? 'on' : ''; ?>"><?php echo esc_html( $r[2] ); ?></td></tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p class="ost-muted">
			پیش‌بینی خودکار (بر اساس سهم رقبا): <?php echo esc_html( $rng( $e['auto']['low'] ?? 0, $e['auto']['high'] ?? 0 ) ); ?> کلیک ماهانه در پایان دوره، فقط از کلمات بررسی‌شده.
			<?php echo $e['manual'] ? 'الان عدد دستی شما جایگزین آن است.' : ( 100 !== (int) $e['adjust'] ? 'الان با ضریب ' . esc_html( ost_num( $e['adjust'] ) ) . '٪ تنظیم شده.' : '' ); ?>
			<?php echo ! $e['has_money'] ? '<br>برای نمایش فروش تومانی، «میانگین ارزش سفارش» را وارد کنید.' : ''; ?>
		</p>
	</section>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ost-panel ost-est-form">
		<?php ost_form_fields( 'ost_estimate' ); ?><input type="hidden" name="id" value="<?php echo (int) $id; ?>">
		<h2>تنظیم تخمین‌ها</h2>
		<label class="ost-field">ضریب کلمات کشف‌نشده (دم بلند)
			<select name="longtail"><?php foreach ( ost_longtail_options() as $v => $l ) { echo '<option value="' . esc_attr( $v ) . '" ' . selected( abs( (float) $v - (float) $est['longtail'] ) < 0.01, true, false ) . '>' . esc_html( $l ) . '</option>'; } ?></select>
			<small class="ost-muted">کلمات بررسی‌شده معمولاً فقط بخشی از کل جستجوهای یک حوزه‌اند؛ هرچه محصولات و مدل‌ها متنوع‌تر، ضریب بزرگ‌تر.</small>
		</label>
		<div class="ost-field">عدد اصلی پروپوزال
			<label class="ost-check"><input type="radio" name="headline" value="all" <?php checked( 'all', $est['headline'] ); ?>> با کلمات کشف‌نشده (تصویر واقعی‌تر بازار)</label>
			<label class="ost-check"><input type="radio" name="headline" value="list" <?php checked( 'list', $est['headline'] ); ?>> فقط کلمات بررسی‌شده (محتاطانه)</label>
		</div>
		<label class="ost-field">ضریب پیش‌بینی (٪)
			<input type="text" name="adjust" class="qty" value="<?php echo esc_attr( ost_num( $est['adjust'] ) ); ?>">
			<small class="ost-muted">۱۰۰ = پیش‌بینی خودکار. اگر شناخت شما از بازار یا برند کارفرما بیشتر است، کم یا زیادش کنید (۲۰ تا ۵۰۰).</small>
		</label>
		<div class="ost-field">یا عدد دستی: کلیک ماهانه پایان دوره (فقط کلمات بررسی‌شده)
			<div class="ost-row"><input type="text" name="low" class="qty" placeholder="حداقل" value="<?php echo esc_attr( $est['low'] ? ost_num( $est['low'] ) : '' ); ?>"> تا <input type="text" name="high" class="qty" placeholder="حداکثر" value="<?php echo esc_attr( $est['high'] ? ost_num( $est['high'] ) : '' ); ?>"></div>
			<small class="ost-muted">خالی = خودکار. با عدد دستی، ضریب پیش‌بینی نادیده گرفته می‌شود.</small>
		</div>
		<?php if ( $svc ) : ?>
			<label class="ost-field">نرخ تبدیل بازدید به سرنخ (٪) <input type="text" name="lead_rate" class="qty" value="<?php echo esc_attr( $est['lead_rate'] ? ost_num( $est['lead_rate'], 1 ) : '' ); ?>" placeholder="<?php echo esc_attr( ost_num( ost_setting( 'lead_rate', 3 ), 1 ) ); ?>"></label>
			<label class="ost-field">چند درصد سرنخ‌ها مشتری می‌شوند؟ (٪) <input type="text" name="close_rate" class="qty" value="<?php echo esc_attr( ost_num( $est['close_rate'] ) ); ?>"></label>
			<label class="ost-field">ارزش هر مشتری / قرارداد (تومان) <input type="text" name="aov" class="ost-money" value="<?php echo esc_attr( $in['aov'] ? ost_num( $in['aov'] ) : '' ); ?>"></label>
		<?php else : ?>
			<label class="ost-field">نرخ تبدیل خرید (٪) <input type="text" name="conversion_rate" class="qty" value="<?php echo esc_attr( ost_num( $in['conversion_rate'], 1 ) ); ?>"><small class="ost-muted">معمول فروشگاه‌های ایرانی ۰٫۵ تا ۲٪ کلیک‌های خرید.</small></label>
			<label class="ost-field">میانگین ارزش سفارش (تومان) <input type="text" name="aov" class="ost-money" value="<?php echo esc_attr( $in['aov'] ? ost_num( $in['aov'] ) : '' ); ?>"></label>
		<?php endif; ?>
		<div class="ost-row">
			<button class="ost-btn primary">محاسبه دوباره</button>
			<button class="ost-btn ghost" name="reset" value="1" data-confirm="تنظیمات تخمین به حالت خودکار برگردد؟">بازگشت به خودکار</button>
		</div>
	</form>
</div>
