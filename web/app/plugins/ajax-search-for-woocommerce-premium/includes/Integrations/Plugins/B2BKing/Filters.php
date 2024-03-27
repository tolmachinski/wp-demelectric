<?php
/**
 * @dgwt_wcas_premium_only
 */

namespace DgoraWcas\Integrations\Plugins\B2BKing;

use DgoraWcas\Engines\TNTSearchMySQL\Config;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Filters {
	public $plugin_names = array(
		'b2bking/b2bking.php',
	);

	private $visible_products = null;

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

		if ( isset( $_SESSION['dgwt-wcas-b2bking-visible-products'] ) && is_array( $_SESSION['dgwt-wcas-b2bking-visible-products'] ) ) {
			$this->visible_products = $_SESSION['dgwt-wcas-b2bking-visible-products'];
		}
	}

	/**
	 * Include only products returned by B2BKing
	 */
	private function excludeHidenProducts() {
		add_filter( 'dgwt/wcas/tnt/search_results/ids', function ( $ids ) {
			// Filter products only if list of visible IDs have been passed via $_SESSION
			if ( is_array( $this->visible_products ) ) {
				$ids = array_intersect( $ids, $this->visible_products );
			}

			return $ids;
		} );
	}
}
