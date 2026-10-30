<?php
/**
 * App document: sidebar + main area.
 *
 * @package Ostorlab
 * @var string     $view
 * @var string     $title
 * @var string     $nav
 * @var array      $vars
 * @var array|null $proposal
 */

defined( 'ABSPATH' ) || exit;

$user   = wp_get_current_user();
$items  = ost_proposals();
$pipe   = ost_pipeline( $items );
$cur_id = $proposal ? $proposal['id'] : 0;
$recent = array_slice( array_filter( $items, function ( $p ) {
	return ! in_array( $p['status'], array( 'won', 'lost' ), true );
} ), 0, 8 );
$link   = function ( $key, $url, $icon, $label, $badge = 0, $hot = false ) use ( $nav ) {
	printf(
		'<a class="%s" href="%s">%s<span>%s</span>%s</a>',
		$key === $nav ? 'is-active' : '',
		esc_url( $url ),
		ost_icon( $icon, 18 ), // phpcs:ignore
		esc_html( $label ),
		$badge ? '<em class="' . ( $hot ? 'is-hot' : '' ) . '">' . esc_html( ost_num( $badge ) ) . '</em>' : ''
	);
};
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( $title . ' · اسطرلاب' ); ?></title>
	<?php ost_app_assets(); ?>
</head>
<body class="ost-app view-<?php echo esc_attr( $view ); ?>">
<div class="ost-shell">
	<aside class="ost-side" id="ost-side">
		<a class="ost-brand" href="<?php echo esc_url( ost_url() ); ?>">
			<span class="ost-brand-mark"><?php echo ost_icon( 'astrolabe', 24 ); // phpcs:ignore ?></span>
			<span><b>اسطرلاب</b><small>SEO PROPOSALS · RONAGH</small></span>
		</a>
		<a class="ost-new-btn" href="<?php echo esc_url( ost_url( 'new' ) ); ?>"><?php echo ost_icon( 'plus', 18 ); // phpcs:ignore ?> پروپوزال جدید</a>
		<nav class="ost-nav">
			<?php $link( 'dashboard', ost_url(), 'grid', 'داشبورد پروپوزال‌ها', count( $pipe['due'] ), (bool) $pipe['due'] ); ?>
			<?php if ( $recent ) : ?>
				<p class="ost-nav-h">در جریان</p>
				<?php foreach ( $recent as $p ) : $sl = ost_status_label( $p['status'] ); ?>
					<a class="ost-nav-item <?php echo $cur_id === $p['id'] ? 'is-active' : ''; ?>" href="<?php echo esc_url( ost_proposal_url( $p['id'] ) ); ?>">
						<i class="ost-dot c-<?php echo esc_attr( $sl[1] ); ?>" title="<?php echo esc_attr( $sl[0] ); ?>"></i><span><?php echo esc_html( $p['title'] ); ?></span>
					</a>
				<?php endforeach; ?>
			<?php endif; ?>
			<p class="ost-nav-h">ابزار</p>
			<?php $link( 'settings', ost_url( 'settings' ), 'gear', 'تنظیمات و اتصال‌ها' ); ?>
			<?php $link( 'guide', ost_url( 'guide' ), 'book', 'راهنما' ); ?>
			<?php if ( function_exists( 'sap_url' ) ) : ?>
				<a href="<?php echo esc_url( sap_url() ); ?>"><?php echo ost_icon( 'map', 18 ); // phpcs:ignore ?><span>سکان (اکشن پلن)</span></a>
			<?php endif; ?>
		</nav>
		<div class="ost-side-foot">
			<span class="ost-user"><i><?php echo esc_html( mb_substr( $user->display_name, 0, 1 ) ); ?></i><?php echo esc_html( $user->display_name ); ?></span>
			<span class="ost-side-links">
				<a href="<?php echo esc_url( admin_url() ); ?>" title="پیشخوان وردپرس">وردپرس</a>
				<a href="<?php echo esc_url( wp_logout_url( ost_url() ) ); ?>">خروج</a>
			</span>
		</div>
	</aside>
	<div class="ost-backdrop" data-ost-close-nav></div>
	<main class="ost-main">
		<div class="ost-topbar">
			<button type="button" class="ost-menu-btn" data-ost-open-nav aria-label="منو"><?php echo ost_icon( 'menu', 22 ); // phpcs:ignore ?></button>
			<a class="ost-topbar-brand" href="<?php echo esc_url( ost_url() ); ?>">اسطرلاب</a>
		</div>
		<div class="ost-wrap">
			<?php
			ost_flash();
			ost_view( 'app/views/' . $view, $vars );
			?>
		</div>
	</main>
</div>
</body>
</html>
