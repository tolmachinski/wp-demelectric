<?php
/**
 * @dgwt_wcas_premium_only
 */

namespace DgoraWcas\Integrations\Plugins\ProductSpecifications;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Integration with "Product Specifications for Woocommerce"
 *
 * Plugin URL: https://wordpress.org/plugins/product-specifications/
 * Author: Am!n A.Rezapour
 */
class ProductSpecifications {
	public function init() {
		if ( ! defined( 'DWSPECS_VERSION' ) ) {
			return;
		}
		if ( version_compare( DWSPECS_VERSION, '0.5.2' ) < 0 ) {
			return;
		}

		add_filter( 'dgwt/wcas/indexer/searchable_custom_fields', array( $this, 'searchableCustomFields' ), 5 );
		add_filter( 'dgwt/wcas/indexer/items_row', array( $this, 'processSpecificationsData' ) );
	}

	/**
	 * Add new item to the "Search in custom fields" list
	 *
	 * @param array $fields
	 *
	 * @return array
	 */
	public function searchableCustomFields( $fields ) {
		return array_merge( array(
			array(
				'label' => '[Product Specifications for WooCommerce] Specifications content',
				'key'   => '_dwps_specification_table'
			)
		), $fields );
	}

	/**
	 * Process item custom field and extract specifications contents
	 *
	 * @param $row
	 *
	 * @return mixed
	 */
	public function processSpecificationsData( $row ) {
		if ( ! empty( $row['cf__dwps_specification_table'] ) ) {
			$data = maybe_unserialize( $row['cf__dwps_specification_table'] );
			if ( ! is_array( $data ) ) {
				$row['cf__dwps_specification_table'] = null;

				return $row;
			}

			$values = array();

			foreach ( $data as $tables ) {
				if ( isset( $tables['attributes'] ) && is_array( $tables['attributes'] ) ) {
					foreach ( $tables['attributes'] as $attribute ) {
						if ( isset( $attribute['value'] ) ) {
							$values[] = $attribute['value'];
						}
					}
				}
			}

			$row['cf__dwps_specification_table'] = join( ' | ', $values );
		}

		return $row;
	}
}
