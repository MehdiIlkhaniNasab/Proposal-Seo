<?php
/**
 * Proposal storage: a private post type; inputs, collected data (one packed meta
 * per source), computed budget / roadmap and edited texts as post meta.
 *
 * @package Ostorlab
 */

defined( 'ABSPATH' ) || exit;

/**
 * Data buckets collected by the analysis (one meta each).
 */
const OST_BUCKETS = array( 'keywords', 'seosignal', 'gap', 'reportage', 'semrush', 'gsc', 'audit', 'sf', 'analysis' );

function ost_register_cpt() {
	register_post_type( OST_CPT, array(
		'label'           => 'پروپوزال‌های اسطرلاب',
		'public'          => false,
		'show_ui'         => false,
		'show_in_rest'    => false,
		'supports'        => array( 'title' ),
		'capability_type' => 'post',
	) );
}

/**
 * Default inputs of a proposal.
 */
function ost_input_defaults() {
	return array(
		'client_name'     => '',
		'contact_person'  => '',
		'brand_terms'     => array(),
		'business_type'   => 'shop',
		'industry'        => '',
		'domain'          => '',
		'has_site'        => 1,
		'gsc_access'      => 0,
		'gsc_property'    => '',
		'cms_access'      => 0,
		'keywords'        => array(),
		'competitors'     => array(),
		'aov'             => 0,
		'conversion_rate' => (float) ost_setting( 'conversion_rate', 1.5 ),
		'months'          => (int) ost_setting( 'default_months', 12 ),
		'crawl_limit'     => 500,
		'goals'           => '',
		'notes'           => '',
		// Presenter (empty = from settings).
		'presenter_name'  => '',
		'presenter_title' => '',
		'presenter_phone' => '',
		'presenter_email' => '',
		'team'            => '',
		'proposal_no'     => '',
		'validity_days'   => 0,
	);
}

/**
 * Business types.
 */
function ost_business_types() {
	return array(
		'shop'    => 'فروشگاه اینترنتی',
		'service' => 'خدماتی (فرم، تماس، مشاوره)',
		'local'   => 'کسب‌وکار محلی (کلینیک، شعبه، نمایندگی)',
		'content' => 'رسانه / محتوایی',
		'saas'    => 'نرم‌افزار و پلتفرم آنلاین',
	);
}

/**
 * Proposal with all of its data.
 *
 * @return array|null
 */
function ost_proposal( $id ) {
	$post = get_post( (int) $id );
	if ( ! $post || OST_CPT !== $post->post_type ) {
		return null;
	}
	$inputs = wp_parse_args( (array) get_post_meta( $post->ID, '_ost_inputs', true ), ost_input_defaults() );
	return array(
		'id'       => $post->ID,
		'title'    => $post->post_title,
		'status'   => (string) get_post_meta( $post->ID, '_ost_status', true ) ?: 'draft',
		'inputs'   => $inputs,
		'token'    => ost_proposal_token( $post->ID ),
		'budget'   => (array) get_post_meta( $post->ID, '_ost_budget', true ),
		'texts'    => (array) get_post_meta( $post->ID, '_ost_texts', true ),
		'job'      => (array) get_post_meta( $post->ID, '_ost_job', true ),
		'slides'   => (array) get_post_meta( $post->ID, '_ost_slides', true ),
		'strategy' => (array) get_post_meta( $post->ID, '_ost_strategy', true ),
		'created'  => strtotime( $post->post_date_gmt . ' UTC' ),
		'modified' => strtotime( $post->post_modified_gmt . ' UTC' ),
	);
}

/**
 * All proposals, newest first.
 */
function ost_proposals() {
	$ids = get_posts( array( 'post_type' => OST_CPT, 'post_status' => 'any', 'numberposts' => 200, 'fields' => 'ids', 'orderby' => 'modified' ) );
	return array_filter( array_map( 'ost_proposal', $ids ) );
}

/**
 * Create or update a proposal from inputs.
 *
 * @return int Post ID.
 */
function ost_save_proposal( $id, $inputs ) {
	$inputs = ost_clean_inputs( $inputs );
	$title  = $inputs['client_name'] ?: ( $inputs['domain'] ?: 'پروپوزال بدون نام' );
	if ( $id && ost_proposal( $id ) ) {
		wp_update_post( array( 'ID' => $id, 'post_title' => $title ) );
	} else {
		$id = wp_insert_post( array( 'post_type' => OST_CPT, 'post_status' => 'publish', 'post_title' => $title ) );
		update_post_meta( $id, '_ost_status', 'draft' );
	}
	update_post_meta( $id, '_ost_inputs', $inputs );
	return (int) $id;
}

