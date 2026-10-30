<?php
/**
 * Settings: connections, agency brand, prices, off-page quality rules.
 *
 * @var string $tab
 * @package Fanoos
 */

defined( 'ABSPATH' ) || exit;

$s    = fns_settings();
$tabs = array( 'connections' => 'اتصال‌ها', 'agency' => 'آژانس و برند', 'pricing' => 'قیمت‌ها و پیش‌فرض‌ها', 'quality' => 'قواعد کیفیت لینک' );
$tab  = isset( $tabs[ $tab ] ) ? $tab : 'connections';
$secret = function ( $key, $label, $hint = '' ) use ( $s ) {
	$from = fns_setting_from_sokan( $key );
	echo '<label>' . esc_html( $label ) . '<input type="password" autocomplete="new-password" dir="ltr" name="s[' . esc_attr( $key ) . ']" placeholder="' . esc_attr( $s[ $key ] ? fns_mask( $s[ $key ] ) : ( $from ? 'از تنظیمات سکان' : '' ) ) . '">';
	if ( $s[ $key ] ) {
		echo '<span class="fns-inline"><input type="checkbox" name="clear[]" value="' . esc_attr( $key ) . '"> پاک شود</span>';
	}
	echo $hint ? '<small>' . wp_kses_post( $hint ) . '</small>' : '';
	echo '</label>';
};
$text = function ( $key, $label, $hint = '', $ltr = false ) use ( $s ) {
	echo '<label>' . esc_html( $label ) . '<input type="text" ' . ( $ltr ? 'dir="ltr"' : '' ) . ' name="s[' . esc_attr( $key ) . ']" value="' . esc_attr( (string) $s[ $key ] ) . '">' . ( $hint ? '<small>' . wp_kses_post( $hint ) . '</small>' : '' ) . '</label>';
};
$money = function ( $key, $label, $hint = '' ) use ( $s ) {
	echo '<label>' . esc_html( $label ) . '<input type="text" class="fns-money" name="s[' . esc_attr( $key ) . ']" value="' . esc_attr( fns_num( $s[ $key ] ) ) . '">' . ( $hint ? '<small>' . esc_html( $hint ) . '</small>' : '' ) . '</label>';
};
$area = function ( $key, $label, $rows = 4, $hint = '', $ltr = false ) use ( $s ) {
	echo '<label>' . esc_html( $label ) . '<textarea ' . ( $ltr ? 'dir="ltr"' : '' ) . ' name="s[' . esc_attr( $key ) . ']" rows="' . (int) $rows . '">' . esc_textarea( (string) $s[ $key ] ) . '</textarea>' . ( $hint ? '<small>' . wp_kses_post( $hint ) . '</small>' : '' ) . '</label>';
};
$test = function ( $svc ) {
	echo '<button type="button" class="fns-btn sm" data-test="' . esc_attr( $svc ) . '">تست اتصال</button><span class="fns-test-out" data-out="' . esc_attr( $svc ) . '"></span>';
};
?>
<div class="fns-bar"><h1>تنظیمات فانوس</h1></div>
<nav class="fns-tabs">
	<?php foreach ( $tabs as $k => $l ) : ?>
		<a href="<?php echo esc_url( add_query_arg( 'tab', $k, fns_admin_url( 'settings' ) ) ); ?>" class="<?php echo $tab === $k ? 'on' : ''; ?>"><?php echo esc_html( $l ); ?></a>
	<?php endforeach; ?>
</nav>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fns-form">
	<?php wp_nonce_field( 'fns_save_settings' ); ?>
	<input type="hidden" name="action" value="fns_save_settings">
	<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">

