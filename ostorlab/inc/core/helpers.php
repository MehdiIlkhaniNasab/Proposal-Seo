<?php
/**
 * Small shared helpers: Persian digits and numbers, money, dates, packing.
 *
 * @package Ostorlab
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'array_is_list' ) ) {
	/**
	 * PHP 8.0 has no array_is_list() (added in 8.1).
	 */
	function array_is_list( array $arr ) {
		$i = 0;
		foreach ( $arr as $k => $v ) {
			if ( $k !== $i++ ) {
				return false;
			}
		}
		return true;
	}
}

/**
 * Western digits → Persian digits.
 */
function ost_fa( $value ) {
	return strtr( (string) $value, array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹', '.' => '٫', '-' => '−' ) );
}

/**
 * Persian / Arabic digits → Western digits (for user input).
 */
function ost_en_digits( $value ) {
	return strtr( (string) $value, array( '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '٫' => '.', '،' => ',' ) );
}

/**
 * Number from user input ("۱٬۵۰۰٬۰۰۰", "1,500,000").
 */
function ost_to_number( $value ) {
	$v = preg_replace( '/[^0-9.\-]/', '', ost_en_digits( $value ) );
	return '' === $v ? 0 : (float) $v;
}

/**
 * Formatted number with Persian digits and thousands separator.
 */
function ost_num( $n, $decimals = 0 ) {
	$n = (float) $n;
	return str_replace( ',', '٬', ost_fa( number_format( $n, $decimals, '.', ',' ) ) );
}

/**
 * Compact number: 12٫۴ هزار، ۳٫۱ میلیون.
 */
function ost_compact( $n ) {
	$n = (float) $n;
	if ( abs( $n ) >= 1e9 ) {
		return ost_num( $n / 1e9, $n >= 1e10 ? 0 : 1 ) . ' میلیارد';
	}
	if ( abs( $n ) >= 1e6 ) {
		return ost_num( $n / 1e6, $n >= 1e7 ? 0 : 1 ) . ' میلیون';
	}
	if ( abs( $n ) >= 1e4 ) {
		return ost_num( $n / 1e3, 0 ) . ' هزار';
	}
	return ost_num( $n );
}

/**
 * Money in Toman.
 */
function ost_toman( $n, $compact = false ) {
	return ( $compact ? ost_compact( $n ) : ost_num( round( $n ) ) ) . ' تومان';
}

/**
 * Percentage from a ratio.
 */
function ost_pct( $ratio, $decimals = 0 ) {
	return ost_num( 100 * (float) $ratio, $decimals ) . '٪';
}

/**
 * Array path lookup: ost_get( $a, 'x.y.z' ).
 */
function ost_get( $arr, $path, $default = null ) {
	foreach ( explode( '.', $path ) as $k ) {
		if ( ! is_array( $arr ) || ! array_key_exists( $k, $arr ) ) {
			return $default;
		}
		$arr = $arr[ $k ];
	}
	return $arr;
}

/**
 * Normalise Persian text for matching: ي/ك → ی/ک, ZWNJ → space, digits, lower case.
 */
function ost_norm( $s ) {
	$s = str_replace( array( 'ي', 'ك', 'ة', 'ۀ', 'أ', 'إ', "\xE2\x80\x8C", "\xC2\xA0" ), array( 'ی', 'ک', 'ه', 'ه', 'ا', 'ا', ' ', ' ' ), (string) $s );
	$s = ost_en_digits( $s );
	$s = mb_strtolower( $s );
	return trim( preg_replace( '/\s+/u', ' ', $s ) );
}

/**
 * Clean list from a textarea (newline, comma, Persian comma).
 */
function ost_list( $text ) {
	$lines = preg_split( '/\r\n|\r|\n|,|،|;|؛/u', (string) $text );
	return array_values( array_unique( array_filter( array_map( 'trim', $lines ), 'strlen' ) ) );
}

/**
 * Bare host of a URL or domain: "https://www.Example.ir/x" → "example.ir".
 */
function ost_domain( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return '';
	}
	if ( ! preg_match( '#^https?://#i', $url ) ) {
		$url = 'https://' . $url;
	}
	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	return preg_replace( '/^www\./', '', $host );
}

/**
 * Home URL of a domain.
 */
function ost_home_url( $domain ) {
	return 'https://' . ost_domain( $domain ) . '/';
}

/**
 * Path of a URL ("/" for home).
 */
function ost_path( $url ) {
	$p = (string) wp_parse_url( preg_match( '#^https?://#i', $url ) ? $url : 'https://x' . ( '/' === substr( $url, 0, 1 ) ? '' : '/' ) . $url, PHP_URL_PATH );
	return '' === $p ? '/' : $p;
}

/**
 * Short readable URL for tables.
 */
function ost_short_url( $url, $max = 48 ) {
	$u = rawurldecode( preg_replace( '#^https?://(www\.)?#i', '', (string) $url ) );
	return mb_strlen( $u ) > $max ? mb_substr( $u, 0, $max - 1 ) . '…' : $u;
}

/**
 * Compress data for storage.
 */
function ost_pack( $data ) {
	return base64_encode( gzcompress( wp_json_encode( $data ), 6 ) );
}

/**
 * Decompress stored data.
 */
function ost_unpack( $packed ) {
	if ( empty( $packed ) || ! is_string( $packed ) ) {
		return array();
	}
	$raw  = base64_decode( $packed, true );
	$json = false === $raw ? false : @gzuncompress( $raw ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	$data = false === $json ? null : json_decode( $json, true );
	return is_array( $data ) ? $data : array();
}

/**
 * Encrypt a secret before it is stored.
 */
function ost_encrypt( $plain ) {
	$plain = (string) $plain;
	if ( '' === $plain ) {
		return '';
	}
	if ( ! function_exists( 'sodium_crypto_secretbox' ) ) {
		return 'b64:' . base64_encode( $plain );
	}
	$key   = hash( 'sha256', wp_salt( 'auth' ) . 'ostorlab', true );
	$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
	return 'sb1:' . base64_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, $key ) );
}

/**
 * Decrypt a stored secret.
 */
function ost_decrypt( $stored ) {
	$stored = (string) $stored;
	if ( 0 === strpos( $stored, 'b64:' ) ) {
		return (string) base64_decode( substr( $stored, 4 ) );
	}
	if ( 0 !== strpos( $stored, 'sb1:' ) || ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
		return $stored;
	}
	$raw   = base64_decode( substr( $stored, 4 ) );
	$key   = hash( 'sha256', wp_salt( 'auth' ) . 'ostorlab', true );
	$nonce = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
	$plain = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), $nonce, $key );
	return false === $plain ? '' : $plain;
}

