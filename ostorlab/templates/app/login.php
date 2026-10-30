<?php
/**
 * App login screen (posts to wp-login.php, comes back to the requested route).
 *
 * @package Ostorlab
 * @var string $redirect
 */

defined( 'ABSPATH' ) || exit;

$err = isset( $_GET['ost_login'] ) ? sanitize_key( $_GET['ost_login'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$to  = remove_query_arg( 'ost_login', $redirect );
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title>ورود · اسطرلاب</title>
	<?php ost_app_assets(); ?>
</head>
<body class="ost-app ost-auth">
	<form class="ost-login" method="post" action="<?php echo esc_url( site_url( 'wp-login.php', 'login_post' ) ); ?>">
		<span class="ost-brand-mark is-lg"><?php echo ost_icon( 'astrolabe', 32 ); // phpcs:ignore ?></span>
		<h1>اسطرلاب</h1>
		<p class="ost-login-tag">پیش از حرکت، مسیر را بسنج — پروپوزال‌ساز سئو آژانس رونق</p>
		<?php if ( 'failed' === $err ) : ?>
			<div class="ost-flash is-err">نام کاربری یا رمز عبور اشتباه است.</div>
		<?php elseif ( 'empty' === $err ) : ?>
			<div class="ost-flash is-err">نام کاربری و رمز عبور را وارد کنید.</div>
		<?php endif; ?>
		<label>نام کاربری یا ایمیل<input type="text" name="log" autocomplete="username" dir="ltr" required autofocus></label>
		<label>رمز عبور<input type="password" name="pwd" autocomplete="current-password" dir="ltr" required></label>
		<label class="ost-check"><input type="checkbox" name="rememberme" value="forever" checked> مرا به خاطر بسپار</label>
		<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $to ); ?>">
		<input type="hidden" name="ost_login" value="1">
		<button class="ost-btn primary block" type="submit">ورود</button>
		<a class="ost-muted" href="<?php echo esc_url( wp_lostpassword_url( $to ) ); ?>">فراموشی رمز عبور</a>
	</form>
</body>
</html>
