<?php
/**
 * Proposals list.
 *
 * @var array $items
 * @package Ostorlab
 */

defined( 'ABSPATH' ) || exit;
$ready = ost_ss_connected();
?>
<?php if ( ! $ready ) : ?>
	<div class="ost-notice err">برای شروع، کلید API سئو سیگنال را در <a href="<?php echo esc_url( ost_admin_url( 'settings' ) ); ?>">تنظیمات › اتصال‌ها</a> وارد کنید<?php echo ost_sokan_active() ? ' (یا گزینه «استفاده از تنظیمات سکان» را روشن کنید)' : ''; ?>.</div>
<?php endif; ?>
<?php if ( ! $items ) : ?>
	<section class="ost-empty">
		<span class="ost-hero-ic"><?php echo ost_icon( 'astrolabe', 44 ); // phpcs:ignore ?></span>
		<h2>اولین پروپوزال را بسازید</h2>
		<p>۵ تا ۷ کلمه کلیدی کارفرما و ۳ تا ۵ رقیب را وارد کنید؛ اسطرلاب بازار جستجو، رقبا، رپورتاژها، وضعیت فنی و هزینه‌ها را تحلیل می‌کند و یک پروپوزال اسلایدی آماده تحویل می‌دهد.</p>
		<a class="ost-btn primary lg" href="<?php echo esc_url( ost_admin_url( 'new' ) ); ?>"><?php echo ost_icon( 'plus' ); // phpcs:ignore ?> پروپوزال جدید</a>
	</section>
<?php else : ?>
	<div class="ost-bar">
		<h1>پروپوزال‌ها <small><?php echo esc_html( ost_num( count( $items ) ) ); ?></small></h1>
		<a class="ost-btn primary" href="<?php echo esc_url( ost_admin_url( 'new' ) ); ?>"><?php echo ost_icon( 'plus' ); // phpcs:ignore ?> پروپوزال جدید</a>
	</div>
	<div class="ost-cards">
		<?php foreach ( $items as $p ) : $st = ost_status_label( $p['status'] ); $a = ost_data( $p['id'], 'analysis' ); ?>
			<article class="ost-card">
				<div class="ost-card-top">
					<span class="ost-pill <?php echo esc_attr( $st[1] ); ?>"><?php echo esc_html( $st[0] ); ?></span>
					<small><?php echo esc_html( ost_jdate( $p['modified'] ) ); ?></small>
				</div>
				<h3><a href="<?php echo esc_url( ost_admin_url( 'proposal', array( 'id' => $p['id'] ) ) ); ?>"><?php echo esc_html( $p['title'] ); ?></a></h3>
				<p class="ost-muted"><?php echo esc_html( $p['inputs']['domain'] ?: 'بدون سایت' ); ?> · <?php echo esc_html( ost_num( count( $p['inputs']['keywords'] ) ) ); ?> کلمه · <?php echo esc_html( ost_num( count( $p['inputs']['competitors'] ) ) ); ?> رقیب</p>
				<?php if ( $a ) : $pk = $a['budget']['packages'][ $a['budget']['selected'] ] ?? null; ?>
					<div class="ost-mini">
						<span><b><?php echo esc_html( ost_compact( $a['market']['total'] ) ); ?></b> جستجوی ماهانه</span>
						<span><b><?php echo esc_html( $pk ? ost_compact( $pk['monthly'] ) : '—' ); ?></b> ماهانه</span>
					</div>
				<?php endif; ?>
				<div class="ost-card-act">
					<a class="ost-btn" href="<?php echo esc_url( ost_admin_url( 'proposal', array( 'id' => $p['id'] ) ) ); ?>">باز کردن</a>
					<a class="ost-btn ghost" href="<?php echo esc_url( ost_action_url( 'ost_duplicate', array( 'id' => $p['id'] ) ) ); ?>" title="کپی برای کارفرمای دیگر"><?php echo ost_icon( 'copy', 16 ); // phpcs:ignore ?></a>
					<a class="ost-btn ghost danger" href="<?php echo esc_url( ost_action_url( 'ost_delete', array( 'id' => $p['id'] ) ) ); ?>" data-confirm="این پروپوزال و همه داده‌هایش حذف شود؟"><?php echo ost_icon( 'trash', 16 ); // phpcs:ignore ?></a>
				</div>
			</article>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
