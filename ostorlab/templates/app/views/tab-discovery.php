<?php
/**
 * Tab: business discovery (profile + questions, confirmed before the keyword analysis).
 *
 * @var array $p
 * @package Ostorlab
 */

defined( 'ABSPATH' ) || exit;

$id   = $p['id'];
$d    = ost_discovery( $id );
$ok   = ost_discovery_confirmed( $p );
$wait = 'waiting' === ( $p['job']['status'] ?? '' );
?>
<?php if ( ! $d['status'] ) : ?>
	<section class="ost-panel ost-empty">
		<?php echo ost_icon( 'user', 28 ); // phpcs:ignore ?>
		<h2>اول کسب‌وکار را بشناسیم</h2>
		<p>اسطرلاب صفحه اصلی سایت کارفرما و رقبا را می‌خواند، پروفایل کسب‌وکار را پیش‌نویس می‌کند و چند سؤال کوتاه مخصوص همین حوزه می‌پرسد (با جواب پیشنهادی). با تأیید شما، فقط کلمات مربوط به محصولاتی که واقعاً می‌فروشید تحلیل می‌شوند.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php ost_form_fields( 'ost_discovery_build' ); ?><input type="hidden" name="id" value="<?php echo (int) $id; ?>">
			<button class="ost-btn primary" data-busy="در حال خواندن سایت‌ها و آماده‌کردن سؤال‌ها…"><?php echo ost_icon( 'play', 16 ); // phpcs:ignore ?> ساخت پروفایل و سؤال‌ها</button>
		</form>
	</section>
<?php else : $pr = $d['profile']; ?>
	<?php if ( $wait && ! $ok ) : ?>
		<div class="ost-notice amber"><b>تحلیل منتظر شماست.</b> جواب‌ها را بررسی کنید (پیشنهادها از قبل پر شده‌اند) و «تأیید و ادامه تحلیل» را بزنید.</div>
	<?php endif; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ost-disc">
		<?php ost_form_fields( 'ost_discovery_save' ); ?><input type="hidden" name="id" value="<?php echo (int) $id; ?>">
		<section class="ost-panel ost-disc-profile">
			<div class="ost-panel-head">
				<h2><?php echo ost_icon( 'user', 18 ); // phpcs:ignore ?> پروفایل کسب‌وکار <span class="ost-pill <?php echo $ok ? 'green' : 'amber'; ?>"><?php echo $ok ? 'تأیید شده' : 'منتظر تأیید'; ?></span></h2>
				<small class="ost-muted"><?php echo 'ai' === $d['source'] ? 'پیش‌نویس هوش مصنوعی از روی سایت‌ها' : 'بدون هوش مصنوعی؛ جواب‌ها را خودتان کامل کنید'; ?> · <?php echo esc_html( ost_ago( $d['at'] ) ); ?></small>
			</div>
			<label class="ost-field">خلاصه: این کسب‌وکار چه می‌فروشد و به چه کسی؟
				<textarea name="summary" rows="2"><?php echo esc_textarea( $pr['summary'] ?? '' ); ?></textarea>
			</label>
			<?php if ( $d['pages'] ) : ?>
				<p class="ost-muted">صفحاتی که خوانده شد:
					<?php foreach ( $d['pages'] as $dom => $pg ) { echo '<span class="ost-chip"><b dir="ltr">' . esc_html( $dom ) . '</b> ' . esc_html( mb_substr( $pg['title'], 0, 50 ) ) . '</span> '; } ?>
				</p>
			<?php endif; ?>
		</section>
		<section class="ost-panel">
			<h2>سؤال‌ها <small class="ost-muted">جواب‌های پیشنهادی از قبل انتخاب شده‌اند؛ فقط اصلاح کنید.</small></h2>
			<ol class="ost-qs">
				<?php
				foreach ( $d['questions'] as $q ) :
					$val  = ost_discovery_answer( $d, $q );
					$name = 'ans[' . $q['id'] . ']';
					?>
					<li class="ost-q">
						<div class="ost-q-head"><b><?php echo esc_html( $q['q'] ); ?></b><?php echo empty( $q['fixed'] ) ? ' <span class="ost-pill navy">مخصوص این حوزه</span>' : ''; ?><?php if ( ! empty( $q['why'] ) ) : ?><small><?php echo esc_html( $q['why'] ); ?></small><?php endif; ?></div>
						<?php if ( 'text' === $q['type'] ) : ?>
							<textarea name="<?php echo esc_attr( $name ); ?>" rows="<?php echo in_array( $q['id'], array( 'sells', 'not_sells' ), true ) ? 4 : 2; ?>"><?php echo esc_textarea( is_array( $val ) ? implode( "\n", $val ) : $val ); ?></textarea>
						<?php else : $multi = 'multi' === $q['type']; $vals = (array) $val; $other = implode( '، ', array_diff( $vals, $q['options'] ) ); ?>
							<div class="ost-opts">
								<?php foreach ( $q['options'] as $o ) : ?>
									<label class="ost-opt"><input type="<?php echo $multi ? 'checkbox' : 'radio'; ?>" name="<?php echo esc_attr( $name . ( $multi ? '[]' : '' ) ); ?>" value="<?php echo esc_attr( $o ); ?>" <?php checked( in_array( $o, $vals, true ) ); ?>><span><?php echo esc_html( $o ); ?></span></label>
								<?php endforeach; ?>
								<?php if ( ! $multi ) : ?>
									<label class="ost-opt"><input type="radio" name="<?php echo esc_attr( $name ); ?>" value="__other" <?php checked( '' !== $other ); ?>><span>سایر</span></label>
								<?php endif; ?>
								<input type="text" class="ost-other" name="other[<?php echo esc_attr( $q['id'] ); ?>]" value="<?php echo esc_attr( $other ); ?>" placeholder="<?php echo $multi ? 'مورد دیگر…' : 'جواب دیگر…'; ?>">
							</div>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
			<div class="ost-row">
				<button class="ost-btn primary" name="confirm" value="1"><?php echo ost_icon( 'play', 16 ); // phpcs:ignore ?> تأیید و ادامه تحلیل</button>
				<button class="ost-btn">فقط ذخیره</button>
				<small class="ost-muted">با تأیید، کلمات مربوط به «حوزه‌هایی که کار نمی‌کنید» از کل تحلیل حذف می‌شوند و هوش مصنوعی همه کلمات را با همین پروفایل بازبینی می‌کند.</small>
			</div>
		</section>
	</form>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ost-row">
		<?php ost_form_fields( 'ost_discovery_build' ); ?><input type="hidden" name="id" value="<?php echo (int) $id; ?>">
		<button class="ost-btn sm ghost" data-confirm="پیش‌نویس و سؤال‌ها از نو ساخته شود؟ (جواب‌های ثابت قبلی حفظ می‌شوند)" data-busy="در حال ساخت…">ساخت دوباره پروفایل از روی سایت‌ها</button>
	</form>
<?php endif; ?>
