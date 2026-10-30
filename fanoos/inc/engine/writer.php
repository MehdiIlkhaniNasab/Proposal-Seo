<?php
/**
 * Proposal texts: written by the AI from the analysis numbers, in plain Persian
 * for an Iranian business owner (no "what is SEO" lecture, no ranking promises),
 * with a template fallback so a proposal is always complete. Every text can be
 * edited in the texts tab before export.
 *
 * @package Fanoos
 */

defined( 'ABSPATH' ) || exit;

/**
 * Text keys with labels (order of the texts tab).
 */
function fns_text_fields() {
	return array(
		'headline'          => array( 'تیتر جلد (یک جمله)', 1 ),
		'executive_summary' => array( 'خلاصه اجرایی (۲ پاراگراف کوتاه)', 6 ),
		'market'            => array( 'بازار جستجو', 3 ),
		'current_state'     => array( 'وضعیت فعلی سایت', 3 ),
		'competitors'       => array( 'رقبا', 3 ),
		'offpage'           => array( 'اعتبار و لینک‌ها', 3 ),
		'technical'         => array( 'وضعیت فنی', 3 ),
		'content'           => array( 'محتوا', 3 ),
		'strengths'         => array( 'نقاط قوت (هر خط یک مورد)', 4 ),
		'weaknesses'        => array( 'نقاط ضعف (هر خط یک مورد)', 4 ),
		'opportunities'     => array( 'فرصت‌ها (هر خط یک مورد)', 4 ),
		'threats'           => array( 'تهدیدها (هر خط یک مورد)', 4 ),
		'commitments'       => array( 'تعهدات ما (هر خط یک مورد)', 5 ),
		'client_needs'      => array( 'آنچه از شما لازم داریم (هر خط یک مورد)', 4 ),
		'next_steps'        => array( 'قدم‌های بعدی (هر خط یک مورد)', 3 ),
	);
}

/**
 * Compact facts for the AI (numbers already computed, no raw data).
 */
function fns_writer_facts( $p, $a ) {
	$in      = $p['inputs'];
	$cd      = $a['client_domain'] ?? '';
	$pkg     = $a['budget']['packages'][ $a['budget']['selected'] ?? 'pro' ] ?? array();
	$profile = function ( $d ) use ( $a ) {
		$pr = $a['profiles'][ $d ] ?? array();
		$of = $a['offpage'][ $d ] ?? array();
		$te = $a['technical'][ $d ] ?? array();
		return array(
			'domain'             => $d,
			'ranking_keywords'   => $pr['keywords'] ?? 0,
			'ranking_pages'      => $pr['ranking_pages'] ?? 0,
			'site_pages'         => $pr['site_pages'] ?? 0,
			'monthly_clicks_est' => $pr['clicks'] ?? 0,
			'buyer_clicks_est'   => $pr['buyer_clicks'] ?? 0,
			'market_share'       => round( 100 * ( $a['sov'][ $d ] ?? 0 ) ),
			'quality_reportages' => $of['quality_reportages'] ?? 0,
			'reportages_last12'  => $of['reportage']['last12'] ?? 0,
			'tech_score'         => $te['score'] ?? null,
			'top_tech_issues'    => array_slice( array_column( (array) ( $te['issues'] ?? array() ), 'label' ), 0, 4 ),
			'top_pages'          => array_slice( array_column( (array) ( $pr['top_pages'] ?? array() ), 'top' ), 0, 3 ),
		);
	};
	$clusters = array();
	foreach ( (array) ( $a['market']['clusters'] ?? array() ) as $c ) {
		$clusters[] = array( 'cluster' => $c['seed'], 'monthly_searches' => $c['total'], 'buyer_searches' => $c['buyer'], 'trend_pct' => $c['change'], 'top' => array_slice( array_column( $c['top'], 'w' ), 0, 5 ) );
	}
	return array(
		'client'       => $in['client_name'],
		'business'     => fns_business_types()[ $in['business_type'] ] ?? '',
		'industry'     => $in['industry'],
		'goals'        => $in['goals'],
		'has_site'     => (bool) $cd,
		'search_console' => ! empty( $a['gsc']['totals'] ) ? array( 'clicks_3m' => $a['gsc']['totals']['clicks'], 'impressions_3m' => $a['gsc']['totals']['impressions'], 'brand_clicks' => $a['gsc']['brand']['brand'] ?? 0, 'nonbrand_clicks' => $a['gsc']['brand']['nonbrand'] ?? 0 ) : null,
		'market'       => array( 'monthly_searches' => $a['market']['total'] ?? 0, 'buyer_searches' => $a['market']['buyer'] ?? 0, 'clusters' => $clusters ),
		'client_profile' => $cd ? $profile( $cd ) : null,
		'competitors'  => array_map( $profile, array_keys( array_filter( (array) ( $a['profiles'] ?? array() ), function ( $x ) {
			return 'competitor' === ( $x['role'] ?? '' );
		} ) ) ),
		'keyword_gap'  => array( 'count' => $a['gap']['count'] ?? 0, 'top' => array_slice( array_column( (array) ( $a['gap']['top'] ?? array() ), 'w' ), 0, 8 ) ),
		'forecast'     => array( 'months' => $in['months'], 'baseline_clicks' => $a['forecast']['baseline'] ?? 0, 'expected_clicks_end' => array( $a['forecast']['scenarios']['low'] ?? 0, $a['forecast']['scenarios']['high'] ?? 0 ) ),
		'plan'         => array( 'package' => $pkg['label'] ?? '', 'quantities' => $pkg['qty'] ?? array(), 'monthly_fee' => $pkg['fee'] ?? 0 ),
		'notes'        => $in['notes'],
	);
}

