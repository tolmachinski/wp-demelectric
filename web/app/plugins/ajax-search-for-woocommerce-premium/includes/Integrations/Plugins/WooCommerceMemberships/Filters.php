<?php
/**
 * @dgwt_wcas_premium_only
 */

namespace DgoraWcas\Integrations\Plugins\WooCommerceMemberships;

use DgoraWcas\Engines\TNTSearchMySQL\Config;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Filters {
	public $plugin_names = array(
		'woocommerce-memberships/woocommerce-memberships.php',
	);

	private $visible_products;

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

		if ( isset( $_SESSION['dgwt-wcas-woocommerce-memberships-visible-products'] ) ) {
			$this->visible_products = $_SESSION['dgwt-wcas-woocommerce-memberships-visible-products'];
		}
	}

	/**
	 * Include only products returned by WooCommerce Memberships
	 */
	private function excludeHidenProducts() {
		add_filter( 'dgwt/wcas/tnt/search_results/ids', function ( $ids ) {
			if ( is_array( $this->visible_products ) ) {
				$ids = array_intersect( $ids, $this->visible_products );
			}

			return $ids;
		} );
	}
}
