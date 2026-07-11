<?php

namespace Arts\RepeaterTags\Tests\Integration;

use Arts\RepeaterTags\Services\LoopRepeat;
use Elementor\Widget_Base;
use WP_Query;

/**
 * Characterizes the Pro repeat mode's query expansion and current-row registry.
 * The production hook fires with (WP_Query, Widget_Base) — the widget here is a
 * minimal stub carrying the frozen control key; the loop-grid/carousel control
 * registration itself is editor plumbing and out of scope.
 */
class LoopRepeatTest extends TestCase {

	private LoopRepeat $loop_repeat;

	public function set_up(): void {
		parent::set_up();

		$this->loop_repeat = new LoopRepeat();
	}

	private function make_widget( string $repeater_key ): Widget_Base {
		$data = array(
			'id'       => 'rt_test_stub',
			'elType'   => 'widget',
			'settings' => array( LoopRepeat::CONTROL_KEY => $repeater_key ),
		);

		return new class( $data, array() ) extends Widget_Base {
			public function get_name(): string {
				return 'rt-loop-repeat-stub';
			}
		};
	}

	/** @param array<int, int> $row_counts Demo rows to seed per created page. */
	private function make_pages_query( array $row_counts ): WP_Query {
		$ids = array();

		foreach ( $row_counts as $i => $count ) {
			$page_id = $this->create_post_id( array( 'post_type' => 'page' ) );

			if ( $count > 0 ) {
				update_field(
					'field_rt_demo_items',
					array_map(
						static fn ( int $n ): array => array( 'caption' => 'P' . $i . 'R' . $n ),
						range( 1, $count )
					),
					$page_id
				);
			}

			$ids[] = $page_id;
		}

		return new WP_Query(
			array(
				'post_type'      => 'page',
				'post__in'       => $ids,
				'orderby'        => 'post__in',
				'posts_per_page' => -1,
			)
		);
	}

	public function test_expands_each_post_into_one_item_per_row(): void {
		$query    = $this->make_pages_query( array( 2, 1, 0 ) );
		$original = array_values( wp_list_pluck( $query->posts, 'ID' ) );

		$this->loop_repeat->expand_query_results( $query, $this->make_widget( 'field_rt_demo_items' ) );

		$this->assertSame(
			array( $original[0], $original[0], $original[1] ),
			array_values( wp_list_pluck( $query->posts, 'ID' ) ),
			'two rows on the first post, one on the second, the zero-row post drops out'
		);
		$this->assertSame( 3, $query->post_count );
	}

	public function test_current_row_resolves_per_item_inside_the_loop(): void {
		$query = $this->make_pages_query( array( 2, 1 ) );

		$this->loop_repeat->expand_query_results( $query, $this->make_widget( 'field_rt_demo_items' ) );

		// Assign before asserting: assertNull() on the call expression itself would
		// make PHPStan remember the method as returning null for the whole test.
		$outside_loop = $this->loop_repeat->resolve_current_row_index();

		$this->assertNull( $outside_loop, 'no sentinel outside a loop' );

		$resolved = array();

		while ( $query->have_posts() ) {
			$query->the_post();

			$resolved[] = $this->loop_repeat->resolve_current_row_index();
		}

		wp_reset_postdata();

		$this->assertSame( array( 0, 1, 0 ), $resolved );

		$after_loop = $this->loop_repeat->resolve_current_row_index();

		$this->assertNull( $after_loop, 'the sentinel drops when the loop ends' );
	}

	public function test_off_setting_leaves_the_query_untouched(): void {
		$query    = $this->make_pages_query( array( 2, 1 ) );
		$original = wp_list_pluck( $query->posts, 'ID' );

		$this->loop_repeat->expand_query_results( $query, $this->make_widget( '' ) );

		$this->assertSame( $original, wp_list_pluck( $query->posts, 'ID' ) );
	}

	public function test_unknown_key_leaves_the_query_untouched(): void {
		$query    = $this->make_pages_query( array( 2 ) );
		$original = wp_list_pluck( $query->posts, 'ID' );

		$this->loop_repeat->expand_query_results( $query, $this->make_widget( 'field_rt_unknown' ) );

		$this->assertSame( $original, wp_list_pluck( $query->posts, 'ID' ) );
	}

	public function test_options_scoped_key_expands_every_post_by_the_global_rows(): void {
		update_field(
			'field_rt_global_items',
			array(
				array( 'caption' => 'G1' ),
				array( 'caption' => 'G2' ),
			),
			'option'
		);

		$query    = $this->make_pages_query( array( 0, 0 ) );
		$original = array_values( wp_list_pluck( $query->posts, 'ID' ) );

		$this->loop_repeat->expand_query_results( $query, $this->make_widget( 'field_rt_global_items' ) );

		$this->assertSame(
			array( $original[0], $original[0], $original[1], $original[1] ),
			array_values( wp_list_pluck( $query->posts, 'ID' ) )
		);

		$resolved = array();

		while ( $query->have_posts() ) {
			$query->the_post();

			$resolved[] = $this->loop_repeat->resolve_current_row_index();
		}

		wp_reset_postdata();

		$this->assertSame( array( 0, 1, 0, 1 ), $resolved );
	}

	public function test_non_query_and_non_widget_inputs_are_ignored(): void {
		$query    = $this->make_pages_query( array( 1 ) );
		$original = wp_list_pluck( $query->posts, 'ID' );

		/* @phpstan-ignore argument.type (deliberately wrong input — pins the runtime guard) */
		$this->loop_repeat->expand_query_results( (object) array(), $this->make_widget( 'field_rt_demo_items' ) );
		/* @phpstan-ignore argument.type (deliberately wrong input — pins the runtime guard) */
		$this->loop_repeat->expand_query_results( $query, (object) array() );

		$this->assertSame( $original, wp_list_pluck( $query->posts, 'ID' ) );
	}
}
