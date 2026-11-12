<?php
/**
 * Keyword sources of the market:
 *   1. SEO Signal research of the seed keywords (as before);
 *   2. the manager's own list — pasted from Excel, or a CSV / XLSX file: keyword, monthly
 *      volume (missing volumes are fetched from SEO Signal in one bulk request) and an
 *      optional group, which becomes a cluster. "Only these keywords" skips the seed research;
 *   3. market depth — what the client and the competitors already rank for beyond the seeds
 *      (data already collected, no extra quota). These words are kept only when the AI
 *      review confirms they belong to this business (without AI: when they share a word with
 *      a seed's subject).
 *
 * @package Ostorlab
 */

defined( 'ABSPATH' ) || exit;

const OST_DEPTH_SEED = 'سایر موضوعات بازار (از کلمات رقبا)';

/**
 * The manager's keyword list.
 *
 * @return array{rows: array, mode: string, at: int}
 */
function ost_manual_kw( $id ) {
	$m = get_post_meta( $id, '_ost_manual_kw', true );
	return array_replace( array( 'rows' => array(), 'mode' => 'add', 'at' => 0 ), is_array( $m ) ? $m : array() );
}

/**
 * Rows from pasted text (Excel copy = tab separated) or CSV: keyword, volume, group.
 * A header row and empty lines are skipped.
 *
 * @return array [{w, v, g}]
 */
function ost_parse_kw_table( $text ) {
	$rows = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
		$line = trim( preg_replace( '/^\xEF\xBB\xBF/', '', $line ) );
		if ( '' === $line ) {
			continue;
		}
		$cols = preg_split( '/\t|;|,(?![0-9۰-۹]{3})|\|/u', $line );
		$rows[] = array_map( function ( $c ) {
			return trim( $c, " \t\"'" );
		}, $cols );
	}
	return ost_kw_rows( $rows );
}

/**
 * Normalise table rows: find which column is the volume, skip headers.
 */
function ost_kw_rows( $table ) {
	$out = array();
	foreach ( $table as $cols ) {
		$cols = array_values( array_map( 'strval', (array) $cols ) );
		if ( ! $cols || '' === trim( $cols[0] ) ) {
			continue;
		}
		$w = sanitize_text_field( $cols[0] );
		$v = isset( $cols[1] ) ? trim( ost_en_digits( $cols[1] ) ) : '';
		if ( '' !== $v && ! preg_match( '/^[0-9.,٬\s]+$/u', $v ) ) {
			if ( ! $out ) {
				continue; // Header row ("keyword | volume").
			}
			$v = '';
		}
		$out[ ost_norm( $w ) ] = array(
			'w' => $w,
			'v' => '' === $v ? null : (int) ost_to_number( $v ),
			'g' => sanitize_text_field( (string) ( $cols[2] ?? '' ) ),
		);
	}
	return array_values( $out );
}

/**
 * First sheet of an XLSX file as rows (no library needed).
 *
 * @return array|WP_Error
 */
