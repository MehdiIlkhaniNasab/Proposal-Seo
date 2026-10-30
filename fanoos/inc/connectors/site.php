<?php
/**
 * Built-in quick audit of a site (no Screaming Frog needed): home page, robots.txt,
 * sitemaps (URL count by type) and a sample of pages read in parallel. Works for
 * the client and every competitor, so the technical slide always has data; a
 * Screaming Frog crawl adds the deep checks on top.
 *
 * @package Fanoos
 */

defined( 'ABSPATH' ) || exit;

/**
 * Browser-like request headers (some Iranian hosts block unknown bots).
 */
function fns_site_headers() {
	return array(
		'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36 FanoosAudit/1.0',
		'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
		'Accept-Language' => 'fa-IR,fa;q=0.9,en;q=0.6',
	);
}

/**
 * GET a URL; returns [code, body, final_url, ms, headers] or WP_Error.
 */
function fns_site_get( $url, $timeout = 15 ) {
	$t   = microtime( true );
	$res = fns_http( $url, array( 'method' => 'GET', 'timeout' => $timeout, 'redirection' => 5, 'headers' => fns_site_headers(), 'sslverify' => false ) );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$final = $url;
	if ( isset( $res['http_response'] ) && is_object( $res['http_response'] ) && method_exists( $res['http_response'], 'get_response_object' ) ) {
		$final = (string) $res['http_response']->get_response_object()->url;
	}
	return array(
		'code'    => (int) wp_remote_retrieve_response_code( $res ),
		'body'    => (string) wp_remote_retrieve_body( $res ),
		'url'     => $final,
		'ms'      => (int) round( 1000 * ( microtime( true ) - $t ) ),
		'headers' => wp_remote_retrieve_headers( $res ),
	);
}

/**
 * Read several URLs in parallel.
 *
 * @return array url => [code, body, ms] (or ['error' => msg])
 */
function fns_site_get_many( $urls, $timeout = 15 ) {
	$out = array();
	if ( ! $urls ) {
		return $out;
	}
	$requests = array();
	foreach ( array_values( $urls ) as $i => $u ) {
		$requests[ $i ] = array( 'url' => $u, 'type' => 'GET', 'headers' => fns_site_headers() );
	}
	$class = class_exists( '\WpOrg\Requests\Requests' ) ? '\WpOrg\Requests\Requests' : ( class_exists( 'Requests' ) ? 'Requests' : '' );
	if ( '' === $class ) {
		foreach ( $urls as $u ) {
			$r         = fns_site_get( $u, $timeout );
			$out[ $u ] = is_wp_error( $r ) ? array( 'error' => $r->get_error_message() ) : $r;
		}
		return $out;
	}
	$t   = microtime( true );
	$res = $class::request_multiple( $requests, array( 'timeout' => $timeout, 'connect_timeout' => 8, 'redirects' => 5, 'verify' => false ) );
	$ms  = (int) round( 1000 * ( microtime( true ) - $t ) );
	foreach ( array_values( $urls ) as $i => $u ) {
		$r = $res[ $i ] ?? null;
		if ( ! is_object( $r ) || ! isset( $r->status_code ) ) {
			$out[ $u ] = array( 'error' => is_object( $r ) && method_exists( $r, 'getMessage' ) ? $r->getMessage() : 'no response' );
			continue;
		}
		$out[ $u ] = array( 'code' => (int) $r->status_code, 'body' => (string) $r->body, 'url' => (string) $r->url, 'ms' => $ms, 'redirects' => (int) $r->redirects );
	}
	return $out;
}

/**
 * SEO elements of one HTML page.
 */
