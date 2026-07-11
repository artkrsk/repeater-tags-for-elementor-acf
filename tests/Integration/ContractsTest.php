<?php

namespace Arts\RepeaterTags\Tests\Integration;

use Arts\RepeaterTags\Conditions\RowCount;
use Arts\RepeaterTags\Controls\RowPicker;
use Arts\RepeaterTags\Managers\Ajax;
use Arts\RepeaterTags\Plugin;
use Arts\RepeaterTags\Services\LoopRepeat;
use Arts\RepeaterTags\Tags\RepeaterColor;
use Arts\RepeaterTags\Tags\RepeaterDate;
use Arts\RepeaterTags\Tags\RepeaterGallery;
use Arts\RepeaterTags\Tags\RepeaterMedia;
use Arts\RepeaterTags\Tags\RepeaterNumber;
use Arts\RepeaterTags\Tags\RepeaterText;
use Arts\RepeaterTags\Tags\RepeaterUrl;
use Elementor\Plugin as ElementorPlugin;
use ReflectionClass;

/**
 * Pins the frozen contracts. These are STORAGE identifiers — they live in saved
 * `_elementor_data` on every page a user has built, so renaming one silently unbinds their
 * work. Treat this suite like a schema migration guard: a red test here means the change is
 * breaking, not that the assertion is stale.
 */
class ContractsTest extends TagTestCase {

	/** @return array<string, class-string<\Elementor\Core\DynamicTags\Base_Tag>> */
	private function frozen_tag_names(): array {
		return array(
			'arts-repeater-text'    => RepeaterText::class,
			'arts-repeater-media'   => RepeaterMedia::class,
			'arts-repeater-url'     => RepeaterUrl::class,
			'arts-repeater-gallery' => RepeaterGallery::class,
			'arts-repeater-number'  => RepeaterNumber::class,
			'arts-repeater-color'   => RepeaterColor::class,
			'arts-repeater-date'    => RepeaterDate::class,
		);
	}

	public function test_frozen_tag_names_and_group(): void {
		foreach ( $this->frozen_tag_names() as $name => $tag_class ) {
			$tag = $this->make_tag( $tag_class, array() );

			$this->assertSame( $name, $tag->get_name() );
			$this->assertSame( 'arts-repeater-tags', $tag->get_group() );
		}
	}

	public function test_frozen_control_condition_and_setting_keys(): void {
		// The two constant assertions are tautological to static analysis — that is the point.
		// They fail the moment the constant is renamed, which is exactly the breaking change
		// this suite guards, and PHPStan flags the stale literal in the same breath.
		/* @phpstan-ignore method.alreadyNarrowedType (the literal IS the storage contract) */
		$this->assertSame( 'arts-repeater-tags-row-picker', RowPicker::TYPE );
		/* @phpstan-ignore method.alreadyNarrowedType (the literal IS the storage contract) */
		$this->assertSame( 'arts_repeater_tags_repeat_field', LoopRepeat::CONTROL_KEY );

		$this->assertSame( 'arts-repeater-tags-row-picker', ( new RowPicker() )->get_type() );
		$this->assertSame( 'arts-repeater-row-count', ( new RowCount() )->get_name() );
	}

	public function test_all_seven_tags_and_the_group_register_with_elementor(): void {
		$manager = ElementorPlugin::$instance->dynamic_tags;

		foreach ( $this->frozen_tag_names() as $name => $tag_class ) {
			$info = $manager->get_tag_info( $name );

			$this->assertIsArray( $info, $name . ' must be registered with Elementor' );
			$this->assertInstanceOf( $tag_class, $info['instance'] );
		}

		$config = $manager->get_config();

		$this->assertIsArray( $config );
		$this->assertIsArray( $config['groups'] );
		$this->assertArrayHasKey( 'arts-repeater-tags', $config['groups'] );
	}

	public function test_the_row_picker_control_registers_under_its_frozen_type(): void {
		// PHP's get_type() must EXACTLY match the JS addControlView() key. A mismatch fails
		// SILENTLY — Elementor falls back to its generic base control view, so the picker
		// renders inert rather than erroring.
		$this->assertInstanceOf(
			RowPicker::class,
			ElementorPlugin::$instance->controls_manager->get_control( RowPicker::TYPE )
		);
	}

	public function test_the_shipped_editor_bundle_uses_the_same_keys_as_php(): void {
		// The other half of that silent-failure pair, asserted against the ARTIFACT: the
		// bundle the plugin actually enqueues must speak the same control type and ajax
		// action the PHP side registers.
		$plugin_php = ( new ReflectionClass( Plugin::class ) )->getFileName();

		$this->assertIsString( $plugin_php );

		$bundle = dirname( $plugin_php ) . '/libraries/repeater-tags-for-elementor-acf/repeater-tags-for-elementor-acf.js';

		$this->assertFileExists( $bundle, 'the editor bundle ships with the plugin' );

		$source = file_get_contents( $bundle );

		$this->assertIsString( $source );
		$this->assertStringContainsString( RowPicker::TYPE, $source, 'the JS control view must register under the PHP control type' );
		$this->assertStringContainsString( 'arts_repeater_tags_get_rows', $source, 'the JS must call the registered ajax action' );
	}

	public function test_the_frozen_ajax_action_is_registered(): void {
		// A spy, not Elementor's ajax module: register_ajax_actions() declares no parameter
		// type, and all that matters here is the action NAME it hands over.
		$ajax = new class() {
			/** @var array<int, string> */
			public array $registered = array();

			public function register_ajax_action( string $tag, callable $callback ): void {
				$this->registered[] = $tag;
			}
		};

		/* @phpstan-ignore argument.type (spy stands in for Elementor's ajax module) */
		( new Ajax() )->register_ajax_actions( $ajax );

		$this->assertSame( array( 'arts_repeater_tags_get_rows' ), $ajax->registered );
	}
}
