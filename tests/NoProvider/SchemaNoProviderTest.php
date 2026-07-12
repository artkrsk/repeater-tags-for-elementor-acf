<?php

namespace Arts\RepeaterTags\Tests\NoProvider;

use Arts\RepeaterTags\Plugin;
use Arts\RepeaterTags\Services\Rows;
use Arts\RepeaterTags\Services\Schema;
use ReflectionClass;
use WP_UnitTestCase;

/**
 * Runs via phpunit-no-provider.xml.dist: the bootstrap loads ONLY this plugin —
 * no Elementor, no SCF. Proves the wp.org support-ticket scenario: the plugin
 * boots inert and Schema fails closed to an empty enumeration.
 *
 * Never call Services\Context here — its loop-item rung dereferences
 * \Elementor\Plugin::$instance and fatals without Elementor.
 */
class SchemaNoProviderTest extends WP_UnitTestCase {

	public function test_premise_neither_dependency_is_loaded(): void {
		$this->assertFalse( function_exists( 'acf_get_field_groups' ) );
		$this->assertFalse( class_exists( '\Elementor\Plugin' ) );

		// The plugin still booted from the built artifact, without a fatal.
		$file = ( new ReflectionClass( Plugin::class ) )->getFileName();

		$this->assertIsString( $file );
		$this->assertStringContainsString( '/wp-content/plugins/repeater-tags-for-elementor-acf/', $file );
	}

	public function test_schema_fails_closed_to_empty_enumeration(): void {
		$schema = new Schema();

		$this->assertSame( array(), $schema->get_repeaters() );
		$this->assertSame( array(), $schema->get_repeaters(), 'the empty result memoizes without error' );
		$this->assertSame( array(), $schema->get_repeater_options() );
		$this->assertNull( $schema->get_entry( 'field_rt_demo_items' ) );
		$this->assertFalse( $schema->is_known_repeater( 'field_rt_demo_items' ) );
	}

	public function test_rows_fail_closed_but_the_dev_filter_contract_holds(): void {
		$captured = array();

		add_filter(
			'arts_repeater_tags/rows',
			static function ( $rows, $field_path, $post_id ) use ( &$captured ) {
				$captured[] = array( $rows, $field_path, $post_id );

				return $rows;
			},
			10,
			3
		);

		$this->assertSame( array(), ( new Rows() )->get( 'field_rt_demo_items', 123 ) );
		$this->assertSame( array( array( array(), '', 123 ) ), $captured );
	}

	public function test_dev_filter_synthesizes_rows_without_any_provider(): void {
		add_filter(
			'arts_repeater_tags/rows',
			static function () {
				return array( array( 'caption' => 'no-provider synth' ) );
			}
		);

		$this->assertSame(
			array( array( 'caption' => 'no-provider synth' ) ),
			( new Rows() )->get( 'field_rt_demo_items', 123 )
		);
	}
}
