<?php
/**
 * Screaming Frog SEO Spider intake.
 *
 * Screaming Frog has no cloud API; its headless CLI runs on the SEO team's own
 * computer. Fanoos generates a ready agent script per proposal (Windows
 * PowerShell / macOS-Linux bash) that, for the client and every competitor:
 *   1. downloads that domain's URL list (important pages from its sitemaps,
 *      capped at the proposal's crawl limit, e.g. 500),
 *   2. crawls it in list mode (or spider mode with your own .seospiderconfig),
 *   3. uploads Internal:All, the filtered All Inlinks bulk export and the
 *      redirects report to /wp-json/fanoos/v1/sf/{proposal}.
 * The same files can also be uploaded by hand on the technical tab.
 *
 * Only a summary per domain (counts + examples) is stored, never the raw crawl.
 *
 * @package Fanoos
 */

defined( 'ABSPATH' ) || exit;

/**
 * Internal:All column names (several SF versions) → keys.
 */
function fns_sf_columns() {
	return array(
		'address'      => array( 'address', 'url' ),
		'content_type' => array( 'content type', 'content' ),
		'code'         => array( 'status code' ),
		'indexability' => array( 'indexability' ),
		'index_status' => array( 'indexability status' ),
		'title'        => array( 'title 1' ),
		'title_len'    => array( 'title 1 length' ),
		'meta'         => array( 'meta description 1' ),
		'meta_len'     => array( 'meta description 1 length' ),
		'h1'           => array( 'h1-1' ),
		'h1_2'         => array( 'h1-2' ),
		'canonical'    => array( 'canonical link element 1' ),
		'meta_robots'  => array( 'meta robots 1' ),
		'size'         => array( 'size (bytes)', 'size' ),
		'words'        => array( 'word count' ),
		'depth'        => array( 'crawl depth', 'level' ),
		'inlinks'      => array( 'unique inlinks', 'inlinks' ),
		'outlinks'     => array( 'unique outlinks', 'outlinks' ),
		'ext_outlinks' => array( 'unique external outlinks', 'external outlinks' ),
		'ms'           => array( 'response time' ),
		'redirect'     => array( 'redirect url' ),
		'near_dupes'   => array( 'no. near duplicates' ),
		'link_score'   => array( 'link score' ),
	);
}

/**
 * Open a CSV (plain or gzip) and return [handle, header map, delimiter].
 */
