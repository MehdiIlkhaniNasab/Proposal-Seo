<?php
/**
 * Proposals list.
 *
 * @var array $items
 * @package Fanoos
 */

defined( 'ABSPATH' ) || exit;
$ready = fns_ss_connected();
?>
<?php if ( ! $ready ) : ?>
	<div class="fns-notice err">برای شروع، کلید API سئو سیگنال را در <a href="<?php echo esc_url( fns_admin_url( 'settings' ) ); ?>">تنظیمات › اتصال‌ها</a> وارد کنید<?php echo fns_sokan_active() ? ' (یا گزینه «استفاده از تنظیمات سکان» را روشن کنید)' : ''; ?>.</div>
<?php endif; ?>
<?php if ( ! $items ) : ?>
	<section class="fns-empty">
		<span class="fns-hero-ic"><?php echo fns_icon( 'light', 44 ); // phpcs:ignore ?></span>
		<h2>اولین پروپوزال را بسازید</h2>
		<p>۵ تا ۷ کلمه کلیدی کارفرما و ۳ تا ۵ رقیب را وارد کنید؛ فانوس بازار جستجو، رقبا، رپورتاژها، وضعیت فنی و هزینه‌ها را تحلیل می‌کند و یک پروپوزال اسلایدی آماده تحویل می‌دهد.</p>
		<a class="fns-btn primary lg" href="<?php echo esc_url( fns_admin_url( 'new' ) ); ?>"><?php echo fns_icon( 'plus' ); // phpcs:ignore ?> پروپوزال جدید</a>
	</section>
<?php else : ?>
	<div class="fns-bar">
		<h1>پروپوزال‌ها <small><?php echo esc_html( fns_num( count( $items ) ) ); ?></small></h1>
		<a class="fns-btn primary" href="<?php echo esc_url( fns_admin_url( 'new' ) ); ?>"><?php echo fns_icon( 'plus' ); // phpcs:ignore ?> پروپوزال جدید</a>
	</div>
	<div class="fns-cards">
		<?php foreach ( $items as $p ) : $st = fns_status_label( $p['status'] ); $a = fns_data( $p['id'], 'analysis' ); ?>
			<article class="fns-card">
				<div class="fns-card-top">
					<span class="fns-pill <?php echo esc_attr( $st[1] ); ?>"><?php echo esc_html( $st[0] ); ?></span>
					<small><?php echo esc_html( fns_jdate( $p['modified'] ) ); ?></small>
				</div>
				<h3><a href="<?php echo esc_url( fns_admin_url( 'proposal', array( 'id' => $p['id'] ) ) ); ?>"><?php echo esc_html( $p['title'] ); ?></a></h3>
				<p class="fns-muted"><?php echo esc_html( $p['inputs']['domain'] ?: 'بدون سایت' ); ?> · <?php echo esc_html( fns_num( count( $p['inputs']['keywords'] ) ) ); ?> کلمه · <?php echo esc_html( fns_num( count( $p['inputs']['competitors'] ) ) ); ?> رقیب</p>
				<?php if ( $a ) : $pk = $a['budget']['packages'][ $a['budget']['selected'] ] ?? null; ?>
					<div class="fns-mini">
						<span><b><?php echo esc_html( fns_compact( $a['market']['total'] ) ); ?></b> جستجوی ماهانه</span>
						<span><b><?php echo esc_html( $pk ? fns_compact( $pk['monthly'] ) : '—' ); ?></b> ماهانه</span>
					</div>
				<?php endif; ?>
				<div class="fns-card-act">
					<a class="fns-btn" href="<?php echo esc_url( fns_admin_url( 'proposal', array( 'id' => $p['id'] ) ) ); ?>">باز کردن</a>
					<a class="fns-btn ghost" href="<?php echo esc_url( fns_action_url( 'fns_duplicate', array( 'id' => $p['id'] ) ) ); ?>" title="کپی برای کارفرمای دیگر"><?php echo fns_icon( 'copy', 16 ); // phpcs:ignore ?></a>
					<a class="fns-btn ghost danger" href="<?php echo esc_url( fns_action_url( 'fns_delete', array( 'id' => $p['id'] ) ) ); ?>" data-confirm="این پروپوزال و همه داده‌هایش حذف شود؟"><?php echo fns_icon( 'trash', 16 ); // phpcs:ignore ?></a>
				</div>
			</article>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
