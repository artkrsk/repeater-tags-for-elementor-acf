<?php

namespace Arts\RepeaterTags\Tests\Integration;

use Arts\RepeaterTags\Services\Rows;

/**
 * Characterizes the rows producer: formatted NAME-keyed reads, the frozen
 * `arts_repeater_tags/rows` filter contract, context-id reads, dot-path
 * addressing, the child tier, memoization, and picker-option derivation.
 *
 * Every test uses a FRESH Rows instance: the memo is instance-level with no
 * reset, and reused context ids ('options', 'term_*') would poison later
 * tests through a shared instance. Filters registered here are restored by
 * the WP test framework between tests.
 */
class RowsTest extends TestCase {

	private Rows $rows;

	public function set_up(): void {
		parent::set_up();

		$this->rows = new Rows();
	}

	private function make_page(): int {
		return $this->create_post_id( array( 'post_type' => 'page' ) );
	}

	/**
	 * @param array<int, array{mixed, string, int|string}> $captured Filled with [rows, path, post_id] per filter call.
	 */
	private function spy_on_rows_filter( array &$captured ): void {
		add_filter(
			'arts_repeater_tags/rows',
			static function ( $rows, $field_path, $post_id ) use ( &$captured ) {
				$captured[] = array( $rows, $field_path, $post_id );

				return $rows;
			},
			10,
			3
		);
	}

	public function test_reads_formatted_rows_keyed_by_sub_field_name(): void {
		$page_id = $this->make_page();

		update_field(
			'field_rt_demo_items',
			array(
				array(
					'caption' => 'One',
					'blurb'   => 'First',
				),
				array(
					'caption' => 'Two',
					'blurb'   => 'Second',
				),
			),
			$page_id
		);

		$rows = $this->rows->get( 'field_rt_demo_items', $page_id );

		$this->assertCount( 2, $rows );
		$this->assertSame( 'One', $rows[0]['caption'] );
		$this->assertSame( 'Second', $rows[1]['blurb'] );
		$this->assertArrayNotHasKey( 'field_rt_demo_caption', $rows[0], 'formatted rows are keyed by NAME, never by field key' );
	}

	public function test_empty_repeater_reads_as_empty_list(): void {
		$this->assertSame( array(), $this->rows->get( 'field_rt_demo_items', $this->make_page() ) );
	}

	public function test_unknown_key_fails_closed_but_filter_still_fires(): void {
		$page_id  = $this->make_page();
		$captured = array();
		$this->spy_on_rows_filter( $captured );

		$this->assertSame( array(), $this->rows->get( 'field_rt_unknown', $page_id ) );

		$this->assertCount( 1, $captured );
		$this->assertSame( array( array(), '', $page_id ), $captured[0] );
	}

	public function test_invalid_post_id_skips_read_but_filter_still_fires(): void {
		$captured = array();
		$this->spy_on_rows_filter( $captured );

		$this->assertSame( array(), $this->rows->get( 'field_rt_demo_items', 0 ) );

		$this->assertCount( 1, $captured );
		$this->assertSame( array( array(), 'rt_demo_items', 0 ), $captured[0] );
	}

	public function test_filter_synthesizes_rows_and_result_is_memoized(): void {
		$page_id = $this->make_page();
		$calls   = 0;

		add_filter(
			'arts_repeater_tags/rows',
			static function ( $rows ) use ( &$calls ) {
				$calls++;

				return array( array( 'caption' => 'synthesized' ) );
			}
		);

		$first = $this->rows->get( 'field_rt_unknown', $page_id );

		$this->assertSame( array( array( 'caption' => 'synthesized' ) ), $first );
		$this->assertSame( $first, $this->rows->get( 'field_rt_unknown', $page_id ) );
		$this->assertSame( 1, $calls, 'the filter fires once per (key, context) — memo serves repeats' );
	}

