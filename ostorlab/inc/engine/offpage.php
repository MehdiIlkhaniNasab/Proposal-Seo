<?php
/**
 * Off-page analysis with a quality filter.
 *
 * What counts is not the raw backlink number (full of directory, comment,
 * profile, sitewide and spam links) but:
 *   - quality reportages: sponsored articles on Iranian media, followed, with a
 *     targeted anchor (a real keyword of the market, not "اینجا" or a URL);
 *   - quality referring domains: followed links from non-spam sources with a
 *     minimum Authority Score;
 * both for the whole domain and for its top traffic / money pages.
 *
 * Sources: SEO Signal's reportage assistant (Iranian reportages a domain has
 * bought, with media and price — works today) and Semrush backlinks (links,
 * anchors, referring domains — when the account is connected).
 *
 * @package Ostorlab
 */

defined( 'ABSPATH' ) || exit;

/**
 * First list of records inside an answer whose shape is not fixed.
 */
function ost_find_rows( $data ) {
	if ( is_array( $data ) && array_is_list( $data ) && isset( $data[0] ) && is_array( $data[0] ) ) {
		return $data;
	}
	foreach ( (array) $data as $v ) {
		if ( is_array( $v ) && array_is_list( $v ) && isset( $v[0] ) && is_array( $v[0] ) ) {
			return $v;
		}
	}
	return array();
}

/**
 * First matching field of a record.
 */
function ost_field( $row, $names, $default = '' ) {
	foreach ( $names as $n ) {
		if ( isset( $row[ $n ] ) && '' !== $row[ $n ] ) {
			return $row[ $n ];
		}
	}
	return $default;
}

/**
 * Anchor type: brand | url | generic | empty | targeted | other.
 *
 * @param string   $anchor  Anchor text.
 * @param string[] $brand   Brand terms of the domain.
 * @param array    $tokens  Market tokens (normalized words of the seeds / money keywords) as keys.
 */
function ost_anchor_type( $anchor, $brand, $tokens ) {
	$a = ost_norm( $anchor );
	if ( '' === $a ) {
		return 'empty';
	}
	if ( preg_match( '#^(https?://|www\.)|\.(ir|com|net|org|co)(/|$)#i', $a ) ) {
		return 'url';
	}
	static $generic = null;
	if ( null === $generic ) {
		$generic = array_flip( array_map( 'ost_norm', ost_list( ost_setting( 'generic_anchors' ) ) ) );
	}
	if ( isset( $generic[ $a ] ) ) {
		return 'generic';
	}
	foreach ( (array) $brand as $b ) {
		$b = ost_norm( $b );
		if ( '' !== $b && ( $a === $b || false !== mb_strpos( $a, $b ) && mb_strlen( $a ) <= mb_strlen( $b ) + 6 ) ) {
			return 'brand';
		}
	}
	$hits = 0;
	foreach ( explode( ' ', $a ) as $w ) {
		if ( mb_strlen( $w ) > 1 && isset( $tokens[ $w ] ) ) {
			$hits++;
		}
	}
	return $hits >= 1 ? 'targeted' : 'other';
}

/**
 * Persian labels of anchor types.
 */
function ost_anchor_labels() {
	return array( 'targeted' => 'هدفمند (کلمه کلیدی)', 'brand' => 'برند', 'url' => 'آدرس سایت', 'generic' => 'عمومی (اینجا، کلیک کنید)', 'empty' => 'خالی / تصویر', 'other' => 'سایر' );
}

/**
 * Market tokens for anchor matching.
 */
function ost_market_tokens( $market, $seeds ) {
	$stop   = array_flip( array( 'از', 'به', 'در', 'با', 'و', 'برای', 'که', 'را', 'این', 'آن', 'های', 'ها', 'یک', 'تا', 'بر', 'هم', 'خرید', 'قیمت' ) );
	$tokens = array();
	$words  = array_merge( (array) $seeds, array_column( array_slice( (array) ( $market['money'] ?? array() ), 0, 40 ), 'w' ) );
	foreach ( $words as $w ) {
		foreach ( explode( ' ', ost_norm( $w ) ) as $t ) {
			if ( mb_strlen( $t ) > 1 && ! isset( $stop[ $t ] ) ) {
				$tokens[ $t ] = true;
			}
		}
	}
	return $tokens;
}