function fns_sf_open( $file, $map ) {
	$raw = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( "\x1f\x8b" === substr( $raw, 0, 2 ) ) {
		$raw = (string) gzdecode( $raw );
	}
	if ( "\xFF\xFE" === substr( $raw, 0, 2 ) || "\xFE\xFF" === substr( $raw, 0, 2 ) ) {
		$raw = (string) mb_convert_encoding( $raw, 'UTF-8', 'UTF-16' );
	}
	$raw = preg_replace( '/^\xEF\xBB\xBF/', '', $raw );
	$fh  = fopen( 'php://temp', 'w+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	fwrite( $fh, $raw ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	rewind( $fh );
	$top   = implode( "\n", array_slice( explode( "\n", substr( $raw, 0, 8192 ) ), 0, 4 ) );
	$delim = ',';
	foreach ( array( ';', "\t" ) as $d ) {
		if ( substr_count( $top, $d ) > substr_count( $top, $delim ) ) {
			$delim = $d;
		}
	}
	$idx = array();
	for ( $i = 0; $i < 4 && ! $idx; $i++ ) {
		$row = fgetcsv( $fh, 0, $delim, '"', '\\' );
		if ( false === $row ) {
			break;
		}
		$row = array_map( function ( $h ) {
			return strtolower( trim( (string) $h, " \t\r\n\"'\xEF\xBB\xBF" ) );
		}, $row );
		foreach ( $map as $key => $names ) {
			foreach ( $names as $n ) {
				$pos = array_search( $n, $row, true );
				if ( false !== $pos ) {
					$idx[ $key ] = $pos;
					break;
				}
			}
		}
		if ( count( $idx ) < 2 ) {
			$idx = array();
		}
	}
	return array( $fh, $idx, $delim );
}

/**
 * What kind of export a file is.
 *
 * @return string internal|inlinks|redirects|''
 */
function fns_sf_detect( $file, $name = '' ) {
	$n = strtolower( $name );
	if ( false !== strpos( $n, 'inlink' ) ) {
		return 'inlinks';
	}
	if ( false !== strpos( $n, 'redirect' ) || false !== strpos( $n, 'chain' ) ) {
		return 'redirects';
	}
	if ( false !== strpos( $n, 'internal' ) ) {
		return 'internal';
	}
	$raw = (string) file_get_contents( $file, false, null, 0, 4096 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( "\x1f\x8b" === substr( $raw, 0, 2 ) ) {
		$raw = (string) @gzdecode( (string) file_get_contents( $file ) ); // phpcs:ignore
	}
	$raw = strtolower( substr( $raw, 0, 4096 ) );
	if ( false !== strpos( $raw, 'destination' ) && false !== strpos( $raw, 'source' ) ) {
		return 'inlinks';
	}
	if ( false !== strpos( $raw, 'number of redirects' ) || false !== strpos( $raw, 'final address' ) ) {
		return 'redirects';
	}
	return false !== strpos( $raw, 'address' ) ? 'internal' : '';
}

/**
 * Summary of an Internal:All export.
 *
 * @return array|WP_Error
 */
function fns_sf_summarize_internal( $file ) {
	list( $fh, $idx, $delim ) = fns_sf_open( $file, fns_sf_columns() );
	if ( ! isset( $idx['address'] ) ) {
		return new WP_Error( 'fns_sf_cols', 'ستون Address پیدا نشد؛ فایل باید خروجی Internal › All با زبان انگلیسی Screaming Frog باشد.' );
	}
	$s = array(
		'urls' => 0, 'html' => 0, 'codes' => array( '2xx' => 0, '3xx' => 0, '4xx' => 0, '5xx' => 0, 'other' => 0 ),
		'indexable' => 0, 'non_indexable' => 0, 'noindex' => 0, 'canonicalised' => 0,
		'title_missing' => 0, 'title_long' => 0, 'title_short' => 0, 'title_dup' => 0,
		'meta_missing' => 0, 'meta_long' => 0, 'meta_dup' => 0,
		'h1_missing' => 0, 'h1_multi' => 0, 'h1_dup' => 0,
		'thin' => 0, 'deep' => 0, 'depth_sum' => 0, 'slow' => 0, 'ms_sum' => 0, 'heavy' => 0,
		'weak_inlinks' => 0, 'inlinks_sum' => 0, 'near_dupes' => 0, 'params' => 0, 'long_url' => 0,
		'examples' => array(), 'top_linked' => array(), 'depth_dist' => array(),
	);
	$titles = array();
	$metas  = array();
	$h1s    = array();
	$ex     = function ( $type, $url, $note = '' ) use ( &$s ) {
		if ( count( $s['examples'][ $type ] ?? array() ) < 6 ) {
			$s['examples'][ $type ][] = array( 'url' => $url, 'note' => $note );
		}
	};
	while ( ( $line = fgetcsv( $fh, 0, $delim, '"', '\\' ) ) !== false ) {
		$r = array();
		foreach ( $idx as $k => $pos ) {
			$r[ $k ] = isset( $line[ $pos ] ) ? trim( (string) $line[ $pos ] ) : '';
		}
		if ( '' === ( $r['address'] ?? '' ) || ! preg_match( '#^https?://#i', $r['address'] ) ) {
			continue;
		}
		$s['urls']++;
		$code = (int) ( $r['code'] ?? 0 );
		$b    = $code >= 200 && $code < 300 ? '2xx' : ( $code >= 300 && $code < 400 ? '3xx' : ( $code >= 400 && $code < 500 ? '4xx' : ( $code >= 500 ? '5xx' : 'other' ) ) );
		$s['codes'][ $b ]++;
		if ( '4xx' === $b || '5xx' === $b ) {
			$ex( 'errors', $r['address'], (string) $code );
		}
		$is_html = '' === ( $r['content_type'] ?? '' ) || false !== stripos( $r['content_type'], 'html' );
		if ( ! $is_html ) {
			continue;
		}
		$s['html']++;
		if ( '2xx' !== $b ) {
			continue;
		}
		$indexable = '' === ( $r['indexability'] ?? '' ) || 0 === strcasecmp( $r['indexability'], 'Indexable' );
		$indexable ? $s['indexable']++ : $s['non_indexable']++;
		if ( false !== stripos( $r['meta_robots'] ?? '', 'noindex' ) || false !== stripos( $r['index_status'] ?? '', 'noindex' ) ) {
			$s['noindex']++;
		}
		if ( false !== stripos( $r['index_status'] ?? '', 'canonical' ) ) {
			$s['canonicalised']++;
		}
		if ( ! $indexable ) {
			continue;
		}
		$t  = $r['title'] ?? '';
		$tl = (int) ( $r['title_len'] ?? mb_strlen( $t ) );
		if ( '' === $t ) {
			$s['title_missing']++;
			$ex( 'title_missing', $r['address'] );
		} elseif ( $tl > 65 ) {
			$s['title_long']++;
		} elseif ( $tl < 25 ) {
			$s['title_short']++;
		}
		$titles[ fns_norm( $t ) ][] = $r['address'];
		$m = $r['meta'] ?? '';
		if ( '' === $m ) {
			$s['meta_missing']++;
		} elseif ( (int) ( $r['meta_len'] ?? mb_strlen( $m ) ) > 165 ) {
			$s['meta_long']++;
		} else {
			$metas[ fns_norm( $m ) ][] = $r['address'];
		}
		$h = $r['h1'] ?? '';
		if ( '' === $h ) {
			$s['h1_missing']++;
			$ex( 'h1_missing', $r['address'] );
		} else {
			$h1s[ fns_norm( $h ) ][] = $r['address'];
		}
		if ( '' !== ( $r['h1_2'] ?? '' ) ) {
			$s['h1_multi']++;
		}
		$w = (int) ( $r['words'] ?? 0 );
		if ( isset( $r['words'] ) && $w < 250 ) {
			$s['thin']++;
			$ex( 'thin', $r['address'], fns_num( $w ) . ' کلمه' );
		}
		$d = (int) ( $r['depth'] ?? 0 );
		$s['depth_sum'] += $d;
		$s['depth_dist'][ min( $d, 6 ) ] = ( $s['depth_dist'][ min( $d, 6 ) ] ?? 0 ) + 1;
		if ( $d > 3 ) {
			$s['deep']++;
		}
		$ms = (float) ( $r['ms'] ?? 0 );
		$ms = $ms > 0 && $ms < 60 ? $ms * 1000 : $ms; // SF reports seconds.
		$s['ms_sum'] += $ms;
		if ( $ms > 1200 ) {
			$s['slow']++;
			$ex( 'slow', $r['address'], fns_num( $ms / 1000, 1 ) . ' ثانیه' );
		}
		if ( (int) ( $r['size'] ?? 0 ) > 1500000 ) {
			$s['heavy']++;
		}
		$in = (int) ( $r['inlinks'] ?? 0 );
		$s['inlinks_sum'] += $in;
		if ( isset( $r['inlinks'] ) && $in <= 1 && $d > 0 ) {
			$s['weak_inlinks']++;
			$ex( 'weak_inlinks', $r['address'] );
		}
		if ( (int) ( $r['near_dupes'] ?? 0 ) > 0 ) {
			$s['near_dupes']++;
		}
		if ( false !== strpos( $r['address'], '?' ) ) {
			$s['params']++;
		}
		if ( mb_strlen( rawurldecode( $r['address'] ) ) > 115 ) {
			$s['long_url']++;
		}
		$s['top_linked'][] = array( 'url' => $r['address'], 'in' => $in, 'score' => (float) ( $r['link_score'] ?? 0 ) );
	}
	fclose( $fh ); // phpcs:ignore
	foreach ( array( 'title_dup' => $titles, 'meta_dup' => $metas, 'h1_dup' => $h1s ) as $k => $groups ) {
		foreach ( $groups as $text => $urls ) {
			if ( '' !== $text && count( $urls ) > 1 ) {
				$s[ $k ] += count( $urls );
				if ( 'title_dup' === $k ) {
					$ex( 'title_dup', $urls[0], fns_num( count( $urls ) ) . ' صفحه با یک عنوان' );
				}
			}
		}
	}
	usort( $s['top_linked'], function ( $a, $b ) {
		return $b['in'] <=> $a['in'];
	} );
	$s['top_linked'] = array_slice( $s['top_linked'], 0, 10 );
	$n               = max( 1, $s['indexable'] );
	$s['avg_depth']  = round( $s['depth_sum'] / $n, 1 );
	$s['avg_ms']     = (int) round( $s['ms_sum'] / $n );
	$s['avg_inlinks'] = round( $s['inlinks_sum'] / $n, 1 );
	ksort( $s['depth_dist'] );
	return $s;
}

/**
 * Summary of the (filtered) All Inlinks export: broken links, links to
 * redirects, body vs navigation links, generic anchors.
 */
function fns_sf_summarize_inlinks( $file ) {
	$map = array(
		'type' => array( 'type' ), 'source' => array( 'source' ), 'dest' => array( 'destination' ), 'anchor' => array( 'anchor' ),
		'code' => array( 'status code' ), 'follow' => array( 'follow' ), 'pos' => array( 'link position' ),
	);
	list( $fh, $idx, $delim ) = fns_sf_open( $file, $map );
	if ( ! isset( $idx['source'], $idx['dest'] ) ) {
		return new WP_Error( 'fns_sf_cols', 'ستون‌های Source و Destination در خروجی All Inlinks پیدا نشد.' );
	}
	$generic = array_map( 'fns_norm', fns_list( fns_setting( 'generic_anchors' ) ) );
	$s       = array( 'body' => 0, 'nav' => 0, 'broken' => 0, 'to_redirect' => 0, 'nofollow' => 0, 'generic_anchor' => 0, 'empty_anchor' => 0, 'examples' => array() );
	$targets = array();
	while ( ( $line = fgetcsv( $fh, 0, $delim, '"', '\\' ) ) !== false ) {
		$r = array();
		foreach ( $idx as $k => $pos ) {
			$r[ $k ] = isset( $line[ $pos ] ) ? trim( (string) $line[ $pos ] ) : '';
		}
		if ( 'NavCount' === ( $r['type'] ?? '' ) ) {
			$s['nav'] += (int) ( $r['anchor'] ?? 0 );
			continue;
		}
		$type = strtolower( $r['type'] ?? 'hyperlink' );
		if ( ! in_array( $type, array( 'hyperlink', 'ahref', '' ), true ) ) {
			continue;
		}
		$pos  = strtolower( $r['pos'] ?? '' );
		$code = (int) ( $r['code'] ?? 200 );
		if ( '' === $pos || 'content' === $pos ) {
			$s['body']++;
			$targets[ $r['dest'] ] = ( $targets[ $r['dest'] ] ?? 0 ) + 1;
			$a = fns_norm( $r['anchor'] ?? '' );
			if ( '' === $a ) {
				$s['empty_anchor']++;
			} elseif ( in_array( $a, $generic, true ) ) {
				$s['generic_anchor']++;
			}
		} else {
			$s['nav']++;
		}
		if ( $code >= 400 || 0 === $code && '' !== ( $r['code'] ?? '' ) ) {
			$s['broken']++;
			if ( count( $s['examples']['broken'] ?? array() ) < 8 ) {
				$s['examples']['broken'][] = array( 'from' => $r['source'], 'to' => $r['dest'], 'code' => $code, 'anchor' => $r['anchor'] ?? '' );
			}
		} elseif ( $code >= 300 && $code < 400 ) {
			$s['to_redirect']++;
		}
		if ( 'false' === strtolower( $r['follow'] ?? 'true' ) ) {
			$s['nofollow']++;
		}
	}
	fclose( $fh ); // phpcs:ignore
	$s['linked_pages'] = count( $targets );
	return $s;
}

/**
 * Summary of a redirects report: chains, loops, temporary redirects.
 */
function fns_sf_summarize_redirects( $file ) {
	$map = array(
		'address' => array( 'address', 'source' ), 'code' => array( 'status code' ), 'hops' => array( 'number of redirects' ),
		'loop' => array( 'loop' ), 'final' => array( 'final address' ), 'type' => array( 'redirect type', 'chain type' ),
	);
	list( $fh, $idx, $delim ) = fns_sf_open( $file, $map );
	$s = array( 'total' => 0, 'chains' => 0, 'loops' => 0, 'temporary' => 0 );
	while ( ( $line = fgetcsv( $fh, 0, $delim, '"', '\\' ) ) !== false ) {
		$r = array();
		foreach ( $idx as $k => $pos ) {
			$r[ $k ] = isset( $line[ $pos ] ) ? trim( (string) $line[ $pos ] ) : '';
		}
		if ( '' === ( $r['address'] ?? '' ) ) {
			continue;
		}
		$s['total']++;
		if ( (int) ( $r['hops'] ?? 1 ) > 1 ) {
			$s['chains']++;
		}
		if ( 'true' === strtolower( $r['loop'] ?? '' ) ) {
			$s['loops']++;
		}
		if ( in_array( (int) ( $r['code'] ?? 0 ), array( 302, 303, 307 ), true ) ) {
			$s['temporary']++;
		}
	}
	fclose( $fh ); // phpcs:ignore
	return $s;
}

/**
 * Import one uploaded file for a domain of a proposal.
 *
 * @return array|WP_Error {kind, summary}
 */
function fns_sf_import( $id, $domain, $file, $name = '' ) {
	$kind = fns_sf_detect( $file, $name );
	if ( '' === $kind ) {
		return new WP_Error( 'fns_sf_kind', 'نوع فایل شناخته نشد (Internal All، All Inlinks یا Redirects).' );
	}
	$sum = 'internal' === $kind ? fns_sf_summarize_internal( $file ) : ( 'inlinks' === $kind ? fns_sf_summarize_inlinks( $file ) : fns_sf_summarize_redirects( $file ) );
	if ( is_wp_error( $sum ) ) {
		return $sum;
	}
	$domain              = fns_domain( $domain );
	$all                 = fns_data( $id, 'sf' );
	$all[ $domain ][ $kind ] = $sum;
	$all[ $domain ]['at']    = time();
	fns_set_data( $id, 'sf', $all );
	return array( 'kind' => $kind, 'summary' => $sum );
}

/**
 * Agent script for a proposal (Windows PowerShell or bash).
 */
function fns_sf_agent_script( $p, $os ) {
	$base = untrailingslashit( rest_url( 'fanoos/v1/sf/' . $p['id'] ) );
	$vars = array( '{{BASE}}' => $base, '{{KEY}}' => $p['token'], '{{DATE}}' => gmdate( 'Y-m-d' ), '{{NAME}}' => preg_replace( '/[^\w.-]/', '', $p['inputs']['domain'] ?: 'proposal-' . $p['id'] ) );
	if ( 'win' === $os ) {
		return strtr( <<<'PS1'
<#
  Fanoos (Ronagh) - Screaming Frog agent for Windows.  Proposal: {{NAME}}  Generated {{DATE}}
  Crawls the client site and every competitor (important pages from each sitemap) and sends the result to Fanoos.

  Run:      powershell -ExecutionPolicy Bypass -File fanoos-sf-{{NAME}}.ps1
  Options:  -Mode spider -Config "C:\path\limit-500.seospiderconfig"   (spider mode with your own crawl limit)
            -SpiderPath "C:\...\ScreamingFrogSEOSpiderCli.exe"
            -Only "competitor.ir"   (just one domain)
#>
param([string]$Mode = "list", [string]$Config = "", [string]$SpiderPath = "", [string]$Only = "")
$ErrorActionPreference = "Stop"
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
$Base = "{{BASE}}"; $Key = "{{KEY}}"; $Headers = @{ "X-Fanoos-Key" = $Key }
if (-not $SpiderPath) {
  $SpiderPath = @("$env:ProgramFiles\Screaming Frog SEO Spider\ScreamingFrogSEOSpiderCli.exe", "${env:ProgramFiles(x86)}\Screaming Frog SEO Spider\ScreamingFrogSEOSpiderCli.exe") | Where-Object { Test-Path $_ } | Select-Object -First 1
}
if (-not ($SpiderPath -and (Test-Path $SpiderPath))) { Write-Error "ScreamingFrogSEOSpiderCli.exe not found. Install SEO Spider or pass -SpiderPath."; exit 1 }
$targets = (Invoke-RestMethod -Uri "$Base/targets" -Headers $Headers).targets
foreach ($t in $targets) {
  $domain = $t.domain
  if ($Only -and $domain -ne $Only) { continue }
  Write-Host "[Fanoos] === $domain ($($t.role)) ==="
  $out = Join-Path $env:TEMP ("fanoos-" + $domain + "-" + (Get-Date -Format "yyyyMMddHHmmss"))
  New-Item -ItemType Directory -Path $out | Out-Null
  $args0 = @("--headless", "--output-folder", $out, "--export-tabs", "Internal:All", "--bulk-export", "Links:All Inlinks", "--save-report", "Redirects:All Redirects", "--overwrite")
  if ($Mode -eq "spider") { $args0 = @("--crawl", "https://$domain/") + $args0 }
  else {
    $list = Join-Path $out "urls.txt"
    Invoke-WebRequest -Uri ("$Base/urls?domain=" + [uri]::EscapeDataString($domain)) -Headers $Headers -OutFile $list -UseBasicParsing
    Write-Host "[Fanoos] URLs to crawl: $((Get-Content $list).Count)"
    $args0 = @("--crawl-list", $list) + $args0
  }
  if ($Config) { $args0 += @("--config", $Config) }
  & $SpiderPath @args0
  $files = Get-ChildItem -Path $out -Filter *.csv -Recurse
  if (-not $files) { Write-Warning "No export produced for $domain"; continue }
  foreach ($f in $files) {
    $gz = $f.FullName + ".gz"
    $in = [IO.File]::OpenRead($f.FullName); $o = [IO.File]::Create($gz)
    $z = New-Object IO.Compression.GZipStream($o, [IO.Compression.CompressionMode]::Compress)
    $in.CopyTo($z); $z.Dispose(); $o.Dispose(); $in.Dispose()
    Write-Host "[Fanoos] Uploading $($f.Name) ($([Math]::Round((Get-Item $gz).Length / 1MB, 1)) MB) ..."
    $r = & curl.exe -sS -X POST ("$Base" + "?domain=" + $domain) -H "X-Fanoos-Key: $Key" -F "file=@$gz;type=application/gzip" -F "name=$($f.Name)"
    Write-Host $r
  }
  Remove-Item -Recurse -Force $out
}
Write-Host "[Fanoos] Done. Refresh the technical tab in Fanoos."
PS1
		, $vars );
	}
	return strtr( <<<'SH'
#!/usr/bin/env bash
# Fanoos (Ronagh) - Screaming Frog agent for macOS / Linux.  Proposal: {{NAME}}  Generated {{DATE}}
#   Run:      bash fanoos-sf-{{NAME}}.sh
#   Options:  --mode spider --config limit-500.seospiderconfig   --spider /path/to/cli   --only competitor.ir
set -euo pipefail
BASE="{{BASE}}"; KEY="{{KEY}}"; MODE="list"; CONFIG=""; SPIDER="${SF_SPIDER:-}"; ONLY=""
while [[ $# -gt 0 ]]; do
  case "$1" in
    --mode) MODE="$2"; shift 2 ;; --config) CONFIG="$2"; shift 2 ;;
    --spider) SPIDER="$2"; shift 2 ;; --only) ONLY="$2"; shift 2 ;;
    *) echo "Unknown option $1" >&2; exit 1 ;;
  esac
done
if [[ -z "$SPIDER" ]]; then
  for c in "/Applications/Screaming Frog SEO Spider.app/Contents/MacOS/ScreamingFrogSEOSpiderLauncher" "$(command -v screamingfrogseospider || true)"; do
    [[ -n "$c" && -x "$c" ]] && { SPIDER="$c"; break; }
  done
fi
[[ -n "$SPIDER" ]] || { echo "Screaming Frog CLI not found (use --spider PATH)" >&2; exit 1; }
TARGETS=$(curl -fsS "$BASE/targets?format=text" -H "X-Fanoos-Key: $KEY")
for DOMAIN in $TARGETS; do
  [[ -n "$ONLY" && "$DOMAIN" != "$ONLY" ]] && continue
  echo "[Fanoos] === $DOMAIN ==="
  OUT="$(mktemp -d -t fanoos-XXXX)"
  ARGS=(--headless --output-folder "$OUT" --export-tabs "Internal:All" --bulk-export "Links:All Inlinks" --save-report "Redirects:All Redirects" --overwrite)
  if [[ "$MODE" == "spider" ]]; then ARGS=(--crawl "https://$DOMAIN/" "${ARGS[@]}")
  else
    curl -fsS "$BASE/urls?domain=$DOMAIN" -H "X-Fanoos-Key: $KEY" -o "$OUT/urls.txt"
    echo "[Fanoos] URLs to crawl: $(wc -l < "$OUT/urls.txt")"
    ARGS=(--crawl-list "$OUT/urls.txt" "${ARGS[@]}")
  fi
  [[ -n "$CONFIG" ]] && ARGS+=(--config "$CONFIG")
  "$SPIDER" "${ARGS[@]}" || echo "[Fanoos] SEO Spider exited with $?" >&2
  while IFS= read -r -d '' F; do
    gzip -c "$F" > "$F.gz"
    echo "[Fanoos] Uploading $(basename "$F") ..."
    curl -sS -X POST "$BASE?domain=$DOMAIN" -H "X-Fanoos-Key: $KEY" -F "file=@$F.gz" -F "name=$(basename "$F")"; echo
  done < <(find "$OUT" -name '*.csv' -print0)
  rm -rf "$OUT"
done
echo "[Fanoos] Done. Refresh the technical tab in Fanoos."
SH
	, $vars );
}