	public function test_filter_return_is_normalized(): void {
		add_filter(
			'arts_repeater_tags/rows',
			static function () {
				return array( array( 'a' => 1 ), 'junk', 42, array( 'b' => array( 'x' ) ) );
			}
		);

		$this->assertSame(
			array( array( 'a' => 1 ), array( 'b' => array( 'x' ) ) ),
			$this->rows->get( 'field_rt_unknown', $this->make_page() )
		);
	}

	public function test_options_context_roundtrip(): void {
		// Writes use ACF's 'option' alias; Context resolves the canonical 'options'.
		update_field( 'field_rt_global_items', array( array( 'caption' => 'Global one' ) ), 'option' );

		$captured = array();
		$this->spy_on_rows_filter( $captured );

		$rows = $this->rows->get( 'field_rt_global_items', 'options' );

		$this->assertCount( 1, $rows );
		$this->assertSame( 'Global one', $rows[0]['caption'] );
		$this->assertSame( array( 'rt_global_items', 'options' ), array( $captured[0][1], $captured[0][2] ) );
	}

	public function test_term_context_roundtrip(): void {
		$term_id = $this->create_category_id();

		update_field( 'field_rt_term_items', array( array( 'caption' => 'Term row' ) ), 'term_' . $term_id );

		$captured = array();
		$this->spy_on_rows_filter( $captured );

		$rows = $this->rows->get( 'field_rt_term_items', 'term_' . $term_id );

		$this->assertSame( 'Term row', $rows[0]['caption'] );
		$this->assertSame( 'term_' . $term_id, $captured[0][2] );
	}

	public function test_user_context_roundtrip(): void {
		$user_id = $this->create_user_id();

		update_field( 'field_rt_author_items', array( array( 'caption' => 'Author row' ) ), 'user_' . $user_id );

		$rows = $this->rows->get( 'field_rt_author_items', 'user_' . $user_id );

		$this->assertSame( 'Author row', $rows[0]['caption'] );
	}

	public function test_group_hosted_repeater_descends_the_dot_path(): void {
		$page_id = $this->make_page();

		update_field(
			'field_rt_ng_group',
			array(
				'child'             => 'Group child text',
				'rt_group_repeater' => array(
					array( 'label_txt' => 'Inner row A' ),
					array( 'label_txt' => 'Inner row B' ),
				),
			),
			$page_id
		);

		$rows = $this->rows->get( 'field_rt_ng_repeater', $page_id );

		$this->assertCount( 2, $rows );
		$this->assertSame( 'Inner row A', $rows[0]['label_txt'] );
	}

	public function test_walk_row_path_depth_one_semantics(): void {
		$row = array(
			'title' => 'T',
			'meta'  => array( 'sku' => 'SKU-1' ),
			'flat'  => 'x',
		);

		$this->assertSame( 'T', $this->rows->walk_row_path( $row, 'title' ) );
		$this->assertSame( 'SKU-1', $this->rows->walk_row_path( $row, 'meta.sku' ) );
		$this->assertSame( array( 'sku' => 'SKU-1' ), $this->rows->walk_row_path( $row, 'meta' ) );
		$this->assertNull( $this->rows->walk_row_path( $row, 'missing' ) );
		$this->assertNull( $this->rows->walk_row_path( $row, 'meta.missing' ) );
		$this->assertNull( $this->rows->walk_row_path( $row, 'flat.deeper' ), 'a non-array intermediate fails closed' );
	}

	/** Seeds the nested parents fixture shape: two parents with specs, one without. */
	private function seed_nested_parents( int $page_id ): void {
		update_field(
			'field_rt_nested_parent_items',
			array(
				array(
					'title' => 'Parent One',
					'meta'  => array( 'sku' => 'SKU-1001' ),
					'specs' => array(
						array(
							'spec_name'  => 'Weight',
							'spec_value' => '1.2 kg',
						),
						array(
							'spec_name'  => 'Width',
							'spec_value' => '340 mm',
						),
					),
				),
				array(
					'title' => 'Parent Two',
					'meta'  => array( 'sku' => 'SKU-2002' ),
					'specs' => array(
						array(
							'spec_name'  => 'Material',
							'spec_value' => 'Solid oak',
						),
					),
				),
				array(
					'title' => 'Parent Three',
					'meta'  => array( 'sku' => 'SKU-3003' ),
					'specs' => array(),
				),
			),
			$page_id
		);
	}

