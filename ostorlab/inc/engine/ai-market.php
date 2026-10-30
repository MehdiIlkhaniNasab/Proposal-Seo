<?php
/**
 * AI market intelligence:
 *   1. Keyword review — the AI reads the keyword list with the business context, drops
 *      what is not about this business, rescues related words the relevance rule missed
 *      and picks the keywords that really matter (with its reason).
 *   2. Demand by channel — Google search is measured (SEO Signal); the share of demand
 *      that happens on Instagram, marketplaces, in person … is estimated by the AI for this
 *      industry in Iran (or from defaults per business type), and turned into an estimated
 *      monthly demand per channel.
 *   3. Market opportunities — the AI's own recommendation from different angles (gap,
 *      competition, speed, profit), on top of the scored opportunities of the strategy.
 *
 * @package Ostorlab
 */

defined( 'ABSPATH' ) || exit;

/**
 * Business context for prompts.
 */
function ost_ai_business( $p ) {
	$in = $p['inputs'];
	return array(
		'business' => $in['client_name'],
		'type'     => ost_business_types()[ $in['business_type'] ] ?? '',
		'industry' => $in['industry'],
		'site'     => $in['domain'],
		'goals'    => $in['goals'],
		'seeds'    => $in['keywords'],
		'notes'    => $in['notes'],
	);
}

/**
 * Evidence that a keyword belongs to this market: the client or a competitor ranks for it
 * (SEO Signal), it is in a keyword gap, or the client gets impressions for it (Search Console).
 *
 * @return array{words: array, topics: array}
 */
function ost_kw_evidence( $id ) {
	$words = array();
	foreach ( (array) ost_data( $id, 'seosignal' ) as $d ) {
		foreach ( (array) ( $d['keywords'] ?? array() ) as $k ) {
			$words[ ost_norm( $k['w'] ?? '' ) ] = true;
		}
	}
	foreach ( (array) ost_data( $id, 'gap' ) as $rows ) {
		foreach ( (array) $rows as $k ) {
			$words[ ost_norm( $k['w'] ?? '' ) ] = true;
		}
	}
	foreach ( (array) ( ost_data( $id, 'gsc' )['queries'] ?? array() ) as $q ) {
		$words[ ost_norm( $q['query'] ?? '' ) ] = true;
	}
	unset( $words[''] );
	$topics = array();
	foreach ( array_keys( $words ) as $w ) {
		$topics[ ost_topic_key( $w ) ] = true;
	}
	unset( $topics[''] );
	return array( 'words' => $words, 'topics' => $topics );
}

function ost_kw_has_evidence( $word, $ev ) {
	$k = ost_norm( $word );
	return isset( $ev['words'][ $k ] ) || isset( $ev['topics'][ ost_topic_key( $k ) ] );
}

/**
 * Size of one review request (small requests are never cut off).
 */
const OST_KW_CHUNK = 90;

/**
 * 1. Keyword review, one chunk per runner tick. The AI reads each word with the confirmed
 * business profile and whether the client or a competitor ranks for it, and marks it:
 * off-niche (with the reason), uncertain, or important. A failed chunk is skipped, never
 * the whole review.
 *
 * @return array|WP_Error ['again' => bool, 'msg' => string]
 */