function fns_site_parse_html( $html, $url ) {
	$get = function ( $re ) use ( $html ) {
		return preg_match( $re, $html, $m ) ? html_entity_decode( trim( wp_strip_all_tags( $m[1] ) ), ENT_QUOTES, 'UTF-8' ) : '';
	};
	$host   = fns_domain( $url );
	$title  = $get( '#<title[^>]*>(.*?)</title>#is' );
	$meta   = $get( '#<meta[^>]+name=["\']description["\'][^>]*content=["\'](.*?)["\']#is' ) ?: $get( '#<meta[^>]+content=["\'](.*?)["\'][^>]*name=["\']description["\']#is' );
	$robots = $get( '#<meta[^>]+name=["\']robots["\'][^>]*content=["\'](.*?)["\']#is' );
	$canon  = $get( '#<link[^>]+rel=["\']canonical["\'][^>]*href=["\'](.*?)["\']#is' ) ?: $get( '#<link[^>]+href=["\'](.*?)["\'][^>]*rel=["\']canonical["\']#is' );
	preg_match_all( '#<h1[^>]*>(.*?)</h1>#is', $html, $h1 );
	preg_match_all( '#<h2[^>]*>#i', $html, $h2 );
	preg_match_all( '#<img\b[^>]*>#i', $html, $imgs );
	$no_alt = 0;
	foreach ( $imgs[0] as $img ) {
		if ( ! preg_match( '#\balt=["\'][^"\']+["\']#i', $img ) ) {
			$no_alt++;
		}
	}
	preg_match_all( '#<a\b[^>]*href=["\']([^"\'\#]+)#i', $html, $links );
	$internal = 0;
	$external = 0;
	foreach ( $links[1] as $href ) {
		if ( preg_match( '#^(mailto|tel|javascript):#i', $href ) ) {
			continue;
		}
		$h = preg_match( '#^https?://#i', $href ) ? fns_domain( $href ) : $host;
		( $h === $host ) ? $internal++ : $external++;
	}
	$schema = array();
	if ( preg_match_all( '#<script[^>]+application/ld\+json[^>]*>(.*?)</script>#is', $html, $ld ) ) {
		foreach ( $ld[1] as $block ) {
			if ( preg_match_all( '#"@type"\s*:\s*(\[[^\]]*\]|"[^"]+")#', $block, $types ) ) {
				foreach ( $types[1] as $t ) {
					foreach ( (array) json_decode( '[' . trim( $t, '[]' ) . ']', true ) as $one ) {
						$schema[] = (string) $one;
					}
				}
			}
		}
	}
	$text  = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( preg_replace( '#<(script|style|nav|header|footer|noscript)[^>]*>.*?</\1>#is', ' ', $html ) ) ) );
	$words = $text ? count( preg_split( '/\s+/u', $text ) ) : 0;
	return array(
		'title'      => $title,
		'title_len'  => mb_strlen( $title ),
		'meta'       => $meta,
		'meta_len'   => mb_strlen( $meta ),
		'h1'         => count( $h1[0] ),
		'h1_text'    => isset( $h1[1][0] ) ? mb_substr( trim( wp_strip_all_tags( $h1[1][0] ) ), 0, 120 ) : '',
		'h2'         => count( $h2[0] ),
		'canonical'  => $canon,
		'noindex'    => false !== stripos( $robots, 'noindex' ),
		'images'     => count( $imgs[0] ),
		'no_alt'     => $no_alt,
		'internal'   => $internal,
		'external'   => $external,
		'schema'     => array_values( array_unique( $schema ) ),
		'words'      => $words,
		'viewport'   => (bool) preg_match( '#<meta[^>]+name=["\']viewport#i', $html ),
		'og'         => (bool) preg_match( '#<meta[^>]+property=["\']og:#i', $html ),
		'lazy'       => (bool) preg_match( '#loading=["\']lazy#i', $html ),
		'size'       => strlen( $html ),
		'cms'        => fns_site_cms( $html ),
	);
}

/**
 * Detect the CMS from HTML.
 */
function fns_site_cms( $html ) {
	if ( false !== stripos( $html, 'woocommerce' ) ) {
		return 'WordPress + WooCommerce';
	}
	if ( false !== stripos( $html, '/wp-content/' ) || false !== stripos( $html, '/wp-includes/' ) ) {
		return 'WordPress';
	}
	if ( preg_match( '#<meta[^>]+name=["\']generator["\'][^>]*content=["\']([^"\']+)#i', $html, $m ) ) {
		return mb_substr( $m[1], 0, 40 );
	}
	if ( false !== stripos( $html, '__NEXT_DATA__' ) ) {
		return 'Next.js';
	}
	if ( false !== stripos( $html, 'data-reactroot' ) || false !== stripos( $html, 'id="root"' ) ) {
		return 'React (SPA)';
	}
	return 'نامشخص (اختصاصی)';
}