/**
 * Mask a secret for display.
 */
function ost_mask( $secret ) {
	$secret = (string) $secret;
	if ( strlen( $secret ) < 8 ) {
		return '' === $secret ? '' : '••••';
	}
	return substr( $secret, 0, 4 ) . str_repeat( '•', 8 ) . substr( $secret, -4 );
}

/**
 * Median of numbers.
 */
function ost_median( $values ) {
	$values = array_values( array_filter( array_map( 'floatval', (array) $values ), 'is_finite' ) );
	if ( ! $values ) {
		return 0;
	}
	sort( $values );
	$n = count( $values );
	return $n % 2 ? $values[ intdiv( $n, 2 ) ] : ( $values[ $n / 2 - 1 ] + $values[ $n / 2 ] ) / 2;
}

/**
 * Average of numbers.
 */
function ost_avg( $values ) {
	$values = array_map( 'floatval', (array) $values );
	return $values ? array_sum( $values ) / count( $values ) : 0;
}

/**
 * Clamp.
 */
function ost_clamp( $v, $min, $max ) {
	return max( $min, min( $max, $v ) );
}

/**
 * Gregorian → Jalali [y, m, d].
 */
function ost_jalali( $gy, $gm, $gd ) {
	$g_d_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
	$gy2   = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
	$days  = 355666 + ( 365 * $gy ) + intdiv( $gy2 + 3, 4 ) - intdiv( $gy2 + 99, 100 ) + intdiv( $gy2 + 399, 400 ) + $gd + $g_d_m[ $gm - 1 ];
	$jy    = -1595 + ( 33 * intdiv( $days, 12053 ) );
	$days %= 12053;
	$jy   += 4 * intdiv( $days, 1461 );
	$days %= 1461;
	if ( $days > 365 ) {
		$jy  += intdiv( $days - 1, 365 );
		$days = ( $days - 1 ) % 365;
	}
	if ( $days < 186 ) {
		$jm = 1 + intdiv( $days, 31 );
		$jd = 1 + ( $days % 31 );
	} else {
		$jm = 7 + intdiv( $days - 186, 30 );
		$jd = 1 + ( ( $days - 186 ) % 30 );
	}
	return array( $jy, $jm, $jd );
}

/**
 * Persian month names.
 */
