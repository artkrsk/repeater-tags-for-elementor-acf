<?php
/**
 * Plugin Name: RT Demo Fixtures (local dev only)
 * Description: Demo ACF field groups + seeded page for developing Repeater Tags for Elementor & ACF. Not part of the plugin - synced to the Local site AND mounted as mu-plugins by the wp-env test harness (the field groups are the test schema; seeders hook admin_init only and stay inert under PHPUnit).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// CPT with an archive: exercises the post-type-archive context (queried object is a
// WP_Post_Type — ACF has no storage for it; documents what the context ladder does there).
add_action(
	'init',
	function () {
		register_post_type(
			'rt_book',
			array(
				'label'       => 'RT Books',
				'public'      => true,
				'has_archive' => 'rt-books',
				'rewrite'     => array( 'slug' => 'rt-book' ),
				'supports'    => array( 'title' ),
			)
		);
	}
);

add_action(
	'acf/init',
	function () {
		if ( function_exists( 'acf_add_options_page' ) ) {
			acf_add_options_page(
				array(
					'page_title' => 'RT Demo Options',
					'menu_slug'  => 'rt-demo-options',
				)
			);
		}
	}
);

add_action(
	'acf/include_fields',
	function () {
		// Shop-shaped showcase: mirrors ArtsStore's "Product Presentation" repeaters
		// (same sub-field names/types) so the demo exercises a real-world schema.
		acf_add_local_field_group(
			array(
				'key'      => 'group_rt_product_presentation',
				'title'    => 'Product Presentation (Demo)',
				'fields'   => array(
					array(
						'key'        => 'field_rt_product_mockups',
						'label'      => 'Product Mockups',
						'name'       => 'product_mockups',
						'type'       => 'repeater',
						'layout'     => 'row',
						'sub_fields' => array(
							array( 'key' => 'field_rt_pm_caption', 'label' => 'Caption', 'name' => 'caption', 'type' => 'text' ),
							array( 'key' => 'field_rt_pm_description', 'label' => 'Description', 'name' => 'description', 'type' => 'textarea' ),
							array( 'key' => 'field_rt_pm_image', 'label' => 'Image', 'name' => 'image', 'type' => 'image', 'return_format' => 'array' ),
							array( 'key' => 'field_rt_pm_video', 'label' => 'Video', 'name' => 'video', 'type' => 'file', 'return_format' => 'array' ),
							array( 'key' => 'field_rt_pm_video_youtube', 'label' => 'Video (YouTube)', 'name' => 'video_youtube', 'type' => 'text' ),
							array( 'key' => 'field_rt_pm_video_vimeo', 'label' => 'Video (Vimeo)', 'name' => 'video_vimeo', 'type' => 'text' ),
							array( 'key' => 'field_rt_pm_gallery', 'label' => 'Gallery', 'name' => 'gallery', 'type' => 'gallery', 'return_format' => 'array' ),
							array( 'key' => 'field_rt_pm_link', 'label' => 'Link', 'name' => 'link', 'type' => 'link', 'return_format' => 'array' ),
						),
					),
					array(
						'key'        => 'field_rt_product_counters',
						'label'      => 'Product Counters',
						'name'       => 'product_counters',
						'type'       => 'repeater',
						'layout'     => 'table',
						'sub_fields' => array(
							array( 'key' => 'field_rt_pc_value', 'label' => 'Value', 'name' => 'value', 'type' => 'number' ),
							array( 'key' => 'field_rt_pc_label', 'label' => 'Label', 'name' => 'label', 'type' => 'text' ),
						),
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'page',
						),
					),
				),
			)
		);

		acf_add_local_field_group(
			array(
				'key'      => 'group_rt_demo',
				'title'    => 'RT Demo',
				'fields'   => array(
					array(
						'key'        => 'field_rt_demo_items',
						'label'      => 'Demo Items',
						'name'       => 'rt_demo_items',
						'type'       => 'repeater',
						'layout'     => 'row',
						// Deliberately NOT the first text sub-field — proves the picker
						// honors the admin-chosen collapsed label over the fallback.
						'collapsed'  => 'field_rt_demo_blurb',
						'sub_fields' => array(
							array( 'key' => 'field_rt_demo_caption', 'label' => 'Caption', 'name' => 'caption', 'type' => 'text' ),
							array( 'key' => 'field_rt_demo_blurb', 'label' => 'Blurb', 'name' => 'blurb', 'type' => 'textarea' ),
							array( 'key' => 'field_rt_demo_image', 'label' => 'Image', 'name' => 'image', 'type' => 'image', 'return_format' => 'array' ),
							array( 'key' => 'field_rt_demo_file', 'label' => 'File', 'name' => 'file', 'type' => 'file', 'return_format' => 'array' ),
							array( 'key' => 'field_rt_demo_cta', 'label' => 'CTA', 'name' => 'cta', 'type' => 'link', 'return_format' => 'array' ),
							array( 'key' => 'field_rt_demo_website', 'label' => 'Website', 'name' => 'website', 'type' => 'url' ),
							array( 'key' => 'field_rt_demo_photos', 'label' => 'Photos', 'name' => 'photos', 'type' => 'gallery', 'return_format' => 'array' ),
							array( 'key' => 'field_rt_demo_count', 'label' => 'Count', 'name' => 'count', 'type' => 'number' ),
							array( 'key' => 'field_rt_demo_accent', 'label' => 'Accent', 'name' => 'accent', 'type' => 'color_picker' ),
							array( 'key' => 'field_rt_demo_starts_at', 'label' => 'Starts At', 'name' => 'starts_at', 'type' => 'date_time_picker' ),
							array( 'key' => 'field_rt_demo_badge', 'label' => 'Badge', 'name' => 'badge', 'type' => 'select', 'return_format' => 'array', 'choices' => array( 'featured' => 'Featured', 'limited' => 'Limited run', 'staple' => 'Everyday staple' ) ),
							array( 'key' => 'field_rt_demo_details', 'label' => 'Details', 'name' => 'details', 'type' => 'wysiwyg' ),
							array( 'key' => 'field_rt_demo_related_book', 'label' => 'Related book', 'name' => 'related_book', 'type' => 'post_object', 'post_type' => array( 'rt_book' ), 'return_format' => 'object' ),
						),
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'page',
						),
					),
				),
			)
		);

		// Options-scoped repeater: exercises the options_page location detection,
		// the page-capability ajax branch, and rung 0 of the context ladder.
		acf_add_local_field_group(
			array(
				'key'      => 'group_rt_demo_options',
				'title'    => 'RT Demo Options',
				'fields'   => array(
					array(
						'key'        => 'field_rt_global_items',
						'label'      => 'Global Items',
						'name'       => 'rt_global_items',
						'type'       => 'repeater',
						'layout'     => 'row',
						'sub_fields' => array(
							array( 'key' => 'field_rt_go_caption', 'label' => 'Caption', 'name' => 'caption', 'type' => 'text' ),
							array( 'key' => 'field_rt_go_count', 'label' => 'Count', 'name' => 'count', 'type' => 'number' ),
							array( 'key' => 'field_rt_go_accent', 'label' => 'Accent', 'name' => 'accent', 'type' => 'color_picker' ),
							array( 'key' => 'field_rt_go_starts_at', 'label' => 'Starts At', 'name' => 'starts_at', 'type' => 'date_time_picker' ),
							array( 'key' => 'field_rt_go_link', 'label' => 'Link', 'name' => 'link', 'type' => 'url' ),
							array( 'key' => 'field_rt_go_address', 'label' => 'Address', 'name' => 'address', 'type' => 'text' ),
							array( 'key' => 'field_rt_go_location', 'label' => 'Location', 'name' => 'location', 'type' => 'google_map' ),
							array( 'key' => 'field_rt_go_perks', 'label' => 'Perks', 'name' => 'perks', 'type' => 'checkbox', 'return_format' => 'label', 'choices' => array( 'returns' => 'Free returns', 'wrap' => 'Gift wrap', 'support' => 'Priority support' ) ),
						),
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'options_page',
							'operator' => '==',
							'value'    => 'rt-demo-options',
						),
					),
				),
			)
		);

		// Term-scoped repeater: exercises rung 2 of the context ladder (term_{id})
		// on a Theme Builder category archive template.
		acf_add_local_field_group(
			array(
				'key'      => 'group_rt_term_demo',
				'title'    => 'RT Term Demo',
				'fields'   => array(
					array(
						'key'        => 'field_rt_term_items',
						'label'      => 'Term Items',
						'name'       => 'rt_term_items',
						'type'       => 'repeater',
						'layout'     => 'row',
						'sub_fields' => array(
							array( 'key' => 'field_rt_ti_caption', 'label' => 'Caption', 'name' => 'caption', 'type' => 'text' ),
							array( 'key' => 'field_rt_ti_website', 'label' => 'Website', 'name' => 'website', 'type' => 'url' ),
						),
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'taxonomy',
							'operator' => '==',
							'value'    => 'category',
						),
					),
				),
			)
		);

		// Author/user-scoped repeater: exercises rung 2 of the context ladder for the WP_User
		// branch (user_{id}) on a Theme Builder author-archive template. The frontend resolves
		// it (queried WP_User → user_{id}); the editor row picker forwards preview_type
		// 'archive/author', which the rows ajax must match (not the bare 'author') to enumerate.
		acf_add_local_field_group(
			array(
				'key'      => 'group_rt_author_demo',
				'title'    => 'RT Author Demo',
				'fields'   => array(
					array(
						'key'        => 'field_rt_author_items',
						'label'      => 'Author Items',
						'name'       => 'rt_author_items',
						'type'       => 'repeater',
						'layout'     => 'row',
						'sub_fields' => array(
							array( 'key' => 'field_rt_ai_caption', 'label' => 'Caption', 'name' => 'caption', 'type' => 'text' ),
							array( 'key' => 'field_rt_ai_website', 'label' => 'Website', 'name' => 'website', 'type' => 'url' ),
						),
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'user_form',
							'operator' => '==',
							'value'    => 'all',
						),
					),
				),
			)
		);

		// Repeater on the rt_book CPT — the posts carry the data; the interesting question
		// is what the tags resolve on the /rt-books/ POST TYPE ARCHIVE (no ACF storage there).
		acf_add_local_field_group(
			array(
				'key'      => 'group_rt_book_demo',
				'title'    => 'RT Book Demo',
				'fields'   => array(
					array(
						'key'        => 'field_rt_book_specs',
						'label'      => 'Specs',
						'name'       => 'rt_book_specs',
						'type'       => 'repeater',
						'layout'     => 'table',
						'sub_fields' => array(
							array( 'key' => 'field_rt_bs_caption', 'label' => 'Caption', 'name' => 'caption', 'type' => 'text' ),
						),
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'rt_book',
						),
					),
				),
			)
		);

		// Nested structures demo (GH #2): a repeater with a group + a nested repeater in its
		// rows, a top-level group hosting a repeater, and a two-layout flexible content
		// field. Both layouts define a 'heading' text sub-field on purpose — it exercises
		// the union dedupe ("Hero, Quote → Heading"); 'author' lives only in Quote for the
		// layout-mismatch fail-closed assertion.
		acf_add_local_field_group(
			array(
				'key'      => 'group_rt_nested_demo',
				'title'    => 'RT Nested Demo',
				'fields'   => array(
					array(
						'key'        => 'field_rt_nested_parent_items',
						'label'      => 'Parent Items',
						'name'       => 'rt_parent_items',
						'type'       => 'repeater',
						'layout'     => 'row',
						'sub_fields' => array(
							array( 'key' => 'field_rt_np_title', 'label' => 'Title', 'name' => 'title', 'type' => 'text' ),
							array(
								'key'        => 'field_rt_np_meta',
								'label'      => 'Meta',
								'name'       => 'meta',
								'type'       => 'group',
								'sub_fields' => array(
									array( 'key' => 'field_rt_np_meta_sku', 'label' => 'SKU', 'name' => 'sku', 'type' => 'text' ),
								),
							),
							array(
								'key'        => 'field_rt_np_specs',
								'label'      => 'Specs',
								'name'       => 'specs',
								'type'       => 'repeater',
								'layout'     => 'table',
								'sub_fields' => array(
									array( 'key' => 'field_rt_np_spec_name', 'label' => 'Spec Name', 'name' => 'spec_name', 'type' => 'text' ),
									array( 'key' => 'field_rt_np_spec_value', 'label' => 'Spec Value', 'name' => 'spec_value', 'type' => 'text' ),
								),
							),
						),
					),
					array(
						'key'        => 'field_rt_ng_group',
						'label'      => 'Nested Group',
						'name'       => 'rt_nested_group',
						'type'       => 'group',
						'sub_fields' => array(
							array( 'key' => 'field_rt_ng_child', 'label' => 'Child', 'name' => 'child', 'type' => 'text' ),
							array(
								'key'        => 'field_rt_ng_repeater',
								'label'      => 'Group Repeater',
								'name'       => 'rt_group_repeater',
								'type'       => 'repeater',
								'layout'     => 'table',
								'sub_fields' => array(
									array( 'key' => 'field_rt_ng_label_txt', 'label' => 'Label Text', 'name' => 'label_txt', 'type' => 'text' ),
								),
							),
						),
					),
					array(
						'key'     => 'field_rt_fx_sections',
						'label'   => 'Flex Sections',
						'name'    => 'rt_flex_sections',
						'type'    => 'flexible_content',
						'layouts' => array(
							'layout_rt_fx_hero'  => array(
								'key'        => 'layout_rt_fx_hero',
								'name'       => 'hero',
								'label'      => 'Hero',
								'display'    => 'block',
								'sub_fields' => array(
									array( 'key' => 'field_rt_fx_hero_heading', 'label' => 'Heading', 'name' => 'heading', 'type' => 'text' ),
									array( 'key' => 'field_rt_fx_hero_image', 'label' => 'Image', 'name' => 'image', 'type' => 'image', 'return_format' => 'array' ),
								),
							),
							'layout_rt_fx_quote' => array(
								'key'        => 'layout_rt_fx_quote',
								'name'       => 'quote',
								'label'      => 'Quote',
								'display'    => 'block',
								'sub_fields' => array(
									array( 'key' => 'field_rt_fx_quote_heading', 'label' => 'Heading', 'name' => 'heading', 'type' => 'text' ),
									array( 'key' => 'field_rt_fx_quote_author', 'label' => 'Author', 'name' => 'author', 'type' => 'text' ),
								),
							),
						),
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'page',
						),
					),
				),
			)
		);
	}
);

add_action(
	'admin_init',
	function () {
		if ( ! function_exists( 'update_field' ) || get_option( 'arts_repeater_tags_demo_seeded' ) ) {
			return;
		}

		$page_id = wp_insert_post(
			array(
				'post_title'  => 'Repeater Tags Demo',
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);

		if ( ! $page_id || is_wp_error( $page_id ) ) {
			return;
		}

		$rows = array(
			array(
				'caption' => 'First item caption',
				'blurb'   => 'Blurb for the first row.',
				'cta'     => array( 'title' => 'Read more', 'url' => 'https://example.com/one', 'target' => '' ),
				'website' => 'https://example.com/one',
			),
			array(
				'caption' => 'Second item caption',
				'blurb'   => 'Blurb for the second row.',
				'cta'     => array( 'title' => 'Details', 'url' => 'https://example.com/two', 'target' => '_blank' ),
				'website' => 'https://example.com/two',
			),
			array(
				'caption' => 'Third item caption',
				'blurb'   => 'Blurb for the third row.',
				'cta'     => array( 'title' => 'Learn', 'url' => 'https://example.com/three', 'target' => '' ),
				'website' => 'https://example.com/three',
			),
		);

		update_field( 'field_rt_demo_items', $rows, $page_id );
		update_option( 'arts_repeater_tags_demo_seeded', $page_id );
	}
);

// v2 seeds for the expansion scope: new sub-field values on the demo page,
// options-page rows, and a category term with rows.
add_action(
	'admin_init',
	function () {
		if ( ! function_exists( 'update_field' ) || get_option( 'arts_repeater_tags_demo_seeded_v2' ) ) {
			return;
		}

		$page_id = (int) get_option( 'arts_repeater_tags_demo_seeded' );

		if ( $page_id > 0 ) {
			$rows = array(
				array(
					'caption'   => 'First item caption',
					'blurb'     => 'Blurb for the first row.',
					'cta'       => array( 'title' => 'Read more', 'url' => 'https://example.com/one', 'target' => '' ),
					'website'   => 'https://example.com/one',
					'count'     => 12,
					'accent'    => '#e63946',
					'starts_at' => '2026-12-31 12:00:00',
				),
				array(
					'caption'   => 'Second item caption',
					'blurb'     => 'Blurb for the second row.',
					'cta'       => array( 'title' => 'Details', 'url' => 'https://example.com/two', 'target' => '_blank' ),
					'website'   => 'https://example.com/two',
					'count'     => 40,
					'accent'    => '#457b9d',
					'starts_at' => '2027-03-15 09:30:00',
				),
				array(
					'caption'   => 'Third item caption',
					'blurb'     => 'Blurb for the third row.',
					'cta'       => array( 'title' => 'Learn', 'url' => 'https://example.com/three', 'target' => '' ),
					'website'   => 'https://example.com/three',
					'count'     => 7,
					'accent'    => '#2a9d8f',
					'starts_at' => '2027-06-01 18:00:00',
				),
			);

			update_field( 'field_rt_demo_items', $rows, $page_id );
		}

		update_field(
			'field_rt_global_items',
			array(
				array(
					'caption'   => 'Global promo banner',
					'count'     => 99,
					'accent'    => '#f4a261',
					'starts_at' => '2026-11-27 00:00:00',
					'link'      => 'https://example.com/promo',
				),
				array(
					'caption'   => 'Support office hours',
					'count'     => 24,
					'accent'    => '#264653',
					'starts_at' => '2026-08-01 10:00:00',
					'link'      => 'https://example.com/support',
				),
			),
			'option'
		);

		$term    = term_exists( 'RT Demo Cat', 'category' );
		$term    = $term ? $term : wp_insert_term( 'RT Demo Cat', 'category' );
		$term_id = is_array( $term ) ? (int) $term['term_id'] : (int) $term;

		if ( $term_id > 0 ) {
			update_field(
				'field_rt_term_items',
				array(
					array(
						'caption' => 'Term row one',
						'website' => 'https://example.com/term-one',
					),
					array(
						'caption' => 'Term row two',
						'website' => 'https://example.com/term-two',
					),
				),
				'term_' . $term_id
			);
		}

		update_option( 'arts_repeater_tags_demo_seeded_v2', 1 );
	}
);

// v3 seeds: the visual test stand — deterministic picsum images + generic-store copy.
// Replaces v2's abstract row values on the demo page and options; term rows stay v2.
add_action(
	'admin_init',
	function () {
		if ( ! function_exists( 'update_field' ) || get_option( 'arts_repeater_tags_demo_seeded_v3' ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$media = get_option( 'arts_repeater_tags_demo_media', array() );

		foreach ( array( 'p1', 'p2', 'p3', 'g1', 'g2', 'g3', 'g4', 'g5' ) as $slug ) {
			if ( ! empty( $media[ $slug ] ) && wp_attachment_is_image( $media[ $slug ] ) ) {
				continue;
			}

			$id = media_sideload_image( 'https://picsum.photos/seed/rt-' . $slug . '/1200/800.jpg', 0, 'RT demo ' . $slug, 'id' );

			if ( ! is_wp_error( $id ) ) {
				$media[ $slug ] = (int) $id;
			}
		}

		update_option( 'arts_repeater_tags_demo_media', $media );

		// Sideloads can fail transiently — retry on the next admin_init until all 8 landed.
		if ( count( $media ) < 8 ) {
			return;
		}

		$page_id = (int) get_option( 'arts_repeater_tags_demo_seeded' );

		if ( $page_id > 0 ) {
			update_field(
				'field_rt_product_mockups',
				array(
					array(
						'caption'       => 'Nordic Oak Desk Lamp',
						'description'   => 'Hand-finished oak base, warm 2700K LED and a touch dimmer.',
						'image'         => $media['p1'],
						'video_youtube' => 'https://www.youtube.com/watch?v=ScMzIvxBSi4',
						'gallery'       => array( $media['g1'], $media['g2'], $media['g3'] ),
						'link'          => array( 'title' => 'View product', 'url' => 'https://example.com/products/desk-lamp', 'target' => '' ),
					),
					array(
						'caption'     => 'Canvas Weekender Bag',
						'description' => 'Waxed cotton canvas with full-grain leather straps. 35 litres.',
						'image'       => $media['p2'],
						'gallery'     => array( $media['g2'], $media['g4'], $media['g5'] ),
						'link'        => array( 'title' => 'View product', 'url' => 'https://example.com/products/weekender-bag', 'target' => '' ),
					),
					array(
						'caption'     => 'Ceramic Pour-Over Set',
						'description' => 'Matte stoneware dripper and carafe for a slow morning brew.',
						'image'       => $media['p3'],
						'gallery'     => array( $media['g1'], $media['g3'] ),
						'link'        => array( 'title' => 'View product', 'url' => 'https://example.com/products/pour-over-set', 'target' => '' ),
					),
				),
				$page_id
			);

			update_field(
				'field_rt_product_counters',
				array(
					array( 'value' => 4800, 'label' => 'Five-star reviews' ),
					array( 'value' => 2300, 'label' => 'Orders shipped' ),
					array( 'value' => 14, 'label' => 'Day returns' ),
				),
				$page_id
			);

			update_field(
				'field_rt_demo_items',
				array(
					array(
						'caption'   => 'Summer Bundle',
						'blurb'     => 'Desk lamp + pour-over set together. Ships free worldwide.',
						'image'     => $media['g1'],
						'file'      => $media['p1'],
						'cta'       => array( 'title' => 'Shop the bundle', 'url' => 'https://example.com/offers/summer', 'target' => '' ),
						'website'   => 'https://example.com/offers/summer',
						'photos'    => array( $media['g1'], $media['g4'] ),
						'count'     => 129,
						'accent'    => '#e63946',
						'starts_at' => '2027-07-01 12:00:00',
					),
					array(
						'caption'   => 'Weekender Restock',
						'blurb'     => 'The bag is back in all three colours. Preorder now.',
						'image'     => $media['g2'],
						'file'      => $media['p2'],
						'cta'       => array( 'title' => 'Preorder', 'url' => 'https://example.com/offers/restock', 'target' => '_blank' ),
						'website'   => 'https://example.com/offers/restock',
						'photos'    => array( $media['g2'], $media['g5'] ),
						'count'     => 189,
						'accent'    => '#457b9d',
						'starts_at' => '2027-09-15 09:00:00',
					),
					array(
						'caption'   => 'Holiday Gift Set',
						'blurb'     => 'Curated small goods in a linen box, wrapped and ready.',
						'image'     => $media['g3'],
						'file'      => $media['p3'],
						'cta'       => array( 'title' => 'Reserve yours', 'url' => 'https://example.com/offers/holiday', 'target' => '' ),
						'website'   => 'https://example.com/offers/holiday',
						'photos'    => array( $media['g3'], $media['g4'] ),
						'count'     => 99,
						'accent'    => '#2a9d8f',
						'starts_at' => '2027-11-27 00:00:00',
					),
				),
				$page_id
			);
		}

		update_field(
			'field_rt_global_items',
			array(
				array(
					'caption'   => 'Free shipping on orders over $50',
					'count'     => 50,
					'accent'    => '#f4a261',
					'starts_at' => '2027-01-31 23:59:59',
					'link'      => 'https://example.com/shipping',
					'address'   => '30 Rockefeller Plaza, New York, NY',
				),
				array(
					'caption'   => 'Support hours: Mon–Fri, 9:00–17:00',
					'count'     => 24,
					'accent'    => '#264653',
					'starts_at' => '2027-02-14 10:00:00',
					'link'      => 'https://example.com/support',
					'address'   => 'Alexanderplatz 1, Berlin',
				),
			),
			'option'
		);

		update_option( 'arts_repeater_tags_demo_seeded_v3', 1 );
	}
);

// v4 seeds: two RT Books with distinct repeater rows, so a post-type-archive render
// reveals WHICH post (if any) the context ladder resolved against.
add_action(
	'admin_init',
	function () {
		if ( ! function_exists( 'update_field' ) || get_option( 'arts_repeater_tags_demo_seeded_v4' ) ) {
			return;
		}

		$books = array(
			'Book Alpha' => array( 'Alpha spec row 1', 'Alpha spec row 2' ),
			'Book Beta'  => array( 'Beta spec row 1' ),
		);

		foreach ( $books as $title => $captions ) {
			$book_id = wp_insert_post(
				array(
					'post_title'  => $title,
					'post_type'   => 'rt_book',
					'post_status' => 'publish',
				)
			);

			if ( ! $book_id || is_wp_error( $book_id ) ) {
				return;
			}

			$rows = array();

			foreach ( $captions as $caption ) {
				$rows[] = array( 'caption' => $caption );
			}

			update_field( 'field_rt_book_specs', $rows, $book_id );
		}

		flush_rewrite_rules();
		update_option( 'arts_repeater_tags_demo_seeded_v4', 1 );
	}
);

// v5 seeds: a self-hosted mp4 (shipped in rt-demo-assets/) into the first mockup row's
// `video` File sub-field — the stand's "Choose Video File" binding reads it.
add_action(
	'admin_init',
	function () {
		if ( ! function_exists( 'update_field' ) || get_option( 'arts_repeater_tags_demo_seeded_v5' ) ) {
			return;
		}

		$page_id = (int) get_option( 'arts_repeater_tags_demo_seeded' );

		if ( $page_id <= 0 ) {
			return;
		}

		$rows = get_field( 'product_mockups', $page_id, false );

		if ( ! is_array( $rows ) || ! isset( $rows[0] ) ) {
			return;
		}

		// Idempotent across environments: a manually-seeded video wins.
		if ( empty( $rows[0]['field_rt_pm_video'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';

			$tmp = wp_tempnam( 'rt-demo-video.mp4' );

			if ( ! copy( __DIR__ . '/rt-demo-assets/rt-demo-video.mp4', $tmp ) ) {
				return;
			}

			$video_id = media_handle_sideload(
				array(
					'name'     => 'rt-demo-video.mp4',
					'tmp_name' => $tmp,
				),
				$page_id
			);

			if ( is_wp_error( $video_id ) ) {
				return;
			}

			$rows[0]['field_rt_pm_video'] = (int) $video_id;
			update_field( 'field_rt_product_mockups', $rows, $page_id );
		}

		update_option( 'arts_repeater_tags_demo_seeded_v5', 1 );
	}
);

// v6 seeds: values for the type-bridge sub-fields (choice/wysiwyg/post_object on the demo
// page rows; google_map/checkbox on the options rows). Add-only, raw rows keyed by field KEY.
add_action(
	'admin_init',
	function () {
		if ( ! function_exists( 'update_field' ) || get_option( 'arts_repeater_tags_demo_seeded_v6' ) ) {
			return;
		}

		$page_id = (int) get_option( 'arts_repeater_tags_demo_seeded' );

		if ( $page_id > 0 ) {
			$alpha = get_posts(
				array(
					'post_type'      => 'rt_book',
					'title'          => 'Book Alpha',
					'posts_per_page' => 1,
				)
			);

			$rows = get_field( 'rt_demo_items', $page_id, false );

			if ( is_array( $rows ) && isset( $rows[0] ) ) {
				$badges  = array( 'featured', 'limited', 'staple' );
				$details = array(
					'<p>Includes a <strong>2-year warranty</strong> and free recycling of your old lamp.</p>',
					'<p>Made of <strong>waxed cotton</strong> that ages beautifully with use.</p>',
					'<p>Ships in a <strong>plastic-free</strong> gift box with a brew guide.</p>',
				);

				foreach ( $rows as $i => $row ) {
					$rows[ $i ]['field_rt_demo_badge']   = $badges[ $i ] ?? 'staple';
					$rows[ $i ]['field_rt_demo_details'] = $details[ $i ] ?? '';

					if ( isset( $alpha[0] ) ) {
						$rows[ $i ]['field_rt_demo_related_book'] = (int) $alpha[0]->ID;
					}
				}

				update_field( 'field_rt_demo_items', $rows, $page_id );
			}
		}

		$options_rows = get_field( 'rt_global_items', 'option', false );

		if ( is_array( $options_rows ) && isset( $options_rows[0] ) ) {
			$options_rows[0]['field_rt_go_location'] = array(
				'address' => 'Studio 4, 30 Rockefeller Plaza, New York',
				'lat'     => 40.7587,
				'lng'     => -73.9787,
			);
			$options_rows[0]['field_rt_go_perks']    = array( 'returns', 'wrap' );

			update_field( 'field_rt_global_items', $options_rows, 'option' );
		}

		update_option( 'arts_repeater_tags_demo_seeded_v6', 1 );
	}
);

// v7 seeds: nested-structures values (GH #2). Whole-field name-keyed writes, incl. nested
// rows — the formatted read-back is CLI-verified. Parent row 3 deliberately has NO specs
// (an empty child repeater reads back as boolean false → fail-closed + picker empty state).
add_action(
	'admin_init',
	function () {
		if ( ! function_exists( 'update_field' ) || get_option( 'arts_repeater_tags_demo_seeded_v7' ) ) {
			return;
		}

		$page_id = (int) get_option( 'arts_repeater_tags_demo_seeded' );

		if ( $page_id <= 0 ) {
			return;
		}

		// Hero images reuse the v3 sideloads — retry on the next admin_init until they exist.
		$media = get_option( 'arts_repeater_tags_demo_media', array() );

		if ( ! is_array( $media ) || empty( $media['p1'] ) || empty( $media['p2'] ) ) {
			return;
		}

		update_field(
			'field_rt_nested_parent_items',
			array(
				array(
					'title' => 'Parent One',
					'meta'  => array( 'sku' => 'SKU-1001' ),
					'specs' => array(
						array( 'spec_name' => 'Weight', 'spec_value' => '1.2 kg' ),
						array( 'spec_name' => 'Width', 'spec_value' => '340 mm' ),
					),
				),
				array(
					'title' => 'Parent Two',
					'meta'  => array( 'sku' => 'SKU-2002' ),
					'specs' => array(
						array( 'spec_name' => 'Material', 'spec_value' => 'Solid oak' ),
					),
				),
				array(
					'title' => 'Parent Three',
					'meta'  => array( 'sku' => 'SKU-3003' ),
					'specs' => array(),
				),
			),
			$page_id
		);

		update_field(
			'field_rt_ng_group',
			array(
				'child'             => 'Group child text',
				'rt_group_repeater' => array(
					array( 'label_txt' => 'Inner row A' ),
					array( 'label_txt' => 'Inner row B' ),
				),
			),
			$page_id
		);

		update_field(
			'field_rt_fx_sections',
			array(
				array(
					'acf_fc_layout' => 'hero',
					'heading'       => 'Hero headline one',
					'image'         => (int) $media['p1'],
				),
				array(
					'acf_fc_layout' => 'quote',
					'heading'       => 'A quiet quote heading',
					'author'        => 'Jane Doe',
				),
				array(
					'acf_fc_layout' => 'hero',
					'heading'       => 'Hero headline two',
					'image'         => (int) $media['p2'],
				),
			),
			$page_id
		);

		update_option( 'arts_repeater_tags_demo_seeded_v7', 1 );
	}
);

// v8 seeds: author/user-scoped repeater rows on the first administrator — data for the
// Theme Builder author-archive stand section. The stored option value is the seeded user id,
// so you know which author to point the template's "Preview Dynamic Content as" at.
add_action(
	'admin_init',
	function () {
		if ( ! function_exists( 'update_field' ) || get_option( 'arts_repeater_tags_demo_seeded_v8' ) ) {
			return;
		}

		$admins = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => array( 'ID' ),
			)
		);

		if ( empty( $admins ) ) {
			return;
		}

		$user_id = (int) $admins[0]->ID;

		update_field(
			'field_rt_author_items',
			array(
				array(
					'caption' => 'Author row one',
					'website' => 'https://example.com/author-one',
				),
				array(
					'caption' => 'Author row two',
					'website' => 'https://example.com/author-two',
				),
			),
			'user_' . $user_id
		);

		update_option( 'arts_repeater_tags_demo_seeded_v8', $user_id );
	}
);
