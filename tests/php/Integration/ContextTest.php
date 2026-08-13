<?php

namespace Arts\RepeaterTags\Tests\Integration;

use Arts\RepeaterTags\Services\Context;
use ElementorPro\Modules\LoopBuilder\Documents\Loop;

/**
 * Characterizes the read-target ladder: options store → loop-item document →
 * queried term/user → get_the_ID(). Runs with Elementor + PRO Elements loaded;
 * the loop-item rung uses the real Pro document type, not a stand-in.
 */
class ContextTest extends TestCase {

	private Context $context;

	public function set_up(): void {
		parent::set_up();

		$this->context = new Context();
	}

	/** Queries a category archive with one assigned post; returns the term id. */
	private function go_to_category_archive(): int {
		$term_id = $this->create_category_id();

		$this->create_post_id( array( 'post_category' => array( $term_id ) ) );
		$this->go_to( '/?cat=' . $term_id );

		return $term_id;
	}

	public function test_options_scoped_key_wins_over_any_query(): void {
		$this->go_to_category_archive();

		$this->assertSame( 'options', $this->context->resolve_post_id( 'field_rt_global_items' ) );
	}

	public function test_singular_resolves_to_the_post_id(): void {
		$page_id   = $this->create_post_id( array( 'post_type' => 'page' ) );
		$permalink = get_permalink( $page_id );

		$this->assertIsString( $permalink );
		$this->go_to( $permalink );

		$this->assertSame( $page_id, $this->context->resolve_post_id( 'field_rt_demo_items' ) );
	}

	public function test_term_archive_resolves_to_term_context(): void {
		$term_id = $this->go_to_category_archive();

		$this->assertSame( 'term_' . $term_id, $this->context->resolve_post_id( 'field_rt_term_items' ) );
	}

	public function test_unknown_key_still_resolves_the_query_context(): void {
		$term_id = $this->go_to_category_archive();

		$this->assertSame( 'term_' . $term_id, $this->context->resolve_post_id( 'field_rt_unknown' ) );
	}

	public function test_author_archive_resolves_to_user_context(): void {
		$user_id = $this->create_user_id( array( 'role' => 'author' ) );

		$this->create_post_id( array( 'post_author' => $user_id ) );
		$this->go_to( '/?author=' . $user_id );

		$this->assertSame( 'user_' . $user_id, $this->context->resolve_post_id( 'field_rt_author_items' ) );
	}

	public function test_editor_ajax_analogue_falls_back_to_the_global_post(): void {
		// ajax_render_tags' switch_to_post() sets the global post but never the
		// queried object — rung 2 self-skips and rung 3 reads get_the_ID().
		$page_id = $this->create_post_id( array( 'post_type' => 'page' ) );

		$this->go_to( '/' );
		$GLOBALS['post'] = get_post( $page_id );

		$this->assertSame( $page_id, $this->context->resolve_post_id( 'field_rt_demo_items' ) );
	}

	public function test_loop_item_document_beats_the_archive_context(): void {
		$this->go_to_category_archive();

		// Loop Grid iterates the_post(), so the global post IS the current card.
		$card_id = $this->create_post_id( array( 'post_type' => 'page' ) );

		$GLOBALS['post'] = get_post( $card_id );

		$template_id = $this->create_post_id( array( 'post_type' => 'elementor_library' ) );
		$documents   = \Elementor\Plugin::$instance->documents;

		$documents->switch_to_document( new Loop( array( 'post_id' => $template_id ) ) );

		try {
			$this->assertSame(
				$card_id,
				$this->context->resolve_post_id( 'field_rt_term_items' ),
				'inside a loop-item render the ladder must return the card post, not the archive term'
			);
		} finally {
			$documents->restore_document();
		}
	}
}