/**
 * Validate inputs from the form.
 */
function ost_clean_inputs( $in ) {
	$d   = ost_input_defaults();
	$out = array();
	foreach ( $d as $k => $def ) {
		$v = $in[ $k ] ?? $def;
		if ( is_array( $def ) ) {
			$v = is_array( $v ) ? $v : ost_list( $v );
			$v = array_values( array_filter( array_map( 'sanitize_text_field', $v ), 'strlen' ) );
		} elseif ( is_int( $def ) ) {
			$v = (int) ost_to_number( $v );
		} elseif ( is_float( $def ) ) {
			$v = (float) ost_to_number( $v );
		} else {
			$v = in_array( $k, array( 'goals', 'notes', 'team' ), true ) ? sanitize_textarea_field( (string) $v ) : sanitize_text_field( (string) $v );
		}
		$out[ $k ] = $v;
	}
	foreach ( array( 'has_site', 'gsc_access', 'cms_access' ) as $flag ) {
		$out[ $flag ] = empty( $in[ $flag ] ) ? 0 : 1;
	}
	$out['domain']      = ost_domain( $out['domain'] );
	$out['has_site']    = '' !== $out['domain'] && $out['has_site'] ? 1 : 0;
	$out['keywords']    = array_slice( $out['keywords'], 0, 10 );
	$out['competitors'] = array_slice( array_values( array_unique( array_filter( array_map( 'ost_domain', $out['competitors'] ) ) ) ), 0, 6 );
	$out['competitors'] = array_values( array_diff( $out['competitors'], array( $out['domain'] ) ) );
	$out['months']      = (int) ost_clamp( $out['months'], 6, 18 );
	$out['crawl_limit'] = (int) ost_clamp( $out['crawl_limit'], 50, 5000 );
	$out['business_type'] = array_key_exists( $out['business_type'], ost_business_types() ) ? $out['business_type'] : 'shop';
	if ( ! $out['brand_terms'] && $out['domain'] ) {
		$out['brand_terms'] = array( preg_replace( '/\..*$/', '', $out['domain'] ) );
	}
	return $out;
}

/**
 * Collected data of a bucket.
 */
function ost_data( $id, $bucket ) {
	return ost_unpack( (string) get_post_meta( $id, '_ost_r_' . $bucket, true ) );
}

/**
 * Store a bucket.
 */
function ost_set_data( $id, $bucket, $data ) {
	update_post_meta( $id, '_ost_r_' . $bucket, ost_pack( $data ) );
}

/**
 * Merge one key into a bucket (e.g. one competitor's data).
 */
function ost_put_data( $id, $bucket, $key, $value ) {
	$d         = ost_data( $id, $bucket );
	$d[ $key ] = $value;
	ost_set_data( $id, $bucket, $d );
}

/**
 * Share / agent token of a proposal (web view link and Screaming Frog uploads).
 */
function ost_proposal_token( $id ) {
	$t = (string) get_post_meta( $id, '_ost_token', true );
	if ( '' === $t ) {
		$t = wp_generate_password( 24, false );
		update_post_meta( $id, '_ost_token', $t );
	}
	return $t;
}

/**
 * Domains of a proposal: client first (when it has a site), then competitors.
 *
 * @return array<string,string> domain => role (client|competitor)
 */
function ost_domains( $p ) {
	$out = array();
	if ( $p['inputs']['has_site'] && $p['inputs']['domain'] ) {
		$out[ $p['inputs']['domain'] ] = 'client';
	}
	foreach ( $p['inputs']['competitors'] as $c ) {
		$out[ $c ] = 'competitor';
	}
	return $out;
}

/**
 * Delete a proposal.
 */
function ost_delete_proposal( $id ) {
	if ( ost_proposal( $id ) ) {
		wp_delete_post( (int) $id, true );
	}
}

/**
 * Duplicate a proposal's inputs (new client in the same industry).
 */
function ost_duplicate_proposal( $id ) {
	$p = ost_proposal( $id );
	if ( ! $p ) {
		return 0;
	}
	$in                = $p['inputs'];
	$in['client_name'] = $in['client_name'] . ' (کپی)';
	return ost_save_proposal( 0, $in );
}
