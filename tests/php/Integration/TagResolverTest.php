<?php

namespace Arts\RepeaterTags\Tests\Integration;

use Arts\RepeaterTags\Services\LoopRepeat;
use Arts\RepeaterTags\Tags\RepeaterText;
use Elementor\Widget_Base;
use WP_Query;

/**
 * Characterizes BaseRepeaterTag::resolve_cell_with_meta() — the cell resolver every render
 * funnels through. The services suites prove the ROWS are right; this proves a tag addresses
 * the right CELL inside them: the row-index sentinels on both tiers, the child tier, and the
 * flexible-content row-aware type lookup.
 *
 * RepeaterText is the vehicle throughout (its render() is a plain esc_html for scalar types),
 * so an assertion reads as "the resolver landed on this cell".
 */
class TagResolverTest extends TagTestCase {

	private const DEMO = 'field_rt_demo_items';

	/** Seeds demo rows on a page and makes it the global post (rung 3 of the context ladder). */
	private function seed_current_page( int $rows = 3 ): int {
		$page_id = $this->create_post_id( array( 'post_type' => 'page' ) );

		if ( $rows > 0 ) {
			update_field(
				self::DEMO,
				array_map(
					static fn ( int $i ): array => array( 'caption' => 'Row ' . $i ),
					range( 1, $rows )
				),
				$page_id
			);
		}

		$GLOBALS['post'] = get_post( $page_id );

		return $page_id;
	}

	/** @param array<string, mixed> $settings */
	private function demo_text( array $settings ): mixed {
		return $this->tag_content(
			RepeaterText::class,
			$settings + array(
				'repeater_field'          => self::DEMO,
				'sub_field_' . self::DEMO => 'caption',
			)
		);
	}

	public function test_row_index_addresses_the_row(): void {
		$this->seed_current_page();

		$this->assertSame( 'Row 1', $this->demo_text( array( 'row_index' => '0' ) ) );
		$this->assertSame( 'Row 3', $this->demo_text( array( 'row_index' => '2' ) ) );
	}

	public function test_last_row_sentinel(): void {
		$this->seed_current_page();

		$this->assertSame( 'Row 3', $this->demo_text( array( 'row_index' => '-1' ) ) );
	}

	public function test_last_row_sentinel_on_an_empty_repeater_fails_closed(): void {
		$this->seed_current_page( 0 );

		// -1 maps to count-1 = -1, which is simply a missing index — the same fail-closed
		// path as any other miss, and no notice.
		$this->assertSame( '', $this->demo_text( array( 'row_index' => '-1' ) ) );
	}

	public function test_row_index_past_the_end_fails_closed(): void {
		$this->seed_current_page();

		$this->assertSame( '', $this->demo_text( array( 'row_index' => '99' ) ) );
	}

	public function test_unbound_settings_fail_closed(): void {
		$this->seed_current_page();

		$this->assertSame(
			'',
			$this->tag_content( RepeaterText::class, array( 'row_index' => '0' ) ),
			'no repeater bound'
		);
		$this->assertSame(
			'',
			$this->tag_content(
				RepeaterText::class,
				array(
					'repeater_field' => self::DEMO,
					'row_index'      => '0',
				)
			),
			'repeater bound, no sub-field'
		);
	}

	public function test_no_post_context_fails_closed(): void {
		$this->seed_current_page();

		// The ladder's last rung is get_the_ID(); with no global post it yields 0, which is
		// not a valid read target.
		$GLOBALS['post'] = null;

		$this->assertSame( '', $this->demo_text( array( 'row_index' => '0' ) ) );
	}

	public function test_group_hosted_dot_path_within_a_row(): void {
		$page_id = $this->create_post_id( array( 'post_type' => 'page' ) );

		update_field(
			'field_rt_nested_parent_items',
			array(
				array(
					'title' => 'Parent One',
					'meta'  => array( 'sku' => 'SKU-1001' ),
				),
			),
			$page_id
		);

		$GLOBALS['post'] = get_post( $page_id );

		$this->assertSame(
			'SKU-1001',
			$this->tag_content(
				RepeaterText::class,
				array(
					'repeater_field'                            => 'field_rt_nested_parent_items',
					'row_index'                                 => '0',
					'sub_field_field_rt_nested_parent_items'    => 'meta.sku',
				)
			)
		);
	}

