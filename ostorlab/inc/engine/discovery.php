<?php
/**
 * Business discovery: before any keyword is analysed, Ostorlab learns the business.
 *
 * The AI reads the client's and two competitors' home pages (title, headings, menu)
 * with the inputs and drafts a business profile — what it sells, what it does NOT
 * sell, audience, price tier, region — plus a few questions specific to this niche,
 * each with a suggested answer. The fixed core questions are always asked. The SEO
 * manager confirms or corrects the answers; the analysis waits for it. The confirmed
 * profile then drives the keyword review (off-niche words are removed), the channels,
 * the opportunities and every text.
 *
 * @package Ostorlab
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stored discovery of a proposal.
 */
function ost_discovery( $id ) {
	$d = get_post_meta( $id, '_ost_discovery', true );
	$d = is_array( $d ) ? $d : array();
	return array_replace( array(
		'status'    => '',      // '' | draft | confirmed.
		'source'    => '',      // ai | rules.
		'profile'   => array(), // summary, sells[], not_sells[], audience, price_tier, geo.
		'questions' => array(), // [{id, q, type, options[], suggested, why, fixed}].
		'answers'   => array(), // id => string | string[].
		'pages'     => array(), // domain => short page summary (what the AI read).
		'sig'       => '',
		'at'        => 0,
		'confirmed' => 0,
	), $d );
}

function ost_discovery_save( $id, $changes ) {
	update_post_meta( $id, '_ost_discovery', array_replace( ost_discovery( $id ), $changes ) );
}

/**
 * What the discovery depends on: a new site or business type asks again.
 */
function ost_discovery_sig( $p ) {
	return md5( $p['inputs']['domain'] . '|' . $p['inputs']['business_type'] . '|' . $p['inputs']['client_name'] );
}

function ost_discovery_confirmed( $p ) {
	$d = ost_discovery( $p['id'] );
	return 'confirmed' === $d['status'] && $d['sig'] === ost_discovery_sig( $p );
}

/**
 * The core questions, asked for every business.
 */
function ost_discovery_core() {
	return array(
		array( 'id' => 'sells', 'q' => 'محصولات یا خدماتی که واقعاً می‌فروشید چیست؟ (هر خط یک مورد)', 'type' => 'text', 'why' => 'فقط کلمات همین محصولات تحلیل می‌شوند.' ),
		array( 'id' => 'not_sells', 'q' => 'چه محصولات یا حوزه‌هایی را کار نمی‌کنید؟ (هر خط یک مورد؛ کلمات مربوطشان کنار گذاشته می‌شود)', 'type' => 'text', 'why' => 'مثلاً برای فروشگاه بدلیجات: «طلا»، «دستبند پلیسی».' ),
		array( 'id' => 'model', 'q' => 'مدل فروش شما چیست؟', 'type' => 'choice', 'options' => array( 'خرده‌فروشی آنلاین', 'عمده‌فروشی', 'خرده و عمده', 'فروشگاه حضوری و آنلاین', 'ارائه خدمات' ) ),
		array( 'id' => 'geo', 'q' => 'محدوده فروش یا خدمات‌دهی شما کجاست؟', 'type' => 'choice', 'options' => array( 'سراسر ایران (ارسال)', 'یک شهر یا استان', 'چند شهر مشخص', 'ایران و خارج از کشور' ) ),
		array( 'id' => 'price', 'q' => 'رده قیمتی محصولات شما؟', 'type' => 'choice', 'options' => array( 'اقتصادی', 'متوسط', 'بالا / لوکس', 'متنوع (همه رده‌ها)' ) ),
		array( 'id' => 'focus', 'q' => 'پرسودترین یا مهم‌ترین محصولات برای رشد کدام‌اند؟', 'type' => 'text', 'why' => 'فرصت‌های «سودآور» و نقطه شروع روی همین‌ها متمرکز می‌شود.' ),
		array( 'id' => 'channels', 'q' => 'مشتری‌ها الان بیشتر از کجا می‌آیند؟', 'type' => 'multi', 'options' => array( 'اینستاگرام', 'گوگل', 'حضوری و معرفی', 'مارکت‌پلیس‌ها (دیجی‌کالا، باسلام…)', 'تبلیغات', 'پیام‌رسان‌ها' ) ),
	);
}

/**
 * Short, readable summary of a home page for the AI: title, description, headings, menu.
 */
