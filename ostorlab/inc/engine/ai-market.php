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
 * 1. Keyword review.
 *
 * @return array|WP_Error {drop: string[] (normalized), keep: string[], important: [{w, why, priority}], note}
 */
function ost_ai_keyword_review( $p, $raw ) {
	$cands = ost_keyword_candidates( $raw );
	if ( ! $cands ) {
		return array( 'drop' => array(), 'keep' => array(), 'important' => array(), 'note' => '' );
	}
	$lines = array();
	foreach ( $cands as $c ) {
		$lines[] = $c['w'] . ' | ' . $c['v'] . ' | ' . ( $c['sure'] ? 'مرتبط با کلمه اصلی' : 'نامطمئن' );
	}
	$str    = array( 'type' => 'array', 'items' => array( 'type' => 'string' ) );
	$schema = array(
		'type'       => 'object',
		'properties' => array(
			'irrelevant' => $str,
			'rescue'     => $str,
			'important'  => array( 'type' => 'array', 'items' => array( 'type' => 'object', 'properties' => array( 'w' => array( 'type' => 'string' ), 'why' => array( 'type' => 'string' ), 'priority' => array( 'type' => 'integer' ) ), 'required' => array( 'w', 'why', 'priority' ) ) ),
			'note'       => array( 'type' => 'string' ),
		),
		'required'   => array( 'irrelevant', 'rescue', 'important', 'note' ),
	);
	$system = implode( "\n", array(
		'تو تحلیلگر ارشد کلمات کلیدی برای سئوی کسب‌وکارهای ایرانی هستی.',
		'فهرست کلمات (کلمه | جستجوی ماهانه | وضعیت) از ابزار سئو سیگنال آمده و ممکن است کلمات بی‌ربط داشته باشد (ابزار برای کلمات کم‌جستجو، کلمات صرفاً هم‌لفظ مثل «خرید vpn» برای «خرید نیمست» برمی‌گرداند).',
		'کارها:',
		'1) irrelevant: هر کلمه‌ای که به محصولات/خدمات همین کسب‌وکار ربط ندارد یا مشتری این سایت نیست (حتی اگر «مرتبط با کلمه اصلی» علامت خورده). کلمه را دقیقاً همان‌طور که در فهرست آمده بنویس.',
		'2) rescue: از کلمات «نامطمئن» فقط آن‌هایی که واقعاً به این کسب‌وکار مربوطند (مترادف، نام دیگر محصول).',
		'3) important: ۱۰ تا ۲۰ کلمه کلیدی واقعاً مهم برای فروش این کسب‌وکار، از همین فهرست؛ priority=1 حیاتی، 2 مهم، 3 مفید؛ why یک جمله کوتاه فارسی (چرا این کلمه مشتری می‌آورد).',
		'4) note: دیدگاه تحلیلی تو درباره بازار کلمات این کسب‌وکار در ۲ تا ۳ جمله ساده فارسی: کدام خوشه‌ها ارزشمندند، کدام کم‌ارزش، چه چیزی جای خالی دارد.',
		'فقط از کلمات همین فهرست استفاده کن.',
	) );
	$user = "کسب‌وکار:\n" . wp_json_encode( ost_ai_business( $p ), JSON_UNESCAPED_UNICODE ) . "\n\nفهرست کلمات:\n" . implode( "\n", $lines );
	$res  = ost_ai_request( $system, $user, $schema, 'analysis', 6000 );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$valid = array();
	foreach ( $cands as $c ) {
		$valid[ ost_norm( $c['w'] ) ] = true;
	}
	$pick = function ( $list ) use ( $valid ) {
		return array_values( array_filter( array_map( 'ost_norm', array_map( 'strval', (array) $list ) ), function ( $w ) use ( $valid ) {
			return isset( $valid[ $w ] );
		} ) );
	};
	$important = array();
	foreach ( (array) ( $res['important'] ?? array() ) as $i ) {
		if ( isset( $valid[ ost_norm( (string) ( $i['w'] ?? '' ) ) ] ) ) {
			$important[] = array( 'w' => (string) $i['w'], 'why' => sanitize_text_field( (string) ( $i['why'] ?? '' ) ), 'priority' => (int) ( $i['priority'] ?? 2 ) );
		}
	}
	return array(
		'drop'      => $pick( $res['irrelevant'] ?? array() ),
		'keep'      => $pick( $res['rescue'] ?? array() ),
		'important' => $important,
		'note'      => sanitize_textarea_field( (string) ( $res['note'] ?? '' ) ),
	);
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