	public function test_child_rows_resolve_within_a_parent_row(): void {
		$page_id = $this->make_page();
		$this->seed_nested_parents( $page_id );

		$parents = $this->rows->get( 'field_rt_nested_parent_items', $page_id );

		$this->assertCount( 3, $parents );

		$specs = $this->rows->get_child_rows( $parents[0], 'specs' );

		$this->assertCount( 2, $specs );
		$this->assertSame( 'Weight', $specs[0]['spec_name'] );
		$this->assertSame( '340 mm', $specs[1]['spec_value'] );

		$this->assertSame( array(), $this->rows->get_child_rows( $parents[2], 'specs' ), 'an empty child repeater reads as an empty list' );
		$this->assertSame( array(), $this->rows->get_child_rows( $parents[0], 'bogus' ) );
	}

	public function test_child_row_options_and_parent_index_sentinels(): void {
		$page_id = $this->make_page();
		$this->seed_nested_parents( $page_id );

		$expected = array(
			array(
				'id'   => -1,
				'text' => 'Last row',
			),
			array(
				'id'   => 0,
				'text' => '1 — Weight',
			),
			array(
				'id'   => 1,
				'text' => '2 — Width',
			),
		);

		$this->assertSame( $expected, $this->rows->get_child_row_options( 'field_rt_nested_parent_items', 'specs', 0, $page_id ) );

		// -2 previews against parent row 0 (the loop sentinel resolves only at render).
		$this->assertSame( $expected, $this->rows->get_child_row_options( 'field_rt_nested_parent_items', 'specs', -2, $page_id ) );

		// -1 = last parent row, whose specs are empty — no phantom "Last row" entry.
		$this->assertSame( array(), $this->rows->get_child_row_options( 'field_rt_nested_parent_items', 'specs', -1, $page_id ) );

		$this->assertSame( array(), $this->rows->get_child_row_options( 'field_rt_nested_parent_items', 'specs', 99, $page_id ) );
		$this->assertSame( array(), $this->rows->get_child_row_options( 'field_rt_nested_parent_items', 'bogus', 0, $page_id ) );
		$this->assertSame( array(), $this->rows->get_child_row_options( 'field_rt_unknown', 'specs', 0, $page_id ) );
	}

	public function test_reads_memoize_per_key_and_context(): void {
		$page_id = $this->make_page();

		update_field( 'field_rt_demo_items', array( array( 'caption' => 'Before' ) ), $page_id );

		$first = $this->rows->get( 'field_rt_demo_items', $page_id );

		update_field(
			'field_rt_demo_items',
			array(
				array( 'caption' => 'After' ),
				array( 'caption' => 'Extra' ),
			),
			$page_id
		);

		$this->assertSame( $first, $this->rows->get( 'field_rt_demo_items', $page_id ), 'the instance memo serves repeat reads' );

		$fresh = ( new Rows() )->get( 'field_rt_demo_items', $page_id );

		$this->assertCount( 2, $fresh, 'a fresh instance sees the updated value — the staleness lives in the memo, not ACF' );
	}

	public function test_row_options_prefer_the_collapsed_label_sub_field(): void {
		$page_id = $this->make_page();

		update_field(
			'field_rt_demo_items',
			array(
				array(
					'caption' => 'Cap one',
					'blurb'   => 'Blurb one',
				),
				array(
					'caption' => 'Cap two',
					'blurb'   => '',
				),
				array(
					'caption' => '',
					'blurb'   => '',
				),
			),
			$page_id
		);

		$this->assertSame(
			array(
				array(
					'id'   => -1,
					'text' => 'Last row',
				),
				array(
					'id'   => 0,
					'text' => '1 — Blurb one',
				),
				array(
					'id'   => 1,
					'text' => '2 — Cap two',
				),
				array(
					'id'   => 2,
					'text' => 'Row 3',
				),
			),
			$this->rows->get_row_options( 'field_rt_demo_items', $page_id )
		);
	}