function ost_discovery_page( $html ) {
	$clean = function ( $s ) {
		return trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( (string) $s ), ENT_QUOTES, 'UTF-8' ) ) );
	};
	$html  = preg_replace( '#<(script|style|noscript|svg)\b.*?</\1>#is', ' ', (string) $html );
	$title = preg_match( '#<title[^>]*>(.*?)</title>#is', $html, $m ) ? $clean( $m[1] ) : '';
	$desc  = preg_match( '#<meta[^>]+name=["\']description["\'][^>]*content=["\'](.*?)["\']#is', $html, $m ) ? $clean( $m[1] ) : '';
	preg_match_all( '#<h[1-3][^>]*>(.*?)</h[1-3]>#is', $html, $h );
	preg_match_all( '#<a\b[^>]*>(.*?)</a>#is', $html, $a );
	$pick = function ( $list, $max, $len ) use ( $clean ) {
		$out = array();
		foreach ( $list as $t ) {
			$t = $clean( $t );
			$n = mb_strlen( $t );
			if ( $n >= 2 && $n <= $len && ! isset( $out[ $t ] ) && ! preg_match( '/^(ورود|ثبت ?نام|سبد خرید|حساب کاربری|تماس با ما|درباره ما|بلاگ|وبلاگ|خانه|صفحه اصلی|قوانین|سوالات متداول|login|cart|home)$/iu', $t ) ) {
				$out[ $t ] = true;
			}
			if ( count( $out ) >= $max ) {
				break;
			}
		}
		return array_keys( $out );
	};
	return array(
		'title'    => mb_substr( $title, 0, 160 ),
		'desc'     => mb_substr( $desc, 0, 300 ),
		'headings' => $pick( $h[1], 20, 80 ),
		'menu'     => $pick( $a[1], 60, 40 ),
	);
}

/**
 * Read the client's and up to two competitors' home pages (in parallel where possible).
 */
function ost_discovery_read( $p ) {
	$doms = array();
	foreach ( ost_domains( $p ) as $d => $role ) {
		if ( 'client' === $role || count( $doms ) < 3 ) {
			$doms[ $d ] = $role;
		}
	}
	$urls = array();
	foreach ( array_keys( $doms ) as $d ) {
		$urls[ $d ] = ost_home_url( $d );
	}
	$got = ost_site_get_many( array_values( $urls ), 12 );
	$out = array();
	foreach ( $urls as $d => $u ) {
		$r = $got[ $u ] ?? null;
		if ( $r && empty( $r['error'] ) && (int) ( $r['code'] ?? 0 ) < 400 && ! empty( $r['body'] ) ) {
			$out[ $d ] = array_merge( array( 'role' => $doms[ $d ] ), ost_discovery_page( $r['body'] ) );
		}
	}
	return $out;
}

/**
 * Build the draft: profile + questions (AI when connected, else the core questions only).
 *
 * @return string|WP_Error Message.
 */
