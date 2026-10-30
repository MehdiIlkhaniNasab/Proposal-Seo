<?php
/**
 * No access.
 *
 * @package Ostorlab
 */

defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html lang="fa" dir="rtl">
<head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow"><title>دسترسی ندارید · اسطرلاب</title><?php ost_app_assets(); ?></head>
<body class="ost-app ost-auth">
	<div class="ost-login">
		<span class="ost-brand-mark is-lg"><?php echo ost_icon( 'astrolabe', 32 ); // phpcs:ignore ?></span>
		<h1>دسترسی ندارید</h1>
		<p class="ost-muted">اسطرلاب فقط برای مدیران سئو فعال است. از مدیر سایت بخواهید دسترسی بدهد.</p>
		<a class="ost-btn block" href="<?php echo esc_url( wp_logout_url( ost_url() ) ); ?>">خروج و ورود با حساب دیگر</a>
	</div>
</body>
</html>
