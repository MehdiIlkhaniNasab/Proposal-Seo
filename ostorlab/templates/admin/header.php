<?php
/**
 * Admin header with brand and navigation.
 *
 * @var string $page
 * @package Ostorlab
 */

defined( 'ABSPATH' ) || exit;
$nav = array(
	'ostorlab'          => array( 'پروپوزال‌ها', ost_admin_url(), 'file' ),
	'ostorlab-new'      => array( 'پروپوزال جدید', ost_admin_url( 'new' ), 'plus' ),
	'ostorlab-settings' => array( 'تنظیمات', ost_admin_url( 'settings' ), 'gear' ),
);
?>
<header class="ost-top">
	<div class="ost-brand">
		<span class="ost-mark"><?php echo ost_icon( 'astrolabe', 26 ); // phpcs:ignore ?></span>
		<div><b>اسطرلاب</b><small>پروپوزال‌ساز هوشمند سئو · <?php echo esc_html( ost_setting( 'agency_name' ) ); ?></small></div>
	</div>
	<nav class="ost-nav">
		<?php foreach ( $nav as $k => $n ) : ?>
			<a href="<?php echo esc_url( $n[1] ); ?>" class="<?php echo $page === $k ? 'on' : ''; ?>"><?php echo ost_icon( $n[2], 16 ); // phpcs:ignore ?><?php echo esc_html( $n[0] ); ?></a>
		<?php endforeach; ?>
	</nav>
</header>
