<?php

namespace Arts\RepeaterTags\Tests\NoAcf;

use Arts\RepeaterTags\Controls\RowPicker;
use Arts\RepeaterTags\Managers\Ajax;
use Arts\RepeaterTags\Plugin;
use Arts\RepeaterTags\Services\Context;
use Arts\RepeaterTags\Services\Schema;
use Arts\RepeaterTags\Tags\RepeaterText;
use Elementor\Core\Frontend\Performance;
use Elementor\Plugin as ElementorPlugin;
use ReflectionProperty;
use WP_UnitTestCase;

/**
 * Runs via phpunit-no-acf.xml.dist: Elementor and PRO Elements ARE loaded, the ACF provider
 * is NOT. This is the one dependency combo `Requires Plugins` cannot express — the header ANDs
 * slug-exact entries and the provider ships as either ACF Pro or the SCF fork — so it is the
 * combo Schema's soft-check exists for, and the likeliest wp.org support ticket.
 *
 * The sibling no-provider suite loads NEITHER dependency, which means it must avoid Context
 * entirely (that service dereferences \Elementor\Plugin::$instance). Here Elementor is present,
 * so every Elementor-facing seam — tag registration, the row-picker control, the ajax endpoint,
 * the context ladder — actually runs, with nothing behind it. All of it must degrade, not fatal.
 */
class NoAcfTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		// The Sub-field/Repeater selects' options only survive on admin requests — Elementor
		// strips UI-only control args on the frontend (Performance::should_optimize_controls),
		// and it caches both that verdict and the per-tag control stack for the whole process.
		set_current_screen( 'edit-post' );

		$is_frontend = new ReflectionProperty( Performance::class, 'is_frontend' );
		$is_frontend->setAccessible( true );
		$is_frontend->setValue( null, null );

		ElementorPlugin::$instance->controls_manager->delete_stack( $this->make_text_tag() );
	}

	private function make_text_tag(): RepeaterText {
		return new RepeaterText(
			array(
				'id'       => 'rt-no-acf',
				'settings' => array(),
			)
		);
	}

	/** The WP factories are typed int|WP_Error; this suite has no base class to lean on. */
	private function create_post_id(): int {
		$post_id = self::factory()->post->create();

		self::assertIsInt( $post_id );

		return $post_id;
	}

	private function create_admin_id(): int {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		self::assertIsInt( $user_id );

		return $user_id;
	}

	public function test_premise_elementor_is_loaded_but_acf_is_not(): void {
		$this->assertTrue( class_exists( \Elementor\Plugin::class ), 'Elementor must be loaded' );
		$this->assertTrue( defined( 'ELEMENTOR_PRO_VERSION' ), 'PRO Elements must be loaded' );
		$this->assertFalse( function_exists( 'acf_get_field_groups' ), 'the ACF provider must be absent' );
	}

	public function test_schema_fails_closed_to_an_empty_enumeration(): void {
		$schema = new Schema();

		$this->assertSame( array(), $schema->get_repeaters() );
		$this->assertSame( array(), $schema->get_repeater_options() );
		$this->assertNull( $schema->get_entry( 'field_rt_demo_items' ) );
		$this->assertFalse( $schema->is_known_repeater( 'field_rt_demo_items' ) );
	}

	public function test_the_tags_still_register_and_say_there_is_nothing_to_pick(): void {
		$manager = ElementorPlugin::$instance->dynamic_tags;

		foreach ( array( 'arts-repeater-text', 'arts-repeater-media', 'arts-repeater-url', 'arts-repeater-gallery', 'arts-repeater-number', 'arts-repeater-color', 'arts-repeater-date' ) as $name ) {
			$this->assertIsArray( $manager->get_tag_info( $name ), $name . ' must still register without a provider' );
		}

		$control = $this->make_text_tag()->get_controls( 'repeater_field' );

		$this->assertIsArray( $control );
		$this->assertSame(
			array( '' => 'No ACF repeater fields found' ),
			$control['options'],
			'the picker degrades to an honest empty state rather than an empty select'
		);
	}

	public function test_the_row_picker_control_still_registers(): void {
		$this->assertInstanceOf(
			RowPicker::class,
			ElementorPlugin::$instance->controls_manager->get_control( RowPicker::TYPE )
		);
	}

	public function test_the_ajax_endpoint_enumerates_nothing(): void {
		wp_set_current_user( $this->create_admin_id() );

		// Every key is unknown to an empty schema, so the whitelist rejects the request before
		// any ACF call site is reachable.
		$this->assertSame(
			array( 'options' => array() ),
			( new Ajax() )->handle_get_rows(
				array(
					'repeater_key' => 'field_rt_demo_items',
					'post_id'      => $this->create_post_id(),
				)
			)
		);
	}

	public function test_the_context_ladder_runs(): void {
		$post_id = $this->create_post_id();

		$GLOBALS['post'] = get_post( $post_id );

		// Rung 1 dereferences \Elementor\Plugin::$instance->documents — present here, which is
		// exactly why the no-provider suite cannot cover this.
		$this->assertSame( $post_id, ( new Context() )->resolve_post_id( 'field_rt_demo_items' ) );
	}

	public function test_a_tag_renders_empty_rather_than_fataling(): void {
		$GLOBALS['post'] = get_post( $this->create_post_id() );

		$tag = new RepeaterText(
			array(
				'id'       => 'rt-no-acf-render',
				'settings' => array(
					'repeater_field'                => 'field_rt_demo_items',
					'row_index'                     => '0',
					'sub_field_field_rt_demo_items' => 'caption',
				),
			)
		);

		$this->assertSame( '', $tag->get_content(), 'a page built while ACF was active must not fatal after it is deactivated' );
	}

	public function test_the_rows_service_still_honors_the_dev_filter(): void {
		add_filter(
			'arts_repeater_tags/rows',
			static fn (): array => array( array( 'caption' => 'From the filter' ) )
		);

		$GLOBALS['post'] = get_post( $this->create_post_id() );

		$rows = new ReflectionProperty( Plugin::class, 'rows' );
		$rows->setAccessible( true );
		$rows->setValue( Plugin::instance(), null );

		$tag = new RepeaterText(
			array(
				'id'       => 'rt-no-acf-filter',
				'settings' => array(
					'repeater_field'                => 'field_rt_demo_items',
					'row_index'                     => '0',
					'sub_field_field_rt_demo_items' => 'caption',
				),
			)
		);

		// The dev API is provider-independent by design: rows can come from anywhere.
		$this->assertSame( 'From the filter', $tag->get_content() );
	}
}