	public function test_row_options_empty_repeater_offers_no_phantom_last_row(): void {
		$this->assertSame( array(), $this->rows->get_row_options( 'field_rt_demo_items', $this->make_page() ) );
	}

	public function test_row_option_labels_truncate(): void {
		$page_id = $this->make_page();
		$long    = str_repeat( 'a', Rows::LABEL_MAX_LENGTH + 5 );

		update_field( 'field_rt_demo_items', array( array( 'blurb' => $long ) ), $page_id );

		$options = $this->rows->get_row_options( 'field_rt_demo_items', $page_id );

		$this->assertSame( '1 — ' . str_repeat( 'a', Rows::LABEL_MAX_LENGTH ) . '…', $options[1]['text'] );
	}

	public function test_flex_row_options_label_by_layout_with_own_layout_snippet(): void {
		$page_id = $this->make_page();

		update_field(
			'field_rt_fx_sections',
			array(
				array(
					'acf_fc_layout' => 'hero',
					'heading'       => 'Hero headline one',
				),
				array(
					'acf_fc_layout' => 'quote',
					'heading'       => 'A quiet quote heading',
					'author'        => 'Jane Doe',
				),
				array(
					'acf_fc_layout' => 'hero',
					'heading'       => '',
				),
			),
			$page_id
		);

		$this->assertSame(
			array(
				array(
					'id'   => -1,
					'text' => 'Last row',
				),
				array(
					'id'   => 0,
					'text' => '1 — Hero: Hero headline one',
				),
				array(
					'id'   => 1,
					'text' => '2 — Quote: A quiet quote heading',
				),
				array(
					'id'   => 2,
					'text' => '3 — Hero',
				),
			),
			$this->rows->get_row_options( 'field_rt_fx_sections', $page_id )
		);
	}

	public function test_flex_orphaned_layout_row_shows_raw_layout_name(): void {
		$page_id = $this->make_page();

		// A stored layout name no longer defined in the field: ACF formats such a
		// row as acf_fc_layout alone, so it labels by raw name and never snippets.
		update_field(
			'field_rt_fx_sections',
			array(
				array(
					'acf_fc_layout' => 'ghost',
					'heading'       => 'Ghost heading',
				),
			),
			$page_id
		);

		$options = $this->rows->get_row_options( 'field_rt_fx_sections', $page_id );

		$this->assertSame( '1 — ghost', $options[1]['text'] );
	}

	public function test_reads_never_disturb_an_active_acf_loop(): void {
		$page_id = $this->make_page();

		update_field(
			'field_rt_product_mockups',
			array(
				array( 'caption' => 'M1' ),
				array( 'caption' => 'M2' ),
				array( 'caption' => 'M3' ),
			),
			$page_id
		);
		update_field( 'field_rt_demo_items', array( array( 'caption' => 'D1' ) ), $page_id );

		$seen = array();

		// The hard repo rule, asserted behaviorally: a service read mid-iteration
		// must not touch ACF's global loop stack.
		while ( have_rows( 'product_mockups', $page_id ) ) {
			the_row();

			$seen[] = get_sub_field( 'caption' );

			if ( 1 === count( $seen ) ) {
				$this->assertCount( 1, $this->rows->get( 'field_rt_demo_items', $page_id ) );
				$this->assertNotEmpty( $this->rows->get_row_options( 'field_rt_demo_items', $page_id ) );
			}
		}

		$this->assertSame( array( 'M1', 'M2', 'M3' ), $seen );
	}
}