	public function test_unknown_repeater_key_still_resolves_through_the_rows_filter(): void {
		$this->seed_current_page();

		// The dev API can synthesize rows for a key the schema never enumerated — the
		// null-entry path is deliberate, and the tag must stay resolvable through it.
		add_filter(
			'arts_repeater_tags/rows',
			static fn (): array => array( array( 'caption' => 'Synthesized' ) )
		);

		$this->assertSame(
			'Synthesized',
			$this->tag_content(
				RepeaterText::class,
				array(
					'repeater_field'             => 'field_rt_synthetic',
					'row_index'                  => '0',
					'sub_field_field_rt_synthetic' => 'caption',
				)
			)
		);
	}

	/**
	 * The current-loop-row sentinel resolves through LoopRepeat's registry, so the loop has to
	 * be real: expand a query the way the Pro hook does, then read the tag per item.
	 */
	public function test_current_loop_row_sentinel_resolves_per_item(): void {
		$page_id = $this->create_post_id( array( 'post_type' => 'page' ) );

		update_field(
			self::DEMO,
			array(
				array( 'caption' => 'Row 1' ),
				array( 'caption' => 'Row 2' ),
			),
			$page_id
		);

		$widget = new class( array(
			'id'       => 'rt_loop_stub',
			'elType'   => 'widget',
			'settings' => array( LoopRepeat::CONTROL_KEY => self::DEMO ),
		), array() ) extends Widget_Base {
			public function get_name(): string {
				return 'rt-resolver-loop-stub';
			}
		};

		$query = new WP_Query(
			array(
				'post_type'      => 'page',
				'post__in'       => array( $page_id ),
				'posts_per_page' => -1,
			)
		);

		// The registry is keyed by the query object, and resolve_current_row_index() reads the
		// live in_the_loop/current_post — so it must be the SAME LoopRepeat the tag reads,
		// i.e. the one behind the Plugin singleton.
		\Arts\RepeaterTags\Plugin::instance()->loop_repeat()->expand_query_results( $query, $widget );

		$seen = array();

		while ( $query->have_posts() ) {
			$query->the_post();

			$seen[] = $this->demo_text( array( 'row_index' => '-2' ) );
		}

		wp_reset_postdata();

		$this->assertSame( array( 'Row 1', 'Row 2' ), $seen, 'one card per row, each reading its own row' );
	}

	public function test_current_loop_row_sentinel_outside_a_loop_previews_row_zero(): void {
		$this->seed_current_page();

		// Editing the loop card template, or previewing the tag: a useful preview beats an
		// empty card.
		$this->assertSame( 'Row 1', $this->demo_text( array( 'row_index' => '-2' ) ) );
	}

	/** Seeds the nested parent/child shape and makes the page current. */
	private function seed_nested_current_page(): void {
		$page_id = $this->create_post_id( array( 'post_type' => 'page' ) );

		update_field(
			'field_rt_nested_parent_items',
			array(
				array(
					'title' => 'Parent One',
					'specs' => array(
						array( 'spec_value' => 'Spec A' ),
						array( 'spec_value' => 'Spec B' ),
					),
				),
				array(
					'title' => 'Parent Two',
					'specs' => array( array( 'spec_value' => 'Spec C' ) ),
				),
			),
			$page_id
		);

		$GLOBALS['post'] = get_post( $page_id );
	}

	/** @param array<string, mixed> $settings */
	private function child_text( array $settings ): mixed {
		return $this->tag_content(
			RepeaterText::class,
			$settings + array(
				'repeater_field'                         => 'field_rt_nested_parent_items',
				'sub_field_field_rt_nested_parent_items' => 'specs',
				'child_sub_field_field_rt_np_specs'      => 'spec_value',
			)
		);
	}

	public function test_child_tier_addresses_a_row_within_the_parent_row(): void {
		$this->seed_nested_current_page();

		$this->assertSame(
			'Spec B',
			$this->child_text(
				array(
					'row_index'                         => '0',
					'child_row_index_field_rt_np_specs' => '1',
				)
			)
		);
		$this->assertSame(
			'Spec C',
			$this->child_text(
				array(
					'row_index'                         => '1',
					'child_row_index_field_rt_np_specs' => '0',
				)
			)
		);
	}

	public function test_child_tier_honors_last_row_but_not_the_loop_sentinel(): void {
		$this->seed_nested_current_page();

		$this->assertSame(
			'Spec B',
			$this->child_text(
				array(
					'row_index'                         => '0',
					'child_row_index_field_rt_np_specs' => '-1',
				)
			),
			'last row applies per tier'
		);

		// Frozen contract: there is NO -2 on the child tier. A hand-saved one is just a
		// missing index — it must not silently behave like row 0.
		$this->assertSame(
			'',
			$this->child_text(
				array(
					'row_index'                         => '0',
					'child_row_index_field_rt_np_specs' => '-2',
				)
			)
		);
	}