<?php if ( 'connections' === $tab ) : ?>
	<?php if ( fns_sokan_active() ) : ?>
		<section class="fns-panel">
			<input type="hidden" name="has_reuse_sokan" value="1">
			<label class="fns-switch"><input type="checkbox" name="s[reuse_sokan]" value="1" <?php checked( $s['reuse_sokan'] ); ?>><span></span> استفاده از تنظیمات سکان برای فیلدهای خالی (کلید سئو سیگنال، هوش مصنوعی و اتصال گوگل)</label>
		</section>
	<?php endif; ?>
	<section class="fns-panel">
		<h2>سئو سیگنال <small class="fns-muted">کلمات، رقبا، شکاف کلمات، رپورتاژ</small></h2>
		<div class="fns-grid g3">
			<?php $secret( 'seosignal_key', 'کلید API (ایمیل:توکن)', 'پنل سئو سیگنال › منوی کاربری › مدیریت API. برای بخش رپورتاژ، سرویس «دستیار رپورتاژ» باید روی پلن فعال باشد.' ); ?>
			<?php $text( 'seosignal_daily_cap', 'سقف درخواست روزانه فانوس', 'از ۵۰ درخواست روزانه حساب؛ بقیه برای پنل و سکان می‌ماند.' ); ?>
			<?php $text( 'seosignal_cache_days', 'نگهداری پاسخ‌ها (روز)', 'اجرای دوباره در این مدت سهمیه مصرف نمی‌کند.' ); ?>
		</div>
		<?php $test( 'seosignal' ); ?>
		<?php $u = fns_ss_usage(); ?>
		<p class="fns-muted">مصرف امروز فانوس: <?php echo esc_html( fns_num( $u['used'] ) ); ?><?php echo $u['sokan'] ? ' · سکان: ' . esc_html( fns_num( $u['sokan'] ) ) : ''; ?> · باقی‌مانده مجاز: <?php echo esc_html( fns_num( fns_ss_remaining() ) ); ?></p>
	</section>
	<section class="fns-panel">
		<h2>هوش مصنوعی <small class="fns-muted">نوشتن خلاصه اجرایی و متن‌ها</small></h2>
		<div class="fns-grid g3">
			<label>سرویس
				<select name="s[ai_provider]">
					<option value="gateway" <?php selected( $s['ai_provider'], 'gateway' ); ?>>درگاه سازگار با OpenAI (GapGPT)</option>
					<option value="anthropic" <?php selected( $s['ai_provider'], 'anthropic' ); ?>>Anthropic مستقیم</option>
				</select>
			</label>
			<?php $text( 'ai_base_url', 'آدرس درگاه', 'پیش‌فرض: https://api.gapgpt.app/v1 (همان API سکان)', true ); ?>
			<?php $secret( 'ai_gateway_key', 'کلید درگاه' ); ?>
			<?php $secret( 'anthropic_key', 'کلید Anthropic (در صورت انتخاب)' ); ?>
			<?php $text( 'ai_model', 'مدل نوشتن', 'مثلاً claude-sonnet-5-5 یا gpt-5', true ); ?>
			<?php $text( 'ai_budget', 'سقف بودجه ماهانه (دلار)' ); ?>
		</div>
		<?php $test( 'ai' ); ?>
		<p class="fns-muted">مصرف این ماه فانوس: <?php echo esc_html( fns_num( fns_ai_usage()['cost'], 2 ) ); ?> دلار · <?php echo fns_ai_via_sokan() ? 'درخواست‌ها از طریق سکان ارسال می‌شود.' : ''; ?></p>
	</section>
	<section class="fns-panel">
		<h2>سمراش <small class="fns-muted">بک‌لینک، دامنه ارجاع‌دهنده، انکر، لینک صفحات پرفروش</small></h2>
		<input type="hidden" name="has_semrush_enabled" value="1">
		<label class="fns-switch"><input type="checkbox" name="s[semrush_enabled]" value="1" <?php checked( $s['semrush_enabled'] ); ?>><span></span> فعال (فقط وقتی اکانت Business با Backlinks API دارید)</label>
		<div class="fns-grid g3">
			<?php $secret( 'semrush_key', 'کلید API سمراش' ); ?>
			<?php $text( 'semrush_limit', 'تعداد بک‌لینک خوانده‌شده برای هر دامنه', 'هر ردیف واحد API مصرف می‌کند؛ حدود ' . fns_num( fns_semrush_units_estimate() ) . ' واحد برای هر دامنه.' ); ?>
			<?php $text( 'semrush_min_ascore', 'حداقل Authority Score منبع باکیفیت' ); ?>
		</div>
		<?php $test( 'semrush' ); ?>
	</section>
	<section class="fns-panel">
		<h2>سرچ کنسول گوگل <small class="fns-muted">فقط وقتی کارفرما دسترسی داده</small></h2>
		<p class="fns-muted">وضعیت: <?php echo 'own' === fns_google_mode() ? 'سرویس اکانت فانوس' : ( 'sokan' === fns_google_mode() ? 'اتصال گوگل سکان' : 'متصل نیست' ); ?><?php echo fns_google_service_email() ? ' · ایمیلی که کارفرما باید در Search Console › Users اضافه کند: <b dir="ltr">' . esc_html( fns_google_service_email() ) . '</b>' : ''; ?></p>
		<?php $area( 'google_service_account', 'JSON سرویس اکانت (Google Cloud › IAM › Service Accounts › Keys)', 4, 'API «Google Search Console» در همان پروژه گوگل کلاد باید فعال باشد.', true ); ?>
		<?php $test( 'google' ); ?>
	</section>
	<section class="fns-panel">
		<h2>ساخت PDF</h2>
		<div class="fns-grid g3">
			<label>روش
				<select name="s[pdf_service]">
					<option value="browser" <?php selected( $s['pdf_service'], 'browser' ); ?>>مرورگر (چاپ به PDF) — بدون نیاز به سرویس</option>
					<option value="gotenberg" <?php selected( $s['pdf_service'], 'gotenberg' ); ?>>سرویس Gotenberg — دانلود مستقیم</option>
				</select>
			</label>
			<?php $text( 'pdf_service_url', 'آدرس Gotenberg', 'مثلاً http://127.0.0.1:3000 (docker run -p 3000:3000 gotenberg/gotenberg:8)', true ); ?>
			<?php $secret( 'pdf_service_token', 'توکن / Basic Auth (اختیاری)' ); ?>
		</div>
		<?php $test( 'pdf' ); ?>
	</section>
	<section class="fns-panel">
		<h2>شبکه</h2>
		<label>مسیر درخواست‌ها با پروکسی وردپرس (WP_PROXY_HOST)
			<select name="s[proxy_mode]">
				<option value="smart" <?php selected( $s['proxy_mode'], 'smart' ); ?>>هوشمند: فقط گوگل، سمراش و هوش مصنوعی خارجی از پروکسی</option>
				<option value="all" <?php selected( $s['proxy_mode'], 'all' ); ?>>همه درخواست‌ها</option>
				<option value="none" <?php selected( $s['proxy_mode'], 'none' ); ?>>هیچ‌کدام</option>
			</select>
		</label>
	</section>

