<?php

namespace Arts\RepeaterTags\Tests\Integration;

use Arts\RepeaterTags\Plugin;
use Arts\RepeaterTags\Services\Rows;
use ReflectionClass;

/**
 * Harness acceptance: proves the suite runs against the BUILT artifact with the
 * full provider stack, and that one repeater read works end-to-end through the
 * Plugin singleton accessors (the seam every real caller uses).
 */
class SmokeTest extends TestCase {

	/**
	 * Two classes on purpose: the repo-root autoloader ALSO maps this namespace
	 * (to src/php), so both the classmap and PSR-4 paths must prove they lost to
	 * the built plugin's prepended autoloader.
	 */
	public function test_plugin_classes_resolve_from_built_artifact(): void {
		foreach ( array( Plugin::class, Rows::class ) as $class ) {
			$file = ( new ReflectionClass( $class ) )->getFileName();

			$this->assertIsString( $file );
			$this->assertStringContainsString(
				'/wp-content/plugins/repeater-tags-for-elementor-acf/',
				$file,
				$class . ' must load from the dist mount, not the repo source tree'
			);
		}
	}

	public function test_provider_stack_present(): void {
		$this->assertTrue( function_exists( 'acf_get_field_groups' ), 'SCF must provide the ACF API' );
		$this->assertTrue( class_exists( \Elementor\Plugin::class ), 'Elementor free must be loaded' );
		$this->assertTrue( defined( 'ELEMENTOR_PRO_VERSION' ), 'PRO Elements must be loaded' );
		$this->assertIsArray( acf_get_field_group( 'group_rt_demo' ), 'fixture field groups must be registered' );
	}

	public function test_end_to_end_rows_read_via_singleton_accessors(): void {
		$page_id = $this->create_post_id( array( 'post_type' => 'page' ) );

		update_field(
			'field_rt_demo_items',
			array(
				array(
					'caption' => 'One',
					'blurb'   => 'First blurb',
				),
				array(
					'caption' => 'Two',
					'blurb'   => 'Second blurb',
				),
			),
			$page_id
		);

		$rows = Plugin::instance()->rows()->get( 'field_rt_demo_items', $page_id );

		$this->assertCount( 2, $rows );
		$this->assertSame( 'One', $rows[0]['caption'] );
		$this->assertSame( 'Second blurb', $rows[1]['blurb'] );
	}
}