function ost_discovery_build( $p ) {
	$in    = $p['inputs'];
	$pages = array();
	try {
		$pages = ost_discovery_read( $p );
	} catch ( Throwable $e ) {
		$pages = array();
	}
	$core    = ost_discovery_core();
	$profile = array( 'summary' => '', 'sells' => array(), 'not_sells' => array(), 'audience' => '', 'price_tier' => '', 'geo' => '' );
	$extra   = array();
	$suggest = array();
	$source  = 'rules';
	if ( ost_ai_connected() ) {
		$str    = array( 'type' => 'string' );
		$list   = array( 'type' => 'array', 'items' => $str );
		$schema = array(
			'type'       => 'object',
			'properties' => array(
				'summary'    => $str,
				'sells'      => $list,
				'not_sells'  => $list,
				'audience'   => $str,
				'price_tier' => $str,
				'geo'        => $str,
				'suggested'  => array( 'type' => 'object', 'properties' => array( 'model' => $str, 'geo' => $str, 'price' => $str, 'focus' => $str, 'channels' => $list ), 'required' => array( 'model', 'geo', 'price', 'focus', 'channels' ) ),
				'questions'  => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array( 'q' => $str, 'type' => $str, 'options' => $list, 'suggested' => $list, 'why' => $str ),
						'required'   => array( 'q', 'type', 'options', 'suggested', 'why' ),
					),
				),
			),
			'required'   => array( 'summary', 'sells', 'not_sells', 'audience', 'price_tier', 'geo', 'suggested', 'questions' ),
		);
		$system = implode( "\n", array(
			'تو تحلیلگر ارشد بازار و سئو برای کسب‌وکارهای ایرانی هستی. قبل از تحلیل کلمات کلیدی باید کسب‌وکار را دقیق بشناسیم تا کلمات بی‌ربط (محصولاتی که این کسب‌وکار نمی‌فروشد) وارد تحلیل نشوند.',
			'از روی ورودی‌ها و خلاصه صفحه اصلی سایت کارفرما و رقبا (عنوان، توضیح، تیترها، منو):',
			'- summary: دو جمله ساده: این کسب‌وکار دقیقاً چه می‌فروشد و به چه کسی.',
			'- sells: فهرست دسته‌های محصول/خدمت واقعی (۳ تا ۱۰ مورد کوتاه، از روی منو و تیترها).',
			'- not_sells: حوزه‌هایی که با کلمات اصلی هم‌لفظ یا نزدیک‌اند ولی این کسب‌وکار کار نمی‌کند و ممکن است ابزار کلمات اشتباهی بیاورد (مثلاً برای «دستبند» بدلیجات: «دستبند پلیسی»، «دستبند طلا» اگر طلا نمی‌فروشد؛ برای «نیمست»: «vpn»). ۳ تا ۸ مورد کوتاه.',
			'- suggested: جواب پیشنهادی تو برای سؤال‌های ثابت: model (یکی از: خرده‌فروشی آنلاین، عمده‌فروشی، خرده و عمده، فروشگاه حضوری و آنلاین، ارائه خدمات)، geo (یکی از: سراسر ایران (ارسال)، یک شهر یا استان، چند شهر مشخص، ایران و خارج از کشور)، price (یکی از: اقتصادی، متوسط، بالا / لوکس، متنوع (همه رده‌ها))، focus (محصولات احتمالاً پرسود، یک خط)، channels (از: اینستاگرام، گوگل، حضوری و معرفی، مارکت‌پلیس‌ها (دیجی‌کالا، باسلام…)، تبلیغات، پیام‌رسان‌ها).',
			'- questions: ۳ تا ۵ سؤال مخصوص همین حوزه که جوابشان تحلیل کلمات و پیشنهاد را دقیق‌تر می‌کند و از روی سایت معلوم نیست (مثلاً جنس محصول، جنسیت مخاطب، تولیدکننده یا واردکننده، خدمات پس از فروش، شهرهای دارای شعبه). type یکی از choice (یک جواب)، multi (چند جواب)، text. برای choice و multi سه تا شش گزینه کوتاه. suggested: جواب محتمل تو (برای text یک مورد). why: یک جمله که چرا می‌پرسیم.',
			'- اگر صفحه‌ای خوانده نشده، از روی ورودی‌ها حدس بزن و در summary بگو که حدسی است.',
			'- فارسی ساده و کوتاه.',
		) );
		$user = "ورودی‌ها:\n" . wp_json_encode( array(
			'business' => $in['client_name'],
			'type'     => ost_business_types()[ $in['business_type'] ] ?? '',
			'industry' => $in['industry'],
			'site'     => $in['domain'],
			'keywords' => $in['keywords'],
			'competitors' => $in['competitors'],
			'goals'    => $in['goals'],
			'notes'    => $in['notes'],
		), JSON_UNESCAPED_UNICODE ) . "\n\nصفحات خوانده‌شده:\n" . wp_json_encode( $pages, JSON_UNESCAPED_UNICODE );
		$res = ost_ai_request( $system, $user, $schema, 'analysis', 4000 );
		if ( ! is_wp_error( $res ) ) {
			$source = 'ai';
			$lines  = function ( $v ) {
				return array_values( array_filter( array_map( 'sanitize_text_field', array_map( 'strval', (array) $v ) ) ) );
			};
			$profile = array(
				'summary'    => sanitize_textarea_field( (string) ( $res['summary'] ?? '' ) ),
				'sells'      => $lines( $res['sells'] ?? array() ),
				'not_sells'  => $lines( $res['not_sells'] ?? array() ),
				'audience'   => sanitize_text_field( (string) ( $res['audience'] ?? '' ) ),
				'price_tier' => sanitize_text_field( (string) ( $res['price_tier'] ?? '' ) ),
				'geo'        => sanitize_text_field( (string) ( $res['geo'] ?? '' ) ),
			);
			$suggest = (array) ( $res['suggested'] ?? array() );
			foreach ( array_slice( array_filter( (array) ( $res['questions'] ?? array() ), 'is_array' ), 0, 5 ) as $i => $q ) {
				$type = in_array( $q['type'] ?? '', array( 'choice', 'multi', 'text' ), true ) ? $q['type'] : 'text';
				$opts = $lines( $q['options'] ?? array() );
				if ( 'text' !== $type && count( $opts ) < 2 ) {
					$type = 'text';
				}
				$extra[] = array(
					'id'        => 'x' . ( $i + 1 ),
					'q'         => sanitize_text_field( (string) ( $q['q'] ?? '' ) ),
					'type'      => $type,
					'options'   => 'text' === $type ? array() : array_slice( $opts, 0, 6 ),
					'suggested' => 'multi' === $type ? $lines( $q['suggested'] ?? array() ) : (string) ( $lines( $q['suggested'] ?? array() )[0] ?? '' ),
					'why'       => sanitize_text_field( (string) ( $q['why'] ?? '' ) ),
				);
			}
		} else {
			$error = $res->get_error_message();
		}
	}
	$questions = array();
	foreach ( $core as $q ) {
		$q['fixed'] = true;
		switch ( $q['id'] ) {
			case 'sells':
				$q['suggested'] = implode( "\n", $profile['sells'] ?: $in['keywords'] );
				break;
			case 'not_sells':
				$q['suggested'] = implode( "\n", $profile['not_sells'] );
				break;
			case 'channels':
				$q['suggested'] = array_values( array_intersect( $q['options'], array_map( 'strval', (array) ( $suggest['channels'] ?? array() ) ) ) );
				break;
			default:
				$q['suggested'] = (string) ( $suggest[ $q['id'] ] ?? '' ); // A choice outside the options shows as «other».
		}
		$questions[] = $q;
	}
	$questions = array_values( array_filter( array_merge( $questions, $extra ), function ( $q ) {
		return '' !== $q['q'];
	} ) );
	$old = ost_discovery( $p['id'] );
	ost_discovery_save( $p['id'], array(
		'status'    => 'draft',
		'source'    => $source,
		'profile'   => $profile,
		'questions' => $questions,
		'answers'   => array(),
		'pages'     => $pages,
		'sig'       => ost_discovery_sig( $p ),
		'at'        => time(),
		'confirmed' => 0,
		'prev'      => 'confirmed' === $old['status'] ? $old['answers'] : array(),
	) );
	return 'ai' === $source
		? sprintf( 'پروفایل کسب‌وکار و %s سؤال آماده شد (%s سایت خوانده شد)؛ منتظر تأیید شما', ost_num( count( $questions ) ), ost_num( count( $pages ) ) )
		: 'سؤال‌های اصلی آماده شد' . ( isset( $error ) ? ' (هوش مصنوعی جواب نداد: ' . $error . ')' : ' (بدون هوش مصنوعی)' ) . '؛ منتظر تأیید شما';
}

