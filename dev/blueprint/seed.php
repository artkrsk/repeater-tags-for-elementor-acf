<?php
/**
 * Plugin Name: Repeater Tags — Playground Demo Seed
 * Description: Seeds the wp.org Live Preview demo. Inlined into blueprint.json by
 *              dev/blueprint/build-blueprint.js — never shipped in the plugin ZIP.
 *
 * Self-contained on purpose: it registers its OWN field groups (field_rtb_* — distinct from
 * dev/mu-plugins/rt-demo-fixtures.php's field_rt_*) so the two can coexist on the dev site and
 * neither drifts from the other. Images are drawn with GD rather than sideloaded — Playground
 * has no reliable outbound network for media.
 *
 * @package Arts\RepeaterTags
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pinned so the blueprint's landingPage can address the page without guessing. The generator
 * reads this constant — keep the literal on one line.
 */
define( 'RTB_DEMO_PAGE_ID', 9901 );

// Elementor otherwise hijacks the first admin request with its onboarding wizard, which would
// land the visitor somewhere other than the editor.
add_action(
	'admin_init',
	function () {
		update_option( 'elementor_onboarded', true );
		delete_transient( 'elementor_activation_redirect' );
	},
	1
);

add_action(
	'acf/init',
	function () {
		if ( function_exists( 'acf_add_options_page' ) ) {
			acf_add_options_page(
				array(
					'page_title' => 'Demo Site Settings',
					'menu_title' => 'Demo Site Settings',
					'menu_slug'  => 'rt-demo-options',
				)
			);
		}
	}
);

