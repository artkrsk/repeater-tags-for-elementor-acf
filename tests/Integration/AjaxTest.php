<?php

namespace Arts\RepeaterTags\Tests\Integration;

use Arts\RepeaterTags\Managers\Ajax;

/**
 * Characterizes the row-enumeration endpoint (frozen action arts_repeater_tags_get_rows).
 * This is the plugin's capability boundary: the picker enumerates ROW LABELS, which are real
 * post/term/user content, so every branch of resolve_request_context() must enforce the same
 * capability model as Elementor's own render_tags — options-page capability / edit_term /
 * edit_user / edit_post. A regression here leaks content into a lesser user's dropdown, which
 * is why the denial cases matter as much as the happy ones.
 */
class AjaxTest extends TestCase {

	private Ajax $ajax;

	public function set_up(): void {
		parent::set_up();

		$this->ajax = new Ajax();
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<int, array{id: int, text: string}>
	 */
	private function get_rows( array $data ): array {
		return $this->ajax->handle_get_rows( $data )['options'];
	}

	/**
	 * @param array<int, array{id: int, text: string}> $options
	 * @return array<int, string>
	 */
	private function texts( array $options ): array {
		return array_column( $options, 'text' );
	}

	private function login( string $role ): int {
		$user_id = $this->create_user_id( array( 'role' => $role ) );

		wp_set_current_user( $user_id );

		return $user_id;
	}

	private function seed_page_with_rows(): int {
		$page_id = $this->create_post_id( array( 'post_type' => 'page' ) );

		update_field(
			'field_rt_demo_items',
			array(
				array( 'caption' => 'Alpha' ),
				array( 'caption' => 'Beta' ),
			),
			$page_id
		);

		return $page_id;
	}

	public function test_unknown_repeater_key_fails_the_whitelist(): void {
		$this->login( 'administrator' );

		$this->assertSame(
			array(),
			$this->get_rows(
				array(
					'repeater_key' => 'field_rt_not_a_real_key',
					'post_id'      => $this->seed_page_with_rows(),
				)
			),
			'enumeration is whitelisted against the schema, never against the request'
		);
	}

	public function test_singular_preview_requires_edit_post(): void {
		$page_id = $this->seed_page_with_rows();
		$request = array(
			'repeater_key' => 'field_rt_demo_items',
			'post_id'      => $page_id,
		);

		$this->login( 'editor' );

		$this->assertSame(
			array( 'Last row', '1 — Alpha', '2 — Beta' ),
			$this->texts( $this->get_rows( $request ) )
		);

		$this->login( 'subscriber' );

		$this->assertSame( array(), $this->get_rows( $request ), 'no edit_post, no rows' );
	}

	public function test_missing_post_id_yields_no_rows(): void {
		$this->login( 'administrator' );

		$this->assertSame( array(), $this->get_rows( array( 'repeater_key' => 'field_rt_demo_items' ) ) );
	}

	public function test_options_scoped_repeater_checks_the_options_page_capability(): void {
		update_field( 'field_rt_global_items', array( array( 'caption' => 'Global' ) ), 'option' );

		// The options store is site-wide: the preview post id is irrelevant to this branch,
		// and a post the user cannot edit must not change the outcome either way.
		$request = array(
			'repeater_key' => 'field_rt_global_items',
			'post_id'      => $this->create_post_id( array( 'post_type' => 'page' ) ),
		);

		$this->login( 'editor' );

		$this->assertSame(
			array( 'Last row', '1 — Global' ),
			$this->texts( $this->get_rows( $request ) ),
			'edit_posts is ACF\'s default options-page capability — an editor holds it'
		);

		$this->login( 'subscriber' );

		$this->assertSame( array(), $this->get_rows( $request ) );
	}

	public function test_taxonomy_preview_resolves_the_term_context_and_checks_edit_term(): void {
		$term_id = $this->create_category_id();

		update_field( 'field_rt_term_items', array( array( 'caption' => 'Term row' ) ), 'term_' . $term_id );

		$request = array(
			'repeater_key' => 'field_rt_term_items',
			'preview_type' => 'taxonomy/category',
			'post_id'      => $term_id,
		);

		$this->login( 'editor' );

		$this->assertSame( array( 'Last row', '1 — Term row' ), $this->texts( $this->get_rows( $request ) ) );

		$this->login( 'subscriber' );

		$this->assertSame( array(), $this->get_rows( $request ), 'no manage_categories, no term rows' );
	}

	public function test_author_archive_preview_resolves_the_user_context_and_checks_edit_user(): void {
		$author_id = $this->create_user_id( array( 'role' => 'author' ) );

		update_field( 'field_rt_author_items', array( array( 'caption' => 'Author row' ) ), 'user_' . $author_id );

		// Pro nests author archives under the 'archive' category — 'archive/author', never a
		// bare 'author' prefix.
		$request = array(
			'repeater_key' => 'field_rt_author_items',
			'preview_type' => 'archive/author',
			'post_id'      => $author_id,
		);

		$this->login( 'administrator' );

		$this->assertSame( array( 'Last row', '1 — Author row' ), $this->texts( $this->get_rows( $request ) ) );

		// edit_user on ANOTHER user maps to edit_users — an editor does not have it.
		$this->login( 'editor' );

		$this->assertSame( array(), $this->get_rows( $request ) );
	}

	public function test_author_archive_without_a_preview_id_falls_back_to_the_current_user(): void {
		$user_id = $this->login( 'author' );

		update_field( 'field_rt_author_items', array( array( 'caption' => 'My row' ) ), 'user_' . $user_id );

		$request = array(
			'repeater_key' => 'field_rt_author_items',
			'preview_type' => 'archive/author',
			'post_id'      => 0,
		);

		// Editing yourself is always permitted, so the fallback resolves for any logged-in role.
		$this->assertSame( array( 'Last row', '1 — My row' ), $this->texts( $this->get_rows( $request ) ) );

		wp_set_current_user( 0 );

		$this->assertSame( array(), $this->get_rows( $request ), 'logged out, there is no current user to fall back to' );
	}

	public function test_post_type_archive_preview_resolves_the_newest_post_of_that_type(): void {
		$this->login( 'editor' );

		$request = array(
			'repeater_key' => 'field_rt_book_specs',
			'preview_type' => 'post_type_archive/rt_book',
		);

		$this->assertSame( array(), $this->get_rows( $request ), 'no posts of the type — nothing to preview against' );

		$older = $this->create_post_id(
			array(
				'post_type' => 'rt_book',
				'post_date' => '2020-01-01 00:00:00',
			)
		);
		$newer = $this->create_post_id(
			array(
				'post_type' => 'rt_book',
				'post_date' => '2024-01-01 00:00:00',
			)
		);

		update_field( 'field_rt_book_specs', array( array( 'caption' => 'Older book' ) ), $older );
		update_field( 'field_rt_book_specs', array( array( 'caption' => 'Newer book' ) ), $newer );

		// Mirrors the render-time fallback: the archive's default query starts at the newest post.
		$this->assertSame( array( 'Last row', '1 — Newer book' ), $this->texts( $this->get_rows( $request ) ) );
	}

	public function test_child_enumeration_reads_the_nested_repeater_of_the_addressed_parent_row(): void {
		$this->login( 'editor' );

		$page_id = $this->create_post_id( array( 'post_type' => 'page' ) );

		update_field(
			'field_rt_nested_parent_items',
			array(
				array(
					'title' => 'Parent One',
					'specs' => array(
						array( 'spec_name' => 'Weight' ),
						array( 'spec_name' => 'Width' ),
					),
				),
				array(
					'title' => 'Parent Two',
					'specs' => array( array( 'spec_name' => 'Material' ) ),
				),
			),
			$page_id
		);

		$request = array(
			'repeater_key' => 'field_rt_nested_parent_items',
			'post_id'      => $page_id,
			'child_path'   => 'specs',
		);

		$this->assertSame(
			array( 'Last row', '1 — Weight', '2 — Width' ),
			$this->texts( $this->get_rows( $request + array( 'parent_row_index' => 0 ) ) )
		);
		$this->assertSame(
			array( 'Last row', '1 — Material' ),
			$this->texts( $this->get_rows( $request + array( 'parent_row_index' => 1 ) ) )
		);

		// A child request is whitelisted against the PARENT's registered children.
		$this->assertSame(
			array(),
			$this->get_rows(
				array(
					'repeater_key'     => 'field_rt_nested_parent_items',
					'post_id'          => $page_id,
					'child_path'       => 'title',
					'parent_row_index' => 0,
				)
			),
			'a plain sub-field path is not a nested repeater'
		);
	}

	public function test_child_enumeration_against_flexible_content_fails_closed(): void {
		$this->login( 'editor' );

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

		// Flexible content never registers children — there is no second tier to address.
		$this->assertSame(
			array(),
			$this->get_rows(
				array(
					'repeater_key'     => 'field_rt_fx_sections',
					'post_id'          => $page_id,
					'child_path'       => 'heading',
					'parent_row_index' => 0,
				)
			)
		);

		// Without child_path the same entry enumerates normally, so the miss above is the
		// child guard, not a broken fixture.
		$this->assertSame(
			array( 'Last row', '1 — Hero: Hero one' ),
			$this->texts(
				$this->get_rows(
					array(
						'repeater_key' => 'field_rt_fx_sections',
						'post_id'      => $page_id,
					)
				)
			)
		);
	}

	public function test_loop_item_document_offers_the_current_row_sentinel(): void {
		$this->login( 'editor' );

		$page_id = $this->seed_page_with_rows();
		$request = array(
			'repeater_key' => 'field_rt_demo_items',
			'post_id'      => $page_id,
		);

		$this->assertSame(
			array( 'Current loop row', 'Last row', '1 — Alpha', '2 — Beta' ),
			$this->texts( $this->get_rows( $request + array( 'document_type' => 'loop-item' ) ) )
		);
		$this->assertSame(
			array( -2, -1, 0, 1 ),
			array_column( $this->get_rows( $request + array( 'document_type' => 'loop-item' ) ), 'id' )
		);

		$this->assertSame(
			array( 'Last row', '1 — Alpha', '2 — Beta' ),
			$this->texts( $this->get_rows( $request + array( 'document_type' => 'wp-page' ) ) ),
			'the sentinel is offered only where it resolves'
		);
	}

	public function test_loop_item_sentinel_is_offered_even_when_the_target_has_no_rows(): void {
		$this->login( 'editor' );

		// A loop card is designed independently of the preview target's data: the sentinel is
		// the primary binding there, so unlike "Last row" it survives an empty enumeration.
		$this->assertSame(
			array(
				array(
					'id'   => -2,
					'text' => 'Current loop row',
				),
			),
			$this->get_rows(
				array(
					'repeater_key'  => 'field_rt_demo_items',
					'post_id'       => $this->create_post_id( array( 'post_type' => 'page' ) ),
					'document_type' => 'loop-item',
				)
			)
		);
	}

	public function test_child_enumeration_is_not_offered_the_loop_sentinel(): void {
		$this->login( 'editor' );

		$page_id = $this->create_post_id( array( 'post_type' => 'page' ) );

		update_field(
			'field_rt_nested_parent_items',
			array(
				array(
					'title' => 'Parent One',
					'specs' => array( array( 'spec_name' => 'Weight' ) ),
				),
			),
			$page_id
		);

		// Frozen: child pickers have no -2, whatever the document type.
		$this->assertSame(
			array( -1, 0 ),
			array_column(
				$this->get_rows(
					array(
						'repeater_key'     => 'field_rt_nested_parent_items',
						'post_id'          => $page_id,
						'child_path'       => 'specs',
						'parent_row_index' => 0,
						'document_type'    => 'loop-item',
					)
				),
				'id'
			)
		);
	}
}
