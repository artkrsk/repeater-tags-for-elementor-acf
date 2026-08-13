<?php

namespace Arts\RepeaterTags\Tests\Integration;

use Arts\RepeaterTags\Tags\RepeaterColor;
use Arts\RepeaterTags\Tags\RepeaterDate;
use Arts\RepeaterTags\Tags\RepeaterGallery;
use Arts\RepeaterTags\Tags\RepeaterMedia;
use Arts\RepeaterTags\Tags\RepeaterNumber;
use Arts\RepeaterTags\Tags\RepeaterText;
use Arts\RepeaterTags\Tags\RepeaterUrl;

/**
 * Characterizes each tag's value normalization: the coercion from ACF's FORMATTED value —
 * whose shape depends on the sub-field's type AND its return_format — into what the bound
 * Elementor control expects. This is the layer where a wrong branch renders empty instead of
 * erroring, so every accepted return_format shape gets an assertion.
 */
class TagValuesTest extends TagTestCase {

	/**
	 * Seeds one row of a repeater on a page and makes that page the current post.
	 *
	 * @param array<string, mixed> $row
	 */
	private function seed_row( string $repeater_key, array $row ): void {
		$page_id = $this->create_post_id( array( 'post_type' => 'page' ) );

		update_field( $repeater_key, array( $row ), $page_id );

		$GLOBALS['post'] = get_post( $page_id );
	}

	/**
	 * @param class-string<\Elementor\Core\DynamicTags\Base_Tag> $tag_class
	 * @return mixed
	 */
	private function bound( string $tag_class, string $repeater_key, string $sub_path ) {
		return $this->tag_content(
			$tag_class,
			array(
				'repeater_field'            => $repeater_key,
				'row_index'                 => '0',
				'sub_field_' . $repeater_key => $sub_path,
			)
		);
	}

	// ---------------------------------------------------------------- Text

	public function test_text_renders_scalar_types_escaped(): void {
		$this->seed_row( 'field_rt_demo_items', array( 'caption' => 'Plain <b>caption</b>' ) );

		$this->assertSame( 'Plain &lt;b&gt;caption&lt;/b&gt;', $this->bound( RepeaterText::class, 'field_rt_demo_items', 'caption' ) );
	}

	public function test_text_renders_wysiwyg_through_kses_not_esc_html(): void {
		$this->seed_row(
			'field_rt_demo_items',
			array( 'details' => '<strong>Rich</strong><script>alert(1)</script>' )
		);

		$value = $this->bound( RepeaterText::class, 'field_rt_demo_items', 'details' );

		$this->assertIsString( $value );
		$this->assertStringContainsString( '<strong>Rich</strong>', $value, 'safe HTML survives — this is not esc_html' );
		$this->assertStringNotContainsString( '<script>', $value, 'wp_kses_post strips the script' );
	}

	public function test_text_renders_a_google_map_address(): void {
		update_field(
			'field_rt_global_items',
			array(
				array(
					'location' => array(
						'address' => '1 Test Street, Testville',
						'lat'     => '51.5',
						'lng'     => '-0.1',
					),
				),
			),
			'option'
		);

		$this->assertSame(
			'1 Test Street, Testville',
			$this->bound( RepeaterText::class, 'field_rt_global_items', 'location' )
		);
	}

	public function test_text_renders_an_unset_google_map_as_empty(): void {
		// An unset google_map formats to boolean false, not an array.
		update_field( 'field_rt_global_items', array( array( 'caption' => 'No map here' ) ), 'option' );

		$this->assertSame( '', $this->bound( RepeaterText::class, 'field_rt_global_items', 'location' ) );
	}

	public function test_text_renders_a_choice_field_by_label(): void {
		// select, return_format 'array' → {value, label}: the label wins.
		$this->seed_row( 'field_rt_demo_items', array( 'badge' => 'featured' ) );

		$this->assertSame( 'Featured', $this->bound( RepeaterText::class, 'field_rt_demo_items', 'badge' ) );
	}

	public function test_text_joins_a_multi_value_choice_field(): void {
		// checkbox, return_format 'label' → a list of label strings.
		update_field(
			'field_rt_global_items',
			array( array( 'perks' => array( 'returns', 'wrap' ) ) ),
			'option'
		);

		$this->assertSame(
			'Free returns, Gift wrap',
			$this->bound( RepeaterText::class, 'field_rt_global_items', 'perks' )
		);
	}

