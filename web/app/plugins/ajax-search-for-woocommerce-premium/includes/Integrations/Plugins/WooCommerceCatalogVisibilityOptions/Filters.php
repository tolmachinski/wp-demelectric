<?php
/**
 * @dgwt_wcas_premium_only
 */
namespace DgoraWcas\Integrations\Plugins\WooCommerceCatalogVisibilityOptions;

use DgoraWcas\Engines\TNTSearchMySQL\Config;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Filters {
	public $plugin_names = array(
		'woocommerce-catalog-visibility-options/woocommerce-catalog-visibility-options.php',
	);

	private $disallowed_products = array();

	public function init() {

		foreach ( $this->plugin_names as $plugin_name ) {

			if ( Config::isPluginActive( $plugin_name ) ) {

				$this->setVisibleProducts();
				$this->excludeHiddenProducts();

				break;
			}

		}
	}

	/**
	 * Set disallowed products from PHP Session
	 *
	 * @return void
	 */
	private function setVisibleProducts() {
		if ( ! session_id() ) {
			session_start();
		}

		if ( ! empty( $_SESSION['dgwt-wcas-wccvo-disallowed-products'] ) ) {
			$this->disallowed_products = $_SESSION['dgwt-wcas-wccvo-disallowed-products'];
		}
	}

	/**
	 * Exclude products returned by WooCommerce Catalog Visibility Options
	 */
	private function excludeHiddenProducts() {
		add_filter( 'dgwt/wcas/tnt/search_results/ids', function ( $ids ) {
			if ( ! empty( $this->disallowed_products ) && is_array( $this->disallowed_products ) ) {
				$ids = array_diff( $ids, $this->disallowed_products );
			}

			return $ids;
		} );
	}
}
