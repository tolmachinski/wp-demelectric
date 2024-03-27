<?php
/**
 * @dgwt_wcas_premium_only
 */
namespace DgoraWcas\Integrations\Plugins\AdvancedCustomFields;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Integration with Advanced Custom Fields
 *
 * Plugin URL: https://www.advancedcustomfields.com
 * Author: Elliot Condon
 */
class AdvancedCustomFields {
	public $fields = array();
	public $fieldsToRemove = array();

	public function init() {
		if ( ! defined( 'ACF_VERSION' ) ) {
			return;
		}
		if ( version_compare( ACF_VERSION, '5.8.0' ) < 0 ) {
			return;
		}

		add_filter( 'dgwt/wcas/indexer/searchable_custom_fields', array( $this, 'searchableCustomFields' ), 5 );
		add_filter( 'dgwt/wcas/indexer/source_query/select/custom_field', array( $this, 'queryForCustomField' ), 10, 3 );
		add_filter( 'dgwt/wcas/product/custom_field', array( $this, 'getCustomField' ), 10, 3 );
	}

	/**
	 * Add ACF fields (with labels) to the "Search in custom fields" list and remove unwanted and unused founded ACF fields
	 *
	 * @param array $fields
	 *
	 * @return array
	 */
	public function searchableCustomFields( $fields ) {
		$acfFields = $this->getFields();

		$inUseMetaKeys = DGWT_WCAS()->settings->getOption( 'search_in_custom_fields' );
		if ( empty( $inUseMetaKeys ) ) {
			$inUseMetaKeys = array();
		} else {
			$inUseMetaKeys = explode( ',', DGWT_WCAS()->settings->getOption( 'search_in_custom_fields' ) );
		}

		foreach ( $fields as $index => $field ) {
			// Remove any used in products meta fields that are unsupported ACF fields or it's repeater main field
			foreach ( $this->fieldsToRemove as $fieldToRemove ) {
				if ( preg_match( "/^" . $fieldToRemove . "$/", $field['key'] ) ) {
					unset( $fields[ $index ] );
					continue 2;
				}
			}

			// Remove fields matched as ACF field by regex and not stored in settings
			foreach ( $acfFields as $acfField ) {
				if (
					preg_match( "/^" . substr( $acfField['key'], 4 ) . "$/", $field['key'] ) &&
					! in_array( $field['key'], $inUseMetaKeys )
				) {
					unset( $fields[ $index ] );
				}
			}
		}

		return array_merge( $acfFields, $fields );
	}

	/**
	 * "Select" query for values of ACF fields
	 *
	 * @param $query
	 * @param $keyRaw
	 * @param $colName
	 *
	 * @return mixed|string|void
	 */
	public function queryForCustomField( $query, $keyRaw, $colName ) {
		global $wpdb;

		if ( substr( $keyRaw, 0, 4 ) !== 'acf/' ) {
			return $query;
		}

		$colName = str_replace( '-', '_', $colName );

		return $wpdb->prepare(
			", (SELECT GROUP_CONCAT(meta_value SEPARATOR ' ') FROM $wpdb->postmeta WHERE post_id = posts.ID AND `meta_key` REGEXP %s) AS $colName",
			'^' . substr( $keyRaw, 4 ) . '$'
		);
	}

	/**
	 * Get custom field value
	 *
	 * @param string|bool $value
	 * @param string $metaKey
	 * @param $productID
	 *
	 * @return string|null
	 */
	public function getCustomField( $value, $metaKey, $productID ) {
		global $wpdb;

		if ( substr( $metaKey, 0, 4 ) !== 'acf/' ) {
			return $value;
		}

		$query = $wpdb->prepare(
			"SELECT GROUP_CONCAT(meta_value SEPARATOR ' ') FROM $wpdb->postmeta WHERE post_id = %d AND `meta_key` REGEXP %s",
			$productID,
			'^' . substr( $metaKey, 4 ) . '$'
		);

		return $wpdb->get_var( $query );
	}

