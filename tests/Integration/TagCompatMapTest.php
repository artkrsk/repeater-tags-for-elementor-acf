<?php

namespace Arts\RepeaterTags\Tests\Integration;

use Arts\RepeaterTags\Tags\RepeaterColor;
use Arts\RepeaterTags\Tags\RepeaterDate;
use Arts\RepeaterTags\Tags\RepeaterGallery;
use Arts\RepeaterTags\Tags\RepeaterMedia;
use Arts\RepeaterTags\Tags\RepeaterNumber;
use Arts\RepeaterTags\Tags\RepeaterText;
use Arts\RepeaterTags\Tags\RepeaterUrl;
use Elementor\Core\DynamicTags\Base_Tag;
use Elementor\Core\Frontend\Performance;
use Elementor\Plugin as ElementorPlugin;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Enforces the compat-map promise stated on BaseRepeaterTag::get_accepted_sub_field_types():
 * a type is listed ONLY when this tag reliably renders its formatted value, because a
 * pickable-but-empty-rendering option reads as breakage to the user.
 *
 * The test drives itself off each tag's own accepted list against the 'RT Type Matrix'
 * fixture, whose sub-fields are NAMED AFTER THEIR ACF TYPE. So for every accepted type it
 * proves both halves at once: the type is OFFERED in that tag's Sub-field select, and a bound
 * row RENDERS non-empty. Adding a type to a tag's map without a fixture fails here loudly —
 * the map and the fixtures cannot drift apart.
 */
class TagCompatMapTest extends TagTestCase {

	private const MATRIX = 'field_rt_types_items';

	/**
	 * Reading a control's `options` needs an ADMIN request. Elementor's
	 * Controls_Stack::add_control() unsets the UI-only args (label, options, placeholder…)
	 * whenever Performance::should_optimize_controls() is true — i.e. on any request that is
	 * not admin/preview/REST — because the frontend only needs type/default/condition to
	 * resolve a value. PHPUnit is such a request, so without this the Sub-field select's
	 * options simply do not exist. The editor, where the picker actually lives, is admin.
	 *
	 * Two statics have to move together: Performance caches its frontend verdict, and
	 * Controls_Manager caches each tag's control stack per PHP process (keyed by
	 * 'tag-' . get_name()), so a stack another suite already built in frontend mode would be
	 * served back with the args stripped.
	 */
	public function set_up(): void {
		parent::set_up();

		set_current_screen( 'edit-post' );

		$this->reset_control_arg_optimization();
	}

	public function tear_down(): void {
		set_current_screen( 'front' );

		$this->reset_control_arg_optimization();

		parent::tear_down();
	}

	private function reset_control_arg_optimization(): void {
		$is_frontend = new ReflectionProperty( Performance::class, 'is_frontend' );
		$is_frontend->setAccessible( true );
		$is_frontend->setValue( null, null );

		foreach ( $this->tag_classes() as $tag_class ) {
			ElementorPlugin::$instance->controls_manager->delete_stack( $this->make_tag( $tag_class, array() ) );
		}
	}

	/** @return array<int, class-string<Base_Tag>> */
	private function tag_classes(): array {
		return array(
			RepeaterText::class,
			RepeaterMedia::class,
			RepeaterUrl::class,
			RepeaterGallery::class,
			RepeaterNumber::class,
			RepeaterColor::class,
			RepeaterDate::class,
		);
	}

	/**
	 * @param class-string<Base_Tag> $tag_class
	 * @return array<int, string>
	 */
	private function accepted_types( string $tag_class ): array {
		$method = new ReflectionMethod( $tag_class, 'get_accepted_sub_field_types' );
		$method->setAccessible( true );

		$types = $method->invoke( $this->make_tag( $tag_class, array() ) );

		$this->assertIsArray( $types );

		/** @var array<int, string> $types */
		return $types;
	}

	/**
	 * The Sub-field select's offered options for one repeater on one tag.
	 *
	 * @param class-string<Base_Tag> $tag_class
	 * @return array<string, string> Path ⇒ label.
	 */
	private function sub_field_options( string $tag_class, string $repeater_key ): array {
		$control = $this->make_tag( $tag_class, array() )->get_controls( 'sub_field_' . $repeater_key );

		$this->assertIsArray( $control );
		$this->assertArrayHasKey( 'options', $control, 'control args are only retained on admin requests — see set_up()' );
		$this->assertIsArray( $control['options'] );

		/** @var array<string, string> $options */
		$options = $control['options'];

		return $options;
	}

