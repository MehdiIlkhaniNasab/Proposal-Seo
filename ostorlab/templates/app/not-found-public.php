<?php
/**
 * A shared proposal link that does not exist (or was switched off).
 *
 * @package Ostorlab
 */

defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html lang="fa" dir="rtl">
<head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow"><title>پروپوزال در دسترس نیست</title><?php ost_app_assets(); ?></head>
<body class="ost-app ost-auth">
	<div class="ost-login">
		<h1>این پروپوزال در دسترس نیست</h1>
		<p class="ost-muted">لینک منقضی یا غیرفعال شده است. برای دریافت نسخه به‌روز با <?php echo esc_html( ost_setting( 'agency_name' ) ); ?> تماس بگیرید.</p>
	</div>
</body>
</html>
