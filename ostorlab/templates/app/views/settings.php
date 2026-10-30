<?php
/**
 * Settings: connections, agency brand, prices, off-page quality rules.
 *
 * @var string $tab
 * @package Ostorlab
 */

defined( 'ABSPATH' ) || exit;

$s    = ost_settings();
$tabs = array( 'connections' => 'اتصال‌ها', 'agency' => 'آژانس و برند', 'pricing' => 'قیمت‌ها و پیش‌فرض‌ها', 'quality' => 'قواعد کیفیت لینک' );
$tab  = isset( $tabs[ $tab ] ) ? $tab : 'connections';
$secret = function ( $key, $label, $hint = '' ) use ( $s ) {
	$from = ost_setting_from_sokan( $key );
	echo '<label>' . esc_html( $label ) . '<input type="password" autocomplete="new-password" dir="ltr" name="s[' . esc_attr( $key ) . ']" placeholder="' . esc_attr( $s[ $key ] ? ost_mask( $s[ $key ] ) : ( $from ? 'از تنظیمات سکان' : '' ) ) . '">';
	if ( $s[ $key ] ) {
		echo '<span class="ost-inline"><input type="checkbox" name="clear[]" value="' . esc_attr( $key ) . '"> پاک شود</span>';
	}
	echo $hint ? '<small>' . wp_kses_post( $hint ) . '</small>' : '';
	echo '</label>';
};
$text = function ( $key, $label, $hint = '', $ltr = false ) use ( $s ) {
	echo '<label>' . esc_html( $label ) . '<input type="text" ' . ( $ltr ? 'dir="ltr"' : '' ) . ' name="s[' . esc_attr( $key ) . ']" value="' . esc_attr( (string) $s[ $key ] ) . '">' . ( $hint ? '<small>' . wp_kses_post( $hint ) . '</small>' : '' ) . '</label>';
};
$money = function ( $key, $label, $hint = '' ) use ( $s ) {
	echo '<label>' . esc_html( $label ) . '<input type="text" class="ost-money" name="s[' . esc_attr( $key ) . ']" value="' . esc_attr( ost_num( $s[ $key ] ) ) . '">' . ( $hint ? '<small>' . esc_html( $hint ) . '</small>' : '' ) . '</label>';
};
$area = function ( $key, $label, $rows = 4, $hint = '', $ltr = false ) use ( $s ) {
	echo '<label>' . esc_html( $label ) . '<textarea ' . ( $ltr ? 'dir="ltr"' : '' ) . ' name="s[' . esc_attr( $key ) . ']" rows="' . (int) $rows . '">' . esc_textarea( (string) $s[ $key ] ) . '</textarea>' . ( $hint ? '<small>' . wp_kses_post( $hint ) . '</small>' : '' ) . '</label>';
};
$test = function ( $svc ) {
	echo '<button type="button" class="ost-btn sm" data-test="' . esc_attr( $svc ) . '">تست اتصال</button><span class="ost-test-out" data-out="' . esc_attr( $svc ) . '"></span>';
};
?>
<header class="ost-head"><div><p class="ost-crumb">اسطرلاب</p><h1>تنظیمات و اتصال‌ها</h1></div></header>
<nav class="ost-tabs">
	<?php foreach ( $tabs as $k => $l ) : ?>
		<a href="<?php echo esc_url( ost_url( 'settings/' . $k ) ); ?>" class="<?php echo $tab === $k ? 'on' : ''; ?>"><?php echo esc_html( $l ); ?></a>
	<?php endforeach; ?>
</nav>
<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ost-form">
	<?php ost_form_fields( 'ost_save_settings' ); ?>
	<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">