	/**
	 * We want to get fields in all languages
	 *
	 * @param \WP_Query $query
	 */
	public function disableWpml( $query ) {
		$query->set( 'suppress_filters', true );
		$query->set( 'lang', '' );
	}

	/**
	 * Get all ACF fields related to products
	 *
	 * @return array
	 */
	private function getFields() {
		add_action( 'pre_get_posts', array( $this, 'disableWpml' ) );
		$fieldGroups = acf_get_field_groups();
		remove_action( 'pre_get_posts', array( $this, 'disableWpml' ) );

		foreach ( $fieldGroups as $group ) {
			if ( ! $this->isGroupRelatedToProduct( $group ) ) {
				continue;
			}

			$fields = acf_get_fields( $group );
			if ( empty( $fields ) ) {
				continue;
			}

			$this->prepareFields( $fields, $group );
		}

		return $this->fields;
	}

	/**
	 * Check if group is related to product
	 *
	 * The group may have different conditions, but we are only checking the relation to the product post type.
	 *
	 * @param $group
	 *
	 * @return bool
	 */
	private function isGroupRelatedToProduct( $group ) {
		$result = false;

		if ( empty( $group ) || empty( $group['location'] ) ) {
			return $result;
		}

		foreach ( $group['location'] as $ruleGroup ) {
			if ( empty( $ruleGroup ) ) {
				continue;
			}

			foreach ( $ruleGroup as $rule ) {
				if ( $rule['operator'] === '==' && $rule['param'] === 'post_type' && $rule['value'] === 'product' ) {
					$result = true;
					break;
				}
			}
		}

		return $result;
	}

	/**
	 * Prepare list of ACF fields that can be indexed
	 *
	 * @param array $fields
	 * @param array $group
	 * @param string $label
	 * @param string $key
	 */
	private function prepareFields( $fields, $group, $label = '', $key = '' ) {
		if ( empty( $label ) ) {
			$label = '[ACF] ' . $group['title'];
		}
		if ( empty( $key ) ) {
			$key = 'acf/';
		}

		foreach ( $fields as $field ) {
			if ( ! in_array( $field['type'], $this->getAllowedFieldTypes() ) ) {
				// Add not allowed field to list of fields that will be removed
				if ( $key === 'acf/' ) {
					$this->fieldsToRemove[] = $field['name'];
				}

				continue;
			}

			// Process other than default types of fields
			$processed = apply_filters( 'dgwt/wcas/integrations/acf/field_processed', false, $field, $this, $label, $key );
			if ( $processed ) {
				continue;
			}

			// Repeater
			if ( $field['type'] === 'repeater' ) {
				// Main repeater field containing the number of rows should be removed
				$this->fieldsToRemove[] = substr( $key . $field['name'], 4 );

				if ( ! empty( $field['sub_fields'] ) ) {
					$newLabel = $label . ' → ' . $field['label'];
					$newKey   = $key . $field['name'] . '_[0-9]+_';
					$this->prepareFields( $field['sub_fields'], $group, $newLabel, $newKey );
				}
			} elseif ( $field['type'] === 'flexible_content' ) {
				// Flexible content
				if ( empty( $field['layouts'] ) ) {
					continue;
				}

				foreach ( $field['layouts'] as $layout ) {
					if ( empty( $layout['sub_fields'] ) ) {
						continue;
					}
					$newLabel = $label . ' → ' . $field['label'] . ' → ' . $layout['label'];
					$newKey   = $key . $field['name'] . '_[0-9]+_';
					$this->prepareFields( $layout['sub_fields'], $layout, $newLabel, $newKey );
				}
			} else {
				// Regular field (text, etc.)
				$newLabel       = $label . ' → ' . $field['label'];
				$newKey         = $key . $field['name'];
				$this->fields[] = array(
					'label' => $newLabel,
					'key'   => $newKey,
				);
			}
		}
	}

	/**
	 * Get allowed field types
	 *
	 * @return string[]
	 */
	private function getAllowedFieldTypes() {
		$types = array( 'text', 'textarea', 'wysiwyg', 'repeater', 'flexible_content' );

		return apply_filters( 'dgwt/wcas/integrations/acf/allowed_field_types', $types );
	}
}