/**
 * robots.txt: exists, blocks everything?, sitemap lines.
 */
function fns_site_robots( $base ) {
	$r = fns_site_get( $base . 'robots.txt', 12 );
	if ( is_wp_error( $r ) || 200 !== $r['code'] || false !== stripos( $r['body'], '<html' ) ) {
		return array( 'exists' => false, 'sitemaps' => array(), 'blocks_all' => false, 'disallow' => 0 );
	}
	preg_match_all( '#^\s*sitemap:\s*(\S+)#im', $r['body'], $sm );
	$block_all = (bool) preg_match( '#user-agent:\s*\*\s*(?:\r?\n(?!\s*user-agent).*)*?\r?\n\s*disallow:\s*/\s*(\r?\n|$)#i', $r['body'] );
	return array(
		'exists'     => true,
		'sitemaps'   => array_values( array_unique( $sm[1] ) ),
		'blocks_all' => $block_all,
		'disallow'   => preg_match_all( '#^\s*disallow:\s*\S#im', $r['body'] ),
	);
}

/**
 * Sitemaps: total URLs and URLs by type, plus a list of URLs for crawling.
 *
 * @return array{found: bool, files: int, total: int, types: array, urls: string[]}
 */
function fns_site_sitemaps( $base, $from_robots, $collect = 500 ) {
	$queue = $from_robots ?: array( $base . 'sitemap_index.xml', $base . 'sitemap.xml', $base . 'wp-sitemap.xml' );
	$seen  = array();
	$total = 0;
	$types = array();
	$urls  = array();
	$found = false;
	$by_file = array();
	while ( $queue && count( $seen ) < 60 ) {
		$sm = array_shift( $queue );
		if ( isset( $seen[ $sm ] ) ) {
			continue;
		}
		$seen[ $sm ] = true;
		$r           = fns_site_get( $sm, 20 );
		if ( is_wp_error( $r ) || 200 !== $r['code'] ) {
			continue;
		}
		$body = $r['body'];
		if ( "\x1f\x8b" === substr( $body, 0, 2 ) && function_exists( 'gzdecode' ) ) {
			$body = (string) @gzdecode( $body ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		if ( false === stripos( $body, '<urlset' ) && false === stripos( $body, '<sitemapindex' ) ) {
			continue;
		}
		$found = true;
		preg_match_all( '#<loc>\s*(?:<!\[CDATA\[)?\s*(.*?)\s*(?:\]\]>)?\s*</loc>#is', $body, $locs );
		if ( false !== stripos( $body, '<sitemapindex' ) ) {
			foreach ( $locs[1] as $child ) {
				$queue[] = html_entity_decode( $child );
			}
			if ( ! $from_robots ) {
				$queue = array_values( array_filter( $queue, function ( $q ) use ( $base ) {
					return 0 !== strpos( $q, $base . 'sitemap' ) || false !== strpos( $q, '/sitemap' );
				} ) );
			}
			continue;
		}
		if ( ! $from_robots ) {
			$queue = array(); // A plain urlset at a guessed address: do not try the other guesses.
		}
		$type            = fns_site_sitemap_type( $sm );
		$n               = count( $locs[1] );
		$total          += $n;
		$types[ $type ]  = ( $types[ $type ] ?? 0 ) + $n;
		$by_file[ $sm ]  = array_map( 'html_entity_decode', $locs[1] );
	}
	// URL list for crawling: spread over the files (categories / products / posts all represented).
	while ( count( $urls ) < $collect && $by_file ) {
		foreach ( $by_file as $f => &$list ) {
			if ( ! $list ) {
				unset( $by_file[ $f ] );
				continue;
			}
			$urls[] = array_shift( $list );
			if ( count( $urls ) >= $collect ) {
				break;
			}
		}
		unset( $list );
	}
	return array( 'found' => $found, 'files' => count( $seen ), 'total' => $total, 'types' => $types, 'urls' => array_values( array_unique( $urls ) ) );
}

/**
 * Content type of a sitemap file from its name.
 */
function fns_site_sitemap_type( $url ) {
	$u = strtolower( $url );
	$map = array(
		'product_cat' => 'دسته محصولات',
		'product-cat' => 'دسته محصولات',
		'product'     => 'محصولات',
		'category'    => 'دسته‌بندی',
		'post_tag'    => 'برچسب',
		'tag'         => 'برچسب',
		'post'        => 'مقالات',
		'blog'        => 'مقالات',
		'article'     => 'مقالات',
		'page'        => 'برگه‌ها',
		'brand'       => 'برندها',
		'author'      => 'نویسندگان',
		'service'     => 'خدمات',
	);
	foreach ( $map as $needle => $label ) {
		if ( false !== strpos( $u, $needle ) ) {
			return $label;
		}
	}
	return 'سایر';
}

/**
 * Quick audit of a domain.
 *
 * @return array|WP_Error
 */
function fns_site_audit( $domain, $sample = 14, $collect = 500 ) {
	$base = fns_home_url( $domain );
	$home = fns_site_get( $base, 20 );
	if ( is_wp_error( $home ) ) {
		$home = fns_site_get( 'https://www.' . fns_domain( $domain ) . '/', 20 );
	}
	if ( is_wp_error( $home ) ) {
		return new WP_Error( 'fns_site', sprintf( 'سایت %s باز نشد: %s', $domain, fns_net_error( $home ) ) );
	}
	$final  = $home['url'];
	$base   = trailingslashit( preg_replace( '#^(https?://[^/]+).*$#', '$1', $final ) );
	$robots = fns_site_robots( $base );
	$maps   = fns_site_sitemaps( $base, $robots['sitemaps'], $collect );
	$http   = fns_site_get( 'http://' . fns_domain( $domain ) . '/', 10 );
	$pages  = array( $final => array_merge( fns_site_parse_html( $home['body'], $final ), array( 'code' => $home['code'], 'ms' => $home['ms'] ) ) );
	$pick   = array();
	if ( $maps['urls'] ) {
		$step = max( 1, (int) floor( count( $maps['urls'] ) / $sample ) );
		for ( $i = 0; $i < count( $maps['urls'] ) && count( $pick ) < $sample; $i += $step ) {
			if ( fns_path( $maps['urls'][ $i ] ) !== '/' ) {
				$pick[] = $maps['urls'][ $i ];
			}
		}
	} else {
		preg_match_all( '#<a\b[^>]*href=["\'](' . preg_quote( $base, '#' ) . '[^"\'\#?]+)#i', $home['body'], $m );
		$pick = array_slice( array_values( array_unique( $m[1] ) ), 0, $sample );
	}
	foreach ( fns_site_get_many( $pick ) as $u => $r ) {
		$pages[ $u ] = isset( $r['error'] )
			? array( 'code' => 0, 'error' => $r['error'] )
			: array_merge( 200 === $r['code'] ? fns_site_parse_html( $r['body'], $u ) : array(), array( 'code' => $r['code'], 'ms' => $r['ms'], 'redirects' => $r['redirects'] ?? 0 ) );
	}
	$h = $home['headers'];
	return array(
		'domain'   => fns_domain( $domain ),
		'final'    => $final,
		'https'    => 0 === strpos( $final, 'https://' ),
		'http_redirects' => ! is_wp_error( $http ) && 0 === strpos( $http['url'], 'https://' ),
		'home_ms'  => $home['ms'],
		'server'   => is_object( $h ) || is_array( $h ) ? (string) ( $h['server'] ?? '' ) : '',
		'cdn'      => ( is_object( $h ) || is_array( $h ) ) && ( ! empty( $h['cf-ray'] ) || false !== stripos( (string) ( $h['server'] ?? '' ), 'arvan' ) || ! empty( $h['ar-sid'] ) ) ? 'CDN' : '',
		'robots'   => $robots,
		'sitemap'  => array_diff_key( $maps, array( 'urls' => 1 ) ),
		'urls'     => $maps['urls'],
		'pages'    => $pages,
		'cms'      => $pages[ $final ]['cms'] ?? '',
		'at'       => time(),
	);
}
