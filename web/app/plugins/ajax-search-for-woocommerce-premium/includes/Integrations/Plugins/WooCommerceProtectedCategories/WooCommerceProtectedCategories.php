<?php
/**
 * @dgwt_wcas_premium_only
 */

namespace DgoraWcas\Integrations\Plugins\WooCommerceProtectedCategories;

use Barn2\Plugin\WC_Protected_Categories\Util;
use DgoraWcas\Helpers;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Integration with WooCommerce Protected Categories
 *
 * Plugin URL: https://barn2.co.uk/wordpress-plugins/woocommerce-protected-categories/
 * Author: Barn2 Plugins
 */
class WooCommerceProtectedCategories {
	public function init() {
		if ( ! dgoraAsfwFs()->is_premium() ) {
			return;
		}
		if ( ! defined( '\Barn2\Plugin\WC_Protected_Categories\PLUGIN_VERSION' ) ) {
			return;
		}
		if ( version_compare( \Barn2\Plugin\WC_Protected_Categories\PLUGIN_VERSION, '2.5.4' ) < 0 ) {
			return;
		}

		add_filter( 'dgwt/wcas/tnt/indexer/readable/product/data', array( $this, 'addProductCategories' ), 10, 3 );

		add_action( 'init', array( $this, 'storeInSessionHiddenCategories' ) );

		add_filter( 'dgwt/wcas/troubleshooting/renamed_plugins', array( $this, 'getFolderRenameInfo' ) );
	}

	/**
	 * Add category IDs to index data
	 */
	public function addProductCategories( $data, $product_id, $product ) {
		$data['meta']['cat_ids'] = $product->getWooObject()->get_category_ids();

		return $data;
	}

	/**
	 * Store hidden category IDs in session for current user
	 */
	public function storeInSessionHiddenCategories() {
		$hiddenCategoryIds = array();
		$showProtected     = Util::showing_protected_categories();

		// Get all the product categories, and check which are hidden.
		foreach ( Util::to_category_visibilities( Util::get_product_categories() ) as $category ) {
			if ( $category->is_private() || ( ! $showProtected && $category->is_protected() ) ) {
				$hiddenCategoryIds[] = $category->get_term_id();
			}
		}

		foreach ( $hiddenCategoryIds as $categoryId ) {
			$children = get_term_children( $categoryId, 'product_cat' );
			if ( is_array( $children ) ) {
				$hiddenCategoryIds = array_merge( $hiddenCategoryIds, $children );
			}
		}

		$newSession = false;
		if ( ! session_id() ) {
			session_start();
			$newSession = true;
		}

		$_SESSION['dgwt-wcas-woocommerce-protected-categories'] = array_unique( $hiddenCategoryIds );

		if ( $newSession && function_exists( 'session_status' ) && PHP_SESSION_ACTIVE === session_status() ) {
			session_write_close();
		}
	}

	/**
	 * Get info about renamed plugin folder
	 *
	 * @param array $plugins
	 *
	 * @return array
	 */
	public function getFolderRenameInfo( $plugins ) {
		$filters = new Filters();

		$result = Helpers::getFolderRenameInfo__premium_only( 'WooCommerce Protected Categories', $filters->plugin_names );
		if ( $result ) {
			$plugins[] = $result;
		}

		return $plugins;
	}
}