<?php if ( 'connections' === $tab ) : ?>
	<?php if ( ost_sokan_active() ) : ?>
		<section class="ost-panel">
			<input type="hidden" name="has_reuse_sokan" value="1">
			<label class="ost-switch"><input type="checkbox" name="s[reuse_sokan]" value="1" <?php checked( $s['reuse_sokan'] ); ?>><span></span> استفاده از تنظیمات سکان برای فیلدهای خالی (کلید سئو سیگنال، هوش مصنوعی و اتصال گوگل)</label>
		</section>
	<?php endif; ?>
	<section class="ost-panel">
		<h2>سئو سیگنال <small class="ost-muted">کلمات، رقبا، شکاف کلمات، رپورتاژ</small></h2>
		<div class="ost-grid g3">
			<?php $secret( 'seosignal_key', 'کلید API (ایمیل:توکن)', 'پنل سئو سیگنال › منوی کاربری › مدیریت API. برای بخش رپورتاژ، سرویس «دستیار رپورتاژ» باید روی پلن فعال باشد.' ); ?>
			<?php $text( 'seosignal_daily_cap', 'سقف درخواست روزانه اسطرلاب', 'از ۵۰ درخواست روزانه حساب؛ بقیه برای پنل و سکان می‌ماند.' ); ?>
			<?php $text( 'seosignal_cache_days', 'نگهداری پاسخ‌ها (روز)', 'اجرای دوباره در این مدت سهمیه مصرف نمی‌کند.' ); ?>
		</div>
		<?php $test( 'seosignal' ); ?>
		<?php $u = ost_ss_usage(); ?>
		<p class="ost-muted">مصرف امروز اسطرلاب: <?php echo esc_html( ost_num( $u['used'] ) ); ?><?php echo $u['sokan'] ? ' · سکان: ' . esc_html( ost_num( $u['sokan'] ) ) : ''; ?> · باقی‌مانده مجاز: <?php echo esc_html( ost_num( ost_ss_remaining() ) ); ?></p>
	</section>
	<section class="ost-panel">
		<h2>هوش مصنوعی <small class="ost-muted">نوشتن خلاصه اجرایی و متن‌ها</small></h2>
		<div class="ost-grid g3">
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
		<p class="ost-muted">مصرف این ماه اسطرلاب: <?php echo esc_html( ost_num( ost_ai_usage()['cost'], 2 ) ); ?> دلار · <?php echo ost_ai_via_sokan() ? 'درخواست‌ها از طریق سکان ارسال می‌شود.' : ''; ?></p>
	</section>
	<section class="ost-panel">
		<h2>سمراش <small class="ost-muted">بک‌لینک، دامنه ارجاع‌دهنده، انکر، لینک صفحات پرفروش</small></h2>
		<input type="hidden" name="has_semrush_enabled" value="1">
		<label class="ost-switch"><input type="checkbox" name="s[semrush_enabled]" value="1" <?php checked( $s['semrush_enabled'] ); ?>><span></span> فعال (فقط وقتی اکانت Business با Backlinks API دارید)</label>
		<div class="ost-grid g3">
			<?php $secret( 'semrush_key', 'کلید API سمراش' ); ?>
			<?php $text( 'semrush_limit', 'تعداد بک‌لینک خوانده‌شده برای هر دامنه', 'هر ردیف واحد API مصرف می‌کند؛ حدود ' . ost_num( ost_semrush_units_estimate() ) . ' واحد برای هر دامنه.' ); ?>
			<?php $text( 'semrush_min_ascore', 'حداقل Authority Score منبع باکیفیت' ); ?>
		</div>
		<?php $test( 'semrush' ); ?>
	</section>
	<section class="ost-panel">
		<h2>سرچ کنسول گوگل <small class="ost-muted">فقط وقتی کارفرما دسترسی داده</small></h2>
		<p class="ost-muted">وضعیت: <?php echo 'own' === ost_google_mode() ? 'سرویس اکانت اسطرلاب' : ( 'sokan' === ost_google_mode() ? 'اتصال گوگل سکان' : 'متصل نیست' ); ?><?php echo ost_google_service_email() ? ' · ایمیلی که کارفرما باید در Search Console › Users اضافه کند: <b dir="ltr">' . esc_html( ost_google_service_email() ) . '</b>' : ''; ?></p>
		<?php $area( 'google_service_account', 'JSON سرویس اکانت (Google Cloud › IAM › Service Accounts › Keys)', 4, 'API «Google Search Console» در همان پروژه گوگل کلاد باید فعال باشد.', true ); ?>
		<?php $test( 'google' ); ?>
	</section>
	<section class="ost-panel">
		<h2>ساخت PDF</h2>
		<div class="ost-grid g3">
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
	<section class="ost-panel">
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
	<section class="ost-panel">
		<h2>برند آژانس در پروپوزال</h2>
		<div class="ost-grid g3">
			<?php $text( 'agency_name', 'نام آژانس' ); ?>
			<?php $text( 'agency_tagline', 'شعار' ); ?>
			<label>لوگو<span class="ost-logo-pick"><img src="<?php echo esc_url( ost_logo_url() ); ?>" alt="" id="ost-logo-img"><input type="file" name="logo_file" accept="image/*"><input type="hidden" name="s[agency_logo]" value="<?php echo esc_attr( $s['agency_logo'] ); ?>"></span><small>برای کیفیت بهتر PNG با عرض حداقل ۶۰۰ پیکسل و پس‌زمینه شفاف.</small></label>
			<?php $text( 'agency_site', 'وب‌سایت', '', true ); ?>
			<?php $text( 'agency_phone', 'تلفن آژانس', '', true ); ?>
			<?php $text( 'agency_email', 'ایمیل آژانس', '', true ); ?>
			<?php $text( 'agency_address', 'آدرس' ); ?>
			<?php $text( 'agency_instagram', 'اینستاگرام', 'مثلاً @ronagh.agency', true ); ?>
			<?php $text( 'agency_linkedin', 'لینکدین', '', true ); ?>
		</div>
		<?php $area( 'agency_stats', 'آمار اعتبار آژانس (هر خط: عنوان | عدد) — در اسلاید «تیم شما»', 3, 'مثلاً: پروژه سئو اجراشده | ۱۲۰+' ); ?>
	</section>
	<section class="ost-panel">
		<h2>مدیر سئو / ارائه‌دهنده پیش‌فرض <small class="ost-muted">در هر پروپوزال قابل تغییر است</small></h2>
		<div class="ost-grid g3">
			<?php $text( 'consultant_name', 'نام (روی جلد و اسلاید پایانی)' ); ?>
			<?php $text( 'consultant_title', 'سمت' ); ?>
			<?php $text( 'consultant_phone', 'موبایل / تلفن مستقیم', '', true ); ?>
			<?php $text( 'consultant_email', 'ایمیل مستقیم', '', true ); ?>
			<label>عکس<span class="ost-logo-pick"><img src="<?php echo esc_url( $s['consultant_photo'] ?: ost_logo_url() ); ?>" alt="" id="ost-photo-img"><input type="file" name="photo_file" accept="image/*"><input type="hidden" name="s[consultant_photo]" value="<?php echo esc_attr( $s['consultant_photo'] ); ?>"></span><small>مربعی؛ خالی = حرف اول نام.</small></label>
			<?php $text( 'proposal_prefix', 'پیشوند شماره پروپوزال', 'مثلاً RN ← RN-1405-012', true ); ?>
			<?php $text( 'proposal_validity', 'اعتبار پیش‌فرض پروپوزال (روز)' ); ?>
		</div>
		<?php $area( 'consultant_bio', 'معرفی کوتاه ارائه‌دهنده (یک یا دو جمله)', 2 ); ?>
		<?php $area( 'team', 'تیم پیش‌فرض پروژه (هر خط: نام یا عنوان | نقش)', 4 ); ?>
		<?php $area( 'agency_about', 'معرفی کوتاه آژانس (اسلاید نمونه‌کار)', 3 ); ?>
		<?php $area( 'portfolio', 'نمونه‌کارها (هر خط: عنوان | نتیجه اصلی | توضیح)', 5, 'تا ۶ مورد در اسلاید نمایش داده می‌شود. خالی = اسلاید حذف می‌شود.' ); ?>
	</section>
	<section class="ost-panel">
		<h2>رنگ‌های تم</h2>
		<div class="ost-grid g3">
			<label>آبی اصلی<input type="color" name="s[theme_primary]" value="<?php echo esc_attr( $s['theme_primary'] ); ?>"></label>
			<label>آبی آسمانی (تأکید)<input type="color" name="s[theme_accent]" value="<?php echo esc_attr( $s['theme_accent'] ); ?>"></label>
			<label>سرمه‌ای (پس‌زمینه جلد)<input type="color" name="s[theme_dark]" value="<?php echo esc_attr( $s['theme_dark'] ); ?>"></label>
		</div>
	</section>