/**
 * Is a source URL a spam / low-value placement (forum, comment, profile, free blog …)?
 */
function ost_is_spam_source( $url ) {
	static $patterns = null;
	if ( null === $patterns ) {
		$patterns = array_map( 'strtolower', ost_list( ost_setting( 'spam_patterns' ) ) );
	}
	$u = strtolower( (string) $url );
	foreach ( $patterns as $p ) {
		if ( '' !== $p && false !== strpos( $u, $p ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Is a host Iranian (TLD) or does a title contain Persian text?
 */
function ost_is_iranian( $host, $title = '', $country = '' ) {
	static $tlds = null;
	if ( null === $tlds ) {
		$tlds = ost_list( ost_setting( 'iran_tlds' ) );
	}
	if ( 'ir' === strtolower( (string) $country ) ) {
		return true;
	}
	foreach ( $tlds as $t ) {
		if ( substr( strtolower( $host ), -strlen( $t ) ) === strtolower( $t ) ) {
			return true;
		}
	}
	return (bool) preg_match( '/[\x{0600}-\x{06FF}]{3,}/u', (string) $title );
}

/**
 * Does a source URL look like an article (where reportages live)?
 */
function ost_is_article_url( $url ) {
	$path = trim( rawurldecode( ost_path( $url ) ), '/' );
	if ( '' === $path ) {
		return false;
	}
	$last = basename( $path );
	return (bool) ( preg_match( '/[-_]/', $last ) && mb_strlen( $last ) >= 12 || preg_match( '#(news|blog|article|mag|post|maghale|khabar|report|content)/#i', $path ) || preg_match( '#/\d{3,}(/|$)#', '/' . $path ) );
}

/**
 * Jalali "1404/02/18" → months ago (approximate).
 */
function ost_jalali_age_months( $jdate ) {
	if ( ! preg_match( '#(1[34]\d\d)[/\-](\d{1,2})#', ost_en_digits( (string) $jdate ), $m ) ) {
		return null;
	}
	list( $y, $mo ) = ost_jalali( (int) gmdate( 'Y' ), (int) gmdate( 'n' ), (int) gmdate( 'j' ) );
	return max( 0, ( $y - (int) $m[1] ) * 12 + ( $mo - (int) $m[2] ) );
}

/**
 * Reportage profile from SEO Signal's buyer analysis.
 *
 * @param array $raw {reportages, anchors, pages} raw answers (or error strings).
 */
function ost_reportage_profile( $raw, $brand, $tokens ) {
	$list = ost_find_rows( $raw['reportages'] ?? array() );
	$out  = array(
		'available' => ! empty( $raw['reportages'] ) && ! isset( $raw['error'] ),
		'error'     => (string) ( $raw['error'] ?? '' ),
		'total'     => 0, 'follow' => 0, 'last12' => 0, 'media' => 0, 'price_avg' => 0, 'spend' => 0, 'spend12' => 0,
		'targeted_ratio' => null, 'quality' => 0, 'anchors' => array(), 'pages' => array(), 'recent' => array(), 'top_media' => array(),
	);
	$media  = array();
	$prices = array();
	foreach ( $list as $r ) {
		$host   = ost_domain( (string) ost_field( $r, array( 'FromHost', 'Domain', 'Host' ) ) ?: (string) ost_field( $r, array( 'Url', 'URL' ) ) );
		$follow = (int) ost_field( $r, array( 'Follow' ), 0 );
		$price  = (float) ost_field( $r, array( 'Price' ), 0 );
		$age    = ost_jalali_age_months( ost_field( $r, array( 'PublishDate', 'Date' ) ) );
		if ( ost_is_spam_source( (string) ost_field( $r, array( 'Url', 'URL' ) ) ) ) {
			continue;
		}
		$out['total']++;
		if ( $follow > 0 ) {
			$out['follow']++;
		}
		if ( null !== $age && $age <= 12 ) {
			$out['last12']++;
			$out['spend12'] += $price;
		}
		$out['spend'] += $price;
		if ( $price > 0 ) {
			$prices[] = $price;
		}
		$media[ $host ] = ( $media[ $host ] ?? 0 ) + 1;
		if ( count( $out['recent'] ) < 6 ) {
			$out['recent'][] = array( 'host' => $host, 'title' => (string) ost_field( $r, array( 'Title' ) ), 'date' => (string) ost_field( $r, array( 'PublishDate' ) ), 'price' => $price );
		}
	}
	arsort( $media );
	$out['media']     = count( $media );
	$out['top_media'] = array_slice( $media, 0, 8, true );
	$out['price_avg'] = $prices ? ost_median( $prices ) : 0;
	// Anchors of the reportages: share of targeted ones.
	$anchors = array();
	foreach ( ost_find_rows( $raw['anchors'] ?? array() ) as $a ) {
		$text = (string) ost_field( $a, array( 'AnchorText', 'Anchor', 'Text', 'Word' ) );
		$n    = max( 1, (int) ost_field( $a, array( 'All', 'Count', 'LinkCount', 'Follow' ), 1 ) );
		$t    = ost_anchor_type( $text, $brand, $tokens );
		$anchors[ $t ] = ( $anchors[ $t ] ?? 0 ) + $n;
	}
	$out['anchors'] = $anchors;
	$an_sum         = array_sum( $anchors );
	if ( $an_sum ) {
		$out['targeted_ratio'] = ( $anchors['targeted'] ?? 0 ) / $an_sum;
	}
	// Quality reportages: followed, not spam, with a targeted anchor (ratio from the anchor report).
	$ratio          = null === $out['targeted_ratio'] ? 1 : max( 0.15, $out['targeted_ratio'] + 0.5 * ( ( $anchors['other'] ?? 0 ) / max( 1, $an_sum ) ) );
	$out['quality'] = (int) round( $out['follow'] * min( 1, $ratio ) );
	// Pages that receive reportage links (money pages of the domain).
	foreach ( ost_find_rows( $raw['pages'] ?? array() ) as $pg ) {
		$url = (string) ost_field( $pg, array( 'Url', 'URL', 'Page', 'ToUrl' ) );
		if ( '' !== $url ) {
			$out['pages'][] = array( 'url' => $url, 'n' => (int) ost_field( $pg, array( 'All', 'Count', 'Follow', 'LinkCount' ), 0 ) );
		}
	}
	usort( $out['pages'], function ( $a, $b ) {
		return $b['n'] <=> $a['n'];
	} );
	$out['pages'] = array_slice( $out['pages'], 0, 6 );
	return $out;
}

/**
 * Backlink profile from Semrush with the quality filter.
 *
 * @param array $sr ost_semrush_domain() result.
 */
function ost_backlink_profile( $sr, $brand, $tokens ) {
	$min = (int) ost_setting( 'semrush_min_ascore', 10 );
	$ov  = (array) ( $sr['overview'] ?? array() );
	$cls = function ( $links ) use ( $brand, $tokens, $min ) {
		$r = array( 'sample' => 0, 'quality' => 0, 'reportage' => 0, 'spam' => 0, 'nofollow' => 0, 'foreign' => 0, 'domains' => array(), 'q_domains' => array(), 'anchors' => array(), 'examples' => array() );
		foreach ( (array) $links as $l ) {
			$src  = (string) ( $l['source_url'] ?? '' );
			$host = ost_domain( $src );
			if ( '' === $host ) {
				continue;
			}
			$r['sample']++;
			$r['domains'][ $host ] = true;
			$nof   = in_array( strtolower( (string) ( $l['nofollow'] ?? '' ) ), array( 'true', '1' ), true );
			$site  = in_array( strtolower( (string) ( $l['sitewide'] ?? '' ) ), array( 'true', '1' ), true );
			$spam  = $site || ost_is_spam_source( $src ) || in_array( strtolower( (string) ( $l['form'] ?? '' ) ), array( 'true', '1' ), true ) || in_array( strtolower( (string) ( $l['frame'] ?? '' ) ), array( 'true', '1' ), true ) || (int) ( $l['external_num'] ?? 0 ) > 80;
			$iran  = ost_is_iranian( $host, (string) ( $l['source_title'] ?? '' ) );
			$type  = ost_anchor_type( (string) ( $l['anchor'] ?? '' ), $brand, $tokens );
			$score = (int) ( $l['page_ascore'] ?? 0 );
			$r['anchors'][ $type ] = ( $r['anchors'][ $type ] ?? 0 ) + 1;
			if ( $nof ) {
				$r['nofollow']++;
			}
			if ( $spam || $score < $min && $score > 0 ) {
				$r['spam']++;
				continue;
			}
			if ( ! $iran ) {
				$r['foreign']++;
			}
			if ( $nof ) {
				continue;
			}
			$r['quality']++;
			$r['q_domains'][ $host ] = true;
			if ( $iran && 'targeted' === $type && ost_is_article_url( $src ) && (int) ( $l['external_num'] ?? 0 ) <= 20 ) {
				$r['reportage']++;
				if ( count( $r['examples'] ) < 6 ) {
					$r['examples'][] = array( 'from' => $src, 'anchor' => (string) $l['anchor'], 'to' => (string) ( $l['target_url'] ?? '' ), 'score' => $score );
				}
			}
		}
		$r['domains']   = count( $r['domains'] );
		$r['q_domains'] = count( $r['q_domains'] );
		return $r;
	};
	$main  = $cls( $sr['links'] ?? array() );
	$total = (int) ( $ov['total'] ?? 0 );
	$refd  = (int) ( $ov['domains_num'] ?? 0 );
	$scale = $main['sample'] > 0 && $total > $main['sample'] ? $total / $main['sample'] : 1;
	// Referring domains with authority and country (quality share of domains).
	$q_ref = 0;
	$ir    = 0;
	foreach ( (array) ( $sr['refdomains'] ?? array() ) as $d ) {
		$score = (int) ( $d['domain_ascore'] ?? 0 );
		$host  = (string) ( $d['domain'] ?? '' );
		if ( ost_is_iranian( $host, '', (string) ( $d['country'] ?? '' ) ) ) {
			$ir++;
		}
		if ( $score >= $min && ! ost_is_spam_source( $host ) ) {
			$q_ref++;
		}
	}
	$ref_sample = count( (array) ( $sr['refdomains'] ?? array() ) );
	$q_ref_est  = $ref_sample ? (int) round( $refd * $q_ref / $ref_sample ) : $main['q_domains'];
	$pages      = array();
	foreach ( (array) ( $sr['top_pages'] ?? array() ) as $tp ) {
		$c       = $cls( $tp['links'] );
		$pages[] = array(
			'url'       => $tp['url'],
			'backlinks' => (int) ( $tp['overview']['total'] ?? $c['sample'] ),
			'domains'   => (int) ( $tp['overview']['domains_num'] ?? $c['domains'] ),
			'quality'   => $c['quality'],
			'reportage' => $c['reportage'],
		);
	}
	return array(
		'available'        => ! empty( $ov ),
		'ascore'           => (int) ( $ov['ascore'] ?? 0 ),
		'backlinks'        => $total,
		'refdomains'       => $refd,
		'follow'           => (int) ( $ov['follows_num'] ?? 0 ),
		'sample'           => $main['sample'],
		'quality_links'    => (int) round( $main['quality'] * $scale ),
		'quality_refdomains' => $q_ref_est,
		'reportages'       => (int) round( $main['reportage'] * $scale ),
		'spam_share'       => $main['sample'] ? $main['spam'] / $main['sample'] : 0,
		'iran_ref_share'   => $ref_sample ? $ir / $ref_sample : 0,
		'anchors'          => $main['anchors'],
		'examples'         => $main['examples'],
		'pages'            => $pages,
		'errors'           => (array) ( $sr['errors'] ?? array() ),
	);
}

/**
 * Combined off-page profile of a domain (reportages + backlinks).
 */
function ost_offpage_profile( $reportage_raw, $semrush_raw, $brand, $tokens ) {
	$rep = ost_reportage_profile( (array) $reportage_raw, $brand, $tokens );
	$bl  = $semrush_raw ? ost_backlink_profile( (array) $semrush_raw, $brand, $tokens ) : array( 'available' => false );
	$q   = max( (int) $rep['quality'], (int) ( $bl['reportages'] ?? 0 ) );
	return array(
		'reportage'         => $rep,
		'backlinks'         => $bl,
		'quality_reportages' => $q,
		'quality_refdomains' => (int) ( $bl['quality_refdomains'] ?? 0 ) ?: (int) $rep['media'],
	);
}