function ost_kw_review_step( $p ) {
	$id    = $p['id'];
	$raw   = ost_data( $id, 'keywords' );
	$rules = ost_kw_rules( $p, false );
	$cands = array_values( array_filter( ost_keyword_candidates( $raw, 450 ), function ( $c ) use ( $rules ) {
		return ! ost_kw_user_excluded( $c['w'], $rules );
	} ) );
	$chunks = array_chunk( $cands, OST_KW_CHUNK );
	$sig    = md5( wp_json_encode( array( array_column( $cands, 'w' ), ost_discovery_profile_brief( $id ), ost_discovery_qa( $id ) ) ) );
	$state  = ost_data( $id, 'aikw' );
	if ( ( $state['sig'] ?? '' ) !== $sig ) {
		$state = array( 'sig' => $sig, 'next' => 0, 'chunks' => count( $chunks ), 'drop' => array(), 'why' => array(), 'keep' => array(), 'unsure' => array(), 'important' => array(), 'note' => '', 'failed' => 0, 'errors' => array() );
	}
	if ( ! $chunks ) {
		ost_set_data( $id, 'aikw', $state );
		return array( 'again' => false, 'msg' => 'کلمه‌ای برای بازبینی نبود' );
	}
	$i = (int) $state['next'];
	if ( $i < count( $chunks ) ) {
		$ev  = ost_kw_evidence( $id );
		$res = ost_kw_review_chunk( $p, $chunks[ $i ], 0 === $i, $ev );
		if ( is_wp_error( $res ) ) {
			$state['failed']++;
			$state['errors'][] = $res->get_error_message();
		} else {
			foreach ( $res['drop'] as $k => $why ) {
				$state['drop'][]    = $k;
				$state['why'][ $k ] = $why;
			}
			$state['keep']      = array_merge( $state['keep'], $res['keep'] );
			$state['unsure']    = array_merge( $state['unsure'], $res['unsure'] );
			$state['important'] = array_merge( $state['important'], $res['important'] );
			if ( '' !== $res['note'] ) {
				$state['note'] = $res['note'];
			}
		}
		$state['next'] = $i + 1;
	}
	$done = $state['next'] >= count( $chunks );
	if ( $done ) {
		usort( $state['important'], function ( $a, $b ) {
			return array( $a['priority'], $b['v'] ?? 0 ) <=> array( $b['priority'], $a['v'] ?? 0 );
		} );
		$seen               = array();
		$state['important'] = array_values( array_filter( $state['important'], function ( $x ) use ( &$seen ) {
			$k = ost_norm( $x['w'] );
			if ( isset( $seen[ $k ] ) ) {
				return false;
			}
			$seen[ $k ] = true;
			return true;
		} ) );
		$state['important'] = array_slice( $state['important'], 0, 20 );
	}
	ost_set_data( $id, 'aikw', $state );
	if ( $state['failed'] >= count( $chunks ) && $done ) {
		return new WP_Error( 'ost_aikw', 'بازبینی کلمات با هوش مصنوعی انجام نشد: ' . end( $state['errors'] ) . ' — قانون‌ها و فهرست «کار نمی‌کنیم» اعمال شد.' );
	}
	$msg = $done
		? sprintf( '%s کلمه بی‌ربط با کسب‌وکار پیدا شد، %s کلمه کلیدی اصلی انتخاب شد%s', ost_num( count( $state['drop'] ) ), ost_num( count( $state['important'] ) ), $state['failed'] ? '؛ ' . ost_num( $state['failed'] ) . ' بخش ناموفق بود' : '' )
		: sprintf( 'بخش %s از %s بررسی شد…', ost_num( $state['next'] ), ost_num( count( $chunks ) ) );
	return array( 'again' => ! $done, 'msg' => $msg );
}

/**
 * Review one chunk of keywords.
 *
 * @return array|WP_Error {drop: norm => why, keep: norm[], unsure: norm[], important: [], note}
 */
