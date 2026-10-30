<?php
/**
 * AI writing (executive summary, insights, SWOT, plain-language notes).
 *
 * Same API as Sokan: any OpenAI-compatible gateway (default GapGPT,
 * https://api.gapgpt.app/v1, which serves Claude / GPT / Gemini models) or the
 * Anthropic Messages API directly. When Sokan is active and Fanoos has no key of
 * its own, the call goes through Sokan's client, so its key, proxy rules and
 * monthly budget apply to both plugins.
 *
 * @package Fanoos
 */

defined( 'ABSPATH' ) || exit;

const FNS_CLAUDE_URL     = 'https://api.anthropic.com/v1/messages';
const FNS_CLAUDE_VERSION = '2026-10-21';

/**
 * Should the call go through Sokan's AI client?
 */
function fns_ai_via_sokan() {
	$own = fns_settings();
	return function_exists( 'sap_ai_request' ) && ! empty( $own['reuse_sokan'] ) && '' === (string) $own['ai_gateway_key'] && '' === (string) $own['anthropic_key'];
}

function fns_ai_connected() {
	if ( fns_ai_via_sokan() ) {
		return function_exists( 'sap_ai_connected' ) && sap_ai_connected();
	}
	return 'anthropic' === fns_setting( 'ai_provider', 'gateway' ) ? '' !== fns_setting( 'anthropic_key' ) : '' !== fns_setting( 'ai_gateway_key' );
}

/**
 * This month's spend (USD) of Fanoos' own calls.
 */
function fns_ai_usage() {
	$u = get_option( 'fns_ai_usage', array() );
	if ( ! is_array( $u ) || ( $u['month'] ?? '' ) !== gmdate( 'Y-m' ) ) {
		$u = array( 'month' => gmdate( 'Y-m' ), 'cost' => 0.0, 'requests' => 0 );
	}
	return $u;
}

/**
 * Approximate USD price per 1M tokens [in, out].
 */
function fns_ai_price( $model ) {
	$p = array(
		'gpt-4.1-mini'      => array( 0.40, 1.60 ),
		'gpt-5-mini'        => array( 0.25, 2.00 ),
		'claude-haiku-5-5'  => array( 0.10, 0.50 ),
		'claude-sonnet-5-5' => array( 2.00, 10.00 ),
		'claude-opus-5-5'   => array( 4.00, 20.00 ),
		'gemini-2.5-pro'    => array( 2.50, 20.00 ),
		'gpt-5'             => array( 1.25, 10.00 ),
	);
	return $p[ $model ] ?? array( 2.50, 15.00 );
}

function fns_ai_track( $model, $in, $out ) {
	$u             = fns_ai_usage();
	$p             = fns_ai_price( $model );
	$u['cost']     = round( $u['cost'] + ( $in * $p[0] + $out * $p[1] ) / 1e6, 5 );
	$u['requests'] = (int) $u['requests'] + 1;
	update_option( 'fns_ai_usage', $u, false );
}

/**
 * One request; the answer is a JSON object matching $schema.
 *
 * @param string $purpose analysis|light.
 * @return array|WP_Error
 */
function fns_ai_request( $system, $user, $schema, $purpose = 'analysis', $max_tokens = 8000 ) {
	if ( fns_ai_via_sokan() ) {
		return sap_ai_request( $system, $user, $schema, array( 'purpose' => $purpose, 'max_tokens' => $max_tokens, 'effort' => 'medium', 'timeout' => 300 ) );
	}
	if ( ! fns_ai_connected() ) {
		return new WP_Error( 'fns_ai_key', 'هوش مصنوعی متصل نیست (فانوس › تنظیمات › اتصال‌ها).' );
	}
	$u = fns_ai_usage();
	if ( (float) fns_setting( 'ai_budget', 10 ) > 0 && $u['cost'] >= (float) fns_setting( 'ai_budget', 10 ) ) {
		return new WP_Error( 'fns_ai_budget', 'سقف بودجه ماهانه هوش مصنوعی فانوس پر شده است؛ متن‌های آماده (بدون هوش مصنوعی) استفاده می‌شوند.' );
	}
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 330 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
	$model = preg_replace( '/[^a-zA-Z0-9._:\/-]/', '', (string) ( 'light' === $purpose ? fns_setting( 'ai_model_light', 'gpt-4.1-mini' ) : fns_setting( 'ai_model', 'claude-sonnet-5-5' ) ) );
	return 'anthropic' === fns_setting( 'ai_provider', 'gateway' )
		? fns_ai_anthropic( $model, $system, $user, $schema, $max_tokens )
		: fns_ai_gateway( $model, $system, $user, $schema, $max_tokens );
}

/**
 * OpenAI-compatible Chat Completions (GapGPT and similar).
 */
