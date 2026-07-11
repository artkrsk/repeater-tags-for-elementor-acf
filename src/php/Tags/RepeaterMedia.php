<?php

namespace Arts\RepeaterTags\Tags;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Modules\DynamicTags\Module as TagsModule;

/**
 * Dynamic tag resolving a media sub-field (image or file) from an ACF repeater row.
 * Returns ['id'=>int, 'url'=>string] — the shape both the IMAGE and MEDIA categories
 * expect. ACF Image and File fields in array format both carry `id` and `url` keys.
 */
class RepeaterMedia extends \Elementor\Core\DynamicTags\Data_Tag {

	use BaseRepeaterTag;

	public function get_name(): string {
		return 'arts-repeater-media';
	}

	public function get_title(): string {
		return esc_html__( 'Repeater Row: Media', 'repeater-tags-for-elementor-acf' );
	}

	/** @return array<int, string> */
	public function get_categories(): array {
		return array( TagsModule::IMAGE_CATEGORY, TagsModule::MEDIA_CATEGORY );
	}

	/** @return array<int, string> */
	protected function get_accepted_sub_field_types(): array {
		return array( 'image', 'file' );
	}

	/**
	 * Handles all three ACF return formats for image/file sub-fields:
	 * array ({id,url}), id (int attachment ID), url (string).
	 *
	 * @param array<string, mixed> $options
	 * @return array{id: int, url: string}
	 */
	public function get_value( array $options = array() ): array {
		$value = $this->resolve_cell();

		if ( is_array( $value ) ) {
			return array(
				'id'  => isset( $value['id'] ) && is_numeric( $value['id'] ) ? (int) $value['id'] : 0,
				'url' => isset( $value['url'] ) && is_string( $value['url'] ) ? $value['url'] : '',
			);
		}

		if ( is_numeric( $value ) && (int) $value > 0 ) {
			return array(
				'id'  => (int) $value,
				'url' => (string) wp_get_attachment_url( (int) $value ),
			);
		}

		if ( is_string( $value ) && '' !== $value ) {
			return array(
				'id'  => 0,
				'url' => $value,
			);
		}

		return array(
			'id'  => 0,
			'url' => '',
		);
	}
}
