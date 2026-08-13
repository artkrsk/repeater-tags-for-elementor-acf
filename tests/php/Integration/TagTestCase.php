<?php

namespace Arts\RepeaterTags\Tests\Integration;

use Elementor\Core\DynamicTags\Base_Tag;

/**
 * Base for the tag suites. Tags are constructed directly — the same way Elementor's own
 * Dynamic_Tags\Manager::create_tag() does it — so no editor, document or tag-manager
 * registration is involved. Two mechanics constrain how:
 *
 * - `id` is load-bearing. Controls_Stack::init() reads $data['id'] off the RAW array (not
 *   the merged defaults), so omitting it raises "Undefined array key", which PHPUnit 9
 *   turns into a failure. An entirely empty $data is worse: init() is skipped outright and
 *   the first get_settings() fatals on a null settings array.
 * - Elementor caches each tag's control stack process-wide, keyed by get_unique_name()
 *   ('tag-' . get_name()) — so register_controls() runs ONCE PER CLASS PER RUN, not per
 *   instance. Harmless here (the fixture schema is static for the whole run), but it means
 *   no test may assert registration against a mutated schema without first calling
 *   controls_manager->delete_stack(). Don't write one that does.
 *
 * Reading values needs no such care: get_settings() does NOT apply control conditions (only
 * get_settings_for_display() does), and settings keys with no registered control pass
 * through untouched — which is what lets the unknown-key + rows-filter path be driven here.
 */
abstract class TagTestCase extends TestCase {

	/**
	 * @param class-string<Base_Tag> $tag_class
	 * @param array<string, mixed>   $settings Frozen setting keys: repeater_field, row_index,
	 *                                         sub_field_{$key}, child_row_index_{$child_key},
	 *                                         child_sub_field_{$child_key}.
	 */
	protected function make_tag( string $tag_class, array $settings ): Base_Tag {
		return new $tag_class(
			array(
				'id'       => 'rt-test-tag',
				'settings' => $settings,
			)
		);
	}

	/**
	 * The tag's own output. Tag::WRAPPED_TAG is false, so render-based tags return exactly
	 * what they echoed — no <span> wrapper — and the before/after/fallback affixes stay
	 * inert while those settings are unset. Data_Tag::get_content() just forwards to
	 * get_value(), so this returns its typed value (string, or the media/gallery arrays).
	 *
	 * @param array<string, mixed> $settings
	 * @param class-string<Base_Tag> $tag_class
	 * @return mixed
	 */
	protected function tag_content( string $tag_class, array $settings ) {
		return $this->make_tag( $tag_class, $settings )->get_content();
	}

	/**
	 * An attachment backed by a real uploads path, so wp_get_attachment_url() and ACF's
	 * acf_get_attachment() both resolve.
	 */
	protected function create_attachment_id( string $file = 'rt-test.jpg', string $mime = 'image/jpeg' ): int {
		$attachment_id = self::factory()->attachment->create_object(
			array(
				'file'           => $file,
				'post_mime_type' => $mime,
			)
		);

		self::assertIsInt( $attachment_id );

		return $attachment_id;
	}
}