function ost_read_xlsx( $file ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		return new WP_Error( 'ost_xlsx', 'هاست شما ZipArchive ندارد؛ فایل را به‌صورت CSV ذخیره کنید یا ستون‌ها را کپی و در کادر بچسبانید.' );
	}
	$zip = new ZipArchive();
	if ( true !== $zip->open( $file ) ) {
		return new WP_Error( 'ost_xlsx', 'فایل اکسل باز نشد.' );
	}
	$strings = array();
	$ss      = $zip->getFromName( 'xl/sharedStrings.xml' );
	if ( $ss && preg_match_all( '#<si>(.*?)</si>#s', $ss, $m ) ) {
		foreach ( $m[1] as $si ) {
			preg_match_all( '#<t[^>]*>(.*?)</t>#s', $si, $t );
			$strings[] = html_entity_decode( implode( '', $t[1] ), ENT_QUOTES | ENT_XML1, 'UTF-8' );
		}
	}
	$sheet = $zip->getFromName( 'xl/worksheets/sheet1.xml' );
	$zip->close();
	if ( ! $sheet ) {
		return new WP_Error( 'ost_xlsx', 'برگه اول فایل اکسل پیدا نشد.' );
	}
	$rows = array();
	preg_match_all( '#<row[^>]*>(.*?)</row>#s', $sheet, $rm );
	foreach ( $rm[1] as $row ) {
		$cells = array();
		preg_match_all( '#<c\b([^>]*)>(.*?)</c>#s', $row, $cm, PREG_SET_ORDER );
		foreach ( $cm as $c ) {
			$col = preg_match( '#r="([A-Z]+)#', $c[1], $r ) ? $r[1] : '';
			$idx = 0;
			foreach ( str_split( $col ) as $ch ) {
				$idx = $idx * 26 + ( ord( $ch ) - 64 );
			}
			$val = '';
			if ( preg_match( '#<v>(.*?)</v>#s', $c[2], $v ) ) {
				$val = false !== strpos( $c[1], 't="s"' ) ? ( $strings[ (int) $v[1] ] ?? '' ) : $v[1];
			} elseif ( preg_match( '#<t[^>]*>(.*?)</t>#s', $c[2], $v ) ) {
				$val = html_entity_decode( $v[1], ENT_QUOTES | ENT_XML1, 'UTF-8' );
			}
			$cells[ max( 0, $idx - 1 ) ] = $val;
		}
		if ( $cells ) {
			ksort( $cells );
			$rows[] = array_replace( array_fill( 0, max( array_keys( $cells ) ) + 1, '' ), $cells );
		}
	}
	return $rows;
}

/**
 * Save an imported list; missing volumes are fetched from SEO Signal (one quota unit).
 *
 * @return string|WP_Error Message.
 */
function ost_manual_kw_import( $id, $rows, $mode, $append ) {
	$old = ost_manual_kw( $id );
	if ( $append ) {
		$have = array();
		foreach ( array_merge( $old['rows'], $rows ) as $r ) {
			$have[ ost_norm( $r['w'] ) ] = $r;
		}
		$rows = array_values( $have );
	}
	$need = array();
	foreach ( $rows as $r ) {
		if ( null === $r['v'] ) {
			$need[] = $r['w'];
		}
	}
	$note = '';
	if ( $need ) {
		$got = ost_ss_connected() ? ost_ss_bulk( $need ) : new WP_Error( 'ost', 'سئو سیگنال متصل نیست' );
		if ( is_wp_error( $got ) ) {
			$note = ' حجم ' . ost_num( count( $need ) ) . ' کلمه گرفته نشد (' . $got->get_error_message() . ')؛ بدون حجم در تحلیل حساب نمی‌شوند.';
		} else {
			$norm = array();
			foreach ( $got as $w => $x ) {
				$norm[ ost_norm( $w ) ] = (int) $x['v'];
			}
			foreach ( $rows as &$r ) {
				if ( null === $r['v'] ) {
					$r['v'] = $norm[ ost_norm( $r['w'] ) ] ?? 0;
				}
			}
			unset( $r );
			$note = ' حجم ' . ost_num( count( $need ) ) . ' کلمه از سئو سیگنال گرفته شد.';
		}
	}
	update_post_meta( $id, '_ost_manual_kw', array( 'rows' => $rows, 'mode' => 'only' === $mode ? 'only' : 'add', 'at' => time() ) );
	return sprintf( '%s کلمه ثبت شد.', ost_num( count( $rows ) ) ) . $note;
}

/**
 * Market depth: words the client and competitors rank for that none of the other
 * sources has, biggest first.
 */
