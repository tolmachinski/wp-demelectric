<?php
/**
 * @dgwt_wcas_premium_only
 */
namespace DgoraWcas\Integrations\Plugins\WooCommerceProductsVisibility;

use DgoraWcas\Engines\TNTSearchMySQL\Config;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Filters {
	public $plugin_names = array(
		'woocommerce-products-visibility/woocommerce-products-visibility.php',
		'woocommerce-visibility/woocommerce-visibility.php' // since v4.x
	);

	private $visible_products = array();

	public function init() {

		foreach ( $this->plugin_names as $plugin_name ) {

			if ( Config::isPluginActive( $plugin_name ) ) {

				$this->setVisibleProducts();
				$this->excludeHidenProducts();

				break;
			}

		}
	}

	/**
	 * Set visible products from PHP Session
	 *
	 * @return void
	 */
	private function setVisibleProducts() {
		if ( ! session_id() ) {
			session_start();
		}

		if ( ! empty( $_SESSION['dgwt-wcas-wcpv-visible-products'] ) ) {
			$this->visible_products = $_SESSION['dgwt-wcas-wcpv-visible-products'];
		}
	}

	/**
	 * Include only products returned by Products Visibility plugin
	 */
	private function excludeHidenProducts() {
		add_filter( 'dgwt/wcas/tnt/search_results/ids', function ( $ids ) {
			if ( !empty($this->visible_products) && is_array( $this->visible_products ) ) {
				$ids = array_intersect( $ids, $this->visible_products );
			}

			return $ids;
		} );
	}
}
