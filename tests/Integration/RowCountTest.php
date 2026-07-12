<?php

namespace Arts\RepeaterTags\Tests\Integration;

use Arts\RepeaterTags\Conditions\RowCount;
use ElementorPro\Modules\DisplayConditions\Classes\Comparator_Provider;

/**
 * Characterizes the Pro Display Condition's check() logic (frozen contract:
 * name arts-repeater-row-count, keys repeater_field / comparator / rows_number).
 * PRO Elements supplies the ElementorPro base classes the condition extends.
 * get_options() is editor plumbing and stays out of scope.
 */
class RowCountTest extends TestCase {

	private RowCount $condition;

	public function set_up(): void {
		parent::set_up();

		$this->condition = new RowCount();
	}

	/** Seeds a page with N demo rows and makes it the global post. */
	private function make_current_page_with_rows( int $count ): int {
		$page_id = $this->create_post_id( array( 'post_type' => 'page' ) );

		if ( $count > 0 ) {
			update_field(
				'field_rt_demo_items',
				array_map(
					static fn ( int $i ): array => array( 'caption' => 'Row ' . $i ),
					range( 1, $count )
				),
				$page_id
			);
		}

		$GLOBALS['post'] = get_post( $page_id );

		return $page_id;
	}

	public function test_frozen_condition_name(): void {
		$this->assertSame( 'arts-repeater-row-count', $this->condition->get_name() );
	}

	public function test_unconfigured_condition_never_hides_content(): void {
		$this->make_current_page_with_rows( 3 );

		$this->assertTrue( $this->condition->check( 'not-an-array' ) );
		$this->assertTrue( $this->condition->check( array() ) );
		$this->assertTrue( $this->condition->check( array( 'repeater_field' => '' ) ) );
		$this->assertTrue( $this->condition->check( array( 'repeater_field' => 'field_rt_unknown' ) ) );
	}

	public function test_greater_than_inclusive_comparator(): void {
		$this->make_current_page_with_rows( 3 );

		$args = array(
			'repeater_field' => 'field_rt_demo_items',
			'comparator'     => Comparator_Provider::COMPARATOR_IS_GREATER_THAN_INCLUSIVE,
		);

		$this->assertTrue( $this->condition->check( $args + array( 'rows_number' => 2 ) ) );
		$this->assertTrue( $this->condition->check( $args + array( 'rows_number' => 3 ) ) );
		$this->assertFalse( $this->condition->check( $args + array( 'rows_number' => 4 ) ) );
	}

	public function test_less_than_inclusive_comparator(): void {
		$this->make_current_page_with_rows( 3 );

		$args = array(
			'repeater_field' => 'field_rt_demo_items',
			'comparator'     => Comparator_Provider::COMPARATOR_IS_LESS_THAN_INCLUSIVE,
		);

		$this->assertTrue( $this->condition->check( $args + array( 'rows_number' => 3 ) ) );
		$this->assertFalse( $this->condition->check( $args + array( 'rows_number' => 2 ) ) );
	}

	public function test_is_and_is_not_comparators(): void {
		$this->make_current_page_with_rows( 3 );

		$this->assertTrue(
			$this->condition->check(
				array(
					'repeater_field' => 'field_rt_demo_items',
					'comparator'     => Comparator_Provider::COMPARATOR_IS,
					'rows_number'    => 3,
				)
			)
		);
		$this->assertFalse(
			$this->condition->check(
				array(
					'repeater_field' => 'field_rt_demo_items',
					'comparator'     => Comparator_Provider::COMPARATOR_IS,
					'rows_number'    => 2,
				)
			)
		);
		$this->assertTrue(
			$this->condition->check(
				array(
					'repeater_field' => 'field_rt_demo_items',
					'comparator'     => Comparator_Provider::COMPARATOR_IS_NOT,
					'rows_number'    => 2,
				)
			)
		);
	}

	public function test_defaults_compare_count_is_zero(): void {
		// Missing comparator defaults to IS, missing rows_number to 0.
		$this->make_current_page_with_rows( 0 );

		$this->assertTrue( $this->condition->check( array( 'repeater_field' => 'field_rt_demo_items' ) ) );

		$this->make_current_page_with_rows( 3 );

		$this->assertFalse( $this->condition->check( array( 'repeater_field' => 'field_rt_demo_items' ) ) );
	}

	public function test_options_scoped_key_counts_the_global_store(): void {
		update_field(
			'field_rt_global_items',
			array(
				array( 'caption' => 'G1' ),
				array( 'caption' => 'G2' ),
			),
			'option'
		);

		// The global post is irrelevant for an options-scoped repeater.
		$this->make_current_page_with_rows( 0 );

		$this->assertTrue(
			$this->condition->check(
				array(
					'repeater_field' => 'field_rt_global_items',
					'comparator'     => Comparator_Provider::COMPARATOR_IS,
					'rows_number'    => 2,
				)
			)
		);
	}
}