function ost_jalali_months() {
	return array( 1 => 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند' );
}

/**
 * Jalali date of a timestamp: "۸ آبان ۱۴۰۵".
 */
function ost_jdate( $ts = null ) {
	$ts = null === $ts ? current_time( 'timestamp' ) : (int) $ts; // phpcs:ignore
	list( $y, $m, $d ) = ost_jalali( (int) gmdate( 'Y', $ts ), (int) gmdate( 'n', $ts ), (int) gmdate( 'j', $ts ) );
	return ost_fa( $d ) . ' ' . ost_jalali_months()[ $m ] . ' ' . ost_fa( $y );
}

/**
 * Jalali month label N months from now: "آذر ۱۴۰۵".
 */
function ost_jmonth_from_now( $offset ) {
	$ts = strtotime( '+' . (int) $offset . ' months', current_time( 'timestamp' ) ); // phpcs:ignore
	list( $y, $m ) = ost_jalali( (int) gmdate( 'Y', $ts ), (int) gmdate( 'n', $ts ), 15 );
	return ost_jalali_months()[ $m ] . ' ' . ost_fa( $y );
}

/**
 * Render a template with variables.
 */
function ost_view( $name, $vars = array() ) {
	$file = OST_DIR . '/templates/' . $name . '.php';
	if ( ! file_exists( $file ) ) {
		return;
	}
	extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract
	include $file;
}

/**
 * Inline SVG icon (stroke icons, 24 grid).
 */
function ost_icon( $name, $size = 18 ) {
	$paths = array(
		'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
		'users'    => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.8-3.6 3.4-5.5 6.5-5.5s5.7 1.9 6.5 5.5"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14.6c1.8.8 3 2.6 3.5 5.4"/>',
		'link'     => '<path d="M10 14a4.5 4.5 0 0 0 6.4 0l3-3a4.5 4.5 0 0 0-6.4-6.4l-1 1"/><path d="M14 10a4.5 4.5 0 0 0-6.4 0l-3 3a4.5 4.5 0 0 0 6.4 6.4l1-1"/>',
		'gear'     => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
		'chart'    => '<path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 5-6"/>',
		'wallet'   => '<rect x="3" y="6" width="18" height="14" rx="2"/><path d="M16 13h2M3 10h18M6 6l9-3 1 3"/>',
		'map'      => '<path d="M9 4 3 6v14l6-2 6 2 6-2V4l-6 2-6-2z"/><path d="M9 4v14M15 6v14"/>',
		'text'     => '<path d="M4 6h16M4 12h16M4 18h10"/>',
		'file'     => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M9 14h6M9 17h4"/>',
		'play'     => '<path d="M7 4v16l13-8z"/>',
		'plus'     => '<path d="M12 5v14M5 12h14"/>',
		'check'    => '<path d="m5 12 5 5 9-10"/>',
		'x'        => '<path d="M6 6l12 12M18 6 6 18"/>',
		'tool'     => '<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18v3h3l6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z"/>',
		'eye'      => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
		'download' => '<path d="M12 4v11M7 10l5 5 5-5M5 20h14"/>',
		'trash'    => '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/>',
		'copy'     => '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3"/>',
		'spark'    => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M6 18l2.5-2.5M15.5 8.5 18 6"/>',
		'astrolabe' => '<circle cx="12" cy="13.5" r="7.5"/><circle cx="12" cy="13.5" r="4"/><path d="M12 3v2.5M10.5 3h3M5.5 19.5l13-12"/><circle cx="12" cy="13.5" r="1" fill="currentColor"/>',
		'target'   => '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="4"/><circle cx="12" cy="12" r="1" fill="currentColor"/>',
		'clock'    => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
		'grid'     => '<rect x="4" y="4" width="7" height="7" rx="2"/><rect x="13" y="4" width="7" height="7" rx="2"/><rect x="4" y="13" width="7" height="7" rx="2"/><rect x="13" y="13" width="7" height="7" rx="2"/>',
		'book'     => '<path d="M5 4.5A1.5 1.5 0 0 1 6.5 3H19v15H6.5A1.5 1.5 0 0 0 5 19.5z"/><path d="M5 19.5A1.5 1.5 0 0 0 6.5 21H19"/>',
		'menu'     => '<path d="M4 7h16M4 12h16M4 17h16"/>',
		'trophy'   => '<path d="M8 4h8v5a4 4 0 0 1-8 0z"/><path d="M8 6H5a3 3 0 0 0 3 4M16 6h3a3 3 0 0 1-3 4M12 13v4M8.5 20h7"/>',
		'send'     => '<path d="M21 3 10 14"/><path d="M21 3 14.5 21l-4.5-7-7-4.5z"/>',
		'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1-4.5 4.2-6.5 8-6.5s7 2 8 6.5"/>',
	);
	return '<svg class="ost-ic" width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ( $paths[ $name ] ?? '' ) . '</svg>';
}