function ost_kw_review_chunk( $p, $chunk, $first, $ev ) {
	$lines = array();
	foreach ( $chunk as $c ) {
		$lines[] = $c['w'] . ' | ' . $c['v'] . ' | ' . ( $c['sure'] ? 'هم‌موضوع کلمه اصلی' : 'نامطمئن' ) . ' | ' . ( ost_kw_has_evidence( $c['w'], $ev ) ? 'رقبا/کارفرما رتبه دارند' : 'هیچ سایتی رتبه ندارد' );
	}
	$str    = array( 'type' => 'string' );
	$schema = array(
		'type'       => 'object',
		'properties' => array(
			'irrelevant' => array( 'type' => 'array', 'items' => array( 'type' => 'object', 'properties' => array( 'w' => $str, 'why' => $str ), 'required' => array( 'w', 'why' ) ) ),
			'rescue'     => array( 'type' => 'array', 'items' => $str ),
			'unsure'     => array( 'type' => 'array', 'items' => $str ),
			'important'  => array( 'type' => 'array', 'items' => array( 'type' => 'object', 'properties' => array( 'w' => $str, 'why' => $str, 'priority' => array( 'type' => 'integer' ) ), 'required' => array( 'w', 'why', 'priority' ) ) ),
			'note'       => $str,
		),
		'required'   => array( 'irrelevant', 'rescue', 'unsure', 'important', 'note' ),
	);
	$system = implode( "\n", array(
		'تو تحلیلگر ارشد کلمات کلیدی برای سئوی کسب‌وکارهای ایرانی هستی و دقت برایت از همه چیز مهم‌تر است.',
		'شناخت تأییدشده کسب‌وکار (profile و answers) را مبنا بگیر: چه می‌فروشد، چه نمی‌فروشد، مخاطب، قیمت، منطقه.',
		'فهرست کلمات (کلمه | جستجوی ماهانه | هم‌موضوع بودن با کلمه اصلی | آیا کارفرما یا رقبا برایش رتبه دارند) از ابزار سئو سیگنال آمده و کلمات هم‌لفظ ولی بی‌ربط دارد (مثلاً برای فروشگاه بدلیجات: «دستبند پلیسی»؛ برای «خرید نیمست»: «خرید vpn»).',
		'کارها:',
		'1) irrelevant: هر کلمه‌ای که خریدارش مشتری این کسب‌وکار نیست یا درباره محصولی است که نمی‌فروشد (حتی اگر هم‌موضوع کلمه اصلی است). why: دلیل خیلی کوتاه (حداکثر ۸ کلمه).',
		'2) rescue: از کلمات «نامطمئن» فقط آن‌هایی که واقعاً به محصولات همین کسب‌وکار مربوطند (مترادف، نام دیگر محصول).',
		'3) unsure: کلماتی که نمی‌توانی با اطمینان بگویی مرتبط‌اند یا نه.',
		'4) important: حداکثر ۸ کلمه از همین بخش که واقعاً برای فروش این کسب‌وکار مهم‌اند (نیت خرید، محصول اصلی یا پرسود)؛ priority=1 حیاتی، 2 مهم، 3 مفید؛ why یک جمله کوتاه.',
		$first ? '5) note: دیدگاه تحلیلی تو درباره بازار کلمات این کسب‌وکار در ۲ تا ۳ جمله ساده: کدام خوشه‌ها ارزشمندند، کدام کم‌ارزش، چه چیزی جای خالی دارد.' : '5) note: خالی ("").',
		'کلمات را دقیقاً همان‌طور که در فهرست آمده بنویس و فقط از همین فهرست. خروجی فقط JSON معتبر.',
	) );
	$user = "کسب‌وکار:\n" . wp_json_encode( array_merge( ost_ai_business( $p ), array( 'profile' => ost_discovery_profile_brief( $p['id'] ), 'answers' => ost_discovery_qa( $p['id'] ) ) ), JSON_UNESCAPED_UNICODE ) . "\n\nفهرست کلمات:\n" . implode( "\n", $lines );
	$res  = ost_ai_request( $system, $user, $schema, 'analysis', 4500 );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$valid = array();
	foreach ( $chunk as $c ) {
		$valid[ ost_norm( $c['w'] ) ] = $c;
	}
	$pick = function ( $list ) use ( $valid ) {
		return array_values( array_filter( array_map( 'ost_norm', array_map( 'strval', (array) $list ) ), function ( $w ) use ( $valid ) {
			return isset( $valid[ $w ] );
		} ) );
	};
	$drop = array();
	foreach ( (array) ( $res['irrelevant'] ?? array() ) as $x ) {
		$k = ost_norm( is_array( $x ) ? (string) ( $x['w'] ?? '' ) : (string) $x );
		if ( isset( $valid[ $k ] ) ) {
			$drop[ $k ] = sanitize_text_field( is_array( $x ) ? (string) ( $x['why'] ?? '' ) : '' );
		}
	}
	$important = array();
	foreach ( (array) ( $res['important'] ?? array() ) as $x ) {
		$k = ost_norm( (string) ( $x['w'] ?? '' ) );
		if ( isset( $valid[ $k ] ) && ! isset( $drop[ $k ] ) ) {
			$important[] = array( 'w' => $valid[ $k ]['w'], 'v' => $valid[ $k ]['v'], 'why' => sanitize_text_field( (string) ( $x['why'] ?? '' ) ), 'priority' => max( 1, min( 3, (int) ( $x['priority'] ?? 2 ) ) ) );
		}
	}
	return array(
		'drop'      => $drop,
		'keep'      => $pick( $res['rescue'] ?? array() ),
		'unsure'    => $pick( $res['unsure'] ?? array() ),
		'important' => $important,
		'note'      => $first ? sanitize_textarea_field( (string) ( $res['note'] ?? '' ) ) : '',
	);
}

/**
 * The user's review of the keywords (keywords tab).
 */
function ost_kw_review( $id ) {
	$r = get_post_meta( $id, '_ost_kw_review', true );
	return array_replace( array( 'confirmed' => 0, 'sig' => '', 'topics' => array(), 'words' => array(), 'restore' => array() ), is_array( $r ) ? $r : array() );
}

/**
 * What the keyword review depends on: new seeds or competitors ask again.
 */
function ost_kw_review_sig( $p ) {
	return md5( wp_json_encode( array( $p['inputs']['keywords'], $p['inputs']['competitors'] ) ) );
}