	public function test_text_renders_a_color_picker_string(): void {
		$this->seed_row( 'field_rt_demo_items', array( 'accent' => '#e63946' ) );

		$this->assertSame( '#e63946', $this->bound( RepeaterText::class, 'field_rt_demo_items', 'accent' ) );
	}

	// ---------------------------------------------------------------- Number

	public function test_number_renders_the_scalar(): void {
		$this->seed_row( 'field_rt_demo_items', array( 'count' => 42 ) );

		$this->assertSame( '42', $this->bound( RepeaterNumber::class, 'field_rt_demo_items', 'count' ) );
	}

	public function test_number_renders_an_empty_cell_as_empty(): void {
		$this->seed_row( 'field_rt_demo_items', array( 'caption' => 'no number' ) );

		$this->assertSame( '', $this->bound( RepeaterNumber::class, 'field_rt_demo_items', 'count' ) );
	}

	// ---------------------------------------------------------------- Color

	public function test_color_passes_a_hex_string_through(): void {
		$this->seed_row( 'field_rt_demo_items', array( 'accent' => '#457b9d' ) );

		$this->assertSame( '#457b9d', $this->bound( RepeaterColor::class, 'field_rt_demo_items', 'accent' ) );
	}

	public function test_color_normalizes_the_rgba_array_format(): void {
		$this->seed_row(
			'field_rt_formats_items',
			array( 'color_rgba' => 'rgba(230,57,70,0.5)' )
		);

		$this->assertSame(
			'rgba(230, 57, 70, 0.5)',
			$this->bound( RepeaterColor::class, 'field_rt_formats_items', 'color_rgba' ),
			'the RGBA array return format becomes a CSS rgba() string'
		);
	}

	public function test_color_renders_an_empty_cell_as_empty(): void {
		$this->seed_row( 'field_rt_demo_items', array( 'caption' => 'no color' ) );

		$this->assertSame( '', $this->bound( RepeaterColor::class, 'field_rt_demo_items', 'accent' ) );
	}

	// ---------------------------------------------------------------- Media

	public function test_media_handles_the_array_return_format(): void {
		$attachment_id = $this->create_attachment_id();

		$this->seed_row( 'field_rt_demo_items', array( 'image' => $attachment_id ) );

		$this->assertSame(
			array(
				'id'  => $attachment_id,
				'url' => wp_get_attachment_url( $attachment_id ),
			),
			$this->bound( RepeaterMedia::class, 'field_rt_demo_items', 'image' )
		);
	}

	public function test_media_handles_the_id_return_format(): void {
		$attachment_id = $this->create_attachment_id();

		$this->seed_row( 'field_rt_formats_items', array( 'image_id' => $attachment_id ) );

		$this->assertSame(
			array(
				'id'  => $attachment_id,
				'url' => wp_get_attachment_url( $attachment_id ),
			),
			$this->bound( RepeaterMedia::class, 'field_rt_formats_items', 'image_id' ),
			'an id-format image resolves its own URL'
		);
	}

	public function test_media_handles_the_url_return_format(): void {
		$attachment_id = $this->create_attachment_id();

		$this->seed_row( 'field_rt_formats_items', array( 'image_url' => $attachment_id ) );

		$this->assertSame(
			array(
				'id'  => 0,
				'url' => wp_get_attachment_url( $attachment_id ),
			),
			$this->bound( RepeaterMedia::class, 'field_rt_formats_items', 'image_url' ),
			'a url-format image has no id to report'
		);
	}

	public function test_media_renders_an_empty_cell_as_the_empty_shape(): void {
		$this->seed_row( 'field_rt_demo_items', array( 'caption' => 'no image' ) );

		$this->assertSame(
			array(
				'id'  => 0,
				'url' => '',
			),
			$this->bound( RepeaterMedia::class, 'field_rt_demo_items', 'image' )
		);
	}

	// ---------------------------------------------------------------- Gallery