add_action(
	'acf/include_fields',
	function () {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		acf_add_local_field_group(
			array(
				'key'      => 'group_rtb_page',
				'title'    => 'Showcase (Demo)',
				'fields'   => array(
					array(
						'key'        => 'field_rtb_showcase',
						'label'      => 'Showcase Items',
						'name'       => 'showcase_items',
						'type'       => 'repeater',
						'layout'     => 'row',
						'collapsed'  => 'field_rtb_si_name',
						'sub_fields' => array(
							array(
								'key'           => 'field_rtb_si_image',
								'label'         => 'Image',
								'name'          => 'image',
								'type'          => 'image',
								'return_format' => 'array',
							),
							array(
								'key'   => 'field_rtb_si_name',
								'label' => 'Name',
								'name'  => 'name',
								'type'  => 'text',
							),
							array(
								'key'   => 'field_rtb_si_tagline',
								'label' => 'Tagline',
								'name'  => 'tagline',
								'type'  => 'textarea',
								'rows'  => 2,
							),
							array(
								'key'   => 'field_rtb_si_price',
								'label' => 'Price',
								'name'  => 'price',
								'type'  => 'number',
							),
							array(
								'key'   => 'field_rtb_si_accent',
								'label' => 'Accent',
								'name'  => 'accent',
								'type'  => 'color_picker',
							),
							array(
								'key'           => 'field_rtb_si_gallery',
								'label'         => 'Gallery',
								'name'          => 'gallery',
								'type'          => 'gallery',
								'return_format' => 'array',
							),
							array(
								'key'           => 'field_rtb_si_link',
								'label'         => 'Link',
								'name'          => 'link',
								'type'          => 'link',
								'return_format' => 'array',
							),
							array(
								'key'        => 'field_rtb_si_meta',
								'label'      => 'Meta',
								'name'       => 'meta',
								'type'       => 'group',
								'layout'     => 'block',
								'sub_fields' => array(
									array(
										'key'   => 'field_rtb_si_meta_material',
										'label' => 'Material',
										'name'  => 'material',
										'type'  => 'text',
									),
								),
							),
							array(
								'key'        => 'field_rtb_si_specs',
								'label'      => 'Specs',
								'name'       => 'specs',
								'type'       => 'repeater',
								'layout'     => 'table',
								'sub_fields' => array(
									array(
										'key'   => 'field_rtb_spec_name',
										'label' => 'Spec',
										'name'  => 'spec_name',
										'type'  => 'text',
									),
									array(
										'key'   => 'field_rtb_spec_value',
										'label' => 'Value',
										'name'  => 'spec_value',
										'type'  => 'text',
									),
								),
							),
						),
					),
					array(
						'key'     => 'field_rtb_sections',
						'label'   => 'Page Sections',
						'name'    => 'page_sections',
						'type'    => 'flexible_content',
						'layouts' => array(
							// Both layouts define `heading` (union-dedupe in the picker); only
							// testimonial defines `author` (layout-aware fail-closed).
							'layout_rtb_hero'        => array(
								'key'        => 'layout_rtb_hero',
								'name'       => 'hero',
								'label'      => 'Hero',
								'display'    => 'block',
								'sub_fields' => array(
									array(
										'key'   => 'field_rtb_hero_heading',
										'label' => 'Heading',
										'name'  => 'heading',
										'type'  => 'text',
									),
									array(
										'key'           => 'field_rtb_hero_image',
										'label'         => 'Image',
										'name'          => 'image',
										'type'          => 'image',
										'return_format' => 'array',
									),
								),
							),
							'layout_rtb_testimonial' => array(
								'key'        => 'layout_rtb_testimonial',
								'name'       => 'testimonial',
								'label'      => 'Testimonial',
								'display'    => 'block',
								'sub_fields' => array(
									array(
										'key'   => 'field_rtb_testi_heading',
										'label' => 'Heading',
										'name'  => 'heading',
										'type'  => 'text',
									),
									array(
										'key'   => 'field_rtb_testi_author',
										'label' => 'Author',
										'name'  => 'author',
										'type'  => 'text',
									),
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

		acf_add_local_field_group(
			array(
				'key'      => 'group_rtb_options',
				'title'    => 'Site Notice (Demo)',
				'fields'   => array(
					array(
						'key'        => 'field_rtb_notice',
						'label'      => 'Site Notice',
						'name'       => 'site_notice',
						'type'       => 'repeater',
						'layout'     => 'row',
						'collapsed'  => 'field_rtb_notice_caption',
						'sub_fields' => array(
							array(
								'key'   => 'field_rtb_notice_caption',
								'label' => 'Caption',
								'name'  => 'caption',
								'type'  => 'text',
							),
							array(
								'key'   => 'field_rtb_notice_link',
								'label' => 'Link',
								'name'  => 'link',
								'type'  => 'url',
							),
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
	}
);

/**
 * Draws a label using GD's built-in bitmap font, scaled up.
 *
 * WordPress ships no usable TTF (dashicons.ttf is icon glyphs), so imagettftext() is out and
 * font 5 alone is 8x15px — illegible on a 1200x800 canvas. Rendering small then resampling up
 * is the standard workaround: soft edges, but readable.
 */
function rtb_draw_label( \GdImage $im, string $label, int $center_x, int $center_y, int $scale ): void {
	$w = imagefontwidth( 5 ) * strlen( $label );
	$h = imagefontheight( 5 );

	$layer = imagecreatetruecolor( $w, $h );
	imagesavealpha( $layer, true );
	imagefill( $layer, 0, 0, imagecolorallocatealpha( $layer, 0, 0, 0, 127 ) );
	imagestring( $layer, 5, 0, 0, $label, imagecolorallocate( $layer, 255, 255, 255 ) );

	imagealphablending( $im, true );
	imagecopyresampled(
		$im,
		$layer,
		(int) ( $center_x - ( $w * $scale ) / 2 ),
		(int) ( $center_y - ( $h * $scale ) / 2 ),
		0,
		0,
		(int) ( $w * $scale ),
		(int) ( $h * $scale ),
		$w,
		$h
	);

	imagedestroy( $layer );
}

/**
 * Generates a gradient placeholder and returns a real attachment ID (0 on failure).
 *
 * @param array<int, int> $from RGB triplet, top of the gradient.
 * @param array<int, int> $to   RGB triplet, bottom of the gradient.
 */
function rtb_make_image( string $label, array $from, array $to, string $filename ): int {
	$w  = 1200;
	$h  = 800;
	$im = imagecreatetruecolor( $w, $h );

	for ( $y = 0; $y < $h; $y++ ) {
		$t = $y / $h;

		imageline(
			$im,
			0,
			$y,
			$w,
			$y,
			imagecolorallocate(
				$im,
				(int) ( $from[0] + ( $to[0] - $from[0] ) * $t ),
				(int) ( $from[1] + ( $to[1] - $from[1] ) * $t ),
				(int) ( $from[2] + ( $to[2] - $from[2] ) * $t )
			)
		);
	}

	imagealphablending( $im, true );
	$veil = imagecolorallocatealpha( $im, 255, 255, 255, 108 );
	imagefilledellipse( $im, (int) ( $w * 0.82 ), (int) ( $h * 0.18 ), 340, 340, $veil );
	imagefilledellipse( $im, (int) ( $w * 0.12 ), (int) ( $h * 0.88 ), 220, 220, $veil );

	rtb_draw_label( $im, $label, $w / 2, $h / 2, 7 );

	ob_start();
	imagejpeg( $im, null, 88 );
	$bytes = ob_get_clean();
	imagedestroy( $im );

	$upload = wp_upload_bits( $filename, null, $bytes );

	if ( ! empty( $upload['error'] ) ) {
		return 0;
	}

	$attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/jpeg',
			'post_title'     => $label,
			'post_status'    => 'inherit',
		),
		$upload['file']
	);

	if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
		return 0;
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';
	wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );

	return (int) $attachment_id;
}

add_action(
	'admin_init',
	function () {
		if ( ! function_exists( 'update_field' ) || get_option( 'rtb_demo_seeded' ) ) {
			return;
		}

		if ( ! extension_loaded( 'gd' ) ) {
			return;
		}

		$palette = array(
			// Product shots say which row they belong to — the row-addressing story, in pixels.
			'p1'    => array( 'Row 1', array( 30, 41, 59 ), array( 99, 102, 241 ) ),
			'p2'    => array( 'Row 2', array( 76, 29, 149 ), array( 236, 72, 153 ) ),
			'p3'    => array( 'Row 3', array( 4, 47, 46 ), array( 20, 184, 166 ) ),
			'g1'    => array( 'Gallery 1', array( 30, 58, 138 ), array( 56, 189, 248 ) ),
			'g2'    => array( 'Gallery 2', array( 88, 28, 135 ), array( 168, 85, 247 ) ),
			'g3'    => array( 'Gallery 3', array( 120, 53, 15 ), array( 251, 146, 60 ) ),
			'g4'    => array( 'Gallery 4', array( 19, 78, 74 ), array( 45, 212, 191 ) ),
			'g5'    => array( 'Gallery 5', array( 127, 29, 29 ), array( 248, 113, 113 ) ),
			'hero1' => array( 'Hero A', array( 12, 74, 110 ), array( 125, 211, 252 ) ),
			'hero2' => array( 'Hero B', array( 63, 63, 70 ), array( 161, 161, 170 ) ),
		);

		$img = array();

		foreach ( $palette as $slug => $spec ) {
			$img[ $slug ] = rtb_make_image( $spec[0], $spec[1], $spec[2], 'rt-demo-' . $slug . '.jpg' );
		}

		$admins = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			)
		);

		$page_id = wp_insert_post(
			array(
				'import_id'   => RTB_DEMO_PAGE_ID,
				'post_title'  => 'Repeater Tags Demo',
				'post_name'   => 'repeater-tags-demo',
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_author' => $admins ? (int) $admins[0] : 1,
			)
		);

		if ( ! $page_id || is_wp_error( $page_id ) ) {
			return;
		}

		// Rows are written NAME-keyed: that's the shape update_field() expects when creating a
		// value outright (raw/KEY-keyed shapes are only needed to patch an existing row).
		update_field(
			'field_rtb_showcase',
			array(
				array(
					'image'   => $img['p1'],
					'name'    => 'Aurora Desk Lamp',
					'tagline' => 'Warm oak base, dimmable to candlelight.',
					'price'   => 148,
					'accent'  => '#6366f1',
					'gallery' => array( $img['g1'], $img['g2'], $img['g3'] ),
					'link'    => array(
						'title'  => 'View product',
						'url'    => 'https://example.com/aurora-desk-lamp',
						'target' => '',
					),
					'meta'    => array( 'material' => 'Solid white oak' ),
					'specs'   => array(
						array(
							'spec_name'  => 'Weight',
							'spec_value' => '1.2 kg',
						),
						array(
							'spec_name'  => 'Shade width',
							'spec_value' => '340 mm',
						),
						array(
							'spec_name'  => 'Bulb',
							'spec_value' => 'E27 · 6W LED',
						),
					),
				),
				array(
					'image'   => $img['p2'],
					'name'    => 'Canvas Weekender',
					'tagline' => 'Waxed cotton, leather trim, lifetime seams.',
					'price'   => 220,
					'accent'  => '#ec4899',
					'gallery' => array( $img['g3'], $img['g4'], $img['g5'] ),
					'link'    => array(
						'title'  => 'View product',
						'url'    => 'https://example.com/canvas-weekender',
						'target' => '',
					),
					'meta'    => array( 'material' => 'Waxed cotton canvas' ),
					'specs'   => array(
						array(
							'spec_name'  => 'Capacity',
							'spec_value' => '42 L',
						),
						array(
							'spec_name'  => 'Cabin legal',
							'spec_value' => 'Yes',
						),
					),
				),
				array(
					'image'   => $img['p3'],
					'name'    => 'Kobo Pour-Over Set',
					'tagline' => 'Hand-thrown stoneware, one cup at a time.',
					'price'   => 64,
					'accent'  => '#14b8a6',
					'gallery' => array( $img['g5'], $img['g1'], $img['g2'] ),
					'link'    => array(
						'title'  => 'View product',
						'url'    => 'https://example.com/kobo-pour-over',
						'target' => '',
					),
					'meta'    => array( 'material' => 'Glazed stoneware' ),
					// Deliberately empty: the child-tier fail-closed demo binds against this row.
					'specs'   => array(),
				),
			),
			$page_id
		);

		update_field(
			'field_rtb_sections',
			array(
				array(
					'acf_fc_layout' => 'hero',
					'heading'       => 'Built for the sites you already have',
					'image'         => $img['hero1'],
				),
				array(
					'acf_fc_layout' => 'testimonial',
					'heading'       => 'It reads the repeater I already built.',
					'author'        => 'Every ACF developer, eventually',
				),
				array(
					'acf_fc_layout' => 'hero',
					'heading'       => 'No custom widgets. No new data model.',
					'image'         => $img['hero2'],
				),
			),
			$page_id
		);

		update_field(
			'field_rtb_notice',
			array(
				array(
					'caption' => 'Free shipping on every order over $50',
					'link'    => 'https://example.com/shipping',
				),
			),
			'option'
		);

		$page_json = __DIR__ . '/assets/demo-page.json';

		// The blueprint writes this next to seed.php from dev/blueprint/demo-page.js. Copy
		// seed.php onto a dev site by itself and the file is simply absent: you still get the
		// ACF data, just an empty page to build on.
		if ( is_readable( $page_json ) ) {
			$data = file_get_contents( $page_json );

			if ( ! empty( trim( (string) $data ) ) ) {
				update_post_meta( $page_id, '_elementor_data', wp_slash( $data ) );
				update_post_meta( $page_id, '_elementor_edit_mode', 'builder' );
				update_post_meta( $page_id, '_elementor_template_type', 'wp-page' );
				update_post_meta( $page_id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.5.0' );

				// Raw meta writes leave Elementor's rendered-markup cache stale; only editor
				// saves invalidate it automatically.
				delete_post_meta( $page_id, '_elementor_element_cache' );
			}
		}

		update_option( 'rtb_demo_seeded', $page_id );
	},
	20
);