<?php elseif ( 'agency' === $tab ) : ?>
	<section class="fns-panel">
		<h2>برند آژانس در پروپوزال</h2>
		<div class="fns-grid g3">
			<?php $text( 'agency_name', 'نام آژانس' ); ?>
			<?php $text( 'agency_tagline', 'شعار' ); ?>
			<label>لوگو<span class="fns-logo-pick"><img src="<?php echo esc_url( fns_logo_url() ); ?>" alt="" id="fns-logo-img"><input type="text" dir="ltr" name="s[agency_logo]" id="fns-logo" value="<?php echo esc_attr( $s['agency_logo'] ); ?>" placeholder="لوگوی رونق (پیش‌فرض)"><button type="button" class="fns-btn sm" id="fns-logo-btn">انتخاب از رسانه</button></span><small>برای کیفیت بهتر PNG با عرض حداقل ۶۰۰ پیکسل و پس‌زمینه شفاف.</small></label>
			<?php $text( 'consultant_name', 'نام مشاور (روی جلد)' ); ?>
			<?php $text( 'consultant_title', 'سمت' ); ?>
			<?php $text( 'agency_site', 'وب‌سایت', '', true ); ?>
			<?php $text( 'agency_phone', 'تلفن', '', true ); ?>
			<?php $text( 'agency_email', 'ایمیل', '', true ); ?>
			<?php $text( 'agency_address', 'آدرس' ); ?>
		</div>
		<?php $area( 'agency_about', 'معرفی کوتاه آژانس (اسلاید نمونه‌کار)', 3 ); ?>
		<?php $area( 'portfolio', 'نمونه‌کارها (هر خط: عنوان | نتیجه اصلی | توضیح)', 5, 'تا ۶ مورد در اسلاید نمایش داده می‌شود. خالی = اسلاید حذف می‌شود.' ); ?>
	</section>
	<section class="fns-panel">
		<h2>رنگ‌های تم</h2>
		<div class="fns-grid g3">
			<label>آبی اصلی<input type="color" name="s[theme_primary]" value="<?php echo esc_attr( $s['theme_primary'] ); ?>"></label>
			<label>آبی آسمانی (تأکید)<input type="color" name="s[theme_accent]" value="<?php echo esc_attr( $s['theme_accent'] ); ?>"></label>
			<label>سرمه‌ای (پس‌زمینه جلد)<input type="color" name="s[theme_dark]" value="<?php echo esc_attr( $s['theme_dark'] ); ?>"></label>
		</div>
	</section>

