<?php

namespace Arts\RepeaterTags\Tests\Integration;

use Arts\RepeaterTags\Services\Schema;

/**
 * Characterizes the site-wide enumeration against the fixture field groups
 * (dev/mu-plugins/rt-demo-fixtures.php, mounted as mu-plugins). Every key,
 * name, and label asserted here is a literal from those groups.
 *
 * @phpstan-import-type RepeaterEntry from Schema
 */
class SchemaTest extends TestCase {

	/** Fresh instance per call — enumeration memoizes per instance. */
	private function schema(): Schema {
		return new Schema();
	}

	/** @return RepeaterEntry */
	private function entry( string $key ): array {
		$entry = $this->schema()->get_entry( $key );

		$this->assertNotNull( $entry, $key . ' must be an enumerated container' );

		return $entry;
	}

	public function test_enumerates_all_fixture_containers_by_field_key(): void {
		$repeaters = $this->schema()->get_repeaters();

		$expected = array(
			'field_rt_product_mockups',
			'field_rt_product_counters',
			'field_rt_demo_items',
			'field_rt_global_items',
			'field_rt_term_items',
			'field_rt_author_items',
			'field_rt_book_specs',
			'field_rt_nested_parent_items',
			'field_rt_ng_repeater',
			'field_rt_fx_sections',
		);

		foreach ( $expected as $key ) {
			$this->assertArrayHasKey( $key, $repeaters );
		}

		// A nested repeater is a CHILD of its parent entry, never a root entry.
		$this->assertArrayNotHasKey( 'field_rt_np_specs', $repeaters );
	}

	public function test_plain_repeater_entry_shape(): void {
		$entry = $this->entry( 'field_rt_demo_items' );

		$this->assertSame( 'repeater', $entry['kind'] );
		$this->assertSame( 'rt_demo_items', $entry['name'] );
		$this->assertSame( 'RT Demo — Demo Items', $entry['label'] );
		$this->assertSame( '', $entry['options_post_id'] );
		$this->assertSame( array(), $entry['children'] );
		$this->assertSame( array(), $entry['layouts'] );
	}

	public function test_collapsed_setting_resolves_sub_key_to_label_path(): void {
		$entry = $this->entry( 'field_rt_demo_items' );

		// The fixture sets collapsed = field_rt_demo_blurb, deliberately NOT the
		// first text sub-field: the KEY must resolve to the 'blurb' path.
		$this->assertSame( 'blurb', $entry['label_sub_field'] );
		$this->assertSame( array( 'caption', 'blurb' ), $entry['text_sub_fields'] );
	}

	public function test_sub_field_meta_captures_type_and_return_format(): void {
		$entry = $this->entry( 'field_rt_demo_items' );

		$this->assertSame(
			array(
				'label'         => 'Caption',
				'type'          => 'text',
				'return_format' => '',
			),
			$entry['sub_fields']['caption']
		);
		$this->assertSame( 'array', $entry['sub_fields']['badge']['return_format'] );
		$this->assertSame( 'array', $entry['sub_fields']['image']['return_format'] );
		$this->assertSame( 'date_time_picker', $entry['sub_fields']['starts_at']['type'] );
	}

	public function test_options_page_scoping(): void {
		$entry = $this->entry( 'field_rt_global_items' );

		$this->assertSame( 'options', $entry['options_post_id'] );
		$this->assertSame( 'edit_posts', $entry['options_capability'] );
		$this->assertSame( 'RT Demo Options — Global Items (Options)', $entry['label'] );
	}

	public function test_group_hosted_repeater_becomes_dot_path_entry(): void {
		$entry = $this->entry( 'field_rt_ng_repeater' );

		$this->assertSame( 'rt_nested_group.rt_group_repeater', $entry['name'] );
		$this->assertSame( 'RT Nested Demo — Nested Group → Group Repeater', $entry['label'] );
		$this->assertSame( array( 'label_txt' ), array_keys( $entry['sub_fields'] ) );
	}