/**
 * Generate texts with the AI.
 *
 * @return array|WP_Error
 */
function fns_writer_ai( $p, $a ) {
	$list   = array( 'type' => 'array', 'items' => array( 'type' => 'string' ) );
	$schema = array(
		'type'       => 'object',
		'properties' => array(
			'headline'          => array( 'type' => 'string' ),
			'executive_summary' => array( 'type' => 'string' ),
			'market'            => array( 'type' => 'string' ),
			'current_state'     => array( 'type' => 'string' ),
			'competitors'       => array( 'type' => 'string' ),
			'offpage'           => array( 'type' => 'string' ),
			'technical'         => array( 'type' => 'string' ),
			'content'           => array( 'type' => 'string' ),
			'strengths'         => $list,
			'weaknesses'        => $list,
			'opportunities'     => $list,
			'threats'           => $list,
			'next_steps'        => $list,
		),
		'required'   => array( 'headline', 'executive_summary', 'market', 'current_state', 'competitors', 'offpage', 'technical', 'content', 'strengths', 'weaknesses', 'opportunities', 'threats', 'next_steps' ),
	);
	$system = implode( "\n", array(
		'تو مدیر سئوی ارشد یک آژانس دیجیتال مارکتینگ ایرانی هستی و متن یک پروپوزال سئو را برای صاحب یک کسب‌وکار ایرانی می‌نویسی.',
		'قواعد:',
		'- فارسی ساده، روان و محترمانه؛ مثل یک مشاور باتجربه که رودررو توضیح می‌دهد. جمله‌های کوتاه. اصطلاح انگلیسی فقط اگر لازم است و با توضیح کوتاه.',
		'- لازم نیست توضیح بدهی سئو چیست؛ کارفرما می‌داند. مستقیم برو سر وضعیت او، رقبا و پول.',
		'- همه ادعاها با عدد از داده‌ها. عددها را گرد کن (مثلاً «حدود ۱۲ هزار جستجو در ماه»). از اعداد فارسی استفاده کن.',
		'- روی کلماتی که فروش و مشتری می‌آورند تأکید کن، نه بازدید آموزشی بی‌ارزش.',
		'- هرگز رتبه، کلیک یا فروش تضمینی وعده نده. بگو «تعهد ما انجام دقیق کارهاست» و پیش‌بینی‌ها را با «حدود» و بازه بیان کن.',
		'- برآوردهای ترافیک و درآمد رقبا تخمینی‌اند؛ این را یک‌بار و کوتاه بگو.',
		'- هر متن بخش ۲ تا ۴ جمله؛ خلاصه اجرایی دو پاراگراف کوتاه: (۱) وضعیت فعلی و چالش اصلی، (۲) راهکار، زمان و نتیجه واقع‌بینانه.',
		'- تیتر جلد یک جمله الهام‌بخش و دقیق درباره فرصت رشد این کسب‌وکار (حداکثر ۱۲ کلمه).',
		'- نقاط قوت/ضعف/فرصت/تهدید هر کدام ۳ تا ۴ مورد کوتاه (حداکثر ۱۲ کلمه)، مشخص و مبتنی بر داده.',
		'- قدم‌های بعدی ۳ مورد عملی (مثل جلسه مرور، دسترسی‌ها، شروع ماه اول).',
		'- اگر سایت ندارد، به‌جای ضعف سایت، از «شروع درست از صفر» و فرصت معماری صحیح بگو.',
	) );
	$user = "داده‌های تحلیل (JSON):\n" . wp_json_encode( fns_writer_facts( $p, $a ), JSON_UNESCAPED_UNICODE );
	$res  = fns_ai_request( $system, $user, $schema, 'analysis', 6000 );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	foreach ( array( 'strengths', 'weaknesses', 'opportunities', 'threats', 'next_steps' ) as $k ) {
		$res[ $k ] = implode( "\n", array_map( 'strval', (array) ( $res[ $k ] ?? array() ) ) );
	}
	return array_map( 'strval', array_intersect_key( $res, fns_text_fields() ) );
}