<?php elseif ( 'pricing' === $tab ) : ?>
	<section class="fns-panel">
		<h2>قیمت‌های پیش‌فرض (تومان)</h2>
		<p class="fns-muted">در هر پروپوزال قابل تغییر است. قیمت رپورتاژ اگر سئو سیگنال قیمت رپورتاژهای رقبا را داشته باشد، از میانه آن‌ها گرفته می‌شود.</p>
		<div class="fns-grid g3">
			<?php $money( 'fee_monthly', 'دستمزد ماهانه تیم سئو (پکیج پیشنهادی)', 'پکیج پایه ۷۵٪ و رشد سریع ۱۴۰٪ این عدد.' ); ?>
			<?php $money( 'price_landing', 'صفحه فرود / دسته‌بندی' ); ?>
			<?php $money( 'price_article', 'مقاله تخصصی' ); ?>
			<?php $money( 'price_update', 'به‌روزرسانی هر صفحه' ); ?>
			<?php $money( 'price_reportage', 'رپورتاژ (اگر داده رقبا نبود)' ); ?>
			<?php $money( 'price_backlink', 'بک‌لینک تخصصی مکمل' ); ?>
		</div>
	</section>
	<section class="fns-panel">
		<h2>پیش‌فرض‌های تخمین</h2>
		<div class="fns-grid g3">
			<label>طول دوره پیش‌فرض<select name="s[default_months]"><?php foreach ( array( 8, 9, 10, 12 ) as $m ) { echo '<option value="' . (int) $m . '" ' . selected( (int) $s['default_months'], $m, false ) . '>' . esc_html( fns_num( $m ) ) . ' ماه</option>'; } ?></select></label>
			<?php $text( 'conversion_rate', 'نرخ تبدیل فروشگاه (٪)', 'میانگین جهانی ۱٫۵ تا ۳٪' ); ?>
			<?php $text( 'lead_rate', 'نرخ سرنخ خدماتی (٪)' ); ?>
			<?php $money( 'click_value', 'ارزش تبلیغاتی هر کلیک', 'برای «ارزش معادل تبلیغات» وقتی ارزش سفارش وارد نشده.' ); ?>
		</div>
	</section>

<?php else : ?>
	<section class="fns-panel">
		<h2>قواعد تشخیص رپورتاژ و بک‌لینک باکیفیت</h2>
		<div class="fns-grid g3">
			<?php $area( 'generic_anchors', 'انکرهای بی‌ارزش (هر خط یکی)', 12 ); ?>
			<?php $area( 'spam_patterns', 'الگوهای منبع کم‌ارزش (بخشی از آدرس)', 12, 'انجمن، کامنت، پروفایل، دایرکتوری، وبلاگ‌های رایگان …', true ); ?>
			<?php $area( 'iran_tlds', 'پسوندهای دامنه ایرانی', 12, 'به‌علاوه منابعی که عنوان فارسی دارند یا کشورشان IR است.', true ); ?>
		</div>
	</section>
<?php endif; ?>
	<div class="fns-actions sticky"><button class="fns-btn primary lg">ذخیره تنظیمات</button></div>
</form>