<?php elseif ( 'pricing' === $tab ) : ?>
	<section class="ost-panel">
		<h2>قیمت‌های پیش‌فرض (تومان)</h2>
		<p class="ost-muted">در هر پروپوزال قابل تغییر است. قیمت رپورتاژ اگر سئو سیگنال قیمت رپورتاژهای رقبا را داشته باشد، از میانه آن‌ها گرفته می‌شود.</p>
		<div class="ost-grid g3">
			<?php $money( 'fee_monthly', 'دستمزد ماهانه تیم سئو (پکیج پیشنهادی)', 'پکیج پایه ۷۵٪ و رشد سریع ۱۴۰٪ این عدد.' ); ?>
			<?php $money( 'price_landing', 'صفحه فرود / دسته‌بندی' ); ?>
			<?php $money( 'price_article', 'مقاله تخصصی' ); ?>
			<?php $money( 'price_update', 'به‌روزرسانی هر صفحه' ); ?>
			<?php $money( 'price_reportage', 'رپورتاژ (اگر داده رقبا نبود)' ); ?>
			<?php $money( 'price_backlink', 'بک‌لینک تخصصی مکمل' ); ?>
		</div>
	</section>
	<section class="ost-panel">
		<h2>پیش‌فرض‌های تخمین</h2>
		<div class="ost-grid g3">
			<label>طول دوره پیش‌فرض<select name="s[default_months]"><?php foreach ( array( 8, 9, 10, 12 ) as $m ) { echo '<option value="' . (int) $m . '" ' . selected( (int) $s['default_months'], $m, false ) . '>' . esc_html( ost_num( $m ) ) . ' ماه</option>'; } ?></select></label>
			<?php $text( 'conversion_rate', 'نرخ تبدیل فروشگاه (٪)', 'میانگین جهانی ۱٫۵ تا ۳٪' ); ?>
			<?php $text( 'lead_rate', 'نرخ سرنخ خدماتی (٪)' ); ?>
			<?php $money( 'click_value', 'ارزش تبلیغاتی هر کلیک', 'برای «ارزش معادل تبلیغات» وقتی ارزش سفارش وارد نشده.' ); ?>
		</div>
	</section>

<?php else : ?>
	<section class="ost-panel">
		<h2>قواعد تشخیص رپورتاژ و بک‌لینک باکیفیت</h2>
		<div class="ost-grid g3">
			<?php $area( 'generic_anchors', 'انکرهای بی‌ارزش (هر خط یکی)', 12 ); ?>
			<?php $area( 'spam_patterns', 'الگوهای منبع کم‌ارزش (بخشی از آدرس)', 12, 'انجمن، کامنت، پروفایل، دایرکتوری، وبلاگ‌های رایگان …', true ); ?>
			<?php $area( 'iran_tlds', 'پسوندهای دامنه ایرانی', 12, 'به‌علاوه منابعی که عنوان فارسی دارند یا کشورشان IR است.', true ); ?>
		</div>
	</section>
<?php endif; ?>
	<div class="ost-actions sticky"><button class="ost-btn primary lg">ذخیره تنظیمات</button></div>
</form>