	public function test_child_tier_without_a_child_sub_field_fails_closed(): void {
		$this->seed_nested_current_page();

		$this->assertSame(
			'',
			$this->tag_content(
				RepeaterText::class,
				array(
					'repeater_field'                         => 'field_rt_nested_parent_items',
					'row_index'                              => '0',
					'sub_field_field_rt_nested_parent_items' => 'specs',
					'child_row_index_field_rt_np_specs'      => '0',
				)
			)
		);
	}

	/**
	 * The sharpest branch in the resolver. 'body' is ONE bindable path, but it is wysiwyg in
	 * the Rich layout and plain text in the Plain layout — and the Sub-field select's union
	 * meta says wysiwyg (first layout defining the path wins). Only a row-aware lookup, keyed
	 * off the addressed row's acf_fc_layout, can render both correctly.
	 */
	public function test_flexible_content_resolves_type_from_the_addressed_rows_layout(): void {
		$page_id = $this->create_post_id( array( 'post_type' => 'page' ) );

		update_field(
			'field_rt_types_flex',
			array(
				array(
					'acf_fc_layout' => 'rich',
					'body'          => '<strong>Bold</strong><script>alert(1)</script>',
				),
				array(
					'acf_fc_layout' => 'plain',
					'body'          => '<strong>Bold</strong>',
				),
			),
			$page_id
		);

		$GLOBALS['post'] = get_post( $page_id );

		$rich = $this->tag_content(
			RepeaterText::class,
			array(
				'repeater_field'                => 'field_rt_types_flex',
				'row_index'                     => '0',
				'sub_field_field_rt_types_flex' => 'body',
			)
		);

		$this->assertIsString( $rich );
		$this->assertStringContainsString( '<strong>Bold</strong>', $rich, 'a wysiwyg row keeps safe HTML' );
		$this->assertStringNotContainsString( '<script>', $rich, 'wp_kses_post strips the script' );

		$plain = $this->tag_content(
			RepeaterText::class,
			array(
				'repeater_field'                => 'field_rt_types_flex',
				'row_index'                     => '1',
				'sub_field_field_rt_types_flex' => 'body',
			)
		);

		$this->assertSame(
			'&lt;strong&gt;Bold&lt;/strong&gt;',
			$plain,
			'the same bound path on a text-typed row is escaped, not kses\'d — the union meta (wysiwyg) must lose to the row\'s own layout'
		);
	}

	public function test_flexible_content_orphaned_layout_row_fails_closed(): void {
		$page_id = $this->create_post_id( array( 'post_type' => 'page' ) );

		// A layout name no longer defined: ACF loads such a row as acf_fc_layout ALONE, so
		// there is no meta and no value to find.
		update_field(
			'field_rt_types_flex',
			array(
				array(
					'acf_fc_layout' => 'ghost',
					'body'          => 'Ghost body',
				),
			),
			$page_id
		);

		$GLOBALS['post'] = get_post( $page_id );

		$this->assertSame(
			'',
			$this->tag_content(
				RepeaterText::class,
				array(
					'repeater_field'                => 'field_rt_types_flex',
					'row_index'                     => '0',
					'sub_field_field_rt_types_flex' => 'body',
				)
			)
		);
	}

	public function test_flexible_content_path_missing_from_the_addressed_layout_fails_closed(): void {
		$page_id = $this->create_post_id( array( 'post_type' => 'page' ) );

		update_field(
			'field_rt_fx_sections',
			array(
				array(
					'acf_fc_layout' => 'hero',
					'heading'       => 'Hero one',
				),
			),
			$page_id
		);

		$GLOBALS['post'] = get_post( $page_id );

		// 'author' is bindable (it is in the union) but lives only in the Quote layout.
		$this->assertSame(
			'',
			$this->tag_content(
				RepeaterText::class,
				array(
					'repeater_field'                 => 'field_rt_fx_sections',
					'row_index'                      => '0',
					'sub_field_field_rt_fx_sections' => 'author',
				)
			)
		);
		$this->assertSame(
			'Hero one',
			$this->tag_content(
				RepeaterText::class,
				array(
					'repeater_field'                 => 'field_rt_fx_sections',
					'row_index'                      => '0',
					'sub_field_field_rt_fx_sections' => 'heading',
				)
			),
			'a path the row\'s layout does define still resolves'
		);
	}
}
