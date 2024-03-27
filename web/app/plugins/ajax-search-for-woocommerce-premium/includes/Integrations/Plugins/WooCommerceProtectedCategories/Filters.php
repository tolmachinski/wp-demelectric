<?php
/**
 * @dgwt_wcas_premium_only
 */

namespace DgoraWcas\Integrations\Plugins\WooCommerceProtectedCategories;

use DgoraWcas\Engines\TNTSearchMySQL\Config;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Filters {
	public $plugin_names = array(
		'woocommerce-protected-categories/woocommerce-protected-categories.php',
	);

	private $hiddenCategoryIds = array();

	public function init() {
		foreach ( $this->plugin_names as $plugin_name ) {
			if ( Config::isPluginActive( $plugin_name ) ) {
				$this->setHiddenCategories();
				$this->excludeProductsFromHiddenCategories();

				break;
			}
		}
	}

	/**
	 * Set hidden category IDs from PHP Session
	 *
	 * @return void
	 */
	private function setHiddenCategories() {
		if ( ! session_id() ) {
			session_start();
		}

		if ( ! empty( $_SESSION['dgwt-wcas-woocommerce-protected-categories'] ) ) {
			$this->hiddenCategoryIds = $_SESSION['dgwt-wcas-woocommerce-protected-categories'];
		}
	}

	/**
	 * Exclude products from hidden categories and hidden categories itself
	 *
	 * @return void
	 */
	private function excludeProductsFromHiddenCategories() {
		if ( empty( $this->hiddenCategoryIds ) ) {
			return;
		}

		// Exclude products from hidden categories.
		add_filter( 'dgwt/wcas/tnt/search_results/products', function ( $results ) {
			if ( empty( $results ) ) {
				return $results;
			}

			foreach ( $results as $index => $result ) {
				if ( ! isset( $result->meta['cat_ids'] ) || ! is_array( $result->meta['cat_ids'] ) ) {
					continue;
				}

				if ( count( array_intersect( $result->meta['cat_ids'], $this->hiddenCategoryIds ) ) > 0 ) {
					unset( $results[ $index ] );
				}
			}

			return array_values( $results );
		} );

		// Exclude hidden categories.
		add_filter( 'dgwt/wcas/tnt/search_results/taxonomies', function ( $results ) {
			if ( empty( $results ) ) {
				return $results;
			}

			foreach ( $results as $index => $result ) {
				if ( $result['taxonomy'] === 'product_cat' && in_array( $result['term_id'], $this->hiddenCategoryIds ) ) {
					unset( $results[ $index ] );
				}
			}

			return array_values( $results );
		} );
	}
}
