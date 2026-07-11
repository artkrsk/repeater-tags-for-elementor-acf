<?php

namespace Arts\RepeaterTags\Tags;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Modules\DynamicTags\Module as TagsModule;

/**
 * Dynamic tag resolving a Gallery sub-field from an ACF repeater row. Feeds an Elementor
 * GALLERY control as [['id'=>int, 'url'=>string], ...]: consumers contract on lowercase
 * 'id' and re-resolve attachments from it ('url' is a best-effort extra newer render
 * paths read when present); ACF attachment arrays carry the id as both 'ID' and 'id'.
 */
class RepeaterGallery extends \Elementor\Core\DynamicTags\Data_Tag {

	use BaseRepeaterTag;

	public function get_name(): string {
		return 'arts-repeater-gallery';
	}

	public function get_title(): string {
		return esc_html__( 'Repeater Row: Gallery', 'repeater-tags-for-elementor-acf' );
	}

	/** @return array<int, string> */
	public function get_categories(): array {
		return array( TagsModule::GALLERY_CATEGORY );
	}

	/** @return array<int, string> */
	protected function get_accepted_sub_field_types(): array {
		return array( 'gallery' );
	}

	/**
	 * @param array<string, mixed> $options
	 * @return array<int, array{id: int, url: string}>
	 */
	public function get_value( array $options = array() ): array {
		$value = $this->resolve_cell();

		if ( ! is_array( $value ) ) {
			return array();
		}

		$items = array();

		foreach ( $value as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$id = 0;

			if ( isset( $item['ID'] ) && is_numeric( $item['ID'] ) ) {
				$id = (int) $item['ID'];
			} elseif ( isset( $item['id'] ) && is_numeric( $item['id'] ) ) {
				$id = (int) $item['id'];
			}

			$items[] = array(
				'id'  => $id,
				'url' => isset( $item['url'] ) && is_string( $item['url'] ) ? $item['url'] : '',
			);
		}

		return $items;
	}
}
