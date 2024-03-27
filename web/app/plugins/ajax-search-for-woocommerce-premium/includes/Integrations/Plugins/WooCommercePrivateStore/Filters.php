<?php
/**
 * @dgwt_wcas_premium_only
 */
namespace DgoraWcas\Integrations\Plugins\WooCommercePrivateStore;

use DgoraWcas\Engines\TNTSearchMySQL\Config;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Filters {
	public $plugin_names = array(
		'woocommerce-private-store/woocommerce-private-store.php',
	);

	private $storeLocked = true;

	public function init() {
		foreach ( $this->plugin_names as $plugin_name ) {
			if ( Config::isPluginActive( $plugin_name ) ) {
				$this->getDataFromSession();
				if ( $this->storeLocked ) {
					$this->hideStoreResults();
				}

				break;
			}
		}
	}

	/**
	 * Get data from PHP session
	 *
	 * @return void
	 */
	private function getDataFromSession() {
		if ( ! session_id() ) {
			session_start();
		}

		if ( isset( $_SESSION['dgwt-wcas-wcps-store-locked'] ) && $_SESSION['dgwt-wcas-wcps-store-locked'] === false ) {
			$this->storeLocked = false;
		}
	}

	/**
	 * Hide results related with store
	 */
	private function hideStoreResults() {
		add_filter( 'dgwt/wcas/tnt/search_results/output', function ( $output ) {
			// Remove all suggestions related with store
			if ( ! empty( $output['suggestions'] ) ) {
				foreach ( $output['suggestions'] as $index => $suggestion ) {
					if ( $suggestion['type'] === 'post' ) {
						continue;
					} else if ( $suggestion['type'] === 'headline' && ( $suggestion['value'] === 'post' || $suggestion['value'] === 'page' ) ) {
						continue;
					}

					unset( $output['suggestions'][ $index ] );
				}

				$output['suggestions'] = array_values( $output['suggestions'] );

				if ( empty( $output['suggestions'] ) ) {
					$output['suggestions'] = array(
						array(
							'value' => '',
							'type'  => 'no-results'
						)
					);
				}
			}

			$output['total'] = 0;

			return $output;
		} );
	}
}