function ost_kw_review_confirmed( $p ) {
	$r = ost_kw_review( $p['id'] );
	return $r['confirmed'] && $r['sig'] === ost_kw_review_sig( $p );
}

/**
 * Exclusions remembered per client site (used by its next proposals).
 */
function ost_client_kw_memory( $domain ) {
	$all = get_option( 'ost_client_kw', array() );
	return array_replace( array( 'topics' => array(), 'terms' => array() ), (array) ( $all[ $domain ] ?? array() ) );
}

/**
 * Every rule that removes or restores keywords for a proposal.
 *
 * @param bool $evidence Also collect the ranking evidence (not needed before the review).
 */
function ost_kw_rules( $p, $evidence = true ) {
	$rv    = ost_kw_review( $p['id'] );
	$mem   = $p['inputs']['domain'] ? ost_client_kw_memory( ost_domain( $p['inputs']['domain'] ) ) : array( 'topics' => array(), 'terms' => array() );
	$terms = array();
	foreach ( array_merge( ost_discovery_excluded( $p['id'] ), (array) $mem['terms'] ) as $t ) {
		$c = ost_compact_text( $t );
		if ( mb_strlen( $c ) > 1 ) {
			$terms[ $c ] = $t;
		}
	}
	return array(
		'terms'    => $terms,
		'topics'   => array_flip( array_merge( (array) $rv['topics'], (array) $mem['topics'] ) ),
		'words'    => array_flip( (array) $rv['words'] ),
		'restore'  => array_flip( (array) $rv['restore'] ),
		'evidence' => $evidence ? ost_kw_evidence( $p['id'] ) : null,
	);
}

/**
 * Removed by the user (an area the business does not work in, an excluded topic or word)?
 *
 * @return string Reason, '' when not excluded.
 */
function ost_kw_user_excluded( $word, $rules ) {
	$k = ost_norm( $word );
	if ( isset( $rules['restore'][ $k ] ) ) {
		return '';
	}
	if ( isset( $rules['words'][ $k ] ) ) {
		return 'حذف‌شده توسط شما';
	}
	if ( isset( $rules['topics'][ ost_topic_key( $k ) ] ) ) {
		return 'موضوع حذف‌شده توسط شما';
	}
	$c = ost_compact_text( $k );
	foreach ( (array) $rules['terms'] as $t => $label ) {
		if ( false !== mb_strpos( $c, $t ) ) {
			return 'حوزه‌ای که کار نمی‌کنید: «' . $label . '»';
		}
	}
	return '';
}

/**
 * Topics of the kept keywords (modifiers removed: «خرید / قیمت / مدل دستبند استیل» = one topic),
 * biggest first, with buyer demand and whether a site ranks for them.
 *
 * @return array topic => {v, n, buy, words[], ev, key}
 */
function ost_kw_topics( $market, $ev ) {
	$key    = array_flip( array_map( 'ost_norm', array_column( (array) ( $market['key'] ?? array() ), 'w' ) ) );
	$topics = array();
	foreach ( (array) ( $market['rows'] ?? array() ) as $r ) {
		$t = ost_topic_key( $r['w'] ) ?: ost_norm( $r['w'] );
		if ( ! isset( $topics[ $t ] ) ) {
			$topics[ $t ] = array( 'v' => 0, 'n' => 0, 'buy' => 0, 'words' => array(), 'ev' => false, 'key' => false );
		}
		$x = &$topics[ $t ];
		$x['v']  += $r['v'];
		$x['n']++;
		$x['buy'] += in_array( $r['i'], array( 'buy', 'compare', 'local' ), true ) ? $r['v'] : 0;
		if ( count( $x['words'] ) < 3 ) {
			$x['words'][] = $r['w'];
		}
		$x['ev']  = $x['ev'] || ost_kw_has_evidence( $r['w'], $ev );
		$x['key'] = $x['key'] || isset( $key[ ost_norm( $r['w'] ) ] );
		unset( $x );
	}
	uasort( $topics, function ( $a, $b ) {
		return $b['v'] <=> $a['v'];
	} );
	return array_slice( $topics, 0, 300, true );
}

/**
 * Channels of demand: key => [label, color].
 */
function ost_channels_def() {
	return array(
		'google'       => array( 'جستجوی گوگل', '#0B4F9C' ),
		'instagram'    => array( 'اینستاگرام و شبکه‌های اجتماعی', '#8B5CF6' ),
		'marketplaces' => array( 'مارکت‌پلیس‌ها (دیجی‌کالا، ترب، باسلام…)', '#D97706' ),
		'offline'      => array( 'حضوری، معرفی و تماس مستقیم', '#0F9F8F' ),
		'other'        => array( 'سایر (تبلیغات، پیام‌رسان‌ها…)', '#94A3B8' ),
	);
}

