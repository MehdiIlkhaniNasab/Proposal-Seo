<?php
/**
 * PDF export.
 *
 * Default ("browser"): the deck opens in a new tab and the print dialog saves it
 * as PDF; the page sets @page to 1280×720 px, so every slide is one 16:9 page,
 * backgrounds included.
 *
 * Optional ("gotenberg"): a Gotenberg service (Docker image gotenberg/gotenberg,
 * headless Chromium) renders the same self-contained HTML server-side and the
 * PDF downloads directly — the same quality, one click.
 *
 * @package Fanoos
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render a proposal to PDF with Gotenberg.
 *
 * @return string|WP_Error PDF bytes.
 */
function fns_pdf_gotenberg( $id ) {
	$base = untrailingslashit( (string) fns_setting( 'pdf_service_url' ) );
	if ( '' === $base ) {
		return new WP_Error( 'fns_pdf_url', 'آدرس سرویس PDF (Gotenberg) در تنظیمات وارد نشده است.' );
	}
	$html     = fns_deck_html( $id, array( 'inline' => true ) );
	$boundary = wp_generate_password( 24, false );
	$fields   = array(
		'paperWidth'        => '13.333',
		'paperHeight'       => '7.5',
		'marginTop'         => '0',
		'marginBottom'      => '0',
		'marginLeft'        => '0',
		'marginRight'       => '0',
		'printBackground'   => 'true',
		'preferCssPageSize' => 'true',
		'waitDelay'         => '1s',
	);
	$body = '';
	foreach ( $fields as $k => $v ) {
		$body .= "--$boundary\r\nContent-Disposition: form-data; name=\"$k\"\r\n\r\n$v\r\n";
	}
	$body   .= "--$boundary\r\nContent-Disposition: form-data; name=\"files\"; filename=\"index.html\"\r\nContent-Type: text/html\r\n\r\n$html\r\n--$boundary--\r\n";
	$headers = array( 'Content-Type' => 'multipart/form-data; boundary=' . $boundary );
	$token   = (string) fns_setting( 'pdf_service_token' );
	if ( '' !== $token ) {
		$headers['Authorization'] = 0 === stripos( $token, 'basic ' ) || 0 === stripos( $token, 'bearer ' ) ? $token : 'Bearer ' . $token;
	}
	$res = wp_remote_post( $base . '/forms/chromium/convert/html', array( 'timeout' => 180, 'headers' => $headers, 'body' => $body ) );
	if ( is_wp_error( $res ) ) {
		return new WP_Error( 'fns_pdf_http', 'اتصال به سرویس PDF ناموفق بود: ' . fns_net_error( $res ) );
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	$pdf  = (string) wp_remote_retrieve_body( $res );
	if ( 200 !== $code || 0 !== strpos( $pdf, '%PDF' ) ) {
		return new WP_Error( 'fns_pdf_fail', sprintf( 'سرویس PDF خطا داد (HTTP %d): %s', $code, mb_substr( wp_strip_all_tags( $pdf ), 0, 200 ) ) );
	}
	return $pdf;
}

/**
 * File name of a proposal's PDF.
 */
function fns_pdf_filename( $p ) {
	$name = $p['inputs']['domain'] ?: sanitize_title( $p['title'] ) ?: 'proposal-' . $p['id'];
	return 'seo-proposal-' . $name . '-' . gmdate( 'Y-m-d' ) . '.pdf';
}
