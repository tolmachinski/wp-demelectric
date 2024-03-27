<?php

namespace DgoraWcas\Integrations\Plugins\MemberPress;

use DgoraWcas\Engines\TNTSearchMySQL\Config;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Filters {
	public $plugin_names = array(
		'memberpress/memberpress.php',
	);

	private $locked_products = array();

	public function init() {
		foreach ( $this->plugin_names as $plugin_name ) {
			if ( Config::isPluginActive( $plugin_name ) ) {
				$this->setLockedProducts();
				$this->excludeLockedProducts();

				break;
			}
		}
	}

	/**
	 * Set locked products from PHP Session
	 *
	 * @return void
	 */
	private function setLockedProducts() {
		if ( ! session_id() ) {
			session_start();
		}

		if ( ! empty( $_SESSION['dgwt-wcas-woocommerce-memberpress-locked-products'] ) ) {
			$this->locked_products = $_SESSION['dgwt-wcas-woocommerce-memberpress-locked-products'];
		}
	}

	/**
	 * Exclude products returned by MemberPress
	 */
	private function excludeLockedProducts() {
		add_filter( 'dgwt/wcas/tnt/search_results/ids', function ( $ids ) {
			if ( ! empty( $this->locked_products ) && is_array( $this->locked_products ) ) {
				$ids = array_diff( $ids, $this->locked_products );
			}

			return $ids;
		} );
	}
}