/**
 * Default shares by business type (when the AI is not available), in percent.
 */
function ost_channel_defaults( $type ) {
	$d = array(
		'shop'    => array( 'google' => 38, 'instagram' => 27, 'marketplaces' => 20, 'offline' => 10, 'other' => 5 ),
		'service' => array( 'google' => 45, 'instagram' => 15, 'marketplaces' => 0, 'offline' => 30, 'other' => 10 ),
		'local'   => array( 'google' => 40, 'instagram' => 22, 'marketplaces' => 0, 'offline' => 33, 'other' => 5 ),
		'content' => array( 'google' => 60, 'instagram' => 25, 'marketplaces' => 0, 'offline' => 0, 'other' => 15 ),
		'saas'    => array( 'google' => 45, 'instagram' => 15, 'marketplaces' => 0, 'offline' => 25, 'other' => 15 ),
	);
	return $d[ $type ] ?? $d['shop'];
}

/**
 * 2. Demand by channel: shares (AI or defaults) and estimated monthly demand per channel,
 * anchored on the measured Google demand.
 */
function ost_channels( $a, $in, $ai ) {
	$shares = ost_channel_defaults( $in['business_type'] ?? 'shop' );
	$why    = array();
	$source = 'default';
	if ( ! empty( $ai['channels'] ) ) {
		$s = array();
		foreach ( array_keys( ost_channels_def() ) as $k ) {
			$s[ $k ] = max( 0, (float) ( $ai['channels'][ $k ]['share'] ?? 0 ) );
			if ( ! empty( $ai['channels'][ $k ]['why'] ) ) {
				$why[ $k ] = (string) $ai['channels'][ $k ]['why'];
			}
		}
		if ( array_sum( $s ) > 0 && $s['google'] > 0 ) {
			$shares = $s;
			$source = 'ai';
		}
	}
	$sum    = max( 1, array_sum( $shares ) );
	$google = (int) ( $a['market']['total'] ?? 0 );
	$total  = $shares['google'] > 0 ? $google / ( $shares['google'] / $sum ) : $google;
	$out    = array();
	foreach ( ost_channels_def() as $k => $def ) {
		if ( $shares[ $k ] <= 0 ) {
			continue;
		}
		$out[ $k ] = array(
			'label'  => $def[0],
			'color'  => $def[1],
			'share'  => $shares[ $k ] / $sum,
			'demand' => (int) round( $total * $shares[ $k ] / $sum ),
			'why'    => $why[ $k ] ?? '',
		);
	}
	return array( 'channels' => $out, 'total' => (int) round( $total ), 'google' => $google, 'source' => $source, 'note' => (string) ( $ai['channels_note'] ?? '' ) );
}

/**
 * 3. Market opportunities: the AI's analysis, or a fallback from the strategy's scored topics.
 *
 * @return array {main: {title, text}, items: [{title, keywords[], angle, why, potential}], source}
 */
function ost_market_opportunities( $a, $ai ) {
	if ( ! empty( $ai['market_opportunities'] ) ) {
		return array(
			'main'   => (array) ( $ai['main_recommendation'] ?? array() ),
			'items'  => array_slice( (array) $ai['market_opportunities'], 0, 5 ),
			'source' => 'ai',
		);
	}
	$angles = array( 'harvest' => 'زودبازده', 'empty' => 'کم‌رقابت', 'gap' => 'شکاف رقبا', 'easy' => 'کم‌رقابت' );
	$items  = array();
	foreach ( array_slice( (array) ( $a['strategy']['picks'] ?? array() ), 0, 4 ) as $o ) {
		$items[] = array(
			'title'     => $o['label'],
			'keywords'  => array_slice( array_column( $o['words'], 'w' ), 0, 4 ),
			'angle'     => $angles[ $o['type'] ] ?? 'زودبازده',
			'why'       => implode( '؛ ', array_slice( $o['reasons'], 0, 2 ) ),
			'potential' => sprintf( 'حدود %s کلیک ماهانه پس از برد، نتیجه در %s تا %s ماه', ost_num( $o['clicks'] ), ost_num( $o['months'][0] ), ost_num( $o['months'][1] ) ),
		);
	}
	return array(
		'main'   => array( 'title' => (string) ( $a['strategy']['name'] ?? '' ), 'text' => (string) ( $a['strategy']['idea'] ?? '' ) ),
		'items'  => $items,
		'source' => 'rules',
	);
}
