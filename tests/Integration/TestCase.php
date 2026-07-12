<?php

namespace Arts\RepeaterTags\Tests\Integration;

use Arts\RepeaterTags\Plugin;
use ReflectionProperty;
use WP_UnitTestCase;

/**
 * Base for the integration suites. The DB rolls back between tests, but two
 * in-process caches do not:
 * - ACF's value store — reused context ids ('options', 'term_*', 'user_*')
 *   would serve a previous test's rows;
 * - the Plugin singleton's Rows handle — callers under test (RowCount,
 *   LoopRepeat) read through it, and its memo has no reset by design.
 */
abstract class TestCase extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		if ( function_exists( 'acf_get_store' ) ) {
			acf_get_store( 'values' )->reset();
		}

		$rows = new ReflectionProperty( Plugin::class, 'rows' );
		$rows->setAccessible( true );
		$rows->setValue( Plugin::instance(), null );
	}

	/** @param array<string, mixed> $args */
	protected function create_post_id( array $args = array() ): int {
		$post_id = self::factory()->post->create( $args );

		self::assertIsInt( $post_id );

		return $post_id;
	}

	protected function create_category_id(): int {
		$term_id = self::factory()->category->create();

		self::assertIsInt( $term_id );

		return $term_id;
	}

	/** @param array<string, mixed> $args */
	protected function create_user_id( array $args = array() ): int {
		$user_id = self::factory()->user->create( $args );

		self::assertIsInt( $user_id );

		return $user_id;
	}
}