	public function test_gallery_handles_the_array_return_format(): void {
		$first  = $this->create_attachment_id( 'one.jpg' );
		$second = $this->create_attachment_id( 'two.jpg' );

		$this->seed_row( 'field_rt_demo_items', array( 'photos' => array( $first, $second ) ) );

		$this->assertSame(
			array(
				array(
					'id'  => $first,
					'url' => wp_get_attachment_url( $first ),
				),
				array(
					'id'  => $second,
					'url' => wp_get_attachment_url( $second ),
				),
			),
			$this->bound( RepeaterGallery::class, 'field_rt_demo_items', 'photos' )
		);
	}

	public function test_gallery_handles_the_id_return_format(): void {
		$first  = $this->create_attachment_id( 'one.jpg' );
		$second = $this->create_attachment_id( 'two.jpg' );

		$this->seed_row( 'field_rt_formats_items', array( 'gallery_ids' => array( $first, $second ) ) );

		// An id-format gallery formats to a list of INTS. The GALLERY control contracts on
		// 'id', so this shape carries everything a consumer needs — it must not drop out.
		$this->assertSame(
			array(
				array(
					'id'  => $first,
					'url' => wp_get_attachment_url( $first ),
				),
				array(
					'id'  => $second,
					'url' => wp_get_attachment_url( $second ),
				),
			),
			$this->bound( RepeaterGallery::class, 'field_rt_formats_items', 'gallery_ids' )
		);
	}

	public function test_gallery_handles_the_url_return_format(): void {
		$first = $this->create_attachment_id( 'one.jpg' );

		$this->seed_row( 'field_rt_formats_items', array( 'gallery_urls' => array( $first ) ) );

		// A url-format gallery formats to a list of URL STRINGS — no id available.
		$this->assertSame(
			array(
				array(
					'id'  => 0,
					'url' => wp_get_attachment_url( $first ),
				),
			),
			$this->bound( RepeaterGallery::class, 'field_rt_formats_items', 'gallery_urls' )
		);
	}

	public function test_gallery_renders_an_empty_cell_as_an_empty_list(): void {
		$this->seed_row( 'field_rt_demo_items', array( 'caption' => 'no gallery' ) );

		$this->assertSame( array(), $this->bound( RepeaterGallery::class, 'field_rt_demo_items', 'photos' ) );
	}

	// ---------------------------------------------------------------- Date

	public function test_date_emits_machine_readable_output_from_the_fields_own_format(): void {
		// The fixture field sets no return_format, so ACF's date_time_picker default applies.
		// The tag re-parses ACF's formatted value with that same format and always emits
		// 'Y-m-d H:i:s' — what Pro's Countdown expects.
		$this->seed_row( 'field_rt_demo_items', array( 'starts_at' => '2026-07-12 15:30:00' ) );

		$this->assertSame(
			'2026-07-12 15:30:00',
			$this->bound( RepeaterDate::class, 'field_rt_demo_items', 'starts_at' )
		);
	}

	public function test_date_round_trips_an_explicit_return_format(): void {
		$this->seed_row( 'field_rt_formats_items', array( 'dt_ymd' => '2026-07-12 15:30:45' ) );

		$this->assertSame(
			'2026-07-12 15:30:45',
			$this->bound( RepeaterDate::class, 'field_rt_formats_items', 'dt_ymd' )
		);
	}

	public function test_date_renders_an_empty_cell_as_empty(): void {
		$this->seed_row( 'field_rt_demo_items', array( 'caption' => 'no date' ) );

		$this->assertSame( '', $this->bound( RepeaterDate::class, 'field_rt_demo_items', 'starts_at' ) );
	}

	// ---------------------------------------------------------------- Url

	public function test_url_returns_a_plain_url_string(): void {
		$this->seed_row( 'field_rt_demo_items', array( 'website' => 'https://example.com/page' ) );

		$this->assertSame( 'https://example.com/page', $this->bound( RepeaterUrl::class, 'field_rt_demo_items', 'website' ) );
	}

	public function test_url_unwraps_a_link_array(): void {
		$this->seed_row(
			'field_rt_demo_items',
			array(
				'cta' => array(
					'title'  => 'Read more',
					'url'    => 'https://example.com/cta',
					'target' => '_blank',
				),
			)
		);

		$this->assertSame( 'https://example.com/cta', $this->bound( RepeaterUrl::class, 'field_rt_demo_items', 'cta' ) );
	}

