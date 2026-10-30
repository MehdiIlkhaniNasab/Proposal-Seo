<?php
/**
 * New / edit proposal form.
 *
 * @var array|null $p
 * @package Fanoos
 */

defined( 'ABSPATH' ) || exit;
$in  = $p ? $p['inputs'] : fns_input_defaults();
$val = function ( $k ) use ( $in ) {
	return is_array( $in[ $k ] ) ? implode( "\n", $in[ $k ] ) : (string) $in[ $k ];
};
$cost = $p ? fns_ss_cost_estimate( $p ) : null;
?>
<div class="fns-bar"><h1><?php echo $p ? 'ویرایش پروپوزال' : 'پروپوزال جدید'; ?></h1></div>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fns-form" id="fns-proposal-form">
	<?php wp_nonce_field( 'fns_save_proposal' ); ?>
	<input type="hidden" name="action" value="fns_save_proposal">
	<input type="hidden" name="id" value="<?php echo (int) ( $p['id'] ?? 0 ); ?>">

	<section class="fns-panel">
		<h2><span class="n">۱</span> کارفرما</h2>
		<div class="fns-grid g3">
			<label>نام کسب‌وکار / برند<input type="text" name="in[client_name]" value="<?php echo esc_attr( $val( 'client_name' ) ); ?>" required placeholder="مثلاً مبلمان آرامش"></label>
			<label>نام فرد تصمیم‌گیر (اختیاری)<input type="text" name="in[contact_person]" value="<?php echo esc_attr( $val( 'contact_person' ) ); ?>" placeholder="آقای / خانم …"></label>
			<label>حوزه فعالیت<input type="text" name="in[industry]" value="<?php echo esc_attr( $val( 'industry' ) ); ?>" placeholder="مثلاً مبلمان و دکوراسیون"></label>
			<label>نوع کسب‌وکار
				<select name="in[business_type]">
					<?php foreach ( fns_business_types() as $k => $l ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $in['business_type'], $k ); ?>><?php echo esc_html( $l ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>میانگین ارزش هر سفارش / قرارداد (تومان)<input type="text" inputmode="numeric" class="fns-money" name="in[aov]" value="<?php echo esc_attr( $in['aov'] ? fns_num( $in['aov'] ) : '' ); ?>" placeholder="مثلاً ۲۸٬۰۰۰٬۰۰۰"><small>برای تخمین درآمد رقبا و درآمد قابل انتظار؛ خالی = ارزش تبلیغاتی کلیک‌ها.</small></label>
			<label>نرخ تبدیل (٪)<input type="text" name="in[conversion_rate]" value="<?php echo esc_attr( $val( 'conversion_rate' ) ); ?>"><small>میانگین جهانی فروشگاه‌ها ۱٫۵ تا ۳٪.</small></label>
		</div>
		<label>هدف کارفرما (اختیاری)<textarea name="in[goals]" rows="2" placeholder="مثلاً فروش آنلاین مبل ال و راحتی در تهران؛ کم کردن وابستگی به تبلیغات"><?php echo esc_textarea( $val( 'goals' ) ); ?></textarea></label>
	</section>

	<section class="fns-panel">
		<h2><span class="n">۲</span> سایت و دسترسی‌ها</h2>
		<div class="fns-grid g3">
			<label>دامنه سایت کارفرما<input type="text" dir="ltr" name="in[domain]" value="<?php echo esc_attr( $val( 'domain' ) ); ?>" placeholder="example.ir"><small>اگر سایت ندارد خالی بگذارید؛ پروپوزال «شروع از صفر» ساخته می‌شود.</small></label>
			<label>نام‌های برند (برای جدا کردن جستجوی برندی)<input type="text" name="in[brand_terms]" value="<?php echo esc_attr( implode( '، ', $in['brand_terms'] ) ); ?>" placeholder="آرامش، aramesh"></label>
			<label>تعداد صفحات برای خزش اسکریمینگ فراگ<input type="text" name="in[crawl_limit]" value="<?php echo esc_attr( $val( 'crawl_limit' ) ); ?>"><small>از نقشه سایت هر دامنه؛ پیش‌فرض ۵۰۰.</small></label>
		</div>
		<div class="fns-checks">
			<label class="fns-switch"><input type="checkbox" name="in[has_site]" value="1" <?php checked( $in['has_site'] ); ?>><span></span> کارفرما سایت فعال دارد</label>
			<label class="fns-switch"><input type="checkbox" name="in[gsc_access]" value="1" <?php checked( $in['gsc_access'] ); ?>><span></span> دسترسی سرچ کنسول داده است</label>
			<label class="fns-switch"><input type="checkbox" name="in[cms_access]" value="1" <?php checked( $in['cms_access'] ); ?>><span></span> دسترسی مدیریت سایت داده است</label>
		</div>
		<label class="fns-gsc">پراپرتی سرچ کنسول (اختیاری)<input type="text" dir="ltr" name="in[gsc_property]" value="<?php echo esc_attr( $val( 'gsc_property' ) ); ?>" placeholder="sc-domain:example.ir"><small><?php echo fns_google_connected() ? 'خالی بگذارید تا خودکار پیدا شود. ایمیل اتصال: ' . esc_html( fns_google_service_email() ?: 'اتصال سکان' ) : 'اتصال گوگل در تنظیمات برقرار نیست؛ بدون سرچ کنسول هم تحلیل کامل انجام می‌شود.'; ?></small></label>
	</section>

	<section class="fns-panel">
		<h2><span class="n">۳</span> کلمات کلیدی و رقبا</h2>
		<div class="fns-grid g2">
			<label>کلمات کلیدی اصلی (۵ تا ۷ کلمه، هر خط یکی)
				<textarea name="in[keywords]" rows="7" required data-count="kw" placeholder="مبل راحتی&#10;مبل ال&#10;سرویس خواب"><?php echo esc_textarea( $val( 'keywords' ) ); ?></textarea>
				<small>هر کلمه یک «خوشه» می‌شود؛ کلمات مرتبط، حجم جستجو و نیت خرید هر خوشه از سئو سیگنال درمی‌آید. <b class="fns-count" data-for="kw"></b></small>
			</label>
			<label>رقبا (۳ تا ۵ دامنه یا لینک، هر خط یکی)
				<textarea name="in[competitors]" rows="7" dir="ltr" data-count="cp" placeholder="competitor1.ir&#10;competitor2.com"><?php echo esc_textarea( $val( 'competitors' ) ); ?></textarea>
				<small>رقبای واقعی در گوگل (نه لزوماً رقبای حضوری). <b class="fns-count" data-for="cp"></b></small>
			</label>
		</div>
	</section>

	<section class="fns-panel">
		<h2><span class="n">۴</span> دوره و یادداشت</h2>
		<div class="fns-grid g3">
			<label>طول دوره (ماه)
				<select name="in[months]">
					<?php foreach ( array( 8, 9, 10, 12 ) as $m ) : ?>
						<option value="<?php echo (int) $m; ?>" <?php selected( (int) $in['months'], $m ); ?>><?php echo esc_html( fns_num( $m ) ); ?> ماه</option>
					<?php endforeach; ?>
				</select>
			</label>
		</div>
		<label>یادداشت برای متن پروپوزال (اختیاری؛ به هوش مصنوعی داده می‌شود)<textarea name="in[notes]" rows="2" placeholder="مثلاً کارفرما قبلاً با آژانس دیگری کار کرده و از گزارش‌های مبهم ناراضی بوده"><?php echo esc_textarea( $val( 'notes' ) ); ?></textarea></label>
	</section>

	<div class="fns-actions sticky">
		<button class="fns-btn primary lg" name="run" value="1"><?php echo fns_icon( 'play' ); // phpcs:ignore ?> ذخیره و شروع تحلیل</button>
		<button class="fns-btn lg">فقط ذخیره</button>
		<?php if ( $cost ) : ?><span class="fns-muted">مصرف سئو سیگنال: حدود <?php echo esc_html( fns_num( $cost['total'] ) ); ?> درخواست (پاسخ‌های قبلی از کش خوانده می‌شوند)</span><?php endif; ?>
	</div>
</form>