/**
 * Template texts (no AI): built from the same numbers.
 */
function fns_writer_fallback( $p, $a ) {
	$in     = $p['inputs'];
	$cd     = $a['client_domain'] ?? '';
	$m      = (array) ( $a['market'] ?? array() );
	$comps  = array_filter( (array) ( $a['profiles'] ?? array() ), function ( $x ) {
		return 'competitor' === ( $x['role'] ?? '' );
	} );
	$leader = '';
	$lead_c = 0;
	foreach ( $comps as $d => $pr ) {
		if ( $pr['clicks'] > $lead_c ) {
			$leader = $d;
			$lead_c = $pr['clicks'];
		}
	}
	$client = $a['profiles'][ $cd ] ?? array();
	$tech   = $a['technical'][ $cd ] ?? array();
	$f      = (array) ( $a['forecast'] ?? array() );
	$top_cl = array_slice( array_keys( (array) ( $m['clusters'] ?? array() ) ), 0, 3 );
	$off_c  = $a['offpage'][ $cd ] ?? array();
	$off_avg = fns_avg( array_map( function ( $d ) use ( $a ) {
		return (int) ( $a['offpage'][ $d ]['quality_reportages'] ?? 0 );
	}, array_keys( $comps ) ) );
	$name   = $in['client_name'] ?: 'شما';
	$t      = array();
	$t['headline'] = sprintf( 'ماهانه حدود %s جستجوی خرید در بازار %s؛ سهم شما کجاست؟', fns_compact( $m['buyer'] ?? 0 ), $in['industry'] ?: ( $top_cl[0] ?? 'شما' ) );
	$t['executive_summary'] = ( $cd
		? sprintf( 'در بازار %s هر ماه حدود %s جستجو انجام می‌شود که حدود %s آن جستجوی آماده خرید است. امروز %s در گوگل با حدود %s کلمه دیده می‌شود، در حالی که %s با حدود %s کلمه و تخمین %s کلیک ماهانه بیشترین سهم را دارد.', implode( '، ', $top_cl ), fns_compact( $m['total'] ?? 0 ), fns_compact( $m['buyer'] ?? 0 ), $name, fns_num( $client['keywords'] ?? 0 ), $leader, fns_num( $comps[ $leader ]['keywords'] ?? 0 ), fns_compact( $lead_c ) )
		: sprintf( 'در بازار %s هر ماه حدود %s جستجو انجام می‌شود که حدود %s آن جستجوی آماده خرید است. چون سایت از ابتدا ساخته می‌شود، فرصت داریم معماری آن را از روز اول درست و بر اساس همین تقاضا بچینیم.', implode( '، ', $top_cl ), fns_compact( $m['total'] ?? 0 ), fns_compact( $m['buyer'] ?? 0 ) ) )
		. "\n\n" . sprintf( 'پیشنهاد ما یک برنامه %s ماهه است: ماه اول رفع موانع و پایه‌گذاری، سپس صفحات پول‌ساز، محتوای تخصصی و رپورتاژ هدفمند. پیش‌بینی واقع‌بینانه ما رسیدن به حدود %s تا %s کلیک غیربرندی در ماه تا پایان دوره است. تعهد ما انجام دقیق و شفاف کارهاست، نه وعده رتبه.', fns_num( $in['months'] ), fns_num( $f['scenarios']['low'] ?? 0 ), fns_num( $f['scenarios']['high'] ?? 0 ) );
	$t['market'] = sprintf( 'بیشترین تقاضا در خوشه‌های %s است. حدود %s از جستجوها نیت خرید، مقایسه یا مراجعه دارند؛ تمرکز ما روی همین جستجوهاست که مشتری می‌آورند.', implode( '، ', $top_cl ), fns_pct( ( $m['buyer'] ?? 0 ) / max( 1, $m['total'] ?? 1 ) ) );
	$t['current_state'] = $cd
		? ( ! empty( $a['gsc']['totals'] )
			? sprintf( 'طبق سرچ کنسول، سایت در ۳ ماه گذشته %s کلیک و %s نمایش در گوگل داشته؛ %s از کلیک‌ها برندی است (کسانی که نام شما را می‌دانند).', fns_num( $a['gsc']['totals']['clicks'] ), fns_num( $a['gsc']['totals']['impressions'] ), fns_pct( ( $a['gsc']['brand']['brand'] ?? 0 ) / max( 1, $a['gsc']['totals']['clicks'] ) ) )
			: sprintf( 'سایت شما برای حدود %s کلمه در گوگل دیده می‌شود و تخمین ما حدود %s کلیک ماهانه از گوگل است. %s صفحه از سایت در نتایج رتبه دارد.', fns_num( $client['keywords'] ?? 0 ), fns_num( $client['clicks'] ?? 0 ), fns_num( $client['ranking_pages'] ?? 0 ) ) )
		: 'سایت هنوز راه‌اندازی نشده است؛ بنابراین رقبا مبنای مقایسه هستند و از روز اول ساختار درست را پیاده می‌کنیم.';
	$t['competitors'] = $leader ? sprintf( '%s با تخمین %s کلیک ماهانه پیشتاز این بازار است. رقبا به‌طور میانگین با %s صفحه در گوگل رتبه دارند. این عددها تخمینی‌اند ولی نشان می‌دهند این سهم از بازار قابل گرفتن است.', $leader, fns_compact( $lead_c ), fns_num( fns_avg( array_column( $comps, 'ranking_pages' ) ) ) ) : '';
	$t['offpage'] = sprintf( 'رقبا به‌طور میانگین %s رپورتاژ باکیفیت (رسانه ایرانی، لینک فالو، انکر هدفمند) دارند و %s %s. لینک‌های بی‌کیفیت مثل دایرکتوری، کامنت و پروفایل را حساب نکرده‌ایم.', fns_num( $off_avg ), $name, $cd ? fns_num( (int) ( $off_c['quality_reportages'] ?? 0 ) ) . ' رپورتاژ' : 'هنوز رپورتاژی ندارد' );
	$t['technical'] = $cd && isset( $tech['score'] ) ? sprintf( 'امتیاز فنی سایت %s از ۱۰۰ (%s) است. مهم‌ترین موارد: %s.', fns_num( $tech['score'] ), $tech['grade'], implode( '، ', array_slice( array_column( (array) $tech['issues'], 'label' ), 0, 3 ) ) ?: 'مورد مهمی دیده نشد' ) : 'سایت جدید را از ابتدا با استانداردهای فنی (سرعت، ساختار آدرس، اسکیما و نقشه سایت) می‌سازیم.';
	$t['content'] = sprintf( 'حدود %s سؤال پرجستجو و %s کلمه‌ای که رقبا دارند و %s ندارد پیدا شد. برنامه محتوا از همین فاصله ساخته شده است.', fns_num( count( (array) ( $m['questions'] ?? array() ) ) ), fns_num( $a['gap']['count'] ?? 0 ), $name );
	$t['strengths']     = $cd ? implode( "\n", array_filter( array( ! empty( $a['gsc']['brand']['brand'] ) ? 'برند شناخته‌شده و جستجوی مستقیم نام شما' : '', ( $tech['score'] ?? 0 ) >= 70 ? 'پایه فنی قابل قبول' : '', ( $client['keywords'] ?? 0 ) > 100 ? 'حضور اولیه در گوگل برای ' . fns_num( $client['keywords'] ) . ' کلمه' : '', 'شناخت بازار و مشتری' ) ) ) : "شروع از صفر بدون بدهی فنی\nامکان معماری درست از روز اول";
	$t['weaknesses']    = implode( "\n", array_filter( array( $cd && ( $client['money_pages'] ?? array() ) ? '' : 'صفحات اختصاصی برای کلمات خرید کم است', ( $off_c['quality_reportages'] ?? 0 ) < $off_avg ? 'اعتبار و رپورتاژ کمتر از رقبا' : '', ( $tech['score'] ?? 100 ) < 70 ? 'مشکلات فنی مؤثر بر خزش و سرعت' : '' ) ) );
	$t['opportunities'] = implode( "\n", array_filter( array( sprintf( '%s جستجوی خرید ماهانه', fns_compact( $m['buyer'] ?? 0 ) ), ( $a['gap']['count'] ?? 0 ) ? fns_num( $a['gap']['count'] ) . ' کلمه که رقبا دارند و شما ندارید' : '', 'سؤال‌های پرجستجوی مشتری که کسی کامل جواب نداده' ) ) );
	$t['threats']       = implode( "\n", array_filter( array( $leader ? 'سرمایه‌گذاری مداوم ' . $leader . ' روی سئو' : '', 'افزایش هزینه تبلیغات و وابستگی به آن', 'تغییرات الگوریتم گوگل' ) ) );
	$t['next_steps']    = "جلسه مرور پروپوزال و پاسخ به سؤال‌ها\nدریافت دسترسی سایت، سرچ کنسول و آنالیتیکس\nشروع ماه اول: رفع موانع فنی و نقشه کلمات";
	return $t;
}