	public function test_url_resolves_a_post_object_to_its_permalink(): void {
		$book_id = $this->create_post_id( array( 'post_type' => 'rt_book' ) );

		$this->seed_row( 'field_rt_demo_items', array( 'related_book' => $book_id ) );

		$this->assertSame(
			get_permalink( $book_id ),
			$this->bound( RepeaterUrl::class, 'field_rt_demo_items', 'related_book' ),
			'return_format object → a WP_Post'
		);
	}

	public function test_url_resolves_a_relationship_to_the_first_items_permalink(): void {
		$first  = $this->create_post_id();
		$second = $this->create_post_id();

		$this->seed_row( 'field_rt_types_items', array( 'relationship' => array( $first, $second ) ) );

		$this->assertSame(
			get_permalink( $first ),
			$this->bound( RepeaterUrl::class, 'field_rt_types_items', 'relationship' ),
			'multi-value relational fields take the first item, in ACF\'s stored order'
		);
	}

	public function test_url_resolves_a_taxonomy_term_to_its_archive(): void {
		$term_id = $this->create_category_id();

		$this->seed_row( 'field_rt_types_items', array( 'taxonomy' => array( $term_id ) ) );

		$this->assertSame(
			get_term_link( $term_id ),
			$this->bound( RepeaterUrl::class, 'field_rt_types_items', 'taxonomy' )
		);
	}

	public function test_url_drops_a_stale_taxonomy_term(): void {
		$term_id = $this->create_category_id();

		$this->seed_row( 'field_rt_types_items', array( 'taxonomy' => array( $term_id ) ) );

		// A deleted term id survives in the 'id' return format; get_term_link() then hands
		// back a WP_Error, which must never reach the href.
		wp_delete_term( $term_id, 'category' );

		$this->assertSame( '', $this->bound( RepeaterUrl::class, 'field_rt_types_items', 'taxonomy' ) );
	}

	public function test_url_resolves_a_user_to_the_author_archive(): void {
		$user_id = $this->create_user_id( array( 'role' => 'author' ) );

		$this->seed_row( 'field_rt_types_items', array( 'user' => $user_id ) );

		$this->assertSame(
			get_author_posts_url( $user_id ),
			$this->bound( RepeaterUrl::class, 'field_rt_types_items', 'user' ),
			'return_format array → an associative shape with an uppercase ID key'
		);
	}

	public function test_url_resolves_a_user_in_the_id_and_object_return_formats(): void {
		$user_id = $this->create_user_id( array( 'role' => 'author' ) );

		$this->seed_row(
			'field_rt_formats_items',
			array(
				'user_id'  => $user_id,
				'user_obj' => $user_id,
			)
		);

		$this->assertSame( get_author_posts_url( $user_id ), $this->bound( RepeaterUrl::class, 'field_rt_formats_items', 'user_id' ) );
		$this->assertSame( get_author_posts_url( $user_id ), $this->bound( RepeaterUrl::class, 'field_rt_formats_items', 'user_obj' ) );
	}

	public function test_url_takes_the_first_of_a_multiple_page_link(): void {
		$first  = $this->create_post_id( array( 'post_type' => 'page' ) );
		$second = $this->create_post_id( array( 'post_type' => 'page' ) );

		$this->seed_row(
			'field_rt_formats_items',
			array( 'page_link_multi' => array( get_permalink( $first ), get_permalink( $second ) ) )
		);

		$this->assertSame(
			get_permalink( $first ),
			$this->bound( RepeaterUrl::class, 'field_rt_formats_items', 'page_link_multi' )
		);
	}

	public function test_url_resolves_a_file_in_the_id_return_format(): void {
		$attachment_id = $this->create_attachment_id( 'doc.pdf', 'application/pdf' );

		$this->seed_row( 'field_rt_formats_items', array( 'file_id' => $attachment_id ) );

		$this->assertSame(
			wp_get_attachment_url( $attachment_id ),
			$this->bound( RepeaterUrl::class, 'field_rt_formats_items', 'file_id' )
		);
	}

	public function test_url_renders_an_empty_cell_as_empty(): void {
		$this->seed_row( 'field_rt_demo_items', array( 'caption' => 'no url' ) );

		$this->assertSame( '', $this->bound( RepeaterUrl::class, 'field_rt_demo_items', 'website' ) );
		$this->assertSame( '', $this->bound( RepeaterUrl::class, 'field_rt_demo_items', 'cta' ) );
	}
}
