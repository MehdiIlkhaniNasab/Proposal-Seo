<?php
/**
 * Admin header with brand and navigation.
 *
 * @var string $page
 * @package Fanoos
 */

defined( 'ABSPATH' ) || exit;
$nav = array(
	'fanoos'          => array( 'پروپوزال‌ها', fns_admin_url(), 'file' ),
	'fanoos-new'      => array( 'پروپوزال جدید', fns_admin_url( 'new' ), 'plus' ),
	'fanoos-settings' => array( 'تنظیمات', fns_admin_url( 'settings' ), 'gear' ),
);
?>
<header class="fns-top">
	<div class="fns-brand">
		<span class="fns-mark"><?php echo fns_icon( 'light', 26 ); // phpcs:ignore ?></span>
		<div><b>فانوس</b><small>پروپوزال‌ساز هوشمند سئو · <?php echo esc_html( fns_setting( 'agency_name' ) ); ?></small></div>
	</div>
	<nav class="fns-nav">
		<?php foreach ( $nav as $k => $n ) : ?>
			<a href="<?php echo esc_url( $n[1] ); ?>" class="<?php echo $page === $k ? 'on' : ''; ?>"><?php echo fns_icon( $n[2], 16 ); // phpcs:ignore ?><?php echo esc_html( $n[0] ); ?></a>
		<?php endforeach; ?>
	</nav>
</header>