/**
 * Answer of a question: saved answer, else the previous confirmed one, else the suggestion.
 */
function ost_discovery_answer( $d, $q ) {
	if ( array_key_exists( $q['id'], $d['answers'] ) ) {
		return $d['answers'][ $q['id'] ];
	}
	if ( ! empty( $q['fixed'] ) && isset( $d['prev'][ $q['id'] ] ) ) {
		return $d['prev'][ $q['id'] ];
	}
	return $q['suggested'] ?? ( 'multi' === $q['type'] ? array() : '' );
}

/**
 * Questions and answers for prompts.
 *
 * @return array [{q, a}]
 */
function ost_discovery_qa( $id ) {
	$d = ost_discovery( $id );
	if ( 'confirmed' !== $d['status'] ) {
		return null;
	}
	$out = array();
	foreach ( $d['questions'] as $q ) {
		$a = ost_discovery_answer( $d, $q );
		$a = is_array( $a ) ? implode( '، ', $a ) : trim( (string) $a );
		if ( '' !== $a ) {
			$out[] = array( 'q' => $q['q'], 'a' => $a );
		}
	}
	return $out;
}

/**
 * Profile for prompts (summary, what it sells / does not sell).
 */
function ost_discovery_profile_brief( $id ) {
	$d = ost_discovery( $id );
	if ( 'confirmed' !== $d['status'] ) {
		return null;
	}
	$pr = $d['profile'];
	return array_filter( array(
		'summary'   => $pr['summary'] ?? '',
		'sells'     => ost_discovery_lines( ost_discovery_answer( $d, array( 'id' => 'sells', 'type' => 'text', 'fixed' => true ) ) ),
		'not_sells' => ost_discovery_excluded( $id ),
		'audience'  => $pr['audience'] ?? '',
	) );
}

/**
 * Text answer → list of lines (also split on Persian / Latin commas).
 */
function ost_discovery_lines( $text ) {
	$text = is_array( $text ) ? implode( "\n", $text ) : (string) $text;
	return array_values( array_filter( array_map( 'trim', preg_split( '/[\n\r،,؛;]+/u', $text ) ) ) );
}

/**
 * Areas the business does not work in (confirmed answer), used to remove keywords.
 */
function ost_discovery_excluded( $id ) {
	$d = ost_discovery( $id );
	if ( 'confirmed' !== $d['status'] ) {
		return array();
	}
	foreach ( $d['questions'] as $q ) {
		if ( 'not_sells' === $q['id'] ) {
			return ost_discovery_lines( ost_discovery_answer( $d, $q ) );
		}
	}
	return array();
}