	public function test_group_inside_row_flattens_and_nested_repeater_becomes_child(): void {
		$entry = $this->entry( 'field_rt_nested_parent_items' );

		$this->assertSame( array( 'title', 'meta.sku' ), array_keys( $entry['sub_fields'] ) );
		$this->assertSame( 'Meta → SKU', $entry['sub_fields']['meta.sku']['label'] );
		$this->assertSame( array( 'title', 'meta.sku' ), $entry['text_sub_fields'] );

		$this->assertSame( array( 'specs' ), array_keys( $entry['children'] ) );

		$child = $entry['children']['specs'];

		$this->assertSame( 'field_rt_np_specs', $child['key'] );
		$this->assertSame( 'Specs', $child['label'] );
		$this->assertSame( '', $child['label_sub_field'] );
		$this->assertSame( array( 'spec_name', 'spec_value' ), array_keys( $child['sub_fields'] ) );
		$this->assertSame( array( 'spec_name', 'spec_value' ), $child['text_sub_fields'] );
	}

	public function test_flexible_content_layouts_and_union(): void {
		$entry = $this->entry( 'field_rt_fx_sections' );

		$this->assertSame( 'flexible_content', $entry['kind'] );
		$this->assertSame( array( 'hero', 'quote' ), array_keys( $entry['layouts'] ) );
		$this->assertSame( 'Hero', $entry['layouts']['hero']['label'] );
		$this->assertSame( array( 'heading', 'image' ), array_keys( $entry['layouts']['hero']['sub_fields'] ) );
		$this->assertSame( array( 'heading', 'author' ), array_keys( $entry['layouts']['quote']['sub_fields'] ) );

		// The union dedupes by path: one 'heading' option with merged layout
		// sources; meta comes from the first layout defining the path.
		$this->assertSame( array( 'heading', 'image', 'author' ), array_keys( $entry['sub_fields'] ) );
		$this->assertSame( 'Hero, Quote → Heading', $entry['sub_fields']['heading']['label'] );
		$this->assertSame( 'Hero → Image', $entry['sub_fields']['image']['label'] );
		$this->assertSame( 'Quote → Author', $entry['sub_fields']['author']['label'] );
	}

	public function test_unknown_key_fails_closed(): void {
		$schema = $this->schema();

		$this->assertNull( $schema->get_entry( 'field_rt_does_not_exist' ) );
		$this->assertFalse( $schema->is_known_repeater( 'field_rt_does_not_exist' ) );
		$this->assertTrue( $schema->is_known_repeater( 'field_rt_demo_items' ) );
	}

	public function test_repeater_options_map_key_to_label(): void {
		$options = $this->schema()->get_repeater_options();

		$this->assertSame( 'RT Demo — Demo Items', $options['field_rt_demo_items'] );
		$this->assertSame( 'RT Demo Options — Global Items (Options)', $options['field_rt_global_items'] );
	}

	public function test_enumeration_memoizes_per_instance(): void {
		$schema = $this->schema();
		$schema->get_repeaters();

		acf_add_local_field_group(
			array(
				'key'      => 'group_rt_test_extra',
				'title'    => 'RT Test Extra',
				'fields'   => array(
					array(
						'key'        => 'field_rt_test_extra_rep',
						'label'      => 'Extra Rep',
						'name'       => 'rt_test_extra_rep',
						'type'       => 'repeater',
						'sub_fields' => array(
							array(
								'key'   => 'field_rt_test_extra_txt',
								'label' => 'Txt',
								'name'  => 'txt',
								'type'  => 'text',
							),
						),
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'page',
						),
					),
				),
			)
		);

		// ACF's local store is process-static — always undo the registration, or
		// it leaks into every later test's enumeration.
		try {
			$this->assertArrayNotHasKey( 'field_rt_test_extra_rep', $schema->get_repeaters() );
			$this->assertArrayHasKey( 'field_rt_test_extra_rep', ( new Schema() )->get_repeaters() );
		} finally {
			acf_remove_local_field_group( 'group_rt_test_extra' );
			acf_remove_local_field( 'field_rt_test_extra_rep' );
			acf_remove_local_field( 'field_rt_test_extra_txt' );
		}
	}
}