/**
 * Default commitment / client-needs lists (agency standard, editable).
 */
function fns_default_commitments() {
	return array(
		'commitments'  => "انجام دقیق تعداد محتوا، به‌روزرسانی و رپورتاژ اعلام‌شده در هر ماه\nرفع مشکلات فنی اعلام‌شده در ماه اول\nگزارش ماهانه شفاف: کارهای انجام‌شده، لینک‌ها و تغییر رتبه و ترافیک\nجلسه ماهانه مرور نتایج و برنامه ماه بعد\nبدون لینک اسپم و روش‌های پرخطر؛ همه کارها مطابق قوانین گوگل",
		'client_needs' => "دسترسی سایت (یا هماهنگی با تیم فنی شما)\nدسترسی سرچ کنسول و گوگل آنالیتیکس\nیک نفر رابط برای تأیید محتوا و اطلاعات محصولات/خدمات\nپرداخت مستقیم بودجه محتوا و رپورتاژ (در پنل‌های خودتان)",
	);
}

/**
 * Final texts: AI / template + saved edits.
 */
function fns_texts( $p, $a ) {
	$base  = array_merge( fns_default_commitments(), fns_writer_fallback( $p, $a ), array_filter( (array) ( $a['ai_texts'] ?? array() ), 'strlen' ) );
	$edits = array_filter( (array) $p['texts'], function ( $v ) {
		return is_string( $v ) && '' !== trim( $v );
	} );
	return array_merge( $base, $edits );
}

/**
 * Lines of a list text.
 */
function fns_lines( $text ) {
	return array_values( array_filter( array_map( function ( $l ) {
		return trim( preg_replace( '/^\s*(?:[\-•*–]+|[0-9۰-۹]+[.)])\s*/u', '', $l ) );
	}, preg_split( '/\r\n|\r|\n/', (string) $text ) ), 'strlen' ) );
}