	/**
	 * One row carrying a representative value for every type any tag accepts. Keyed by ACF
	 * TYPE, which is also the fixture sub-field name — that is what makes the sweep below
	 * table-driven rather than a hand-written case per tag/type pair.
	 *
	 * @return array<string, mixed>
	 */
	private function representative_row(): array {
		$page_id = $this->create_post_id( array( 'post_type' => 'page' ) );

		return array(
			'text'             => 'Some text',
			'textarea'         => "Line one\nLine two",
			'email'            => 'someone@example.com',
			'url'              => 'https://example.com/plain',
			'number'           => 42,
			'range'            => 7,
			'date_picker'      => '2026-07-12',
			'date_time_picker' => '2026-07-12 15:30:00',
			'time_picker'      => '15:30:00',
			'color_picker'     => '#e63946',
			'wysiwyg'          => '<strong>Rich</strong> body',
			'select'           => 'one',
			'checkbox'         => array( 'one', 'two' ),
			'radio'            => 'one',
			'button_group'     => 'two',
			'google_map'       => array(
				'address' => '1 Test Street, Testville',
				'lat'     => '51.5',
				'lng'     => '-0.1',
			),
			'image'            => $this->create_attachment_id( 'compat-image.jpg' ),
			'file'             => $this->create_attachment_id( 'compat-file.pdf', 'application/pdf' ),
			'gallery'          => array( $this->create_attachment_id( 'compat-gallery.jpg' ) ),
			'link'             => array(
				'title'  => 'Linked',
				'url'    => 'https://example.com/link',
				'target' => '',
			),
			'page_link'        => get_permalink( $page_id ),
			'post_object'      => $this->create_post_id(),
			'relationship'     => array( $this->create_post_id() ),
			'taxonomy'         => array( $this->create_category_id() ),
			'user'             => $this->create_user_id( array( 'role' => 'author' ) ),
		);
	}

	/** Nothing a consumer could use: an empty string, an empty list, or a zeroed media shape. */
	private function is_empty_output( mixed $value ): bool {
		if ( is_string( $value ) ) {
			return '' === $value;
		}

		if ( ! is_array( $value ) || array() === $value ) {
			return true;
		}

		if ( array_key_exists( 'id', $value ) && array_key_exists( 'url', $value ) ) {
			return 0 === $value['id'] && '' === $value['url'];
		}

		return false;
	}

	public function test_every_accepted_type_is_offered_and_renders(): void {
		$row = $this->representative_row();

		$page_id = $this->create_post_id( array( 'post_type' => 'page' ) );

		update_field( self::MATRIX, array( $row ), $page_id );

		$GLOBALS['post'] = get_post( $page_id );

		$checked = 0;

		foreach ( $this->tag_classes() as $tag_class ) {
			foreach ( $this->accepted_types( $tag_class ) as $type ) {
				$this->assertArrayHasKey(
					$type,
					$row,
					$tag_class . ' accepts "' . $type . '" but the RT Type Matrix fixture has no sub-field for it — add one, or the promise is untested'
				);

				// Half one: the type is PICKABLE — its path is an option on this tag's
				// Sub-field select for the matrix repeater.
				$this->assertArrayHasKey(
					$type,
					$this->sub_field_options( $tag_class, self::MATRIX ),
					$tag_class . ' accepts "' . $type . '" but does not offer it in the Sub-field select'
				);

				// Half two: a bound row RENDERS. An offered option that outputs nothing is
				// exactly the breakage the compat map exists to prevent.
				$value = $this->tag_content(
					$tag_class,
					array(
						'repeater_field'          => self::MATRIX,
						'row_index'               => '0',
						'sub_field_' . self::MATRIX => $type,
					)
				);

				$this->assertFalse(
					$this->is_empty_output( $value ),
					$tag_class . ' offers "' . $type . '" but renders nothing for it — a pickable dead-end'
				);

				++$checked;
			}
		}

		// Guards the sweep itself: a refactor that emptied the accepted lists would otherwise
		// pass this test vacuously.
		$this->assertGreaterThanOrEqual( 25, $checked );
	}

	public function test_incompatible_types_are_not_offered(): void {
		// The converse of the promise: the Color tag's select carries color_picker and the
		// placeholder, and nothing else — no picking a gallery on a color control.
		$this->assertSame(
			array( '', 'color_picker' ),
			array_keys( $this->sub_field_options( RepeaterColor::class, self::MATRIX ) )
		);
	}

	public function test_a_repeater_with_no_compatible_sub_fields_says_so(): void {
		// field_rt_book_specs holds a single text sub-field: nothing a Media tag can render.
		$this->assertSame(
			array( '' => 'No compatible sub-fields' ),
			$this->sub_field_options( RepeaterMedia::class, 'field_rt_book_specs' )
		);
	}
}