function fns_ai_gateway( $model, $system, $user, $schema, $max_tokens ) {
	$base    = untrailingslashit( esc_url_raw( fns_setting( 'ai_base_url', 'https://api.gapgpt.app/v1' ) ) );
	$system .= "\n\nپاسخ را فقط به صورت یک شیء JSON معتبر مطابق این JSON Schema بده، بدون متن اضافه و بدون ```:\n" . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE );
	$body    = array(
		'model'           => $model,
		'max_tokens'      => $max_tokens,
		'messages'        => array(
			array( 'role' => 'system', 'content' => $system ),
			array( 'role' => 'user', 'content' => $user ),
		),
		'response_format' => array( 'type' => 'json_schema', 'json_schema' => array( 'name' => 'proposal', 'schema' => $schema, 'strict' => false ) ),
	);
	$send = function ( $body ) use ( $base ) {
		return fns_http( $base . '/chat/completions', array(
			'method'  => 'POST',
			'timeout' => 300,
			'headers' => array( 'Authorization' => 'Bearer ' . fns_setting( 'ai_gateway_key' ), 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( $body ),
		) );
	};
	$res  = $send( $body );
	$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
	if ( in_array( $code, array( 400, 422 ), true ) ) {
		unset( $body['response_format'] ); // Some gateway / model pairs reject it.
		$res  = $send( $body );
		$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
	}
	if ( is_wp_error( $res ) ) {
		return new WP_Error( 'fns_ai_http', 'اتصال به سرویس هوش مصنوعی ناموفق بود: ' . fns_net_error( $res ) );
	}
	$data = json_decode( fns_body( $res ), true );
	if ( 200 !== $code ) {
		$msg = (string) ( $data['error']['message'] ?? 'HTTP ' . $code );
		if ( 402 === $code || preg_match( '/quota|credit|balance|insufficient/i', $msg ) ) {
			return new WP_Error( 'fns_ai_credit', 'اعتبار حساب سرویس هوش مصنوعی تمام شده است؛ حساب را شارژ کنید.' );
		}
		return new WP_Error( 'fns_ai_http', 'خطای سرویس هوش مصنوعی: ' . mb_substr( wp_strip_all_tags( $msg ), 0, 240 ) );
	}
	fns_ai_track( $model, (int) ( $data['usage']['prompt_tokens'] ?? 0 ), (int) ( $data['usage']['completion_tokens'] ?? 0 ) );
	$json = fns_ai_extract_json( (string) ( $data['choices'][0]['message']['content'] ?? '' ) );
	return null === $json ? new WP_Error( 'fns_ai_parse', 'پاسخ هوش مصنوعی قابل خواندن نبود؛ دوباره امتحان کنید.' ) : $json;
}

/**
 * Anthropic Messages API with structured output.
 */
function fns_ai_anthropic( $model, $system, $user, $schema, $max_tokens ) {
	$res = fns_http( FNS_CLAUDE_URL, array(
		'method'  => 'POST',
		'timeout' => 300,
		'headers' => array(
			'x-api-key'         => fns_setting( 'anthropic_key' ),
			'anthropic-version' => FNS_CLAUDE_VERSION,
			'content-type'      => 'application/json',
		),
		'body'    => wp_json_encode( array(
			'model'         => $model,
			'max_tokens'    => $max_tokens,
			'system'        => $system,
			'messages'      => array( array( 'role' => 'user', 'content' => $user ) ),
			'output_config' => array( 'effort' => 'medium', 'format' => array( 'type' => 'json_schema', 'schema' => $schema ) ),
		) ),
	) );
	if ( is_wp_error( $res ) ) {
		return new WP_Error( 'fns_ai_http', 'اتصال به سرویس هوش مصنوعی ناموفق بود: ' . fns_net_error( $res ) );
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	$data = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( 200 !== $code ) {
		return new WP_Error( 'fns_ai_http', 'خطای سرویس هوش مصنوعی: ' . ( $data['error']['message'] ?? 'HTTP ' . $code ) );
	}
	fns_ai_track( $model, (int) ( $data['usage']['input_tokens'] ?? 0 ), (int) ( $data['usage']['output_tokens'] ?? 0 ) );
	$text = '';
	foreach ( (array) ( $data['content'] ?? array() ) as $block ) {
		if ( 'text' === ( $block['type'] ?? '' ) ) {
			$text .= (string) $block['text'];
		}
	}
	$json = fns_ai_extract_json( $text );
	return null === $json ? new WP_Error( 'fns_ai_parse', 'پاسخ هوش مصنوعی قابل خواندن نبود؛ دوباره امتحان کنید.' ) : $json;
}

/**
 * JSON object from a model answer (tolerates ``` fences and surrounding text).
 */
function fns_ai_extract_json( $text ) {
	$text = trim( (string) $text );
	$json = json_decode( $text, true );
	if ( is_array( $json ) ) {
		return $json;
	}
	$start = strpos( $text, '{' );
	$end   = strrpos( $text, '}' );
	if ( false === $start || false === $end || $end <= $start ) {
		return null;
	}
	$json = json_decode( substr( $text, $start, $end - $start + 1 ), true );
	return is_array( $json ) ? $json : null;
}

/**
 * Connection test with a tiny request.
 */
function fns_ai_test() {
	$r = fns_ai_request( 'You are a test.', 'Answer {"ok": true}', array( 'type' => 'object', 'properties' => array( 'ok' => array( 'type' => 'boolean' ) ), 'required' => array( 'ok' ) ), 'light', 50 );
	if ( is_wp_error( $r ) ) {
		return array( 'ok' => false, 'msg' => $r->get_error_message() );
	}
	return array( 'ok' => true, 'msg' => 'هوش مصنوعی جواب داد' . ( fns_ai_via_sokan() ? ' (از طریق تنظیمات سکان)' : '' ) . '.' );
}