function ost_market_depth( $p, $raw, $max = 200 ) {
	$have = array();
	foreach ( $raw as $r ) {
		foreach ( (array) ( $r['list'] ?? array() ) as $row ) {
			$have[ ost_norm( $row['w'] ) ] = true;
		}
	}
	$words = array();
	foreach ( (array) ost_data( $p['id'], 'seosignal' ) as $d ) {
		foreach ( (array) ( $d['keywords'] ?? array() ) as $k ) {
			$n = ost_norm( $k['w'] ?? '' );
			if ( '' !== $n && ! isset( $have[ $n ] ) && (int) ( $k['v'] ?? 0 ) > 0 ) {
				$words[ $n ] = array( 'w' => $k['w'], 'v' => (int) $k['v'], 'c' => '' );
			}
		}
	}
	foreach ( (array) ost_data( $p['id'], 'gap' ) as $rows ) {
		foreach ( (array) $rows as $k ) {
			$n = ost_norm( $k['w'] ?? '' );
			if ( '' !== $n && ! isset( $have[ $n ] ) && (int) ( $k['v'] ?? 0 ) > 0 ) {
				$words[ $n ] = array( 'w' => $k['w'], 'v' => (int) $k['v'], 'c' => '' );
			}
		}
	}
	usort( $words, function ( $a, $b ) {
		return $b['v'] <=> $a['v'];
	} );
	return array_slice( $words, 0, $max );
}

/**
 * All keyword groups of the market: seed research, the manager's list, market depth.
 *
 * @return array seed / group => {seed, volume, list, manual?, depth?}
 */
function ost_market_raw( $p ) {
	$id  = $p['id'];
	$kw  = ost_data( $id, 'keywords' );
	$man = ost_manual_kw( $id );
	$raw = array();
	if ( 'only' !== $man['mode'] || ! $man['rows'] ) {
		foreach ( $p['inputs']['keywords'] as $s ) {
			if ( isset( $kw[ $s ] ) ) {
				$raw[ $s ] = $kw[ $s ];
			}
		}
	}
	$groups = array();
	foreach ( $man['rows'] as $r ) {
		if ( (int) $r['v'] > 0 ) {
			$g              = '' !== $r['g'] ? $r['g'] : 'کلمات واردشده شما';
			$groups[ $g ][] = array( 'w' => $r['w'], 'v' => (int) $r['v'], 'c' => '' );
		}
	}
	foreach ( $groups as $g => $list ) {
		$key         = isset( $raw[ $g ] ) ? $g . ' (لیست شما)' : $g;
		$raw[ $key ] = array( 'seed' => $key, 'volume' => 0, 'list' => $list, 'manual' => true );
	}
	if ( 'only' !== $man['mode'] || ! $man['rows'] ) {
		$depth = ost_market_depth( $p, $raw );
		if ( $depth ) {
			$raw[ OST_DEPTH_SEED ] = array( 'seed' => OST_DEPTH_SEED, 'volume' => 0, 'list' => $depth, 'depth' => true );
		}
	}
	return $raw;
}

/**
 * Without the AI review, a market-depth word is kept only when it shares a meaningful
 * word with the subject of a seed or of the manager's list.
 */
function ost_depth_related( $word, $raw ) {
	static $cores = array();
	$key = md5( implode( '|', array_keys( $raw ) ) );
	if ( ! isset( $cores[ $key ] ) ) {
		$cores[ $key ] = array();
		foreach ( $raw as $seed => $r ) {
			if ( empty( $r['depth'] ) ) {
				foreach ( explode( ' ', ost_topic_key( empty( $r['manual'] ) ? $seed : implode( ' ', array_column( array_slice( $r['list'], 0, 20 ), 'w' ) ) ) ) as $t ) {
					if ( mb_strlen( $t ) > 2 ) {
						$cores[ $key ][ $t ] = true;
					}
				}
			}
		}
	}
	foreach ( explode( ' ', ost_norm( $word ) ) as $t ) {
		if ( isset( $cores[ $key ][ $t ] ) ) {
			return true;
		}
	}
	return false;
}
